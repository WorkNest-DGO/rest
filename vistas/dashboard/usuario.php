<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
require_once __DIR__ . '/../../config/db.php';
// Base app dinamica y ruta relativa para validacion
$__sn = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$__pos = strpos($__sn, '/vistas/');
$__app_base = $__pos !== false ? substr($__sn, 0, $__pos) : rtrim(dirname($__sn), '/');
$path_actual = preg_replace('#^' . preg_quote($__app_base, '#') . '#', '', ($__sn ?: $_SERVER['PHP_SELF']));
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

$isAdmin = (($_SESSION['rol'] ?? '') === 'admin');

$title = 'Mi usuario';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <h2>Mi usuario</h2>
      </div>
      <div class="col-12">
        <a href="../dash.php">Dashboard ADMIN</a>
        <a>Mi usuario</a>
      </div>
    </div>
  </div>
</div>

<div class="container mt-5 mb-5">
  <h1 class="section-header">Datos de usuario</h1>

  <form id="formUsuario" class="mt-4" data-admin="<?php echo $isAdmin ? '1' : '0'; ?>">
    <div class="form-group">
      <label for="nombre">Nombre:</label>
      <input type="text" id="nombre" class="form-control" required>
    </div>
    <div class="form-group">
      <label for="usuario">Usuario:</label>
      <input type="text" id="usuario" class="form-control" readonly>
    </div>
    <div class="form-group">
      <label for="rol">Rol:</label>
      <input type="text" id="rol" class="form-control" readonly>
    </div>
    <div class="form-group">
      <label for="sede">Sede:</label>
      <select id="sede" class="form-control" <?php echo $isAdmin ? '' : 'disabled'; ?> required></select>
    </div>
    <div class="form-group">
      <label for="contrasena">Nueva contrasena (opcional):</label>
      <input type="password" id="contrasena" class="form-control">
      <small class="text-muted">Deja en blanco si no deseas cambiarla.</small>
    </div>
    <div class="form-group">
      <label for="contrasena2">Confirmar contrasena:</label>
      <input type="password" id="contrasena2" class="form-control">
    </div>
    <div class="text-end mt-3">
      <button type="submit" class="btn custom-btn">Guardar cambios</button>
    </div>
  </form>
</div>

<!-- Modal global de mensajes -->
<div class="modal fade" id="appMsgModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Mensaje</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="../../utils/js/modal-lite.js"></script>
<script src="usuario.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
