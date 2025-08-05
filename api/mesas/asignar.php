<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$mesa_id = $input['mesa_id'] ?? null;
$usuario_id = $input['usuario_id'] ?? null;

if (!$mesa_id) {
    error('Datos inválidos');
}

$mesa_id = (int)$mesa_id;
$usuario_id = $usuario_id !== null ? (int)$usuario_id : null;

$usuarioActualId = $_SESSION['usuario_id'] ?? null;
$rol = $_SESSION['rol'] ?? '';
if (!$usuarioActualId) {
    error('No autenticado');
}

if ($rol !== 'admin') {
    $stmt = $conn->prepare('SELECT usuario_id FROM mesas WHERE id = ?');
    if (!$stmt) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param('i', $mesa_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$res || (int)$res['usuario_id'] !== $usuarioActualId) {
        error('No autorizado');
    }
}

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
