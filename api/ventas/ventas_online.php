<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

// Config de grupos a mostrar
$repartidorIds = [1, 2, 3];
$usuarioOnlineId = 8;

$sedeId = isset($_SESSION['sede_id']) ? (int)$_SESSION['sede_id'] : null;
$corteId = isset($_SESSION['corte_id']) ? (int)$_SESSION['corte_id'] : null;

$where = ["v.estatus <> 'cancelada'"];
$params = [];
$types = '';

if ($sedeId) {
    $where[] = 'v.sede_id = ?';
    $params[] = $sedeId;
    $types .= 'i';
}

$filtroFecha = null;
if ($corteId) {
    $where[] = 'v.corte_id = ?';
    $params[] = $corteId;
    $types .= 'i';
} else {
    $where[] = 'DATE(v.fecha) = CURDATE()';
    $filtroFecha = date('Y-m-d');
}

$whereSql = implode(' AND ', $where);
$repPlaceholders = implode(',', array_fill(0, count($repartidorIds), '?'));

$sql = "SELECT v.repartidor_id, v.usuario_id
        FROM ventas v
        WHERE ($whereSql) AND (v.repartidor_id IN ($repPlaceholders) OR v.usuario_id = ?)";

$paramsMain = array_merge($params, $repartidorIds, [$usuarioOnlineId]);
$typesMain = $types . str_repeat('i', count($repartidorIds) + 1);

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error('Error al preparar la consulta: ' . $conn->error);
}

$stmt->bind_param($typesMain, ...$paramsMain);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar la consulta: ' . $stmt->error);
}

$res = $stmt->get_result();
$conteoReps = array_fill_keys($repartidorIds, 0);
$conteoUsuario = 0;
while ($row = $res->fetch_assoc()) {
    $rid = isset($row['repartidor_id']) ? (int)$row['repartidor_id'] : null;
    $uid = isset($row['usuario_id']) ? (int)$row['usuario_id'] : null;
    if ($rid && array_key_exists($rid, $conteoReps)) {
        $conteoReps[$rid]++;
    }
    if ($uid === $usuarioOnlineId) {
        $conteoUsuario++;
    }
}
$stmt->close();

// Obtener nombres legibles
$repNombres = [];
if (!empty($repartidorIds)) {
    $placeholders = implode(',', array_fill(0, count($repartidorIds), '?'));
    $repStmt = $conn->prepare("SELECT id, nombre FROM repartidores WHERE id IN ($placeholders)");
    if ($repStmt) {
        $repTypes = str_repeat('i', count($repartidorIds));
        $repStmt->bind_param($repTypes, ...$repartidorIds);
        if ($repStmt->execute()) {
            $rRes = $repStmt->get_result();
            while ($r = $rRes->fetch_assoc()) {
                $repNombres[(int)$r['id']] = $r['nombre'];
            }
        }
        $repStmt->close();
    }
}

$usuarioNombre = null;
$usrStmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
if ($usrStmt) {
    $usrStmt->bind_param('i', $usuarioOnlineId);
    if ($usrStmt->execute()) {
        $uRes = $usrStmt->get_result();
        if ($uRow = $uRes->fetch_assoc()) {
            $usuarioNombre = $uRow['nombre'];
        }
    }
    $usrStmt->close();
}

$grupos = [];
foreach ($conteoReps as $rid => $cnt) {
    $grupos[] = [
        'tipo'   => 'repartidor',
        'id'     => $rid,
        'nombre' => $repNombres[$rid] ?? ('Repartidor ' . $rid),
        'ventas' => $cnt,
    ];
}

$grupos[] = [
    'tipo'   => 'usuario',
    'id'     => $usuarioOnlineId,
    'nombre' => $usuarioNombre ?? ('Usuario ' . $usuarioOnlineId),
    'ventas' => $conteoUsuario,
];

success([
    'corte_id' => $corteId,
    'fecha'    => $filtroFecha,
    'sede_id'  => $sedeId,
    'grupos'   => $grupos,
]);
