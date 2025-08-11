<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = $input['usuario_id'] ?? null;
$fondo_inicial = isset($input['fondo_inicial']) ? (float)$input['fondo_inicial'] : null;
if (!$usuario_id) {
    error('usuario_id requerido');
}
// Si no se envía fondo_inicial, obtenerlo de la tabla fondo
if ($fondo_inicial === null) {
    $stmt = $conn->prepare('SELECT monto FROM fondo WHERE usuario_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $fondo_inicial = (float)$row['monto'];
        }
        $stmt->close();
    }
    if ($fondo_inicial === null) {
        error('fondo_inicial requerido');
    }
} else {
    // Guardar fondo para futuros cortes
    $g = $conn->prepare('INSERT INTO fondo (usuario_id, monto) VALUES (?, ?) ON DUPLICATE KEY UPDATE monto = VALUES(monto)');
    if ($g) {
        $g->bind_param('id', $usuario_id, $fondo_inicial);
        $g->execute();
        $g->close();
    }
}

$stmt = $conn->prepare('SELECT id FROM corte_caja WHERE usuario_id = ? AND fecha_fin IS NULL');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    error('Ya existe un corte abierto para este usuario');
}
$stmt->close();

$fecha_inicio = date('Y-m-d H:i:s');
$stmt = $conn->prepare('INSERT INTO corte_caja (usuario_id, fecha_inicio, fondo_inicial) VALUES (?, ?, ?)');
if (!$stmt) {
    error('Error al preparar inserción: ' . $conn->error);
}
$stmt->bind_param('isd', $usuario_id, $fecha_inicio, $fondo_inicial);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al crear corte: ' . $stmt->error);
}
$corte_id = $stmt->insert_id;
$stmt->close();

// Calcular siguiente folio disponible para el usuario
$folio_inicio = 1;
$q = $conn->prepare('SELECT IFNULL(MAX(t.folio), 0) + 1 AS siguiente FROM tickets t INNER JOIN ventas v ON v.id = t.venta_id WHERE v.usuario_id = ?');
if ($q) {
    $q->bind_param('i', $usuario_id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $folio_inicio = (int)($r['siguiente'] ?? 1);
    $q->close();
}
if ($folio_inicio <= 0) {
    $folio_inicio = 1;
}

$u = $conn->prepare('UPDATE corte_caja SET folio_inicio = ? WHERE id = ?');
if ($u) {
    $u->bind_param('ii', $folio_inicio, $corte_id);
    $u->execute();
    $u->close();
}

// Lógica reemplazada por base de datos: ver bd.sql (Logs)
$log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
if ($log) {
    $mod = 'corte_caja';
    $accion = 'Creación de corte';
    $log->bind_param('issi', $usuario_id, $mod, $accion, $corte_id);
    $log->execute();
    $log->close();
}

success([
    'corte_id'     => $corte_id,
    'folio_inicio' => $folio_inicio,
    'fecha_inicio' => $fecha_inicio
]);
?>
