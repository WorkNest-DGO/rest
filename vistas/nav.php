<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/rest/vistas/');
}
?>
<?php
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
    <title><?php echo $title ?? 'Sistema'; ?></title>
    <link rel="stylesheet" href="../utils/css/estilos.css">
</head>
<body>
<header>
    <h1>Sistema Restaurante</h1>
    <nav>
        <ul>
            <li><a href="<?= BASE_URL ?>cocina/cocina.php">Cocina</a></li>
            <li><a href="<?= BASE_URL ?>corte_caja/corte.php">Corte</a></li>
            <li><a href="<?= BASE_URL ?>insumos/insumos.php">Insumos</a></li>
            <li><a href="<?= BASE_URL ?>inventario/inventario.php">Inventario</a></li>
            <li><a href="<?= BASE_URL ?>mesas/mesas.php">Mesas</a></li>
            <li><a href="<?= BASE_URL ?>recetas/recetas.php">Recetas</a></li>
            <li><a href="<?= BASE_URL ?>repartidores/repartos.php">Repartos</a></li>
            <li><a href="<?= BASE_URL ?>reportes/reportes.php">Reportes</a></li>
            <li><a href="<?= BASE_URL ?>ventas/ventas.php">Ventas</a></li>
            <li><a href="<?= BASE_URL ?>horarios/horarios.php">Horarios</a></li>
            <li><a href="<?= BASE_URL ?>ventas/ticket.php">ticket</a></li>
        </ul>
    </nav>
    <a href="<?= BASE_URL ?>../logout.php">Cerrar sesión</a>
</header>
<main>
<?php echo $content ?? ''; ?>
</main>
</body>
</html>
