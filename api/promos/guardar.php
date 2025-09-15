<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('Sesión no iniciada');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { error('JSON inválido'); }

$id = isset($input['id']) ? (int)$input['id'] : 0;
$motivo = isset($input['motivo']) ? trim($input['motivo']) : '';
$monto = isset($input['monto']) ? (float)$input['monto'] : 0.0;
$activo = isset($input['activo']) ? (int)!!$input['activo'] : 1;
$visible = isset($input['visible_en_ticket']) ? (int)!!$input['visible_en_ticket'] : 1;
$tipo = isset($input['tipo']) ? trim($input['tipo']) : '';
$prioridad = isset($input['prioridad']) ? (int)$input['prioridad'] : 10;
$combinable = isset($input['combinable']) ? (int)!!$input['combinable'] : 1;
$regla = isset($input['regla']) ? $input['regla'] : null; // puede venir como objeto o string

if ($motivo === '' || $tipo === '') {
    error('Campos requeridos: motivo, tipo');
}

// Asegurar que regla sea JSON válido (guardamos string JSON)
if (is_array($regla) || is_object($regla)) {
    $regla_json = json_encode($regla, JSON_UNESCAPED_UNICODE);
} else {
    $regla_json = trim((string)$regla);
    if ($regla_json === '') { $regla_json = '{}'; }
}
$tmp = json_decode($regla_json, true);
if ($tmp === null && json_last_error() !== JSON_ERROR_NONE) {
    error('Campo regla no es JSON válido');
}

if ($id > 0) {
    $sql = 'UPDATE catalogo_promos SET motivo=?, monto=?, activo=?, visible_en_ticket=?, tipo=?, regla=?, prioridad=?, combinable=? WHERE id=?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error('Error preparando actualización: ' . $conn->error); }
    $stmt->bind_param('sdiissiii', $motivo, $monto, $activo, $visible, $tipo, $regla_json, $prioridad, $combinable, $id);
    if (!$stmt->execute()) { $stmt->close(); error('Error al actualizar: ' . $stmt->error); }
    $stmt->close();
    success(['id' => $id, 'mensaje' => 'Actualizado']);
} else {
    $sql = 'INSERT INTO catalogo_promos (motivo, monto, activo, visible_en_ticket, tipo, regla, prioridad, combinable) VALUES (?,?,?,?,?,?,?,?)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error('Error preparando inserción: ' . $conn->error); }
    $stmt->bind_param('sdiissii', $motivo, $monto, $activo, $visible, $tipo, $regla_json, $prioridad, $combinable);
    if (!$stmt->execute()) { $stmt->close(); error('Error al insertar: ' . $stmt->error); }
    $newId = $stmt->insert_id;
    $stmt->close();
    success(['id' => $newId, 'mensaje' => 'Creado']);
}
?>

