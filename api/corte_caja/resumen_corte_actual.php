<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Sesión no iniciada'
    ]);
    exit;
}

// Se permite obtener el corte por parámetro o por sesión
$corte_id = null;

if (isset($_GET['corte_id']) && $_GET['corte_id'] !== '' && $_GET['corte_id'] !== 'null') {
    $corte_id = (int)$_GET['corte_id'];
} elseif (isset($_SESSION['corte_id'])) {
    $corte_id = (int)$_SESSION['corte_id'];
}

if (!$corte_id) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Corte no definido en sesión'
    ]);
    exit;
}
// ==== [INICIO BLOQUE valida: guardia de pendientes] ====
function contar($db, $sql) {
  $res = $db->query($sql);
  if (!$res) return 0;
  $row = $res->fetch_assoc();
  return (int)($row['c'] ?? 0);
}

$ventasActivas = contar($conn, "SELECT COUNT(*) AS c FROM ventas WHERE estatus='activa'");
$mesasOcupadas = contar($conn, "SELECT COUNT(*) AS c FROM mesas  WHERE estado='ocupada'");

if ($ventasActivas > 0 || $mesasOcupadas > 0) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'mensaje' => 'No se puede continuar: existen pendientes.',
    'detalle' => [
      'ventas_activas' => $ventasActivas,
      'mesas_ocupadas' => $mesasOcupadas
    ]
  ]);
  exit;
}
// ==== [FIN BLOQUE valida] ====

// Obtener resumen de ventas agrupado por tipo de pago
$sqlResumen = "SELECT
    t.tipo_pago,
    SUM(t.total)          AS total_bruto,
    SUM(t.monto_recibido) AS total_cobrado
FROM ventas v
JOIN tickets t ON t.venta_id = v.id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?
GROUP BY t.tipo_pago";

$sqlResumen2 = "SELECT
    SUM(v.total)   AS total,
    SUM(v.propina_efectivo) as propina_efectivo,
    SUM(v.propina_cheque) as propina_cheque,
    SUM(v.propina_tarjeta) as propina_tarjeta 
FROM ventas v
WHERE v.estatus = 'cerrada' 
  AND v.corte_id = ? LIMIT 1";

$sqlResumen3 = "SELECT
    SUM(v.total)   AS total,
    SUM(v.promocion_descuento) as descuento_promociones
FROM ventas v
WHERE v.estatus = 'cerrada' 
  AND v.corte_id = ? LIMIT 1";


$stmtResumen = $conn->prepare($sqlResumen);
$stmtResumen->bind_param('i', $corte_id);
$stmtResumen->execute();
$resultResumen = $stmtResumen->get_result();

$stmtResumen2 = $conn->prepare($sqlResumen2);
$stmtResumen2->bind_param('i', $corte_id);
$stmtResumen2->execute();
$resultResumen2 = $stmtResumen2->get_result();
$row2 = $resultResumen2->fetch_assoc();

$stmtResumen3 = $conn->prepare($sqlResumen3);
$stmtResumen3->bind_param('i', $corte_id);
$stmtResumen3->execute();
$resultResumen3 = $stmtResumen3->get_result();
$row3 = $resultResumen3->fetch_assoc();

$resumen = [];
$totalDescuentoPromos =0;
$totalDescuentoPromos=(float)$row3['descuento_promociones'];

$totalProductos = 0;
$totalCobrado   = 0;
$totalPropinas  = 0;
$totalPropinaEfectivo=(float)$row2['propina_efectivo'];
$totalPropinaCheque=(float)$row2['propina_cheque'];
$totalPropinaTarjeta=(float)$row2['propina_tarjeta'];
$totalPropinas=$totalPropinaEfectivo+$totalPropinaCheque+$totalPropinaTarjeta;
while ($row = $resultResumen->fetch_assoc()) {
    $productos    = (float)$row['total_bruto'];
    $totalCobro   = (float)$row['total_cobrado'];
    $resumen[$row['tipo_pago']] = [
        'productos' => $productos,

        'total'     => $totalCobro
    ];
    $totalProductos += $productos;
    $totalCobrado   += $totalCobro;

}
$stmtResumen->close();

