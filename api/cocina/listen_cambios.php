<?php
// /rest/api/cocina/listen_cambios.php
// Long-poll: responde solo cuando hay cambios (versión sube) o tras timeout.

header('Content-Type: application/json');

$since    = isset($_GET['since']) ? intval($_GET['since']) : 0;
$timeout  = 25;        // segundos
$sleepUs  = 300000;    // 0.3s por iteración

// Resolver el directorio de runtime sin depender de notify_lib
$primary  = __DIR__ . '/runtime';
$fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_runtime';
$verPrim  = 0;
$verFall  = 0;
if (file_exists($primary . '/cocina_version.txt')) {
  $verPrim = (int)@file_get_contents($primary . '/cocina_version.txt');
}
if (file_exists($fallback . '/cocina_version.txt')) {
  $verFall = (int)@file_get_contents($fallback . '/cocina_version.txt');
}
$dir = ($verFall > $verPrim) ? $fallback : $primary;

function leerVersion($verFile) {
  if (!file_exists($verFile) || !@is_readable($verFile)) return 0;
  $fp = @fopen($verFile, 'r');
  if (!$fp) return 0;
  flock($fp, LOCK_SH);
  $txt = stream_get_contents($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return intval(trim($txt ?? '0'));
}

$start = microtime(true);

do {
  // Recalcular directorio activo en cada iteración
  $verPrim = file_exists($primary . '/cocina_version.txt') ? (int)@file_get_contents($primary . '/cocina_version.txt') : 0;
  $verFall = file_exists($fallback . '/cocina_version.txt') ? (int)@file_get_contents($fallback . '/cocina_version.txt') : 0;
  $dir = ($verFall > $verPrim) ? $fallback : $primary;
  $verFile  = $dir . '/cocina_version.txt';
  $eventsLog= $dir . '/cocina_events.jsonl';
  clearstatcache(true, $verFile);
  $cur = leerVersion($verFile);

  if ($cur > $since) {
    // Recolecta eventos > since y <= cur
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
    $ids = array_values(array_unique($ids)); // dedup
    echo json_encode(['changed'=>true, 'version'=>$cur, 'ids'=>$ids]);
    exit;
  }

  usleep($sleepUs);
} while ((microtime(true) - $start) < $timeout);

// No hubo cambios en la ventana: devolver versión actual del servidor
$verPrim = file_exists($primary . '/cocina_version.txt') ? (int)@file_get_contents($primary . '/cocina_version.txt') : 0;
$verFall = file_exists($fallback . '/cocina_version.txt') ? (int)@file_get_contents($fallback . '/cocina_version.txt') : 0;
$dir = ($verFall > $verPrim) ? $fallback : $primary;
$cur = 0;
$verFile = $dir . '/cocina_version.txt';
if (file_exists($verFile) && is_readable($verFile)) {
  $fp = @fopen($verFile, 'r');
  if ($fp) { flock($fp, LOCK_SH); $txt = stream_get_contents($fp); flock($fp, LOCK_UN); fclose($fp); $cur = intval(trim($txt ?? '0')); }
}
echo json_encode(['changed'=>false, 'version'=>$cur]);

