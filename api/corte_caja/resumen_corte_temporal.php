<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Sesi��n no iniciada'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$corte_id = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : ($_SESSION['corte_id'] ?? null);
if (!$corte_id) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'No se especific�� corte_id'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->set_charset('utf8mb4');
$resultado = [
    'resumen' => [],
];

/**
 * 1) Datos b���sicos del corte (fondo, fechas, cajero)
 */
$sqlCorte = "SELECT 
                c.fondo_inicial,
                c.fecha_inicio,
                c.fecha_fin,
                u.nombre AS cajero
             FROM corte_caja c
             JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.id = ?";
$stmt = $conn->prepare($sqlCorte);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowCorte = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$fondoInicial = (float)($rowCorte['fondo_inicial'] ?? 0);
$resultado['fondo_inicial'] = $fondoInicial;
$resultado['fecha_inicio']  = $rowCorte['fecha_inicio'] ?? null;
$resultado['fecha_fin']     = $rowCorte['fecha_fin'] ?? null;
$resultado['cajero']        = $rowCorte['cajero'] ?? null;

/**
 * 2) Totales de venta (bruto, descuentos, cortes���as, neto, IVA)
 */
$sqlTotales = "SELECT
    COALESCE(SUM(vtd.total_bruto), 0)          AS total_bruto,
    COALESCE(SUM(vtd.descuento_total), 0)      AS total_descuentos,
    COALESCE(SUM(vtd.total_esperado), 0)       AS venta_con_impuesto,
    COALESCE(SUM(vtd.descuento_cortesias), 0)  AS total_cortesias
FROM vw_tickets_con_descuentos vtd
JOIN ventas v ON v.id = vtd.venta_id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?";

$stmt = $conn->prepare($sqlTotales);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowTot = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$totalBruto         = (float)$rowTot['total_bruto'];
$totalDescuentos    = (float)$rowTot['total_descuentos'];
$ventaConImpuesto   = (float)$rowTot['venta_con_impuesto'];
$totalCortesias     = (float)$rowTot['total_cortesias'];

// Venta neta (sin IVA) e IVA al 16%
$ventaNeta  = $ventaConImpuesto > 0 ? $ventaConImpuesto / 1.16 : 0;
$iva        = $ventaConImpuesto - $ventaNeta;

$resultado['total_bruto']         = round($totalBruto, 2);
$resultado['total_descuentos']    = round($totalDescuentos, 2);
$resultado['total_cortesias']     = round($totalCortesias, 2);
$resultado['venta_con_impuesto']  = round($ventaConImpuesto, 2);
$resultado['venta_neta']          = round($ventaNeta, 2);
$resultado['iva']                 = round($iva, 2);

/**
 * 3) Totales por producto: Alimentos vs Bebidas
 */
$sqlProd = "SELECT
    COALESCE(SUM(CASE WHEN cc.nombre IN ('Bebida','Alcohol') THEN vd.subtotal ELSE 0 END), 0) AS total_bebidas,
    COALESCE(SUM(CASE WHEN cc.nombre NOT IN ('Bebida','Alcohol') OR cc.id IS NULL THEN vd.subtotal ELSE 0 END), 0) AS total_alimentos
FROM ventas v
JOIN venta_detalles vd ON vd.venta_id = v.id
JOIN productos p ON p.id = vd.producto_id
LEFT JOIN catalogo_categorias cc ON cc.id = p.categoria_id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?";

$stmt = $conn->prepare($sqlProd);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowProd = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$totalBebidas   = (float)$rowProd['total_bebidas'];
$totalAlimentos = (float)$rowProd['total_alimentos'];

$resultado['productos'] = [
    'alimentos' => round($totalAlimentos, 2),
    'bebidas'   => round($totalBebidas, 2),
    'total'     => round($totalAlimentos + $totalBebidas, 2)
];

/**
 * 4) Totales por servicio (tipo_entrega): mesa/comedor, domicilio, r���pido
 */
$sqlServ = "SELECT
    v.tipo_entrega,
    COALESCE(SUM(vd.subtotal), 0) AS total
FROM ventas v
JOIN venta_detalles vd ON vd.venta_id = v.id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?
GROUP BY v.tipo_entrega";

$stmt = $conn->prepare($sqlServ);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rsServ = $stmt->get_result();

$porServicio = [
    'mesa'      => 0.0,  // Comedor
    'domicilio' => 0.0,
    'rapido'    => 0.0
];

