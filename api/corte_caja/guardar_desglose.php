<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$corte_id = $input['corte_id'] ?? null;
$desglose = $input['desglose'] ?? [];
$boucher = isset($input['boucher']) ? (float)$input['boucher'] : 0;
$cheque  = isset($input['cheque']) ? (float)$input['cheque'] : 0;
if (!$corte_id || !is_array($desglose)) {
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

$total_calc = $boucher + $cheque;
foreach ($desglose as $denom => $cant) {
    $total_calc += ((float)$denom) * ((int)$cant);
}
if (abs($total_calc - $corte_total) > 5) {
    error('El total del desglose no coincide con el corte');
}

$conn->begin_transaction();
$ins = $conn->prepare('INSERT INTO desglose_corte (corte_id, denominacion, cantidad, tipo_pago) VALUES (?, ?, ?, ?)');
if (!$ins) {
    $conn->rollback();
    error('Error al preparar inserción: ' . $conn->error);
}
foreach ($desglose as $denom => $cant) {
    $tipo = 'efectivo';
    $d = (float)$denom;
    $c = (int)$cant;
    if ($c <= 0) continue;
    $ins->bind_param('idis', $corte_id, $d, $c, $tipo);
    if (!$ins->execute()) {
        $conn->rollback();
        error('Error al guardar desglose: ' . $ins->error);
    }
}
if ($boucher > 0) {
    $tipo = 'boucher';
    $d = $boucher;
    $c = 1;
    $ins->bind_param('idis', $corte_id, $d, $c, $tipo);
    if (!$ins->execute()) {
        $conn->rollback();
        error('Error al guardar boucher');
    }
}
if ($cheque > 0) {
    $tipo = 'cheque';
    $d = $cheque;
    $c = 1;
    $ins->bind_param('idis', $corte_id, $d, $c, $tipo);
    if (!$ins->execute()) {
        $conn->rollback();
        error('Error al guardar cheque');
    }
}
$ins->close();
$conn->commit();

success(['total' => $total_calc]);
?>
