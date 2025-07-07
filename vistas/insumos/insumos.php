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
        
 


        <!-- Blog Start -->
        <div class="blog">
            <div class="container">
                <div class="section-header text-center">
                    <p>Insumos</p>
                    <h2>Recuerda validar los datos antes de guardar altas</h2>
                     <a class="btn custom-btn type="button" id="btnNuevoInsumo">Nuevo insumo</a>
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
                        <ul id="paginador" class="pagination justify-content-center"></ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- Blog End -->
 <form id="formEntrada">
   <div class="container mt-5">
    <h2 class="text-white">Registrar entrada de productos</h2>
    <form id="formEntrada" class="bg-dark p-4 rounded">
        <div class="form-group">
            <label for="proveedor" class="text-white">Proveedor:</label>
            <select id="proveedor" name="proveedor" class="form-control"></select>
            <button type="button" id="btnNuevoProveedor" class="btn custom-btn mt-2">Nuevo proveedor</button>
        </div>

        <div class="table-responsive">
            <table id="tablaProductos" class="table table-bordered table-dark text-white">
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
                        <td><select class="form-control producto"></select></td>
                        <td class="tipo">-</td>
                        <td><input type="number" class="form-control cantidad"></td>
                        <td class="unidad">-</td>
                        <td><input type="number" class="form-control precio"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-white"><strong>Total: $<span id="total">0.00</span></strong></p>

        <div class="form-group">
            <button type="button" id="agregarFila" class="btn custom-btn">Agregar producto</button>
            <button type="button" id="registrarEntrada" class="btn custom-btn">Registrar entrada</button>
        </div>
    </form>
</div>
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



<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="insumos.js"></script>
    </body>
</html>
 
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';


