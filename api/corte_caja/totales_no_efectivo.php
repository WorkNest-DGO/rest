<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Sesión no iniciada'
    ]);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

$sql = "SELECT
    SUM(CASE WHEN t.tipo_pago='boucher' THEN t.total ELSE 0 END) AS total_boucher,
    SUM(CASE WHEN t.tipo_pago='cheque'  THEN t.total ELSE 0 END) AS total_cheque
FROM tickets t
JOIN ventas v   ON v.id = t.venta_id
JOIN corte_caja c ON c.usuario_id = ? AND c.fecha_fin IS NULL
WHERE v.estatus = 'cerrada'
  AND v.corte_id IS NULL
  AND t.fecha >= c.fecha_inicio";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

$boucher = (float)($res['total_boucher'] ?? 0);
$cheque  = (float)($res['total_cheque'] ?? 0);

echo json_encode([
    'success' => true,
    'boucher' => $boucher,
    'cheque'  => $cheque
]);
