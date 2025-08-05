<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('Sesión no iniciada');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error('JSON inválido');
}

$mesa_id   = isset($input['mesa_id']) ? (int)$input['mesa_id'] : null;
$productos = isset($input['productos']) && is_array($input['productos']) ? $input['productos'] : null;
$sede_id   = isset($input['sede_id']) && !empty($input['sede_id']) ? (int)$input['sede_id'] : 1;

if (!$mesa_id || !$productos) {
    error('Datos incompletos');
}

$cajero_id = (int)$_SESSION['usuario_id'];

// Verificar corte abierto para el cajero
$qc = $conn->prepare('SELECT id FROM corte_caja WHERE usuario_id = ? AND fecha_fin IS NULL ORDER BY fecha_inicio DESC LIMIT 1');
if (!$qc) {
    error('Error al preparar consulta de corte: ' . $conn->error);
}
$qc->bind_param('i', $cajero_id);
$qc->execute();
$resCorte = $qc->get_result();
if ($resCorte->num_rows === 0) {
    $qc->close();
    error('Debe abrir caja antes de iniciar una venta.');
}
$corteRow = $resCorte->fetch_assoc();
$corte_id = (int)$corteRow['id'];
$qc->close();

// Verificar si ya hay venta activa para la mesa
$check = $conn->prepare("SELECT id FROM ventas WHERE mesa_id = ? AND estatus = 'activa' LIMIT 1");
if (!$check) {
    error('Error al preparar consulta: ' . $conn->error);
}
$check->bind_param('i', $mesa_id);
$check->execute();
$resCheck = $check->get_result();
if ($resCheck->num_rows > 0) {
    $check->close();
    error('La mesa ya tiene una venta activa');
}
$check->close();

$total = 0;
foreach ($productos as $p) {
    if (!isset($p['producto_id'], $p['cantidad'], $p['precio_unitario'])) {
        error('Formato de producto incorrecto');
    }
    $total += $p['cantidad'] * $p['precio_unitario'];
}

$stmt = $conn->prepare('INSERT INTO ventas (mesa_id, tipo_entrega, total, corte_id, cajero_id, sede_id) VALUES (?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    error('Error al preparar venta: ' . $conn->error);
}
$tipo = 'mesa';
$stmt->bind_param('isdiii', $mesa_id, $tipo, $total, $corte_id, $cajero_id, $sede_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al crear venta: ' . $stmt->error);
}
$venta_id = $stmt->insert_id;
$stmt->close();

$detalle = $conn->prepare('INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
if (!$detalle) {
    error('Error al preparar detalle: ' . $conn->error);
}
foreach ($productos as $p) {
    $producto_id = (int)$p['producto_id'];
    $cantidad = (int)$p['cantidad'];
    $precio = (float)$p['precio_unitario'];
    $detalle->bind_param('iiid', $venta_id, $producto_id, $cantidad, $precio);
    if (!$detalle->execute()) {
        $detalle->close();
        error('Error al insertar detalle: ' . $detalle->error);
    }
}
$detalle->close();

$log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
if ($log) {
    $mod = 'ventas';
    $accion = 'Alta de venta';
    $log->bind_param('issi', $cajero_id, $mod, $accion, $venta_id);
    $log->execute();
    $log->close();
}

success(['venta_id' => $venta_id]);
