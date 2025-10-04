<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { error('JSON inválido'); }

$usuario_id = isset($data['usuario_id']) ? (int)$data['usuario_id'] : null;
$contrasena = isset($data['contrasena']) ? (string)$data['contrasena'] : '';
if (!$usuario_id || $contrasena === '') { error('Credenciales incompletas'); }

$stmt = $conn->prepare('SELECT id, usuario, contrasena, activo FROM usuarios WHERE id = ? LIMIT 1');
if (!$stmt) { error('Error preparando consulta: ' . $conn->error); }
$stmt->bind_param('i', $usuario_id);
if (!$stmt->execute()) { $stmt->close(); error('Error ejecutando consulta: ' . $stmt->error); }
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || (int)$row['activo'] !== 1) { error('Usuario no válido'); }

$hashDb = (string)$row['contrasena'];
$ok = ($hashDb === $contrasena) || ($hashDb === sha1($contrasena));
if (!$ok) { error('Contraseña incorrecta'); }

success(['valido' => true, 'usuario' => ['id' => (int)$row['id'], 'usuario' => $row['usuario']]]);
?>

