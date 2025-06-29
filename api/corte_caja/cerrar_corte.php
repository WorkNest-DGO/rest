<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$corte_id   = $input['corte_id'] ?? null;
$usuario_id = $input['usuario_id'] ?? null;

if (!$corte_id || !$usuario_id) {
    error('Datos incompletos');
}

$stmt = $conn->prepare('SELECT fecha_inicio FROM corte_caja WHERE id = ? AND usuario_id = ? AND fecha_fin IS NULL');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('ii', $corte_id, $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    $stmt->close();
    error('Corte no encontrado o ya cerrado');
}
$row = $res->fetch_assoc();
$fecha_inicio = $row['fecha_inicio'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(* ) AS num, SUM(total) AS total FROM ventas WHERE usuario_id = ? AND fecha >= ? AND fecha <= NOW() AND estatus = 'cerrada'");
if (!$stmt) {
    error('Error al preparar consulta de ventas: ' . $conn->error);
}
$stmt->bind_param('is', $usuario_id, $fecha_inicio);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al obtener ventas: ' . $stmt->error);
}
$ventas = $stmt->get_result()->fetch_assoc();
$numVentas = (int)($ventas['num'] ?? 0);
$total = (float)($ventas['total'] ?? 0);
$stmt->close();

if ($numVentas === 0) {
    error('No hay ventas para cerrar corte');
}

$stmt = $conn->prepare('UPDATE corte_caja SET fecha_fin = NOW(), total = ? WHERE id = ?');
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('di', $total, $corte_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al cerrar corte: ' . $stmt->error);
}
$stmt->close();

success(['ventas_realizadas' => $numVentas, 'total' => $total]);
?>
