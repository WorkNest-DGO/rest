<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

function convertirNumero($num) {
    $unidades = ["", "uno", "dos", "tres", "cuatro", "cinco", "seis", "siete", "ocho", "nueve", "diez", "once", "doce", "trece", "catorce", "quince", "dieciséis", "diecisiete", "dieciocho", "diecinueve", "veinte"];
    $decenas = ["", "diez", "veinte", "treinta", "cuarenta", "cincuenta", "sesenta", "setenta", "ochenta", "noventa"];
    $centenas = ["", "ciento", "doscientos", "trescientos", "cuatrocientos", "quinientos", "seiscientos", "setecientos", "ochocientos", "novecientos"];
    if ($num == 0) return "cero";
    if ($num == 100) return "cien";
    $texto = "";
    if ($num >= 1000000) {
        $millones = floor($num / 1000000);
        $texto .= convertirNumero($millones) . " millones ";
        $num %= 1000000;
    }
    if ($num >= 1000) {
        $miles = floor($num / 1000);
        if ($miles == 1) $texto .= "mil ";
        else $texto .= convertirNumero($miles) . " mil ";
        $num %= 1000;
    }
    if ($num >= 100) {
        $cent = floor($num / 100);
        $texto .= $centenas[$cent] . " ";
        $num %= 100;
    }
    if ($num > 20) {
        $dec = floor($num / 10);
        $texto .= $decenas[$dec];
        $num %= 10;
        if ($num) $texto .= " y " . $unidades[$num];
    } elseif ($num > 0) {
        $texto .= $unidades[$num];
    }
    return trim($texto);
}

function numeroALetras($numero) {
    $entero = floor($numero);
    $decimal = round(($numero - $entero) * 100);
    $decimal = str_pad($decimal, 2, '0', STR_PAD_LEFT);
    $letras = convertirNumero($entero);
    return ucfirst(trim($letras)) . " pesos {$decimal}/100 M.N.";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || (!isset($input['folio']) && !isset($input['venta_id']))) {
    error('Se requiere folio o venta_id');
}

if (isset($input['folio'])) {
    $cond = 't.folio = ?';
    $param = (int)$input['folio'];
} else {
    $cond = 't.venta_id = ?';
    $param = (int)$input['venta_id'];
}

$stmt = $conn->prepare("SELECT t.id, t.folio, t.total, t.propina, t.fecha, t.venta_id,
                                t.mesa_nombre, t.mesero_nombre, t.fecha_inicio, t.fecha_fin,
                                t.tiempo_servicio, t.nombre_negocio, t.direccion_negocio,
                                t.rfc_negocio, t.telefono_negocio, t.sede_id,
                                t.tipo_pago, t.monto_recibido,
                                t.tarjeta_marca_id, tm.descripcion AS tarjeta_marca,
                                t.tarjeta_banco_id, cb.descripcion AS tarjeta_banco,
                                t.boucher,
                                t.cheque_numero, t.cheque_banco_id, cb2.descripcion AS cheque_banco,
                                v.tipo_entrega
                         FROM tickets t
                         LEFT JOIN catalogo_tarjetas tm ON t.tarjeta_marca_id = tm.id
                         LEFT JOIN catalogo_bancos cb ON t.tarjeta_banco_id = cb.id
                         LEFT JOIN catalogo_bancos cb2 ON t.cheque_banco_id = cb2.id
                         LEFT JOIN ventas v ON t.venta_id = v.id
                         WHERE $cond");
if (!$stmt) {
    error('Error al preparar consulta: ' . $conn->error);
}
$stmt->bind_param('i', $param);
if (!$stmt->execute()) {
    $stmt->close();
    error('Error al ejecutar consulta: ' . $stmt->error);
}
$res = $stmt->get_result();
$tickets = [];
while ($t = $res->fetch_assoc()) {
    $det = $conn->prepare("SELECT p.nombre, d.cantidad, d.precio_unitario,
                                 (d.cantidad * d.precio_unitario) AS subtotal
                           FROM ticket_detalles d
                           JOIN productos p ON d.producto_id = p.id
                           WHERE d.ticket_id = ?");
    if (!$det) {
        $stmt->close();
        error('Error al preparar detalle: ' . $conn->error);
    }
    $det->bind_param('i', $t['id']);
    if (!$det->execute()) {
        $det->close();
        $stmt->close();
        error('Error al obtener detalle: ' . $det->error);
    }
    $dres = $det->get_result();
    $prods = [];
    while ($p = $dres->fetch_assoc()) {
        $prods[] = $p;
    }
    $det->close();

    $mesa_nombre      = $t['mesa_nombre']      ?? 'N/A';
    $mesero_nombre    = $t['mesero_nombre']    ?? 'N/A';
    $fecha_inicio     = $t['fecha_inicio']     ?? 'N/A';
    $fecha_fin        = $t['fecha_fin']        ?? 'N/A';
    $tiempo_servicio  = $t['tiempo_servicio']  ?? 'N/A';
    $nombre_negocio   = $t['nombre_negocio']   ?? 'N/A';
    $direccion_negocio= $t['direccion_negocio']?? 'N/A';
    $rfc_negocio      = $t['rfc_negocio']      ?? 'N/A';
    $telefono_negocio = $t['telefono_negocio'] ?? 'N/A';
    $tipo_pago        = $t['tipo_pago']        ?? 'N/A';
    $tipo_entrega     = $t['tipo_entrega']     ?? 'N/A';
    $cambio           = max(0, ($t['monto_recibido'] ?? 0) - ($t['total'] ?? 0));
      $tickets[] = [
          'ticket_id'        => (int)$t['id'],
          'folio'            => (int)$t['folio'],
          'fecha'            => $t['fecha'] ?? 'N/A',
          'venta_id'         => (int)$t['venta_id'],
          'propina'          => (float)$t['propina'],
          'total'            => (float)$t['total'],
          'mesa_nombre'      => $mesa_nombre,
          'mesero_nombre'    => $mesero_nombre,
          'fecha_inicio'     => $fecha_inicio,
          'fecha_fin'        => $fecha_fin,
          'tiempo_servicio'  => $tiempo_servicio,
          'nombre_negocio'   => $nombre_negocio,
          'direccion_negocio'=> $direccion_negocio,
          'rfc_negocio'      => $rfc_negocio,
          'telefono_negocio' => $telefono_negocio,
          'tipo_pago'        => $tipo_pago,
          'tarjeta_marca'    => $t['tarjeta_marca'] ?? null,
          'tarjeta_banco'    => $t['tarjeta_banco'] ?? null,
          'boucher'          => $t['boucher'] ?? null,
          'cheque_numero'    => $t['cheque_numero'] ?? null,
          'cheque_banco'     => $t['cheque_banco'] ?? null,
          'tarjeta'          => [
              'marca'   => $t['tarjeta_marca'] ?? null,
              'banco'   => $t['tarjeta_banco'] ?? null,
              'boucher' => $t['boucher'] ?? null
          ],
          'cheque'           => [
              'numero' => $t['cheque_numero'] ?? null,
              'banco'  => $t['cheque_banco'] ?? null
          ],
          'tipo_entrega'     => $tipo_entrega,
          'cambio'           => (float)$cambio,
          'total_letras'     => numeroALetras($t['total']),
          'logo_url'         => '../../utils/logo.png',
          'sede_id'          => isset($t['sede_id']) && !empty($t['sede_id']) ? (int)$t['sede_id'] : 1,
          'productos'        => $prods
      ];
}
$stmt->close();

success(['tickets' => $tickets]);
?>
