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

// Lógica reemplazada por base de datos: ver bd.sql (Trigger/SP)
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

// Tras cambiar a "listo" el trigger descuenta insumos. Ahora marcamos que
// ya se descargaron si aún no estaba registrado.
if ($nuevo_estado === 'listo') {
    $check = $conn->prepare('SELECT insumos_descargados FROM venta_detalles WHERE id = ?');
    if (!$check) {
        error('Error al verificar insumos: ' . $conn->error);
    }
    $check->bind_param('i', $detalle_id);
    $check->execute();
    $res = $check->get_result();
    if (!$res || $res->num_rows === 0) {
        $check->close();
        error('Detalle no encontrado');
    }
    $row = $res->fetch_assoc();
    $check->close();

    if ((int)$row['insumos_descargados'] === 0) {
        $mk = $conn->prepare('UPDATE venta_detalles SET insumos_descargados = 1 WHERE id = ?');
        if (!$mk) {
            error('Error al preparar actualización de insumos: ' . $conn->error);
        }
        $mk->bind_param('i', $detalle_id);
        if (!$mk->execute()) {
            $mk->close();
            error('Error al marcar insumos: ' . $mk->error);
        }
        $mk->close();
    } else {
        error('Los insumos ya se habían descargado');
    }

    $log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
    if ($log) {
        $usuario_id = $input['usuario_id'] ?? null;
        $mod        = 'cocina';
        $accion     = 'Producto marcado como listo';
        $log->bind_param('issi', $usuario_id, $mod, $accion, $detalle_id);
        $log->execute();
        $log->close();
    }
}

success(true);
?>
