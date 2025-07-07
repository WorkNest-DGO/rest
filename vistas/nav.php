<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/rest/vistas/');
}
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
  <link href="<?= BASE_URL ?>../utils/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>../utils/css/all.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>../utils/lib/animate/animate.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>../utils/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>../utils/fontawesome/css/all.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>../utils/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet">
  <link rel="icon" href="<?= BASE_URL ?>../utils/logo.png" type="image/png">
  <link href="<?= BASE_URL ?>../utils/css/style1.css" rel="stylesheet">
</head>
<body>
<div class="navbar navbar-expand-lg bg-light navbar-light">
    <div class="container-fluid">
        <a href="<?= BASE_URL ?>index.php" class="navbar-brand">Tokyo <span style="text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;">Sushi</span></a>
        <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-between" id="navbarCollapse">
            <div class="navbar-nav ml-auto">
                <a href="<?= BASE_URL ?>index.php" class="nav-item nav-link active">Inicio</a>
                <a href="<?= BASE_URL ?>ventas/ventas.php" class="nav-item nav-link">Cobros</a>
                <a href="<?= BASE_URL ?>corte_caja/corte.php" class="nav-item nav-link">Cortes</a>
                <a href="<?= BASE_URL ?>mesas/mesas.php" class="nav-item nav-link">Mesas</a>
                <a href="<?= BASE_URL ?>repartidores/repartos.php" class="nav-item nav-link">Envios</a>
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">Más</a>
                    <div class="dropdown-menu">
                        <a href="<?= BASE_URL ?>historial.php" class="dropdown-item">Historial</a>
                        <a href="<?= BASE_URL ?>configuracion.php" class="dropdown-item">Configuración</a>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>ayuda.php" class="nav-item nav-link">Ayuda</a>
                <a href="<?= BASE_URL ?>logout.php" class="nav-item nav-link">Cerrar sesión</a>
            </div>
        </div>
    </div>
</div>
<?php echo $content ?? ''; ?>
