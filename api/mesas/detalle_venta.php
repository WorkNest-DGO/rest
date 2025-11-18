<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'])) {
    error('Datos inválidos');
}

$venta_id = (int)$input['venta_id'];

// Obtener mesa y mesero de la venta
$info = $conn->prepare(
    'SELECT v.fecha AS fecha,
            v.tipo_entrega,
            v.usuario_id,
            v.sede_id,
            v.promocion_id,
            v.promocion_descuento,
            m.nombre AS mesa,
            u.nombre AS mesero
     FROM ventas v
     JOIN mesas m ON v.mesa_id = m.id
     JOIN usuarios u ON v.usuario_id = u.id
     WHERE v.id = ?'
);
if (!$info) {
    error('Error al preparar consulta: ' . $conn->error);
}
$info->bind_param('i', $venta_id);
if (!$info->execute()) {
    $info->close();
    error('Error al ejecutar consulta: ' . $info->error);
}
$datosVenta = $info->get_result()->fetch_assoc();
$info->close();

// Obtener productos con estatus de preparación
$stmt = $conn->prepare(
    'SELECT vd.id, p.nombre, vd.cantidad, vd.precio_unitario,
            (vd.cantidad * vd.precio_unitario) AS subtotal,
            vd.estado_producto
     FROM venta_detalles vd
     JOIN productos p ON vd.producto_id = p.id
     WHERE vd.venta_id = ?'
);
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $venta_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar consulta: ' . $stmt->error);
}
$res = $stmt->get_result();
$productos = [];
while ($row = $res->fetch_assoc()) {
    $productos[] = $row;
}
$stmt->close();

success([
    'fecha'               => $datosVenta['fecha'] ?? '',
    'tipo_entrega'        => $datosVenta['tipo_entrega'] ?? '',
    'usuario_id'          => isset($datosVenta['usuario_id']) ? (int)$datosVenta['usuario_id'] : null,
    'sede_id'             => isset($datosVenta['sede_id']) ? (int)$datosVenta['sede_id'] : null,
    'promocion_id'        => isset($datosVenta['promocion_id']) ? (int)$datosVenta['promocion_id'] : null,
    'promocion_descuento' => isset($datosVenta['promocion_descuento']) ? (float)$datosVenta['promocion_descuento'] : 0.0,
    'mesa'                => $datosVenta['mesa'] ?? '',
    'mesero'              => $datosVenta['mesero'] ?? '',
    'productos'           => $productos
]);
?>
