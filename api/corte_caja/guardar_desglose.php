<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$corte_id = $input['corte_id'] ?? ($_SESSION['corte_id'] ?? null);
$detalle   = $input['detalle'] ?? null;

if (!$corte_id || !is_array($detalle)) {
    error('Datos incompletos');
}

$stmt = $conn->prepare('SELECT total FROM corte_caja WHERE id = ?');
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $corte_id);
$stmt->execute();
$res = $stmt->get_result();
$info = $res->fetch_assoc();
$stmt->close();
if (!$info) {
    error('Corte no encontrado');
}
$corte_total = (float)$info['total'];

$catalogo = [];
$resCat = $conn->query('SELECT id, valor FROM catalogo_denominaciones');
while ($row = $resCat->fetch_assoc()) {
    $catalogo[(int)$row['id']] = (float)$row['valor'];
}

$totales = [
    'efectivo' => 0,
    'boucher' => 0,
    'cheque' => 0
];

$filasValidas = [];
foreach ($detalle as $fila) {
    $tipo = $fila['tipo_pago'] ?? 'efectivo';
    if ($tipo === 'efectivo') {
        $id = (int)($fila['denominacion_id'] ?? 0);
        $cantidad = (int)($fila['cantidad'] ?? 0);
        if ($cantidad <= 0) continue;
        if (!$id || !isset($catalogo[$id])) {
            error('Selecciona una denominación válida en todas las filas de efectivo');
        }
        $valor = $catalogo[$id];
        $totales['efectivo'] += $valor * $cantidad;
        $filasValidas[] = [$valor, $cantidad, $tipo, $id];
    } else {
        $monto = (float)($fila['cantidad'] ?? 0);
        if ($monto <= 0) continue;
        $totales[$tipo] += $monto;
        $filasValidas[] = [$monto, 1, $tipo, null];
    }
}

if (!$filasValidas) {
    error('Sin filas válidas');
}

$conn->begin_transaction();
$ins = $conn->prepare('INSERT INTO desglose_corte (corte_id, denominacion, cantidad, tipo_pago, denominacion_id) VALUES (?, ?, ?, ?, ?)');
if (!$ins) {
    $conn->rollback();
    error('Error al preparar inserción: ' . $conn->error);
}

foreach ($filasValidas as $f) {
    [$denom, $cant, $tipo, $denomId] = $f;
    $ins->bind_param('idisi', $corte_id, $denom, $cant, $tipo, $denomId);
    if (!$ins->execute()) {
        $conn->rollback();
        error('Error al guardar desglose: ' . $ins->error);
    }
}
$ins->close();
$conn->commit();

$totalGeneral = $totales['efectivo'] + $totales['boucher'] + $totales['cheque'];
success(['totales' => $totales, 'total_general' => $totalGeneral]);
?>
