<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT * FROM ventas ORDER BY fecha DESC";
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
