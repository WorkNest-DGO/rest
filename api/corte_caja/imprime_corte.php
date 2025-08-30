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

    public function __construct($name = '', $price = '', $dollarSign = false)
    {
        $this->name = $name;
        $this->price = $price;
        $this->dollarSign = $dollarSign;
   
    }

    public function getAsString($width = 48)
    {
        $rightCols = 10;
        $leftCols = $width - $rightCols;
        if ($this->dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $left = str_pad($this->name, $leftCols);

        $sign = ($this->dollarSign ? '$ ' : '');
        $right = str_pad($sign . $this->price  , $rightCols, ' ', STR_PAD_LEFT);
        return "$left$right\n";
    }

    public function __toString()
    {
        return $this->getAsString();
    }
}
class denominaBilletes
{
    private $valor;
    private $cantidad;
    private $dollarSign;

    public function __construct($valor = '', $cantidad = '', $dollarSign = false)
    {
        $this->valor = $valor;
        $this->cantidad = $cantidad;
        $this->dollarSign = $dollarSign;
   
    }

    public function getAsString($width = 48)
    {
        $rightCols = 10;
        $leftCols = $width - $rightCols;
        $totall = (float)$this->valor * (int)$this->cantidad;
        if ($this->dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $left = str_pad('$'.$this->valor.'  x', $leftCols);

        $sign = ($this->dollarSign ? '$ ' : '');        
        $right = str_pad($this->cantidad .'='  , $rightCols, ' ', STR_PAD_LEFT);
        $final = str_pad($sign .$totall, $rightCols , ' ', STR_PAD_LEFT);
        return "$left$right$final\n";
    }

    public function __toString()
    {
        return $this->getAsString();
    }
}


$datos = $_GET['datos'];
$datos2 =json_decode($datos,true);



//$connector = new WindowsPrintConnector("smb://ip_maquina/nombre_impresora");
$connector = new WindowsPrintConnector("smb://FUED/pos58");
//$connector = new WindowsPrintConnector("smb://DESKTOP-O4CO4GV/58");
//$connector = new FilePrintConnector("php://stdout");
$printer = new Printer($connector);
$printer -> initialize();



// //imprime corte

    // Normaliza: algunas APIs devuelven el payload dentro de "resultado"
    $datosT = $datos2['resultado'] ?? $datos2;

    // Aliases y defaults seguros
    $total_bruto      = $datosT['total_bruto'] ?? ($datosT['total_productos'] ?? 0);
    $__totalEsperado  = $datosT['total_esperado'] ?? ($datosT['totalEsperado'] ?? null);
    $total_descuentos = $datosT['total_descuentos'] ?? (
        ($__totalEsperado !== null) ? max(0, $total_bruto - $__totalEsperado) : 0
    );
    $total_esperado   = $__totalEsperado ?? max(0, $total_bruto - $total_descuentos);

    // Esperados por método (si no vienen, se omiten al imprimir)
    $esperado_efectivo = $datosT['esperado_efectivo'] ?? null;
    $esperado_boucher  = $datosT['esperado_boucher']  ?? null;
    $esperado_cheque   = $datosT['esperado_cheque']   ?? null;

    // Propinas (compat)
    $total_propina_efectivo = $datosT['total_propina_efectivo'] ?? 0;
    $total_propina_tarjeta  = $datosT['total_propina_tarjeta']  ?? 0;
    $total_propina_cheque   = $datosT['total_propina_cheque']   ?? 0;
    $total_propinas         = $datosT['total_propinas'] ?? ($total_propina_efectivo + $total_propina_tarjeta + $total_propina_cheque);

    // Folios: usa total_folios si viene; si no, calcula
    $total_folios = $datosT['total_folios'] ?? (
        (isset($datosT['folio_inicio'], $datosT['folio_fin']))
            ? ((int)$datosT['folio_fin'] - (int)$datosT['folio_inicio'] + 1)
            : null
    );
	
	$meseros=$datosT['total_meseros'];
	$repartidores = $datosT['total_repartidor'];
	$monedasBilletes = $datosT['desglose'];
	$pagosEfec =  array();
	$pagosBauch = array();
	$pagosCheq = array();
	$cheq = false;
	$bouch = false;
	$efectivo = null;
	$cheque = null;
	$boucher = null;

