<?php
require_once __DIR__ . '/../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$usuario = $_POST['usuario'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';

$stmt = $conn->prepare('SELECT id FROM usuarios WHERE usuario = ? AND contrasena = ? AND activo = 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'mensaje' => 'Error de servidor']);
    exit;
}
$stmt->bind_param('ss', $usuario, $contrasena);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $_SESSION['usuario_id'] = (int)$row['id'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'mensaje' => 'Credenciales inválidas']);
}
$stmt->close();
