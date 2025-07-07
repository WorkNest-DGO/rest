<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Insumos';
ob_start();
?>
 
 

        <!-- Page Header Start -->
        <div class="page-header">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h2>Ingredientes</h2>
                    </div>
                    <div class="col-12">
                        <a href="../../index.php">Inicio</a>
                        <a href="">Blog</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Page Header End -->
        
 
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

        <!-- Blog Start -->
        <div class="blog">
            <div class="container">
                <div class="section-header text-center">
                    <p>Insumos</p>
                    <h2>Recuerda validar los datos antes de guardar altas</h2>
                     <button type="button" id="btnNuevoInsumo">Nuevo insumo</button>
                </div>
                <div class="row" id="catalogoInsumos"></div>
                
                <form id="formInsumo" style="display:none;margin-top:20px;">
                    <input type="hidden" id="insumoId">
                    <div>
                        <label>Nombre:</label>
                        <input type="text" id="nombre">
                        <label>Unidad:</label>
                        <input type="text" id="unidad">
                        <label>Existencia:</label>
                        <input type="number" step="0.01" id="existencia" value="0">
                        <label>Tipo:</label>
                        <select id="tipo_control">
                            <option value="por_receta">por_receta</option>
                            <option value="unidad_completa">unidad_completa</option>
                            <option value="uso_general">uso_general</option>
                            <option value="no_controlado">no_controlado</option>
                            <option value="desempaquetado">desempaquetado</option>
                        </select>
                        <input type="file" id="imagen">
                        <button type="submit">Guardar</button>
                        <button type="button" id="cancelarInsumo">Cancelar</button>
                    </div>
                </form>

                </div>
                <div class="row">
                    <div class="col-12">
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                            <li class="page-item"><a class="page-link" href="#">1</a></li>
                            <li class="page-item active"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Next</a></li>
                        </ul> 
                    </div>
                </div>
            </div>
        </div>
        <!-- Blog End -->

        <!-- seccion de datos inicio-->
               <h1>Entradas de Insumos</h1>
 <h2>Crear Insumos</h2>

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
<!-- seccion de datos fin  -->


<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="insumos.js"></script>
    </body>
</html>
 
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';


