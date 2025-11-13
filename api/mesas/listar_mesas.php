<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Obtener mesas y, en su caso, la venta activa asociada
$query = "SELECT m.id, m.nombre, m.estado, m.capacidad, m.mesa_principal_id,
                m.area_id, m.ticket_enviado, COALESCE(ca.nombre, m.area) AS area_nombre,
                m.estado_reserva, m.nombre_reserva, m.fecha_reserva,
                m.tiempo_ocupacion_inicio, m.usuario_id, m.alineacion_id,
                u.nombre AS mesero_nombre,
                v.id AS venta_id
          FROM mesas m
          LEFT JOIN catalogo_areas ca ON m.area_id = ca.id
          LEFT JOIN usuarios u ON m.usuario_id = u.id
          LEFT JOIN ventas v ON v.mesa_id = m.id AND v.estatus = 'activa'
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
        'ticket_enviado'    => (bool)$row['ticket_enviado'],
        'area'              => $row['area_nombre'],
        'estado_reserva'    => $row['estado_reserva'],
        'nombre_reserva'    => $row['nombre_reserva'],
        'fecha_reserva'     => $row['fecha_reserva'],
        'tiempo_ocupacion_inicio' => $row['tiempo_ocupacion_inicio'],
        'usuario_id'        => $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null,
        'alineacion_id'     => $row['alineacion_id'] !== null ? (int)$row['alineacion_id'] : null,
        'venta_activa'      => $row['venta_id'] !== null,
        'venta_id'          => $row['venta_id'] !== null ? (int)$row['venta_id'] : null,
        'mesero_nombre'     => $row['mesero_nombre'] ?? null
    ];
}

success($mesas);
?>
