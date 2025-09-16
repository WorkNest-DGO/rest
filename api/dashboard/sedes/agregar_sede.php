<?php
require_once __DIR__ . '../../../../config/db.php';
require_once __DIR__ . '/../../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error('JSON inválido');
}

$nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
$direccion = isset($input['direccion']) ? trim($input['direccion']) : '';
$rfc = isset($input['rfc']) ? trim($input['rfc']) : '';
$telefono = isset($input['telefono']) ? trim($input['telefono']) : '';
$correo = isset($input['correo']) ? trim($input['correo']) : '';
$web = isset($input['web']) ? trim($input['web']) : '';
$activo = isset($input['activo']) ? intval($input['activo']) : 0;


if ($nombre === '' || $direccion === '' || $rfc === '' || $telefono === '' || $correo === '' || $web === '' || !isset($input['activo'])) {
    error('Datos incompletos');
}

$stmt = $conn->prepare('INSERT INTO sedes (nombre, direccion, rfc, telefono, correo, web, activo) VALUES (?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    error('Error: ' . $conn->error);
}
$stmt->bind_param('ssssssi', $nombre, $direccion, $rfc, $telefono, $correo, $web, $activo);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al agregar sede: ' . $stmt->error);
}
$stmt->close();

success(['mensaje' => 'Sede agregada']);

