<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Obtener mesas y, en su caso, la venta activa asociada
$query = "SELECT m.id, m.nombre, m.estado, m.capacidad, m.mesa_principal_id, v.id AS venta_id
          FROM mesas m
          LEFT JOIN ventas v ON v.mesa_id = m.id AND v.estatus = 'activa'
          ORDER BY m.id ASC";
$result = $conn->query($query);

if (!$result) {
    error('Error al obtener mesas: ' . $conn->error);
}


$mesas = [];
while ($row = $result->fetch_assoc()) {
    $mesas[] = [
        'id'                => (int)$row['id'],
        'nombre'            => $row['nombre'],
        'estado'            => $row['estado'],
        'capacidad'         => (int)$row['capacidad'],
        'mesa_principal_id' => $row['mesa_principal_id'] ? (int)$row['mesa_principal_id'] : null,
        'venta_activa'      => $row['venta_id'] !== null,
        'venta_id'          => $row['venta_id'] !== null ? (int)$row['venta_id'] : null
    ];
}

success($mesas);
?>
