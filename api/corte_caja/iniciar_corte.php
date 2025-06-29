<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = $input['usuario_id'] ?? null;
if (!$usuario_id) {
    error('usuario_id requerido');
}

$stmt = $conn->prepare('SELECT id FROM corte_caja WHERE usuario_id = ? AND fecha_fin IS NULL');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    error('Ya existe un corte abierto para este usuario');
}
$stmt->close();

$stmt = $conn->prepare('INSERT INTO corte_caja (usuario_id, fecha_inicio) VALUES (?, NOW())');
if (!$stmt) {
    error('Error al preparar inserción: ' . $conn->error);
}
$stmt->bind_param('i', $usuario_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al crear corte: ' . $stmt->error);
}
$corte_id = $stmt->insert_id;
$stmt->close();

success(['corte_id' => $corte_id]);
?>
