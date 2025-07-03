<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['mesa_id'])) {
    error('Datos inválidos');
}

$mesa_id = (int)$input['mesa_id'];

$stmt = $conn->prepare('UPDATE mesas SET ticket_enviado = FALSE WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $mesa_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar mesa: ' . $stmt->error);
}
$stmt->close();

success(true);
?>
