<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error('JSON inválido');
}

$tipo          = isset($input['tipo']) ? $input['tipo'] : null;
$mesa_id       = isset($input['mesa_id']) ? (int) $input['mesa_id'] : null;
$repartidor_id = isset($input['repartidor_id']) ? (int) $input['repartidor_id'] : null;
$usuario_id    = isset($input['usuario_id']) ? (int) $input['usuario_id'] : null;
$productos     = isset($input['productos']) && is_array($input['productos']) ? $input['productos'] : null;

if (!$tipo || !$usuario_id || !$productos) {
    error('Datos incompletos para crear la venta');
}

if ($tipo === 'mesa') {
    if (!$mesa_id || $repartidor_id) {
        error('Venta en mesa requiere mesa_id y sin repartidor_id');
    }
} elseif ($tipo === 'domicilio') {
    if (!$repartidor_id || $mesa_id) {
        error('Venta a domicilio requiere repartidor_id y sin mesa_id');
    }
} else {
    error('Tipo de venta inválido');
}

$total = 0;
foreach ($productos as $p) {
    if (!isset($p['producto_id'], $p['cantidad'], $p['precio_unitario'])) {
        error('Formato de producto incorrecto');
    }
    $total += $p['cantidad'] * $p['precio_unitario'];
}

$stmt = $conn->prepare('INSERT INTO ventas (mesa_id, repartidor_id, usuario_id, tipo_entrega, total) VALUES (?, ?, ?, ?, ?)');
if (!$stmt) {
    error('Error al preparar venta: ' . $conn->error);
}
$stmt->bind_param('iiisd', $mesa_id, $repartidor_id, $usuario_id, $tipo, $total);
if (!$stmt->execute()) {
    error('Error al crear venta: ' . $stmt->error);
}
$venta_id = $stmt->insert_id;
$stmt->close();

$detalle = $conn->prepare('INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
if (!$detalle) {
    error('Error al preparar detalle: ' . $conn->error);
}

foreach ($productos as $p) {
    $producto_id     = (int) $p['producto_id'];
    $cantidad        = (int) $p['cantidad'];
    $precio_unitario = (float) $p['precio_unitario'];

    $detalle->bind_param('iiid', $venta_id, $producto_id, $cantidad, $precio_unitario);
    if (!$detalle->execute()) {
        $detalle->close();
        error('Error al insertar detalle: ' . $detalle->error);
    }
}
$detalle->close();

// Lógica reemplazada por base de datos: ver bd.sql (Logs)
// Registrar acción en logs
$log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
if ($log) {
    $mod = 'ventas';
    $accion = 'Alta de venta';
    $log->bind_param('issi', $usuario_id, $mod, $accion, $venta_id);
    $log->execute();
    $log->close();
}

success(['venta_id' => $venta_id]);

