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

$usuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;

$sql = "SELECT b.id, b.usuario_id, u.nombre AS usuario_nombre,
               b.producto_id, p.nombre AS producto_nombre,
               b.tipo_regla, b.dia_semana, b.fecha, b.cantidad_max, b.activo
          FROM consumos_beneficios b
          JOIN usuarios u ON u.id = b.usuario_id
          JOIN productos p ON p.id = b.producto_id
         WHERE 1=1";
$types = '';
$params = [];
if ($usuarioId > 0) {
    $sql .= " AND b.usuario_id = ?";
    $types .= 'i';
    $params[] = $usuarioId;
}
$sql .= " ORDER BY u.nombre, p.nombre";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error al preparar beneficios', 'data' => null]);
    exit;
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => false, 'mensaje' => 'Error al obtener beneficios', 'data' => null]);
    exit;
}
$res = $stmt->get_result();
$beneficios = [];
while ($row = $res->fetch_assoc()) {
    $beneficios[] = [
        'id' => (int)$row['id'],
        'usuario_id' => (int)$row['usuario_id'],
        'usuario_nombre' => $row['usuario_nombre'],
        'producto_id' => (int)$row['producto_id'],
        'producto_nombre' => $row['producto_nombre'],
        'tipo_regla' => $row['tipo_regla'],
        'dia_semana' => $row['dia_semana'] !== null ? (int)$row['dia_semana'] : null,
        'fecha' => $row['fecha'],
        'cantidad_max' => (int)$row['cantidad_max'],
        'activo' => (int)$row['activo']
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'mensaje' => 'OK', 'data' => $beneficios]);
