<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/rest');
}
$base_url = BASE_URL;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.html');
    exit;
}

// Conexión a la base de datos
require_once __DIR__ . '/../config/db.php';

$usuario_id = $_SESSION['usuario_id'];

// Obtener rutas permitidas para el usuario
$sql = "SELECT r.id, r.nombre, r.path, r.tipo, r.grupo, r.orden
        FROM usuario_ruta ur
        INNER JOIN rutas r ON ur.ruta_id = r.id
        WHERE ur.usuario_id = ?
        ORDER BY r.orden ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$rutas = [];
$_SESSION['rutas_permitidas'] = [];

while ($row = $result->fetch_assoc()) {
    $rutas[] = $row;
    $_SESSION['rutas_permitidas'][] = $row['path'];
}

// Agrupar por tipo
$enlaces = [];
$dropdowns = [];

foreach ($rutas as $ruta) {
    if ($ruta['tipo'] === 'link') {
        $enlaces[] = $ruta;
    } elseif ($ruta['tipo'] === 'dropdown') {
        $dropdowns[$ruta['grupo']] = [
            'label' => $ruta['nombre'],
            'items' => []
        ];
    } elseif ($ruta['tipo'] === 'dropdown-item') {
        $dropdowns[$ruta['grupo']]['items'][] = $ruta;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tokyo Sushi POS <?= $title ?? 'Sistema'; ?></title>
    <meta name="description" content="Sistema de punto de venta de Tokyo Sushi para control de cobros y operaciones.">
    <meta name="author" content="Tokyo Sushi">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400|Nunito:600,700" rel="stylesheet">
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
                <?php foreach ($enlaces as $link): ?>
                    <a href="<?= $base_url . $link['path'] ?>" class="nav-item nav-link"><?= htmlspecialchars($link['nombre']) ?></a>
                <?php endforeach; ?>

                <?php foreach ($dropdowns as $grupo): ?>
                    <?php if (count($grupo['items']) > 0): ?>
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown"><?= htmlspecialchars($grupo['label']) ?></a>
                            <div class="dropdown-menu">
                                <?php foreach ($grupo['items'] as $item): ?>
                                    <a href="<?= $base_url . $item['path'] ?>" class="dropdown-item"><?= htmlspecialchars($item['nombre']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Logout siempre disponible -->
                <a href="<?= $base_url ?>/vistas/logout.php" class="nav-item nav-link">Cerrar sesión</a>
            </div>
        </div>
    </div>
</div>

<?php echo $content ?? ''; ?>
