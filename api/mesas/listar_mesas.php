<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Obtener mesas y, en su caso, la venta activa asociada
$query = "SELECT m.id, m.nombre, m.estado, m.capacidad, m.mesa_principal_id,
                m.area_id, COALESCE(ca.nombre, m.area) AS area_nombre,
                m.estado_reserva, m.nombre_reserva, m.fecha_reserva,
                m.tiempo_ocupacion_inicio, m.usuario_id AS mesa_usuario_id,
                v.id AS venta_id, v.usuario_id AS mesero_id, u.nombre AS mesero_nombre
          FROM mesas m
          LEFT JOIN catalogo_areas ca ON m.area_id = ca.id
          LEFT JOIN ventas v ON v.mesa_id = m.id AND v.estatus = 'activa'
          LEFT JOIN usuarios u ON v.usuario_id = u.id
          ORDER BY area_nombre, m.id";
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
        'area_id'           => $row['area_id'] !== null ? (int)$row['area_id'] : null,
        'area'              => $row['area_nombre'],
        'estado_reserva'    => $row['estado_reserva'],
        'nombre_reserva'    => $row['nombre_reserva'],
        'fecha_reserva'     => $row['fecha_reserva'],
        'tiempo_ocupacion_inicio' => $row['tiempo_ocupacion_inicio'],
        'mesa_usuario_id'   => $row['mesa_usuario_id'] !== null ? (int)$row['mesa_usuario_id'] : null,
        'venta_activa'      => $row['venta_id'] !== null,
        'venta_id'          => $row['venta_id'] !== null ? (int)$row['venta_id'] : null,
        'mesero_id'         => $row['mesero_id'] !== null ? (int)$row['mesero_id'] : null,
        'mesero_nombre'     => $row['mesero_nombre'] ?? null
    ];
}

success($mesas);
?>
