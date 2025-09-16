<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Método no permitido');
}

if (!isset($_SESSION['usuario_id'])) {
    error('Sesión no iniciada');
}

/*
 Parámetros soportados (GET):
  - q        : string (busca en nombre/descripcion)
  - tipo     : enum('monto_fijo','porcentaje','bogo','combo','categoria_gratis')
  - activo   : 0|1
  - visible  : 0|1  (visible_en_ticket)
  - pagina   : int (>=1)
  - limite   : int (1..200)
  - order_by : id|monto|creado_en|nombre|tipo|activo|visible
  - order_dir: asc|desc
*/

$pagina  = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$limite  = isset($_GET['limite'])  ? max(1, min(200, (int)$_GET['limite'])) : 20;
$offset  = ($pagina - 1) * $limite;

$q       = isset($_GET['q']) ? trim($_GET['q']) : '';
$tipo    = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$activo  = isset($_GET['activo']) ? $_GET['activo'] : '';
$visible = isset($_GET['visible']) ? $_GET['visible'] : '';

$allowedTipos = ['monto_fijo','porcentaje','bogo','combo','categoria_gratis'];
$allowedOrder = ['id','monto','creado_en','nombre','tipo','activo','visible'];
$order_by  = in_array($_GET['order_by'] ?? '', $allowedOrder, true) ? $_GET['order_by'] : 'id';
$order_dir = (strtolower($_GET['order_dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

// Mapeo a columnas reales
$columnMap = [
  'id'        => 'id',
  'monto'     => 'monto',
  'creado_en' => 'creado_en',
  'nombre'    => 'nombre',
  'tipo'      => 'tipo',
  'activo'    => 'activo',
  'visible'   => 'visible_en_ticket'
];

$where   = [];
$types   = '';
$params  = [];

// Búsqueda por texto (nombre/descripcion)
if ($q !== '') {
    $where[] = '(nombre LIKE ? OR descripcion LIKE ?)';
    $like = '%'.$q.'%';
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

// Filtro por tipo
if ($tipo !== '' && in_array($tipo, $allowedTipos, true)) {
    $where[] = 'tipo = ?';
    $types .= 's';
    $params[] = $tipo;
}

// Filtro por activo
if ($activo !== '' && ($activo === '0' || $activo === '1')) {
    $where[] = 'activo = ?';
    $types .= 'i';
    $params[] = (int)$activo;
}

// Filtro por visible_en_ticket
if ($visible !== '' && ($visible === '0' || $visible === '1')) {
    $where[] = 'visible_en_ticket = ?';
    $types .= 'i';
    $params[] = (int)$visible;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE '.implode(' AND ', $where);
}

/* =============== COUNT =============== */
$sqlCount = "SELECT COUNT(*) AS total
             FROM catalogo_promos
             $whereSql";
$stmt = $conn->prepare($sqlCount);
if (!$stmt) {
    error('Error preparando COUNT: '.$conn->error);
}
if ($types !== '') {
    $bind = [];
    $bind[] = & $types;
    foreach ($params as $k => $v) { $bind[] = & $params[$k]; }
    // Nota: bind_param requiere referencias
    call_user_func_array([$stmt, 'bind_param'], $bind);
}
if (!$stmt->execute()) {
    $stmt->close();
    error('Error ejecutando COUNT: '.$stmt->error);
}
$res   = $stmt->get_result();
$row   = $res->fetch_assoc();
$total = (int)($row['total'] ?? 0);
$stmt->close();

/* =============== SELECT DATA =============== */
$sql = "SELECT
          id,
          nombre AS motivo,                     -- alias para el front
          descripcion,
          tipo,
          monto,
          activo,
          visible_en_ticket AS visible,
          NULL AS prioridad,                    -- placeholders para columnas del front
          0 AS combinable,
          creado_en
        FROM catalogo_promos
        $whereSql
        ORDER BY {$columnMap[$order_by]} $order_dir
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error('Error preparando consulta: '.$conn->error);
}

// Agregar LIMIT/OFFSET
$types2  = $types.'ii';
$params2 = $params;
$params2[] = $limite;
$params2[] = $offset;

$bind2 = [];
$bind2[] = & $types2;
foreach ($params2 as $k => $v) { $bind2[] = & $params2[$k]; }
call_user_func_array([$stmt, 'bind_param'], $bind2);

if (!$stmt->execute()) {
    $stmt->close();
    error('Error ejecutando consulta: '.$stmt->error);
}

$datos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

success([
    'promos'  => $datos,
    'total'   => $total,
    'pagina'  => $pagina,
    'limite'  => $limite,
]);
