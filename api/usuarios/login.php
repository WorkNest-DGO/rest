<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$usuario = $input['usuario'] ?? '';
$contrasenaIngresada = $input['contrasena'] ?? '';

if ($usuario === '' || $contrasenaIngresada === '') {
    error('Datos incompletos');
}

$stmt = $conn->prepare('SELECT id, nombre, usuario, contrasena, rol, activo FROM usuarios WHERE usuario = ?');
if (!$stmt) {
    error('Error en la preparación: ' . $conn->error);
}
$stmt->bind_param('s', $usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuarioDB = $result->fetch_assoc();

if (!$usuarioDB || (int)$usuarioDB['activo'] !== 1) {
    error('Usuario o contraseña incorrectos');
}

$hashIngresado = sha1($contrasenaIngresada);

$valido = false;
if (hash_equals($usuarioDB['contrasena'], $hashIngresado)) {
    $valido = true;
} elseif (hash_equals($usuarioDB['contrasena'], $contrasenaIngresada)) {
    $valido = true;
}

if (!$valido) {
    error('Usuario o contraseña incorrectos');
}

unset($usuarioDB['contrasena']);

echo json_encode(['success' => true, 'usuario' => $usuarioDB]);

