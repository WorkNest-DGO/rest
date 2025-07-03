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

$sql = "SELECT id FROM corte_caja 
        WHERE usuario_id = ? AND fecha_fin IS NULL 
        ORDER BY fecha_inicio DESC 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
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
    $respuesta['resultado']['corte_id'] = $row['id'];
}

echo json_encode($respuesta);
?>


