<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT vw.*, v.tipo_entrega, v.usuario_id
          FROM vw_ventas_detalladas vw
          JOIN ventas v ON v.id = vw.venta_id
          ORDER BY vw.fecha DESC"; // Lógica reemplazada por base de datos: ver bd.sql (Vista)
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