// Total esperado normalizado:
// Usar lo realmente cobrado en tickets (monto_recibido) y sumar propinas registradas en ventas.
$totalEsperado = $totalCobrado + $totalPropinas; // valor provisional, se corrige tras calcular agregados

// Agregados de descuentos y esperado (con tickets)
$sqlAgg = "SELECT
  COALESCE(SUM(t.total), 0) AS total_bruto,
  COALESCE(SUM(COALESCE(t.descuento,0)), 0) AS total_descuentos,
  COALESCE(SUM(t.monto_recibido), 0) AS total_esperado,
  COALESCE(SUM(CASE WHEN t.tipo_pago='efectivo' THEN t.monto_recibido ELSE 0 END), 0) AS esperado_efectivo,
  COALESCE(SUM(CASE WHEN t.tipo_pago='boucher'  THEN t.monto_recibido ELSE 0 END), 0) AS esperado_boucher,
  COALESCE(SUM(CASE WHEN t.tipo_pago='cheque'   THEN t.monto_recibido ELSE 0 END), 0) AS esperado_cheque,
  COALESCE(SUM(CASE WHEN t.tipo_pago='tarjeta'  THEN t.monto_recibido ELSE 0 END), 0) AS esperado_tarjeta,
  COALESCE(SUM(CASE WHEN t.tipo_pago='transferencia' THEN t.monto_recibido ELSE 0 END), 0) AS esperado_transferencia
FROM ventas v
JOIN tickets t ON t.venta_id = v.id
WHERE v.estatus = 'cerrada' AND v.corte_id = ?";
$stmtAgg = $conn->prepare($sqlAgg);
if ($stmtAgg) {
    $stmtAgg->bind_param('i', $corte_id);
    if ($stmtAgg->execute()) {
        $rowAgg = $stmtAgg->get_result()->fetch_assoc() ?: [];
    }
    $stmtAgg->close();
}

// Recalcular totalEsperado con base en agregados post-descuentos
$__totalEsperadoProductos = (float)($rowAgg['total_esperado'] ?? 0);
$__esperadoEfectivoProd   = (float)($rowAgg['esperado_efectivo'] ?? 0);
$__esperadoBoucher        = (float)($rowAgg['esperado_boucher'] ?? 0);
$__esperadoCheque         = (float)($rowAgg['esperado_cheque'] ?? 0);
$__esperadoTarjeta        = (float)($rowAgg['esperado_tarjeta'] ?? 0);
$__esperadoTransfer       = (float)($rowAgg['esperado_transferencia'] ?? 0);
$totalPropinaNoEfectivo   = $totalPropinaCheque + $totalPropinaTarjeta;

// Esperado total (productos netos + propinas)
$totalEsperado = $__totalEsperadoProductos + $totalPropinas;
// Esperado solo efectivo (productos + propina en efectivo)
$totalEsperadoEfectivo = $__esperadoEfectivoProd + $totalPropinaEfectivo;
// Esperado no efectivo (tarjeta/boucher/cheque/transferencia + propinas no efect.)
$totalEsperadoNoEfectivo = ($__totalEsperadoProductos - $__esperadoEfectivoProd) + $totalPropinaNoEfectivo;

// Contadores y totales por estatus (activa / cancelada)
$sqlEstatus = "SELECT
    COALESCE(SUM(CASE WHEN v.estatus = 'activa' THEN 1 ELSE 0 END), 0)     AS cuentas_activas,
    COALESCE(SUM(CASE WHEN v.estatus = 'cancelada' THEN 1 ELSE 0 END), 0)  AS cuentas_canceladas,
    COALESCE(SUM(CASE WHEN v.estatus = 'activa' THEN v.total ELSE 0 END), 0)    AS total_activas,
    COALESCE(SUM(CASE WHEN v.estatus = 'cancelada' THEN v.total ELSE 0 END), 0) AS total_canceladas
  FROM ventas v
 WHERE v.corte_id = ?";
