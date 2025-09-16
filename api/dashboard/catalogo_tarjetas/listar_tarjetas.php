<?php
require_once __DIR__ . '../../../../config/db.php';
require_once __DIR__ . '/../../../utils/response.php';

$query = "SELECT id, nombre FROM catalogo_tarjetas ORDER BY nombre ASC";
$result = $conn->query($query);

if (!$result) {
    error('Error al obtener las tarjetas: ' . $conn->error);
}

$tarjetas = [];
while ($row = $result->fetch_assoc()) {
    $tarjetas[] = $row;
}

success($tarjetas);

