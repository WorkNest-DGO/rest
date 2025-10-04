<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Método no permitido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('Sesión no iniciada');
}

$usuario_id = (int)$_SESSION['usuario_id'];

// Determinar corte actual del usuario en sesión
$corte_id = $_SESSION['corte_id'] ?? null;
if (!$corte_id) {
    $stmt = $conn->prepare('SELECT id FROM corte_caja WHERE usuario_id = ? AND fecha_fin IS NULL ORDER BY fecha_inicio DESC LIMIT 1');
    if (!$stmt) {
        error('Error al verificar corte: ' . $conn->error);
    }
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $corte_id = (int)$row['id'];
        $_SESSION['corte_id'] = $corte_id; // mantener consistencia con otras rutas
    }
    $stmt->close();
}

if (!$corte_id) {
    // No hay corte abierto del usuario actual -> regresar lista vacía controlada
    success([ 'corte_id' => null, 'movimientos' => [] ]);
}

// Listar movimientos del corte con nombre de usuario
$sql = "SELECT m.fecha, m.tipo_movimiento, m.monto, m.motivo, u.nombre AS usuario
        FROM movimientos_caja m
        JOIN usuarios u ON u.id = m.usuario_id
        WHERE m.corte_id = ?
        ORDER BY m.fecha DESC, m.id DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$result = $stmt->get_result();
$movs = [];
while ($row = $result->fetch_assoc()) {
    $movs[] = [
        'fecha' => $row['fecha'],
        'tipo'  => $row['tipo_movimiento'],
        'monto' => (float)$row['monto'],
        'motivo'=> $row['motivo'],
        'usuario' => $row['usuario'],
    ];
}
$stmt->close();

success([ 'corte_id' => $corte_id, 'movimientos' => $movs ]);
?>

