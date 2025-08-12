<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tipo   = $input['tipo_movimiento'] ?? '';
$monto  = isset($input['monto']) ? (float)$input['monto'] : 0;
$motivo = trim($input['motivo'] ?? '');

if (!in_array($tipo, ['deposito', 'retiro'], true)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de movimiento inválido']);
    exit;
}
if ($monto <= 0) {
    echo json_encode(['success' => false, 'error' => 'El monto debe ser mayor a 0']);
    exit;
}
if ($motivo === '') {
    echo json_encode(['success' => false, 'error' => 'Motivo requerido']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada']);
    exit;
}
$usuario_id = (int)$_SESSION['usuario_id'];

$stmt = $conn->prepare("SELECT id FROM corte_caja WHERE usuario_id = ? AND fecha_fin IS NULL ORDER BY id DESC LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error al consultar corte: ' . $conn->error]);
    exit;
}
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $corte_id = (int)$row['id'];
} else {
    echo json_encode(['success' => false, 'error' => 'No hay corte abierto']);
    $stmt->close();
    exit;
}
$stmt->close();

$fecha = date('Y-m-d H:i:s');
$stmt = $conn->prepare("INSERT INTO movimientos_caja (corte_id, usuario_id, tipo_movimiento, monto, motivo, fecha) VALUES (?,?,?,?,?,?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error al preparar inserción: ' . $conn->error]);
    exit;
}
$stmt->bind_param('iisdss', $corte_id, $usuario_id, $tipo, $monto, $motivo, $fecha);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Movimiento registrado correctamente']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al registrar movimiento: ' . $stmt->error]);
}
$stmt->close();
