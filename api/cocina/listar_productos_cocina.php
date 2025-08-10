<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$sql = "
SELECT
  v.tipo_entrega,
  CASE
    WHEN v.tipo_entrega = 'mesa' THEN m.nombre
    WHEN v.tipo_entrega = 'domicilio' THEN CONCAT('Domicilio - ', r.nombre)
    ELSE 'Venta rápida'
  END AS destino,
  p.nombre AS producto,
  d.cantidad,
  d.created_at AS hora,
  d.estado_producto AS estado,
  d.observaciones,
  d.id AS detalle_id
FROM venta_detalles d
JOIN ventas v ON v.id = d.venta_id
LEFT JOIN mesas m ON m.id = v.mesa_id
LEFT JOIN repartidores r ON r.id = v.repartidor_id
JOIN productos p ON p.id = d.producto_id
WHERE (1=1)
  AND (
    d.estado_producto <> 'entregado'
    OR (d.estado_producto = 'entregado' AND DATE(d.created_at) = CURDATE())
  )
ORDER BY FIELD(d.estado_producto,'pendiente','en_preparacion','listo','entregado'),
         destino, d.created_at
";
$res = $conn->query($sql);
if (!$res) { error('Error al obtener productos: ' . $conn->error); }

$out = [];
while ($row = $res->fetch_assoc()) {
  $out[] = [
    'destino'       => $row['destino'],
    'tipo'          => $row['tipo_entrega'],
    'producto'      => $row['producto'],
    'cantidad'      => (int)$row['cantidad'],
    'hora'          => $row['hora'],
    'estado'        => $row['estado'],
    'observaciones' => $row['observaciones'],
    'detalle_id'    => (int)$row['detalle_id']
  ];
}
success($out);
