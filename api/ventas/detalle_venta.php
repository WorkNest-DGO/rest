<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'])) {
    echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
    exit;
}

$venta_id = (int)$input['venta_id'];

$stmt = $conn->prepare(
    'SELECT p.nombre, vd.cantidad, vd.precio_unitario, ' .
    '(vd.cantidad * vd.precio_unitario) AS subtotal ' .
    'FROM venta_detalles vd ' .
    'JOIN productos p ON vd.producto_id = p.id ' .
    'WHERE vd.venta_id = ?'
);
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar consulta']);
    exit;
}

$stmt->bind_param('i', $venta_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al ejecutar consulta']);
    $stmt->close();
    exit;
}

$res = $stmt->get_result();
$productos = [];
while ($row = $res->fetch_assoc()) {
    $productos[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'productos' => $productos]);
