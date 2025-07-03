<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$usuario_id = null;
$inicio = null;
$fin = null;

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
if ($inicio) {
    $conditions[] = 'cc.fecha_inicio >= ?';
    $params[] = $inicio;
    $types .= 's';
}
if ($fin) {
    $conditions[] = 'cc.fecha_inicio <= ?';
    $params[] = $fin;
    $types .= 's';
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$query = "SELECT v.corte_id AS id, v.fecha_inicio, v.fecha_fin, v.total, v.cajero AS usuario,
                 cc.observaciones, cc.fondo_inicial,
                 SUM(CASE WHEN dc.tipo_pago='efectivo' THEN dc.denominacion*dc.cantidad ELSE 0 END) AS efectivo,
                 SUM(CASE WHEN dc.tipo_pago='boucher' THEN dc.denominacion*dc.cantidad ELSE 0 END) AS boucher,
                 SUM(CASE WHEN dc.tipo_pago='cheque' THEN dc.denominacion*dc.cantidad ELSE 0 END) AS cheque
          FROM vw_corte_resumen v
          JOIN corte_caja cc ON cc.id = v.corte_id
          LEFT JOIN desglose_corte dc ON dc.corte_id = v.corte_id
          $where
          GROUP BY v.corte_id
          ORDER BY v.fecha_inicio DESC"; // Lógica reemplazada por base de datos: ver bd.sql (Vista)

$stmt = $conn->prepare($query);
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
if ($params) {
    $stmt->bind_param($types, ...$params);
}
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

success($cortes);
?>
