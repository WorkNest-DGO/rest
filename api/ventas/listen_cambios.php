<?php
// /rest/api/ventas/listen_cambios.php
// Long-poll para historial de ventas: responde cuando hay cambios o tras timeout.

header('Content-Type: application/json');

// Evitar bloqueo por sesión en long-poll
if (session_status() === PHP_SESSION_ACTIVE) {
  @session_write_close();
}

$since   = isset($_GET['since']) ? intval($_GET['since']) : 0;
$timeout = 25;       // segundos
$sleepUs = 300000;   // 0.3s
$dir       = __DIR__ . '/runtime';
$verFile   = $dir . '/ventas_version.txt';
$eventsLog = $dir . '/ventas_events.jsonl';

@mkdir($dir, 0775, true);

function leerVersionVentas($verFile) {
  if (!file_exists($verFile)) return 0;
  $fp = fopen($verFile, 'r');
  if (!$fp) return 0;
  flock($fp, LOCK_SH);
  $txt = stream_get_contents($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return intval(trim($txt ?? '0'));
}

$start = microtime(true);
do {
  clearstatcache(true, $verFile);
  $cur = leerVersionVentas($verFile);

  if ($cur > $since) {
    $ids = [];
    if (file_exists($eventsLog)) {
      $fh = fopen($eventsLog, 'r');
      if ($fh) {
        while (($line = fgets($fh)) !== false) {
          $evt = json_decode($line, true);
          if (!$evt) continue;
          if ($evt['v'] > $since && $evt['v'] <= $cur && !empty($evt['ids'])) {
            foreach ($evt['ids'] as $id) $ids[] = (int)$id;
          }
        }
        fclose($fh);
      }
    }
    $ids = array_values(array_unique($ids));
    echo json_encode(['changed'=>true, 'version'=>$cur, 'ids'=>$ids]);
    exit;
  }

  usleep($sleepUs);
} while ((microtime(true) - $start) < $timeout);

echo json_encode(['changed'=>false, 'version'=>$since]);
