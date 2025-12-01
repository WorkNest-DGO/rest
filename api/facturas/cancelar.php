<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/facturama.php';

// Local logger (same style as crear.php -> rest/logs/facturama-YYYY-MM.log)
if (!function_exists('facturas_log')) {
  function facturas_log(string $label, $data = null): void {
    try {
      $logDir = realpath(__DIR__ . '/../../logs');
      if ($logDir === false) { $logDir = __DIR__ . '/../../logs'; }
      if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
      $file = rtrim($logDir, '/\\') . '/facturama-' . date('Y-m') . '.log';
      $entry = ['ts'=>date('Y-m-d H:i:s'),'label'=>$label];
      if (!empty($_SERVER['REMOTE_ADDR'])) { $entry['ip'] = (string)$_SERVER['REMOTE_ADDR']; }
      if ($data instanceof Throwable) {
        $entry['error'] = ['type'=>get_class($data), 'code'=>$data->getCode(), 'message'=>$data->getMessage()];
      } elseif ($data !== null) {
        $entry['data'] = $data;
      }
      $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
      if ($json === false) { $json = $entry['ts'] . ' ' . $label; }
      @file_put_contents($file, $json . PHP_EOL, FILE_APPEND);
    } catch (Throwable $ignored) {}
  }
}

function json_response($ok, $payloadOrMsg) {
  echo json_encode($ok ? ['success'=>true,'resultado'=>$payloadOrMsg]
                       : ['success'=>false,'mensaje'=>(string)$payloadOrMsg],
                   JSON_UNESCAPED_UNICODE);
  exit;
}
function column_exists(mysqli $db, string $t, string $c): bool {
  $q = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $q->bind_param('ss',$t,$c); $q->execute(); $q->store_result();
  $ok = $q->num_rows>0; $q->close(); return $ok;
}
function table_exists(mysqli $db, string $t): bool {
  $q = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $q->bind_param('s',$t); $q->execute(); $q->store_result();
  $ok = $q->num_rows>0; $q->close(); return $ok;
}

// Facturama cancel wrapper with path fallbacks (v3 / legacy)
if (!function_exists('facturama_cancel_cfdi')) {
  function facturama_cancel_cfdi(string $id, string $type, string $motive, ?string $uuidReplacement = null): array {
    if (!function_exists('facturama_request')) {
      throw new RuntimeException('Integracion Facturama no disponible');
    }
    $type = $type !== '' ? $type : 'issued';
    $motive = $motive !== '' ? $motive : '02';
    $qp = http_build_query([
      'type' => $type,
      'motive' => $motive,
      'uuidReplacement' => $uuidReplacement ?: '',
    ]);
    $paths = [
      "/cfdi/" . rawurlencode($id) . "?" . $qp,
      "/api/Cfdi/" . rawurlencode($id) . "?" . $qp,
      "/api/3/cfdis/" . rawurlencode($id) . "?" . $qp,
    ];
    $lastErr = null;
    foreach ($paths as $p) {
      try {
        return facturama_request('DELETE', $p);
      } catch (Throwable $e) {
        $lastErr = $e; // try next
      }
    }
    if ($lastErr) throw $lastErr;
    throw new RuntimeException('Cancelacion Facturama fallida');
  }
}

// Map text reason to SAT code if needed
function map_motivo_to_code(string $motivo): string {
  $m = trim(strtolower($motivo));
  $map = [
    '01' => '01', 'comprobante emitido con errores con relacion' => '01',
    '02' => '02', 'comprobante emitido con errores sin relacion' => '02',
    '03' => '03', 'no se realizo la operacion' => '03',
    '04' => '04', 'operacion nominativa relacionada en una factura global' => '04',
  ];
  if (isset($map[$motivo])) return $map[$motivo];
  foreach ($map as $k=>$v) { if (!ctype_digit($k) && $m === $k) return $v; }
  return preg_match('/^(01|02|03|04)$/', $motivo) ? $motivo : '02';
}

