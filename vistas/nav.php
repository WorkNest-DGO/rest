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
            <li><a href="ventas/ventas.php">Ventas</a></li>
            <li><a href="mesas/mesas.php">Mesas</a></li>
            <li><a href="cocina/cocina.php">Cocina</a></li>
            <li><a href="inventario/inventario.php">Inventario</a></li>
            <li><a href="insumos/insumos.php">Insumos</a></li>
            <li><a href="recetas/recetas.php">Recetas</a></li>
            <li><a href="repartidores/repartos.php">Repartos</a></li>
            <li><a href="reportes/reportes.php">Reportes</a></li>
        </ul>
    </nav>
    <a href="../logout.php">Cerrar sesión</a>
</header>
<main>
<?php echo $content ?? ''; ?>
</main>
</body>
</html>
