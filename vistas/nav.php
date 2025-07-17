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

require_once __DIR__ . '/../config/db.php';

$usuarioId = $_SESSION['usuario_id'];
$sql = "SELECT r.id, r.nombre, r.ruta, r.tipo, r.padre_id, r.orden
        FROM rutas r
        INNER JOIN usuario_ruta ur ON r.id = ur.ruta_id
        WHERE ur.usuario_id = ?
        ORDER BY r.orden";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $usuarioId);
$stmt->execute();
$result = $stmt->get_result();
$rutasPermitidas = [];
while ($row = $result->fetch_assoc()) {
    $rutasPermitidas[] = $row;
}
$stmt->close();

$dropdownItems = [];
$menuRoutes = [];
foreach ($rutasPermitidas as $ruta) {
    if ($ruta['tipo'] === 'dropdown-item') {
        $dropdownItems[$ruta['padre_id']][] = $ruta;
        continue;
    }
    $menuRoutes[] = $ruta;
}

foreach ($dropdownItems as &$items) {
    usort($items, function ($a, $b) {
        return $a['orden'] <=> $b['orden'];
    });
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
<?php foreach ($menuRoutes as $ruta): ?>
    <?php if ($ruta['tipo'] === 'link'): ?>
                    <a href="<?= $base_url . '/' . $ruta['ruta'] ?>" class="nav-item nav-link"><?= $ruta['nombre'] ?></a>
    <?php elseif ($ruta['tipo'] === 'dropdown'): ?>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown"><?= $ruta['nombre'] ?></a>
                        <div class="dropdown-menu">
        <?php if (!empty($dropdownItems[$ruta['id']])): ?>
            <?php foreach ($dropdownItems[$ruta['id']] as $item): ?>
                            <a href="<?= $base_url . '/' . $item['ruta'] ?>" class="dropdown-item"><?= $item['nombre'] ?></a>
            <?php endforeach; ?>
        <?php endif; ?>
                        </div>
                    </div>
    <?php endif; ?>
<?php endforeach; ?>
                    <a href="<?= $base_url ?>/vistas/logout.php" class="nav-item nav-link">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>
    <?php echo $content ?? ''; ?>
