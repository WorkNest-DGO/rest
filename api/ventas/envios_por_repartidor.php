<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if (!defined('ENVIO_CASA_PRODUCT_ID')) {
    define('ENVIO_CASA_PRODUCT_ID', 9001);
}

$corteId = isset($_GET['corte_id'])
    ? (int)$_GET['corte_id']
    : (isset($_SESSION['corte_id']) ? (int)$_SESSION['corte_id'] : null);

$baseSql = "SELECT
                v.id AS venta_id,
                v.fecha AS fecha_venta,
                r.id AS repartidor_id,
                r.nombre AS repartidor,
                v.usuario_id,
                COALESCE(u.nombre, CONCAT('Usuario ', v.usuario_id)) AS usuario,
                SUM(vd.subtotal) AS total_envio_casa,
                COUNT(*) AS num_lineas_envio
            FROM ventas v
            JOIN venta_detalles vd ON vd.venta_id = v.id
            JOIN repartidores r ON r.id = v.repartidor_id
            LEFT JOIN usuarios u ON u.id = v.usuario_id
            WHERE v.tipo_entrega = 'domicilio'
              AND r.nombre = 'Repartidor casa'
              AND vd.producto_id = ?";

$params = [ENVIO_CASA_PRODUCT_ID];
$types = 'i';

$filtroFecha = null;
if ($corteId) {
    $baseSql .= " AND v.corte_id = ?";
    $params[] = $corteId;
    $types .= 'i';
} else {
    $baseSql .= " AND DATE(v.fecha) = CURDATE()";
    $filtroFecha = date('Y-m-d');
}

$baseSql .= " GROUP BY v.id, v.fecha, r.id, r.nombre, v.usuario_id, u.nombre
              ORDER BY v.fecha";

$stmt = $conn->prepare($baseSql);
if (!$stmt) {
    error('Error al preparar la consulta: ' . $conn->error);
}

$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar la consulta: ' . $stmt->error);
}

$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

// Agrupar por usuario (mesero/cajero) para no duplicar filas
$agrupado = [];
foreach ($rows as $r) {
    $uid = isset($r['usuario_id']) ? (int)$r['usuario_id'] : null;
    // Fallback al repartidor si no hay usuario
    $key = $uid !== null ? 'u:' . $uid : ($r['repartidor_id'] ? 'r:' . $r['repartidor_id'] : 'rep:' . $r['repartidor']);
    if (!isset($agrupado[$key])) {
        $agrupado[$key] = [
            'repartidor_id'   => $r['repartidor_id'] ? (int)$r['repartidor_id'] : null,
            'repartidor'      => $r['repartidor'],
            'usuario_id'      => $uid,
            'usuario'         => $r['usuario'],
            'total_envio'     => 0.0,
            'lineas_envio'    => 0,
            'ventas'          => 0,
        ];
    }
    $agrupado[$key]['total_envio'] += (float)($r['total_envio_casa'] ?? 0);
    $agrupado[$key]['lineas_envio'] += (int)($r['num_lineas_envio'] ?? 0);
    $agrupado[$key]['ventas']++;
}

$detalle = array_values($agrupado);
usort($detalle, function ($a, $b) {
    return ($b['total_envio'] <=> $a['total_envio']) ?: strcmp($a['repartidor'] ?? '', $b['repartidor'] ?? '');
});

$totalGeneral = 0;
$totalLineas = 0;
foreach ($detalle as $item) {
    $totalGeneral += (float)$item['total_envio'];
    $totalLineas  += (int)$item['lineas_envio'];
}

success([
    'corte_id'            => $corteId,
    'fecha'               => $filtroFecha,
    'producto_id'         => (int)ENVIO_CASA_PRODUCT_ID,
    'total_envio_general' => $totalGeneral,
    'total_lineas'        => $totalLineas,
    'repartidores'        => $detalle
]);