while ($row = $rsServ->fetch_assoc()) {
    $tipo = $row['tipo_entrega'];
    $monto = (float)$row['total'];
    if (isset($porServicio[$tipo])) {
        $porServicio[$tipo] += $monto;
    }
}
$stmt->close();

$resultado['por_servicio'] = [
    'comedor'   => round($porServicio['mesa'], 2),
    'domicilio' => round($porServicio['domicilio'], 2),
    'rapido'    => round($porServicio['rapido'], 2)
];

/**
 * 5) Formas de pago y plataformas (Didi / Rappi / Uber)
 *
 *   - efectivo: tickets en efectivo que NO son didi/rappi/uber
 *   - tarjeta: tickets con tipo_pago IN ('cheque','boucher') que NO son didi/rappi/uber
 *   - didi/rappi/uber: total del ticket (venta con impuesto) para esos pedidos
 */

// Mapeo de repartidor_id a llave amigable
// 1 = Didi, 2 = Rappi, 3 = Uber
$mapRepartidor = [
    1 => 'didi',
    2 => 'rappi',
    3 => 'uber'
];

// Acumuladores que despu���s usa la secci���n 9
$tarjetaFormas     = 0.0; // total neto de tarjeta (NO plataformas)
$otrosPlataformas  = 0.0; // total neto de Didi/Rappi/Uber

/**
 * 5.a) Formas de pago (SIN plataformas)
 *
 * Agrupa por tipo_pago, excluyendo ventas con repartidor_id IN (1,2,3)
 */
$sqlFormas = "
    SELECT
        t.tipo_pago,
        COALESCE(SUM(t.total), 0)                AS total_bruto,
        COALESCE(SUM(t.descuento), 0)            AS total_descuento,
        COALESCE(SUM(t.total - t.descuento), 0)  AS total_neto
    FROM ventas v
    JOIN tickets t ON t.venta_id = v.id
    WHERE v.estatus = 'cerrada'
      AND v.corte_id = ?      
    GROUP BY t.tipo_pago
";

$stmt = $conn->prepare($sqlFormas);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rsFormas = $stmt->get_result();
$stmt->close();

// Estructura base
$formasPagoResumen = [
    'efectivo' => ['total_bruto' => 0.0, 'total_descuento' => 0.0, 'total_neto' => 0.0],
    'tarjeta'  => ['total_bruto' => 0.0, 'total_descuento' => 0.0, 'total_neto' => 0.0],
    'otros'    => ['total_bruto' => 0.0, 'total_descuento' => 0.0, 'total_neto' => 0.0],
];

while ($row = $rsFormas->fetch_assoc()) {
    $tipo  = strtolower($row['tipo_pago']);
    $bruto = (float)$row['total_bruto'];
    $desc  = (float)$row['total_descuento'];
    $neto  = (float)$row['total_neto'];

    if ($tipo === 'efectivo') {
        $clave = 'efectivo';
    } elseif (in_array($tipo, ['boucher', 'tarjeta'], true)) {
        $clave = 'tarjeta';
    } else {
        $clave = 'otros';
    }

    $formasPagoResumen[$clave]['total_bruto']     += $bruto;
    $formasPagoResumen[$clave]['total_descuento'] += $desc;
    $formasPagoResumen[$clave]['total_neto']      += $neto;

    // Para el saldo final (secci���n 9) s���lo nos interesa el neto
    if ($clave === 'tarjeta') {
        $tarjetaFormas += $neto;
    }
}

// Redondeo final
foreach ($formasPagoResumen as &$fp) {
    $fp['total_bruto']     = round($fp['total_bruto'], 2);
    $fp['total_descuento'] = round($fp['total_descuento'], 2);
    $fp['total_neto']      = round($fp['total_neto'], 2);
}
unset($fp);

$resultado['formas_pago_resumen'] = $formasPagoResumen;

/**
 * 5.b) RESUMEN POR PLATAFORMA (Didi / Rappi / Uber)
 *
 *   total_bruto     = SUM(t.total)
 *   total_descuento = SUM(t.descuento)
 *   total_neto      = SUM(t.total - t.descuento)
 */
$sqlResumenPlat = "
    SELECT
        v.repartidor_id,
        r.nombre AS repartidor,
        COALESCE(SUM(t.total), 0)                AS total_bruto,
        COALESCE(SUM(t.descuento), 0)            AS total_descuento,
        COALESCE(SUM(t.total - t.descuento), 0)  AS total_neto
    FROM ventas v
    JOIN tickets t      ON t.venta_id = v.id
    LEFT JOIN repartidores r ON r.id = v.repartidor_id
    WHERE v.estatus = 'cerrada'
      AND v.corte_id = ?
      AND v.repartidor_id IN (1,2,3)    -- Didi, Rappi, Uber
    GROUP BY v.repartidor_id, r.nombre
