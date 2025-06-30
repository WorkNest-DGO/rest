<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error('JSON inválido');
}

$nombre      = isset($input['nombre']) ? trim($input['nombre']) : '';
$unidad      = isset($input['unidad']) ? trim($input['unidad']) : '';
$existencia  = isset($input['existencia']) ? (float)$input['existencia'] : 0;
$tipo        = isset($input['tipo_control']) ? trim($input['tipo_control']) : '';

if ($nombre === '' || $unidad === '' || $tipo === '') {
    error('Datos incompletos');
}

$stmt = $conn->prepare('INSERT INTO insumos (nombre, unidad, existencia, tipo_control) VALUES (?, ?, ?, ?)');
if (!$stmt) {
    error('Error al preparar inserción: ' . $conn->error);
}
$stmt->bind_param('ssds', $nombre, $unidad, $existencia, $tipo);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al agregar insumo: ' . $stmt->error);
}
$stmt->close();

success(['mensaje' => 'Insumo agregado']);
?>
