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

// Liberar la mesa indicada
$stmt = $conn->prepare('UPDATE mesas SET mesa_principal_id = NULL WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $mesa_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al dividir mesa: ' . $stmt->error);
}
$stmt->close();

// Si la mesa era principal, liberar a todas las unidas a ella
$extra = $conn->prepare('UPDATE mesas SET mesa_principal_id = NULL WHERE mesa_principal_id = ?');
if ($extra) {
    $extra->bind_param('i', $mesa_id);
    $extra->execute();
    $extra->close();
}

success(true);
?>
