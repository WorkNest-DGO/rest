<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
$conn->set_charset('utf8mb4');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado', 'data' => null]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    echo json_encode(['success' => false, 'mensaje' => 'JSON invalido', 'data' => null]);
    exit;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
$usuarioId = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 0;
$productoId = isset($input['producto_id']) ? (int)$input['producto_id'] : 0;
$tipoRegla = trim($input['tipo_regla'] ?? '');
$diaSemana = isset($input['dia_semana']) ? (int)$input['dia_semana'] : null;
$fecha = isset($input['fecha']) ? trim((string)$input['fecha']) : null;
$cantidadMax = isset($input['cantidad_max']) ? (int)$input['cantidad_max'] : 0;
$activo = isset($input['activo']) ? (int)$input['activo'] : 1;

if ($usuarioId <= 0 || $productoId <= 0 || $cantidadMax <= 0) {
    echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos', 'data' => null]);
    exit;
}

if ($tipoRegla !== 'semana' && $tipoRegla !== 'fecha') {
    echo json_encode(['success' => false, 'mensaje' => 'Tipo de regla invalido', 'data' => null]);
    exit;
}

if ($tipoRegla === 'semana') {
    if ($diaSemana < 1 || $diaSemana > 7) {
        echo json_encode(['success' => false, 'mensaje' => 'Dia de semana invalido', 'data' => null]);
        exit;
    }
    $fecha = null;
} else {
    if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        echo json_encode(['success' => false, 'mensaje' => 'Fecha invalida', 'data' => null]);
        exit;
    }
    $diaSemana = null;
}

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE consumos_beneficios
                               SET usuario_id = ?, producto_id = ?, tipo_regla = ?, dia_semana = ?, fecha = ?, cantidad_max = ?, activo = ?
                             WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'mensaje' => 'Error al preparar beneficio', 'data' => null]);
        exit;
    }
    $stmt->bind_param('iisisiii', $usuarioId, $productoId, $tipoRegla, $diaSemana, $fecha, $cantidadMax, $activo, $id);
    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => false, 'mensaje' => 'Error al actualizar beneficio', 'data' => null]);
        exit;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'mensaje' => 'Beneficio actualizado', 'data' => ['id' => $id]]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO consumos_beneficios (usuario_id, producto_id, tipo_regla, dia_semana, fecha, cantidad_max, activo)
                         VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar beneficio', 'data' => null]);
    exit;
}
$stmt->bind_param('iisisii', $usuarioId, $productoId, $tipoRegla, $diaSemana, $fecha, $cantidadMax, $activo);
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'mensaje' => 'Error al guardar beneficio', 'data' => null]);
    exit;
}
$newId = $stmt->insert_id;
$stmt->close();

echo json_encode(['success' => true, 'mensaje' => 'Beneficio creado', 'data' => ['id' => $newId]]);
