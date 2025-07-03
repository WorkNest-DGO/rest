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

// Obtener resumen de ventas relacionadas al corte_id (sin filtrar por usuario)
$sqlResumen = "SELECT 
    SUM(t.total) AS total, 
    SUM(t.propina) AS propinas, 
    COUNT(DISTINCT v.id) AS num_ventas
FROM ventas v
JOIN tickets t ON t.venta_id = v.id
WHERE v.estatus = 'cerrada'
  AND v.corte_id = ?";

$stmtResumen = $conn->prepare($sqlResumen);
$stmtResumen->bind_param("i", $corte_id);
$stmtResumen->execute();
$resultResumen = $stmtResumen->get_result();
$dataResumen = $resultResumen->fetch_assoc();

echo json_encode([
    'success' => true,
    'resultado' => $dataResumen,
    'corte_id' => $corte_id
]);
?>
