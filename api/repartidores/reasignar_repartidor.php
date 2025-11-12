<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!$input || !isset($input['venta_id'], $input['repartidor_id'])) {
    error('Datos incompletos');
}

$venta_id = (int)$input['venta_id'];
$repartidor_usuario_id = (int)$input['repartidor_id'];
// Validar que es un usuario con rol repartidor activo
$stmtU = $conn->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'repartidor' AND (activo IS NULL OR activo = 1) LIMIT 1");
if (!$stmtU) { error('Error al validar repartidor: ' . $conn->error); }
$stmtU->bind_param('i', $repartidor_usuario_id);
if (!$stmtU->execute()) { $stmtU->close(); error('Error al validar repartidor: ' . $stmtU->error); }
$okU = $stmtU->get_result()->num_rows > 0;
$stmtU->close();
if (!$okU) { error('Repartidor no válido'); }

// Validar venta
$stmtV = $conn->prepare("SELECT id, estado_entrega, fecha_asignacion FROM ventas WHERE id = ? LIMIT 1");
if (!$stmtV) { error('Error al validar venta: ' . $conn->error); }
$stmtV->bind_param('i', $venta_id);
if (!$stmtV->execute()) { $stmtV->close(); error('Error al validar venta: ' . $stmtV->error); }
$venta = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$venta) { error('Venta no encontrada'); }

// Actualizar usuario_id (asignación a usuario repartidor) y fecha_asignacion si no estaba asignada
$upd = $conn->prepare("UPDATE ventas SET usuario_id = ?, fecha_asignacion = COALESCE(fecha_asignacion, NOW()) WHERE id = ?");
if (!$upd) { error('Error al preparar actualización: ' . $conn->error); }
$upd->bind_param('ii', $repartidor_usuario_id, $venta_id);
if (!$upd->execute()) { $upd->close(); error('Error al reasignar: ' . $upd->error); }
$upd->close();

success(['venta_id' => $venta_id, 'usuario_id' => $repartidor_usuario_id]);
?>
