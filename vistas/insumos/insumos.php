<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Insumos';
ob_start();
?>
 <h1>Entradas de Insumos</h1>
 <h2>Crear Insumos</h2>
 <button type="button" id="btnNuevoInsumo">Nuevo insumo</button>
 <table id="listaInsumos" border="1">
     <thead>
         <tr>
             <th>Nombre</th>
             <th>Unidad</th>
             <th>Existencia</th>
             <th>Tipo de control</th>
             <th>Acciones</th>
         </tr>
     </thead>
     <tbody></tbody>
 </table>
 <form id="formEntrada">
     <h2>Productos</h2>
     <label for="proveedor">Proveedor:</label>
     <select id="proveedor" name="proveedor"></select>
     <button type="button" id="btnNuevoProveedor">Nuevo proveedor</button>
     <table id="tablaProductos" border="1">
         <thead>
             <tr>
                 <th>Producto</th>
                 <th>Tipo de control</th>
                 <th>Cantidad</th>
                 <th>Unidad</th>
                 <th>Precio unitario</th>
             </tr>
         </thead>
         <tbody>
             <tr>
                 <td><select class="producto"></select></td>
                 <td class="tipo"></td>
                 <td><input type="number" class="cantidad"></td>
                 <td class="unidad"></td>
                 <td><input type="number" class="precio"></td>
             </tr>
         </tbody>
     </table>
     <p><strong>Total (cantidad X precio): $<span id="total">0.00</span></strong></p>
     <button type="button" id="agregarFila">Agregar producto</button>
     <button type="button" id="registrarEntrada">Registrar entrada</button>
 </form>
 <h2>Insumos con bajo stock</h2>
 <table id="bajoStock" border="1">
     <thead>
         <tr>
             <th>ID</th>
             <th>Nombre</th>
             <th>Unidad</th>
             <th>Existencia</th>
         </tr>
     </thead>
     <tbody></tbody>
 </table>
 <h2>Historial de Entradas por Proveedor</h2>
 <table id="historial" border="1">
     <thead>
         <tr>
             <th>Proveedor</th>
             <th>Fecha</th>
             <th>Total</th>
             <th>Producto</th>
         </tr>
     </thead>
     <tbody></tbody>
 </table>
 <script src="insumos.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
