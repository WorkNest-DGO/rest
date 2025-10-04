<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Verifica si existe ALGÚN cajero con corte abierto, independiente del usuario en sesión.
// No modifica variables de sesión.

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Método no permitido');
}

$sql = "SELECT c.id AS corte_id, u.id AS cajero_id, u.usuario, u.nombre
        FROM corte_caja c
        JOIN usuarios u ON u.id = c.usuario_id
        WHERE u.rol = 'cajero' AND c.fecha_fin IS NULL
        ORDER BY c.fecha_inicio DESC
        LIMIT 1";

$res = $conn->query($sql);
if (!$res) {
    error('Error al verificar corte: ' . $conn->error);
}

$abierto = $res->num_rows > 0;
$payload = [ 'abierto' => $abierto ];
if ($abierto) {
    $row = $res->fetch_assoc();
    $payload['corte_id'] = (int)$row['corte_id'];
    $payload['cajero'] = [
        'id' => (int)$row['cajero_id'],
        'usuario' => $row['usuario'],
        'nombre' => $row['nombre']
    ];
}

success($payload);
?>

