<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/facturama.php'; // Helpers y credenciales locales para Facturama

// Logger local a REST (antes de cargar facturama.php para que lo use el wrapper)
if (!function_exists('facturas_log')) {
  function facturas_log(string $label, $data = null): void {
    try {
      $logDir = realpath(__DIR__ . '/../../logs');
      if ($logDir === false) { $logDir = __DIR__ . '/../../logs'; }
      if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
      $file = rtrim($logDir, '/\\') . '/facturama-' . date('Y-m') . '.log';
      $entry = ['ts'=>date('Y-m-d H:i:s'),'label'=>$label];
      if (!empty($_SERVER['REMOTE_ADDR'])) { $entry['ip'] = (string)$_SERVER['REMOTE_ADDR']; }
      if ($data instanceof Throwable) {
        $entry['error'] = ['type'=>get_class($data), 'code'=>$data->getCode(), 'message'=>$data->getMessage()];
      } elseif ($data !== null) {
        $entry['data'] = $data;
      }
      $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
      if ($json === false) { $json = $entry['ts'] . ' ' . $label; }
      @file_put_contents($file, $json . PHP_EOL, FILE_APPEND);
    } catch (Throwable $ignored) {}
  }
}

// Integración Facturama (reutiliza helper de /tokyo si existe)
// Util para normalizar contenido CFDI al guardar (similar a /tokyo/utils/cfdi_content.php)
if (!function_exists('unwrap_if_base64_or_json')) {
  function unwrap_if_base64_or_json($input) {
    if (!is_string($input) || $input === '') return $input;
    $trim = ltrim($input);
    if ($trim !== '' && $trim[0] === '{') {
      $j = json_decode($trim, true);
      if (json_last_error() === JSON_ERROR_NONE && isset($j['ContentEncoding'], $j['Content']) && strtolower((string)$j['ContentEncoding']) === 'base64') {
        $decoded = base64_decode($j['Content'], true);
        if ($decoded !== false) return $decoded;
      }
    }
    $maybe = base64_decode($trim, true);
    if ($maybe !== false) {
      $m = ltrim($maybe);
      if ($m !== '' && ($m[0] === '<' || strncmp($m, '%PDF', 4) === 0)) return $maybe;
    }
    return $input;
  }
}

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
function sanitize_forma_pago(?string $fp): ?string {
  if ($fp === null) return null;
  $fp = preg_replace('/\D/','',(string)$fp);
  return in_array($fp, ['01','03','04','28'], true) ? $fp : null;
}
function forma_pago_desde_tipo(?string $tipoPago, ?string $formaTarjeta = null, ?string $fallback = null): string {
  $tp = strtolower(trim((string)$tipoPago));
  $formaTarjeta = sanitize_forma_pago($formaTarjeta);
  $fallback = sanitize_forma_pago($fallback);
  if ($tp === 'efectivo') return '01';
  if ($tp === 'cheque') return '03';
  if ($tp === 'tarjeta' || $tp === 'boucher') {
    if (in_array($formaTarjeta, ['04','28'], true)) return $formaTarjeta;
    return '04'; // default tarjeta: T. Crédito
  }
  if ($fallback !== null) return $fallback;
  return '03';
}

// ---- Helpers de redondeo seguros (alinea con /tokyo) ----
if (!function_exists('money2')) {
  function money2($v) { return round((float)$v + 1e-9, 2); }     // 2 decimales
}
if (!function_exists('rate6')) {
  function rate6($v)  { return round((float)$v, 6); }            // 6 decimales
}
if (!function_exists('normalizar_items_cfdi')) {
  function normalizar_items_cfdi(array $items): array {
    foreach ($items as &$it) {
      $qty   = isset($it['Quantity'])   ? (float)$it['Quantity']   : 0.0;
      $price = isset($it['UnitPrice'])  ? (float)$it['UnitPrice']  : 0.0;
      $disc  = isset($it['Discount'])   ? (float)$it['Discount']   : 0.0;
      $importe = money2($qty * $price);
      $base    = money2($importe - $disc);
      $sumTras = 0.0; $sumRet = 0.0;
      if (!empty($it['Taxes']) && is_array($it['Taxes'])) {
        foreach ($it['Taxes'] as &$tx) {
          $rate = isset($tx['Rate']) ? (float)$tx['Rate'] : 0.0;
          $tx['Rate'] = rate6($rate);
          $tx['Base'] = money2($base);
          $txTotal = money2($base * $rate);
          $tx['Total'] = $txTotal;
          $isRet = !empty($tx['IsRetention']);
          if ($isRet) $sumRet += $txTotal; else $sumTras += $txTotal;
        }
        unset($tx);
      }
      $conceptTotal = money2($importe - $disc + $sumTras - $sumRet);
      $it['Subtotal'] = $importe;
      if ($disc > 0) { $it['Discount'] = money2($disc); }
      $it['Total']    = $conceptTotal;
    }
    unset($it);
    return $items;
  }
}

