<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$usuario = trim($input['usuario'] ?? '');
$contrasenaIngresada = $input['contrasena'] ?? '';

if ($usuario === '' || $contrasenaIngresada === '') {
    echo json_encode(['success' => false, 'mensaje' => 'Faltan datos']);
    exit;
}

$stmt = $conn->prepare('SELECT id, nombre, usuario, rol, contrasena, activo FROM usuarios WHERE usuario = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error en la preparación']);
    exit;
}
$stmt->bind_param('s', $usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuarioDB = $result->fetch_assoc();

if ($usuarioDB && (int)$usuarioDB['activo'] === 1) {
    $hash = sha1($contrasenaIngresada);
    if ($usuarioDB['contrasena'] === $hash || $usuarioDB['contrasena'] === $contrasenaIngresada) {
        unset($usuarioDB['contrasena']);
        $usuarioDB['activo'] = (int)$usuarioDB['activo'];
        echo json_encode(['success' => true, 'usuario' => $usuarioDB]);
        exit;
    }
}

echo json_encode(['success' => false, 'mensaje' => 'Usuario o contraseña incorrectos']);
