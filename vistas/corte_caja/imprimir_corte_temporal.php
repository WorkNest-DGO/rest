<?php
header('Content-Type: text/html; charset=utf-8');
$datos = [];
if (isset($_GET['datos'])) {
    $json = $_GET['datos'];
    $datos = json_decode($json, true);
    if (!is_array($datos)) {
        $datos = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Corte Temporal</title>
<style>
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #000; padding: 4px; }
</style>
</head>
<body onload="window.print()">
<h2>Corte Temporal</h2>
<table>
<tbody>
<?php foreach ($datos as $k => $v): ?>
    <?php if (is_array($v)): ?>
        <tr><th colspan="2"><?php echo htmlspecialchars($k); ?></th></tr>
        <?php foreach ($v as $k2 => $v2): ?>
            <tr><td><?php echo htmlspecialchars($k2); ?></td><td><?php echo htmlspecialchars(is_array($v2) ? json_encode($v2) : $v2); ?></td></tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td><?php echo htmlspecialchars($k); ?></td><td><?php echo htmlspecialchars($v); ?></td></tr>
    <?php endif; ?>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