";

$stmt = $conn->prepare($sqlResumenPlat);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rsRes = $stmt->get_result();
$stmt->close();

// Usamos el ���ndice 'resumen' como en corte_soft
while ($row = $rsRes->fetch_assoc()) {
    $repId = (int)$row['repartidor_id'];
    $clave = $mapRepartidor[$repId] ?? ('rep_' . $repId);

    $totalBrutoRep     = (float)$row['total_bruto'];
    $totalDescuentoRep = (float)$row['total_descuento'];
    $totalNetoRep      = (float)$row['total_neto'];

    $resultado['resumen'][$clave] = [
        'repartidor_id'   => $repId,
        'nombre'          => $row['repartidor'] ?? $clave,
        'total_bruto'     => round($totalBrutoRep, 2),
        'total_descuento' => round($totalDescuentoRep, 2),
        'total_neto'      => round($totalNetoRep, 2)
    ];

    // Neto de plataformas para la secci���n 9
    $otrosPlataformas += $totalNetoRep;
}

/**
 * 6) Dep���sitos y retiros (movimientos de caja)
 */
$sqlMov = "SELECT
    COALESCE(SUM(CASE WHEN tipo_movimiento = 'deposito' THEN monto ELSE 0 END),0) AS total_depositos,
    COALESCE(SUM(CASE WHEN tipo_movimiento = 'retiro'   THEN monto ELSE 0 END),0) AS total_retiros
FROM movimientos_caja
WHERE corte_id = ?";

$stmt = $conn->prepare($sqlMov);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowMov = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$totalDepositos = (float)$rowMov['total_depositos'];
$totalRetiros   = (float)$rowMov['total_retiros'];

$resultado['movimientos_caja'] = [
    'depositos' => round($totalDepositos, 2),
    'retiros'   => round($totalRetiros, 2)
];

/**
 * 7) Propinas por tipo de pago
 */
$sqlProp = "SELECT
    COALESCE(SUM(propina_efectivo), 0) AS propina_efectivo,
    COALESCE(SUM(propina_cheque), 0)   AS propina_cheque,
    COALESCE(SUM(propina_tarjeta), 0)  AS propina_tarjeta
FROM ventas
WHERE estatus = 'cerrada'
  AND corte_id = ?";

$stmt = $conn->prepare($sqlProp);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowProp = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$propEfec   = (float)$rowProp['propina_efectivo'];
$propCheq   = (float)$rowProp['propina_cheque'];
$propTjt    = (float)$rowProp['propina_tarjeta'];
$totalProp  = $propEfec + $propCheq + $propTjt;

$resultado['propinas'] = [
    'efectivo' => round($propEfec, 2),
    'cheque'   => round($propCheq, 2),
    'tarjeta'  => round($propTjt, 2),
    'total'    => round($totalProp, 2)
];
// Alias para compatibilidad con vistas
$resultado['total_propina_efectivo'] = round($propEfec, 2);
$resultado['total_propina_cheque']   = round($propCheq, 2);
$resultado['total_propina_tarjeta']  = round($propTjt, 2);
$resultado['total_propinas']         = round($totalProp, 2);

/**
 * Extra A) Cuentas por estatus (abiertas/cerradas)
 */
$sqlCuentas = "SELECT
    COALESCE(SUM(CASE WHEN v.estatus = 'activa' THEN 1 ELSE 0 END), 0)  AS cuentas_abiertas,
    COALESCE(SUM(CASE WHEN v.estatus = 'cerrada' THEN 1 ELSE 0 END), 0) AS cuentas_cerradas,
    COALESCE(SUM(CASE WHEN v.estatus = 'activa' THEN v.total ELSE 0 END), 0)  AS total_abiertas,
    COALESCE(SUM(CASE WHEN v.estatus = 'cerrada' THEN v.total ELSE 0 END), 0) AS total_cerradas
  FROM ventas v
 WHERE v.corte_id = ?";
