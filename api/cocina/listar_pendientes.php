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
            d.created_at,
            d.estado_producto,
            d.observaciones,
            d.id AS detalle_id
          FROM venta_detalles d
          JOIN ventas v ON v.id = d.venta_id
          LEFT JOIN mesas m ON m.id = v.mesa_id
          LEFT JOIN repartidores r ON r.id = v.repartidor_id
          JOIN productos p ON p.id = d.producto_id
          WHERE d.estado_producto <> 'entregado'
          ORDER BY destino, d.created_at";
$result = $conn->query($query);
if (!$result) {
    error('Error al obtener productos: ' . $conn->error);
}
$pendientes = [];
while ($row = $result->fetch_assoc()) {
    $pendientes[] = [
        'destino'       => $row['destino'],
        'tipo'          => $row['tipo_entrega'],
        'producto'      => $row['producto'],
        'cantidad'      => (int) $row['cantidad'],
        'hora'          => $row['created_at'],
        'estado'        => $row['estado_producto'],
        'observaciones' => $row['observaciones'],
        'detalle_id'    => (int) $row['detalle_id']
    ];
}

success($pendientes);
?>
