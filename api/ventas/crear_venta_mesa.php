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

// Determinar cajero y corte abiertos
$usuario_sesion_id = (int)$_SESSION['usuario_id'];
$rol = isset($_SESSION['rol']) ? strtolower((string)$_SESSION['rol']) : '';
$corte_id = null;
$cajero_id = null;

if ($rol === 'cajero') {
    // Usar el cajero de sesión
    $cajero_id = $usuario_sesion_id;
    $qc = $conn->prepare('SELECT id FROM corte_caja WHERE usuario_id = ? AND fecha_fin IS NULL ORDER BY fecha_inicio DESC LIMIT 1');
    if (!$qc) { error('Error al preparar consulta de corte: ' . $conn->error); }
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
} else {
    // Buscar cualquier cajero con corte abierto (más reciente)
    $sql = "SELECT c.id AS corte_id, u.id AS cajero_id
            FROM corte_caja c
            JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.fecha_fin IS NULL
            ORDER BY c.fecha_inicio DESC
            LIMIT 1";
    $rs = $conn->query($sql);
    if (!$rs) { error('Error al consultar cortes: ' . $conn->error); }
    if ($rs->num_rows === 0) { error('No hay cajero con corte abierto.'); }
    $row = $rs->fetch_assoc();
    $corte_id = (int)$row['corte_id'];
    $cajero_id = (int)$row['cajero_id'];
}

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

$stmt = $conn->prepare('INSERT INTO ventas (mesa_id, tipo_entrega, total, corte_id, cajero_id, sede_id, propina_efectivo, propina_cheque, propina_tarjeta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    error('Error al preparar venta: ' . $conn->error);
}
$tipo = 'mesa';
// Propinas iniciales en 0 para cumplir con NOT NULL sin default
$propina_efectivo = 0.0; $propina_cheque = 0.0; $propina_tarjeta = 0.0;
$stmt->bind_param('isdiiiddd', $mesa_id, $tipo, $total, $corte_id, $cajero_id, $sede_id, $propina_efectivo, $propina_cheque, $propina_tarjeta);
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
// Llevar registro de IDs creados para notificar a cocina
$ids_creados = [];
foreach ($productos as $p) {
    $producto_id = (int)$p['producto_id'];
    $cantidad = (int)$p['cantidad'];
    $precio = (float)$p['precio_unitario'];
    $detalle->bind_param('iiid', $venta_id, $producto_id, $cantidad, $precio);
    if (!$detalle->execute()) {
        $detalle->close();
        error('Error al insertar detalle: ' . $detalle->error);
    }
    $nid = (int)$detalle->insert_id;
    if ($nid) { $ids_creados[] = $nid; }
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

// Notificar cambio a historial de ventas (long-poll) - silencioso si no hay permisos
try {
    $dir = __DIR__ . '/runtime';
    $okDir = is_dir($dir) || @mkdir($dir, 0775, true);
    if ($okDir && @is_writable($dir)) {
        $verFile   = $dir . '/ventas_version.txt';
        $eventsLog = $dir . '/ventas_events.jsonl';
        $fp = @fopen($verFile, 'c+');
        if ($fp) {
            flock($fp, LOCK_EX);
            rewind($fp);
            $txt  = stream_get_contents($fp);
            $cur  = intval(trim($txt ?? '0'));
            $next = $cur + 1;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string)$next);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            $evt = json_encode(['v'=>$next,'ids'=>[$venta_id],'ts'=>time()]);
            @file_put_contents($eventsLog, $evt . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
} catch (Throwable $e) { /* no interrumpir */ }

// Notificar a pantallas de cocina (long-poll) sobre nuevos detalles sin usar HTTP
if (!empty($ids_creados)) {
    try {
        require_once __DIR__ . '/../cocina/notify_lib.php';
        @cocina_notify(array_values(array_unique($ids_creados)));
    } catch (\Throwable $e) { /* noop */ }
}

success(['venta_id' => $venta_id]);

