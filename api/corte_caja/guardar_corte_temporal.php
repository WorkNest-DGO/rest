<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$corte_id = isset($input['corte_id']) ? (int)$input['corte_id'] : 0;
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$total = isset($input['total']) ? (float)$input['total'] : 0;
$observaciones = $input['observaciones'] ?? '';
$datos_json = json_encode($input['datos_json'] ?? [], JSON_UNESCAPED_UNICODE);

if ($corte_id <= 0 || $usuario_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO corte_caja_historial (corte_id, usuario_id, total, observaciones, datos_json) VALUES (?, ?, ?, ?, ?)');
if ($stmt) {
    $stmt->bind_param('iidss', $corte_id, $usuario_id, $total, $observaciones, $datos_json);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error al preparar sentencia']);
}
