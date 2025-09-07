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
function column_exists(mysqli $db, string $t, string $c): bool {
  $q = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $q->bind_param('ss',$t,$c); $q->execute(); $q->store_result();
  $ok = $q->num_rows>0; $q->close(); return $ok;
}

try {
  $db = get_db(); $db->set_charset('utf8mb4');

  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) json_response(false, 'Body JSON inválido');

  $fid = (int)($body['factura_id'] ?? 0);
  $motivo = trim((string)($body['motivo'] ?? ''));
  $usuarioId = isset($body['usuario_id']) ? (int)$body['usuario_id'] : null;

  if ($fid <= 0) json_response(false, 'factura_id requerido');

  $f_status = column_exists($db,'facturas','status') ? 'status'
            : (column_exists($db,'facturas','estado') ? 'estado' : null);
  if (!$f_status) json_response(false,'La tabla facturas no tiene columna status/estado');

  $st = $db->prepare("SELECT $f_status AS est FROM facturas WHERE id = ?");
  $st->bind_param('i',$fid); $st->execute();
  $res = $st->get_result(); $row = $res ? $res->fetch_assoc() : null; $st->close();
  if (!$row) json_response(false, 'Factura no encontrada');
  if (strcasecmp((string)$row['est'],'cancelada') === 0) json_response(false,'La factura ya está cancelada');

  $sets = ["$f_status = 'cancelada'"];
  $types=''; $bind=[];

  if (column_exists($db,'facturas','motivo_cancelacion') && $motivo!=='') { $sets[]="motivo_cancelacion = ?"; $types.='s'; $bind[]=$motivo; }
  if (column_exists($db,'facturas','cancelada_en')) { $sets[]="cancelada_en = NOW()"; }
  if (column_exists($db,'facturas','cancelada_por') && $usuarioId) { $sets[]="cancelada_por = ?"; $types.='i'; $bind[]=$usuarioId; }

  $sql = "UPDATE facturas SET ".implode(', ',$sets)." WHERE id = ?"; $types.='i'; $bind[]=$fid;
  $st = $db->prepare($sql); $st->bind_param($types, ...$bind); $st->execute(); $st->close();

  // TODO: cancelar CFDI en PAC si aplica

  json_response(true, 'Factura cancelada');

} catch (Throwable $e) {
  json_response(false, 'Error: '.$e->getMessage());
}
