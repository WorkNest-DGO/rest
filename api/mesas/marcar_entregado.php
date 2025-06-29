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

$stmt = $conn->prepare("UPDATE venta_detalles SET estatus_preparacion = 'entregado' WHERE id = ?");
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('i', $detalle_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al marcar entregado: ' . $stmt->error);
}
$stmt->close();

success(true);
?>
