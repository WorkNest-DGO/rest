<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'], $input['subcuentas']) || !is_array($input['subcuentas'])) {
    error('Datos incompletos');
}

$venta_id   = (int)$input['venta_id'];
$subcuentas = $input['subcuentas'];
$sede_id    = isset($input['sede_id']) && !empty($input['sede_id']) ? (int)$input['sede_id'] : 1;

// Obtener datos de la venta, incluyendo el corte asociado
$stmtVenta = $conn->prepare('SELECT mesa_id, usuario_id AS mesero_id, fecha_inicio, tipo_entrega, corte_id FROM ventas WHERE id = ?');
if (!$stmtVenta) {
    error('Error al preparar datos de venta: ' . $conn->error);
}
$stmtVenta->bind_param('i', $venta_id);
if (!$stmtVenta->execute()) {
    $stmtVenta->close();
    error('Error al obtener datos de venta: ' . $stmtVenta->error);
}
$resVenta = $stmtVenta->get_result();
$venta = $resVenta->fetch_assoc();
$stmtVenta->close();
if (!$venta) {
    error('Venta no encontrada');
}

$usuario_id = (int)($venta['mesero_id'] ?? 0);
if (isset($input['usuario_id']) && (int)$input['usuario_id'] !== $usuario_id) {
    http_response_code(400);
    error('La mesa seleccionada pertenece a otro mesero. Actualiza la pantalla e inténtalo de nuevo.');
}

$mesa_nombre = null;
$tipo_entrega = $venta['tipo_entrega'] ?? '';
$corte_id = isset($venta['corte_id']) ? (int)$venta['corte_id'] : null;
if (!$corte_id &&  isset($venta['corte_id'])) {
    error('Venta sin corte asociado');
}
if ($tipo_entrega === 'rapido') {
    $mesa_nombre = 'Venta rápida';
} elseif (!empty($venta['mesa_id'])) {
    $m = $conn->prepare('SELECT nombre FROM mesas WHERE id = ?');
    if ($m) {
        $m->bind_param('i', $venta['mesa_id']);
        if ($m->execute()) {
            $r = $m->get_result()->fetch_assoc();
            $mesa_nombre = $r['nombre'] ?? null;
        }
        $m->close();
    }
}

$mesero_nombre = null;
if (!empty($venta['mesero_id'])) {
    $u = $conn->prepare('SELECT nombre FROM usuarios WHERE id = ?');
    if ($u) {
        $u->bind_param('i', $venta['mesero_id']);
        if ($u->execute()) {
            $r = $u->get_result()->fetch_assoc();
            $mesero_nombre = $r['nombre'] ?? null;
        }
        $u->close();
    }
}

$fecha_inicio = $venta['fecha_inicio'] ?? null;
$fecha_fin = date('Y-m-d H:i:s');
$tiempo_servicio = $fecha_inicio ? (int) ((strtotime($fecha_fin) - strtotime($fecha_inicio)) / 60) : 0;

$stmtSede = $conn->prepare('SELECT nombre, direccion, rfc, telefono, activo FROM sedes WHERE id = ?');
if (!$stmtSede) {
    error('Error al preparar datos de sede: ' . $conn->error);
}
$stmtSede->bind_param('i', $sede_id);
if (!$stmtSede->execute()) {
    $stmtSede->close();
    error('Error al obtener datos de sede: ' . $stmtSede->error);
}
$resSede = $stmtSede->get_result();
$sede = $resSede->fetch_assoc();
$stmtSede->close();
if (!$sede || (isset($sede['activo']) && !$sede['activo'])) {
    $sede_id = 1;
    $stmtSede = $conn->prepare('SELECT nombre, direccion, rfc, telefono, activo FROM sedes WHERE id = ?');
    if ($stmtSede) {
        $stmtSede->bind_param('i', $sede_id);
        if ($stmtSede->execute()) {
            $resSede = $stmtSede->get_result();
            $sede = $resSede->fetch_assoc();
        }
        $stmtSede->close();
    }
    if (!$sede || (isset($sede['activo']) && !$sede['activo'])) {
        error('Sede no válida o inactiva');
    }
}
$nombre_negocio    = $sede['nombre'] ?? '';
$direccion_negocio = $sede['direccion'] ?? '';
$rfc_negocio       = $sede['rfc'] ?? '';
$telefono_negocio  = $sede['telefono'] ?? '';

