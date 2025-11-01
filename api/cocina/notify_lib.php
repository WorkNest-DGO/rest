<?php
// Utilidad para notificar cambios a pantallas de cocina sin depender de HTTP interno

function cocina_resolve_runtime_dir_for_write(): string {
    $primary = __DIR__ . '/runtime';
    // Intentar primario
    if (!is_dir($primary)) {
        @mkdir($primary, 0775, true);
    }
    if (@is_dir($primary) && @is_writable($primary)) {
        return $primary;
    }
    // Fallback a /tmp
    $fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_runtime';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0775, true);
    }
    return $fallback;
}

function cocina_resolve_runtime_dir_for_read(): string {
    $primary  = __DIR__ . '/runtime';
    $fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_runtime';
    $verPrim  = cocina_leer_version_dir($primary);
    $verFall  = cucina_leer_version_dir($fallback);
    if ($verFall > $verPrim) return $fallback;
    return $primary;
}

function cocina_leer_version_dir(string $dir): int {
    $verFile = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_version.txt';
    if (!@file_exists($verFile) || !@is_readable($verFile)) return 0;
    $fp = @fopen($verFile, 'r');
    if (!$fp) return 0;
    @flock($fp, LOCK_SH);
    $txt = stream_get_contents($fp);
    @flock($fp, LOCK_UN);
    @fclose($fp);
    return intval(trim($txt ?? '0'));
}

function cocina_notify(array $ids): bool {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (empty($ids)) return false;

    $primary  = __DIR__ . '/runtime';
    $fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_runtime';
    if (!is_dir($primary))  { @mkdir($primary, 0775, true); }
    if (!is_dir($fallback)) { @mkdir($fallback, 0775, true); }

    $verPrim = cocina_leer_version_dir($primary);
    $verFall = cocina_leer_version_dir($fallback);
    $next = max($verPrim, $verFall) + 1;

    $wrote = false;
    foreach ([$primary, $fallback] as $dir) {
        if (!@is_dir($dir) || !@is_writable($dir)) continue;
        $verFile   = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_version.txt';
        $eventsLog = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_events.jsonl';

        $fp = @fopen($verFile, 'c+');
        if (!$fp) continue;
        @flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string)$next);
        fflush($fp);
        @flock($fp, LOCK_UN);
        @fclose($fp);

        $evt = json_encode(['v' => $next, 'ids' => $ids, 'ts' => time()]);
        @file_put_contents($eventsLog, $evt . PHP_EOL, FILE_APPEND | LOCK_EX);
        $wrote = true;
    }

    return $wrote;
}

?>
