<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$corte_id   = $input['corte_id'] ?? null;
$usuario_id = $input['usuario_id'] ?? null;

if (!$corte_id || !$usuario_id) {
    error('Datos incompletos');
}

$stmt = $conn->prepare('SELECT fecha_inicio FROM corte_caja WHERE id = ? AND usuario_id = ? AND fecha_fin IS NULL');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('ii', $corte_id, $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    $stmt->close();
    error('Corte no encontrado o ya cerrado');
}
$row = $res->fetch_assoc();
$fecha_inicio = $row['fecha_inicio'];
$stmt->close();

// Lógica ahora gestionada por la base de datos vía STORED PROCEDURE
$stmt = $conn->prepare('CALL sp_cerrar_corte(?)');
if (!$stmt) {
    error('Error al preparar cierre: ' . $conn->error);
}
$stmt->bind_param('i', $usuario_id);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar cierre: ' . $stmt->error);
}
$stmt->close();

$info = $conn->prepare('SELECT total FROM corte_caja WHERE id = ?');
if (!$info) {
    error('Error al obtener total: ' . $conn->error);
}
$info->bind_param('i', $corte_id);
if (!$info->execute()) {
    $info->close();
    error('Error al consultar corte: ' . $info->error);
}
$row = $info->get_result()->fetch_assoc();
$info->close();

success(['total' => (float)($row['total'] ?? 0)]);
?>
