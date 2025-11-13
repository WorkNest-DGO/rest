<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['id'])) { error('Falta id'); }
$id = (int)$input['id'];

// Validar que no tenga ventas asociadas
$chk = $conn->prepare('SELECT COUNT(*) AS c FROM ventas WHERE mesa_id = ?');
if (!$chk) { error('Error al preparar validación: ' . $conn->error); }
$chk->bind_param('i', $id);
if (!$chk->execute()) { $chk->close(); error('Error al validar: ' . $chk->error); }
$c = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
$chk->close();
if ($c > 0) {
    http_response_code(400);
    error('No se puede eliminar: la mesa tiene ventas asociadas');
}

$stmt = $conn->prepare('DELETE FROM mesas WHERE id = ?');
if (!$stmt) { error('Error al preparar borrado: ' . $conn->error); }
$stmt->bind_param('i', $id);
if (!$stmt->execute()) { error('Error al eliminar: ' . $stmt->error); }
$stmt->close();
success(['id' => $id]);
?>

