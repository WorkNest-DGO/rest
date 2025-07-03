<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Inventario';
ob_start();
?>
<h1>Inventario</h1>
<button id="agregarProducto">Agregar producto</button>
<table id="tablaProductos" border="1">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Precio</th>
            <th>Existencia</th>
            <th>Descripción</th>
            <th>Activo</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<script src="inventario.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
