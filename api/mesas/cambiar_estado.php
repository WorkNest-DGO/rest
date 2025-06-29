<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['mesa_id']) || !isset($input['nuevo_estado'])) {
    error('Datos inválidos');
}

$mesa_id = (int)$input['mesa_id'];
$nuevo_estado = $input['nuevo_estado'];

$estados = ['libre', 'ocupada', 'reservada'];
if (!in_array($nuevo_estado, $estados, true)) {
    error('Estado no válido');
}

$stmt = $conn->prepare('UPDATE mesas SET estado = ? WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('si', $nuevo_estado, $mesa_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar mesa: ' . $stmt->error);
}
$stmt->close();

success(true);
?>
