<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
$title = 'Asignar Mesas';
ob_start();
?>
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Meseros</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Catálogo de Mesas por mesero</a>
            </div>
        </div>
    </div>
</div>


<h1 class="section-header">Asignar meseros a mesas</h1>
<div class="table-responsive">
<table id="tablaAsignacion" class="styled-table">
    <thead>
        <tr><th>Mesa</th><th>Mesero</th></tr>
    </thead>
    <tbody></tbody>
</table>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script>
  window.usuarioId = <?php echo json_encode($_SESSION['usuario_id']); ?>;
</script>
<script src="asignar.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
