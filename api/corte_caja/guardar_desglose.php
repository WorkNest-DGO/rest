<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$corte_id = $input['corte_id'] ?? ($_SESSION['corte_id'] ?? null);
$detalle   = $input['detalle'] ?? null;

if ($detalle === null && isset($input['desglose'])) {
    $detalle = [];
    foreach ($input['desglose'] as $den => $cant) {
        $detalle[] = ['denominacion' => (float)$den, 'cantidad' => (int)$cant, 'tipo_pago' => 'efectivo'];
    }
    if (isset($input['boucher']) && $input['boucher'] > 0) {
        $detalle[] = ['denominacion' => (float)$input['boucher'], 'cantidad' => 1, 'tipo_pago' => 'boucher'];
    }
    if (isset($input['cheque']) && $input['cheque'] > 0) {
        $detalle[] = ['denominacion' => (float)$input['cheque'], 'cantidad' => 1, 'tipo_pago' => 'cheque'];
    }
}

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

$total_calc = 0;
foreach ($detalle as $fila) {
    $d = (float)($fila['denominacion'] ?? 0);
    $c = (int)($fila['cantidad'] ?? 0);
    if ($fila['tipo_pago'] === 'efectivo') {
        $total_calc += $d * $c;
    } else {
        $total_calc += $d;
    }
}

$conn->begin_transaction();
$ins = $conn->prepare('INSERT INTO desglose_corte (corte_id, denominacion, cantidad, tipo_pago) VALUES (?, ?, ?, ?)');
if (!$ins) {
    $conn->rollback();
    error('Error al preparar inserción: ' . $conn->error);
}
foreach ($detalle as $fila) {
    $d = (float)($fila['denominacion'] ?? 0);
    $c = (int)($fila['cantidad'] ?? 0);
    $tipo = $fila['tipo_pago'] ?? 'efectivo';
    if ($tipo === 'efectivo' && $c <= 0) continue;
    $ins->bind_param('idis', $corte_id, $d, $c, $tipo);
    if (!$ins->execute()) {
        $conn->rollback();
        error('Error al guardar desglose: ' . $ins->error);
    }
}
$ins->close();
$conn->commit();

success(['total' => $total_calc]);
?>
