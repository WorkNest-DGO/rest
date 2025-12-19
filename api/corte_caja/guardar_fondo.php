<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = $input['usuario_id'] ?? null;
$monto = isset($input['monto']) ? (float)$input['monto'] : null;
$solo_desglose = !empty($input['solo_desglose']);
$registrar_desglose = !empty($input['registrar_desglose']);
$corte_id = isset($input['corte_id']) ? (int)$input['corte_id'] : null;
$orden = $input['orden'] ?? 'apertura';
$orden = strtolower(trim((string)$orden));
if ($orden !== 'apertura' && $orden !== 'cierre') {
    $orden = 'apertura';
}
if (!$solo_desglose) {
    if (!$usuario_id || $monto === null) {
        error('Datos incompletos');
    }

$stmt = $conn->prepare('INSERT INTO fondo (usuario_id, monto) VALUES (?, ?) ON DUPLICATE KEY UPDATE monto = VALUES(monto)');
if (!$stmt) {
    error('Error al preparar inserción: ' . $conn->error);
}
$stmt->bind_param('id', $usuario_id, $monto);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al guardar fondo: ' . $stmt->error);
}
$stmt->close();
}

$desglose_insertado = false;
if ($registrar_desglose) {
    if (!$corte_id || $monto === null) {
        error('Datos incompletos para desglose');
    }
    $chk = $conn->prepare('SELECT 1 FROM desglose_corte WHERE corte_id = ? AND orden = ? LIMIT 1');
    if (!$chk) {
        error('Error al preparar verificacion de desglose: ' . $conn->error);
    }
    $chk->bind_param('is', $corte_id, $orden);
    if (!$chk->execute()) {
        $chk->close();
        error('Error al verificar desglose: ' . $chk->error);
    }
    $chk->store_result();
    $yaExiste = $chk->num_rows > 0;
    $chk->close();

    if (!$yaExiste) {
        $ins = $conn->prepare('INSERT INTO desglose_corte (corte_id, denominacion, cantidad, tipo_pago, denominacion_id, orden) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$ins) {
            error('Error al preparar desglose: ' . $conn->error);
        }
        $denom = 1.00;
        $cant = $monto;
        $tipo = 'efectivo';
        $denomId = null;
        $ins->bind_param('iddsis', $corte_id, $denom, $cant, $tipo, $denomId, $orden);
        if (!$ins->execute()) {
            $ins->close();
            error('Error al guardar desglose: ' . $ins->error);
        }
        $ins->close();
        $desglose_insertado = true;
    }
}

success(['monto' => $monto, 'desglose_insertado' => $desglose_insertado]);
?>
