<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Método no permitido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('Sesión no iniciada');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    error('ID inválido');
}

$stmt = $conn->prepare('SELECT id, motivo, monto, activo, visible_en_ticket, tipo, regla, prioridad, combinable, creado_en FROM catalogo_promos WHERE id = ?');
if (!$stmt) { error('Error preparando consulta: ' . $conn->error); }
$stmt->bind_param('i', $id);
if (!$stmt->execute()) { $stmt->close(); error('Error ejecutando consulta: ' . $stmt->error); }
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) { error('No encontrado'); }

success($row);
?>

