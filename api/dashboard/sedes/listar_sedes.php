<?php
require_once __DIR__ . '../../../../config/db.php';
require_once __DIR__ . '/../../../utils/response.php';

$query = "SELECT * FROM sedes ORDER BY nombre ASC";
$result = $conn->query($query);

if (!$result) {
    error('Error al obtener las sedes: ' . $conn->error);
}

$sedes = [];
while ($row = $result->fetch_assoc()) {
    $sedes[] = $row;
}

success($sedes);

