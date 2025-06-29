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
    $conditions[] = 'c.usuario_id = ?';
    $params[] = $usuario_id;
    $types .= 'i';
}
if ($inicio) {
    $conditions[] = 'c.fecha_inicio >= ?';
    $params[] = $inicio;
    $types .= 's';
}
if ($fin) {
    $conditions[] = 'c.fecha_inicio <= ?';
    $params[] = $fin;
    $types .= 's';
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$query = "SELECT c.id, c.fecha_inicio, c.fecha_fin, c.total, u.nombre AS usuario
           FROM corte_caja c
           JOIN usuarios u ON c.usuario_id = u.id
           $where
           ORDER BY c.fecha_inicio DESC";

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
        'usuario' => $row['usuario']
    ];
}
$stmt->close();

success($cortes);
?>
