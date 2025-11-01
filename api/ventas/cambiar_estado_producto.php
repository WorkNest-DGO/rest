<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['detalle_id'], $input['estado'])) {
    error('Datos inválidos');
}

$detalle_id = (int)$input['detalle_id'];
$estado = $input['estado'];

$permitidos = ['pendiente', 'en_preparacion', 'listo', 'entregado'];
if (!in_array($estado, $permitidos, true)) {
    error('Estado no permitido');
}

if ($estado === 'entregado') {
    $stmt = $conn->prepare("UPDATE venta_detalles SET estado_producto = ?, entregado_hr = IF(entregado_hr IS NULL, NOW(), entregado_hr) WHERE id = ?");
} else {
    $stmt = $conn->prepare('UPDATE venta_detalles SET estado_producto = ? WHERE id = ?');
}
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('si', $estado, $detalle_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar estado: ' . $stmt->error);
}
$stmt->close();

// Notificar cambio a pantallas de cocina (long-poll) sin HTTP interno
try {
    require_once __DIR__ . '/../cocina/notify_lib.php';
    @cocina_notify([$detalle_id]);
} catch (\Throwable $e) { /* noop */ }

success(true);
?>