	$date = date("Y-m-d H:i:s");
	

	if(array_key_exists('efectivo', $datosT)){
		$efectivo=$datosT['efectivo'];
	}
	if(array_key_exists('cheque', $datosT)){
		$cheque=$datosT['cheque'];
	}
	if(array_key_exists('boucher', $datosT)){
		$boucher=$datosT['boucher'];
	}

	foreach ($monedasBilletes  as $mb) {
		if($mb['tipo_pago']=='efectivo'){
			$prod = new denominaBilletes( idDenominacionCambio($mb['denominacion_id']),$mb['cantidad'],True);	
			array_push($pagosEfec, $prod->getAsString(32));
		}
		if($mb['tipo_pago']=='cheque'){
			$objeto = new item('Cheque: ' ,$mb['cantidad'],True);
			array_push($pagosCheq, $prod->getAsString(32));
			$cheq =true;
		}
		if($mb['tipo_pago']=='boucher'){
			$objeto = new item('Boucher: ' ,$mb['cantidad'],True);
			array_push($pagosBauch, $prod->getAsString(32));
			$bouch =true;
		}
		
		
	}
// 	// $items3 = array();
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$filename="../../archivos/logo_login2.png";	
	$logo = EscposImage::load($filename, true);
	$printer -> bitImage($logo);

	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text("Corte / Cierre de caja \n");
	$printer -> feed();
	$printer -> selectPrintMode();
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$objeto = new item('Corte ID: ' ,$datosT['corte_id'],True);
	$printer -> text($objeto->getAsString(32));
	$printer -> text("Inicio: " . $datosT['fecha_inicio'] ."\n");
	$printer -> text("Fin: " . $date ."\n");
	$printer->text(
		"Folios: " . $datosT['folio_inicio'] . " - " . $datosT['folio_fin'] .
		($total_folios !== null ? " (" . $total_folios . ")" : "") . "\n"
	);
	$printer -> feed();


	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> text("Totales por forma de pago \n");
	$printer -> feed();

