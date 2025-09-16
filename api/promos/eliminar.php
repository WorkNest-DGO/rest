<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('Sesión no iniciada');
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) { error('ID inválido'); }

$stmt = $conn->prepare('DELETE FROM catalogo_promos WHERE id = ?');
if (!$stmt) { error('Error preparando eliminación: ' . $conn->error); }
$stmt->bind_param('i', $id);
if (!$stmt->execute()) { $stmt->close(); error('Error al eliminar: ' . $stmt->error); }
$af = $stmt->affected_rows;
$stmt->close();

success(['eliminados' => $af]);
?>

