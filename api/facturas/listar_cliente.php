<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

function json_response($ok, $payloadOrMsg) {
  echo json_encode($ok ? ['success'=>true,'resultado'=>$payloadOrMsg]
                       : ['success'=>false,'mensaje'=>(string)$payloadOrMsg],
                   JSON_UNESCAPED_UNICODE);
  exit;
}
function table_exists(mysqli $db, string $t): bool {
  $q = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $q->bind_param('s',$t); $q->execute(); $q->store_result();
  $ok = $q->num_rows>0; $q->close(); return $ok;
}
function column_exists(mysqli $db, string $t, string $c): bool {
  $q = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $q->bind_param('ss',$t,$c); $q->execute(); $q->store_result();
  $ok = $q->num_rows>0; $q->close(); return $ok;
}

try {
  $db = get_db();
  $db->set_charset('utf8mb4');

  if (!table_exists($db, 'clientes_facturacion')) {
    json_response(true, ['clientes' => []]);
  }

  $buscar = isset($_GET['buscar']) ? trim((string)$_GET['buscar']) : '';
  $limit  = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 200;
  $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

  // Columnas tolerantes
  $nameCol = column_exists($db,'clientes_facturacion','razon_social') ? 'razon_social'
           : (column_exists($db,'clientes_facturacion','nombre') ? 'nombre' : null);
  $hasRFC  = column_exists($db,'clientes_facturacion','rfc');
  $hasCorreo = column_exists($db,'clientes_facturacion','correo');
  $hasTelefono = column_exists($db,'clientes_facturacion','telefono');
  $hasUso = column_exists($db,'clientes_facturacion','uso_cfdi');
  $hasRegimen = column_exists($db,'clientes_facturacion','regimen');
  $hasCP = column_exists($db,'clientes_facturacion','cp');

  // SELECT dinámico
  $parts = [ 'cf.id' ];
  if ($nameCol)   $parts[] = "cf.$nameCol AS nombre"; else $parts[] = "NULL AS nombre";
  if ($hasRFC)    $parts[] = 'cf.rfc';
  if ($hasCorreo) $parts[] = 'cf.correo';
  if ($hasTelefono) $parts[] = 'cf.telefono';
  if ($hasUso)    $parts[] = 'cf.uso_cfdi';
  if ($hasRegimen)$parts[] = 'cf.regimen';
  if ($hasCP)     $parts[] = 'cf.cp';
  $select = implode(', ', $parts);

  $sql = "SELECT $select FROM clientes_facturacion cf";
  $types = '';
  $params = [];
  $wheres = [];
  if ($buscar !== '') {
    $like = "%$buscar%";
    if ($nameCol) { $wheres[] = "cf.$nameCol LIKE ?"; $types.='s'; $params[]=$like; }
    if ($hasRFC)  { $wheres[] = "cf.rfc LIKE ?";      $types.='s'; $params[]=$like; }
    if (ctype_digit($buscar)) { $wheres[] = 'cf.id = ?'; $types.='i'; $params[] = (int)$buscar; }
  }
  if ($wheres) $sql .= ' WHERE ' . implode(' OR ', $wheres);
  $sql .= ' ORDER BY nombre ASC';
  $sql .= ' LIMIT ? OFFSET ?';
  $types .= 'ii'; $params[] = $limit; $params[] = $offset;

  $st = $db->prepare($sql);
  if ($types) $st->bind_param($types, ...$params);
  $st->execute();
  $rs = $st->get_result(); $clientes = [];
  while ($r = $rs->fetch_assoc()) { $clientes[] = $r; }
  $st->close();

  json_response(true, ['clientes' => $clientes]);

} catch (Throwable $e) {
  json_response(false, 'Error: '.$e->getMessage());
}