$stmt = $conn->prepare($sqlCuentas);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowCuentas = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$resultado['cuentas_por_estatus'] = [
    'abiertas' => [
        'cantidad' => (int)($rowCuentas['cuentas_abiertas'] ?? 0),
        'total'    => round((float)($rowCuentas['total_abiertas'] ?? 0), 2)
    ],
    'cerradas' => [
        'cantidad' => (int)($rowCuentas['cuentas_cerradas'] ?? 0),
        'total'    => round((float)($rowCuentas['total_cerradas'] ?? 0), 2)
    ],
];
$resultado['cuentas_activas']           = $resultado['cuentas_por_estatus']['abiertas']['cantidad'];
$resultado['total_cuentas_activas']     = $resultado['cuentas_por_estatus']['abiertas']['total'];
$resultado['cuentas_canceladas']        = $resultado['cuentas_por_estatus']['cerradas']['cantidad'];
$resultado['total_cuentas_canceladas']  = $resultado['cuentas_por_estatus']['cerradas']['total'];

/**
 * Extra B) Totales por mesero
 */
$sqlMeseros = "SELECT
    TRIM(u.nombre) AS nombre,
    COALESCE(SUM(vtd.total_esperado), 0) AS total_neto
FROM ventas v
JOIN vw_tickets_con_descuentos vtd ON vtd.venta_id = v.id
LEFT JOIN usuarios u ON u.id = v.usuario_id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?
GROUP BY u.nombre";

$stmt = $conn->prepare($sqlMeseros);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rsMeseros = $stmt->get_result();
$stmt->close();

$meseros = [];
while ($row = $rsMeseros->fetch_assoc()) {
    $nombreMesero = trim($row['nombre'] ?? '');
    if ($nombreMesero === '') {
        $nombreMesero = 'Sin mesero';
    }
    $totalNeto = round((float)$row['total_neto'], 2);
    $meseros[] = [
        'nombre'      => $nombreMesero,
        'total_neto'  => $totalNeto,
        'total'       => $totalNeto
    ];
}
$resultado['totales_mesero'] = $meseros;
$resultado['total_meseros']  = $meseros; // alias para la vista

/**
 * Extra C) Totales por repartidor (usuarios con rol repartidor / env��o casa)
 * Se consideran solo las ventas cerradas con repartidor_id = 4 (Repartidor casa)
 * y usuario_id con rol repartidor.
 */
$sqlRepartidorCasa = "SELECT
    u.id AS usuario_id,
    TRIM(u.nombre) AS nombre,
    COALESCE(SUM(vtd.total_esperado), 0) AS total_neto
FROM ventas v
JOIN vw_tickets_con_descuentos vtd ON vtd.venta_id = v.id
JOIN usuarios u ON u.id = v.usuario_id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?
  AND v.repartidor_id = 4
  AND u.rol = 'repartidor'
GROUP BY u.id, u.nombre";

$stmt = $conn->prepare($sqlRepartidorCasa);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rsRepartidor = $stmt->get_result();
$stmt->close();

$repartidoresCasa = [];
while ($row = $rsRepartidor->fetch_assoc()) {
    $totalNeto = round((float)$row['total_neto'], 2);
    $repartidoresCasa[] = [
        'usuario_id' => (int)$row['usuario_id'],
        'nombre'     => $row['nombre'],
        'total_neto' => $totalNeto,
        'total'      => $totalNeto
    ];
}
$resultado['totales_repartidor'] = $repartidoresCasa;
$resultado['total_repartidor']   = $repartidoresCasa; // alias esperado por la vista

/**
 * Extra D) Promociones aplicadas
 */
$sqlPromos = "SELECT
    COALESCE(SUM(v.promocion_descuento), 0) AS total_promociones,
    COALESCE(SUM(CASE WHEN v.promocion_descuento > 0 THEN 1 ELSE 0 END), 0) AS ventas_con_promocion
FROM ventas v
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?";
$stmt = $conn->prepare($sqlPromos);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowPromos = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$resultado['promociones_aplicadas'] = [
    'total_descuento' => round((float)($rowPromos['total_promociones'] ?? 0), 2),
    'ventas_con_promocion' => (int)($rowPromos['ventas_con_promocion'] ?? 0)
];
$resultado['total_descuento_promos'] = $resultado['promociones_aplicadas']['total_descuento'];

/**
 * Extra E) Total por venta r��pida (mostrador)
 */
$sqlRapido = "SELECT
    COALESCE(SUM(vtd.total_esperado), 0) AS total_rapido
FROM ventas v
JOIN vw_tickets_con_descuentos vtd ON vtd.venta_id = v.id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?
  AND v.tipo_entrega = 'rapido'";
$stmt = $conn->prepare($sqlRapido);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowRapido = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$resultado['total_rapido'] = round((float)($rowRapido['total_rapido'] ?? 0), 2);

