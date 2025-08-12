<?php
require_once __DIR__ . '/../../config/db.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo 'ID inválido';
    exit;
}
$stmt = $conn->prepare('SELECT datos_json FROM corte_caja_historial WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$datos = json_decode($res['datos_json'] ?? '{}', true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ticket Corte Temporal</title>
<style>
body { font-family: monospace; }
ul { list-style: none; padding: 0; }
</style>
</head>
<body>
<h2>Corte Temporal</h2>
<ul>
<?php foreach ($datos as $k => $v) {
    if (!is_array($v)) {
        echo '<li><strong>' . htmlspecialchars($k) . ':</strong> ' . htmlspecialchars((string)$v) . '</li>';
    }
} ?>
</ul>
<script>
window.print();
</script>
</body>
</html>
