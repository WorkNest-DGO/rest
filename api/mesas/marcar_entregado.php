<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/inventario.php';

// Toda la lógica de descuento de insumos se maneja en este archivo,
// sin apoyarse en triggers ni procedimientos almacenados.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['detalle_id'])) {
    error('Datos inválidos');
}

$detalle_id = (int)$input['detalle_id'];

$info = $conn->prepare('SELECT producto_id, cantidad, estatus_preparacion, insumos_descargados FROM venta_detalles WHERE id = ?');
if (!$info) {
    error('Error al preparar consulta: ' . $conn->error);
}
$info->bind_param('i', $detalle_id);
if (!$info->execute()) {
    $info->close();
    error('Error al obtener detalle: ' . $info->error);
}
$res      = $info->get_result();
$detalle  = $res->fetch_assoc();
$info->close();

if (!$detalle) {
    error('Detalle no encontrado');
}

$upd = $conn->prepare("UPDATE venta_detalles SET estatus_preparacion = 'entregado' WHERE id = ?");
if (!$upd) {
    error('Error al preparar actualización: ' . $conn->error);
}
$upd->bind_param('i', $detalle_id);
if (!$upd->execute()) {
    $upd->close();
    error('Error al marcar entregado: ' . $upd->error);
}
$upd->close();

// Descontar insumos si aún no se ha hecho
if ((int)$detalle['insumos_descargados'] === 0) {
    $productoId    = (int)$detalle['producto_id'];
    $cantidadVenta = (int)$detalle['cantidad'];
    $rec = $conn->prepare('SELECT insumo_id, cantidad FROM recetas WHERE producto_id = ?');
    if ($rec) {
        $rec->bind_param('i', $productoId);
        if ($rec->execute()) {
            $res = $rec->get_result();
            while ($row = $res->fetch_assoc()) {
                $insumoId  = (int)$row['insumo_id'];
                $descuento = $cantidadVenta * (float)$row['cantidad'];
                $up = $conn->prepare('UPDATE insumos SET existencia = existencia - ? WHERE id = ?');
                if ($up) {
                    $up->bind_param('di', $descuento, $insumoId);
                    $up->execute();
                    $up->close();
                }
            }
        }
        $rec->close();
    }

    $mark = $conn->prepare('UPDATE venta_detalles SET insumos_descargados = 1 WHERE id = ?');
    if ($mark) {
        $mark->bind_param('i', $detalle_id);
        $mark->execute();
        $mark->close();
    }
} else {
    $mark = $conn->prepare('UPDATE venta_detalles SET insumos_descargados = 1 WHERE id = ? AND insumos_descargados = 0');
    if ($mark) {
        $mark->bind_param('i', $detalle_id);
        $mark->execute();
        $mark->close();
    }
}

// Descontar existencia del producto si no estaba listo previamente
if (!in_array($detalle['estatus_preparacion'], ['listo', 'entregado'], true)) {
    $upProd = $conn->prepare('UPDATE productos SET existencia = existencia - ? WHERE id = ?');
    if ($upProd) {
        $cant = (int)$detalle['cantidad'];
        $pid  = (int)$detalle['producto_id'];
        $upProd->bind_param('ii', $cant, $pid);
        $upProd->execute();
        $upProd->close();
    }
}

success(true);
?>
