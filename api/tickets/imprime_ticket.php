<?php 
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../impresoras/helpers.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\GdEscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
class item
{
    private $name;
    private $price;
    private $dollarSign;

    public function __construct($name = '', $price = '', $dollarSign = false,$cantidad='',$subtotal='')
    {
        $this->name = $name;
        $this->cantidad = $cantidad;
        $this->price = $price;
        $this->dollarSign = $dollarSign;
        $this->subtotal = $subtotal;
    }

    public function getAsString($width = 48)
    {
        $rightCols = 10;
        $leftCols = $width - $rightCols;
        if ($this->dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $left = str_pad($this->cantidad . 'x ' . $this->name, $leftCols);

        $sign = ($this->dollarSign ? '$ ' : '');
        $right = str_pad($sign . $this->price  , $rightCols, ' ', STR_PAD_LEFT);
        $final = str_pad($sign .$this->subtotal, $rightCols , ' ', STR_PAD_LEFT);
        return "$left$right$final\n";
    }

    public function __toString()
    {
        return $this->getAsString();
    }
}
//$connector = new WindowsPrintConnector("smb://ip_maquina/nombre_impresora");
$printId = isset($_GET['print_id']) ? (int)$_GET['print_id'] : null;
$printerIp = obtener_impresora_ip($conn, $printId);
$connector = new WindowsPrintConnector($printerIp);
//$connector = new FilePrintConnector("php://stdout");
$printer = new Printer($connector);
$printer -> initialize();

$ventaId= $_GET['venta_id'];
$elementos = obtenerDatos($ventaId,$conn);
$desglose = array();
$datosT = $elementos[0];
$promocion_descuento2 = $datosT['promocion_descuento'];
$promocion_nombre2    = isset($datosT['promocion_nombre']) ? $datosT['promocion_nombre'] : '';
$recibos = $elementos;
//var_dump($recibos);

$error_msg = null;
try { foreach ($recibos  as $recibo) {

	$datosT = $recibo;
	$desglose=$recibo['productos'];

	// === Utilidades locales ===
	$fmt = function($n){ return number_format((float)$n, 2, '.', ','); };
	$ticketId = isset($datosT['ticket_id']) ? (int)$datosT['ticket_id'] : 0;

	// Total bruto desde los detalles impresos del ticket
	$totalBruto = 0.0;
	if (is_array($desglose)) {
		foreach ($desglose as $produc) { $totalBruto += (float)($produc['subtotal'] ?? 0); }
	}

	// Descuentos del ticket + detalle de producto si aplica
	$descuentos = [];
	$descuentoTotal = 0.0;
	if ($ticketId > 0) {
		if ($q = $conn->prepare("SELECT td.tipo, td.porcentaje, td.monto, td.motivo, td.venta_detalle_id,
		                               vd.cantidad, vd.precio_unitario, p.nombre AS producto
		                        FROM ticket_descuentos td
		                        LEFT JOIN venta_detalles vd ON vd.id = td.venta_detalle_id
		                        LEFT JOIN productos p ON p.id = vd.producto_id
		                        WHERE td.ticket_id = ?
		                        ORDER BY td.id ASC")) {
			$q->bind_param('i', $ticketId);
			if ($q->execute()) {
				$resD = $q->get_result();
				while ($row = $resD->fetch_assoc()) { $descuentos[] = $row; $descuentoTotal += (float)($row['monto'] ?? 0); }
			}
			$q->close();
		}
		// Fallback si no hay filas pero el ticket trae descuento acumulado
		if (empty($descuentos)) {
			$ticketDescuentoCampo = 0.0;
			if ($q2 = $conn->prepare('SELECT descuento FROM tickets WHERE id = ?')) {
				$q2->bind_param('i', $ticketId);
				if ($q2->execute()) { $r2 = $q2->get_result()->fetch_assoc(); $ticketDescuentoCampo = (float)($r2['descuento'] ?? 0); }
				$q2->close();
			}
			if ($ticketDescuentoCampo > 0) {
				$descuentos[] = ['tipo'=>'monto_fijo','porcentaje'=>null,'monto'=>$ticketDescuentoCampo,'motivo'=>null,'venta_detalle_id'=>null,'cantidad'=>null,'precio_unitario'=>null,'producto'=>null];
				$descuentoTotal = $ticketDescuentoCampo;
			}
		}
	}

	// Total final a pagar
	$totalAPagar = max(0, $totalBruto - $descuentoTotal- $promocion_descuento2);
	

	$items3 = array();
	   
	foreach ($desglose  as $produc) {

		$prod = new item($produc['nombre'],$produc['precio_unitario'],True,$produc['cantidad'],$produc['subtotal']);	
		array_push($items3, $prod->getAsString(32));
	}
	    
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$filename="../../archivos/logo_login.png";	
	$logo = EscposImage::load($filename, true);
	$printer -> bitImage($logo);





	// /* Name of shop */
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text($datosT['nombre_negocio'] ."\n");
	$printer -> selectPrintMode();
	$printer -> text($datosT['direccion_negocio'] ."\n");
	$printer -> text($datosT['rfc_negocio'] ."\n");
	$printer -> text($datosT['telefono_negocio'] ."\n");
	$printer -> text($datosT['fecha'] ."\n");
	$printer -> feed();

	// /* Title of receipt */
	$printer -> setEmphasis(true);
	$printer -> text("Folio: " . $datosT['folio'] ."\n");
	$printer -> text("Venta: " . $datosT['venta_id'] ."\n");
	$printer -> text("Sede: " . $datosT['sede_id'] ."\n");
	$printer -> text("Mesa: " . $datosT['mesa_nombre'] ."\n");
	$printer -> text("Mesero: " . $datosT['mesero_nombre'] ."\n");
	$printer -> text("Tipo entrega: " . $datosT['tipo_entrega'] ."\n");
	$printer -> text("Tipo pago: " . $datosT['tipo_pago'] ."\n");
	$printer -> text("Inicio: " . $datosT['fecha_inicio'] ."\n");
	$printer -> text("Fin: " . $datosT['fecha_fin'] ."\n");
	$printer -> text("Tiempo: " . $datosT['tiempo_servicio'] ."\n");

	$printer -> setEmphasis(false);

	// /* Items */

	foreach ($items3 as $item2) {
	    $printer -> text($item2);
	}

	$printer -> feed();
	$printer -> setEmphasis(true);
	// ----- Subtotal -----
	$printer->text(str_pad('Subtotal:', 20) . '$ ' . $fmt($totalBruto) . "\n");

	// ----- Descuentos (si hay) -----
	if (!empty($descuentos)) {
		$printer->text("------------------------------\n");
		$printer->text("DESCUENTOS APLICADOS\n");
		foreach ($descuentos as $d) {
			$linea = '';
			$tipo = $d['tipo'] ?? '';
			if ($tipo === 'cortesia') {
				$prod = !empty($d['producto']) ? $d['producto'] : 'Producto';
				$cant = !empty($d['cantidad']) ? (' x' . (int)$d['cantidad']) : '';
				$linea = "Cortesí­a: {$prod}{$cant}";
			} elseif ($tipo === 'porcentaje') {
				$porc  = ($d['porcentaje'] !== null) ? $fmt($d['porcentaje']) : '0';
				$linea = "Descuento {$porc}%";
			} else {
				$linea = "Descuento monto fijo";
			}
			$importe = '$ ' . $fmt($d['monto'] ?? 0);
			$texto  = function_exists('mb_substr') ? mb_substr($linea, 0, 30) : substr($linea, 0, 30);
			$lenT   = function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
			$printer->text($texto . str_repeat(' ', max(1, 32 - $lenT - strlen($importe))) . $importe . "\n");
			if (!empty($d['motivo'])) { $printer->text("Motivo: " . $d['motivo'] . "\n"); }
		}
		$printer->text(str_pad('Total descuento:', 20) . "-$ " . $fmt($descuentoTotal) . "\n");
	}
	// Promociones aplicadas (por venta). Imprime leyenda y, debajo, el/los nombres.
	if ($promocion_descuento2 > 0 || !empty($promocion_nombre2)) {
		$printer->text("------------------------------\n");
		$printer->text("PROMOCIONES APLICADAS\n");
		if ($promocion_descuento2 > 0) {
			$printer->text("Acumulado Promociones : " . $promocion_descuento2 . "\n");
		}
		if (!empty($promocion_nombre2)) {
			// Imprimir nombre de la promoción asociada a la venta
			$printer->text("- " . $promocion_nombre2 . "\n");
		}
	}

	// ----- Total a pagar -----
	$printer -> text(str_pad('Total:', 20) . '$ ' . $fmt($totalAPagar) . "\n");
	// Mantener campos existentes
	$printer -> text("Cambio: " . $datosT['cambio'] ."\n");
	$printer -> text($datosT['total_letras'] ."\n");
	$printer -> feed();
	// Leyendas adicionales
	$printer->text("\nPara facturación visita nuestro sitio\n");
	$printer->text("https://tokyosushiprime.com/tokyo/vistas/facturacion.php\n");
	//$printer->text("------------------------------\n");
	//$printer->text("Obtén un descuento en tu próxima compra.\n");
	//$printer->text("Contesta una encuesta de satisfacción en\nnuestro sitio y obtén una sorpresa en tu\npróxima visita (aplica en todos nuestros\nrestaurantes).\n");
	//$printer->text("Entra a:\nhttps://tokyosushiprime.com/tokyo/vistas/encuesta.php\n");

	$printer -> text("Gracias por su compra \n");
	$printer -> feed();


	// /* Cut the receipt and open the cash drawer */
	$printer -> cut();
}
} catch (Throwable $e) {
    $error_msg = $e->getMessage();
}
$printer -> close();
// Mostrar modal de confirmación y regresar a ventas
header('Content-Type: text/html; charset=utf-8');
$ok = true;
$msg = 'Ticket enviado a la impresora';
echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Imprimir ticket</title><style>body{margin:0;background:#f1f5f9;font-family:ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}.overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center}.modal{width:min(520px,90vw);background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.25);overflow:hidden}.hdr{padding:14px 18px;color:#fff;font-weight:800;background:#16a34a}.cnt{padding:18px;color:#0f172a}.ftr{padding:14px 18px;background:#f8fafc;display:flex;justify-content:flex-end}button{padding:10px 16px;border:0;border-radius:10px;font-weight:800;cursor:pointer;color:#fff;background:#0ea5e9}</style></head><body>';
echo '<div class="overlay"><div class="modal"><div class="hdr">Enviado</div><div class="cnt"><div style="font-size:1.1rem;font-weight:800;margin-bottom:6px">' . $msg . '</div></div><div class="ftr"><button id="btnOk">Aceptar</button></div></div></div>';
echo '<script>(function(){var btn=document.getElementById("btnOk");function go(){var url="../../vistas/ventas/ventas.php";try{if(window.opener&&!window.opener.closed){window.opener.location.href=url;window.close();return;}}catch(e){}window.location.href=url;}if(btn)btn.addEventListener("click",go);document.addEventListener("keydown",function(e){if(e.key==="Enter")go();});})();</script></body></html>';
		

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
function obtenerDatos($ventaId,$conn){
	 $cond = 't.venta_id = ?';
    $stmt = $conn->prepare("SELECT t.id, t.folio, t.total, (v.propina_efectivo + v.propina_cheque + v.propina_tarjeta) as propina , v.promocion_descuento, v.promocion_id, t.fecha, t.venta_id,
                                t.mesa_nombre, t.mesero_nombre, t.fecha_inicio, t.fecha_fin,
                                t.tiempo_servicio, t.nombre_negocio, t.direccion_negocio,
                                t.rfc_negocio, t.telefono_negocio, t.sede_id,
                                t.tipo_pago, t.monto_recibido,
                                tm.nombre AS tarjeta,
                                b1.nombre AS banco_tarjeta,
                                t.boucher,
                                b2.nombre AS banco_cheque,
                                t.cheque_numero,
                                v.tipo_entrega,
                                cp.nombre AS promocion_nombre
                         FROM tickets t
                         LEFT JOIN catalogo_tarjetas tm ON tm.id = t.tarjeta_marca_id
                         LEFT JOIN catalogo_bancos b1 ON b1.id = t.tarjeta_banco_id
                         LEFT JOIN catalogo_bancos b2 ON b2.id = t.cheque_banco_id
                         LEFT JOIN ventas v ON t.venta_id = v.id
                         LEFT JOIN catalogo_promos cp ON cp.id = v.promocion_id
                         WHERE $cond");
	if (!$stmt) {
	    error('Error al preparar consulta: ' . $conn->error);
	}
	$stmt->bind_param('i', $ventaId);
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
          'promocion_descuento'          => (float)$t['promocion_descuento'],
          'promocion_id'        => isset($t['promocion_id']) ? (int)$t['promocion_id'] : null,
          'promocion_nombre'    => $t['promocion_nombre'] ?? null,
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
	          'tarjeta'          => $t['tarjeta'] ?? null,
	          'banco_tarjeta'    => $t['banco_tarjeta'] ?? null,
	          'boucher'          => $t['boucher'] ?? null,
	          'banco_cheque'     => $t['banco_cheque'] ?? null,
	          'cheque_numero'    => $t['cheque_numero'] ?? null,
	          'tipo_entrega'     => $tipo_entrega,
	          'cambio'           => (float)$cambio,
	          'total_letras'     => numeroALetras($t['total']),
	          'logo_url'         => '../../utils/logo.png',
	          'sede_id'          => isset($t['sede_id']) && !empty($t['sede_id']) ? (int)$t['sede_id'] : 1,
	          'productos'        => $prods
	      ];
	}
	return $tickets;
}

/* A wrapper to do organise item names & prices into columns */





 ?>



