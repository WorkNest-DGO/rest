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
    'SELECT v.tipo_entrega,
            v.fecha,
            v.usuario_id,
            v.sede_id,
            v.promocion_id,
            v.promocion_descuento,
            m.nombre AS mesa,
            r.nombre AS repartidor,
            u.nombre AS mesero,
            v.seudonimo_entrega,
            v.propina_efectivo,
            v.propina_cheque,
            v.propina_tarjeta,
            v.foto_entrega
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

$promosVenta = [];
$promosIds = [];
$promosTotal = 0.0;
if ($promoStmt = $conn->prepare('SELECT vp.promo_id, cp.nombre, COALESCE(vp.descuento_aplicado,0) AS descuento_aplicado FROM venta_promos vp JOIN catalogo_promos cp ON cp.id = vp.promo_id WHERE vp.venta_id = ? ORDER BY vp.id')) {
    $promoStmt->bind_param('i', $venta_id);
    if ($promoStmt->execute()) {
        $resPromo = $promoStmt->get_result();
        while ($rowPromo = $resPromo->fetch_assoc()) {
            $rowPromo['promo_id'] = (int)($rowPromo['promo_id'] ?? 0);
            $rowPromo['descuento_aplicado'] = isset($rowPromo['descuento_aplicado']) ? (float)$rowPromo['descuento_aplicado'] : 0.0;
            $promosVenta[] = $rowPromo;
            $promosIds[] = $rowPromo['promo_id'];
            $promosTotal += $rowPromo['descuento_aplicado'];
        }
    }
    $promoStmt->close();
}

$stmt = $conn->prepare(
    'SELECT vd.id, vd.producto_id, p.nombre, vd.cantidad, vd.precio_unitario, ' .
    '(vd.cantidad * vd.precio_unitario) AS subtotal, vd.estado_producto, vd.entregado_hr, p.categoria_id ' .
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
    'success'              => true,
    'tipo_entrega'         => $datosVenta['tipo_entrega'] ?? '',
    'fecha'                => $datosVenta['fecha'] ?? '',
    'usuario_id'           => isset($datosVenta['usuario_id']) ? (int)$datosVenta['usuario_id'] : null,
    'sede_id'              => isset($datosVenta['sede_id']) ? (int)$datosVenta['sede_id'] : null,
    'promocion_id'         => isset($datosVenta['promocion_id']) ? (int)$datosVenta['promocion_id'] : null,
    'promocion_descuento'  => isset($datosVenta['promocion_descuento']) ? (float)$datosVenta['promocion_descuento'] : 0.0,
    'promociones'          => $promosVenta,
    'promociones_ids'      => $promosIds,
    'promociones_total_descuento' => $promosTotal,
    'mesa'                 => $datosVenta['mesa'] ?? '',
    'repartidor'           => $datosVenta['repartidor'] ?? '',
    'mesero'               => $datosVenta['mesero'] ?? '',
    'seudonimo_entrega'    => $datosVenta['seudonimo_entrega'] ?? '',
    'foto_entrega'         => $datosVenta['foto_entrega'] ?? '',
    'propina_efectivo'     => $datosVenta['propina_efectivo'] ?? '',
    'propina_cheque'       => $datosVenta['propina_cheque'] ?? '',
    'propina_tarjeta'      => $datosVenta['propina_tarjeta'] ?? '',
    'productos'            => $productos
]);
