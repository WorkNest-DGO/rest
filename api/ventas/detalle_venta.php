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

// Obtener datos generales de la venta
$info = $conn->prepare(
    'SELECT v.tipo_entrega, m.nombre AS mesa, r.nombre AS repartidor, u.nombre AS mesero
     FROM ventas v
     LEFT JOIN mesas m ON v.mesa_id = m.id
     LEFT JOIN repartidores r ON v.repartidor_id = r.id
     JOIN usuarios u ON v.usuario_id = u.id
     WHERE v.id = ?'
);
if (!$info) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar consulta']);
    exit;
}
$info->bind_param('i', $venta_id);
if (!$info->execute()) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al ejecutar consulta']);
    $info->close();
    exit;
}
$datosVenta = $info->get_result()->fetch_assoc();
$info->close();

$stmt = $conn->prepare(
    'SELECT vd.id, vd.producto_id, p.nombre, vd.cantidad, vd.precio_unitario, ' .
    '(vd.cantidad * vd.precio_unitario) AS subtotal, vd.estatus_preparacion ' .
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

echo json_encode([
    'success'      => true,
    'tipo_entrega' => $datosVenta['tipo_entrega'] ?? '',
    'mesa'         => $datosVenta['mesa'] ?? '',
    'repartidor'   => $datosVenta['repartidor'] ?? '',
    'mesero'       => $datosVenta['mesero'] ?? '',
    'productos'    => $productos
]);
