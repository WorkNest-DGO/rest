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

// Calcular totales de boucher y cheque del corte abierto
$sqlNoEf = "SELECT
    SUM(CASE WHEN t.tipo_pago='boucher' THEN t.total ELSE 0 END) AS total_boucher,
    SUM(CASE WHEN t.tipo_pago='cheque'  THEN t.total ELSE 0 END) AS total_cheque
FROM tickets t
JOIN ventas v ON v.id = t.venta_id
WHERE v.estatus = 'cerrada'
  AND v.corte_id IS NULL
  AND t.fecha >= ?
  AND v.usuario_id = ?";

$stmtNoEf = $conn->prepare($sqlNoEf);
if (!$stmtNoEf) {
    error('Error al calcular totales de no efectivo: ' . $conn->error);
}
$stmtNoEf->bind_param('si', $fecha_inicio, $usuario_id);
$stmtNoEf->execute();
$totNoEf = $stmtNoEf->get_result()->fetch_assoc();
$stmtNoEf->close();

$totalBoucher = (float)($totNoEf['total_boucher'] ?? 0);
$totalCheque  = (float)($totNoEf['total_cheque'] ?? 0);

// Registrar boucher y cheque en desglose_corte
$insNe = $conn->prepare('INSERT INTO desglose_corte (corte_id, denominacion, cantidad, tipo_pago, denominacion_id) VALUES (?, ?, ?, ?, ?)');
if (!$insNe) {
    error('Error al preparar inserción de no efectivo: ' . $conn->error);
}
$den = 1.00;
$cant = $totalBoucher;
$tipo = 'boucher';
$denId = 12;
$insNe->bind_param('iddsi', $corte_id, $den, $cant, $tipo, $denId);
if (!$insNe->execute()) {
    $insNe->close();
    error('Error al guardar boucher: ' . $insNe->error);
}
$cant = $totalCheque;
$tipo = 'cheque';
$denId = 13;
if (!$insNe->execute()) {
    $insNe->close();
    error('Error al guardar cheque: ' . $insNe->error);
}
$insNe->close();

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

// Obtener folio final del corte
$folio_fin = 0;
$ff = $conn->prepare('SELECT IFNULL(MAX(folio), 0) AS folio FROM tickets WHERE corte_id = ? OR (fecha >= ? AND fecha <= ?)');
if ($ff) {
    $ff->bind_param('iss', $corte_id, $fecha_inicio, $fecha_fin);
    $ff->execute();
    $resFf = $ff->get_result()->fetch_assoc();
    $folio_fin = (int)($resFf['folio'] ?? 0);
    $ff->close();
}

// Cerrar corte asignando fecha_fin, total calculado y folio final
$upd = $conn->prepare('UPDATE corte_caja SET fecha_fin = ?, total = ?, observaciones = ?, folio_fin = ? WHERE id = ?');
if (!$upd) {
    error('Error al preparar cierre: ' . $conn->error);
}
$upd->bind_param('sdsii', $fecha_fin, $totalFinal, $observa, $folio_fin, $corte_id);
if (!$upd->execute()) {
    $upd->close();
    error('Error al cerrar corte: ' . $upd->error);
}
$upd->close();

$updVentas = $conn->prepare("UPDATE ventas SET corte_id = ? WHERE usuario_id = ? AND fecha >= ? AND fecha <= ? AND estatus = 'cerrada' AND (corte_id IS NULL)");
if ($updVentas) {
    $updVentas->bind_param('iiss', $corte_id, $usuario_id, $fecha_inicio, $fecha_fin);
    $updVentas->execute();
    $updVentas->close();
}

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
    'fondo_inicial'    => $fondo_inicial
]);
?>
