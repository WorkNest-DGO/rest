<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['detalle_id'])) {
    error('Datos inválidos');
}

$detalle_id = (int) $input['detalle_id'];

$info = $conn->prepare('SELECT venta_id, cantidad, precio_unitario, estatus_preparacion FROM venta_detalles WHERE id = ?');
if (!$info) {
    error('Error al preparar consulta: ' . $conn->error);
}
$info->bind_param('i', $detalle_id);
if (!$info->execute()) {
    $info->close();
    error('Error al ejecutar consulta: ' . $info->error);
}
$detalle = $info->get_result()->fetch_assoc();
$info->close();

if (!$detalle) {
    error('Detalle no encontrado');
}

if ($detalle['estatus_preparacion'] === 'entregado') {
    error('No se puede eliminar el producto');
}

$del = $conn->prepare('DELETE FROM venta_detalles WHERE id = ?');
if (!$del) {
    error('Error al preparar eliminación: ' . $conn->error);
}
$del->bind_param('i', $detalle_id);
if (!$del->execute()) {
    $del->close();
    error('Error al eliminar producto: ' . $del->error);
}
$del->close();

$totalRestar = $detalle['cantidad'] * $detalle['precio_unitario'];
$up = $conn->prepare('UPDATE ventas SET total = total - ? WHERE id = ?');
if ($up) {
    $up->bind_param('di', $totalRestar, $detalle['venta_id']);
    $up->execute();
    $up->close();
}

success(true);
?>
