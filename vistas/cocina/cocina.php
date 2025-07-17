<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
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
                                <div id="pendientes-cards" class="col-lg-7 col-md-12"></div>
                                <div class="col-lg-5 d-none d-lg-block">
                                    <img src="../../utils/img/menu-burger-img.jpg" alt="Image">
                                </div>
                            </div>
                        </div>
                        <div id="Entregados" class="container tab-pane fade">
                            <div class="row">
                                <!-- entregados aqui -->
                                <div id="entregados-cards" class="col-lg-7 col-md-12"></div>
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
