<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
$conn->set_charset('utf8mb4');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado', 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'mensaje' => 'ID invalido', 'data' => null]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM consumos_beneficios WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar eliminacion', 'data' => null]);
    exit;
}
$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'mensaje' => 'Error al eliminar beneficio', 'data' => null]);
    exit;
}
$stmt->close();

echo json_encode(['success' => true, 'mensaje' => 'Beneficio eliminado', 'data' => ['id' => $id]]);
