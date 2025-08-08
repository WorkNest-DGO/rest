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
    SUM(t.total)   AS total_productos,
    SUM(t.propina) AS total_propina
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
while ($row = $resultResumen->fetch_assoc()) {
    $productos = (float)$row['total_productos'];
    $propina   = (float)$row['total_propina'];
    $resumen[$row['tipo_pago']] = [
        'productos' => $productos,
        'propina'   => $propina,
        'total'     => $productos + $propina
    ];
    $totalProductos += $productos;
    $totalPropinas  += $propina;
}
$stmtResumen->close();

$totalEsperado = $totalProductos + $totalPropinas;

// Obtener fondo inicial
$stmtFondo = $conn->prepare('SELECT fondo_inicial FROM corte_caja WHERE id = ?');
$stmtFondo->bind_param('i', $corte_id);
$stmtFondo->execute();
$rowFondo = $stmtFondo->get_result()->fetch_assoc();
$fondoInicial = (float)($rowFondo['fondo_inicial'] ?? 0);
$stmtFondo->close();

$totalFinal = $totalEsperado + $fondoInicial;

// Total a entregar en efectivo (fondo + ventas y propinas en efectivo)
$efectivoProductos = $resumen['efectivo']['productos'] ?? 0;
$efectivoPropina   = $resumen['efectivo']['propina'] ?? 0;
$totalAEntregar    = $fondoInicial + $efectivoProductos + $efectivoPropina;

echo json_encode([
    'success'        => true,
    'resultado'      => $resumen,
    'total_productos'=> $totalProductos,
    'total_propinas' => $totalPropinas,
    'totalEsperado'  => $totalEsperado,
    'fondo'          => $fondoInicial,
    'totalFinal'     => $totalFinal,
    'totalAEntregar' => $totalAEntregar,
    'corte_id'       => $corte_id
]);
?>
