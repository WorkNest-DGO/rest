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

$dia = trim($input['dia_semana'] ?? '');
$inicio = $input['hora_inicio'] ?? '';
$fin = $input['hora_fin'] ?? '';
$serie = isset($input['serie_id']) ? (int)$input['serie_id'] : 0;

if ($dia === '' || $inicio === '' || $fin === '' || !$serie) {
    error('Datos incompletos');
}

$check = $conn->prepare('SELECT id FROM horarios WHERE dia_semana = ? AND NOT (? >= hora_fin OR ? <= hora_inicio)');
$check->bind_param('sss', $dia, $inicio, $fin);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    error('Rango traslapa con otro existente');
}
$check->close();

$stmt = $conn->prepare('INSERT INTO horarios (dia_semana, hora_inicio, hora_fin, serie_id) VALUES (?,?,?,?)');
if (!$stmt) {
    error('Error al preparar inserción: ' . $conn->error);
}
$stmt->bind_param('sssi', $dia, $inicio, $fin, $serie);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al crear horario: ' . $stmt->error);
}
$id = $stmt->insert_id;
$stmt->close();

success(['id' => $id]);
?>
