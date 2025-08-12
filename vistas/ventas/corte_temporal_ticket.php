<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

$corte_id = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : ($_SESSION['corte_id'] ?? null);
if (!$corte_id) {
    die('Corte no especificado');
}

// Datos del negocio
$sede_id = $_SESSION['sede_id'] ?? 1;
$stmt = $conn->prepare('SELECT nombre, direccion, rfc, telefono FROM sedes WHERE id = ?');
$stmt->bind_param('i', $sede_id);
$stmt->execute();
$negocio = $stmt->get_result()->fetch_assoc();
$stmt->close();
$nombreNegocio = $negocio['nombre'] ?? '';
$direccionNegocio = $negocio['direccion'] ?? '';
$rfcNegocio = $negocio['rfc'] ?? '';
$telefonoNegocio = $negocio['telefono'] ?? '';

// Obtener resumen del corte
$apiUrl = sprintf('../../api/corte_caja/resumen_corte_actual.php?corte_id=%d', $corte_id);
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// mantener la sesión
curl_setopt($ch, CURLOPT_COOKIE, session_name().'='.session_id());
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$r = $data['resultado'] ?? [];
$cajero = $data['cajero'] ?? '';

function fmt($num) {
    return '$' . number_format((float)$num, 2);
}

$lineas = [];
$metodos = ['efectivo', 'boucher', 'cheque'];
foreach ($metodos as $m) {
    if (isset($r[$m])) {
        $info = $r[$m];
        $lineas[] = ucfirst($m) . ': productos ' . fmt($info['productos']) .
            ' | propina ' . fmt($info['propina']) .
            ' | total ' . fmt($info['total']);
    }
}

$meserosLinea = '';
if (!empty($r['total_meseros'])) {
    $parts = [];
    foreach ($r['total_meseros'] as $m) {
        $parts[] = $m['nombre'] . ' ' . fmt($m['total']);
    }
    $meserosLinea = 'Meseros: ' . implode(', ', $parts);
}

$repartidoresLinea = '';
if (!empty($r['total_repartidor'])) {
    $parts = [];
    foreach ($r['total_repartidor'] as $rep) {
        $parts[] = $rep['nombre'] . ' ' . fmt($rep['total']);
    }
    $repartidoresLinea = 'Repartidores: ' . implode(', ', $parts);
}

$fechaImpresion = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Corte Temporal</title>
    <link rel="stylesheet" href="../../utils/css/style.css">
    <style>
        body { font-family: monospace; }
        #ticketContainer { width: 300px; margin: 0 auto; }
        #ticketContainer div { margin-bottom: 3px; }
        @media print { body { width: 300px; } }
    </style>
</head>
<body onload="window.print()">
<div id="ticketContainer">
    <h2 class="section-header"><?= htmlspecialchars($nombreNegocio) ?></h2>
    <div><?= htmlspecialchars($direccionNegocio) ?></div>
    <div><?= htmlspecialchars($rfcNegocio) ?></div>
    <div><?= htmlspecialchars($telefonoNegocio) ?></div>
    <div><?= htmlspecialchars($fechaImpresion) ?></div>
    <div><strong>Cajero:</strong> <?= htmlspecialchars($cajero) ?></div>
    <div><strong>Folio inicio:</strong> <?= htmlspecialchars($r['folio_inicio'] ?? '') ?></div>
    <div><strong>Folio fin:</strong> <?= htmlspecialchars($r['folio_fin'] ?? '') ?></div>
    <?php foreach ($lineas as $ln): ?>
    <div><?= $ln ?></div>
    <?php endforeach; ?>
    <div>Total productos: <?= fmt($r['total_productos'] ?? 0) ?></div>
    <div>Total propinas: <?= fmt($r['total_propinas'] ?? 0) ?></div>
    <div>Fondo: <?= fmt($r['fondo'] ?? 0) ?></div>
    <div>Depósitos: <?= fmt($r['total_depositos'] ?? 0) ?></div>
    <div>Retiros: <?= fmt($r['total_retiros'] ?? 0) ?></div>
    <div>Total final: <?= fmt($r['totalFinal'] ?? 0) ?></div>
    <?php if ($meserosLinea): ?><div><?= $meserosLinea ?></div><?php endif; ?>
    <div>Rápido: <?= fmt($r['total_rapido'] ?? 0) ?></div>
    <?php if ($repartidoresLinea): ?><div><?= $repartidoresLinea ?></div><?php endif; ?>
    <p>Gracias por su preferencia</p>
    <div><?= htmlspecialchars($fechaImpresion) ?></div>
</div>
</body>
</html>
<?php
?>
