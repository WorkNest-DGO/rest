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

// Obtener corte_id desde sesión (establecido en verificar_corte_abierto)
if (!isset($_SESSION['corte_id'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Corte no definido en sesión'
    ]);
    exit;
}

$corte_id = $_SESSION['corte_id'];

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
while ($row = $resultResumen->fetch_assoc()) {
    $resumen[$row['tipo_pago']] = [
        'total' => (float)$row['total'],
        'propina' => (float)$row['propina']
    ];
}

echo json_encode([
    'success' => true,
    'resultado' => $resumen,
    'corte_id' => $corte_id
]);
?>
