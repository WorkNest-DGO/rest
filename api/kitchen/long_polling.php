<?php
require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$ultimoId = isset($input['ultimo_id']) ? (int)$input['ultimo_id'] : 0;

$start = microtime(true);
$nueva = null;

do {
    $stmt = $conn->prepare('SELECT * FROM venta_detalles WHERE id > ? ORDER BY id ASC LIMIT 1');
    $stmt->bind_param('i', $ultimoId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $nueva = $row;
        break;
    }
    usleep(500000); // 0.5 segundos
} while ((microtime(true) - $start) < 25);

header('Content-Type: application/json');
if ($nueva) {
    echo json_encode(['nueva_venta' => true, 'data' => $nueva]);
} else {
    echo json_encode(['nueva_venta' => false]);
}
?>
