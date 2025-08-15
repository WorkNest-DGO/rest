<?php 
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/db.php';
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
//$connector = new WindowsPrintConnector("smb://FUED/80");
$connector = new FilePrintConnector("php://stdout");
$printer = new Printer($connector);
$printer -> initialize();

$ventaId= $_GET['venta_id'];
$elementos = obtenerDatos($ventaId,$conn);
$desglose = array();
$datosT = $elementos[0];

$recibos = $elementos;
//var_dump($recibos);

foreach ($recibos  as $recibo) {

	$datosT = $recibo;
	$desglose=$recibo['productos'];
	

	$items3 = array();
	   
	foreach ($desglose  as $produc) {

		$prod = new item($produc['nombre'],$produc['precio_unitario'],True,$produc['cantidad'],$produc['subtotal']);	
		array_push($items3, $prod->getAsString(32));
	}
	    
	$filename="../../archivos/logo.png";



	// /* Start the printer */
	$logo = EscposImage::load($filename, false);


	// /* Print top logo */
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> graphics($logo);

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
	$printer -> text("Propina: " . $datosT['propina'] ."\n");
	$printer -> text("Cambio: " . $datosT['cambio'] ."\n");
	$printer -> text("Total: " . $datosT['total'] ."\n");
	$printer -> text($datosT['total_letras'] ."\n");
	$printer -> feed();
	$printer -> text("Gracias por su compra \n");
	$printer -> feed();


	// /* Cut the receipt and open the cash drawer */
	$printer -> cut();
}
$printer -> close();
echo "enviado";
		

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
	$stmt = $conn->prepare("SELECT t.id, t.folio, t.total, t.propina, t.fecha, t.venta_id,
                                t.mesa_nombre, t.mesero_nombre, t.fecha_inicio, t.fecha_fin,
                                t.tiempo_servicio, t.nombre_negocio, t.direccion_negocio,
                                t.rfc_negocio, t.telefono_negocio, t.sede_id,
                                t.tipo_pago, t.monto_recibido,
                                tm.nombre AS tarjeta,
                                b1.nombre AS banco_tarjeta,
                                t.boucher,
                                b2.nombre AS banco_cheque,
                                t.cheque_numero,
                                v.tipo_entrega
                         FROM tickets t
                         LEFT JOIN catalogo_tarjetas tm ON tm.id = t.tarjeta_marca_id
                         LEFT JOIN catalogo_bancos b1 ON b1.id = t.tarjeta_banco_id
                         LEFT JOIN catalogo_bancos b2 ON b2.id = t.cheque_banco_id
                         LEFT JOIN ventas v ON t.venta_id = v.id
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
