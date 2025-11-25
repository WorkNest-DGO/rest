<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Sesión no iniciada'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$corte_id = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : ($_SESSION['corte_id'] ?? null);
if (!$corte_id) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'No se especificó corte_id'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->set_charset('utf8mb4');
$resultado = [
    'resumen' => [],
];

/**
 * 1) Datos básicos del corte (fondo, fechas, cajero)
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
 * 2) Totales de venta (bruto, descuentos, cortesías, neto, IVA)
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
 * 4) Totales por servicio (tipo_entrega): mesa/comedor, domicilio, rápido
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

// Acumuladores que después usa la sección 9
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
      AND (v.repartidor_id IS NULL OR v.repartidor_id NOT IN (1,2,3))
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
    } elseif (in_array($tipo, ['cheque', 'boucher', 'tarjeta'], true)) {
        $clave = 'tarjeta';
    } else {
        $clave = 'otros';
    }

    $formasPagoResumen[$clave]['total_bruto']     += $bruto;
    $formasPagoResumen[$clave]['total_descuento'] += $desc;
    $formasPagoResumen[$clave]['total_neto']      += $neto;

    // Para el saldo final (sección 9) sólo nos interesa el neto
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

// Usamos el índice 'resumen' como en tu snippet
while ($row = $rsRes->fetch_assoc()) {
    $repId = (int)$row['repartidor_id'];
    $clave = $mapRepartidor[$repId] ?? ('rep_' . $repId);

    $totalBruto     = (float)$row['total_bruto'];
    $totalDescuento = (float)$row['total_descuento'];
    $totalNeto      = (float)$row['total_neto'];

    $resultado['resumen'][$clave] = [
        'repartidor_id'   => $repId,
        'nombre'          => $row['repartidor'] ?? $clave,
        'total_bruto'     => round($totalBruto, 2),
        'total_descuento' => round($totalDescuento, 2),
        'total_neto'      => round($totalNeto, 2)
    ];

    // Neto de plataformas para la sección 9
    $otrosPlataformas += $totalNeto;
}

/**
 * 6) Depósitos y retiros (movimientos de caja)
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
 *    - efectivo_en_caja: fondo + efectivo_bruto - depósitos - retiros
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

$efectivoEnCaja = $fondoInicial + $efectivoBruto - $totalDepositos - $totalRetiros;
$saldoFinal     = $efectivoEnCaja + $tarjetaFormas + $otrosPlataformas;

$resultado['efectivo_caja'] = round($efectivoEnCaja, 2);
$resultado['saldo_final']   = round($saldoFinal, 2);

echo json_encode([
    'success'   => true,
    'resultado' => $resultado
], JSON_UNESCAPED_UNICODE);
