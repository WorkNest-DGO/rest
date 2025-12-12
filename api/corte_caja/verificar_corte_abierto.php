<?php


session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Sesión no iniciada'
    ]);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

$usuario_id = $_SESSION['usuario_id'];

$sede_id = null;
$uStmt = $conn->prepare('SELECT sede_id FROM usuarios WHERE id = ?');
if ($uStmt) {
    $uStmt->bind_param('i', $usuario_id);
    $uStmt->execute();
    $sede_id = (int)($uStmt->get_result()->fetch_assoc()['sede_id'] ?? 0);
    $uStmt->close();
}
if (!$sede_id) {
    error('Usuario sin sede');
}

$sql = "SELECT c.id FROM corte_caja c 
        JOIN usuarios u ON u.id = c.usuario_id
        WHERE u.sede_id = ? AND c.fecha_fin IS NULL 
        ORDER BY c.fecha_inicio DESC 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sede_id);
$stmt->execute();
$result = $stmt->get_result();

$respuesta = [
    'success' => true,
    'resultado' => [
        'abierto' => $result->num_rows > 0
    ]
];

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $_SESSION['corte_id'] = (int)$row['id'];
    $respuesta['resultado']['corte_id'] = $row['id'];
}

echo json_encode($respuesta);
?>


