<?php
declare(strict_types=1);

/**
 * Endpoint: eliminar_producto_venta.php
 * Elimina un renglón (detalle) de la venta y actualiza el total.
 * Devuelve JSON SIEMPRE.
 */

/* ===== Diagnóstico seguro y logging ===== */
error_reporting(E_ALL);
ini_set('display_errors', '0');         // No imprimir errores al cliente
ini_set('log_errors', '1');             // Registrar en log
@mkdir(__DIR__ . '/../../logs', 0777, true);
ini_set('error_log', __DIR__ . '/../../logs/php_error.log');

header('Content-Type: application/json; charset=utf-8');

/* Captura errores fatales y los regresa como JSON */
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $traceId = bin2hex(random_bytes(6));
        error_log("[TRACE:$traceId] FATAL {$err['message']} in {$err['file']}:{$err['line']}");
        if (!headers_sent()) http_response_code(500);
        echo json_encode(['success' => false, 'mensaje' => 'Error interno (fatal).', 'trace_id' => $traceId]);
    }
});

/* Captura excepciones no manejadas como JSON */
set_exception_handler(function (Throwable $e) {
    $traceId = bin2hex(random_bytes(6));
    error_log("[TRACE:$traceId] EXCEPTION {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}");
    if (!headers_sent()) http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error interno (excepción).', 'trace_id' => $traceId]);
    exit;
});

/* ====== INCLUDES / RUTAS ======
   Mantén tus rutas tal cual ya las tienes.
   Si tu proyecto ya usa esta ruta para la conexión, déjala igual.
*/
require_once __DIR__ . '/../../config/db.php'; // <- MANTENER RUTA COMO LA TENGAS EN TU PROYECTO

// Verificación rápida de conexión
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Conexión a BD no disponible']);
    exit;
}

/* ====== Validaciones de request ====== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'JSON inválido']);
    exit;
}

if (!isset($input['detalle_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Falta detalle_id']);
    exit;
}

$detalle_id = (int)$input['detalle_id'];
if ($detalle_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'detalle_id inválido']);
    exit;
}

/* ====== Obtener detalle ====== */
$sqlSel = 'SELECT id, venta_id, cantidad, precio_unitario, estado_producto 
           FROM venta_detalles WHERE id = ?';
$stmt = $conn->prepare($sqlSel);
if (!$stmt) {
    throw new RuntimeException('Prepare (select) falló: ' . $conn->error);
}
$stmt->bind_param('i', $detalle_id);
$stmt->execute();
$res = $stmt->get_result();
$detalle = $res->fetch_assoc();
$stmt->close();

if (!$detalle) {
    echo json_encode(['success' => false, 'mensaje' => 'Detalle no encontrado']);
    exit;
}

/* Regla de negocio: no permitir eliminar si ya está entregado */
if (isset($detalle['estado_producto']) && $detalle['estado_producto'] === 'entregado') {
    http_response_code(409);
    echo json_encode(['success' => false, 'mensaje' => 'No se puede eliminar un producto entregado']);
    exit;
}

/* ====== Transacción: eliminar y actualizar venta ====== */
$conn->begin_transaction();

try {
    // Eliminar el detalle
    $sqlDel = 'DELETE FROM venta_detalles WHERE id = ? LIMIT 1';
    $del = $conn->prepare($sqlDel);
    if (!$del) {
        throw new RuntimeException('Prepare (delete) falló: ' . $conn->error);
    }
    $del->bind_param('i', $detalle_id);
    if (!$del->execute()) {
        throw new RuntimeException('Execute (delete) falló: ' . $del->error);
    }
    $del->close();

    // Calcular monto a restar
    $cantidad = (float)$detalle['cantidad'];
    $precio   = (float)$detalle['precio_unitario'];
    $montoRestar = $cantidad * $precio;

    // Actualizar total de la venta
    $ventaId = (int)$detalle['venta_id'];
    $sqlUpd = 'UPDATE ventas SET total = total - ? WHERE id = ?';
    $up = $conn->prepare($sqlUpd);
    if (!$up) {
        throw new RuntimeException('Prepare (update venta) falló: ' . $conn->error);
    }
    $up->bind_param('di', $montoRestar, $ventaId);
    if (!$up->execute()) {
        throw new RuntimeException('Execute (update venta) falló: ' . $up->error);
    }
    $up->close();

    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    throw $e; // lo capturará el set_exception_handler y responderá JSON con trace_id
}
