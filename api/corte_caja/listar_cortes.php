<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$usuario_id = null;
$inicio = null; // deprecated
$fin = null;    // deprecated
$fecha_inicio = $_REQUEST['fecha_inicio'] ?? null;
$fecha_fin = $_REQUEST['fecha_fin'] ?? null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (isset($_GET['usuario_id'])) {
    $usuario_id = (int)$_GET['usuario_id'];
}
if (isset($_GET['inicio'])) {
    $inicio = $_GET['inicio'];
}
if (isset($_GET['fin'])) {
    $fin = $_GET['fin'];
}

$conditions = [];
$params = [];
$types = '';
if ($usuario_id) {
    $conditions[] = 'cc.usuario_id = ?';
    $params[] = $usuario_id;
    $types .= 'i';
}
// Compatibilidad con parámetros antiguos
if (!$fecha_inicio && $inicio) {
    $fecha_inicio = $inicio;
}
if (!$fecha_fin && $fin) {
    $fecha_fin = $fin;
}
if ($fecha_inicio) {
    $conditions[] = 'DATE(v.fecha_inicio) >= ?';
    $params[] = $fecha_inicio;
    $types .= 's';
}
if ($fecha_fin) {
    $conditions[] = 'DATE(v.fecha_fin) <= ?';
    $params[] = $fecha_fin;
    $types .= 's';
}
$searchParams = [];
if ($search !== '') {
    $conditions[] = "(CAST(v.corte_id AS CHAR) LIKE ? OR v.cajero LIKE ? OR v.fecha_inicio LIKE ? OR v.fecha_fin LIKE ? OR CAST(v.total AS CHAR) LIKE ?)";
    $searchLike = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $searchParams[] = $searchLike;
    }
    $types .= 'sssss';
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$countQuery = "SELECT COUNT(DISTINCT v.corte_id) AS total
               FROM vw_corte_resumen v
               JOIN corte_caja cc ON cc.id = v.corte_id
               LEFT JOIN desglose_corte dc ON dc.corte_id = v.corte_id AND (dc.orden = 'cierre' OR dc.orden IS NULL)
               $where";
$stmt = $conn->prepare($countQuery);
if (!$stmt) {
    error('Error al preparar conteo: ' . $conn->error);
}
if ($params || $search !== '') {
    $bindParams = array_merge($params, $searchParams);
    $stmt->bind_param($types, ...$bindParams);
}
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar conteo: ' . $stmt->error);
}
$res = $stmt->get_result();
$totalRow = $res->fetch_assoc();
$total = (int)$totalRow['total'];
$stmt->close();

$query = "SELECT v.corte_id AS id, v.fecha_inicio, v.fecha_fin, v.total, v.cajero AS usuario,
                 cc.observaciones, cc.fondo_inicial,
                 SUM(CASE WHEN dc.tipo_pago='efectivo' THEN cd.valor*dc.cantidad ELSE 0 END) AS efectivo,
                 SUM(CASE WHEN dc.tipo_pago='boucher' THEN cd.valor*dc.cantidad ELSE 0 END) AS boucher,
                 SUM(CASE WHEN dc.tipo_pago='cheque' THEN cd.valor*dc.cantidad ELSE 0 END) AS cheque
          FROM vw_corte_resumen v
          JOIN corte_caja cc ON cc.id = v.corte_id
          LEFT JOIN desglose_corte dc ON dc.corte_id = v.corte_id AND (dc.orden = 'cierre' OR dc.orden IS NULL)
          LEFT JOIN catalogo_denominaciones cd ON cd.id = dc.denominacion_id
          $where
          GROUP BY v.corte_id
          ORDER BY v.fecha_inicio DESC
          LIMIT ? OFFSET ?"; // Lógica reemplazada por base de datos: ver bd.sql (Vista)

$stmt = $conn->prepare($query);
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$bindParams = array_merge($params, $searchParams, [$limit, $offset]);
$stmt->bind_param($types . 'ii', ...$bindParams);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar consulta: ' . $stmt->error);
}
$res = $stmt->get_result();
$cortes = [];
while ($row = $res->fetch_assoc()) {
    $cortes[] = [
        'id' => (int)$row['id'],
        'fecha_inicio' => $row['fecha_inicio'],
        'fecha_fin' => $row['fecha_fin'],
        'total' => $row['total'] !== null ? (float)$row['total'] : null,
        'usuario' => $row['usuario'],
        'efectivo' => $row['efectivo'] !== null ? (float)$row['efectivo'] : null,
        'boucher' => $row['boucher'] !== null ? (float)$row['boucher'] : null,
        'cheque' => $row['cheque'] !== null ? (float)$row['cheque'] : null,
        'fondo_inicial' => $row['fondo_inicial'] !== null ? (float)$row['fondo_inicial'] : null,
        'observaciones' => $row['observaciones'] ?? ''
    ];
}
$stmt->close();

success(['total' => $total, 'cortes' => $cortes]);
?>