/**
 * Devuelve el codigo de producto SAT segun la categoria del producto.
 * Catalogos solicitados:
 *  1 (bebida)        -> 50193000
 *  2 (postre)        -> 50192301
 *  8,9,11,12,13      -> 50192100
 *  3,4,5,6,7,10      -> 90101503
 *  default           -> 01010101
 */
function product_code_from_categoria(?int $catId): string {
  $map = [
    1 => '50193000',
    2 => '50192301',
    3 => '90101503',
    4 => '90101503',
    5 => '90101503',
    6 => '90101503',
    7 => '90101503',
    8 => '50192100',
    9 => '50192100',
    10 => '90101503',
    11 => '50192100',
    12 => '50192100',
    13 => '50192100',
    14 => '50202201',
  ];
  return $map[$catId ?? 0] ?? '01010101';
}

function descripcion_cfdi_from_categoria(?int $catId, string $nombreOriginal): string {
  if ($catId === 1 || $catId === 14) return 'Consumo de bebidas en general';
  if ($catId === 2) return 'postres preparados para consumo';
  return $nombreOriginal;
}

/**
 * Construye items CFDI, prorrateando el descuento del ticket sobre los productos
 * para que el total facturado cuadre con el monto_recibido (o total - descuento).
 */
function cfdi_ticket_data(mysqli $db, int $ticketId): array {
  static $cache = [];
  if (isset($cache[$ticketId])) return $cache[$ticketId];

  // Header del ticket (monto cobrado y descuento)
  $ticketRow = ['total'=>0.0,'descuento'=>0.0,'monto_recibido'=>null];
  if ($stTk = $db->prepare("SELECT total, descuento, monto_recibido FROM tickets WHERE id = ? LIMIT 1")) {
    $stTk->bind_param('i', $ticketId);
    if ($stTk->execute()) {
      $resTk = $stTk->get_result();
      if ($tmp = $resTk->fetch_assoc()) { $ticketRow = $tmp; }
    }
    $stTk->close();
  }
  $descuentoTicket = max(0.0, (float)($ticketRow['descuento'] ?? 0.0));
  $montoRecibido   = isset($ticketRow['monto_recibido']) ? (float)$ticketRow['monto_recibido'] : null;

  // Detalles del ticket
  $sql = "SELECT td.id AS ticket_detalle_id, td.producto_id, COALESCE(p.nombre, CONCAT('Producto ', td.producto_id)) AS descripcion,
                 td.cantidad, td.precio_unitario, p.categoria_id
          FROM ticket_detalles td LEFT JOIN productos p ON p.id = td.producto_id WHERE td.ticket_id = ?";
  $st = $db->prepare($sql); $st->bind_param('i',$ticketId); $st->execute();
  $rs = $st->get_result(); $rows = [];
  while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
  $st->close();
  if (!$rows) {
    $cache[$ticketId] = ['items'=>[], 'detalles'=>[], 'subtotal'=>0.0, 'impuestos'=>0.0, 'total'=>0.0, 'target_total'=>0.0];
    return $cache[$ticketId];
  }

  $totalBruto = 0.0;
  foreach ($rows as $r) { $totalBruto += (float)$r['precio_unitario'] * (float)$r['cantidad']; }

  // Objetivo: monto_recibido; si no hay, intentar total - descuento
  $targetBruto = $montoRecibido !== null ? (float)$montoRecibido : ($totalBruto - $descuentoTicket);
  if ($targetBruto < 0) $targetBruto = 0.0;

  // Factor de prorrateo
  $factor = ($totalBruto > 0) ? ($targetBruto / $totalBruto) : 1.0;
  if ($factor < 0) $factor = 0.0;

  $items = [];
  $detalles = [];
  $importeSum = 0.0;
  $n = count($rows);
  foreach ($rows as $idx => $r) {
    $qty = (float)$r['cantidad'];
    $precioConIva = (float)$r['precio_unitario'];
    $precioAjustado = $precioConIva * $factor;

    // Ajuste fino en el �ltimo item para cuadrar con el objetivo
    $importeCalculado = $precioAjustado * $qty;
    if ($idx === $n - 1 && $targetBruto > 0) {
      $restante = $targetBruto - ($importeSum + $importeCalculado);
      if ($qty > 0) {
        $precioAjustado += $restante / $qty;
        $importeCalculado = $precioAjustado * $qty;
      }
    }

    $importe = money2($importeCalculado);
    if ($idx === $n - 1 && $targetBruto > 0) {
      // Asegurar que la suma de importes coincide exactamente con el objetivo
      $ajusteFinal = money2($targetBruto - ($importeSum + $importe));
      $importe += $ajusteFinal;
      if ($qty > 0) { $precioAjustado = $importe / $qty; }
    }
    $importeSum += $importe;

    $unitPriceNeto = round($precioAjustado / 1.16, 6);
    $catId = isset($r['categoria_id']) ? (int)$r['categoria_id'] : null;
    $descCfdi = descripcion_cfdi_from_categoria($catId, (string)$r['descripcion']);
    $items[] = [
      'ProductCode' => product_code_from_categoria($catId),
      'IdentificationNumber' => (string)((int)$r['producto_id']),
      'Description' => $descCfdi,
      'Unit' => 'Pieza',
      'UnitCode' => 'H87',
      'UnitPrice' => (float)$unitPriceNeto,
      'Quantity' => (float)$qty,
      'Taxes' => [[
        'Name' => 'IVA',
        'Base' => (float)round($unitPriceNeto * $qty, 6),
        'Rate' => 0.16,
        'IsRetention' => false,
        'FactorType' => 'Tasa',
        'Total' => (float)round($unitPriceNeto * $qty * 0.16, 6),
      ]],
      'TaxObject' => '02',
    ];

    $detalles[] = [
      'ticket_detalle_id' => (int)$r['ticket_detalle_id'],
      'producto_id' => (int)$r['producto_id'],
      'descripcion' => $descCfdi,
      'cantidad' => (float)$qty,
      'precio_unitario' => (float)money2($precioAjustado),
      'importe' => (float)$importe,
    ];
  }

  // Normalizar items y sumar totales
  if (!empty($items)) { $items = normalizar_items_cfdi($items); }
  $sumSub = 0.0; $sumImp = 0.0; $sumTot = 0.0;
  foreach ($items as $it) {
    $sumSub += (float)($it['Subtotal'] ?? 0.0);
    $sumTot += (float)($it['Total'] ?? 0.0);
    if (!empty($it['Taxes']) && is_array($it['Taxes'])) {
      foreach ($it['Taxes'] as $tx) {
        if (!empty($tx['IsRetention'])) continue;
        $sumImp += (float)($tx['Total'] ?? 0.0);
      }
    }
  }
  $diff = money2($targetBruto - $sumTot);
  if (abs($diff) >= 0.01 && !empty($items)) {
    $idxAdj = count($items) - 1;
    $qtyAdj = (float)($items[$idxAdj]['Quantity'] ?? 1.0);
    if ($qtyAdj <= 0) { $qtyAdj = 1.0; }
    $currentGross = (float)($items[$idxAdj]['Total'] ?? 0.0);
    $newGross = money2($currentGross + $diff);
    if ($newGross < 0) { $newGross = 0.0; }
    $unitPriceAdj = round(($newGross / 1.16) / $qtyAdj, 6);
    $items[$idxAdj]['UnitPrice'] = $unitPriceAdj;
    if (isset($detalles[$idxAdj])) {
      $detalles[$idxAdj]['precio_unitario'] = (float)money2($qtyAdj > 0 ? ($newGross / $qtyAdj) : $newGross);
      $detalles[$idxAdj]['importe'] = (float)money2($newGross);
    }
    $items[$idxAdj]['Taxes'] = [[
      'Name' => 'IVA',
      'Base' => (float)round($unitPriceAdj * $qtyAdj, 6),
      'Rate' => 0.16,
      'IsRetention' => false,
      'FactorType' => 'Tasa',
      'Total' => (float)round($unitPriceAdj * $qtyAdj * 0.16, 6),
    ]];
    $items = normalizar_items_cfdi($items);
    $sumSub = 0.0; $sumImp = 0.0; $sumTot = 0.0;
    foreach ($items as $it) {
      $sumSub += (float)($it['Subtotal'] ?? 0.0);
      $sumTot += (float)($it['Total'] ?? 0.0);
      if (!empty($it['Taxes']) && is_array($it['Taxes'])) {
        foreach ($it['Taxes'] as $tx) {
          if (!empty($tx['IsRetention'])) continue;
          $sumImp += (float)($tx['Total'] ?? 0.0);
        }
      }
    }
  }

  $cache[$ticketId] = [
    'items' => $items,
    'detalles' => $detalles,
    'subtotal' => money2($sumSub),
    'impuestos' => money2($sumImp),
    'total' => money2($sumTot),
    'target_total' => $targetBruto,
  ];
  return $cache[$ticketId];
}

