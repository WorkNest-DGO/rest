<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../utils/response.php';

$fecha = $_GET['fecha'] ?? date('Y-m-d');

$stmt = $conn->prepare("SELECT v.usuario_id AS mesero_id, u.nombre AS mesero, SUM(v.total) AS total
                        FROM ventas v
                        JOIN usuarios u ON v.usuario_id = u.id
                        WHERE DATE(v.fecha) = ? AND v.estatus = 'cerrada'
                        GROUP BY v.usuario_id
                        ORDER BY mesero");
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('s', $fecha);
$stmt->execute();
$res = $stmt->get_result();
$datos = [];
while ($row = $res->fetch_assoc()) {
    $datos[] = [
        'mesero_id' => (int)$row['mesero_id'],
        'mesero' => $row['mesero'],
        'total' => (float)$row['total']
    ];
}
$stmt->close();

success($datos);
