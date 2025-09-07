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
function column_is_nullable(mysqli $db, string $t, string $c): bool {
  $q = $db->prepare("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $q->bind_param('ss',$t,$c); $q->execute();
  $res = $q->get_result(); $row = $res ? $res->fetch_assoc() : null; $q->close();
  return $row ? ($row['IS_NULLABLE'] === 'YES') : true;
}
function ints(array $a): array { return array_values(array_filter(array_map('intval',$a),fn($x)=>$x>0)); }

try {
  $db = get_db();
  $db->set_charset('utf8mb4');

  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) json_response(false, 'Body JSON inválido');

  $modo = (string)($body['modo'] ?? '');
  $tickets = isset($body['tickets']) && is_array($body['tickets']) ? ints($body['tickets']) : [];
  $clienteId = isset($body['cliente_id']) ? (int)$body['cliente_id'] : 0;
  $periodo = isset($body['periodo']) && is_array($body['periodo']) ? $body['periodo'] : null;

  if (!in_array($modo,['uno_a_uno','global'],true)) json_response(false, 'modo debe ser uno_a_uno o global');
  if (empty($tickets)) json_response(false, 'Proporciona tickets[]');
  if ($clienteId <= 0 && column_exists($db,'facturas','cliente_id')) json_response(false, 'cliente_id requerido');

  $f_fecha  = column_exists($db,'facturas','fecha') ? 'fecha'
            : (column_exists($db,'facturas','fecha_emision') ? 'fecha_emision' : null);
  $f_status = column_exists($db,'facturas','status') ? 'status'
            : (column_exists($db,'facturas','estado') ? 'estado' : null);
  $has_sub  = column_exists($db,'facturas','subtotal');
  $has_imp  = column_exists($db,'facturas','impuestos');
  $has_tot  = column_exists($db,'facturas','total');
  $has_not  = column_exists($db,'facturas','notas');
  $has_cli  = column_exists($db,'facturas','cliente_id');
  $has_tkid = column_exists($db,'facturas','ticket_id');
  $tkid_nullable = $has_tkid ? column_is_nullable($db,'facturas','ticket_id') : true;

  if (!$f_fecha || !$f_status) json_response(false, 'La tabla facturas requiere fecha/status');

  // Evitar duplicados (puente y/o legacy), solo no canceladas
  $in = implode(',', $tickets);
  $dup = [];
  if (table_exists($db, 'factura_tickets')) {
    $q = $db->query("SELECT ticket_id FROM factura_tickets WHERE ticket_id IN ($in)");
    while($r=$q->fetch_assoc()) $dup[] = (int)$r['ticket_id'];
  }
  if ($has_tkid) {
    $q = $db->query("SELECT ticket_id FROM facturas WHERE ticket_id IN ($in) AND COALESCE($f_status,'generada') <> 'cancelada'");
    while($r=$q->fetch_assoc()) $dup[] = (int)$r['ticket_id'];
  }
  $dup = array_values(array_unique($dup));
  if ($dup) json_response(false, 'Uno o más tickets ya fueron facturados: '.implode(',',$dup));

  // Totales
  $totales = [];
  $rs = $db->query("SELECT td.ticket_id, SUM(td.cantidad * td.precio_unitario) AS subtotal FROM ticket_detalles td WHERE td.ticket_id IN ($in) GROUP BY td.ticket_id");
  while($r=$rs->fetch_assoc()){ $s=(float)$r['subtotal']; $totales[(int)$r['ticket_id']] = [$s,0.0,$s]; }
  $rs = $db->query("SELECT id,total FROM tickets WHERE id IN ($in)");
  while($r=$rs->fetch_assoc()){ $id=(int)$r['id']; if (!isset($totales[$id])) { $s=(float)$r['total']; $totales[$id]=[$s,0.0,$s]; } }

  if ($modo === 'uno_a_uno') {
    $emitidas = [];
    foreach ($tickets as $tk) {
      [$sub,$imp,$tot] = $totales[$tk] ?? [0.0,0.0,0.0];
      $db->begin_transaction();

      $cols=[]; $vals=[]; $types=''; $bind=[];
      if ($has_cli) { $cols[]='cliente_id'; $vals[]='?'; $types.='i'; $bind[]=$clienteId; }
      if ($has_sub) { $cols[]='subtotal';   $vals[]='?'; $types.='d'; $bind[]=$sub; }
      if ($has_imp) { $cols[]='impuestos';  $vals[]='?'; $types.='d'; $bind[]=$imp; }
      if ($has_tot) { $cols[]='total';      $vals[]='?'; $types.='d'; $bind[]=$tot; }
      if ($has_tkid) { $cols[]='ticket_id'; $vals[]='?'; $types.='i'; $bind[]=$tk; }
      $cols[]=$f_fecha;  $vals[]='NOW()';
      $cols[]=$f_status; $vals[]="'generada'";
      if ($has_not && $periodo) { $cols[]='notas'; $vals[]='?'; $types.='s'; $bind[] = json_encode(['modo'=>'uno_a_uno','tickets'=>[$tk],'periodo'=>$periodo], JSON_UNESCAPED_UNICODE); }

      $sql = "INSERT INTO facturas (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st = $db->prepare($sql); if ($types) $st->bind_param($types, ...$bind); $st->execute();
      $fid = $st->insert_id; $st->close();

      // Puente
      if (table_exists($db,'factura_tickets')) {
        $st = $db->prepare("INSERT INTO factura_tickets (factura_id, ticket_id) VALUES (?,?)");
        $st->bind_param('ii', $fid, $tk); $st->execute(); $st->close();
      }

      // Detalles
      if (table_exists($db,'factura_detalles')) {
        $sqlDet = "INSERT INTO factura_detalles (factura_id, ticket_detalle_id, producto_id, descripcion, cantidad, precio_unitario, importe)
                   SELECT ?, td.id, td.producto_id, p.nombre, td.cantidad, td.precio_unitario, (td.cantidad*td.precio_unitario)
                   FROM ticket_detalles td
                   LEFT JOIN productos p ON p.id = td.producto_id
                   WHERE td.ticket_id = ?";
        $st = $db->prepare($sqlDet); $st->bind_param('ii', $fid,$tk); $st->execute(); $st->close();
      }

      $db->commit();
      $emitidas[] = ['factura_id'=>$fid,'tickets'=>[$tk],'total'=>$tot];
    }
    json_response(true, ['facturas'=>$emitidas]);
  }

  // GLOBAL (1:N)
  $sub = 0.0; $imp = 0.0; $tot = 0.0;
  foreach($tickets as $tk){ [$s,$i,$t] = $totales[$tk] ?? [0.0,0.0,0.0]; $sub+=$s; $imp+=$i; $tot+=$t; }

  $db->begin_transaction();
  $cols=[]; $vals=[]; $types=''; $bind=[];
  if ($has_cli) { $cols[]='cliente_id'; $vals[]='?'; $types.='i'; $bind[]=$clienteId; }
  if ($has_sub) { $cols[]='subtotal';   $vals[]='?'; $types.='d'; $bind[]=$sub; }
  if ($has_imp) { $cols[]='impuestos';  $vals[]='?'; $types.='d'; $bind[]=$imp; }
  if ($has_tot) { $cols[]='total';      $vals[]='?'; $types.='d'; $bind[]=$tot; }
  if ($has_tkid) {
    $cols[]='ticket_id'; $vals[]='?'; $types.='i'; $bind[] = ($tkid_nullable ? null : (int)($tickets[0] ?? 0));
    if ($tkid_nullable && end($bind) === null) { array_pop($cols); array_pop($vals); $types = substr($types,0,-1); array_pop($bind); }
  }
  $cols[]=$f_fecha;  $vals[]='NOW()';
  $cols[]=$f_status; $vals[]="'generada'";
  if ($has_not) { $cols[]='notas'; $vals[]='?'; $types.='s'; $bind[] = json_encode(['modo'=>'global','tickets'=>$tickets,'periodo'=>$periodo], JSON_UNESCAPED_UNICODE); }

  $sql = "INSERT INTO facturas (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
  $st = $db->prepare($sql); if ($types) $st->bind_param($types, ...$bind); $st->execute();
  $fid = $st->insert_id; $st->close();

  if (table_exists($db,'factura_tickets')) {
    $st = $db->prepare("INSERT INTO factura_tickets (factura_id, ticket_id) VALUES (?,?)");
    foreach($tickets as $tk){ $st->bind_param('ii',$fid,$tk); $st->execute(); }
    $st->close();
  }

  if (table_exists($db,'factura_detalles')) {
    $in = implode(',', $tickets);
    $sqlDet = "INSERT INTO factura_detalles (factura_id, ticket_detalle_id, producto_id, descripcion, cantidad, precio_unitario, importe)
               SELECT ?, td.id, td.producto_id, p.nombre, td.cantidad, td.precio_unitario, (td.cantidad*td.precio_unitario)
               FROM ticket_detalles td
               LEFT JOIN productos p ON p.id = td.producto_id
               WHERE td.ticket_id IN ($in)";
    $st = $db->prepare($sqlDet); $st->bind_param('i',$fid); $st->execute(); $st->close();
  }

  $db->commit();
  json_response(true, ['factura_id'=>$fid,'tickets'=>$tickets,'total'=>$tot]);

} catch (Throwable $e) {
  if (isset($db) && $db->errno === 0) { /* noop */ }
  echo json_encode(['success'=>false,'mensaje'=>'Error: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
