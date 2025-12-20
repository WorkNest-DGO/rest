<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Metodo no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$ticket_id = 0;
if (is_array($input) && isset($input['ticket_id'])) {
    $ticket_id = (int)$input['ticket_id'];
} elseif (isset($_GET['ticket_id'])) {
    $ticket_id = (int)$_GET['ticket_id'];
}

if ($ticket_id <= 0) {
    error('ticket_id requerido');
}

$hdr = null;
$sql = "SELECT t.id, t.folio, t.total, t.descuento, t.monto_recibido, t.tipo_pago, t.venta_id,
               v.repartidor_id
        FROM tickets t
        LEFT JOIN ventas v ON v.id = t.venta_id
        WHERE t.id = ? LIMIT 1";
$st = $conn->prepare($sql);
if (!$st) {
    error('Error al preparar ticket: ' . $conn->error);
}
$st->bind_param('i', $ticket_id);
if (!$st->execute()) {
    $st->close();
    error('Error al consultar ticket: ' . $st->error);
}
if ($res = $st->get_result()) {
    $hdr = $res->fetch_assoc() ?: null;
}
$st->close();
if (!$hdr) {
    error('Ticket no encontrado');
}

$detalles = [];
$sqlDet = "SELECT td.id AS ticket_detalle_id,
                  td.producto_id,
                  COALESCE(p.nombre, CONCAT('Producto ', td.producto_id)) AS descripcion,
                  td.cantidad,
                  td.precio_unitario,
                  p.categoria_id
           FROM ticket_detalles td
           LEFT JOIN productos p ON p.id = td.producto_id
           WHERE td.ticket_id = ?";
$st2 = $conn->prepare($sqlDet);
if (!$st2) {
    error('Error al preparar detalle: ' . $conn->error);
}
$st2->bind_param('i', $ticket_id);
if (!$st2->execute()) {
    $st2->close();
    error('Error al consultar detalle: ' . $st2->error);
}
$res2 = $st2->get_result();
while ($row = $res2->fetch_assoc()) {
    $detalles[] = [
        'ticket_detalle_id' => (int)$row['ticket_detalle_id'],
        'producto_id' => (int)$row['producto_id'],
        'descripcion' => $row['descripcion'] ?? '',
        'cantidad' => (float)$row['cantidad'],
        'precio_unitario' => (float)$row['precio_unitario'],
        'categoria_id' => isset($row['categoria_id']) ? (int)$row['categoria_id'] : null
    ];
}
$st2->close();

success([
    'ticket' => [
        'id' => (int)$hdr['id'],
        'folio' => $hdr['folio'],
        'total' => isset($hdr['total']) ? (float)$hdr['total'] : 0.0,
        'descuento' => isset($hdr['descuento']) ? (float)$hdr['descuento'] : 0.0,
        'monto_recibido' => isset($hdr['monto_recibido']) ? (float)$hdr['monto_recibido'] : null,
        'tipo_pago' => $hdr['tipo_pago'] ?? null,
        'venta_id' => isset($hdr['venta_id']) ? (int)$hdr['venta_id'] : null,
        'repartidor_id' => isset($hdr['repartidor_id']) ? (int)$hdr['repartidor_id'] : null
    ],
    'detalles' => $detalles
]);
?>
