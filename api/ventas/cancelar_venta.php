<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'])) {
    error('Datos inválidos');
}

$venta_id = (int) $input['venta_id'];

// Verificar estatus y datos de la venta
$info = $conn->prepare('SELECT estatus, mesa_id, tipo_entrega, estado_entrega, usuario_id FROM ventas WHERE id = ?');
if (!$info) {
    error('Error al preparar consulta: ' . $conn->error);
}
$info->bind_param('i', $venta_id);
if (!$info->execute()) {
    $info->close();
    error('Error al ejecutar consulta: ' . $info->error);
}
$res = $info->get_result();
if ($res->num_rows === 0) {
    $info->close();
    error('Venta no encontrada');
}
$venta = $res->fetch_assoc();
$info->close();

if ($venta['estatus'] !== 'activa') {
    error('No se puede cancelar esta venta');
}

// Si es a domicilio y ya va en camino, no se permite cancelar
if ($venta['tipo_entrega'] === 'domicilio' && $venta['estado_entrega'] === 'en_camino') {
    error('No se puede cancelar una venta que ya está en camino');
}

// Verificar que ningún producto haya sido preparado o entregado
$check = $conn->prepare("SELECT COUNT(*) AS num FROM venta_detalles WHERE venta_id = ? AND (estado_producto <> 'pendiente' OR estatus_preparacion <> 'pendiente')");
if (!$check) {
    error('Error al preparar verificación: ' . $conn->error);
}
$check->bind_param('i', $venta_id);
if (!$check->execute()) {
    $check->close();
    error('Error al ejecutar verificación: ' . $check->error);
}
$row = $check->get_result()->fetch_assoc();
$check->close();
if ($row && (int)$row['num'] > 0) {
    error('La venta contiene productos ya preparados o entregados');
}

$stmt = $conn->prepare("UPDATE ventas SET estatus = 'cancelada' WHERE id = ?");
if (!$stmt) {
    error('Error al preparar actualización: ' . $conn->error);
}
$stmt->bind_param('i', $venta_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al cancelar venta: ' . $stmt->error);
}

$stmt->close();

// Liberar mesa si aplica
if (!empty($venta['mesa_id'])) {
    $mesa_id = (int)$venta['mesa_id'];
    $datosMesa = [];
    $infoMesa = $conn->prepare('SELECT usuario_id, tiempo_ocupacion_inicio FROM mesas WHERE id = ?');
    if ($infoMesa) {
        $infoMesa->bind_param('i', $mesa_id);
        if ($infoMesa->execute()) {
            $resInfo = $infoMesa->get_result();
            $datosMesa = $resInfo->fetch_assoc();
        }
        $infoMesa->close();
    }
    $inicio = $datosMesa['tiempo_ocupacion_inicio'] ?? null;
    $mesa_usuario = $datosMesa['usuario_id'] ?? (int)$venta['usuario_id'];
    $log = $conn->prepare('INSERT INTO log_mesas (mesa_id, venta_id, usuario_id, fecha_inicio, fecha_fin) VALUES (?,?,?,?,NOW())');
    if ($log) {
        $log->bind_param('iiis', $mesa_id, $venta_id, $mesa_usuario, $inicio);
        $log->execute();
        $log->close();
    }
    $upd = $conn->prepare("UPDATE mesas SET estado = 'libre', tiempo_ocupacion_inicio = NULL, estado_reserva = 'ninguna', nombre_reserva = NULL, fecha_reserva = NULL, usuario_id = NULL, ticket_enviado = FALSE WHERE id = ?");
    if ($upd) {
        $upd->bind_param('i', $mesa_id);
        $upd->execute();
        $upd->close();
    }
}

success(['mensaje' => 'Venta cancelada correctamente']);

