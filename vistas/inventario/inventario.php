<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Inventario';
ob_start();
?>
<!-- Page Header Start -->
<div class="page-header mb-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2>Modulo de Platillos</h2>
            </div>
            <div class="col-12">
                <a href="">Inicio</a>
                <a href="">Inventario de Platillos</a>
            </div>
        </div>
    </div>
</div>
<!-- Page Header End -->

<div class="container mt-5 mb-5">
  <h1 class="section-header">Inventario</h1>

  <div class="mb-3 text-end">
    <button class="btn custom-btn" id="agregarProducto" onclick="abrirModalAgregar()">Agregar producto</button>
  </div>

  <div class="table-responsive">
    <table id="tablaProductos" class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Precio</th>
          <th>Existencia</th>
          <th>Descripción</th>
          <th>Activo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div id="modalAgregar" class="modal" style="display:none;">
  <div class="modal-content">
    <h2>Agregar Producto</h2>
    <form id="formAgregar">
      <label>Nombre:</label>
      <input type="text" id="nombreProducto" required><br>
      <label>Precio:</label>
      <input type="number" step="0.01" id="precioProducto" required><br>
      <label>Descripción:</label>
      <textarea id="descripcionProducto"></textarea><br>
      <label>Existencia:</label>
      <input type="number" id="existenciaProducto" required><br>
      <div class="modal-buttons">
        <button class="btn custom-btn" type="submit">Guardar</button>
        <button class="btn custom-btn" type="button" onclick="cerrarModal()">Cancelar</button>
      </div>
    </form>
  </div>
</div>


<div id="modalAlerta" class="modal-style" style="display:none;">
  <div class="modal-content-style">
    <h3 id="modalTitulo">Resultado</h3>
    <p id="mensajeModal"></p>
    <button class="btn custom-btn" id="cerrarModal">Aceptar</button>
  </div>
</div>


<script src="inventario.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
