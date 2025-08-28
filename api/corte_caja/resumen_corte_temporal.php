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
$corte_id = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : ($_SESSION['corte_id'] ?? null);
if (!$corte_id) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Corte no definido en sesión'
    ]);
    exit;
}


// Obtener resumen de ventas agrupado por tipo de pago
$sqlResumen = "SELECT
    t.tipo_pago,
    SUM(t.total)   AS total,
    SUM(t.propina) AS propina
FROM ventas v
JOIN tickets t ON t.venta_id = v.id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?
GROUP BY t.tipo_pago";

$stmtResumen = $conn->prepare($sqlResumen);
$stmtResumen->bind_param('i', $corte_id);
$stmtResumen->execute();
$resultResumen = $stmtResumen->get_result();

$resumen = [];
$totalProductos = 0;
$totalPropinas  = 0;
$total_final_efectivo = 0;
while ($row = $resultResumen->fetch_assoc()) {
    $total   = (float)$row['total'];
    $propina = (float)$row['propina'];
    $productos = $total - $propina;
    $resumen[$row['tipo_pago']] = [
        'productos' => $productos,
        'propina'   => $propina,
        'total'     => $total
    ];
    $totalProductos += $productos;
    $totalPropinas  += $propina;
    if (strtolower($row['tipo_pago']) === 'efectivo') {
        $total_final_efectivo += $total;
    }
}
$stmtResumen->close();

$totalEsperado = $totalProductos + $totalPropinas;

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

$totalFinal = $totalEsperado + $fondoInicial + $totalDepositos - $totalRetiros;

// Total de ventas registradas por usuarios con rol de mesero
$sqlMeseros = "
    SELECT 
        TRIM(u.nombre) AS nombre, 
        IFNULL(SUM(t.total), 0) AS total
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
$sqlRapido = "SELECT SUM(t.total) AS total
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

// Totales agrupados por repartidor
$sqlRepartidor = "SELECT r.nombre, IFNULL(SUM(t.total), 0) AS total
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
$resultado['total_propinas']  = $totalPropinas;
$resultado['totalEsperado']   = $totalEsperado;
$resultado['fondo']           = $fondoInicial;
$resultado['total_depositos'] = $totalDepositos;
$resultado['total_retiros']   = $totalRetiros;
$resultado['totalFinal']      = $totalFinal;
$resultado['totalFinalEfectivo'] = $total_final_efectivo+$fondoInicial;
$resultado['corte_id']        = $corte_id;
$resultado['total_meseros'] = $meseros;
$resultado['total_rapido']    = $totalRapido;
$resultado['total_repartidor']= $totalRepartidor;
$resultado['fecha_inicio']    = $fechaInicio;
$resultado['folio_inicio']    = $folioInicio;
$resultado['folio_fin']       = $folioFin;
$resultado['total_folios']    = $totalFolios;

echo json_encode([
    'success'   => true,
    'resultado' => $resultado,
    "cajero" => $rowFondo['cajero'] ?? ''
]);
?>
