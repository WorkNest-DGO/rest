<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT m.nombre AS mesa, p.nombre AS producto, d.cantidad, d.created_at AS hora, d.estatus_preparacion, d.id AS detalle_id
          FROM venta_detalles d
          JOIN ventas v ON v.id = d.venta_id
          JOIN mesas m ON m.id = v.mesa_id
          JOIN productos p ON p.id = d.producto_id
          WHERE d.estatus_preparacion IN ('pendiente','en preparación')
          ORDER BY m.nombre, d.created_at";
$result = $conn->query($query);
if (!$result) {
    error('Error al obtener productos: ' . $conn->error);
}
$pendientes = [];
while ($row = $result->fetch_assoc()) {
    $pendientes[] = [
        'mesa'       => $row['mesa'],
        'producto'   => $row['producto'],
        'cantidad'   => (int) $row['cantidad'],
        'hora'       => $row['hora'],
        'estatus'    => $row['estatus_preparacion'],
        'detalle_id' => (int) $row['detalle_id']
    ];
}

success($pendientes);
?>
