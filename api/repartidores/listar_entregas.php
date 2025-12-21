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

$input = json_decode(file_get_contents('php://input'), true);

$repartidor_id = null;

if (isset($_GET['repartidor_id'])) {
    $repartidor_id = (int) $_GET['repartidor_id'];
} elseif (isset($_POST['repartidor_id'])) {
    $repartidor_id = (int) $_POST['repartidor_id'];
} elseif ($input && isset($input['repartidor_id'])) {
    $repartidor_id = (int) $input['repartidor_id'];
}

$usuario_id = null;
if (isset($_GET['user_id'])) {
    $usuario_id = (int) $_GET['user_id'];
} elseif (isset($_GET['usuario_id'])) {
    $usuario_id = (int) $_GET['usuario_id'];
} elseif (isset($_POST['user_id'])) {
    $usuario_id = (int) $_POST['user_id'];
} elseif (isset($_POST['usuario_id'])) {
    $usuario_id = (int) $_POST['usuario_id'];
} elseif ($input && isset($input['user_id'])) {
    $usuario_id = (int) $input['user_id'];
} elseif ($input && isset($input['usuario_id'])) {
    $usuario_id = (int) $input['usuario_id'];
}

if (!$usuario_id) {
    error('Debe indicar un usuario para listar entregas');
}

$colUsuarioSede = null;
if (column_exists($conn, 'usuarios', 'sede_id')) {
    $colUsuarioSede = 'sede_id';
} elseif (column_exists($conn, 'usuarios', 'sede')) {
    $colUsuarioSede = 'sede';
}
if (!$colUsuarioSede) {
    error('No existe columna de sede en usuarios');
}

$sedeFiltro = null;
$stmtSede = $conn->prepare("SELECT {$colUsuarioSede} AS sede_val FROM usuarios WHERE id = ? LIMIT 1");
if (!$stmtSede) {
    error('Error al preparar usuario: ' . $conn->error);
}
$stmtSede->bind_param('i', $usuario_id);
if ($stmtSede->execute()) {
    $rs = $stmtSede->get_result();
    if ($row = $rs->fetch_assoc()) {
        if (isset($row['sede_val'])) $sedeFiltro = (int)$row['sede_val'];
    }
}
$stmtSede->close();
if ($sedeFiltro === null) {
    error('No se pudo determinar la sede del usuario');
}

$colVentaSede = null;
if (column_exists($conn, 'ventas', 'sede_id')) {
    $colVentaSede = 'sede_id';
} elseif (column_exists($conn, 'ventas', 'sede')) {
    $colVentaSede = 'sede';
}
if (!$colVentaSede) {
    error('No existe columna de sede en ventas');
}

$sql = "SELECT v.id, v.usuario_id, v.repartidor_id, v.fecha, v.total, v.estatus, v.entregado, v.estado_entrega, v.fecha_asignacion, v.fecha_inicio, v.fecha_entrega, v.seudonimo_entrega, v.foto_entrega, v.observacion,
                COALESCE(u.nombre, r.nombre) AS repartidor
           FROM ventas v
      LEFT JOIN usuarios u ON u.id = v.usuario_id AND u.rol = 'repartidor'
      LEFT JOIN repartidores r ON r.id = v.repartidor_id
          WHERE v.estatus IN ('activa','cerrada')";
if ($repartidor_id) {
    $sql .= " AND v.repartidor_id = ?";
} else {
    $sql .= " AND v.tipo_entrega = 'domicilio'
              AND (u.id IS NOT NULL OR v.repartidor_id = 4)";
}
$sql .= " AND v.{$colVentaSede} = ?
       ORDER BY v.fecha DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}

$types = '';
$params = [];
if ($repartidor_id) {
    $types .= 'i';
    $params[] = $repartidor_id;
}
$types .= 'i';
$params[] = $sedeFiltro;
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar consulta: ' . $stmt->error);
}
$res = $stmt->get_result();
$ventas = [];
while ($row = $res->fetch_assoc()) {
    $ventas[$row['id']] = [
        'id'          => (int)$row['id'],
        'fecha'       => $row['fecha'],
        'usuario_id'  => isset($row['usuario_id']) ? (int)$row['usuario_id'] : null,
        'repartidor_id' => isset($row['repartidor_id']) ? (int)$row['repartidor_id'] : null,
        'total'       => (float)$row['total'],
        'estatus'     => $row['estatus'],
        'entregado'   => (int)$row['entregado'],
        'estado_entrega'   => $row['estado_entrega'],
        'fecha_asignacion' => $row['fecha_asignacion'],
        'fecha_inicio'     => $row['fecha_inicio'],
        'fecha_entrega'    => $row['fecha_entrega'],
        'seudonimo_entrega'=> $row['seudonimo_entrega'],
        'foto_entrega'     => $row['foto_entrega'],
        'observacion'      => $row['observacion'] ?? '',
        'repartidor'       => $row['repartidor'] ?? '',
        'productos' => []
    ];
}
$stmt->close();

if ($ventas) {
    $ids = implode(',', array_keys($ventas));
    $det = $conn->query(
        "SELECT vd.venta_id, p.nombre, vd.cantidad, vd.precio_unitario FROM venta_detalles vd JOIN productos p ON vd.producto_id = p.id WHERE vd.venta_id IN ($ids)"
    );
    if ($det) {
        while ($d = $det->fetch_assoc()) {
            $ventaId = (int)$d['venta_id'];
            if (isset($ventas[$ventaId])) {
                $ventas[$ventaId]['productos'][] = [
                    'nombre' => $d['nombre'],
                    'cantidad' => (int)$d['cantidad'],
                    'precio_unitario' => (float)$d['precio_unitario']
                ];
            }
        }
        $det->free();
    }
}

success(array_values($ventas));
?>
