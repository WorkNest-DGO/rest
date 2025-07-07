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
                <div class="row">

                <!-- aqui deben ir insumos -->
                    <div class="col-md-3">
                        <div class="blog-item">
                            <div class="blog-img">
                                <img src="../../utils/img/blog-1.jpg" alt="Blog">
                            </div>
                            <div class="blog-content">
                                <h2 class="blog-title">Lorem ipsum dolor sit amet</h2>
                                <div class="blog-meta">
                                    <p><i class="far fa-user"></i>Admin</p>
                                    <p><i class="far fa-list-alt"></i>Food</p>
                                    <p><i class="far fa-calendar-alt"></i>01-Jan-2045</p>
                                    <p><i class="far fa-comments"></i>10</p>
                                </div>
                                <div class="blog-text">
                                    <p>
                                        Lorem ipsum dolor sit amet elit. Neca pretim miura bitur facili ornare velit non vulpte liqum metus tortor. Lorem ipsum dolor sit amet elit. Neca pretim miura bitur facili ornare velit non vulpte
                                    </p>
                                    <a class="btn custom-btn" href="">Read More</a>
                                </div>
                            </div>
                        </div>
                    </div>
               <!-- aqui termina donde van insumos -->

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


