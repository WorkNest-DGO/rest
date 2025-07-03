<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Recetas';
ob_start();
?>
<h1>Recetas de Productos</h1>
<label for="producto_id">Producto:</label>
<select id="producto_id"></select>
<div id="vistaProducto">
    <p id="nombreProducto"></p>
    <img id="imgProducto" style="max-width:200px;display:none;">
    <form id="formImagen" style="display:none;">
        <input type="file" id="imagenProducto" accept="image/*">
        <button type="button" id="subirImagen">Subir imagen</button>
    </form>
</div>
<h2>Ingredientes</h2>
<table id="tablaReceta" border="1">
    <thead>
        <tr>
            <th>Insumo</th>
            <th>Cantidad</th>
            <th>Unidad</th>
            <th>Acción</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><select class="insumo"></select></td>
            <td><input type="number" step="0.01" class="cantidad"></td>
            <td class="unidad"></td>
            <td><button type="button" class="eliminar">Eliminar</button></td>
        </tr>
    </tbody>
</table>
<button type="button" id="agregarFila">Agregar insumo</button>
<button type="button" id="guardarReceta">Guardar receta</button>
<button type="button" id="copiarReceta">Copiar receta de otro producto</button>
<div id="modal-copiar" style="display:none;"></div>
<script src="recetas.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
