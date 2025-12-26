<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/facturama.php';

// Logger local si está disponible
if (!function_exists('facturas_log')) {
  function facturas_log(string $label, $data = null): void {
    try {
      $logDir = realpath(__DIR__ . '/../../logs');
      if ($logDir === false) { $logDir = __DIR__ . '/../../logs'; }
      if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
      $file = rtrim($logDir, '/\\') . '/facturama-' . date('Y-m') . '.log';
      $entry = ['ts'=>date('Y-m-d H:i:s'),'label'=>$label];
      if (!empty($_SERVER['REMOTE_ADDR'])) { $entry['ip'] = (string)$_SERVER['REMOTE_ADDR']; }
      if ($data !== null) $entry['data'] = $data;
      @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    } catch (Throwable $e) {}
  }
}

function unwrap_if_base64_or_json($input) {
  if (!is_string($input) || $input === '') return $input;
  $trim = ltrim($input);
  if ($trim !== '' && $trim[0] === '{') {
    $j = json_decode($trim, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($j['ContentEncoding'], $j['Content']) && strtolower((string)$j['ContentEncoding']) === 'base64') {
      $decoded = base64_decode($j['Content'], true);
      if ($decoded !== false) return $decoded;
    }
  }
  $maybe = base64_decode($trim, true);
  if ($maybe !== false) {
    $m = ltrim($maybe);
    if ($m !== '' && ($m[0] === '<' || strncmp($m, '%PDF', 4) === 0)) return $maybe;
  }
  return $input;
}

function normalize_xml_payload($input) {
  if (!is_string($input) || $input === '') return $input;
  $trimmed = ltrim($input);
  if ($trimmed === '') return $input;
  if (strncmp($trimmed, "\xEF\xBB\xBF", 3) === 0) {
    $trimmed = substr($trimmed, 3);
  }
  if ($trimmed !== '' && $trimmed[0] === '<') return $trimmed;
  return $input;
}

function json_error_exit(string $msg, int $code = 400) {
  http_response_code($code);
  echo json_encode(['success'=>false,'mensaje'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $db = get_db();
  $db->set_charset('utf8mb4');

  $tipo = strtolower(trim((string)($_GET['tipo'] ?? '')));
  $facturaId = isset($_GET['factura_id']) ? (int)$_GET['factura_id'] : 0;
  if (!in_array($tipo, ['xml','pdf'], true)) json_error_exit('tipo debe ser xml o pdf', 422);
  if ($facturaId <= 0) json_error_exit('factura_id requerido', 422);

  // Columnas tolerantes
  $has_xmlp = (bool)$db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='facturas' AND COLUMN_NAME='xml_path'")->num_rows;
  $has_pdfp = (bool)$db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='facturas' AND COLUMN_NAME='pdf_path'")->num_rows;
  $has_fid  = (bool)$db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='facturas' AND COLUMN_NAME='facturama_id'")->num_rows;
  $has_uuid = (bool)$db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='facturas' AND COLUMN_NAME='uuid'")->num_rows;

  $sel = 'SELECT id';
  if ($has_xmlp) $sel .= ', xml_path';
  if ($has_pdfp) $sel .= ', pdf_path';
  if ($has_fid)  $sel .= ', facturama_id';
  if ($has_uuid) $sel .= ', uuid';
  $sel .= ' FROM facturas WHERE id=? LIMIT 1';
  $st = $db->prepare($sel); $st->bind_param('i',$facturaId); $st->execute();
  $row = $st->get_result()->fetch_assoc(); $st->close();
  if (!$row) json_error_exit('Factura no encontrada', 404);

  // Intentar servir archivo local si existe
  $rel = null;
  if ($tipo === 'xml' && $has_xmlp) $rel = (string)($row['xml_path'] ?? '');
  if ($tipo === 'pdf' && $has_pdfp) $rel = (string)($row['pdf_path'] ?? '');
  $baseDir = realpath(__DIR__ . '/../../'); if ($baseDir === false) $baseDir = __DIR__ . '/../../';
  $abs = $rel ? (rtrim($baseDir, '/\\') . '/' . ltrim($rel, '/\\')) : '';
  if ($rel && is_file($abs)) {
    $fn = basename($abs);
    header('Content-Type: ' . ($tipo === 'xml' ? 'application/xml' : 'application/pdf'));
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    readfile($abs);
    exit;
  }

  // Si no hay archivo local, intentar descargar de Facturama y cachear
  $fid = $has_fid ? (string)($row['facturama_id'] ?? '') : '';
  if ($fid === '' || !function_exists('facturama_download_issued')) json_error_exit('Archivo no disponible', 404);

  [$bin, $ct] = facturama_download_issued($tipo, $fid);
  $clean = unwrap_if_base64_or_json($bin);
  if ($tipo === 'xml') {
    $clean = normalize_xml_payload($clean);
  }
  $year = date('Y'); $month = date('m');
  $dir = rtrim($baseDir, '/\\') . '/archivos/facturas/' . $year . '/' . $month;
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $uuid = $has_uuid ? (string)($row['uuid'] ?? '') : '';
  $name = ($uuid !== '' ? $uuid : ('cfdi_' . $facturaId)) . '.' . $tipo;
  $path = $dir . '/' . $name;
  @file_put_contents($path, $clean);
  $relNew = 'archivos/facturas/' . $year . '/' . $month . '/' . $name;

  // Actualizar DB si hay columnas
  if ($tipo === 'xml' && $has_xmlp) {
    $st = $db->prepare('UPDATE facturas SET xml_path=? WHERE id=?'); $st->bind_param('si',$relNew,$facturaId); $st->execute(); $st->close();
  } elseif ($tipo === 'pdf' && $has_pdfp) {
    $st = $db->prepare('UPDATE facturas SET pdf_path=? WHERE id=?'); $st->bind_param('si',$relNew,$facturaId); $st->execute(); $st->close();
  }

  header('Content-Type: ' . ($tipo === 'xml' ? 'application/xml' : 'application/pdf'));
  header('Content-Disposition: attachment; filename="' . $name . '"');
  echo $clean;
  exit;

} catch (Throwable $e) {
  facturas_log('REST_DESCARGAR_ERROR', ['error'=>$e->getMessage()]);
  http_response_code(500);
  echo json_encode(['success'=>false,'mensaje'=>'Error: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
