<?php
declare(strict_types=1);

function json_ok($data = null): void {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;
}

function json_error(string $message, int $httpCode = 200): void {
  http_response_code($httpCode);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

function env(string $key, $default = null) {
  $val = getenv($key);
  if ($val !== false && $val !== null) return $val;
  // Try to load from .env if present
  static $loaded = false;
  static $map = [];
  if (!$loaded) {
    $envPath = __DIR__ . '/../../.env';
    if (is_file($envPath)) {
      $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
      foreach ($lines as $ln) {
        if (strpos(ltrim($ln), '#') === 0) continue;
        $pos = strpos($ln, '=');
        if ($pos === false) continue;
        $k = trim(substr($ln, 0, $pos));
        $v = trim(substr($ln, $pos+1));
        $v = trim($v, "'\"");
        $map[$k] = $v;
      }
    }
    $loaded = true;
  }
  return $map[$key] ?? $default;
}

function get_header(string $name): ?string {
  $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
  return $_SERVER[$key] ?? null;
}

function ints_from_array($val, int $max = 200): array {
  if (!is_array($val)) return [];
  $out = [];
  foreach ($val as $v) {
    $i = (int)$v;
    if ($i > 0) $out[] = $i;
    if (count($out) >= $max) break;
  }
  return array_values(array_unique($out));
}

function pdo_column_exists(PDO $pdo, string $table, string $column): bool {
  $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$table, $column]);
  return (bool)$st->fetchColumn();
}

function pdo_table_exists(PDO $pdo, string $table): bool {
  $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$table]);
  return (bool)$st->fetchColumn();
}

?>