// Construir Items CFDI a partir de ticket_detalles (mysqli)
function cfdi_items_from_ticket(mysqli $db, int $ticketId): array {
  $data = cfdi_ticket_data($db, $ticketId);
  return $data['items'];
}

// Obtener datos del cliente fiscal (tolerante a columnas)
function obtener_cliente_fiscal(mysqli $db, int $clienteId): array {
  $cols = ['id'];
  $has = fn(string $c)=>column_exists($db,'clientes_facturacion',$c);
  $map = [
    'rfc'=>'rfc','razon_social'=>'razon_social','correo'=>'correo','regimen'=>'regimen','cp'=>'cp','uso_cfdi'=>'uso_cfdi',
  ];
  foreach ($map as $c=>$alias) { if ($has($c)) $cols[] = "$c AS $alias"; else $cols[] = "NULL AS $alias"; }
  $sql = "SELECT ".implode(',', $cols)." FROM clientes_facturacion WHERE id = ? LIMIT 1";
  $st = $db->prepare($sql); $st->bind_param('i',$clienteId); $st->execute();
  $res = $st->get_result(); $row = $res ? ($res->fetch_assoc() ?: []) : [];
  $st->close();
  return $row ?: [];
}

function build_cfdi_for_tickets(mysqli $db, array $ticketIds, array $cliente, ?string $serie = null, ?array $periodo = null, ?string $formaPago = null): array {
  $items = [];
  foreach ($ticketIds as $tk) {
    foreach (cfdi_items_from_ticket($db, (int)$tk) as $it) $items[] = $it;
  }
  $fp = sanitize_forma_pago($formaPago) ?? '03';
  $cfdi = [
    'Serie' => $serie !== null ? $serie : (getenv('CFDI_SERIE') ?: 'A'),
    'Currency' => 'MXN',
    'ExpeditionPlace' => (method_exists('FacturamaCfg','expeditionPlace') ? FacturamaCfg::expeditionPlace() : (getenv('FACTURAMA_EXPEDITION_PLACE') ?: '34217')),
    'CfdiType' => 'I',
    'PaymentMethod' => 'PUE',
    'PaymentForm' => $fp,
    'Receiver' => [
      'Rfc' => strtoupper((string)($cliente['rfc'] ?? '')),
      'Name' => (string)($cliente['razon_social'] ?? ''),
      'FiscalRegime' => (string)($cliente['regimen'] ?? ''),
      'TaxZipCode' => (string)($cliente['cp'] ?? ''),
      'CfdiUse' => (string)($cliente['uso_cfdi'] ?? ''),
    ],
    'Items' => $items,
  ];
  // Ajustes para PUBLICO EN GENERAL (receptor genérico)
  $rfcRx = strtoupper((string)($cfdi['Receiver']['Rfc'] ?? ''));
  $nameRx = (string)($cfdi['Receiver']['Name'] ?? '');
  $nameUpper = function_exists('mb_strtoupper') ? mb_strtoupper($nameRx, 'UTF-8') : strtoupper($nameRx);
  $isGenerico = ($rfcRx === 'XAXX010101000' && $nameUpper === 'PUBLICO EN GENERAL');
  if ($isGenerico) {
    $cfdi['Receiver']['CfdiUse'] = 'S01';
    $cfdi['Receiver']['FiscalRegime'] = '616';
    $cfdi['Receiver']['Name'] = 'PUBLICO EN GENERAL';

    // Fecha base: del periodo o de los tickets (mínima)
    $fechaBase = null;
    if (is_array($periodo) && !empty($periodo['desde'])) {
      try { $fechaBase = new DateTime((string)$periodo['desde']); } catch (Throwable $e) { $fechaBase = null; }
    }
    if (!$fechaBase) {
      // Buscar fecha mínima de los tickets si existe columna fecha
      $tFechaCol = 'fecha';
      $hasFecha = (bool)$db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tickets' AND COLUMN_NAME='fecha'")->num_rows;
      if ($hasFecha) {
        $in = implode(',', array_map('intval', $ticketIds));
        $sql = "SELECT MIN($tFechaCol) AS fmin FROM tickets WHERE id IN ($in)";
        $rs = $db->query($sql);
        $r = $rs ? $rs->fetch_assoc() : null;
        if ($r && !empty($r['fmin'])) {
          try { $fechaBase = new DateTime((string)$r['fmin']); } catch (Throwable $e) { $fechaBase = null; }
        }
      }
      if (!$fechaBase) { $fechaBase = new DateTime('now'); }
    }
    $mes  = $fechaBase->format('m');
    $anio = (int)$fechaBase->format('Y');
    $anioHoy = (int)(new DateTime('now'))->format('Y');
    if (!($anio === $anioHoy || $anio === $anioHoy - 1)) {
      // Forzar año válido
      $anio = $anioHoy;
    }
    $cfdi['GlobalInformation'] = [
      'Periodicity' => '01',
      'Months' => $mes,
      'Year' => $anio,
    ];
  }
  // Normalizar items al final
  if (!empty($cfdi['Items'])) { $cfdi['Items'] = normalizar_items_cfdi($cfdi['Items']); }
  return $cfdi;
}