$stmtEstatus = $conn->prepare($sqlEstatus);
$stmtEstatus->bind_param('i', $corte_id);
$stmtEstatus->execute();
$rowEstatus = $stmtEstatus->get_result()->fetch_assoc();
$stmtEstatus->close();

// Obtener fondo inicial y fecha de inicio del corte
$stmtFondo = $conn->prepare('SELECT c.fondo_inicial, c.fecha_inicio, u.nombre AS cajero
             FROM corte_caja c
             JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.id = ?');
$stmtFondo->bind_param('i', $corte_id);
$stmtFondo->execute();
$rowFondo    = $stmtFondo->get_result()->fetch_assoc();
$fondoInicial = (float)($rowFondo['fondo_inicial'] ?? 0);
$fechaInicio  = $rowFondo['fecha_inicio'] ?? '';
$resultado['cajero'] = $rowFondo['cajero'] ?? '';
$stmtFondo->close();

// Obtener totales de depósitos y retiros para el corte
$sqlMovimientos = "SELECT
        SUM(CASE WHEN tipo_movimiento='deposito' THEN monto ELSE 0 END) AS total_depositos,
        SUM(CASE WHEN tipo_movimiento='retiro' THEN monto ELSE 0 END) AS total_retiros
    FROM movimientos_caja
    WHERE corte_id = ?";
$stmtMovimientos = $conn->prepare($sqlMovimientos);
$stmtMovimientos->bind_param('i', $corte_id);
$stmtMovimientos->execute();
$rowMovimientos = $stmtMovimientos->get_result()->fetch_assoc();
$totalDepositos = (float)($rowMovimientos['total_depositos'] ?? 0);
$totalRetiros   = (float)($rowMovimientos['total_retiros'] ?? 0);
$stmtMovimientos->close();

$totalFinalGeneral = $totalEsperado + $fondoInicial + $totalDepositos - $totalRetiros;
$totalFinalEfectivo = $totalEsperadoEfectivo + $fondoInicial + $totalDepositos - $totalRetiros;

// Total cobrado registrado por usuarios con rol de mesero
$sqlMeseros = "
    SELECT
        TRIM(u.nombre) AS nombre,
        IFNULL(SUM(t.monto_recibido), 0) AS total
    FROM usuarios u
    LEFT JOIN ventas v
        ON v.usuario_id = u.id
        AND v.corte_id = ?
    LEFT JOIN tickets t 
        ON t.venta_id = v.id
    WHERE u.rol = 'mesero'
    GROUP BY u.nombre
";

$stmtMeseros = $conn->prepare($sqlMeseros);
$stmtMeseros->bind_param('i', $corte_id);
$stmtMeseros->execute();
$resMeseros = $stmtMeseros->get_result();

$meseros = [];
while ($row = $resMeseros->fetch_assoc()) {
    $meseros[] = [
        'nombre' => trim($row['nombre']),
        'total'  => (float)$row['total']
    ];
}
$stmtMeseros->close();


// Total de ventas de tipo rapida
$sqlRapido = "SELECT SUM(t.monto_recibido) AS total
               FROM ventas v
               JOIN tickets t ON t.venta_id = v.id
              WHERE v.estatus = 'cerrada'
                AND v.corte_id = ?
                AND v.tipo_entrega = 'rapido'";
$stmtRapido = $conn->prepare($sqlRapido);
$stmtRapido->bind_param('i', $corte_id);
$stmtRapido->execute();
$totalRapido = (float)($stmtRapido->get_result()->fetch_assoc()['total'] ?? 0);
$stmtRapido->close();

// Totales cobrados agrupados por repartidor
$sqlRepartidor = "SELECT r.nombre, IFNULL(SUM(t.monto_recibido), 0) AS total
                  FROM repartidores r
                  LEFT JOIN ventas v ON v.repartidor_id = r.id AND v.corte_id = ?
                  LEFT JOIN tickets t ON t.venta_id = v.id
                  GROUP BY r.nombre";
$stmtRepartidor = $conn->prepare($sqlRepartidor);
$stmtRepartidor->bind_param('i', $corte_id);
$stmtRepartidor->execute();
$resultRepartidor = $stmtRepartidor->get_result();
$totalRepartidor = [];
while ($row = $resultRepartidor->fetch_assoc()) {
    $totalRepartidor[] = [
        'nombre' => $row['nombre'],
        'total'  => (float)$row['total']
    ];
}
$stmtRepartidor->close();

// Información de folios asociados al corte
$sqlFolios = "SELECT 
    MIN(t.folio) AS folio_inicio,
    MAX(t.folio) AS folio_fin,
    COUNT(t.folio) AS total_folios
FROM ventas v
JOIN tickets t ON t.venta_id = v.id
WHERE v.estatus = 'cerrada'
               AND corte_id = ?";
$stmtFolios = $conn->prepare($sqlFolios);
$stmtFolios->bind_param('i', $corte_id);
$stmtFolios->execute();
$rowFolios   = $stmtFolios->get_result()->fetch_assoc();
$folioInicio = (int)($rowFolios['folio_inicio'] ?? 0);
$folioFin    = (int)($rowFolios['folio_fin'] ?? 0);
$totalFolios = (int)($rowFolios['total_folios'] ?? 0);
$stmtFolios->close();

$resultado = $resumen;
$resultado['total_productos'] = $totalProductos;
$resultado['total_cobrado']   = $totalCobrado;
$resultado['total_propina_efectivo']  = $totalPropinaEfectivo;
$resultado['total_propina_cheque']  = $totalPropinaCheque;
$resultado['total_propina_tarjeta']  = $totalPropinaTarjeta;
$resultado['total_descuento_promos']  = $totalDescuentoPromos;
$resultado['total_propinas']  = $totalPropinas;
$resultado['totalEsperado']   = $totalEsperado; // productos post-descuento + propinas
$resultado['totalEsperadoEfectivo'] = $totalEsperadoEfectivo;
$resultado['totalEsperadoNoEfectivo'] = $totalEsperadoNoEfectivo;
$resultado['fondo']           = $fondoInicial;
$resultado['total_depositos'] = $totalDepositos;
$resultado['total_retiros']   = $totalRetiros;
$resultado['totalFinal']      = $totalFinalEfectivo;       // esperado en caja (efectivo)
$resultado['totalFinalGeneral']= $totalFinalGeneral;       // referencia general (todos los medios)
$resultado['corte_id']        = $corte_id;
$resultado['total_meseros'] = $meseros;
$resultado['total_rapido']    = $totalRapido;
$resultado['total_repartidor']= $totalRepartidor;
$resultado['fecha_inicio']    = $fechaInicio;
$resultado['folio_inicio']    = $folioInicio;
$resultado['folio_fin']       = $folioFin;
$resultado['total_folios']    = $totalFolios;
// Nuevos agregados (no rompen compatibilidad)
$resultado['total_bruto']       = (float)($rowAgg['total_bruto']       ?? 0);
$resultado['total_descuentos']  = (float)($rowAgg['total_descuentos']  ?? 0);
$resultado['total_esperado']    = (float)($rowAgg['total_esperado']    ?? 0);
$resultado['esperado_efectivo'] = (float)($rowAgg['esperado_efectivo'] ?? 0);
$resultado['esperado_boucher']  = (float)($rowAgg['esperado_boucher']  ?? 0);
$resultado['esperado_cheque']   = (float)($rowAgg['esperado_cheque']   ?? 0);
$resultado['esperado_tarjeta']  = (float)($rowAgg['esperado_tarjeta']  ?? 0);
$resultado['esperado_transferencia'] = (float)($rowAgg['esperado_transferencia'] ?? 0);
// Totales y contadores por estatus
$resultado['cuentas_activas']      = (int)($rowEstatus['cuentas_activas'] ?? 0);
$resultado['total_cuentas_activas']= (float)($rowEstatus['total_activas'] ?? 0);
$resultado['cuentas_canceladas']   = (int)($rowEstatus['cuentas_canceladas'] ?? 0);
$resultado['total_cuentas_canceladas'] = (float)($rowEstatus['total_canceladas'] ?? 0);

echo json_encode([
    'success'   => true,
    'resultado' => $resultado,
    "cajero" => $rowFondo['cajero'] ?? ''
]);
?>
