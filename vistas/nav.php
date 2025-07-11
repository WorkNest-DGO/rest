<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/rest');
}
$base_url = BASE_URL;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tokyo Sushi POS <?php echo $title ?? 'Sistema'; ?></title>
    <meta name="description" content="Sistema de punto de venta de Tokyo Sushi para control de cobros y operaciones.">
    <meta name="author" content="Tokyo Sushi">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400|Nunito:600,700" rel="stylesheet">

    <!-- CSS Libraries -->
    <link href="<?= $base_url ?>/utils/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/css/all.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/lib/animate/animate.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?= $base_url ?>/utils/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet">
    <link rel="icon" href="<?= $base_url ?>/utils/logo.png" type="image/png">
    <link href="<?= $base_url ?>/utils/css/style1.css" rel="stylesheet">
</head>

<body>
    <div class="navbar navbar-expand-lg bg-light navbar-light">
        <div class="container-fluid">
            <a href="<?= $base_url ?>/vistas/index.php" class="navbar-brand">Tokyo <span style="text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;">Sushi</span></a>
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-between" id="navbarCollapse">
                <div class="navbar-nav ml-auto">
                    <a href="<?= $base_url ?>/vistas/index.php" class="nav-item nav-link active">Inicio</a>
                                        <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">Productos</a>
                        <div class="dropdown-menu">
                            <a href="<?= $base_url ?>/vistas/insumos/insumos.php" class="dropdown-item">Insumos</a>
                            <a href="<?= $base_url ?>/vistas/inventario/inventario.php" class="dropdown-item">Inventario</a>
                            <a href="<?= $base_url ?>/vistas/recetas/recetas.php" class="dropdown-item">Recetas</a>
                        </div>
                    </div>
                    <a href="<?= $base_url ?>/vistas/cocina/cocina.php" class="nav-item nav-link">Cocina</a>
                    <a href="<?= $base_url ?>/vistas/ventas/ventas.php" class="nav-item nav-link">Ventas</a>
                    <a href="<?= $base_url ?>/vistas/corte_caja/corte.php" class="nav-item nav-link">Cortes</a>
                    <a href="<?= $base_url ?>/vistas/repartidores/repartos.php" class="nav-item nav-link">Repartos</a>

                    <a href="<?= $base_url ?>/vistas/mesas/mesas.php" class="nav-item nav-link">Mesas</a>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">Más</a>
                        <div class="dropdown-menu">
                            <a href="<?= $base_url ?>/vistas/horarios/horarios.php" class="dropdown-item">Horarios</a>
                            <a href="<?= $base_url ?>/vistas/ventas/ticket.php" class="dropdown-item">ticket</a>
                            <a href="<?= $base_url ?>/vistas/reportes/reportes.php" class="dropdown-item">Reportes</a>
                        </div>
                    </div>
                    <a href="<?= $base_url ?>/vistas/ayuda.php" class="nav-item nav-link">Ayuda</a>
                    <a href="<?= $base_url ?>/vistas/logout.php" class="nav-item nav-link">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>
    <?php echo $content ?? ''; ?>
