<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../utils/response.php';

$usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
if ($usuarioId <= 0) {
    error('Sesion no valida');
}

$stmt = $conn->prepare("SELECT u.id, u.nombre, u.usuario, u.rol, u.activo, u.sede_id, s.nombre AS sede_nombre
                         FROM usuarios u
                         LEFT JOIN sedes s ON s.id = u.sede_id
                         WHERE u.id = ? LIMIT 1");
if (!$stmt) {
    error('Error al preparar usuario: ' . $conn->error);
}
$stmt->bind_param('i', $usuarioId);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al consultar usuario: ' . $stmt->error);
}
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$row) {
    error('Usuario no encontrado');
}

$row['id'] = (int)$row['id'];
$row['activo'] = isset($row['activo']) ? (int)$row['activo'] : 0;
$row['sede_id'] = isset($row['sede_id']) ? (int)$row['sede_id'] : null;

success($row);
