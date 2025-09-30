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

// Verificación de autorización
$mesaUsuarioId = null;
$stmt = $conn->prepare('SELECT usuario_id FROM mesas WHERE id = ?');
if (!$stmt) { error('Error al preparar consulta: ' . $conn->error); }
$stmt->bind_param('i', $mesa_id);
if (!$stmt->execute()) { $stmt->close(); error('Error al ejecutar consulta: ' . $stmt->error); }
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($res) {
    $mesaUsuarioId = $res['usuario_id'] !== null ? (int)$res['usuario_id'] : null;
}

if ($rol !== 'admin') {
    $esPropia = ($mesaUsuarioId !== null && $mesaUsuarioId === (int)$usuarioActualId);
    $sinAsignar = ($mesaUsuarioId === null || $mesaUsuarioId === 0);
    if (!$esPropia && !$sinAsignar) {
        // Requiere contraseña del usuario asignado a la mesa
        $pass_asignado = $input['pass_asignado'] ?? null;
        if ($pass_asignado === null || $pass_asignado === '') {
            error('Se requiere la contraseña del mesero asignado');
        }
        $stmtU = $conn->prepare('SELECT contrasena, activo FROM usuarios WHERE id = ? LIMIT 1');
        if (!$stmtU) { error('Error al preparar consulta de usuario: ' . $conn->error); }
        $stmtU->bind_param('i', $mesaUsuarioId);
        if (!$stmtU->execute()) { $stmtU->close(); error('Error al ejecutar consulta de usuario: ' . $stmtU->error); }
        $usr = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        if (!$usr || (int)$usr['activo'] !== 1) {
            error('Usuario asignado inválido');
        }
        $hashDb = (string)$usr['contrasena'];
        $ok = ($hashDb === $pass_asignado) || ($hashDb === sha1($pass_asignado));
        if (!$ok) {
            error('Contraseña del mesero asignado incorrecta');
        }
        // Autorizado por contraseña válida
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
    $stmt = $conn->prepare("UPDATE mesas
SET estado = 'libre',
    tiempo_ocupacion_inicio = NULL,
    estado_reserva = 'ninguna',
    nombre_reserva = NULL,
    fecha_reserva = NULL
WHERE id = ?
");
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
