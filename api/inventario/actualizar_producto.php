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

$id          = isset($input['id']) ? (int)$input['id'] : 0;
$nombre      = isset($input['nombre']) ? trim($input['nombre']) : '';
$precio      = isset($input['precio']) ? (float)$input['precio'] : null;
$descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';
$existencia  = isset($input['existencia']) ? (int)$input['existencia'] : 0;
$categoriaId = isset($input['categoria_id']) ? (int)$input['categoria_id'] : 0;

if ($id <= 0 || $nombre === '' || $precio === null || $categoriaId <= 0) {
    error('Datos incompletos');
}

$stmt = $conn->prepare('UPDATE productos SET nombre = ?, precio = ?, descripcion = ?, existencia = ?, categoria_id = ? WHERE id = ?');
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('sdsiii', $nombre, $precio, $descripcion, $existencia, $categoriaId, $id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar producto: ' . $stmt->error);
}
$stmt->close();

success(['mensaje' => 'Producto actualizado']);
