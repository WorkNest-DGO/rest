<?php
declare(strict_types=1);
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config/db.php';

try {
  require_auth();

  $side = isset($_GET['side']) ? trim((string)$_GET['side']) : '';
  if (!in_array($side, ['bd1','bd2'], true)) {
    json_error('Parámetro side inválido');
  }
  $pdo = $side === 'bd1' ? ($pdoOp ?? null) : ($pdoEsp ?? null);
  if (!($pdo instanceof PDO)) {
    json_error("Conexión PDO no disponible para {$side}", 500);
  }

  $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
  $page = max(1, (int)($_GET['page'] ?? 1));
  $perPage = max(1, min(200, (int)($_GET['per_page'] ?? 20)));
  $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
  $to   = isset($_GET['to'])   ? trim((string)$_GET['to'])   : '';
  $offset = ($page - 1) * $perPage;

  // Column detection (tolerant)
  $t_has_folio = pdo_column_exists($pdo, 'tickets', 'folio');
  $t_has_fecha = pdo_column_exists($pdo, 'tickets', 'fecha');
  $t_has_creado = pdo_column_exists($pdo, 'tickets', 'creado_en');
  $t_fecha_col = $t_has_fecha ? 'fecha' : ($t_has_creado ? 'creado_en' : null);
  $t_has_venta = pdo_column_exists($pdo, 'tickets', 'venta_id');
  $t_has_total = pdo_column_exists($pdo, 'tickets', 'total');
  $t_has_desc  = pdo_column_exists($pdo, 'tickets', 'descuento');
  $t_has_corte = pdo_column_exists($pdo, 'tickets', 'corte_id');

  $ventas_exists = pdo_table_exists($pdo, 'ventas');
  $ventas_has_corte = $ventas_exists && pdo_column_exists($pdo, 'ventas', 'corte_id');

  $ft_exists = pdo_table_exists($pdo, 'factura_tickets');
  $f_exists  = pdo_table_exists($pdo, 'facturas');
  $f_has_status = $f_exists && pdo_column_exists($pdo, 'facturas', 'status');
  $f_has_estado = $f_exists && pdo_column_exists($pdo, 'facturas', 'estado');
  $f_status_col = $f_has_status ? 'status' : ($f_has_estado ? 'estado' : null);
  $f_has_ticket = $f_exists && pdo_column_exists($pdo, 'facturas', 'ticket_id');

  // Build SELECT
  $sel_cols = [
    't.id AS id'
  ];
  if ($t_has_venta) $sel_cols[] = 't.venta_id'; else $sel_cols[] = 'NULL AS venta_id';
  $sel_cols[] = $t_has_folio ? 't.folio' : 'NULL AS folio';
  $sel_cols[] = $t_has_total ? 't.total' : 'NULL AS total';
  $sel_cols[] = $t_has_desc  ? 't.descuento' : '0 AS descuento';
  $sel_cols[] = $t_fecha_col ? "t.$t_fecha_col AS fecha" : 'NULL AS fecha';
  if ($t_has_corte) {
    $sel_cols[] = 't.corte_id';
    $joinVentas = '';
  } elseif ($ventas_exists && $ventas_has_corte && $t_has_venta) {
    $sel_cols[] = 'v.corte_id';
    $joinVentas = ' LEFT JOIN ventas v ON v.id = t.venta_id ';
  } else {
    $sel_cols[] = 'NULL AS corte_id';
    $joinVentas = '';
  }

  // Joins for factura flag (SI/NO)
  $joinFactura = '';
  $flagFactura = "'NO' AS factura";
  if ($f_exists) {
    $parts = [];
    if ($ft_exists) {
      $parts[] = 'f1.id IS NOT NULL';
      $joinFactura .= ' LEFT JOIN factura_tickets ft ON ft.ticket_id = t.id ';
      $joinFactura .= ' LEFT JOIN facturas f1 ON f1.id = ft.factura_id ';
      if ($f_status_col) {
        $joinFactura .= " AND COALESCE(f1.$f_status_col, 'generada') <> 'cancelada' ";
      }
    }
    if ($f_has_ticket) {
      $parts[] = 'f2.id IS NOT NULL';
      $joinFactura .= ' LEFT JOIN facturas f2 ON f2.ticket_id = t.id ';
      if ($f_status_col) {
        $joinFactura .= " AND COALESCE(f2.$f_status_col, 'generada') <> 'cancelada' ";
      }
    }
    if ($parts) {
      $flagFactura = 'CASE WHEN ' . implode(' OR ', $parts) . " THEN 'SI' ELSE 'NO' END AS factura";
    }
  }

  $select = 'SELECT ' . implode(', ', $sel_cols) . ', ' . $flagFactura . ' FROM tickets t' . $joinVentas . $joinFactura;

  // Where filters
  $wheres = [];
  $params = [];
  if ($t_fecha_col && $from !== '') {
    $wheres[] = "t.$t_fecha_col >= ?"; $params[] = $from;
  }
  if ($t_fecha_col && $to !== '') {
    $wheres[] = "t.$t_fecha_col < DATE_ADD(?, INTERVAL 1 DAY)"; $params[] = $to;
  }
  if ($q !== '') {
    $like = "%$q%";
    $sub = [];
    if (ctype_digit($q)) { $sub[] = 't.id = ?'; $params[] = (int)$q; }
    if ($t_has_folio) { $sub[] = 't.folio LIKE ?'; $params[] = $like; }
    if ($t_has_venta) { $sub[] = 't.venta_id LIKE ?'; $params[] = $like; }
    if ($sub) $wheres[] = '(' . implode(' OR ', $sub) . ')';
  }
  $where = $wheres ? (' WHERE ' . implode(' AND ', $wheres)) : '';

  // Count total
  $sqlCount = 'SELECT COUNT(*) FROM ( ' . $select . $where . ' ) x';
  $st = $pdo->prepare($sqlCount);
  $st->execute($params);
  $total = (int)$st->fetchColumn();
  $st->closeCursor();

  // Fetch page
  $order = $t_fecha_col ? " ORDER BY fecha DESC, id DESC" : ' ORDER BY id DESC';
  $sql = $select . $where . $order . ' LIMIT ? OFFSET ?';
  $st = $pdo->prepare($sql);
  $bind = $params;
  $bind[] = $perPage;
  $bind[] = $offset;
  $st->execute($bind);
  $rows = $st->fetchAll();
  $st->closeCursor();

  json_ok(['rows' => $rows, 'page' => $page, 'per_page' => $perPage, 'total' => $total]);

} catch (Throwable $e) {
  json_error('Error: ' . $e->getMessage(), 500);
}

?>
