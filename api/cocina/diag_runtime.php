<?php
// /rest/api/cocina/diag_runtime.php
// Diagnóstico del runtime de notificaciones de cocina.
header('Content-Type: application/json');

$primary  = __DIR__ . '/runtime';
$fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_runtime';

function read_version($dir) {
  $file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_version.txt';
  if (!file_exists($file) || !is_readable($file)) return 0;
  $fp = @fopen($file, 'r');
  if (!$fp) return 0;
  @flock($fp, LOCK_SH);
  $txt = stream_get_contents($fp);
  @flock($fp, LOCK_UN);
  @fclose($fp);
  return (int)trim($txt ?: '0');
}

$p = [
  'path' => $primary,
  'exists' => @is_dir($primary),
  'writable' => @is_writable($primary),
  'version' => read_version($primary)
];
$f = [
  'path' => $fallback,
  'exists' => @is_dir($fallback),
  'writable' => @is_writable($fallback),
  'version' => read_version($fallback)
];

$eff = ($f['version'] > $p['version']) ? $fallback : $primary;

echo json_encode([
  'ok' => true,
  'primary' => $p,
  'fallback' => $f,
  'effective_read_dir' => $eff
]);
?>

