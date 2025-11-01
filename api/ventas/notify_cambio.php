<?php
// /rest/api/ventas/notify_cambio.php
// Incrementa versión y registra ids de ventas para despertar clientes (long-poll)

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'Método no permitido']);
  exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$ids = isset($payload['ids']) && is_array($payload['ids'])
  ? array_values(array_unique(array_map('intval',$payload['ids'])))
  : [];

if (!$ids) {
  echo json_encode(['ok'=>false,'msg'=>'ids vacíos']);
  exit;
}

$dir = __DIR__ . '/runtime';
@mkdir($dir, 0775, true);

$verFile   = $dir . '/ventas_version.txt';
$eventsLog = $dir . '/ventas_events.jsonl';

$fp = @fopen($verFile, 'c+');
if (!$fp) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se pudo abrir ventas_version.txt']);
  exit;
}

flock($fp, LOCK_EX);
$txt  = stream_get_contents($fp);
$cur  = intval(trim($txt ?? '0'));
$next = $cur + 1;
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, (string)$next);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

$evt = json_encode(['v'=>$next,'ids'=>$ids,'ts'=>time()], JSON_UNESCAPED_UNICODE);
@file_put_contents($eventsLog, $evt . PHP_EOL, FILE_APPEND | LOCK_EX);

echo json_encode(['ok'=>true,'version'=>$next]);
