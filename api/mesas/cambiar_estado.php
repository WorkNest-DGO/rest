<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['mesa_id']) || !isset($input['nuevo_estado'])) {
    error('Datos inválidos');
}

$mesa_id = (int)$input['mesa_id'];
$nuevo_estado = $input['nuevo_estado'];
$nombre_reserva = $input['nombre_reserva'] ?? null;
$fecha_reserva  = $input['fecha_reserva'] ?? null;

$usuarioActualId = $_SESSION['usuario_id'] ?? null;
$rol = $_SESSION['rol'] ?? '';
if (!$usuarioActualId) {
    error('No autenticado');
}

if ($rol !== 'admin') {
    $stmt = $conn->prepare('SELECT usuario_id FROM mesas WHERE id = ?');
    if (!$stmt) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param('i', $mesa_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$res || (int)$res['usuario_id'] !== $usuarioActualId) {
        error('No autorizado');
    }
}

$estados = ['libre', 'ocupada', 'reservada'];
if (!in_array($nuevo_estado, $estados, true)) {
    error('Estado no válido');
}

if ($nuevo_estado === 'ocupada') {
    $stmt = $conn->prepare("UPDATE mesas SET estado = 'ocupada', tiempo_ocupacion_inicio = IF(tiempo_ocupacion_inicio IS NULL, NOW(), tiempo_ocupacion_inicio) WHERE id = ?");
    if (!$stmt) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param('i', $mesa_id);
} elseif ($nuevo_estado === 'libre') {
    $stmt = $conn->prepare("UPDATE mesas SET estado = 'libre', tiempo_ocupacion_inicio = NULL, estado_reserva = 'ninguna', nombre_reserva = NULL, fecha_reserva = NULL, usuario_id = NULL WHERE id = ?");
    if (!$stmt) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param('i', $mesa_id);
} elseif ($nuevo_estado === 'reservada') {
    $stmt = $conn->prepare("UPDATE mesas SET estado = 'reservada', estado_reserva = 'reservada', nombre_reserva = ?, fecha_reserva = ? WHERE id = ?");
    if (!$stmt) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $stmt->bind_param('ssi', $nombre_reserva, $fecha_reserva, $mesa_id);
} else {
    error('Estado no válido');
}
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al actualizar mesa: ' . $stmt->error);
}
$stmt->close();

success(true);
?>
