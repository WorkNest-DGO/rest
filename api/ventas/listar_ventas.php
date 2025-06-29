<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT v.*, m.nombre AS mesa, r.nombre AS repartidor
          FROM ventas v
          LEFT JOIN mesas m ON v.mesa_id = m.id
          LEFT JOIN repartidores r ON v.repartidor_id = r.id
          ORDER BY v.fecha DESC";
$result = $conn->query($query);

if (!$result) {
    error('Error al obtener ventas: ' . $conn->error);
}

$ventas = [];
while ($row = $result->fetch_assoc()) {
    $ventas[] = $row;
}

success($ventas);
?>
