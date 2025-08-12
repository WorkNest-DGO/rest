<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$corte_id     = isset($input['corte_id']) ? (int)$input['corte_id'] : 0;
$usuario_id   = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 0;
$total        = isset($input['total']) ? (float)$input['total'] : 0;
$observaciones = $input['observaciones'] ?? '';
$datos_json    = $input['datos_json'] ?? '';

$stmt = $conn->prepare('INSERT INTO corte_caja_historial (corte_id, usuario_id, total, observaciones, datos_json) VALUES (?, ?, ?, ?, ?)');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}
$stmt->bind_param('iidss', $corte_id, $usuario_id, $total, $observaciones, $datos_json);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
$stmt->close();
?>
