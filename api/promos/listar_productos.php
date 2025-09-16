<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Método no permitido');
}
if (!isset($_SESSION['usuario_id'])) { error('Sesión no iniciada'); }

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limite']) ? max(1, min(100, (int)$_GET['limite'])) : 50;

if ($q !== '') {
    $stmt = $conn->prepare('SELECT id, nombre, precio, activo FROM productos WHERE nombre LIKE ? ORDER BY nombre ASC LIMIT ?');
    if (!$stmt) { error('Error preparando consulta: ' . $conn->error); }
    $term = "%$q%";
    $stmt->bind_param('si', $term, $limit);
} else {
    $stmt = $conn->prepare('SELECT id, nombre, precio, activo FROM productos ORDER BY nombre ASC LIMIT ?');
    if (!$stmt) { error('Error preparando consulta: ' . $conn->error); }
    $stmt->bind_param('i', $limit);
}
if (!$stmt->execute()) { $stmt->close(); error('Error ejecutando consulta: ' . $stmt->error); }
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

success(['productos' => $rows]);
?>

