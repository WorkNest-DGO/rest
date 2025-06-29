<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT e.id, p.nombre AS proveedor, e.fecha, e.total FROM entradas_insumo e JOIN proveedores p ON e.proveedor_id = p.id ORDER BY e.fecha DESC";
$result = $conn->query($query);

if (!$result) {
    error('Error al obtener entradas: ' . $conn->error);
}

$entradas = [];
while ($row = $result->fetch_assoc()) {
    $entradas[] = $row;
}

success($entradas);
