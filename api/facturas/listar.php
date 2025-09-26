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

  // Filtros
  $desde = $_GET['desde'] ?? null;
  $hasta = $_GET['hasta'] ?? null;
  $estado = $_GET['estado'] ?? 'todas'; // pendientes | facturadas | todas
  $buscar = trim((string)($_GET['buscar'] ?? ''));
  $facturaId = isset($_GET['factura_id']) ? (int)$_GET['factura_id'] : 0;

  if (!$desde || !$hasta) {
    $desde = $desde ?: (new DateTime('first day of this month 00:00:00'))->format('Y-m-d');
    $hasta = $hasta ?: (new DateTime('first day of next month 00:00:00'))->format('Y-m-d');
  }

  // Detección de columnas
  $f_fecha  = column_exists($db,'facturas','fecha') ? 'fecha'
            : (column_exists($db,'facturas','fecha_emision') ? 'fecha_emision' : null);
  $f_status = column_exists($db,'facturas','status') ? 'status'
            : (column_exists($db,'facturas','estado') ? 'estado' : null);
  if (!$f_fecha)  json_response(false, "La tabla facturas requiere columna fecha/fecha_emision");
  if (!$f_status) json_response(false, "La tabla facturas requiere columna status/estado");

  $has_folio      = column_exists($db,'facturas','folio');
  $has_cliente_id = column_exists($db,'facturas','cliente_id');
  $has_total      = column_exists($db,'facturas','total');
  $has_ticket_id  = column_exists($db,'facturas','ticket_id');

  $t_fecha = column_exists($db,'tickets','fecha') ? 'fecha' : null;

  $has_cf_tbl = table_exists($db,'clientes_facturacion');
  $cf_nombre  = $has_cf_tbl && column_exists($db,'clientes_facturacion','razon_social') ? 'razon_social'
              : ($has_cf_tbl && column_exists($db,'clientes_facturacion','nombre') ? 'nombre' : null);
  $cf_cols = [
    'rfc' => $has_cf_tbl && column_exists($db,'clientes_facturacion','rfc'),
    'correo' => $has_cf_tbl && column_exists($db,'clientes_facturacion','correo'),
    'telefono' => $has_cf_tbl && column_exists($db,'clientes_facturacion','telefono'),
    'calle' => $has_cf_tbl && column_exists($db,'clientes_facturacion','calle'),
    'numero_ext' => $has_cf_tbl && column_exists($db,'clientes_facturacion','numero_ext'),
    'numero_int' => $has_cf_tbl && column_exists($db,'clientes_facturacion','numero_int'),
    'colonia' => $has_cf_tbl && column_exists($db,'clientes_facturacion','colonia'),
    'municipio' => $has_cf_tbl && column_exists($db,'clientes_facturacion','municipio'),
    'estado' => $has_cf_tbl && column_exists($db,'clientes_facturacion','estado'),
    'pais' => $has_cf_tbl && column_exists($db,'clientes_facturacion','pais'),
    'cp' => $has_cf_tbl && column_exists($db,'clientes_facturacion','cp'),
    'regimen' => $has_cf_tbl && column_exists($db,'clientes_facturacion','regimen'),
    'uso_cfdi' => $has_cf_tbl && column_exists($db,'clientes_facturacion','uso_cfdi')
  ];

  // ===== Detalle de una factura (incluye datos de receptor) =====
  if ($facturaId > 0) {
    $sel_cf = '';
    if ($has_cf_tbl && $has_cliente_id) {
      $cf_list = [];
      if ($cf_nombre) $cf_list[] = "cf.$cf_nombre AS nombre";
      foreach ($cf_cols as $k=>$on) if ($on) $cf_list[] = "cf.$k AS $k";
      if ($cf_list) $sel_cf = ", " . implode(", ", $cf_list);
    }

    $has_uuid = column_exists($db,'facturas','uuid');
    $has_xmlp = column_exists($db,'facturas','xml_path');
    $has_pdfp = column_exists($db,'facturas','pdf_path');
    $sql = "SELECT f.id AS factura_id, f.$f_fecha AS fecha, f.$f_status AS status" .
           ($has_folio ? ", f.folio" : "") .
           ($has_total ? ", f.total" : "") .
           ($has_cliente_id ? ", f.cliente_id" : "") .
           ($has_ticket_id ? ", f.ticket_id" : "") .
           ($has_uuid ? ", f.uuid" : "") .
           ($has_xmlp ? ", f.xml_path" : "") .
           ($has_pdfp ? ", f.pdf_path" : "") .
           $sel_cf .
           " FROM facturas f " .
           ($has_cf_tbl && $has_cliente_id ? "LEFT JOIN clientes_facturacion cf ON cf.id = f.cliente_id " : "") .
           "WHERE f.id = ? LIMIT 1";
    $st = $db->prepare($sql); $st->bind_param('i',$facturaId); $st->execute();
    $fact = $st->get_result()->fetch_assoc(); $st->close();
    if (!$fact) json_response(false, 'Factura no encontrada');

    // Tickets asociados: de puente o legacy 1:1
    $tickets = [];
    if (table_exists($db,'factura_tickets')) {
      $st = $db->prepare("SELECT t.id, t.folio, t.total, ".($t_fecha ? "t.$t_fecha AS fecha" : "NULL AS fecha")." FROM factura_tickets ft JOIN tickets t ON t.id = ft.ticket_id WHERE ft.factura_id = ? ORDER BY t.id");
      $st->bind_param('i',$facturaId); $st->execute(); $rs = $st->get_result();
      while($r = $rs->fetch_assoc()) $tickets[] = $r; $st->close();
    }
    if (empty($tickets) && $has_ticket_id && !empty($fact['ticket_id'])) {
      $tkid = (int)$fact['ticket_id'];
      $st = $db->prepare("SELECT t.id, t.folio, t.total, ".($t_fecha ? "t.$t_fecha AS fecha" : "NULL AS fecha")." FROM tickets t WHERE t.id = ?");
      $st->bind_param('i',$tkid); $st->execute(); $rs = $st->get_result();
      if ($r = $rs->fetch_assoc()) $tickets[] = $r; $st->close();
    }

    // Detalles
    $det = [];
    if (table_exists($db,'factura_detalles')) {
      $st = $db->prepare("SELECT fd.id, fd.ticket_detalle_id, fd.producto_id, fd.descripcion, fd.cantidad, fd.precio_unitario, fd.importe FROM factura_detalles fd WHERE fd.factura_id = ?");
      $st->bind_param('i',$facturaId); $st->execute(); $rs = $st->get_result();
      while($r=$rs->fetch_assoc()) $det[]=$r; $st->close();
    }

    json_response(true, ['factura'=>$fact,'tickets'=>$tickets,'detalles'=>$det]);
  }

  $out = ['pendientes'=>[], 'facturadas'=>[]];

  // ====== PENDIENTES (excluir si ya facturado vía puente o legacy) ======
  if ($estado === 'todas' || $estado === 'pendientes') {
    $params = []; $types = '';
    $sql = "SELECT t.id, t.folio, t.total, ".($t_fecha ? "t.$t_fecha AS fecha" : "NULL AS fecha")."
            FROM tickets t
            LEFT JOIN factura_tickets ft ON ft.ticket_id = t.id
            LEFT JOIN facturas f ON ".($has_ticket_id ? "f.ticket_id = t.id" : "0")."
               AND ".($f_status ? "COALESCE(f.$f_status,'generada')" : "'generada'")." <> 'cancelada'
            WHERE ft.ticket_id IS NULL " . ($has_ticket_id ? "AND f.id IS NULL " : "");
    if ($t_fecha) { $sql .= "AND t.$t_fecha >= ? AND t.$t_fecha < ? "; $types.='ss'; $params[]=$desde; $params[]=$hasta; }
    if ($buscar !== '') { $sql .= "AND (t.folio LIKE ?) "; $types.='s'; $params[] = "%$buscar%"; }
    $sql .= "ORDER BY ".($t_fecha ? "t.$t_fecha ASC, " : "")."t.folio ASC";

    $st = $db->prepare($sql);
    if ($types) $st->bind_param($types, ...$params);
    $st->execute(); $rs = $st->get_result();
    while($r=$rs->fetch_assoc()) $out['pendientes'][]=$r;
    $st->close();
  }

  // ====== FACTURADAS (de puente y/o legacy 1:1) ======
  if ($estado === 'todas' || $estado === 'facturadas') {
    $params = [$desde,$hasta]; $types = 'ss';

    $sel_folio   = $has_folio      ? "f.folio" : "NULL AS folio";
    $sel_total   = $has_total      ? "f.total" : "NULL AS total";
    $sel_cliente = $has_cliente_id ? "f.cliente_id" : "NULL AS cliente_id";
    $sel_status  = $f_status       ? "f.$f_status AS status" : "NULL AS status";

    $sel_cliente_txt = "";
    $join_cf = "";
    if ($has_cf_tbl && $has_cliente_id) {
      $join_cf = " LEFT JOIN clientes_facturacion cf ON cf.id = f.cliente_id ";
      $parts = [];
      if ($cf_nombre) $parts[] = "cf.$cf_nombre AS cliente";
      if ($cf_cols['rfc']) $parts[] = "cf.rfc AS rfc";
      // Para la tabla (detalle reducido)
      $sel_cliente_txt = $parts ? (", ".implode(", ", $parts)) : "";
    }

    // Subconsulta A: facturas con ticket_id directo (legacy 1:1)
    $subA = "SELECT f.id AS factura_id, $sel_folio, f.$f_fecha AS fecha,
                    $sel_cliente, $sel_total, $sel_status,
                    1 AS tickets_cnt,
                    CAST(f.ticket_id AS CHAR) AS tickets
                    $sel_cliente_txt
             FROM facturas f
             $join_cf
             WHERE f.$f_fecha >= ? AND f.$f_fecha < ?";
    // Subconsulta B: facturas con múltiples tickets (puente)
    $subB = "SELECT f.id AS factura_id, $sel_folio, f.$f_fecha AS fecha,
                    $sel_cliente, $sel_total, $sel_status,
                    COUNT(ft.ticket_id) AS tickets_cnt,
                    GROUP_CONCAT(ft.ticket_id ORDER BY ft.ticket_id) AS tickets
                    $sel_cliente_txt
             FROM facturas f
             JOIN factura_tickets ft ON ft.factura_id = f.id
             $join_cf
             WHERE f.$f_fecha >= ? AND f.$f_fecha < ?
             GROUP BY f.id";

    $sql = "SELECT x.factura_id,
                   MIN(x.folio) AS folio,
                   MIN(x.fecha) AS fecha,
                   MIN(x.cliente_id) AS cliente_id,
                   MIN(x.total) AS total,
                   MIN(x.status) AS status,
                   SUM(x.tickets_cnt) AS tickets_cnt,
                   GROUP_CONCAT(x.tickets ORDER BY x.tickets) AS tickets" .
                   ($sel_cliente_txt ? ", MIN(x.cliente) AS cliente" : "") .
                   ($sel_cliente_txt && strpos($sel_cliente_txt,' rfc ')!==false ? ", MIN(x.rfc) AS rfc" : "") . "
            FROM (
               $subA
               UNION ALL
               $subB
            ) AS x
            GROUP BY x.factura_id
            ORDER BY fecha DESC, folio DESC";

    // Parámetros para A y B
    $typesUnion = $types . $types;
    $paramsUnion = [$desde,$hasta,$desde,$hasta];

    // Buscar por término
    if ($buscar !== '') {
      // Envolvemos arriba
      $sql = "SELECT * FROM ( $sql ) z WHERE 1";
      $like = "%$buscar%";
      if ($has_folio)   { $sql .= " AND (z.folio LIKE ?)"; $typesUnion .= 's'; $paramsUnion[] = $like; }
      else              { $sql .= " AND (z.factura_id LIKE ?)"; $typesUnion .= 's'; $paramsUnion[] = $like; }
    }

    $st = $db->prepare($sql);
    $st->bind_param($typesUnion, ...$paramsUnion);
    $st->execute(); $rs = $st->get_result();
    while($r=$rs->fetch_assoc()) $out['facturadas'][] = $r;
    $st->close();
  }

  json_response(true, $out);

} catch (Throwable $e) {
  json_response(false, 'Error: '.$e->getMessage());
}
