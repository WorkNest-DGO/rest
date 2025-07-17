<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.html');
    exit;
}
$title = 'Inicio';
ob_start();
?>


    <!-- Carousel Start -->
    <div class="carousel">
        <div class="container-fluid">
            <div class="owl-carousel">
                <div class="carousel-item">
                    <div class="carousel-img">
                        <img src="../utils/img/carousel-1.jpg" alt="Image">
                    </div>
                    <div class="carousel-text">
                        <h1>Bien<span>venido</span> al sistema</h1>
                        <p class="text-white">Controla, cobra y mejora tu servicio cada día</p>
                        <div class="carousel-btn">
                            <a class="btn custom-btn" href="">Ingresar al sistema</a>
                            <a class="btn custom-btn" style="color:black" href="">Ver mis cobros</a>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="carousel-img">
                        <img src="../utils/img/carousel-2.jpg" alt="Image">
                    </div>
                    <div class="carousel-text">
                        <h1>Mejora tu<span> administración</span></h1>
                        <p class="text-white">Controla, cobra y mejora tu servicio cada día</p>
                        <div class="carousel-btn">
                            <a class="btn custom-btn" href="">Ingresar al sistema</a>
                            <a class="btn custom-btn" style="color:black">Ver mis cobros</a>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="carousel-img">
                        <img src="../utils/img/carousel-3.jpg" alt="Image">
                    </div>
                    <div class="carousel-text">
                        <h1>Ordenes con <span>la rapidez </span>de un click</h1>
                        <p class="text-white">Controla, cobra y mejora tu servicio cada día</p>
                        <div class="carousel-btn">
                            <a class="btn custom-btn" href="">Ingresar al sistema</a>
                            <a class="btn custom-btn" style="color:black" href="">Ver mis cobros</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Carousel End -->

    <!-- About Start -->
    <div class="about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="about-img">
                        <img src="../utils/img/about.jpg" alt="Tokyo Sushi - Equipo en acción">
                        <button type="button" class="btn-play" data-toggle="modal" data-src="https://www.youtube.com/embed/DWRcNpR6Kdc" data-target="#videoModal">
                            <span></span>
                        </button>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="about-content">
                        <div class="section-header">
                            <p>Sobre el sistema</p>
                            <h2>Transformando el servicio desde adentro</h2>
                        </div>
                        <div class="about-text">
                            <p>
                                Tokyo Sushi ha evolucionado más allá de la cocina. Con este sistema de cobro, buscamos agilizar tu trabajo, asegurar cada transacción y brindarte herramientas confiables para cada jornada.
                            </p>
                            <p>
                                Nuestro equipo merece tecnología que responda a su esfuerzo diario. Este sistema fue diseñado pensando en ti: para que puedas enfocarte en brindar atención de calidad mientras cada cobro, cuenta y movimiento queda correctamente registrado.
                            </p>
                            <a class="btn custom-btn" href="#">Ver mis herramientas</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- About End -->


    <!-- Video Modal Start-->
    <div class="modal fade" id="videoModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <!-- 16:9 aspect ratio -->
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe class="embed-responsive-item" src="" id="video" allowscriptaccess="always" allow="autoplay"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Video Modal End -->


    <!-- Feature Start -->
    <div class="feature">
        <div class="container">
            <div class="row">
                <!-- Texto lateral izquierdo -->
                <div class="col-lg-5">
                    <div class="section-header">
                        <p>¿Por qué usar este sistema?</p>
                        <h2>Funciones clave para tu jornada</h2>
                    </div>
                    <div class="feature-text">
                        <div class="feature-img">
                            <div class="row">
                                <div class="col-6">
                                    <img src="../utils/img/feature-1.jpg" alt="Cobro rápido">
                                </div>
                                <div class="col-6">
                                    <img src="../utils/img/feature-2.jpg" alt="Control de caja">
                                </div>
                                <div class="col-6">
                                    <img src="../utils/img/feature-3.jpg" alt="Reportes precisos">
                                </div>
                                <div class="col-6">
                                    <img src="../utils/img/feature-4.jpg" alt="Historial seguro">
                                </div>
                            </div>
                        </div>
                        <p>
                            Este sistema fue diseñado para brindar agilidad, transparencia y control total en tu flujo de trabajo. Desde el acceso a caja, generación de órdenes, cobros, cortes, hasta reportes y seguimiento histórico: todo en un solo lugar.
                        </p>
                        <a class="btn custom-btn" href="#">Ir a mi panel</a>
                    </div>
                </div>

                <!-- Cuadro de funciones destacadas -->
                <div class="col-lg-7">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="feature-item">
                                <i class="flaticon-cooking"></i>
                                <h3>Órdenes claras y rápidas</h3>
                                <p>
                                    Registra cada pedido en segundos y visualiza al instante los platillos por mesa o por cliente.
                                </p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="feature-item">
                                <i class="flaticon-vegetable"></i>
                                <h3>Cobros eficientes</h3>
                                <p>
                                    Genera recibos con un solo clic y divide cuentas fácilmente según las necesidades del comensal.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="feature-item">
                                <i class="flaticon-medal"></i>
                                <h3>Control de caja</h3>
                                <p>
                                    Realiza cortes y arqueos de manera precisa, con seguimiento de fondo y movimientos por turno.
                                </p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="feature-item">
                                <i class="flaticon-meat"></i>
                                <h3>Reportes en tiempo real</h3>
                                <p>
                                    Consulta ingresos, ventas y estados de cuenta al momento, sin depender de cálculos manuales.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="feature-item">
                                <i class="flaticon-courier"></i>
                                <h3>Seguimiento histórico</h3>
                                <p>
                                    Accede al historial de cobros, usuarios y movimientos para auditorías o aclaraciones.
                                </p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="feature-item">
                                <i class="flaticon-fruits-and-vegetables"></i>
                                <h3>Acceso seguro y personalizado</h3>
                                <p>
                                    Cada usuario cuenta con su perfil, permisos y registros, asegurando trazabilidad en cada acción.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feature End -->


    <!-- Food Start -->
    <div class="food">
        <div class="container">
            <div class="row align-items-center">
                <!-- Primer bloque: Cobros -->
                <div class="col-md-4">
                    <div class="food-item">
                        <i class="flaticon-burger"></i>
                        <h2>Registro de cobros</h2>
                        <p style="color:black">
                            Accede de forma rápida y sencilla a la generación de cobros por orden, cuenta individual o dividida. Cada cobro queda vinculado a tu usuario y turno.
                        </p>
                        <a href="#">Ir a Cobros</a>
                    </div>
                </div>

                <!-- Segundo bloque: Cortes de caja -->
                <div class="col-md-4">
                    <div class="food-item">
                        <i class="flaticon-snack"></i>
                        <h2>Cortes y arqueos</h2>
                        <p style="color:black">
                            Realiza arqueos parciales o cierres de caja. Visualiza diferencias, movimientos y fondos asignados con detalle y claridad operativa.
                        </p>
                        <a href="#">Ver Cortes</a>
                    </div>
                </div>

                <!-- Tercer bloque: Reportes -->
                <div class="col-md-4">
                    <div class="food-item">
                        <i class="flaticon-cocktail"></i>
                        <h2>Consultas y reportes</h2>
                        <p style="color:black">
                            Consulta reportes de ingresos, historial de ventas, movimientos por usuario o caja y otros indicadores clave para el seguimiento de operación.
                        </p>
                        <a href="#">Ir a Reportes</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Food End -->


    <?php require_once __DIR__ . '/footer.php'; ?>
</body>

</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/nav.php';
?>
