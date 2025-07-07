<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Cocina';
ob_start();
?>


       <!-- Page Header Start -->
        <div class="page-header mb-0">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h2>Modulo de Cocina</h2>
                    </div>
                    <div class="col-12">
                        <a href="">Inicio</a>
                        <a href="">Comadas de cocina</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Page Header End -->
        
        

<!-- pendientes -->
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
<!-- Pendientes -->

<!-- entregados -->
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
<!-- entregados -->

        <!-- Menu Start -->
        <div class="menu">
            <div class="container">
                <div class="section-header text-center">
                    <p>Food Menu</p>
                    <h2>Delicious Food Menu</h2>
                </div>
                <div class="menu-tab">
                    <ul class="nav nav-pills justify-content-center">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#Pendientes">Pendientes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#Entregados">Entregados</a>
                        </li>

                    </ul>
                    <div class="tab-content">
                        <div id="Pendientes" class="container tab-pane active">
                            <div class="row">
<!-- pendientes aqui -->
                                <div class="col-lg-7 col-md-12">
                                    <div class="menu-item">
                                        <div class="menu-img">
                                            <img src="../../utils/img/menu-burger.jpg"  alt="Image"> <!-- imagen generica q se repite en todos -->
                                        </div>
                                        <div class="menu-text">
                                            <h3><span>Mini cheese Burger</span> <strong>$9.00</strong></h3> <!-- primero nombre de la mesa luego entre strong el estado del pedido, el estado debe ser del color referente al timpo de entrega (revisar validacion existente) -->
                                            <!-- aqui metemos como texto la cantidad y tiempo  -->
                                            <p>Lorem ipsum dolor sit amet elit. Phasel nec preti facil</p> <!-- el boton de estado lo ponemos aqui en vez de p -->
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-5 d-none d-lg-block">
                                    <img src="../../utils/img/menu-burger-img.jpg" alt="Image">
                                </div>
                            </div>
                        </div>
                        <div id="Entregados" class="container tab-pane fade">
                            <div class="row">
                                <div class="col-lg-7 col-md-12">

                                <!-- entregados aqui-->
                                    <div class="menu-item">
                                        <div class="menu-img">
                                            <img src="../../utils/img/menu-snack.jpg" alt="Image">
                                        </div>
                                        <div class="menu-text">
                                            <h3><span>Corn Tikki - Spicy fried Aloo</span> <strong>$15.00</strong></h3>
                                            <p>Lorem ipsum dolor sit amet elit. Phasel nec preti facil</p>
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-5 d-none d-lg-block">
                                    <img src="../../utils/img/menu-snack-img.jpg" alt="Image">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <!-- Menu End -->

<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="cocina.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
