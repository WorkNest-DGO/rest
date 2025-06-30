<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$venta_id = null;
if ($input && isset($input['venta_id'])) {
    $venta_id = (int)$input['venta_id'];
} elseif (isset($_POST['venta_id'])) {
    $venta_id = (int)$_POST['venta_id'];
}

if (!$venta_id) {
    error('Datos inválidos');
}

// verificar que todos los productos estén listos
$check = $conn->prepare("SELECT COUNT(*) AS faltan FROM venta_detalles WHERE venta_id = ? AND estatus_preparacion <> 'listo'");
if (!$check) {
    error('Error al preparar verificación: ' . $conn->error);
}
$check->bind_param('i', $venta_id);
if (!$check->execute()) {
    $check->close();
    error('Error al ejecutar verificación: ' . $check->error);
}
$res = $check->get_result();
$row = $res->fetch_assoc();
$check->close();
if ($row && (int)$row['faltan'] > 0) {
    error('Aún hay productos sin preparar');
}

$stmt = $conn->prepare("UPDATE ventas SET estatus = 'cerrada', entregado = 1 WHERE id = ?");
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('i', $venta_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar venta: ' . $stmt->error);
}

if ($stmt->affected_rows === 0) {
    $stmt->close();
    error('Venta no encontrada');
}
$stmt->close();

success(true);
?>
