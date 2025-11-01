<?php
// /rest/api/cocina/notify_cambio.php
// Recibe ids de venta_detalles cambiados y actualiza una versión global en archivos,
// para despertar a las pantallas (long-poll) sin tocar la BD.

header('Content-Type: application/json');
require_once __DIR__ . '/notify_lib.php';

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

if (!cocina_notify($ids)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se pudo actualizar versión']);
  exit;
}

// Responder versión actual calculando desde archivos (sin depender de helpers)
$primary  = __DIR__ . '/runtime';
$fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cocina_runtime';
$v1 = file_exists($primary . '/cocina_version.txt') ? (int)@file_get_contents($primary . '/cocina_version.txt') : 0;
$v2 = file_exists($fallback . '/cocina_version.txt') ? (int)@file_get_contents($fallback . '/cocina_version.txt') : 0;
$ver = max($v1, $v2);
echo json_encode(['ok'=>true,'version'=>$ver]);
