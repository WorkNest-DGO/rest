<?php
// /rest/api/cocina/detalles_por_ids.php
// Devuelve el detalle completo de productos de cocina para los IDs solicitados.
// Estructura de salida compatible con listar_productos_cocina.php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if (!defined('ENVIO_CASA_PRODUCT_ID')) define('ENVIO_CASA_PRODUCT_ID', 9001);
if (!defined('CARGO_PLATAFORMA_PRODUCT_ID')) define('CARGO_PLATAFORMA_PRODUCT_ID', 9000);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$ids = isset($payload['ids']) && is_array($payload['ids'])
  ? array_values(array_unique(array_map('intval', $payload['ids'])))
  : [];

if (!$ids) {
    echo json_encode(['ok' => true, 'data' => []]);
    exit;
}

// Aplicar filtros por rol (barra vs alimentos) de forma equivalente a listar_productos_cocina
$rol = $_SESSION['rol'] ?? ($_SESSION['usuario']['rol'] ?? null);

$place = implode(',', array_fill(0, count($ids), '?'));

$sql = "SELECT
  d.id                          AS detalle_id,
  v.id                          AS venta_id,
  v.fecha                       AS fecha_venta,
  v.tipo_entrega,
  CASE
    WHEN v.tipo_entrega = 'mesa' THEN
      CONCAT(
        m.nombre,
        ' (',
        COALESCE(ca.nombre, 'Sin área'),
        ')'
      )
    WHEN v.tipo_entrega = 'domicilio' THEN CONCAT('Domicilio - ', r.nombre)
    ELSE 'Venta rápida'
  END                           AS destino,

  p.id                          AS producto_id,
  p.nombre                      AS producto,
  cat.nombre                    AS categoria,
  d.cantidad,
  d.precio_unitario,
  d.subtotal,
  d.insumos_descargados,

  d.created_at                  AS hora,
  d.entregado_hr,
  TIMESTAMPDIFF(MINUTE, d.created_at, NOW()) AS minutos_transcurridos,
  d.estado_producto             AS estado,

  d.observaciones,
  v.observacion                 AS observaciones_venta,

  u.nombre                      AS mesero,
  cu.nombre                     AS cajero,
  s.nombre                      AS sede,

  v.estado_entrega,
  v.seudonimo_entrega,
  r.telefono                    AS repartidor_telefono,
  (
    SELECT GROUP_CONCAT(
             CONCAT(i.nombre, ' ',
                    ROUND(r2.cantidad * d.cantidad, 2), ' ',
                    COALESCE(i.unidad, ''))
             ORDER BY i.nombre
             SEPARATOR ' | '
           )
    FROM recetas r2
    JOIN insumos i ON i.id = r2.insumo_id
    WHERE r2.producto_id = d.producto_id
  )                              AS insumos_requeridos,

  CASE
    WHEN d.estado_producto IN ('pendiente','en_preparacion')
         AND TIMESTAMPDIFF(MINUTE, d.created_at, NOW()) >= 20
      THEN 'alta'
    WHEN d.estado_producto IN ('pendiente','en_preparacion')
         AND TIMESTAMPDIFF(MINUTE, d.created_at, NOW()) BETWEEN 10 AND 19
      THEN 'media'
    ELSE 'normal'
  END                            AS prioridad

FROM venta_detalles d
JOIN ventas v            ON v.id = d.venta_id
LEFT JOIN mesas m        ON m.id = v.mesa_id
LEFT JOIN catalogo_areas ca ON ca.id = m.area_id
LEFT JOIN repartidores r ON r.id = v.repartidor_id
JOIN productos p         ON p.id = d.producto_id
LEFT JOIN catalogo_categorias cat ON cat.id = p.categoria_id
LEFT JOIN usuarios u     ON u.id = v.usuario_id
LEFT JOIN usuarios cu    ON cu.id = v.cajero_id
LEFT JOIN sedes s        ON s.id = v.sede_id
WHERE d.id IN ($place)";

// Filtros de rol
if ($rol === 'barra') {
    $sql .= " AND p.categoria_id = 1";
} elseif ($rol === 'alimentos') {
    $sql .= " AND p.categoria_id <> 1";
}

// Excluir ENVIO/CARGO
$sql .= " AND d.producto_id <> " . (int)ENVIO_CASA_PRODUCT_ID;
$sql .= " AND d.producto_id <> " . (int)CARGO_PLATAFORMA_PRODUCT_ID;

// Orden consistente (opcional)
$sql .= " ORDER BY FIELD(d.estado_producto, 'pendiente','en_preparacion','listo','entregado'), d.created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['ok'=>false,'msg'=>'Error al ejecutar consulta']);
        exit;
    }
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        // Salvaguarda adicional por si algún ID corresponde a ENVIO/CARGO
        if ((int)$row['producto_id'] === (int)ENVIO_CASA_PRODUCT_ID) { continue; }
        if ((int)$row['producto_id'] === (int)CARGO_PLATAFORMA_PRODUCT_ID) { continue; }
        $out[] = [
            'venta_id'             => (int)$row['venta_id'],
            'tipo'                 => $row['tipo_entrega'],
            'destino'              => $row['destino'],
            'producto_id'          => (int)$row['producto_id'],
            'producto'             => $row['producto'],
            'categoria'            => isset($row['categoria']) ? $row['categoria'] : null,
            'cantidad'             => (float)$row['cantidad'],
            'precio_unitario'      => (float)$row['precio_unitario'],
            'subtotal'             => (float)$row['subtotal'],
            'insumos_descargados'  => (int)$row['insumos_descargados'],
            'hora'                 => $row['hora'],
            'entregado_hr'         => $row['entregado_hr'],
            'minutos_transcurridos'=> (int)$row['minutos_transcurridos'],
            'estado'               => $row['estado'],
            'observaciones'        => $row['observaciones'],
            'observaciones_venta'  => $row['observaciones_venta'],
            'mesero'               => $row['mesero'],
            'cajero'               => $row['cajero'],
            'sede'                 => $row['sede'],
            'estado_entrega'       => $row['estado_entrega'],
            'seudonimo_entrega'    => $row['seudonimo_entrega'],
            'repartidor_telefono'  => $row['repartidor_telefono'],
            'insumos_requeridos'   => $row['insumos_requeridos'],
            'prioridad'            => $row['prioridad'],
            'detalle_id'           => (int)$row['detalle_id']
        ];
    }
    $stmt->close();
    echo json_encode(['ok'=>true,'data'=>$out]);
    exit;
}

http_response_code(500);
echo json_encode(['ok'=>false,'msg'=>'Error preparando consulta']);

?>

