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
$corte_id      = isset($input['corte_id']) ? (int) $input['corte_id'] : null;
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

// Si la venta es en mesa, revisar si ya existe una venta activa para esa mesa
if ($tipo === 'mesa') {
    $check = $conn->prepare("SELECT id, usuario_id FROM ventas WHERE mesa_id = ? AND estatus = 'activa' LIMIT 1");
    if (!$check) {
        error('Error al preparar consulta: ' . $conn->error);
    }
    $check->bind_param('i', $mesa_id);
    if (!$check->execute()) {
        $check->close();
        error('Error al ejecutar consulta: ' . $check->error);
    }
    $res = $check->get_result();
    $existing = $res->fetch_assoc();
    $check->close();
    if ($existing) {
        $venta_id = (int)$existing['id'];
        // Si se especificó un usuario distinto, actualizarlo
        if ($usuario_id && $usuario_id !== (int)$existing['usuario_id']) {
            $upUser = $conn->prepare('UPDATE ventas SET usuario_id = ? WHERE id = ?');
            if ($upUser) {
                $upUser->bind_param('ii', $usuario_id, $venta_id);
                $upUser->execute();
                $upUser->close();
            }
        }
        // Actualizar total acumulado y asignar corte si no tiene
        $upTotal = $conn->prepare('UPDATE ventas SET total = total + ?, corte_id = IFNULL(corte_id, ?) WHERE id = ?');
        if ($upTotal) {
            $upTotal->bind_param('dii', $total, $corte_id, $venta_id);
            $upTotal->execute();
            $upTotal->close();
        }
    }
}

$nueva_venta = false;

if (!isset($venta_id)) {
    if ($tipo === 'domicilio') {
        $stmt = $conn->prepare('INSERT INTO ventas (mesa_id, repartidor_id, usuario_id, tipo_entrega, total, corte_id, fecha_asignacion) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    } else {
        $stmt = $conn->prepare('INSERT INTO ventas (mesa_id, repartidor_id, usuario_id, tipo_entrega, total, corte_id) VALUES (?, ?, ?, ?, ?, ?)');
    }
    if (!$stmt) {
        error('Error al preparar venta: ' . $conn->error);
    }
    $stmt->bind_param('iiisdi', $mesa_id, $repartidor_id, $usuario_id, $tipo, $total, $corte_id);
    if (!$stmt->execute()) {
        error('Error al crear venta: ' . $stmt->error);
    }
    $venta_id = $stmt->insert_id;
    $stmt->close();
    $nueva_venta = true;
}

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
    $accion = $nueva_venta ? 'Alta de venta' : 'Actualización de venta';
    $log->bind_param('issi', $usuario_id, $mod, $accion, $venta_id);
    $log->execute();
    $log->close();
}

success(['venta_id' => $venta_id]);

