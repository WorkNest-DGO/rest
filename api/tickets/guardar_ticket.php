<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'], $input['subcuentas']) || !is_array($input['subcuentas'])) {
    error('Datos incompletos');
}
$banderaPromo = !empty($input['bandera_promo']);
$descuentoPromo = isset($input['promocion_descuento']) ? (float)$input['promocion_descuento'] : 0.00;
$promoId = isset($input['promocion_id']) ? (int)$input['promocion_id'] : 0;
$promociones_ids = [];
if (isset($input['promociones_ids']) && is_array($input['promociones_ids'])) {
    foreach ($input['promociones_ids'] as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
            $promociones_ids[] = $pid;
        }
    }
    $promociones_ids = array_values(array_unique($promociones_ids));
}
$venta_id   = (int)$input['venta_id'];
$subcuentas = $input['subcuentas'];
// Campos de descuento opcionales desde UI
$descuento_porcentaje_in = isset($input['descuento_porcentaje']) ? (float)$input['descuento_porcentaje'] : 0.0;
$descuento_porcentaje_in = max(0.0, min(100.0, $descuento_porcentaje_in));
$cortesias_in = $input['cortesias'] ?? [];
if (!is_array($cortesias_in)) { $cortesias_in = []; }
$montoActualEditado = isset($input['monto_actual_editado']) ? (float)$input['monto_actual_editado'] : null;
$montoActualCalculado = isset($input['monto_actual_calculado']) ? (float)$input['monto_actual_calculado'] : null;

function obtenerFechaServidor(mysqli $conn) {
    $ahora = date('Y-m-d H:i:s');
    if ($res = $conn->query('SELECT NOW() AS ahora')) {
        $row = $res->fetch_assoc();
        if (!empty($row['ahora'])) {
            $ahora = $row['ahora'];
        }
        $res->close();
    }
    return $ahora;
}

// Obtener datos de la venta, incluyendo el corte asociado
$stmtVenta = $conn->prepare('SELECT v.mesa_id, v.usuario_id AS mesero_id, v.fecha_inicio, v.tipo_entrega, v.corte_id, v.repartidor_id, r.nombre AS repartidor_nombre FROM ventas v LEFT JOIN repartidores r ON r.id = v.repartidor_id WHERE v.id = ?');
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

$usuarioSesion = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
$usuario_id = $usuarioSesion
    ? $usuarioSesion
    : (isset($input['usuario_id']) ? (int)$input['usuario_id'] : (int)($venta['mesero_id'] ?? 0));
if (!$usuario_id) {
    error('Usuario no autenticado');
}

$repartidorNombre = $venta['repartidor_nombre'] ?? '';
$permiteAjustePlataforma = (strtolower(trim($venta['tipo_entrega'] ?? '')) === 'domicilio'
    && in_array(strtolower(trim($repartidorNombre)), ['didi', 'uber', 'rappi']));

//$usuario_id = (int)($venta['mesero_id'] ?? 0);
//if (isset($input['usuario_id']) && (int)$input['usuario_id'] !== $usuario_id) {
  //  http_response_code(400);
    //error('La mesa seleccionada pertenece a otro mesero. Actualiza la pantalla e inténtalo de nuevo.');
//}

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
$fecha_fin = obtenerFechaServidor($conn);
$tiempo_servicio = $fecha_inicio ? (int) ((strtotime($fecha_fin) - strtotime($fecha_inicio)) / 60) : 0;

