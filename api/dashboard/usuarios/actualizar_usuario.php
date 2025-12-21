<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Metodo no permitido');
}

$usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
if ($usuarioId <= 0) {
    error('Sesion no valida');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    error('JSON invalido');
}

$rol = $_SESSION['rol'] ?? '';
$isAdmin = $rol === 'admin';

$nombre = trim($input['nombre'] ?? '');
$contrasena = trim((string)($input['contrasena'] ?? ''));
$sedeId = isset($input['sede_id']) ? (int)$input['sede_id'] : 0;

if ($nombre === '') {
    error('Nombre requerido');
}
if ($isAdmin && $sedeId <= 0) {
    error('Sede requerida');
}

if ($contrasena !== '') {
    $hash = sha1($contrasena);
    if ($isAdmin) {
        $stmt = $conn->prepare('UPDATE usuarios SET nombre = ?, contrasena = ?, sede_id = ? WHERE id = ?');
        if (!$stmt) {
            error('Error al preparar usuario: ' . $conn->error);
        }
        $stmt->bind_param('ssii', $nombre, $hash, $sedeId, $usuarioId);
    } else {
        $stmt = $conn->prepare('UPDATE usuarios SET nombre = ?, contrasena = ? WHERE id = ?');
        if (!$stmt) {
            error('Error al preparar usuario: ' . $conn->error);
        }
        $stmt->bind_param('ssi', $nombre, $hash, $usuarioId);
    }
} else {
    if ($isAdmin) {
        $stmt = $conn->prepare('UPDATE usuarios SET nombre = ?, sede_id = ? WHERE id = ?');
        if (!$stmt) {
            error('Error al preparar usuario: ' . $conn->error);
        }
        $stmt->bind_param('sii', $nombre, $sedeId, $usuarioId);
    } else {
        $stmt = $conn->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?');
        if (!$stmt) {
            error('Error al preparar usuario: ' . $conn->error);
        }
        $stmt->bind_param('si', $nombre, $usuarioId);
    }
}

if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar usuario: ' . $stmt->error);
}
$stmt->close();

success(['mensaje' => 'Usuario actualizado']);
