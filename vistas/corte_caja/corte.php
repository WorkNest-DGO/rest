<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Corte de Caja';
ob_start();
?>
<h1>Corte de Caja</h1>
<div id="corteActual">
    <button id="btnIniciar">Iniciar Corte</button>
</div>
<div id="resumenModal" style="display:none;"></div>
<div id="modalDesglose" style="display:none;"></div>
<h2>Historial de Cortes</h2>
<table id="tablaCortes" border="1">
    <thead>
        <tr>
            <th>ID</th>
            <th>Cajero</th>
            <th>Fecha inicio</th>
            <th>Fecha fin</th>
            <th>Total</th>
            <th>Detalle</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<script src="corte.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
