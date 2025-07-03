<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
if (!$usuario_id) {
    error('usuario_id requerido');
}

$stmt = $conn->prepare('SELECT monto FROM fondo WHERE usuario_id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    success(['existe' => true, 'monto' => (float)$row['monto']]);
} else {
    success(['existe' => false]);
}
?>
