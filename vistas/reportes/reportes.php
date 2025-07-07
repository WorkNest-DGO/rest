<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Reportes';
ob_start();
?>
<h1>Reportes de Cortes</h1>
<div>
    Usuario: <select id="filtroUsuario"></select>
    Inicio: <input type="date" id="filtroInicio">
    Fin: <input type="date" id="filtroFin">
    <button id="aplicarFiltros">Buscar</button>
    <button id="btnImprimir">Imprimir</button>
</div>
<button id="btnResumen">Resumen de corte actual</button>
<div id="modal" style="display:none;"></div>
<h2>Historial de Cortes</h2>
<table id="tablaCortes" border="1">
    <thead>
        <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Fecha inicio</th>
            <th>Fecha cierre</th>
            <th>Total</th>
            <th>Efectivo</th>
            <th>Tarjeta</th>
            <th>Cheque</th>
            <th>Fondo</th>
            <th>Observaciones</th>
            <th>Detalle</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="reportes.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