$stmtSede = $conn->prepare('SELECT u.id, u.sede_id, s.nombre, s.direccion, s.rfc, s.telefono, s.activo, s.serie_id FROM usuarios u LEFT JOIN sedes s ON s.id = u.sede_id WHERE u.id = ?');
if (!$stmtSede) {
    error('Error al preparar datos de sede/serie: ' . $conn->error);
}
$stmtSede->bind_param('i', $usuario_id);
if (!$stmtSede->execute()) {
    $stmtSede->close();
    error('Error al obtener sede del usuario: ' . $stmtSede->error);
}
$resSede = $stmtSede->get_result();
$sede = $resSede->fetch_assoc();
$stmtSede->close();
if (!$sede || !$sede['sede_id'] || isset($sede['activo']) && !(int)$sede['activo']) {
    error('Usuario sin sede asignada o sede inactiva');
}
$sede_id = (int)$sede['sede_id'];
$serieBase = isset($sede['serie_id']) ? (int)$sede['serie_id'] : 0; // serie se deriva de la sede del usuario, ya no del payload/horario
if (!$serieBase) {
    error('Sede sin serie de folios configurada');
}
$query =$conn->prepare("SELECT id FROM tickets WHERE venta_id = ? ORDER BY id asc");
$query->bind_param('i', $venta_id);
if (!$query->execute()) {
    $query->close();
    error('Error al obtener datos de sede: ' . $query->error);
}
$result2 = $query->get_result();
$ticketsss = [];
$contador=0;
while ($row = $result2->fetch_assoc()) {
    $contador=$contador+1;
    $ticketsss[] = (int)$row['id'];
}
// Validación correcta: si la venta ya tiene ticket(s), no permitir recrearlos ni continuar al flujo de cobro
if ($contador > 0) {
    error('La venta ya tiene ticket generado. Solo puede registrar propinas.');
}
if($contador>0){
    $placeholders = implode(',', array_fill(0, count($ticketsss), '?'));    
    $types = str_repeat('i', count($ticketsss));
    $sqlDel = "DELETE FROM ticket_detalles WHERE ticket_id in ($placeholders) ";
    $del = $conn->prepare($sqlDel);
    if (!$del) {
        throw new RuntimeException('Prepare (delete) falló: ' . $conn->error);
    }
    $uno = (int)165;
    $dos = (int)166;
    $del->bind_param($types,...$ticketsss);
    if (!$del->execute()) {
        throw new RuntimeException('Execute (delete) falló: ' . $del->error);
    }
    $del->close();
    $sqlDel2 = "DELETE FROM tickets WHERE id in ( " . $placeholders . ") ";
    $del2 = $conn->prepare($sqlDel2);
    if (!$del2) {
        throw new RuntimeException('Prepare (delete) falló: ' . $conn->error);
    }
    $del2->bind_param($types,...$ticketsss);
    if (!$del2->execute()) {
        throw new RuntimeException('Execute (delete) falló: ' . $del2->error);
    }
    $del2->close();
}

// Recalcular descuentos del lado servidor (no confiamos en front)
$total_bruto = 0.0; $cortesias_total = 0.0; $desc_pct_monto = 0.0; $descuento_total = 0.0; $total_esperado = 0.0;
do {
    $stm = $conn->prepare("SELECT SUM(cantidad * precio_unitario) AS total_bruto FROM venta_detalles WHERE venta_id = ?");
    if (!$stm) { break; }
    $stm->bind_param('i', $venta_id);
    if (!$stm->execute()) { $stm->close(); break; }
    $total_bruto = (float)($stm->get_result()->fetch_assoc()['total_bruto'] ?? 0);
    $stm->close();

    $cortesias = array_values(array_unique(array_map('intval', $cortesias_in)));
    if (count($cortesias) > 0) {
        $place = implode(',', array_fill(0, count($cortesias), '?'));
        $types = str_repeat('i', count($cortesias) + 1);
        $sqlC = "SELECT SUM(cantidad * precio_unitario) AS s FROM venta_detalles WHERE venta_id = ? AND id IN ($place)";
        $stmt = $conn->prepare($sqlC);
        if ($stmt) {
            $params = array_merge([$venta_id], $cortesias);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $cortesias_total = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
            }
            $stmt->close();
        }
    }
    $base = max(0.0, $total_bruto - $cortesias_total);
    $desc_pct_monto = round($base * ($descuento_porcentaje_in / 100.0), 2);
    $descuento_total = round($cortesias_total + $desc_pct_monto, 2);
    $total_esperado = round($total_bruto - $descuento_total, 2);
} while (false);
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

