<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Verifica si existe cualquier corte abierto (de cualquier usuario) y devuelve datos del usuario.
// No modifica variables de sesión.

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Método no permitido');
}

$sede_id = null;
if (isset($_SESSION['usuario_id'])) {
    $sStmt = $conn->prepare('SELECT sede_id FROM usuarios WHERE id = ?');
    if ($sStmt) {
        $sStmt->bind_param('i', $_SESSION['usuario_id']);
        $sStmt->execute();
        $sede_id = (int)($sStmt->get_result()->fetch_assoc()['sede_id'] ?? 0);
        $sStmt->close();
    }
}

$sql = "SELECT c.id AS corte_id, u.id AS usuario_id, u.usuario, u.nombre, u.rol
        FROM corte_caja c
        JOIN usuarios u ON u.id = c.usuario_id
        WHERE c.fecha_fin IS NULL" . ($sede_id ? " AND u.sede_id = ?" : "") . "
        ORDER BY c.fecha_inicio DESC
        LIMIT 1";

if ($sede_id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $sede_id);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($sql);
}
if (!$res) {
    error('Error al verificar corte: ' . $conn->error);
}

$abierto = $res->num_rows > 0;
$payload = [ 'abierto' => $abierto ];
if ($abierto) {
    $row = $res->fetch_assoc();
    $payload['corte_id'] = (int)$row['corte_id'];
    $payload['usuario'] = [
        'id' => (int)$row['usuario_id'],
        'usuario' => $row['usuario'],
        'nombre' => $row['nombre'],
        'rol' => $row['rol']
    ];
}

success($payload);
?>
