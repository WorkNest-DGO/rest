<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$mesa_id = $input['mesa_id'] ?? null;
$usuario_id = $input['usuario_id'] ?? null;
$asignador_id = $input['usuario_asignador_id'] ?? null;

if (!$mesa_id || !$asignador_id) {
    error('Datos inválidos');
}

$mesa_id = (int)$mesa_id;
$asignador_id = (int)$asignador_id;
$usuario_id = $usuario_id !== null ? (int)$usuario_id : null;

$conn->query('SET @usuario_asignador_id = ' . $asignador_id);

$stmt = $conn->prepare('UPDATE mesas SET usuario_id = ? WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('ii', $usuario_id, $mesa_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al asignar mesero: ' . $stmt->error);
}
$stmt->close();

success(true);
