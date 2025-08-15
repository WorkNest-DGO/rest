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

$datos = $_GET['datos'];
$datos2 =json_decode($datos,true);



//$connector = new WindowsPrintConnector("smb://ip_maquina/nombre_impresora");
//$connector = new WindowsPrintConnector("smb://FUED/80");
$connector = new FilePrintConnector("php://stdout");
$printer = new Printer($connector);
$printer -> initialize();



//imprime corte

	$datosT = $datos2;
	
	$meseros=$datosT['total_meseros'];
	$repartidores = $datosT['total_repartidor'];
	$efectivo = null;
	$cheque = null;
	$boucher = null;


	if(array_key_exists('efectivo', $datosT)){
		$efectivo=$datosT['efectivo'];
	}
	if(array_key_exists('cheque', $datosT)){
		$cheque=$datosT['cheque'];
	}
	if(array_key_exists('boucher', $datosT)){
		$boucher=$datosT['boucher'];
	}
	// $items3 = array();
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$filename="../../archivos/logo.png";	
	$logo = EscposImage::load($filename, false);
	$printer -> graphics($logo);

	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text("Corte de Caja Temporal \n");
	if(!$efectivo==null){
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text("Efectivo\n");
		$printer -> setJustification(Printer::JUSTIFY_LEFT);
		$printer -> selectPrintMode();
		$objeto = new item('Productos ' ,$efectivo['productos'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Propina ' ,$efectivo['propina'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Total ' ,$efectivo['total'],True);
		$printer -> text($objeto->getAsString(32));
		$printer -> feed();
	}
	if(!$cheque==null){
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text("Cheque\n");
		$printer -> setJustification(Printer::JUSTIFY_LEFT);
		$printer -> selectPrintMode();
		$objeto = new item('Productos ' ,$cheque['productos'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Propina ' ,$cheque['propina'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Total ' ,$cheque['total'],True);
		$printer -> text($objeto->getAsString(32));
		$printer -> feed();		
	}
	if(!$boucher==null){
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text("Boucher\n");
		$printer -> setJustification(Printer::JUSTIFY_LEFT);
		$printer -> selectPrintMode();
		$objeto = new item('Productos ' ,$boucher['productos'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Propina ' ,$boucher['propina'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Total ' ,$boucher['total'],True);
		$printer -> text($objeto->getAsString(32));
		$printer -> feed();
		
	}

	$printer -> text(" \n");
	$printer -> feed();
	$objeto = new item('Total_Productos ' ,$datosT['total_productos'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Total_Propinas ' ,$datosT['total_propinas'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Total_Esperado ' ,$datosT['totalEsperado'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Fondo ' ,$datosT['fondo'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Total_Depositos ' ,$datosT['total_depositos'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Total_Retiros ' ,$datosT['total_retiros'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Total_Final ' ,$datosT['totalFinal'],True);
	$printer -> text($objeto->getAsString(32));
	$objeto = new item('Numero_Corte ' ,$datosT['corte_id'],True);
	$printer -> text($objeto->getAsString(32));
	$printer -> text(" \n");
	$printer -> feed();

	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text("Total por Mesero\n");	
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$printer -> selectPrintMode();
	foreach($meseros as $mesero){
		$objeto = new item($mesero['nombre'] ,$mesero['total'],True);
		$printer -> text($objeto->getAsString(32));
	}
	
	$objeto = new item('Total_Rapido ' ,$datosT['total_rapido'],True);
	$printer -> text($objeto->getAsString(32));
	$printer -> text(" \n");
	$printer -> feed();

	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text("Total por Repartidor\n");	
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$printer -> selectPrintMode();
	foreach($repartidores as $repartidor){
		$objeto = new item($repartidor['nombre'] ,$repartidor['total'],True);
		$printer -> text($objeto->getAsString(32));
	}
	$printer -> text(" \n");
	$printer -> feed();
	$printer -> text("Fecha_Inicio: " . $datosT['fecha_inicio'] ."\n");
	$printer -> text("Folio Inicial: " . $datosT['folio_inicio'] ."\n");
	$printer -> text("Folio Final: " . $datosT['folio_fin'] ."\n");
	$printer -> text("Total de Folios: " . $datosT['total_folios'] ."\n");



	// /* Cut the receipt and open the cash drawer */
	$printer -> cut();

$printer -> close();
echo "enviado";
		


/* A wrapper to do organise item names & prices into columns */




 ?>