try {
  $db = get_db();
  $db->set_charset('utf8mb4');

  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) json_response(false, 'Body JSON inválido');

  $modo = (string)($body['modo'] ?? '');
  $tickets = isset($body['tickets']) && is_array($body['tickets']) ? ints($body['tickets']) : [];
  $clienteId = isset($body['cliente_id']) ? (int)$body['cliente_id'] : 0;
  $periodo = isset($body['periodo']) && is_array($body['periodo']) ? $body['periodo'] : null;
  $formaPagoBody = sanitize_forma_pago($body['forma_pago'] ?? null);
  $formaPagoTarjeta = sanitize_forma_pago($body['forma_pago_tarjeta'] ?? null);

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
  $has_ticket_tipo_pago = column_exists($db,'tickets','tipo_pago');

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

  // Totales y tipo de pago
  $totales = [];
  $ticketTipoPago = [];
  $sqlTk = "SELECT id,total, descuento, monto_recibido" . ($has_ticket_tipo_pago ? ", tipo_pago" : ", NULL AS tipo_pago") . " FROM tickets WHERE id IN ($in)";
  $rs = $db->query($sqlTk);
  while($r=$rs->fetch_assoc()){
    $id=(int)$r['id'];
    if ($has_ticket_tipo_pago) { $ticketTipoPago[$id] = strtolower((string)$r['tipo_pago']); }
  }
  foreach ($tickets as $tk) {
    $calc = cfdi_ticket_data($db, (int)$tk);
    $totales[(int)$tk] = [$calc['subtotal'], $calc['impuestos'], $calc['total']];
  }

  if ($modo === 'uno_a_uno') {
    $emitidas = [];
    $errores  = [];
    $cliente = obtener_cliente_fiscal($db, $clienteId);
    foreach ($tickets as $tk) {
      try {
        [$sub,$imp,$tot] = $totales[$tk] ?? [0.0,0.0,0.0];
        $tipoPagoTicket = $ticketTipoPago[$tk] ?? null;
        $formaPagoTicket = forma_pago_desde_tipo($tipoPagoTicket, $formaPagoTarjeta, $formaPagoBody);
        $db->begin_transaction();

        // 1) Insert local (temporal)
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

        // 2) Puente y detalles locales
        if (table_exists($db,'factura_tickets')) {
          $st = $db->prepare("INSERT INTO factura_tickets (factura_id, ticket_id) VALUES (?,?)");
          $st->bind_param('ii', $fid, $tk); $st->execute(); $st->close();
        }
        if (table_exists($db,'factura_detalles')) {
          $detTicket = cfdi_ticket_data($db, (int)$tk)['detalles'] ?? [];
          if ($detTicket) {
            $sqlDet = "INSERT INTO factura_detalles (factura_id, ticket_detalle_id, producto_id, descripcion, cantidad, precio_unitario, importe) VALUES (?,?,?,?,?,?,?)";
            $st = $db->prepare($sqlDet);
            foreach ($detTicket as $det) {
              $desc = $det['descripcion'] ?? '';
              $cant = (float)($det['cantidad'] ?? 0);
              $precio = (float)($det['precio_unitario'] ?? 0);
              $imp = (float)($det['importe'] ?? 0);
              $st->bind_param('iiisddd', $fid, $det['ticket_detalle_id'], $det['producto_id'], $desc, $cant, $precio, $imp);
              $st->execute();
            }
            $st->close();
          }
        }

        // 3) Construir CFDI e intentar timbrar (Facturama)
        if (!function_exists('facturama_create_cfdi_json')) { throw new RuntimeException('Integración Facturama no disponible'); }
        $cfdi = build_cfdi_for_tickets($db, [$tk], $cliente, null, null, $formaPagoTicket);
        if (function_exists('facturas_log')) { facturas_log('REST_CFDI_TO_FACTURAMA', ['modo'=>'uno_a_uno','factura_id'=>$fid,'ticket_id'=>$tk,'cfdi'=>$cfdi,'tipo_pago'=>$tipoPagoTicket,'forma_pago'=>$formaPagoTicket]); }
        $resp = facturama_create_cfdi_json($cfdi);
        $facturamaId = (string)($resp['Id'] ?? ($resp['id'] ?? ''));
        $uuid = (string)($resp['Uuid'] ?? ($resp['FolioFiscal'] ?? ''));
        $serieResp = (string)($resp['Serie'] ?? '');
        $folioResp = (string)($resp['Folio'] ?? ($resp['FolioInt'] ?? ''));

        // 4) Actualizar factura con datos de timbrado (si hay columnas)
        $colsUp = [];
        $typesUp = '';
        $bindUp = [];
        if (column_exists($db,'facturas','facturama_id')) { $colsUp[]='facturama_id=?'; $typesUp.='s'; $bindUp[]=$facturamaId ?: null; }
        if (column_exists($db,'facturas','uuid'))         { $colsUp[]='uuid=?';         $typesUp.='s'; $bindUp[]=$uuid ?: null; }
        if (column_exists($db,'facturas','serie'))        { $colsUp[]='serie=?';        $typesUp.='s'; $bindUp[]=$serieResp ?: null; }
        if (column_exists($db,'facturas','folio'))        { $colsUp[]='folio=?';        $typesUp.='s'; $bindUp[]=$folioResp ?: null; }
        if (column_exists($db,'facturas','metodo_pago'))  { $colsUp[]='metodo_pago=?';  $typesUp.='s'; $bindUp[]='PUE'; }
        if (column_exists($db,'facturas','forma_pago'))   { $colsUp[]='forma_pago=?';   $typesUp.='s'; $bindUp[]=$formaPagoTicket; }
        if (column_exists($db,'facturas','uso_cfdi'))     { $colsUp[]='uso_cfdi=?';     $typesUp.='s'; $bindUp[]=(string)($cliente['uso_cfdi'] ?? ''); }
        if ($colsUp) {
          $sqlUp = "UPDATE facturas SET ".implode(',', $colsUp)." WHERE id=?";
          $typesUp .= 'i'; $bindUp[] = $fid;
          $st = $db->prepare($sqlUp); $st->bind_param($typesUp, ...$bindUp); $st->execute(); $st->close();
        }

        // 5) Descargar y cachear XML/PDF si hay columnas y facturamaId
        $has_xmlp = column_exists($db,'facturas','xml_path');
        $has_pdfp = column_exists($db,'facturas','pdf_path');
        if ($facturamaId !== '' && ($has_xmlp || $has_pdfp)) {
          $year = date('Y'); $month = date('m');
          $baseDir = realpath(__DIR__ . '/../../');
          if ($baseDir === false) { $baseDir = __DIR__ . '/../../'; }
          $dir = rtrim($baseDir, '/\\') . '/archivos/facturas/' . $year . '/' . $month;
          if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
          $relXml = null; $relPdf = null;
          if ($has_xmlp) {
            try {
              [$xml, $ctXml] = facturama_download_issued('xml', $facturamaId);
              $xmlClean = unwrap_if_base64_or_json($xml);
              $xmlName = ($uuid !== '' ? $uuid : ('cfdi_' . $fid)) . '.xml';
              $xmlPath = $dir . '/' . $xmlName;
              @file_put_contents($xmlPath, $xmlClean);
              $relXml = 'archivos/facturas/' . $year . '/' . $month . '/' . $xmlName;
            } catch (Throwable $e2) { /* ignore */ }
          }
          if ($has_pdfp) {
            try {
              [$pdf, $ctPdf] = facturama_download_issued('pdf', $facturamaId);
              $pdfClean = unwrap_if_base64_or_json($pdf);
              $pdfName = ($uuid !== '' ? $uuid : ('cfdi_' . $fid)) . '.pdf';
              $pdfPath = $dir . '/' . $pdfName;
              @file_put_contents($pdfPath, $pdfClean);
              $relPdf = 'archivos/facturas/' . $year . '/' . $month . '/' . $pdfName;
            } catch (Throwable $e2) { /* ignore */ }
          }
          if ($relXml !== null || $relPdf !== null) {
            $sets = [];$typesSet='';$bindSet=[];
            if ($relXml !== null && $has_xmlp) { $sets[]='xml_path=?'; $typesSet.='s'; $bindSet[]=$relXml; }
            if ($relPdf !== null && $has_pdfp) { $sets[]='pdf_path=?'; $typesSet.='s'; $bindSet[]=$relPdf; }
            if ($sets) {
              $sqlSet = 'UPDATE facturas SET ' . implode(',', $sets) . ' WHERE id=?';
              $typesSet .= 'i'; $bindSet[] = $fid;
              $st = $db->prepare($sqlSet); $st->bind_param($typesSet, ...$bindSet); $st->execute(); $st->close();
            }
          }
        }

        $db->commit();
        if (function_exists('facturas_log')) { facturas_log('REST_FACTURAMA_OK', ['modo'=>'uno_a_uno','factura_id'=>$fid,'ticket_id'=>$tk,'uuid'=>$uuid,'fid'=>$facturamaId]); }
        $emitidas[] = ['factura_id'=>$fid,'tickets'=>[$tk],'total'=>$tot,'uuid'=>$uuid];
      } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $e2) {}
        if (function_exists('facturas_log')) { facturas_log('REST_FACTURAMA_ERROR', ['modo'=>'uno_a_uno','ticket_id'=>$tk,'error'=>$e->getMessage()]); }
        $errores[] = ['ticket_id'=>$tk,'error'=>$e->getMessage()];
      }
    }
    if (count($emitidas) === 0) { json_response(false, 'No se pudieron timbrar los tickets seleccionados'); }
    json_response(true, ['facturas'=>$emitidas,'errores'=>$errores]);
  }

  // GLOBAL (1:N)
  $sub = 0.0; $imp = 0.0; $tot = 0.0;
  $tiposSeleccionados = [];
  foreach($tickets as $tk){
    [$s,$i,$t] = $totales[$tk] ?? [0.0,0.0,0.0];
    $sub+=$s; $imp+=$i; $tot+=$t;
    if (isset($ticketTipoPago[$tk])) $tiposSeleccionados[] = $ticketTipoPago[$tk];
  }
  $tiposUnicos = array_values(array_unique(array_filter($tiposSeleccionados, fn($v)=>$v!=='')));
  if (count($tiposUnicos) > 1 && $formaPagoBody === null) {
    json_response(false, 'Los tickets seleccionados tienen distintos tipo_pago; elige tickets con el mismo tipo o proporciona forma_pago para factura global.');
  }
  $tipoPagoGlobal = count($tiposUnicos) === 1 ? $tiposUnicos[0] : null;
  $formaPagoGlobal = forma_pago_desde_tipo($tipoPagoGlobal, $formaPagoTarjeta, $formaPagoBody);

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
    $sqlDet = "INSERT INTO factura_detalles (factura_id, ticket_detalle_id, producto_id, descripcion, cantidad, precio_unitario, importe) VALUES (?,?,?,?,?,?,?)";
    $st = $db->prepare($sqlDet);
    foreach ($tickets as $tk) {
      $detTicket = cfdi_ticket_data($db, (int)$tk)['detalles'] ?? [];
      foreach ($detTicket as $det) {
        $desc = $det['descripcion'] ?? '';
        $cant = (float)($det['cantidad'] ?? 0);
        $precio = (float)($det['precio_unitario'] ?? 0);
        $imp = (float)($det['importe'] ?? 0);
        $st->bind_param('iiisddd', $fid, $det['ticket_detalle_id'], $det['producto_id'], $desc, $cant, $precio, $imp);
        $st->execute();
      }
    }
    $st->close();
  }

  // Timbrar Factura GLOBAL (1:N) vía Facturama
  try {
    if (!function_exists('facturama_create_cfdi_json')) { throw new RuntimeException('Integración Facturama no disponible'); }
    $cliente = obtener_cliente_fiscal($db, $clienteId);
    $cfdi = build_cfdi_for_tickets($db, $tickets, $cliente, null, $periodo, $formaPagoGlobal);
    if (function_exists('facturas_log')) { facturas_log('REST_CFDI_TO_FACTURAMA', ['modo'=>'global','factura_id'=>$fid,'tickets'=>$tickets,'cfdi'=>$cfdi,'tipo_pago'=>$tipoPagoGlobal,'forma_pago'=>$formaPagoGlobal]); }
    $resp = facturama_create_cfdi_json($cfdi);
    $facturamaId = (string)($resp['Id'] ?? ($resp['id'] ?? ''));
    $uuid = (string)($resp['Uuid'] ?? ($resp['FolioFiscal'] ?? ''));
    $serieResp = (string)($resp['Serie'] ?? '');
    $folioResp = (string)($resp['Folio'] ?? ($resp['FolioInt'] ?? ''));

    $colsUp = [];$typesUp='';$bindUp=[];
    if (column_exists($db,'facturas','facturama_id')) { $colsUp[]='facturama_id=?'; $typesUp.='s'; $bindUp[]=$facturamaId ?: null; }
    if (column_exists($db,'facturas','uuid'))         { $colsUp[]='uuid=?';         $typesUp.='s'; $bindUp[]=$uuid ?: null; }
    if (column_exists($db,'facturas','serie'))        { $colsUp[]='serie=?';        $typesUp.='s'; $bindUp[]=$serieResp ?: null; }
    if (column_exists($db,'facturas','folio'))        { $colsUp[]='folio=?';        $typesUp.='s'; $bindUp[]=$folioResp ?: null; }
    if (column_exists($db,'facturas','metodo_pago'))  { $colsUp[]='metodo_pago=?';  $typesUp.='s'; $bindUp[]='PUE'; }
    if (column_exists($db,'facturas','forma_pago'))   { $colsUp[]='forma_pago=?';   $typesUp.='s'; $bindUp[]=$formaPagoGlobal; }
    if (column_exists($db,'facturas','uso_cfdi'))     { $colsUp[]='uso_cfdi=?';     $typesUp.='s'; $bindUp[]=(string)($cliente['uso_cfdi'] ?? ''); }
    if ($colsUp) {
      $sqlUp = "UPDATE facturas SET ".implode(',', $colsUp)." WHERE id=?";
      $typesUp .= 'i'; $bindUp[] = $fid;
      $st = $db->prepare($sqlUp); $st->bind_param($typesUp, ...$bindUp); $st->execute(); $st->close();
    }

    // Descargar y cachear XML/PDF si hay columnas y facturamaId
    $has_xmlp = column_exists($db,'facturas','xml_path');
    $has_pdfp = column_exists($db,'facturas','pdf_path');
    if ($facturamaId !== '' && ($has_xmlp || $has_pdfp)) {
      $year = date('Y'); $month = date('m');
      $baseDir = realpath(__DIR__ . '/../../');
      if ($baseDir === false) { $baseDir = __DIR__ . '/../../'; }
      $dir = rtrim($baseDir, '/\\') . '/archivos/facturas/' . $year . '/' . $month;
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $relXml = null; $relPdf = null;
      if ($has_xmlp) {
        try {
          [$xml, $ctXml] = facturama_download_issued('xml', $facturamaId);
          $xmlClean = unwrap_if_base64_or_json($xml);
          $xmlName = ($uuid !== '' ? $uuid : ('cfdi_' . $fid)) . '.xml';
          $xmlPath = $dir . '/' . $xmlName;
          @file_put_contents($xmlPath, $xmlClean);
          $relXml = 'archivos/facturas/' . $year . '/' . $month . '/' . $xmlName;
        } catch (Throwable $e2) { /* ignore */ }
      }
      if ($has_pdfp) {
        try {
          [$pdf, $ctPdf] = facturama_download_issued('pdf', $facturamaId);
          $pdfClean = unwrap_if_base64_or_json($pdf);
          $pdfName = ($uuid !== '' ? $uuid : ('cfdi_' . $fid)) . '.pdf';
          $pdfPath = $dir . '/' . $pdfName;
          @file_put_contents($pdfPath, $pdfClean);
          $relPdf = 'archivos/facturas/' . $year . '/' . $month . '/' . $pdfName;
        } catch (Throwable $e2) { /* ignore */ }
      }
      if ($relXml !== null || $relPdf !== null) {
        $sets = [];$typesSet='';$bindSet=[];
        if ($relXml !== null && $has_xmlp) { $sets[]='xml_path=?'; $typesSet.='s'; $bindSet[]=$relXml; }
        if ($relPdf !== null && $has_pdfp) { $sets[]='pdf_path=?'; $typesSet.='s'; $bindSet[]=$relPdf; }
        if ($sets) {
          $sqlSet = 'UPDATE facturas SET ' . implode(',', $sets) . ' WHERE id=?';
          $typesSet .= 'i'; $bindSet[] = $fid;
          $st = $db->prepare($sqlSet); $st->bind_param($typesSet, ...$bindSet); $st->execute(); $st->close();
        }
      }
    }

    $db->commit();
    if (function_exists('facturas_log')) { facturas_log('REST_FACTURAMA_OK', ['modo'=>'global','factura_id'=>$fid,'tickets'=>$tickets,'uuid'=>$uuid,'fid'=>$facturamaId]); }
    json_response(true, ['factura_id'=>$fid,'tickets'=>$tickets,'total'=>$tot,'uuid'=>$uuid]);
  } catch (Throwable $e) {
    try { $db->rollback(); } catch (Throwable $e2) {}
    if (function_exists('facturas_log')) { facturas_log('REST_FACTURAMA_ERROR', ['modo'=>'global','factura_id'=>$fid,'tickets'=>$tickets,'error'=>$e->getMessage()]); }
    json_response(false, 'Error al timbrar factura global: '.$e->getMessage());
  }

} catch (Throwable $e) {
  if (isset($db) && $db->errno === 0) { /* noop */ }
  echo json_encode(['success'=>false,'mensaje'=>'Error: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
