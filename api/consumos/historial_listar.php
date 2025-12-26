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

function normalize_date(string $value, bool $endOfDay): ?string {
    $value = trim($value);
    if ($value === '') return null;
    $ts = strtotime($value);
    if ($ts === false) return null;
    if ($endOfDay) {
        return date('Y-m-d 23:59:59', $ts);
    }
    return date('Y-m-d H:i:s', $ts);
}

$fechaInicio = normalize_date($_GET['fecha_inicio'] ?? '', false);
$fechaFin = normalize_date($_GET['fecha_fin'] ?? '', true);
$usuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$soloCobrables = isset($_GET['solo_cobrables']) ? (int)$_GET['solo_cobrables'] : 0;

$sql = "SELECT c.id, c.usuario_id, u.nombre AS usuario_nombre,
               c.producto_id, p.nombre AS producto_nombre,
               c.cantidad, c.precio_unitario, c.subtotal,
               c.es_gratis, c.descuento_nomina, c.monto_nomina,
               c.fecha_consumo
          FROM consumos_empleado c
          JOIN usuarios u ON u.id = c.usuario_id
          JOIN productos p ON p.id = c.producto_id
         WHERE 1=1";

$types = '';
$params = [];
if ($fechaInicio) {
    $sql .= " AND c.fecha_consumo >= ?";
    $types .= 's';
    $params[] = $fechaInicio;
}
if ($fechaFin) {
    $sql .= " AND c.fecha_consumo <= ?";
    $types .= 's';
    $params[] = $fechaFin;
}
if ($usuarioId > 0) {
    $sql .= " AND c.usuario_id = ?";
    $types .= 'i';
    $params[] = $usuarioId;
}
if ($soloCobrables) {
    $sql .= " AND c.descuento_nomina = 'pendiente'";
}
$sql .= " ORDER BY c.fecha_consumo DESC, c.id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar historial', 'data' => null]);
    exit;
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'mensaje' => 'Error al obtener historial', 'data' => null]);
    exit;
}
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$row['id'],
        'usuario_id' => (int)$row['usuario_id'],
        'usuario_nombre' => $row['usuario_nombre'],
        'producto_id' => (int)$row['producto_id'],
        'producto_nombre' => $row['producto_nombre'],
        'cantidad' => (int)$row['cantidad'],
        'precio_unitario' => (float)$row['precio_unitario'],
        'subtotal' => (float)$row['subtotal'],
        'es_gratis' => isset($row['es_gratis']) ? (int)$row['es_gratis'] : 0,
        'descuento_nomina' => $row['descuento_nomina'],
        'monto_nomina' => (float)$row['monto_nomina'],
        'fecha_consumo' => $row['fecha_consumo']
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'mensaje' => 'OK', 'data' => $rows]);
