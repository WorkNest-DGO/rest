<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || (!isset($input['folio']) && !isset($input['venta_id']))) {
    error('Se requiere folio o venta_id');
}

if (isset($input['folio'])) {
    $cond = 't.folio = ?';
    $param = (int)$input['folio'];
} else {
    $cond = 't.venta_id = ?';
    $param = (int)$input['venta_id'];
}

$stmt = $conn->prepare("SELECT t.id, t.folio, t.total, t.propina, t.fecha, t.venta_id,
                               t.mesa_nombre, t.mesero_nombre, t.fecha_inicio, t.fecha_fin,
                               t.tiempo_servicio, t.nombre_negocio, t.direccion_negocio,
                               t.rfc_negocio, t.telefono_negocio, t.sede_id
                        FROM tickets t WHERE $cond");
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $param);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar consulta: ' . $stmt->error);
}
$res = $stmt->get_result();
$tickets = [];
while ($t = $res->fetch_assoc()) {
    $det = $conn->prepare("SELECT p.nombre, d.cantidad, d.precio_unitario,
                                 (d.cantidad * d.precio_unitario) AS subtotal
                           FROM ticket_detalles d
                           JOIN productos p ON d.producto_id = p.id
                           WHERE d.ticket_id = ?");
    if (!$det) {
        $stmt->close();
        error('Error al preparar detalle: ' . $conn->error);
    }
    $det->bind_param('i', $t['id']);
    if (!$det->execute()) {
        $det->close();
        $stmt->close();
        error('Error al obtener detalle: ' . $det->error);
    }
    $dres = $det->get_result();
    $prods = [];
    while ($p = $dres->fetch_assoc()) {
        $prods[] = $p;
    }
    $det->close();

    $tickets[] = [
        'ticket_id'        => (int)$t['id'],
        'folio'            => (int)$t['folio'],
        'fecha'            => $t['fecha'],
        'venta_id'         => (int)$t['venta_id'],
        'propina'          => (float)$t['propina'],
        'total'            => (float)$t['total'],
        'mesa_nombre'      => $t['mesa_nombre'],
        'mesero_nombre'    => $t['mesero_nombre'],
        'fecha_inicio'     => $t['fecha_inicio'],
        'fecha_fin'        => $t['fecha_fin'],
        'tiempo_servicio'  => $t['tiempo_servicio'],
        'nombre_negocio'   => $t['nombre_negocio'],
        'direccion_negocio'=> $t['direccion_negocio'],
        'rfc_negocio'      => $t['rfc_negocio'],
        'telefono_negocio' => $t['telefono_negocio'],
        'sede_id'          => $t['sede_id'],
        'productos'        => $prods
    ];
}
$stmt->close();

success(['tickets' => $tickets]);
?>
