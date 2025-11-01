<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['venta_id'])) {
    error('Datos inválidos');
}

$venta_id = (int)$input['venta_id'];

/* 1) Verificar que la venta exista y esté activa (opcional pero recomendable) */
$venta = null;
$stmt = $conn->prepare("SELECT id, mesa_id, estatus FROM ventas WHERE id = ? LIMIT 1");
if (!$stmt) error('Error interno (prepare venta): '.$conn->error);
$stmt->bind_param('i', $venta_id);
$stmt->execute();
$res = $stmt->get_result();
$venta = $res->fetch_assoc();
$stmt->close();

if (!$venta) {
    error('La venta no existe');
}
if ($venta['estatus'] === 'cancelada') {
    success(['mensaje' => 'La venta ya estaba cancelada']);
}
if ($venta['estatus'] !== 'activa') {
    error('Solo puedes cancelar ventas activas');
}

/* 2) Revisar si hay productos NO pendientes */
$sqlNoPend = "SELECT COUNT(*) AS num FROM venta_detalles 
              WHERE venta_id = ? AND estado_producto <> 'pendiente'";
$chk = $conn->prepare($sqlNoPend);
if (!$chk) error('Error interno (prepare validación): '.$conn->error);
$chk->bind_param('i', $venta_id);
$chk->execute();
$noPend = $chk->get_result()->fetch_assoc()['num'] ?? 0;
$chk->close();

if ((int)$noPend > 0) {
    error('No se puede cancelar: hay productos que no están en estado pendiente');
}

/* 3) Obtener los detalles pendientes para cancelarlos con el API */
$detalles = [];
$det = $conn->prepare("SELECT id FROM venta_detalles WHERE venta_id = ? AND estado_producto = 'pendiente'");
if (!$det) error('Error interno (prepare detalles): '.$conn->error);
$det->bind_param('i', $venta_id);
$det->execute();
$rs = $det->get_result();
while ($row = $rs->fetch_assoc()) {
    $detalles[] = (int)$row['id'];
}
$det->close();

/* 4) Llamar al endpoint eliminar_producto_venta.php por cada detalle */
if (!function_exists('llamarEliminarDetalle')) {
    function llamarEliminarDetalle(int $ventaId, int $detalleId): array {
        // Construye URL relativa al script actual: .../api/ventas/eliminar_producto_venta.php
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
        $url    = $scheme.'://'.$host.$base.'/../mesas/eliminar_producto_venta.php';

        $payload = json_encode(['venta_id' => $ventaId, 'detalle_id' => $detalleId], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'mensaje' => 'Fallo cURL: '.$err];
        }
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'mensaje' => 'HTTP '.$status.': '.$raw];
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['success' => false, 'mensaje' => 'Respuesta no válida del API: '.$raw];
        }
        return $json;
    }
}

foreach ($detalles as $detalle_id) {
    $r = llamarEliminarDetalle($venta_id, $detalle_id);
    if (empty($r['success'])) {
        // Si falla eliminar un detalle, detenemos y avisamos
        $motivo = $r['mensaje'] ?? 'Error desconocido al eliminar detalle '.$detalle_id;
        error('No se pudo eliminar el producto (detalle '.$detalle_id.'): '.$motivo);
    }
}

/* 5) Cancelar la venta y liberar la mesa en una transacción */
$conn->begin_transaction();

try {
    // Poner total=0 y estatus=cancelada
    $up = $conn->prepare("UPDATE ventas SET total = 0.00, estatus = 'cancelada' WHERE id = ?");
    if (!$up) throw new RuntimeException('Prepare update venta: '.$conn->error);
    $up->bind_param('i', $venta_id);
    if (!$up->execute()) throw new RuntimeException('Execute update venta: '.$up->error);
    $up->close();

    // Liberar mesa si aplica
    $mesa_id = $venta['mesa_id'] ? (int)$venta['mesa_id'] : null;
    if ($mesa_id) {
        $um = $conn->prepare("UPDATE mesas 
                              SET estado = 'libre',
                                  tiempo_ocupacion_inicio = NULL,
                                  usuario_id = NULL,
                                  ticket_enviado = 0
                              WHERE id = ?");
        if (!$um) throw new RuntimeException('Prepare update mesa: '.$conn->error);
        $um->bind_param('i', $mesa_id);
        if (!$um->execute()) throw new RuntimeException('Execute update mesa: '.$um->error);
        $um->close();
    }

    $conn->commit();
    // Notificar cambio a historial de ventas (long-poll) - silencioso si no hay permisos
    try {
        $dir = __DIR__ . '/runtime';
        $okDir = is_dir($dir) || @mkdir($dir, 0775, true);
        if ($okDir && @is_writable($dir)) {
            $verFile   = $dir . '/ventas_version.txt';
            $eventsLog = $dir . '/ventas_events.jsonl';
            $fp = @fopen($verFile, 'c+');
            if ($fp) {
                flock($fp, LOCK_EX);
                rewind($fp);
                $txt  = stream_get_contents($fp);
                $cur  = intval(trim($txt ?? '0'));
                $next = $cur + 1;
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, (string)$next);
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
                $evt = json_encode(['v'=>$next,'ids'=>[$venta_id],'ts'=>time()]);
                @file_put_contents($eventsLog, $evt . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        }
    } catch (Throwable $e) { /* noop */ }

    success(['success' => true, 'mensaje' => 'Venta cancelada correctamente']);
} catch (Throwable $e) {
    $conn->rollback();
    error('Error al cancelar la venta: '.$e->getMessage());
}
