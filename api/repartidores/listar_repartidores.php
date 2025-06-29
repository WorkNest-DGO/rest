<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT id, nombre FROM repartidores ORDER BY nombre";
$result = $conn->query($query);

if (!$result) {
    error('Error al obtener repartidores: ' . $conn->error);
}

$repartidores = [];
while ($row = $result->fetch_assoc()) {
    $repartidores[] = $row;
}

success($repartidores);
?>