$insTicket  = $conn->prepare('INSERT INTO tickets (venta_id, folio, serie_id, total, fecha, usuario_id, monto_recibido, tipo_pago, sede_id, mesa_nombre, mesero_nombre, fecha_inicio, fecha_fin, tiempo_servicio, nombre_negocio, direccion_negocio, rfc_negocio, telefono_negocio, tipo_entrega, tarjeta_marca_id, tarjeta_banco_id, boucher, cheque_numero, cheque_banco_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insDetalle = $conn->prepare('INSERT INTO ticket_detalles (ticket_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
if (!$insTicket || !$insDetalle) {
    $conn->rollback();
    error('Error al preparar inserción: ' . $conn->error);
}

$subProcesadas = [];
$totalEsperadoGlobal = 0.0;
$fechaAhora = $fecha_fin;

$ticketsResp = [];
foreach ($subcuentas as $sub) {
    if (!isset($sub['productos']) || !is_array($sub['productos'])) {
        $conn->rollback();
        error('Subcuenta inválida');
    }
    $serie = $serieBase; // serie definida por sede del usuario
    if (!$serie) {
        $conn->rollback();
        error('Sede sin serie de folios configurada');
    }
    $folioStmt = $conn->prepare('SELECT id, folio_actual, descripcion FROM catalogo_folios WHERE id = ? FOR UPDATE');
    if (!$folioStmt) {
        $conn->rollback();
        error('Error al preparar folio: ' . $conn->error);
    }
    $folioStmt->bind_param('i', $serie);
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
    $serie_desc  = $row['descripcion'] ?? '';
    // folio a usar = folio_actual actual; luego incrementarlo en tabla
    $folio_insert = (int)$row['folio_actual'];
    $folio_siguiente = $folio_insert + 1;

    // $propina = isset($sub['propina']) ? (float)$sub['propina'] : 0;
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
    //$total += $propina;

    $tipo_pago = $sub['tipo_pago'] ?? null;
    $monto_recibido = isset($sub['monto_recibido']) ? (float)$sub['monto_recibido'] : null;
    if (!$tipo_pago || $monto_recibido === null) {
        $conn->rollback();
        error('Tipo de pago o monto recibido faltante');
    }
    // Descuentos por subcuenta (server-side)
    $detalle_ids = isset($sub['detalle_ids']) && is_array($sub['detalle_ids']) ? array_values(array_unique(array_map('intval', $sub['detalle_ids']))) : [];
    $cortesias_sub = isset($sub['cortesias']) && is_array($sub['cortesias']) ? array_values(array_unique(array_map('intval', $sub['cortesias']))) : [];
    $pct_sub = isset($sub['descuento_porcentaje']) ? max(0.0, min(100.0, (float)$sub['descuento_porcentaje'])) : 0.0;
    $monto_fijo_sub = isset($sub['descuento_monto_fijo']) ? max(0.0, (float)$sub['descuento_monto_fijo']) : 0.0;
    $cortesias_total_sub = 0.0;
    $cortesias_filtradas = is_array($cortesias_sub) ? array_values(array_unique(array_map('intval', $cortesias_sub))) : [];
    // Si llegan detalle_ids, filtramos. Si no, usamos solo las cortesías seleccionadas.
    if ($detalle_ids && is_array($detalle_ids) && count($detalle_ids) > 0) {
        $detalle_ids = array_values(array_unique(array_map('intval', $detalle_ids)));
        $cortesias_filtradas = array_values(array_intersect($cortesias_filtradas, $detalle_ids));
    }
    if (count($cortesias_filtradas) > 0) {
        $place = implode(',', array_fill(0, count($cortesias_filtradas), '?'));
        $types = str_repeat('i', count($cortesias_filtradas) + 1);
        $sqlC = "SELECT SUM(cantidad * precio_unitario) AS s
                 FROM venta_detalles
                 WHERE venta_id = ? AND id IN ($place)";
        if ($stmC = $conn->prepare($sqlC)) {
            $params = array_merge([$venta_id], $cortesias_filtradas);
            $stmC->bind_param($types, ...$params);
            if ($stmC->execute()) {
                $cortesias_total_sub = (float)($stmC->get_result()->fetch_assoc()['s'] ?? 0);
            }
            $stmC->close();
        }
    }
    $base_sub = max(0.0, $total - $cortesias_total_sub);
    $desc_pct_monto_sub = round($base_sub * ($pct_sub/100.0), 2);
    $descuento_total_sub = min($total, round($cortesias_total_sub + $desc_pct_monto_sub + $monto_fijo_sub, 2));
    $total_esperado_sub = max(0.0, round($total - $descuento_total_sub, 2));

    if ($tipo_pago === 'boucher' || $tipo_pago === 'cheque') {
        $monto_recibido = $total_esperado_sub;
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
    // Motivo de descuento por subcuenta (opcional)
    $motivo_desc = null;
    if (isset($sub['desc_des'])) {
        $motivo_desc = trim((string)$sub['desc_des']);
        if ($motivo_desc === '') { $motivo_desc = null; }
    }
    // Primera pasada: almacenar los calculos por subcuenta y continuar.
    $subProcesadas[] = [
        'serie' => $serie,
        'total_bruto' => $total,
        'tipo_pago' => $tipo_pago,
        'monto_recibido_raw' => $monto_recibido,
        'detalle_ids' => $detalle_ids,
        'cortesias_filtradas' => $cortesias_filtradas,
        'cortesias_total_sub' => $cortesias_total_sub,
        'pct_sub' => $pct_sub,
        'monto_fijo_sub' => $monto_fijo_sub,
        'desc_pct_monto_sub' => $desc_pct_monto_sub,
        'descuento_total_sub' => $descuento_total_sub,
        'total_esperado_sub' => $total_esperado_sub,
        'motivo_desc' => $motivo_desc,
        'tarjeta_marca_id' => $tarjeta_marca_id,
        'tarjeta_banco_id' => $tarjeta_banco_id,
        'boucher' => $boucher,
        'cheque_numero' => $cheque_numero,
        'cheque_banco_id' => $cheque_banco_id,
        'productos' => $sub['productos'],
        'fecha' => $fechaAhora
    ];
    $totalEsperadoGlobal += $total_esperado_sub;
}

$promoAplicadoTotal = 0.0;
$conteoSubs = count($subProcesadas);
foreach ($subProcesadas as $idx => &$subCalc) {
    $promoShare = 0.0;
    if ($descuentoPromo > 0 && $totalEsperadoGlobal > 0) {
        if ($idx === $conteoSubs - 1) {
            $promoShare = max(0.0, round($descuentoPromo - $promoAplicadoTotal, 2));
        } else {
            $ratio = $subCalc['total_esperado_sub'] > 0 ? ($subCalc['total_esperado_sub'] / $totalEsperadoGlobal) : 0;
            $promoShare = round($descuentoPromo * $ratio, 2);
        }
    }
    $promoAplicadoTotal += $promoShare;
    $totalCobrar = max(0.0, round($subCalc['total_esperado_sub'] - $promoShare, 2));
    $descuentoFinal = min($subCalc['total_bruto'], round($subCalc['descuento_total_sub'] + $promoShare, 2));
    $montoRecibidoFinal = $subCalc['monto_recibido_raw'];
    if ($subCalc['tipo_pago'] === 'boucher' || $subCalc['tipo_pago'] === 'cheque') {
        $montoRecibidoFinal = $totalCobrar;
    } elseif ($montoRecibidoFinal === null) {
        $montoRecibidoFinal = $totalCobrar;
    }
    // Si hubo promoci�n aplicada en esta subcuenta, registra lo cobrado neto (evita dejar el bruto en monto_recibido).
    if (!empty($subCalc['promo_descuento'])) {
        $montoRecibidoFinal = $totalCobrar;
    }
    if ($montoRecibidoFinal < $totalCobrar) {
        $montoRecibidoFinal = $totalCobrar;
    }
    $subCalc['promo_descuento'] = $promoShare;
    $subCalc['total_cobrar'] = $totalCobrar;
    $subCalc['descuento_final'] = $descuentoFinal;
    $subCalc['monto_ticket'] = $montoRecibidoFinal;
}
unset($subCalc);

$montoOverrideAplicable = ($permiteAjustePlataforma && $montoActualEditado !== null);
if ($montoOverrideAplicable && count($subProcesadas) > 0) {
    $montoDeseado = max(0.0, round((float)$montoActualEditado, 2));
    $sumaCobrar = 0.0;
    foreach ($subProcesadas as $s) {
        $sumaCobrar += (float)($s['total_cobrar'] ?? 0);
    }
    $delta = round($montoDeseado - $sumaCobrar, 2);
    if (abs($delta) >= 0.01) {
        $lastIdx = count($subProcesadas) - 1;
        $nuevoTotal = max(0.0, round(($subProcesadas[$lastIdx]['total_cobrar'] ?? 0) + $delta, 2));
        $subProcesadas[$lastIdx]['total_cobrar'] = $nuevoTotal;
        $subProcesadas[$lastIdx]['monto_ticket'] = $nuevoTotal;
        $subProcesadas[$lastIdx]['total_esperado_sub'] = $nuevoTotal;
    }
    $total_esperado = 0.0;
    foreach ($subProcesadas as $s) {
        $total_esperado += (float)($s['total_cobrar'] ?? 0);
    }
}


$descuentoPromoAplicado = $promoAplicadoTotal > 0 ? round($promoAplicadoTotal, 2) : 0.0;
if ($descuentoPromoAplicado > 0) {
    $descuento_total = min($total_bruto, round($descuento_total + $descuentoPromoAplicado, 2));
    $total_esperado = max(0.0, round($total_bruto - $descuento_total, 2));
    $descuentoPromo = $descuentoPromoAplicado;
}

$promoCatalogoId = 0;
if (count($promociones_ids) === 1) {
    $promoCatalogoId = (int)$promociones_ids[0];
} elseif (!empty($input['promocion_id'])) {
    $promoCatalogoId = (int)$input['promocion_id'];
}

foreach ($subProcesadas as $sub) {
    $serie = $serieBase; // mismo folio para todas las subcuentas, derivado de la sede del usuario
    if (!$serie) {
        $conn->rollback();
        error('Sede sin serie de folios configurada');
    }
    $folioStmt = $conn->prepare('SELECT id, folio_actual, descripcion FROM catalogo_folios WHERE id = ? FOR UPDATE');
    if (!$folioStmt) {
        $conn->rollback();
        error('Error al preparar folio: ' . $conn->error);
    }
    $folioStmt->bind_param('i', $serie);
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
    $serie_desc  = $row['descripcion'] ?? '';
    $folio_insert = (int)$row['folio_actual'];
    $folio_siguiente = $folio_insert + 1;

    // Si hubo ajuste manual (Didi/Uber/Rappi) usamos el total a cobrar calculado/ajustado
    $total = $sub['total_cobrar'];
    $total_esperado_sub = $sub['total_cobrar'];
    $monto_ticket = $sub['monto_ticket'];
    $tipo_pago = $sub['tipo_pago'];
    $tarjeta_marca_id = $sub['tarjeta_marca_id'];
    $tarjeta_banco_id = $sub['tarjeta_banco_id'];
    $boucher = $sub['boucher'];
    $cheque_numero = $sub['cheque_numero'];
    $cheque_banco_id = $sub['cheque_banco_id'];
    $motivo_desc = $sub['motivo_desc'];
    // Forzar fecha del servidor al generar ticket (evita desfaces de cliente)
    $fecha = $fechaAhora;

    $insTicket->bind_param(
        'iiidsidsissssisssssiissi',
        $venta_id,
        $folio_insert,
        $catalogo_id,
        $total,
        $fecha,
        $usuario_id,
        $monto_ticket,
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
    // Guardar descuento total y motivo del descuento (desc_des) en tickets
    if ($motivo_desc !== null) {
        $updDesc = $conn->prepare('UPDATE tickets SET descuento = ?, desc_des = ? WHERE id = ?');
        if ($updDesc) { $updDesc->bind_param('dsi', $sub['descuento_final'], $motivo_desc, $ticket_id); $updDesc->execute(); $updDesc->close(); }
    } else {
        $updDesc = $conn->prepare('UPDATE tickets SET descuento = ?, desc_des = NULL WHERE id = ?');
        if ($updDesc) { $updDesc->bind_param('di', $sub['descuento_final'], $ticket_id); $updDesc->execute(); $updDesc->close(); }
    }
    // Registrar conceptos en ticket_descuentos si existe
    if ($sub['cortesias_total_sub'] > 0 && !empty($sub['cortesias_filtradas'])) {
        if ($insC = $conn->prepare("INSERT INTO ticket_descuentos (ticket_id, tipo, venta_detalle_id, monto, usuario_id, catalogo_promo_id) VALUES (?, 'cortesia', ?, ?, ?, 0)")) {
            foreach ($sub['cortesias_filtradas'] as $vdId) {
                if ($q = $conn->prepare('SELECT (cantidad*precio_unitario) AS sub FROM venta_detalles WHERE id = ? AND venta_id = ?')) {
                    $q->bind_param('ii', $vdId, $venta_id);
                    if ($q->execute()) {
                        $subm = (float)($q->get_result()->fetch_assoc()['sub'] ?? 0);
                        if ($subm > 0) {
                            $insC->bind_param('iidi', $ticket_id, $vdId, $subm, $usuario_id);
                            $insC->execute();
                        }
                    }
                    $q->close();
                }
            }
            $insC->close();
        }
    }
    if ($sub['desc_pct_monto_sub'] > 0) {
        $insP = $conn->prepare("INSERT INTO ticket_descuentos (ticket_id, tipo, porcentaje, monto, usuario_id, catalogo_promo_id) VALUES (?, 'porcentaje', ?, ?, ?, 0)");
        if ($insP) { $insP->bind_param('iddi', $ticket_id, $sub['pct_sub'], $sub['desc_pct_monto_sub'], $usuario_id); $insP->execute(); $insP->close(); }
    }
    if ($sub['monto_fijo_sub'] > 0) {
        $insM = $conn->prepare("INSERT INTO ticket_descuentos (ticket_id, tipo, monto, usuario_id, catalogo_promo_id) VALUES (?, 'monto_fijo', ?, ?, 0)");
        if ($insM) { $insM->bind_param('idi', $ticket_id, $sub['monto_fijo_sub'], $usuario_id); $insM->execute(); $insM->close(); }
    }
    if (!empty($sub['promo_descuento'])) {
        $promoIdRegistro = $promoCatalogoId > 0 ? $promoCatalogoId : 0;
        if ($insPr = $conn->prepare("INSERT INTO ticket_descuentos (ticket_id, tipo, monto, usuario_id, catalogo_promo_id) VALUES (?, 'promocion', ?, ?, ?)"))
        {
            $insPr->bind_param('idii', $ticket_id, $sub['promo_descuento'], $usuario_id, $promoIdRegistro);
            $insPr->execute();
            $insPr->close();
        }
    }

    if ($tipo_pago === 'boucher' || $tipo_pago === 'cheque') {
        $denom_id = $tipo_pago === 'boucher' ? 12 : 13;
        $insDesglose = $conn->prepare('INSERT INTO desglose_corte (corte_id, denominacion, cantidad, tipo_pago, denominacion_id, orden) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$insDesglose) {
            $conn->rollback();
            error('Error al preparar desglose: ' . $conn->error);
        }
        $denom = 1.00;
        $cant = $total_esperado_sub;
        $ordenDesglose = 'cierre';
        $insDesglose->bind_param('iddsis', $corte_id, $denom, $cant, $tipo_pago, $denom_id, $ordenDesglose);
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
        error('Error al preparar actualizacion de folio: ' . $conn->error);
    }
    $updFolio->bind_param('ii', $folio_siguiente, $catalogo_id);
    if (!$updFolio->execute()) {
        $conn->rollback();
        error('Error al actualizar folio: ' . $updFolio->error);
    }
    $updFolio->close();

    $ticketsResp[] = [
        'ticket_id' => $ticket_id,
        'folio'     => $folio_insert,
        'serie_id'  => $catalogo_id,
        'folio_str' => $serie_desc ? ($serie_desc . '-' . $folio_insert) : (string)$folio_insert,
        'total'     => $total
    ];
}

if ($banderaPromo) {
    $promoId = $promoId ?: (int)($input['promocion_id'] ?? 0);
    $descuentoPromo = $descuentoPromoAplicado > 0 ? $descuentoPromoAplicado : (float)($input['promocion_descuento'] ?? 0);
    $updPromoDesc = $conn->prepare('UPDATE ventas SET promocion_id = ? , promocion_descuento = ? WHERE id = ?');
    if ($updPromoDesc) {
            $updPromoDesc->bind_param('idi', $promoId, $descuentoPromo ,$venta_id);
            $updPromoDesc->execute();
            $updPromoDesc->close();
    }
    $conteoPromos = count($promociones_ids);
    if ($conteoPromos > 1) {
        $delPivot = $conn->prepare('DELETE FROM venta_promos WHERE venta_id = ?');
        if ($delPivot) {
            $delPivot->bind_param('i', $venta_id);
            $delPivot->execute();
            $delPivot->close();
        }
        $insPivot = $conn->prepare('INSERT INTO venta_promos (venta_id, promo_id, descuento_aplicado) VALUES (?, ?, ?)');
        if ($insPivot) {
            $ventaRef = $venta_id;
            $promoRef = 0;
            $descRef = 0.0;
            $insPivot->bind_param('iid', $ventaRef, $promoRef, $descRef);
            $perPromo = $conteoPromos > 0 ? round($descuentoPromo / $conteoPromos, 2) : 0.0;
            $acumulado = 0.0;
            foreach ($promociones_ids as $idx => $promoPivotId) {
                $promoRef = (int)$promoPivotId;
                if ($promoRef <= 0) {
                    continue;
                }
                if ($conteoPromos === 1) {
                    $descRef = round($descuentoPromo, 2);
                } else {
                    if ($idx === $conteoPromos - 1) {
                        $descRef = round($descuentoPromo - $acumulado, 2);
                    } else {
                        $descRef = $perPromo;
                        $acumulado += $perPromo;
                    }
                }
                $insPivot->execute();
            }
            $insPivot->close();
        }
    } else {
        $cleanPivot = $conn->prepare('DELETE FROM venta_promos WHERE venta_id = ?');
        if ($cleanPivot) {
            $cleanPivot->bind_param('i', $venta_id);
            $cleanPivot->execute();
            $cleanPivot->close();
        }
    }
}
elseif (!empty($promociones_ids)) {
    $cleanPivot = $conn->prepare('DELETE FROM venta_promos WHERE venta_id = ?');
    if ($cleanPivot) {
        $cleanPivot->bind_param('i', $venta_id);
        $cleanPivot->execute();
        $cleanPivot->close();
    }
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

// Liberar mesa si la venta fue en mesa (después de cerrar la venta y antes del commit)
if (($venta['tipo_entrega'] ?? null) === 'mesa' && !empty($venta['mesa_id'])) {
    // Poner mesa en libre y limpiar banderas de ocupación
    $updMesa = $conn->prepare("
        UPDATE mesas
           SET estado = 'libre',

               tiempo_ocupacion_inicio = NULL,
               ticket_enviado = 0,
               usuario_id = NULL
         WHERE id = ?
    ");
    if (!$updMesa) {
        $conn->rollback();
        error('Error al preparar liberación de mesa: ' . $conn->error);
    }
    $updMesa->bind_param('i', $venta['mesa_id']);
    if (!$updMesa->execute()) {
        $conn->rollback();
        error('Error al liberar mesa: ' . $updMesa->error);
    }
    $updMesa->close();

    // Cerrar el registro de ocupación en log_mesas
    if ($updLog = $conn->prepare("
        UPDATE log_mesas
           SET fecha_fin = NOW()
         WHERE mesa_id = ? AND venta_id = ? AND fecha_fin IS NULL
    ")) {
        $updLog->bind_param('ii', $venta['mesa_id'], $venta_id);
        if (!$updLog->execute()) {
            $conn->rollback();
            error('Error al cerrar log de mesa: ' . $updLog->error);
        }
        $updLog->close();
    }
}

$conn->commit();

success([
    'tickets' => $ticketsResp,
    'total_bruto' => $total_bruto,
    'descuento_porcentaje' => $descuento_porcentaje_in,
    'cortesias_total' => $cortesias_total,
    'descuento_total' => $descuento_total,
    'promocion_id' => $promoId,
    'descuento_promo' => $descuentoPromo,
    'total_esperado' => $total_esperado
]);
?>






