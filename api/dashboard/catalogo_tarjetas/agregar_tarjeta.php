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

if ($nombre === '') {
    error('Datos incompletos');
}

$stmt = $conn->prepare('INSERT INTO catalogo_tarjetas (nombre) VALUES (?)');
if (!$stmt) {
    error('Error al preparar inserción: ' . $conn->error);
}
$stmt->bind_param('s', $nombre);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al agregar tarjeta: ' . $stmt->error);
}
$stmt->close();

success(['mensaje' => 'Tarjeta agregada']);

