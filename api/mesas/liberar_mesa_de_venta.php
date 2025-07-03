<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$venta_id = $input['venta_id'] ?? null;
if (!$venta_id) {
    error('Datos inválidos');
}
$venta_id = (int)$venta_id;
$datosMesa = [];

$stmt = $conn->prepare('SELECT mesa_id, usuario_id FROM ventas WHERE id = ?');
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
if (!$row || !$row['mesa_id']) {
    success(true); // nothing to liberar
}
$mesa_id = (int)$row['mesa_id'];
$venta_usuario = (int)$row['usuario_id'];

$info = $conn->prepare('SELECT usuario_id, tiempo_ocupacion_inicio FROM mesas WHERE id = ?');
if ($info) {
    $info->bind_param('i', $mesa_id);
    if ($info->execute()) {
        $resInfo = $info->get_result();
        $datosMesa = $resInfo->fetch_assoc();
    }
    $info->close();
}
$inicio = $datosMesa['tiempo_ocupacion_inicio'] ?? null;
$mesa_usuario = $datosMesa['usuario_id'] ?? $venta_usuario;

$log = $conn->prepare('INSERT INTO log_mesas (mesa_id, venta_id, usuario_id, fecha_inicio, fecha_fin) VALUES (?,?,?,?,NOW())');
if ($log) {
    $log->bind_param('iiis', $mesa_id, $venta_id, $mesa_usuario, $inicio);
    $log->execute();
    $log->close();
}

$upd = $conn->prepare("UPDATE mesas SET estado = 'libre', tiempo_ocupacion_inicio = NULL, estado_reserva = 'ninguna', nombre_reserva = NULL, fecha_reserva = NULL, usuario_id = NULL, ticket_enviado = FALSE WHERE id = ?");
if (!$upd) {
    error('Error al preparar actualización: ' . $conn->error);
}
$upd->bind_param('i', $mesa_id);
if (!$upd->execute()) {
    $upd->close();
    error('Error al liberar mesa: ' . $upd->error);
}
$upd->close();

success(true);
?>
