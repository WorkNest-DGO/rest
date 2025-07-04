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
            d.id AS detalle_id
          FROM venta_detalles d
          JOIN ventas v ON v.id = d.venta_id
          LEFT JOIN mesas m ON m.id = v.mesa_id
          LEFT JOIN repartidores r ON r.id = v.repartidor_id
          JOIN productos p ON p.id = d.producto_id
          WHERE d.estado_producto = 'entregado' AND DATE(d.created_at) = CURDATE()
          ORDER BY destino, d.created_at DESC";

$result = $conn->query($query);
if (!$result) {
    error('Error al obtener productos: ' . $conn->error);
}

$entregados = [];
while ($row = $result->fetch_assoc()) {
    $entregados[] = [
        'destino'    => $row['destino'],
        'tipo'       => $row['tipo_entrega'],
        'producto'   => $row['producto'],
        'cantidad'   => (int) $row['cantidad'],
        'hora'       => $row['created_at'],
        'detalle_id' => (int) $row['detalle_id']
    ];
}

success($entregados);
?>