	if(!$efectivo==null){
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text("Efectivo\n");
		$printer -> setJustification(Printer::JUSTIFY_LEFT);
		$printer -> selectPrintMode();
		$objeto = new item('Productos: ' ,$efectivo['productos'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Propina ' ,$datosT['total_propina_efectivo'],True);
		$printer -> text($objeto->getAsString(32));	
		$totalEf=$efectivo['total']+$datosT['total_propina_efectivo'];
		$objeto = new item('Total ' ,$totalEf,True);
		$printer -> text($objeto->getAsString(32));	
		$printer -> feed();
	}
	if(!$cheque==null){
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text("Cheque\n");
		$printer -> setJustification(Printer::JUSTIFY_LEFT);
		$printer -> selectPrintMode();
		$objeto = new item('Productos: ' ,$cheque['productos'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Propina ' ,$datosT['total_propina_cheque'],True);
		$printer -> text($objeto->getAsString(32));
		$totalCh=$cheque['total']+$datosT['total_propina_cheque'];
		$objeto = new item('Total ' ,$totalCh,True);
		$printer -> text($objeto->getAsString(32));	
		$printer -> feed();		
	}
	if(!$boucher==null){
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text("Boucher\n");
		$printer -> setJustification(Printer::JUSTIFY_LEFT);
		$printer -> selectPrintMode();
		$objeto = new item('Productos: ' ,$boucher['productos'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Propina ' ,$datosT['total_propina_tarjeta'],True);
		$printer -> text($objeto->getAsString(32));
		$totalTar=$boucher['total']+$datosT['total_propina_tarjeta'];
		$objeto = new item('Total ' ,$totalTar,True);
		$printer -> text($objeto->getAsString(32));	
		$printer -> feed();
		
	}
	$diferencia = (float)$datosT['totalFinal'] - (float)$total_esperado;
	$diferencia = '' . $diferencia;

	$objeto = new item('Total productos: ' ,$datosT['total_productos'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Total propinas: ' ,$datosT['total_propinas'],True);
	$printer -> text($objeto->getAsString(32));
	// Agregados
	$objeto = new item('Total bruto: '      , $total_bruto, True);
	$printer->text($objeto->getAsString(32));
	$objeto = new item('Total descuentos: ' , $total_descuentos, True);
	$printer->text($objeto->getAsString(32));
	// Preferir total_esperado normalizado
	$objeto = new item('Total esperado: '   , $total_esperado, True);
	$printer -> text($objeto->getAsString(32));
	// Esperado por método (si aplica)
	if ($esperado_efectivo !== null) {
		$objeto = new item('Esperado efectivo: ' , $esperado_efectivo, True);
		$printer->text($objeto->getAsString(32));
	}
	if ($esperado_boucher !== null) {
		$objeto = new item('Esperado boucher: '  , $esperado_boucher, True);
		$printer->text($objeto->getAsString(32));
	}
	if ($esperado_cheque !== null) {
		$objeto = new item('Esperado cheque: '   , $esperado_cheque, True);
		$printer->text($objeto->getAsString(32));
	}
	$objeto = new item('Fondo inicial: ' ,$datosT['fondo'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Depósitos: ' ,$datosT['total_depositos'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Retiros: ' ,$datosT['total_retiros'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Conteo (Total final) ' ,$datosT['totalFinal'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('DIF (Conteo - Esperado): ' ,$diferencia,True);
	$printer -> text($objeto->getAsString(32));
	$printer -> text(" \n");
	$printer -> feed();

	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text("Ventas por Mesero\n");	
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$printer -> selectPrintMode();
	foreach($meseros as $mesero){
		$objeto = new item($mesero['nombre'] .':' ,$mesero['total'],True);
		$printer -> text($objeto->getAsString(32));
	}
	
	$objeto = new item('Ventas mostrador/rápido ..... : ' ,$datosT['total_rapido'],True);
	$printer -> text($objeto->getAsString(32));
	$printer -> text(" \n");
	$printer -> feed();

	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text("Repartidores \n");	
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$printer -> selectPrintMode();
	foreach($repartidores as $repartidor){
		$objeto = new item($repartidor['nombre'] ,$repartidor['total'],True);
		$printer -> text($objeto->getAsString(32));
	}
	$printer -> text(" \n");
	$printer -> feed();

	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text(" Desglose de denominaciones \n");	
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$printer -> text("[Efectivo] \n");	
	foreach($pagosEfec as $pagoEfe){
		$printer -> text($pagoEfe);		
	}
	$printer -> text(" \n");
	$printer -> feed();
	$printer -> text("[No Efectivo] \n");
	if($cheq==true){
		foreach($pagosCheq as $pagoCheq){
			$printer -> text($pagoCheq);		
		}
		$printer -> text(" \n");
		$printer -> feed();
	}
	
	if($bouch==true){
		foreach($pagosBauch as $pagoBauch){
			$printer -> text($pagoBauch);		
		}
		$printer -> text(" \n");
		$printer -> feed();
	}
	

	$printer -> text("Impreso: " . $date ."\n");
	$printer -> text(" \n");
	$printer -> feed();

	// /* Cut the receipt and open the cash drawer */
	$printer -> cut();

$printer -> close();
echo "enviado";
		function idDenominacionCambio($denominacionId){
			if ($denominacionId == 1) {
			   return "0.50" ;
			} elseif ($denominacionId == 2) {
			    return "1.00" ;
			} elseif ($denominacionId == 3) {
		   		return "2.00" ;
			} elseif ($denominacionId == 4) {
		   		return "5.00" ;
			} elseif ($denominacionId == 5) {
		   		return "10.00" ;
			} elseif ($denominacionId == 6) {
		   		return "20.00" ;
			} elseif ($denominacionId == 7) {
		   		return "50.00" ;
			} elseif ($denominacionId == 8) {
		   		return "100.00" ;
			} elseif ($denominacionId == 9) {
		   		return "200.00" ;
			} elseif ($denominacionId == 10) {
		   		return "500.00" ;
			} elseif ($denominacionId == 11) {
		   		return "1000.00" ;
			} elseif ($denominacionId == 12) {
		   		return "1.00" ;
			} elseif ($denominacionId == 13) {
		   		return "1.00" ;
			}

		}


/* A wrapper to do organise item names & prices into columns */




 ?>
