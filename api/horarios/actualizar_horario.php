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

$id = isset($input['id']) ? (int)$input['id'] : 0;
$dia = trim($input['dia_semana'] ?? '');
$inicio = $input['hora_inicio'] ?? '';
$fin = $input['hora_fin'] ?? '';
$serie = isset($input['serie_id']) ? (int)$input['serie_id'] : 0;

if (!$id || $dia === '' || $inicio === '' || $fin === '' || !$serie) {
    error('Datos incompletos');
}

$check = $conn->prepare('SELECT id FROM horarios WHERE dia_semana = ? AND id != ? AND NOT (? >= hora_fin OR ? <= hora_inicio)');
$check->bind_param('siss', $dia, $id, $inicio, $fin);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    error('Rango traslapa con otro existente');
}
$check->close();

$stmt = $conn->prepare('UPDATE horarios SET dia_semana=?, hora_inicio=?, hora_fin=?, serie_id=? WHERE id=?');
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('sssii', $dia, $inicio, $fin, $serie, $id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar: ' . $stmt->error);
}
$stmt->close();

success(['mensaje' => 'Horario actualizado']);
?>
