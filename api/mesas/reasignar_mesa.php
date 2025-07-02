<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$venta_id = $input['venta_id'] ?? null;
$nueva_mesa_id = $input['nueva_mesa_id'] ?? null;
if (!$venta_id || !$nueva_mesa_id) {
    error('Datos inválidos');
}
$venta_id = (int)$venta_id;
$nueva_mesa_id = (int)$nueva_mesa_id;

$stmt = $conn->prepare('SELECT mesa_id FROM ventas WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $venta_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al obtener venta: ' . $stmt->error);
}
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row) {
    error('Venta no encontrada');
}
$mesa_actual = (int)$row['mesa_id'];

$check = $conn->prepare('SELECT estado FROM mesas WHERE id = ?');
if ($check) {
    $check->bind_param('i', $nueva_mesa_id);
    $check->execute();
    $row2 = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$row2 || $row2['estado'] !== 'libre') {
        error('La mesa destino no está libre');
    }
}

$updVenta = $conn->prepare('UPDATE ventas SET mesa_id = ? WHERE id = ?');
if (!$updVenta) {
    error('Error al preparar actualización: ' . $conn->error);
}
$updVenta->bind_param('ii', $nueva_mesa_id, $venta_id);
if (!$updVenta->execute()) {
    $updVenta->close();
    error('Error al actualizar venta: ' . $updVenta->error);
}
$updVenta->close();

$liberar = $conn->prepare("UPDATE mesas SET estado = 'libre', tiempo_ocupacion_inicio = NULL, estado_reserva = 'ninguna', nombre_reserva = NULL, fecha_reserva = NULL, usuario_id = NULL WHERE id = ?");
if ($liberar) {
    $liberar->bind_param('i', $mesa_actual);
    $liberar->execute();
    $liberar->close();
}
$ocupar = $conn->prepare("UPDATE mesas SET estado = 'ocupada', tiempo_ocupacion_inicio = NOW() WHERE id = ?");
if ($ocupar) {
    $ocupar->bind_param('i', $nueva_mesa_id);
    $ocupar->execute();
    $ocupar->close();
}

success(true);
?>
