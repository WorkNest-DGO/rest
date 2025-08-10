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

// Obtener folio inicial del turno actual o último folio existente
$folio_inicio = 0;
$res = $conn->query("SELECT MIN(folio) AS folio FROM tickets WHERE fecha >= CURDATE()");
if ($res && ($row = $res->fetch_assoc()) && $row['folio'] !== null) {
    $folio_inicio = (int)$row['folio'];
}
if ($folio_inicio === 0) {
    $res = $conn->query("SELECT IFNULL(MAX(folio), 0) AS folio FROM tickets");
    if ($res && ($row = $res->fetch_assoc())) {
        $folio_inicio = (int)$row['folio'];
    }
}

$stmt = $conn->prepare('INSERT INTO corte_caja (usuario_id, fecha_inicio, fondo_inicial, folio_inicio) VALUES (?, NOW(), ?, ?)');
if (!$stmt) {
    error('Error al preparar inserción: ' . $conn->error);
}
$stmt->bind_param('idi', $usuario_id, $fondo_inicial, $folio_inicio);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al crear corte: ' . $stmt->error);
}
$corte_id = $stmt->insert_id;
$stmt->close();

// Lógica reemplazada por base de datos: ver bd.sql (Logs)
$log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
if ($log) {
    $mod = 'corte_caja';
    $accion = 'Creación de corte';
    $log->bind_param('issi', $usuario_id, $mod, $accion, $corte_id);
    $log->execute();
    $log->close();
}

success(['corte_id' => $corte_id]);
?>
