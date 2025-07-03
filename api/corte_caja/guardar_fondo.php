<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = $input['usuario_id'] ?? null;
$monto = isset($input['monto']) ? (float)$input['monto'] : null;
if (!$usuario_id || $monto === null) {
    error('Datos incompletos');
}

$stmt = $conn->prepare('INSERT INTO fondo (usuario_id, monto) VALUES (?, ?) ON DUPLICATE KEY UPDATE monto = VALUES(monto)');
if (!$stmt) {
    error('Error al preparar inserción: ' . $conn->error);
}
$stmt->bind_param('id', $usuario_id, $monto);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al guardar fondo: ' . $stmt->error);
}
$stmt->close();

success(['monto' => $monto]);
?>
