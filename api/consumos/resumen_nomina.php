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

$sql = "SELECT c.usuario_id, u.nombre AS usuario_nombre,
               SUM(c.subtotal) AS total_consumos,
               SUM(CASE WHEN c.descuento_nomina = 'exento' THEN c.subtotal ELSE 0 END) AS total_exento,
               SUM(CASE WHEN c.descuento_nomina = 'exento' THEN 0 ELSE c.monto_nomina END) AS total_cobrable
          FROM consumos_empleado c
          JOIN usuarios u ON u.id = c.usuario_id
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
$sql .= " GROUP BY c.usuario_id, u.nombre ORDER BY u.nombre";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar resumen', 'data' => null]);
    exit;
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'mensaje' => 'Error al obtener resumen', 'data' => null]);
    exit;
}
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'usuario_id' => (int)$row['usuario_id'],
        'usuario_nombre' => $row['usuario_nombre'],
        'total_consumos' => (float)$row['total_consumos'],
        'total_cobrable' => (float)$row['total_cobrable'],
        'total_exento' => (float)$row['total_exento']
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'mensaje' => 'OK', 'data' => $rows]);
