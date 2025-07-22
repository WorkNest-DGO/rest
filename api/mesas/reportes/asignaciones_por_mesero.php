<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../utils/response.php';

$desde = $_GET['desde'] ?? null;
$hasta = $_GET['hasta'] ?? null;
if (!$desde || !$hasta) {
    error('Fechas requeridas');
}

$stmt = $conn->prepare("SELECT l.mesero_nuevo_id AS mesero_id, u.nombre AS mesero, COUNT(*) AS asignaciones
                        FROM log_asignaciones_mesas l
                        JOIN usuarios u ON l.mesero_nuevo_id = u.id
                        WHERE DATE(l.fecha_cambio) BETWEEN ? AND ?
                        GROUP BY l.mesero_nuevo_id
                        ORDER BY mesero");
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('ss', $desde, $hasta);
$stmt->execute();
$res = $stmt->get_result();
$datos = [];
while ($row = $res->fetch_assoc()) {
    $datos[] = [
        'mesero_id' => (int)$row['mesero_id'],
        'mesero' => $row['mesero'],
        'asignaciones' => (int)$row['asignaciones']
    ];
}
$stmt->close();

success($datos);
