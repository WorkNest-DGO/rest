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

$venta_id = (int)$input['venta_id'];
$usuario_id = $input['usuario_id'] ?? null;

if ($usuario_id === null || $usuario_id === '') {
    $stmt = $conn->prepare('UPDATE ventas SET usuario_id = NULL WHERE id = ?');
    if (!$stmt) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param('i', $venta_id);
} else {
    $usuario_id = (int)$usuario_id;
    $stmt = $conn->prepare('UPDATE ventas SET usuario_id = ? WHERE id = ?');
    if (!$stmt) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param('ii', $usuario_id, $venta_id);
}
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar mesero: ' . $stmt->error);
}
$stmt->close();

success(true);
?>
