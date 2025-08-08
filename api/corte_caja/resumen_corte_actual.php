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
    SUM(t.total) AS total,
    SUM(t.propina) AS propina
FROM ventas v
JOIN tickets t ON t.venta_id = v.id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?
GROUP BY t.tipo_pago";

$stmtResumen = $conn->prepare($sqlResumen);
$stmtResumen->bind_param("i", $corte_id);
$stmtResumen->execute();
$resultResumen = $stmtResumen->get_result();

$resumen = [];
$totalEsperado = 0;
while ($row = $resultResumen->fetch_assoc()) {
    $total = (float)$row['total'];
    $propina = (float)$row['propina'];
    $resumen[$row['tipo_pago']] = [
        'total' => $total,
        'propina' => $propina
    ];
    $totalEsperado += $total + $propina;
}

// Obtener fondo inicial
$stmtFondo = $conn->prepare('SELECT fondo_inicial FROM corte_caja WHERE id = ?');
$stmtFondo->bind_param('i', $corte_id);
$stmtFondo->execute();
$rowFondo = $stmtFondo->get_result()->fetch_assoc();
$fondoInicial = (float)($rowFondo['fondo_inicial'] ?? 0);
$stmtFondo->close();

$totalFinal = $totalEsperado + $fondoInicial;

echo json_encode([
    'success' => true,
    'resultado' => $resumen,
    'corte_id' => $corte_id,
    'fondo_inicial' => $fondoInicial,
    'total_con_propinas_y_fondo' => $totalFinal
]);
?>
