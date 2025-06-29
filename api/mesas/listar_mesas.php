<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT id, nombre, estado, capacidad FROM mesas ORDER BY id ASC";
$result = $conn->query($query);

if (!$result) {
    error('Error al obtener mesas: ' . $conn->error);
}

$mesas = [];
while ($row = $result->fetch_assoc()) {
    $mesas[] = $row;
}

success($mesas);
?>
