<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { error('JSON inválido'); }

$id                 = isset($input['id']) ? (int)$input['id'] : null;
$nombre             = isset($input['nombre']) ? trim($input['nombre']) : '';
$estado             = isset($input['estado']) ? trim($input['estado']) : 'libre';
$capacidad          = isset($input['capacidad']) ? (int)$input['capacidad'] : 4;
$mesa_principal_id  = array_key_exists('mesa_principal_id', $input) && $input['mesa_principal_id'] !== '' ? (int)$input['mesa_principal_id'] : null;
$area               = isset($input['area']) ? trim($input['area']) : null;
$usuario_id         = array_key_exists('usuario_id', $input) && $input['usuario_id'] !== '' ? (int)$input['usuario_id'] : null;
$area_id            = array_key_exists('area_id', $input) && $input['area_id'] !== '' ? (int)$input['area_id'] : null;
$alineacion_id      = array_key_exists('alineacion_id', $input) && $input['alineacion_id'] !== '' ? (int)$input['alineacion_id'] : null;

if ($nombre === '') {
    error('El nombre es requerido');
}
if (!in_array($estado, ['libre','ocupada','reservada'], true)) {
    error('Estado inválido');
}

if ($id) {
    $sql = "UPDATE mesas SET nombre = ?, estado = ?, capacidad = ?, mesa_principal_id = ?, area = ?, usuario_id = ?, area_id = ?, alineacion_id = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error('Error al preparar actualización: ' . $conn->error); }
    $stmt->bind_param(
        'ssiisiiii',
        $nombre,
        $estado,
        $capacidad,
        $mesa_principal_id,
        $area,
        $usuario_id,
        $area_id,
        $alineacion_id,
        $id
    );
    if (!$stmt->execute()) { error('Error al actualizar: ' . $stmt->error); }
    $stmt->close();
    success(['id' => $id]);
} else {
    $sql = "INSERT INTO mesas (nombre, estado, capacidad, mesa_principal_id, area, usuario_id, area_id, alineacion_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error('Error al preparar inserción: ' . $conn->error); }
    $stmt->bind_param(
        'ssiisiii',
        $nombre,
        $estado,
        $capacidad,
        $mesa_principal_id,
        $area,
        $usuario_id,
        $area_id,
        $alineacion_id
    );
    if (!$stmt->execute()) { error('Error al insertar: ' . $stmt->error); }
    $newId = $stmt->insert_id;
    $stmt->close();
    success(['id' => (int)$newId]);
}
?>
