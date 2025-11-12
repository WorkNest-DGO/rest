<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$repartidor_id = null;

if (isset($_GET['repartidor_id'])) {
    $repartidor_id = (int) $_GET['repartidor_id'];
} elseif (isset($_POST['repartidor_id'])) {
    $repartidor_id = (int) $_POST['repartidor_id'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['repartidor_id'])) {
        $repartidor_id = (int) $input['repartidor_id'];
    }
}

if ($repartidor_id) {
    $stmt = $conn->prepare(
        "SELECT v.id, v.usuario_id, v.repartidor_id, v.fecha, v.total, v.estatus, v.entregado, v.estado_entrega, v.fecha_asignacion, v.fecha_inicio, v.fecha_entrega, v.seudonimo_entrega, v.foto_entrega, v.observacion,
                COALESCE(u.nombre, r.nombre) AS repartidor
           FROM ventas v
      LEFT JOIN usuarios u ON u.id = v.usuario_id AND u.rol = 'repartidor'
      LEFT JOIN repartidores r ON r.id = v.repartidor_id
          WHERE v.repartidor_id = ?
            AND v.estatus IN ('activa','cerrada')
       ORDER BY v.fecha DESC"
    );
    if (!$stmt) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param('i', $repartidor_id);
} else {
    $stmt = $conn->prepare(
        "SELECT v.id, v.usuario_id, v.repartidor_id, v.fecha, v.total, v.estatus, v.entregado, v.estado_entrega, v.fecha_asignacion, v.fecha_inicio, v.fecha_entrega, v.seudonimo_entrega, v.foto_entrega, v.observacion,
                COALESCE(u.nombre, r.nombre) AS repartidor
           FROM ventas v
      LEFT JOIN usuarios u ON u.id = v.usuario_id AND u.rol = 'repartidor'
      LEFT JOIN repartidores r ON r.id = v.repartidor_id
          WHERE v.tipo_entrega = 'domicilio'
            AND v.estatus IN ('activa','cerrada')
       ORDER BY v.fecha DESC"
    );
    if (!$stmt) {
        error('Error al preparar consulta: ' . $conn->error);
    }
}
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar consulta: ' . $stmt->error);
}
$res = $stmt->get_result();
$ventas = [];
while ($row = $res->fetch_assoc()) {
    $ventas[$row['id']] = [
        'id'          => (int)$row['id'],
        'fecha'       => $row['fecha'],
        'usuario_id'  => isset($row['usuario_id']) ? (int)$row['usuario_id'] : null,
        'repartidor_id' => isset($row['repartidor_id']) ? (int)$row['repartidor_id'] : null,
        'total'       => (float)$row['total'],
        'estatus'     => $row['estatus'],
        'entregado'   => (int)$row['entregado'],
        'estado_entrega'   => $row['estado_entrega'],
        'fecha_asignacion' => $row['fecha_asignacion'],
        'fecha_inicio'     => $row['fecha_inicio'],
        'fecha_entrega'    => $row['fecha_entrega'],
        'seudonimo_entrega'=> $row['seudonimo_entrega'],
        'foto_entrega'     => $row['foto_entrega'],
        'observacion'      => $row['observacion'] ?? '',
        'repartidor'       => $row['repartidor'] ?? '',
        'productos' => []
    ];
}
$stmt->close();

if ($ventas) {
    $ids = implode(',', array_keys($ventas));
    $det = $conn->query(
        "SELECT vd.venta_id, p.nombre, vd.cantidad, vd.precio_unitario FROM venta_detalles vd JOIN productos p ON vd.producto_id = p.id WHERE vd.venta_id IN ($ids)"
    );
    if ($det) {
        while ($d = $det->fetch_assoc()) {
            $ventaId = (int)$d['venta_id'];
            if (isset($ventas[$ventaId])) {
                $ventas[$ventaId]['productos'][] = [
                    'nombre' => $d['nombre'],
                    'cantidad' => (int)$d['cantidad'],
                    'precio_unitario' => (float)$d['precio_unitario']
                ];
            }
        }
        $det->free();
    }
}

success(array_values($ventas));
?>
