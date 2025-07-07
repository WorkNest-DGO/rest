<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Cocina';
ob_start();
?>
<h1>Modulo de Cocina</h1>
<button id="btn-pendientes">Pendientes</button>
<button id="btn-entregados">Entregados hoy</button>
<div id="seccion-pendientes">
    <table id="tabla-pendientes" border="1">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Tiempo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
<div id="seccion-entregados" style="display:none;">
    <table id="tabla-entregados" border="1">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="cocina.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
