<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['id'])) {
    error('Datos inválidos');
}

$id     = (int)$input['id'];
$nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
$unidad = isset($input['unidad']) ? trim($input['unidad']) : '';
$tipo   = isset($input['tipo_control']) ? trim($input['tipo_control']) : '';

if ($nombre === '' || $unidad === '' || $tipo === '') {
    error('Datos incompletos');
}

$stmt = $conn->prepare('UPDATE insumos SET nombre = ?, unidad = ?, tipo_control = ? WHERE id = ?');
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('sssi', $nombre, $unidad, $tipo, $id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar insumo: ' . $stmt->error);
}
$stmt->close();

success(true);
?>