/**
 * 8) Folios: inicio, fin, total de cuentas
 */
$sqlFol = "SELECT
    MIN(t.folio) AS folio_inicio,
    MAX(t.folio) AS folio_fin,
    COUNT(*)     AS total_folios
FROM ventas v
JOIN tickets t ON t.venta_id = v.id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?";

$stmt = $conn->prepare($sqlFol);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowFol = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$resultado['folios'] = [
    'inicio' => isset($rowFol['folio_inicio']) ? (int)$rowFol['folio_inicio'] : null,
    'fin'    => isset($rowFol['folio_fin']) ? (int)$rowFol['folio_fin'] : null,
    'total'  => isset($rowFol['total_folios']) ? (int)$rowFol['total_folios'] : 0
];

/**
 * 9) Efectivo final en caja y saldo final
 *
 *    - efectivo_bruto_cobrado: TODO lo cobrado en efectivo (incluye plataformas)
 *    - efectivo_en_caja: fondo + efectivo_bruto - dep���sitos - retiros
 *    - saldo_final: efectivo_en_caja + tarjeta (no plataformas) + otros (Didi+Rappi+Uber)
 */
$sqlEfBruto = "SELECT 
    COALESCE(SUM(t.monto_recibido),0) AS efectivo_bruto
FROM ventas v
JOIN tickets t ON t.venta_id = v.id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?
  AND t.tipo_pago = 'efectivo'";

$stmt = $conn->prepare($sqlEfBruto);
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$rowEf = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$efectivoBruto = (float)$rowEf['efectivo_bruto'];

$efectivoEnCaja = $fondoInicial + $efectivoBruto + $totalDepositos - $totalRetiros;
$saldoFinal     = $efectivoEnCaja + $tarjetaFormas + $otrosPlataformas  + $totalProp;

$resultado['efectivo_caja'] = round($efectivoEnCaja, 2);
$resultado['saldo_final']   = round($saldoFinal, 2);

// Alias y campos esperados por la vista
$resultado['fondo']            = round($fondoInicial, 2);
$resultado['total_depositos']  = round($totalDepositos, 2);
$resultado['total_retiros']    = round($totalRetiros, 2);
$resultado['totalFinalEfectivo']  = $resultado['efectivo_caja'];
$resultado['totalFinalGeneral']   = $resultado['saldo_final'];
$resultado['corte_id'] = $corte_id;

// Totales de productos / cobrados (compatibilidad)
$resultado['total_productos'] = $resultado['productos']['total'];
$resultado['total_cobrado']   = round($ventaConImpuesto, 2);
$resultado['total_esperado']  = round($ventaConImpuesto, 2);

// Esperados (con propinas)
$esperadoEfectivo    = (float)($formasPagoResumen['efectivo']['total_neto'] ?? 0) ;
$esperadoTarjeta     = (float)($formasPagoResumen['tarjeta']['total_neto'] ?? 0);
$esperadoOtros       = (float)($formasPagoResumen['otros']['total_neto'] ?? 0) ;
$resultado['totalEsperado']          = round($ventaConImpuesto + $totalProp, 2);
$resultado['totalEsperadoEfectivo']  = round($esperadoEfectivo + $propEfec, 2);
$resultado['totalEsperadoNoEfectivo']= round(($ventaConImpuesto - $esperadoEfectivo) + ($totalProp - $propEfec), 2);
$resultado['esperado_efectivo']      = $resultado['totalEsperadoEfectivo'];
$resultado['esperado_tarjeta']       = round($esperadoTarjeta + $propTjt, 2);
$resultado['esperado_cheque']        = round($esperadoOtros + $propCheq, 2);
$resultado['esperado_boucher']       = 0.0;
$resultado['esperado_transferencia'] = 0.0;

// Totales por forma de pago simples para la vista
$resultado['efectivo'] = ['total' => round($esperadoEfectivo, 2)];
$resultado['tarjeta']  = ['total' => round($esperadoTarjeta, 2)];
$resultado['boucher']  = ['total' => 0.0];
$resultado['cheque']   = ['total' => 0.0];
$resultado['transferencia'] = ['total' =>round($esperadoOtros, 2)];

// Folios planos
$resultado['folio_inicio'] = $resultado['folios']['inicio'];
$resultado['folio_fin']    = $resultado['folios']['fin'];
$resultado['total_folios'] = $resultado['folios']['total'];

echo json_encode([
    'success'   => true,
    'resultado' => $resultado
], JSON_UNESCAPED_UNICODE);
