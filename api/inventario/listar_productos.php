<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT id, nombre, precio FROM productos ORDER BY nombre";
$result = $conn->query($query);

if (!$result) {
    error('Error al obtener productos: ' . $conn->error);
}

$productos = [];
while ($row = $result->fetch_assoc()) {
    $productos[] = $row;
}

success($productos);
