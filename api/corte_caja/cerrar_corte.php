<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('Sesión no iniciada');
}

$input      = json_decode(file_get_contents('php://input'), true);
$observa    = $input['observaciones'] ?? '';
$corte_id   = $_SESSION['corte_id'] ?? null;
$usuario_id = $_SESSION['usuario_id'];

if (!$corte_id) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'No hay corte abierto para cerrar'
    ]);
    exit;
}

$stmt = $conn->prepare('SELECT fecha_inicio, fondo_inicial FROM corte_caja WHERE id = ? AND usuario_id = ? AND fecha_fin IS NULL');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('ii', $corte_id, $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    $stmt->close();
    error('Corte no encontrado o ya cerrado');
}

$row = $res->fetch_assoc();
$fecha_inicio   = $row['fecha_inicio'];
$fondo_inicial  = (float)($row['fondo_inicial'] ?? 0);
$stmt->close();

// Calcular total de ventas del periodo
$fecha_fin = date('Y-m-d H:i:s');
// Total de ventas (sin propinas)
$tot = $conn->prepare("SELECT SUM(total) AS total FROM ventas WHERE usuario_id = ? AND fecha >= ? AND fecha <= ? AND estatus = 'cerrada' AND corte_id IS NULL");
if (!$tot) {
    error('Error al calcular total: ' . $conn->error);
}
$tot->bind_param('iss', $usuario_id, $fecha_inicio, $fecha_fin);
$tot->execute();
$resTot = $tot->get_result()->fetch_assoc();
$totalVentas = (float)($resTot['total'] ?? 0);
$tot->close();

// Total de propinas
$prop = $conn->prepare("SELECT SUM(t.propina) AS propina FROM ventas v JOIN tickets t ON t.venta_id = v.id WHERE v.usuario_id = ? AND v.fecha >= ? AND v.fecha <= ? AND v.estatus = 'cerrada' AND v.corte_id IS NULL");
if (!$prop) {
    error('Error al calcular propinas: ' . $conn->error);
}
$prop->bind_param('iss', $usuario_id, $fecha_inicio, $fecha_fin);
$prop->execute();
$resProp = $prop->get_result()->fetch_assoc();
$totalPropinas = (float)($resProp['propina'] ?? 0);
$prop->close();

$totalFinal = $totalVentas + $totalPropinas + $fondo_inicial;

// 1) Folio fin calculado
$stmt = $conn->prepare("\n  SELECT IFNULL(MAX(t.folio), 0) AS folio_fin_calc\n  FROM tickets t\n  INNER JOIN ventas v ON v.id = t.venta_id\n  WHERE v.usuario_id = ?\n    AND v.fecha >= ?\n    AND v.fecha <= ?\n    AND v.estatus = 'cerrada'\n");
$stmt->bind_param('iss', $usuario_id, $fecha_inicio, $fecha_fin);
$stmt->execute();
$folio_fin_calc = (int)($stmt->get_result()->fetch_assoc()['folio_fin_calc'] ?? 0);
$stmt->close();

// 2) Leer folio_inicio del corte
$folio_inicio = 0;
$stmt = $conn->prepare('SELECT IFNULL(folio_inicio,0) AS fi FROM corte_caja WHERE id = ?');
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$folio_inicio = (int)($stmt->get_result()->fetch_assoc()['fi'] ?? 0);
$stmt->close();
if ($folio_inicio <= 0) {
    $folio_inicio = 1;
}
if ($folio_fin_calc === 0) {
    $folio_fin_calc = $folio_inicio;
}
$total_folios = max($folio_fin_calc - $folio_inicio, 0);

$conn->begin_transaction();

// Cerrar corte asignando fecha_fin, total calculado y folios
$upd = $conn->prepare('UPDATE corte_caja SET fecha_fin = NOW(), total = ?, observaciones = ?, folio_fin = ?, total_folios = ? WHERE id = ?');
if (!$upd) {
    $conn->rollback();
    error('Error al preparar cierre: ' . $conn->error);
}
$upd->bind_param('dsiii', $totalFinal, $observa, $folio_fin_calc, $total_folios, $corte_id);
if (!$upd->execute()) {
    $upd->close();
    $conn->rollback();
    error('Error al cerrar corte: ' . $upd->error);
}
$upd->close();

$updVentas = $conn->prepare("UPDATE ventas SET corte_id = ? WHERE usuario_id = ? AND fecha >= ? AND fecha <= ? AND estatus = 'cerrada' AND (corte_id IS NULL)");
if (!$updVentas) {
    $conn->rollback();
    error('Error al preparar actualización de ventas: ' . $conn->error);
}
$updVentas->bind_param('iiss', $corte_id, $usuario_id, $fecha_inicio, $fecha_fin);
if (!$updVentas->execute()) {
    $updVentas->close();
    $conn->rollback();
    error('Error al asignar ventas al corte: ' . $updVentas->error);
}
$updVentas->close();

$conn->commit();

$stmt = $conn->prepare("SELECT COUNT(*) AS num FROM ventas WHERE usuario_id = ? AND fecha >= ? AND fecha <= ? AND estatus = 'cerrada'");
if ($stmt) {
    $stmt->bind_param('iss', $usuario_id, $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $numVentas = (int)($c['num'] ?? 0);
    $stmt->close();
} else {
    $numVentas = 0;
}

// Registrar acción en logs
$log = $conn->prepare('INSERT INTO logs_accion (usuario_id, modulo, accion, referencia_id) VALUES (?, ?, ?, ?)');
if ($log) {
    $mod = 'corte_caja';
    $accion = 'Cierre de corte';
    $log->bind_param('issi', $usuario_id, $mod, $accion, $corte_id);
    $log->execute();
    $log->close();
}

success([
    'ventas_realizadas' => $numVentas,
    'total'            => $totalFinal,
    'total_ventas'     => $totalVentas,
    'total_propinas'   => $totalPropinas,
    'fondo_inicial'    => $fondo_inicial,
    'folio_inicio'     => $folio_inicio,
    'folio_fin'        => $folio_fin_calc,
    'total_folios'     => $total_folios
]);
?>
