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

// Determinar sede (prioridad: user_id/usuario_id en query -> usuario en sesión -> sede en sesión)
$sedeFiltro = null;
$userIdFromQuery = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : null);
$uid = $userIdFromQuery && $userIdFromQuery > 0
    ? $userIdFromQuery
    : (isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null);

$colUsuarioSede = null;
if (column_exists($conn, 'usuarios', 'sede_id')) {
    $colUsuarioSede = 'sede_id';
} elseif (column_exists($conn, 'usuarios', 'sede')) {
    $colUsuarioSede = 'sede';
}
if ($uid && $colUsuarioSede) {
    $stmtSede = $conn->prepare("SELECT {$colUsuarioSede} AS sede_val FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmtSede) {
        $stmtSede->bind_param('i', $uid);
        if ($stmtSede->execute()) {
            $rs = $stmtSede->get_result();
            if ($row = $rs->fetch_assoc()) {
                if (isset($row['sede_val'])) $sedeFiltro = (int)$row['sede_val'];
            }
        }
        $stmtSede->close();
    }
}
if ($sedeFiltro === null) {
    if (isset($_SESSION['sede_id'])) {
        $sedeFiltro = (int)$_SESSION['sede_id'];
    } elseif (isset($_SESSION['sede'])) {
        $sedeFiltro = (int)$_SESSION['sede'];
    }
}

// Si se solicitó user_id y no se pudo determinar sede, abortar para no exponer todas las mesas
if ($userIdFromQuery && $sedeFiltro === null) {
    error('No se pudo determinar la sede del usuario');
}
// Si no hay usuario en sesión ni en query, no listar para evitar exponer todas las mesas
if ($uid === null && $sedeFiltro === null) {
    error('Debe indicar un usuario o sede para listar mesas');
}

// Obtener mesas y, en su caso, la venta activa asociada
$sql = "SELECT m.id, m.nombre, m.estado, m.capacidad, m.mesa_principal_id,
                m.area_id, m.ticket_enviado, COALESCE(ca.nombre, m.area) AS area_nombre,
                m.estado_reserva, m.nombre_reserva, m.fecha_reserva,
                m.tiempo_ocupacion_inicio, m.usuario_id, m.alineacion_id,
                u.nombre AS mesero_nombre, u.usuario AS mesero_usuario,
                al.nombre AS alineacion_nombre,
                mp.nombre AS mesa_principal_nombre,
                v.id AS venta_id
          FROM mesas m
          LEFT JOIN catalogo_areas ca ON m.area_id = ca.id
          LEFT JOIN usuarios u ON m.usuario_id = u.id
          LEFT JOIN alineacion al ON m.alineacion_id = al.id
          LEFT JOIN mesas mp ON m.mesa_principal_id = mp.id
          LEFT JOIN ventas v ON v.mesa_id = m.id AND v.estatus = 'activa'";
if ($sedeFiltro !== null) {
    $sql .= " WHERE m.sede = ?";
}
$sql .= " ORDER BY m.id";

if ($sedeFiltro !== null) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error('Error al preparar consulta de mesas: ' . $conn->error);
    }
    $stmt->bind_param('i', $sedeFiltro);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if (!$result) {
    error('Error al obtener mesas: ' . $conn->error);
}


$mesas = [];
while ($row = $result->fetch_assoc()) {
    $mesas[] = [
        'id'                => (int)$row['id'],
        'nombre'            => $row['nombre'],
        'estado'            => $row['estado'],
        'capacidad'         => (int)$row['capacidad'],
        'mesa_principal_id' => $row['mesa_principal_id'] ? (int)$row['mesa_principal_id'] : null,
        'area_id'           => $row['area_id'] !== null ? (int)$row['area_id'] : null,
        'ticket_enviado'    => (bool)$row['ticket_enviado'],
        'area'              => $row['area_nombre'],
        'estado_reserva'    => $row['estado_reserva'],
        'nombre_reserva'    => $row['nombre_reserva'],
        'fecha_reserva'     => $row['fecha_reserva'],
        'tiempo_ocupacion_inicio' => $row['tiempo_ocupacion_inicio'],
        'usuario_id'        => $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null,
        'alineacion_id'     => $row['alineacion_id'] !== null ? (int)$row['alineacion_id'] : null,
        'venta_activa'      => $row['venta_id'] !== null,
        'venta_id'          => $row['venta_id'] !== null ? (int)$row['venta_id'] : null,
        'mesero_nombre'     => $row['mesero_nombre'] ?? null,
        'mesero_usuario'    => $row['mesero_usuario'] ?? null,
        'alineacion_nombre' => $row['alineacion_nombre'] ?? null,
        'mesa_principal_nombre' => $row['mesa_principal_nombre'] ?? null
    ];
}

success($mesas);
?>
