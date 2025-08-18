<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error('JSON inválido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('Sesión no iniciada');
}

$cajero_id = (int)$_SESSION['usuario_id'];

$tipo          = isset($input['tipo']) ? $input['tipo'] : null;
$mesa_id       = isset($input['mesa_id']) ? (int) $input['mesa_id'] : null;
$repartidor_id = isset($input['repartidor_id']) ? (int) $input['repartidor_id'] : null;
$usuario_id    = isset($input['usuario_id']) ? (int) $input['usuario_id'] : null;
$corte_id      = isset($input['corte_id']) ? (int) $input['corte_id'] : null;
$productos     = isset($input['productos']) && is_array($input['productos']) ? $input['productos'] : null;
$observacion   = isset($input['observacion']) ? $input['observacion'] : null;
$sede_id       = isset($input['sede_id']) && !empty($input['sede_id']) ? (int)$input['sede_id'] : 1;

if (!$tipo || !$usuario_id || !$productos) {
    error('Datos incompletos para crear la venta');
}

if ($tipo === 'mesa') {
    if (!$mesa_id || $repartidor_id) {
        error('Venta en mesa requiere mesa_id y sin repartidor_id');
    }
    $estado = $conn->prepare('SELECT estado, usuario_id FROM mesas WHERE id = ?');
    if (!$estado) {
        error('Error al preparar consulta de mesa: ' . $conn->error);
    }
    $estado->bind_param('i', $mesa_id);
    if (!$estado->execute()) {
        $estado->close();
        error('Error al obtener estado de mesa: ' . $estado->error);
    }
    $resEstado = $estado->get_result();
    $rowEstado = $resEstado->fetch_assoc();
    $estado->close();
    if (!$rowEstado) {
        error('Mesa no encontrada');
    }
    if ((int)($rowEstado['usuario_id'] ?? 0) !== $usuario_id) {
        http_response_code(400);
        error('La mesa seleccionada pertenece a otro mesero. Actualiza la pantalla e inténtalo de nuevo.');
    }
    if ($rowEstado['estado'] !== 'libre') {
        error('La mesa seleccionada no está libre');
    }
} elseif ($tipo === 'domicilio') {
    if (!$repartidor_id || $mesa_id) {
        error('Venta a domicilio requiere repartidor_id y sin mesa_id');
    }
} elseif ($tipo === 'rapido') {
    if ($mesa_id || $repartidor_id) {
        error('Venta rápida no debe incluir mesa ni repartidor');
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
        $upTotal = $conn->prepare('UPDATE ventas SET total = total + ?, corte_id = IFNULL(corte_id, ?), cajero_id = IFNULL(cajero_id, ?) WHERE id = ?');
        if ($upTotal) {
            $upTotal->bind_param('diii', $total, $corte_id, $cajero_id, $venta_id);
            $upTotal->execute();
            $upTotal->close();
        }
    }
}

$nueva_venta = false;

if (!isset($venta_id)) {
    if ($tipo === 'domicilio') {
        $stmt = $conn->prepare('INSERT INTO ventas (mesa_id, repartidor_id, usuario_id, tipo_entrega, total, observacion, corte_id, cajero_id, fecha_asignacion, sede_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
    } else {
        $stmt = $conn->prepare('INSERT INTO ventas (mesa_id, repartidor_id, usuario_id, tipo_entrega, total, observacion, corte_id, cajero_id, sede_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    }
    if (!$stmt) {
        error('Error al preparar venta: ' . $conn->error);
    }
    if ($tipo === 'domicilio') {
        $stmt->bind_param('iiisdsiii', $mesa_id, $repartidor_id, $usuario_id, $tipo, $total, $observacion, $corte_id, $cajero_id, $sede_id);
    } else {
        $stmt->bind_param('iiisdsiii', $mesa_id, $repartidor_id, $usuario_id, $tipo, $total, $observacion, $corte_id, $cajero_id, $sede_id);
    }
    if (!$stmt->execute()) {
        error('Error al crear venta: ' . $stmt->error);
    }
    $venta_id = $stmt->insert_id;
    $stmt->close();
    $nueva_venta = true;
    if ($tipo === 'mesa') {
        $updMesa = $conn->prepare("UPDATE mesas SET estado = 'ocupada', tiempo_ocupacion_inicio = IF(tiempo_ocupacion_inicio IS NULL, NOW(), tiempo_ocupacion_inicio) WHERE id = ?");
        if ($updMesa) {
            $updMesa->bind_param('i', $mesa_id);
            $updMesa->execute();
            $updMesa->close();
        }
    }
}

// Insertar detalles de la venta y conservar el último ID generado
$detalle = $conn->prepare('INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
if (!$detalle) {
    error('Error al preparar detalle: ' . $conn->error);
}

$ultimo_detalle_id = 0;
foreach ($productos as $p) {
    $producto_id     = (int) $p['producto_id'];
    $cantidad        = (int) $p['cantidad'];
    $precio_unitario = (float) $p['precio_unitario'];

    $detalle->bind_param('iiid', $venta_id, $producto_id, $cantidad, $precio_unitario);
    if (!$detalle->execute()) {
        $detalle->close();
        error('Error al insertar detalle: ' . $detalle->error);
    }
    $ultimo_detalle_id = $detalle->insert_id;
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

success(['venta_id' => $venta_id, 'ultimo_detalle_id' => $ultimo_detalle_id]);