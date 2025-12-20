<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$printId = isset($input['print_id']) ? (int)$input['print_id'] : 0;

if ($printId <= 0) {
    error('ID invalido');
}

$stmt = $conn->prepare("DELETE FROM impresoras WHERE print_id = ?");
if (!$stmt) {
    error('Error en la preparacion: ' . $conn->error);
}
$stmt->bind_param('i', $printId);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'mensaje' => 'Impresora eliminada']);
} else {
    error('Error al eliminar impresora: ' . $stmt->error);
}
