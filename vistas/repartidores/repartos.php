<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Repartos';
ob_start();
?>
<h1>Repartos</h1>
<h2>Pendientes</h2>
<table id="tabla-pendientes" border="1">
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Total</th>
            <th>Repartidor</th>
            <th>Productos</th>
            <th>Asignado</th>
            <th>Inicio</th>
            <th>Entrega</th>
            <th>Total (min)</th>
            <th>En camino (min)</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<h2>Entregadas</h2>
<table id="tabla-entregadas" border="1">
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Total</th>
            <th>Repartidor</th>
            <th>Productos</th>
            <th>Asignado</th>
            <th>Inicio</th>
            <th>Entrega</th>
            <th>Total (min)</th>
            <th>En camino (min)</th>
            <th>Ver detalles</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<div id="modal-detalles" style="display:none;"></div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="repartos.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
