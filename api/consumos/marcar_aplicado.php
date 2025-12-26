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
$ids = $input['ids'] ?? [];
if (!is_array($ids) || !count($ids)) {
    echo json_encode(['success' => false, 'mensaje' => 'Sin consumos', 'data' => null]);
    exit;
}

$ids = array_values(array_filter(array_map('intval', $ids), function ($v) {
    return $v > 0;
}));
if (!count($ids)) {
    echo json_encode(['success' => false, 'mensaje' => 'IDs invalidos', 'data' => null]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$sql = "UPDATE consumos_empleado SET descuento_nomina = 'aplicado' WHERE descuento_nomina = 'pendiente' AND id IN ({$placeholders})";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar actualizado', 'data' => null]);
    exit;
}
$stmt->bind_param($types, ...$ids);
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'mensaje' => 'Error al actualizar consumos', 'data' => null]);
    exit;
}
$affected = $stmt->affected_rows;
$stmt->close();

echo json_encode(['success' => true, 'mensaje' => 'Consumos actualizados', 'data' => ['actualizados' => $affected]]);
