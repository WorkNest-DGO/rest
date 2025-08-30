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
$connector = new WindowsPrintConnector("smb://FUED/pos58");
//$connector = new WindowsPrintConnector("smb://DESKTOP-O4CO4GV/58");
//$connector = new FilePrintConnector("php://stdout");
$printer = new Printer($connector);
$printer -> initialize();



//imprime corte

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
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$filename="../../archivos/logo_login2.png";	
	$logo = EscposImage::load($filename, true);
	$printer -> bitImage($logo);
	$printer -> feed();	
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
		$objeto = new item('Productos ' ,$cheque['productos'],True);
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
		$objeto = new item('Productos ' ,$boucher['productos'],True);
		$printer -> text($objeto->getAsString(32));
		$objeto = new item('Propina ' ,$datosT['total_propina_tarjeta'],True);
		$printer -> text($objeto->getAsString(32));
		$totalTar=$boucher['total']+$datosT['total_propina_tarjeta'];
		$objeto = new item('Total ' ,$totalTar,True);
		$printer -> text($objeto->getAsString(32));
		$printer -> feed();
		
	}

	$printer -> text(" \n");
	$printer -> feed();
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
