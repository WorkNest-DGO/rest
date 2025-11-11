<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Verifica si existe ALGÚN cajero con corte abierto, independiente del usuario en sesión.
// No modifica variables de sesión.

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Método no permitido');
}

$sqlPreferCajero = "
  SELECT c.id AS corte_id, u.id AS usuario_id, u.usuario, u.nombre, u.rol
  FROM corte_caja c
  JOIN usuarios u ON u.id = c.usuario_id
  WHERE c.fecha_fin IS NULL
  ORDER BY (LOWER(TRIM(u.rol)) = 'cajero') DESC, c.fecha_inicio DESC
  LIMIT 1
";

$res = $conn->query($sqlPreferCajero);
if (!$res) {
    error('Error al verificar corte: ' . $conn->error);
}

$abierto = $res->num_rows > 0;
$payload = [ 'abierto' => $abierto ];
if ($abierto) {
    $row = $res->fetch_assoc();
    $payload['corte_id'] = (int)$row['corte_id'];
    $payload['cajero'] = [
        'id' => (int)$row['usuario_id'],
        'usuario' => $row['usuario'],
        'nombre' => $row['nombre'],
        'rol' => $row['rol']
    ];
}

success($payload);
?>
