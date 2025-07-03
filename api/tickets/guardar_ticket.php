<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'], $input['usuario_id'], $input['subcuentas']) || !is_array($input['subcuentas'])) {
    error('Datos incompletos');
}

$venta_id   = (int)$input['venta_id'];
$usuario_id = (int)$input['usuario_id'];
$subcuentas = $input['subcuentas'];

$conn->begin_transaction();

$insTicket  = $conn->prepare('INSERT INTO tickets (venta_id, folio, total, propina, usuario_id, tipo_pago, monto_recibido) VALUES (?, ?, ?, ?, ?, ?, ?)');
$insDetalle = $conn->prepare('INSERT INTO ticket_detalles (ticket_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
if (!$insTicket || !$insDetalle) {
    $conn->rollback();
    error('Error al preparar inserción: ' . $conn->error);
}

$ticketsResp = [];
foreach ($subcuentas as $sub) {
    if (!isset($sub['productos']) || !is_array($sub['productos'])) {
        $conn->rollback();
        error('Subcuenta inválida');
    }
    $serie = isset($sub['serie_id']) ? (int)$sub['serie_id'] : null;
    $folioStmt = $serie
        ? $conn->prepare('SELECT id, folio_actual FROM catalogo_folios WHERE id = ? FOR UPDATE')
        : $conn->prepare('SELECT id, folio_actual FROM catalogo_folios LIMIT 1 FOR UPDATE');
    if (!$folioStmt) {
        $conn->rollback();
        error('Error al preparar folio: ' . $conn->error);
    }
    if ($serie) {
        $folioStmt->bind_param('i', $serie);
    }
    if (!$folioStmt->execute()) {
        $conn->rollback();
        error('Error al obtener folio: ' . $folioStmt->error);
    }
    $resFolio = $folioStmt->get_result();
    $row = $resFolio->fetch_assoc();
    $folioStmt->close();
    if (!$row) {
        $conn->rollback();
        error('Serie de folios no encontrada');
    }
    $catalogo_id = (int)$row['id'];
    $folio_actual = (int)$row['folio_actual'] + 1;

    $propina = isset($sub['propina']) ? (float)$sub['propina'] : 0;
    $total = 0;
    foreach ($sub['productos'] as $p) {
        if (!isset($p['producto_id'], $p['cantidad'], $p['precio_unitario'])) {
            $conn->rollback();
            error('Producto inválido en subcuenta');
        }
        $cantidad = (int)$p['cantidad'];
        $precio   = (float)$p['precio_unitario'];
        $total += $cantidad * $precio;
    }
    $total += $propina;

    $tipo_pago = $sub['tipo_pago'] ?? null;
    $monto_recibido = isset($sub['monto_recibido']) ? (float)$sub['monto_recibido'] : null;
    if (!$tipo_pago || $monto_recibido === null) {
        $conn->rollback();
        error('Tipo de pago o monto recibido faltante');
    }
    $insTicket->bind_param('iiddisd', $venta_id, $folio_actual, $total, $propina, $usuario_id, $tipo_pago, $monto_recibido);
    if (!$insTicket->execute()) {
        $conn->rollback();
        error('Error al guardar ticket: ' . $insTicket->error);
    }
    $ticket_id = $insTicket->insert_id;

    foreach ($sub['productos'] as $p) {
        $producto_id     = (int)$p['producto_id'];
        $cantidad        = (int)$p['cantidad'];
        $precio_unitario = (float)$p['precio_unitario'];
        $insDetalle->bind_param('iiid', $ticket_id, $producto_id, $cantidad, $precio_unitario);
        if (!$insDetalle->execute()) {
            $conn->rollback();
            error('Error al guardar detalle: ' . $insDetalle->error);
        }
    }

    $updFolio = $conn->prepare('UPDATE catalogo_folios SET folio_actual = ? WHERE id = ?');
    if (!$updFolio) {
        $conn->rollback();
        error('Error al preparar actualización de folio: ' . $conn->error);
    }
    $updFolio->bind_param('ii', $folio_actual, $catalogo_id);
    if (!$updFolio->execute()) {
        $conn->rollback();
        error('Error al actualizar folio: ' . $updFolio->error);
    }
    $updFolio->close();

    $ticketsResp[] = [
        'ticket_id' => $ticket_id,
        'folio'     => $folio_actual,
        'total'     => $total,
        'propina'   => $propina
    ];
}

$cerrar = $conn->prepare("UPDATE ventas SET estatus = 'cerrada' WHERE id = ?");
if (!$cerrar) {
    $conn->rollback();
    error('Error al preparar cierre de venta: ' . $conn->error);
}
$cerrar->bind_param('i', $venta_id);
if (!$cerrar->execute()) {
    $conn->rollback();
    error('Error al cerrar venta: ' . $cerrar->error);
}

$conn->commit();

success(['tickets' => $ticketsResp]);
?>
