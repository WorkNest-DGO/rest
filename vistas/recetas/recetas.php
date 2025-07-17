<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['rutas_permitidas']) || !in_array($_SERVER['PHP_SELF'], array_map(function ($ruta) {
    return '/rest' . $ruta;
}, $_SESSION['rutas_permitidas']))) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Recetas';
ob_start();
?>
<!-- Page Header Start -->
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Recetas</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Catálogo de Recetas</a>
            </div>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container mt-5 mb-5">
    <h1 class="section-header">Recetas de Platillos</h1>

    <div class="form-group">
        <label for="producto_id">Platillo:</label>
        <select id="producto_id" class="form-control"></select>
    </div>

    <div id="vistaProducto" class="product-view">
        <p id="nombreProducto" class="product-name"></p>
        <img id="imgProducto" class="product-image" alt="Imagen del producto">

        <form id="formImagen" style="display:none;" enctype="multipart/form-data">
            <div class="form-group">
                <label for="imagenProducto">Subir nueva imagen:</label>
                <input type="file" id="imagenProducto" accept="image/*" class="form-control-file">
            </div>
            <button type="button" id="subirImagen" class="btn custom-btn">Subir imagen</button>
        </form>
    </div>
</div>

<div class="container mt-5 mb-5">
  <h2 class="section-subheader">Ingredientes</h2>
  
  <div class="table-responsive">
    <table id="tablaReceta" class="table">
      <thead>
        <tr>
          <th>Ingrediente</th>
          <th>Cantidad</th>
          <th>Unidad</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><select class="form-control insumo"></select></td>
          <td><input type="number" step="0.01" class="form-control cantidad"></td>
          <td class="unidad">—</td>
          <td><button type="button" class="btn btn-sm btn-danger eliminar">Eliminar</button></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="mb-3">
    <button type="button" id="agregarFila" class="btn custom-btn">Agregar insumo</button>
    <button type="button" id="guardarReceta" class="btn custom-btn">Guardar receta</button>
    <button type="button" id="copiarReceta" class="btn btn-secondary">Copiar receta de otro producto</button>
  </div>

  <div id="modal-copiar" style="display:none;"></div>
</div>


<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="recetas.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