try {
  $db = get_db(); $db->set_charset('utf8mb4');

  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) json_response(false, 'Body JSON invalido');

  $fid = (int)($body['factura_id'] ?? 0);
  $motivoTxt = trim((string)($body['motivo'] ?? ''));
  $usuarioId = isset($body['usuario_id']) ? (int)$body['usuario_id'] : null;
  $typeParam = trim((string)($body['type'] ?? 'issued'));
  $motiveParam = trim((string)($body['motive'] ?? ($motivoTxt !== '' ? $motivoTxt : '')));
  $uuidRepl = trim((string)($body['uuidReplacement'] ?? ''));
  $force = false;
  if (isset($body['force'])) { $force = (bool)$body['force']; }
  if (isset($body['forzar'])) { $force = $force || (bool)$body['forzar']; }

  if ($fid <= 0) json_response(false, 'factura_id requerido');

  $f_status = column_exists($db,'facturas','status') ? 'status'
            : (column_exists($db,'facturas','estado') ? 'estado' : null);
  if (!$f_status) json_response(false,'La tabla facturas no tiene columna status/estado');

  // Get identifiers for Facturama
  $has_fid  = column_exists($db,'facturas','facturama_id');
  $has_uuid = column_exists($db,'facturas','uuid');
  $st = $db->prepare(
    "SELECT $f_status AS est" .
    ($has_fid  ? ", facturama_id" : "") .
    ($has_uuid ? ", uuid" : "") .
    " FROM facturas WHERE id = ?"
  );
  $st->bind_param('i',$fid); $st->execute();
  $res = $st->get_result(); $row = $res ? $res->fetch_assoc() : null; $st->close();
  if (!$row) json_response(false, 'Factura no encontrada');
  $statusActual = (string)($row['est'] ?? '');
  if (strcasecmp($statusActual,'cancelada') === 0) {
    json_response(true, ['mensaje'=>'Factura ya estaba cancelada']);
  }

  $facturamaId = $has_fid ? (string)($row['facturama_id'] ?? '') : '';
  $uuid = $has_uuid ? (string)($row['uuid'] ?? '') : '';
  if ($facturamaId === '' && $uuid === '') {
    json_response(false, 'Factura sin facturama_id/uuid para cancelar en PAC');
  }

  // Prepare motive code
  $motiveCode = map_motivo_to_code($motiveParam);
  $typeParam = $typeParam !== '' ? $typeParam : 'issued';

  // Remote cancel
  $cancelResponse = null; $cancelOk = false; $lastErr = null;
  try {
    if (function_exists('facturas_log')) { facturas_log('REST_FACTURAMA_CANCEL_REQ', ['factura_id'=>$fid,'fid'=>$facturamaId,'uuid'=>$uuid,'type'=>$typeParam,'motive'=>$motiveCode,'uuidReplacement'=>$uuidRepl]); }
    if ($facturamaId !== '') {
      $cancelResponse = facturama_cancel_cfdi($facturamaId, $typeParam, $motiveCode, $uuidRepl !== '' ? $uuidRepl : null);
      $cancelOk = true;
    } else {
      // Fallback: try using UUID as identifier
      $cancelResponse = facturama_cancel_cfdi($uuid, $typeParam, $motiveCode, $uuidRepl !== '' ? $uuidRepl : null);
      $cancelOk = true;
    }
  } catch (Throwable $e) {
    $lastErr = $e;
    if (function_exists('facturas_log')) { facturas_log('REST_FACTURAMA_CANCEL_ERR', ['factura_id'=>$fid,'fid'=>$facturamaId,'uuid'=>$uuid,'error'=>$e->getMessage()]); }
  }

  if (!$cancelOk && !$force) {
    json_response(false, 'No se pudo cancelar en Facturama: ' . ($lastErr ? $lastErr->getMessage() : 'error desconocido'));
  }

  if ($cancelOk) {
    if (function_exists('facturas_log')) { facturas_log('REST_FACTURAMA_CANCEL_OK', ['factura_id'=>$fid,'response'=>$cancelResponse]); }
  } else if ($force) {
    if (function_exists('facturas_log')) { facturas_log('REST_FACTURAMA_CANCEL_FORCE', ['factura_id'=>$fid, 'warning'=>'PAC fallo; se fuerza cancelacion local', 'error'=>$lastErr ? $lastErr->getMessage() : null]); }
  }

  // Update DB and free tickets for re-invoicing
  $db->begin_transaction();
  try {
    $sets = ["$f_status = 'cancelada'"];
    $types=''; $bind=[];
    $motivoGuardar = $motiveCode;
    if ($motivoTxt !== '') { $motivoGuardar .= ' - ' . $motivoTxt; }
    if (column_exists($db,'facturas','motivo_cancelacion')) { $sets[]="motivo_cancelacion = ?"; $types.='s'; $bind[]=$motivoGuardar; }
    if (column_exists($db,'facturas','cancelada_en')) { $sets[]="cancelada_en = NOW()"; }
    if (column_exists($db,'facturas','cancelada_por') && $usuarioId) { $sets[]="cancelada_por = ?"; $types.='i'; $bind[]=$usuarioId; }

    $sql = "UPDATE facturas SET ".implode(', ',$sets)." WHERE id = ?"; $types.='i'; $bind[]=$fid;
    $st = $db->prepare($sql); if ($types) { $st->bind_param($types, ...$bind); } $st->execute(); $st->close();

    // Remove bridge rows so tickets become available again
    if (table_exists($db,'factura_tickets')) {
      $st = $db->prepare("DELETE FROM factura_tickets WHERE factura_id = ?");
      $st->bind_param('i',$fid); $st->execute(); $st->close();
    }
    // Optionally remove local details for the canceled invoice
    if (table_exists($db,'factura_detalles')) {
      $st = $db->prepare("DELETE FROM factura_detalles WHERE factura_id = ?");
      $st->bind_param('i',$fid); $st->execute(); $st->close();
    }

    $db->commit();
  } catch (Throwable $e2) {
    try { $db->rollback(); } catch (Throwable $e3) {}
    throw $e2;
  }

  $out = ['mensaje'=>'Factura cancelada','factura_id'=>$fid];
  if ($cancelOk) { $out['pac_response'] = $cancelResponse; }
  else if ($force) { $out['warning'] = 'PAC fallo; se forzo cancelacion local'; }
  json_response(true, $out);

} catch (Throwable $e) {
  json_response(false, 'Error: '.$e->getMessage());
}
