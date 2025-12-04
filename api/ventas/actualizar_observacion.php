<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'])) {
    error('Datos inválidos');
}

$ventaId = (int)$input['venta_id'];
$observacion = isset($input['observacion']) ? trim((string)$input['observacion']) : '';
$usuarioId = $_SESSION['usuario_id'] ?? null;

if (!$usuarioId) {
    error('No autenticado');
}
if ($ventaId <= 0) {
    error('venta_id inválido');
}

// Evitar observaciones excesivamente largas
if (strlen($observacion) > 2000) {
    $observacion = substr($observacion, 0, 2000);
}

$stmt = $conn->prepare('UPDATE ventas SET observacion = ? WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('si', $observacion, $ventaId);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar observación: ' . $stmt->error);
}
$stmt->close();

success(true);
?>
