<?php
require_once __DIR__ . '../../../../config/db.php';
require_once __DIR__ . '/../../../utils/response.php';

$query = "SELECT id, nombre FROM catalogo_bancos ORDER BY nombre ASC";
$result = $conn->query($query);

if (!$result) {
    error('Error al obtener los bancos: ' . $conn->error);
}

$bancos = [];
while ($row = $result->fetch_assoc()) {
    $bancos[] = $row;
}

success($bancos);

