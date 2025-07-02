<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$corte_id   = $input['corte_id'] ?? null;
$usuario_id = $input['usuario_id'] ?? null;
$observa    = $input['observaciones'] ?? '';

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


// Lógica reemplazada por base de datos: ver bd.sql (SP)
$call = $conn->prepare('CALL sp_cerrar_corte(?)');
if (!$call) {
    error('Error al preparar cierre: ' . $conn->error);
}
$call->bind_param('i', $usuario_id);
if (!$call->execute()) {
    $call->close();
    error('Error al cerrar corte: ' . $call->error);
}
$call->close();

$updObs = $conn->prepare('UPDATE corte_caja SET observaciones = ? WHERE id = ?');
if ($updObs) {
    $updObs->bind_param('si', $observa, $corte_id);
    $updObs->execute();
    $updObs->close();
}

$stmt = $conn->prepare('SELECT fecha_inicio, fecha_fin, total FROM corte_caja WHERE id = ?');
if (!$stmt) {
    error('Error al obtener corte: ' . $conn->error);
}
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$updVentas = $conn->prepare("UPDATE ventas SET corte_id = ? WHERE usuario_id = ? AND fecha >= ? AND fecha <= ? AND estatus = 'cerrada' AND (corte_id IS NULL)");
if ($updVentas) {
    $updVentas->bind_param('iiss', $corte_id, $usuario_id, $info['fecha_inicio'], $info['fecha_fin']);
    $updVentas->execute();
    $updVentas->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) AS num FROM ventas WHERE usuario_id = ? AND fecha >= ? AND fecha <= ? AND estatus = 'cerrada'");
if ($stmt) {
    $stmt->bind_param('iss', $usuario_id, $info['fecha_inicio'], $info['fecha_fin']);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $numVentas = (int)($c['num'] ?? 0);
    $stmt->close();
} else {
    $numVentas = 0;
}

// Registrar acción en logs
$log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
if ($log) {
    $mod = 'corte_caja';
    $accion = 'Cierre de corte';
    $log->bind_param('issi', $usuario_id, $mod, $accion, $corte_id);
    $log->execute();
    $log->close();
}

success(['ventas_realizadas' => $numVentas, 'total' => (float)$info['total']]);
?>
