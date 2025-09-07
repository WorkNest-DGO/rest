<?php
declare(strict_types=1);
require_once __DIR__ . '/_util.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

function require_auth(): void {
  $expected = env('MOVER_AUTH_TOKEN', 'dev');
  $got = get_header('X-Auth') ?? '';
  if (!hash_equals((string)$expected, (string)$got)) {
    json_error('No autorizado', 401);
  }
}

function ensure_csrf(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
  }
  return (string)$_SESSION['csrf_token'];
}

function require_csrf_header(): void {
  $sent = get_header('X-CSRF') ?? '';
  $sess = $_SESSION['csrf_token'] ?? '';
  if (!$sess || !hash_equals((string)$sess, (string)$sent)) {
    json_error('CSRF inválido', 403);
  }
}

?>

