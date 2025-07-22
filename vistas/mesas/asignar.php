<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$title = 'Asignar Mesas';
ob_start();
?>
<h1>Asignar meseros a mesas</h1>
<table id="tablaAsignacion" class="table table-bordered">
    <thead>
        <tr><th>Mesa</th><th>Mesero</th></tr>
    </thead>
    <tbody></tbody>
</table>
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
