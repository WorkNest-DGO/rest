<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$query = "SELECT
            v.tipo_entrega,
            CASE
                WHEN v.tipo_entrega = 'mesa' THEN m.nombre
                ELSE CONCAT('Domicilio - ', r.nombre)
            END AS destino,
            p.nombre AS producto,
            d.cantidad,
            d.created_at AS hora,
            d.estatus_preparacion,
            d.id AS detalle_id
          FROM venta_detalles d
          JOIN ventas v ON v.id = d.venta_id
          LEFT JOIN mesas m ON m.id = v.mesa_id
          LEFT JOIN repartidores r ON r.id = v.repartidor_id
          JOIN productos p ON p.id = d.producto_id
          WHERE d.estatus_preparacion IN ('pendiente','en preparación')
          ORDER BY d.created_at";
$result = $conn->query($query);
if (!$result) {
    error('Error al obtener productos: ' . $conn->error);
}
$pendientes = [];
while ($row = $result->fetch_assoc()) {
    $pendientes[] = [
        'destino'    => $row['destino'],
        'tipo'       => $row['tipo_entrega'],
        'producto'   => $row['producto'],
        'cantidad'   => (int) $row['cantidad'],
        'hora'       => $row['hora'],
        'estatus'    => $row['estatus_preparacion'],
        'detalle_id' => (int) $row['detalle_id']
    ];
}

success($pendientes);
?>
