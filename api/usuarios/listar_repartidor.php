<?php
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

$userIdFromQuery = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0);
if ($userIdFromQuery <= 0) {
    success([]);
}

$colUsuarioSede = null;
if (column_exists($conn, 'usuarios', 'sede_id')) {
    $colUsuarioSede = 'sede_id';
} elseif (column_exists($conn, 'usuarios', 'sede')) {
    $colUsuarioSede = 'sede';
}
if (!$colUsuarioSede) {
    success([]);
}

$sedeFiltro = null;
$stmtSede = $conn->prepare("SELECT {$colUsuarioSede} AS sede_val FROM usuarios WHERE id = ? LIMIT 1");
if (!$stmtSede) {
    error('Error al preparar usuario: ' . $conn->error);
}
$stmtSede->bind_param('i', $userIdFromQuery);
if ($stmtSede->execute()) {
    $rs = $stmtSede->get_result();
    if ($row = $rs->fetch_assoc()) {
        if (isset($row['sede_val'])) $sedeFiltro = (int)$row['sede_val'];
    }
}
$stmtSede->close();
if ($sedeFiltro === null) {
    success([]);
}

$query = "SELECT id, nombre FROM usuarios WHERE rol = 'repartidor' AND activo = 1 AND {$colUsuarioSede} = ? ORDER BY nombre";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error('Error al obtener repartidores: ' . $conn->error);
}
$stmt->bind_param('i', $sedeFiltro);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    $stmt->close();
    error('Error al obtener repartidores: ' . $conn->error);
}
$repartidores = [];
while ($row = $result->fetch_assoc()) {
    $repartidores[] = $row;
}
$stmt->close();
success($repartidores);
