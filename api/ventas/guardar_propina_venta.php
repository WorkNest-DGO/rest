<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['venta_id'])) {
    error('Datos incompletos');
}

$venta_id   = (int)$input['venta_id'];
$propinaEfectivo = isset($input['propina_efectivo']) && $input['propina_efectivo'] !== '' ? (float)$input['propina_efectivo'] : 0.00;
$propinaCheque   = isset($input['propina_cheque']) && $input['propina_cheque'] !== '' ? (float)$input['propina_cheque']   : 0.00;
$propinaTarjeta  = isset($input['propina_tarjeta']) && $input['propina_tarjeta'] !== '' ? (float)$input['propina_tarjeta']  : 0.00;




$conn->begin_transaction();



 
   


$cerrar = $conn->prepare("UPDATE ventas SET propina_efectivo = ?, propina_cheque = ? , propina_tarjeta = ? WHERE id = ?");
if (!$cerrar) {
    $conn->rollback();
    error('Error al preparar cierre de venta: ' . $conn->error);
}
$cerrar->bind_param('dddi',$propinaEfectivo,$propinaCheque,$propinaTarjeta, $venta_id);
if (!$cerrar->execute()) {
    $conn->rollback();
    error('Error al cerrar venta: ' . $cerrar->error);
}

$conn->commit();

 $ticketsResp[] = [
        'mensaje' => 'guardado exitoso'
    ];
success(['tickets' => $ticketsResp]);
?>