// Reemplazar nulos por "N/A"
$mesa_nombre      = $mesa_nombre      ?? 'N/A';
$mesero_nombre    = $mesero_nombre    ?? 'N/A';
$nombre_negocio   = $nombre_negocio   ?: 'N/A';
$direccion_negocio= $direccion_negocio?: 'N/A';
$rfc_negocio      = $rfc_negocio      ?: 'N/A';
$telefono_negocio = $telefono_negocio ?: 'N/A';
$tipo_entrega     = $tipo_entrega     ?: 'N/A';

$conn->begin_transaction();

$insTicket  = $conn->prepare('INSERT INTO tickets (venta_id, folio, total, propina, fecha, usuario_id, monto_recibido, tipo_pago, sede_id, mesa_nombre, mesero_nombre, fecha_inicio, fecha_fin, tiempo_servicio, nombre_negocio, direccion_negocio, rfc_negocio, telefono_negocio, tipo_entrega, tarjeta_marca_id, tarjeta_banco_id, boucher, cheque_numero, cheque_banco_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
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
    if ($tipo_pago === 'boucher' || $tipo_pago === 'cheque') {
        $monto_recibido = $total;
    }
    $tarjeta_marca_id = $tarjeta_banco_id = null;
    $cheque_banco_id = null;
    $boucher = $cheque_numero = null;
    if ($tipo_pago === 'boucher') {
        $tarjeta_marca_id = isset($sub['tarjeta_marca_id']) ? (int)$sub['tarjeta_marca_id'] : null;
        $tarjeta_banco_id = isset($sub['tarjeta_banco_id']) ? (int)$sub['tarjeta_banco_id'] : null;
        $boucher = $sub['boucher'] ?? null;
        if (!$tarjeta_marca_id || !$tarjeta_banco_id || !$boucher) {
            $conn->rollback();
            error('Datos de tarjeta incompletos');
        }
    } elseif ($tipo_pago === 'cheque') {
        $cheque_numero = $sub['cheque_numero'] ?? null;
        $cheque_banco_id = isset($sub['cheque_banco_id']) ? (int)$sub['cheque_banco_id'] : null;
        if (!$cheque_numero || !$cheque_banco_id) {
            $conn->rollback();
            error('Datos de cheque incompletos');
        }
    }
    $fecha = date('Y-m-d H:i:s');
    $insTicket->bind_param(
        'iiddsidsissssisssssiissi',
        $venta_id,
        $folio_actual,
        $total,
        $propina,
        $fecha,
        $usuario_id,
        $monto_recibido,
        $tipo_pago,
        $sede_id,
        $mesa_nombre,
        $mesero_nombre,
        $fecha_inicio,
        $fecha_fin,
        $tiempo_servicio,
        $nombre_negocio,
        $direccion_negocio,
        $rfc_negocio,
        $telefono_negocio,
        $tipo_entrega,
        $tarjeta_marca_id,
        $tarjeta_banco_id,
        $boucher,
        $cheque_numero,
        $cheque_banco_id
    );
    if (!$insTicket->execute()) {
        $conn->rollback();
        error('Error al guardar ticket: ' . $insTicket->error);
    }
    $ticket_id = $insTicket->insert_id;

    if ($tipo_pago === 'boucher' || $tipo_pago === 'cheque') {
        $denom_id = $tipo_pago === 'boucher' ? 12 : 13;
        $insDesglose = $conn->prepare('INSERT INTO desglose_corte (corte_id, denominacion, cantidad, tipo_pago, denominacion_id) VALUES (?, ?, ?, ?, ?)');
        if (!$insDesglose) {
            $conn->rollback();
            error('Error al preparar desglose: ' . $conn->error);
        }
        $denom = 1.00;
        $cant = $total;
        $insDesglose->bind_param('iddsi', $corte_id, $denom, $cant, $tipo_pago, $denom_id);
        if (!$insDesglose->execute()) {
            $conn->rollback();
            error('Error al guardar desglose: ' . $insDesglose->error);
        }
        $insDesglose->close();
    }

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
