<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$usuario_id = null;
if (isset($_GET['usuario_id'])) {
    $usuario_id = (int)$_GET['usuario_id'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['usuario_id'])) {
        $usuario_id = (int)$input['usuario_id'];
    }
}

if (!$usuario_id) {
    error('usuario_id requerido');
}

$stmt = $conn->prepare('SELECT id, fecha_inicio FROM corte_caja WHERE usuario_id = ? AND fecha_fin IS NULL');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $usuario_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar consulta: ' . $stmt->error);
}
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    success(['abierto' => true, 'corte_id' => (int)$row['id'], 'fecha_inicio' => $row['fecha_inicio']]);
}
$stmt->close();

success(['abierto' => false]);
?>
