<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

function column_exists(mysqli $db, string $table, string $column): bool {
    $tableSafe = $db->real_escape_string($table);
    $colSafe = $db->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$colSafe}'";
    $res = $db->query($sql);
    if (!$res) return false;
    $ok = $res->num_rows > 0;
    $res->close();
    return $ok;
}

// Determinar usuario (query -> sesión)
$userId = null;
if (isset($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
} elseif (isset($_GET['usuario_id'])) {
    $userId = (int)$_GET['usuario_id'];
} elseif (isset($_SESSION['usuario_id'])) {
    $userId = (int)$_SESSION['usuario_id'];
}
if (!$userId) {
    error('Debe indicar un usuario para listar mesas');
}

// Obtener sede del usuario
$colUsuarioSede = null;
if (column_exists($conn, 'usuarios', 'sede_id')) {
    $colUsuarioSede = 'sede_id';
} elseif (column_exists($conn, 'usuarios', 'sede')) {
    $colUsuarioSede = 'sede';
}
if (!$colUsuarioSede) {
    error('No existe columna de sede en usuarios');
}
$stmtSede = $conn->prepare("SELECT {$colUsuarioSede} AS sede_val FROM usuarios WHERE id = ? LIMIT 1");
if (!$stmtSede) {
    error('Error al preparar consulta de sede: ' . $conn->error);
}
$stmtSede->bind_param('i', $userId);
$stmtSede->execute();
$rsSede = $stmtSede->get_result();
$rowSede = $rsSede ? $rsSede->fetch_assoc() : null;
$stmtSede->close();
if (!$rowSede || !isset($rowSede['sede_val'])) {
    error('No se pudo determinar la sede del usuario');
}
$sedeFiltro = (int)$rowSede['sede_val'];

// Listar mesas filtradas por sede
$stmt = $conn->prepare("SELECT m.id, m.nombre, m.estado, m.usuario_id, u.nombre AS mesero_nombre
          FROM mesas m
          LEFT JOIN usuarios u ON m.usuario_id = u.id
          WHERE m.sede = ?
          ORDER BY m.id");
if (!$stmt) {
    error('Error al preparar consulta de mesas: ' . $conn->error);
}
$stmt->bind_param('i', $sedeFiltro);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    $stmt->close();
    error('Error al obtener mesas: ' . $conn->error);
}
$mesas = [];
while ($row = $result->fetch_assoc()) {
    $mesas[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre'],
        'usuario_id' => $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null,
        'mesero_nombre' => $row['mesero_nombre'],
        'estado' => $row['estado']
    ];
}
$stmt->close();
success($mesas);
