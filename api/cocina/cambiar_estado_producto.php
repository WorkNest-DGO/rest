<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['detalle_id'], $input['nuevo_estado'])) {
    error('Datos inválidos');
}

$detalle_id = (int)$input['detalle_id'];
$nuevo_estado = $input['nuevo_estado'];
$permitidos = ['pendiente', 'en preparación', 'listo', 'entregado'];
if (!in_array($nuevo_estado, $permitidos, true)) {
    error('Estado no válido');
}

$stmt = $conn->prepare('SELECT estatus_preparacion, producto_id, cantidad, insumos_descargados FROM venta_detalles WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $detalle_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    $stmt->close();
    error('Detalle no encontrado');
}
$detalle = $result->fetch_assoc();
$actual   = $detalle['estatus_preparacion'];
$stmt->close();

if (in_array($actual, ['listo', 'entregado'], true)) {
    error('No se puede modificar este producto');
}

$transiciones = [
    'pendiente'      => 'en preparación',
    'en preparación' => 'listo'
];

if (!isset($transiciones[$actual]) || $transiciones[$actual] !== $nuevo_estado) {
    error('Transición no permitida');
}

$warnings = [];
$descargados = (int) $detalle['insumos_descargados'];

if ($nuevo_estado === 'listo' && $descargados === 0) {
    $conn->begin_transaction();
    try {
        $producto_id = (int) $detalle['producto_id'];
        $cantidad    = (int) $detalle['cantidad'];

        $receta = $conn->prepare('SELECT insumo_id, cantidad FROM recetas WHERE producto_id = ?');
        if (!$receta) {
            throw new Exception('Error al preparar receta: ' . $conn->error);
        }
        $receta->bind_param('i', $producto_id);
        if (!$receta->execute()) {
            $receta->close();
            throw new Exception('Error al ejecutar receta: ' . $receta->error);
        }
        $res = $receta->get_result();
        while ($row = $res->fetch_assoc()) {
            $insumo_id = (int) $row['insumo_id'];
            $total     = (float) $row['cantidad'] * $cantidad;

            $check = $conn->prepare('SELECT existencia FROM insumos WHERE id = ?');
            if ($check) {
                $check->bind_param('i', $insumo_id);
                $check->execute();
                $ex = $check->get_result()->fetch_assoc();
                if ($ex && (float) $ex['existencia'] < $total) {
                    $warnings[] = "Insumo {$insumo_id} insuficiente";
                }
                $check->close();
            }

            $updIn = $conn->prepare('UPDATE insumos SET existencia = existencia - ? WHERE id = ?');
            if (!$updIn) {
                $receta->close();
                throw new Exception('Error al preparar descuento: ' . $conn->error);
            }
            $updIn->bind_param('di', $total, $insumo_id);
            if (!$updIn->execute()) {
                $updIn->close();
                $receta->close();
                throw new Exception('Error al descontar insumo: ' . $updIn->error);
            }
            $updIn->close();
        }
        $receta->close();

        $upd = $conn->prepare('UPDATE venta_detalles SET estatus_preparacion = ?, insumos_descargados = 1 WHERE id = ?');
        if (!$upd) {
            throw new Exception('Error al preparar actualización: ' . $conn->error);
        }
        $upd->bind_param('si', $nuevo_estado, $detalle_id);
        if (!$upd->execute()) {
            $upd->close();
            throw new Exception('Error al actualizar: ' . $upd->error);
        }
        $upd->close();

        $conn->commit();
        success(['warning' => $warnings]);
    } catch (Exception $e) {
        $conn->rollback();
        error($e->getMessage());
    }
} else {
    $upd = $conn->prepare('UPDATE venta_detalles SET estatus_preparacion = ? WHERE id = ?');
    if (!$upd) {
        error('Error al preparar actualización: ' . $conn->error);
    }
    $upd->bind_param('si', $nuevo_estado, $detalle_id);
    if (!$upd->execute()) {
        $upd->close();
        error('Error al actualizar: ' . $upd->error);
    }
    $upd->close();

    success(true);
}
?>
