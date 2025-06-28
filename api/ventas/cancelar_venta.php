<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'])) {
    error('Datos inválidos');
}

$venta_id = (int) $input['venta_id'];

$stmt = $conn->prepare("UPDATE ventas SET estatus = 'cancelada' WHERE id = ?");
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('i', $venta_id);
if (!$stmt->execute()) {
    error('Error al cancelar venta: ' . $stmt->error);
}

if ($stmt->affected_rows === 0) {
    $stmt->close();
    error('Venta no encontrada');
}
$stmt->close();

success(['mensaje' => 'Venta cancelada correctamente']);

