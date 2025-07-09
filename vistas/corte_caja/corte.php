<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Corte de Caja';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <h2>Modulo de Ventas</h2>
      </div>
      <div class="col-12">
        <a href="">Inicio</a>
        <a href="">Ventas</a>
      </div>
    </div>
  </div>
</div>
<!-- Page Header End -->

<div class="container mt-5 mb-5">
<h1 class="section-header">Corte de Caja</h1>

<div id="corteActual" class="mb-3">
  <button class="btn custom-btn" id="btnIniciar" class="btn btn-primary">Iniciar Corte</button>
</div>

<!-- Modales -->
<div id="resumenModal" class="custom-modal" style="display:none;"></div>
<div id="modalDesglose" class="custom-modal" style="display:none;"></div>

<h2 class="section-header">Historial de Cortes</h2>
<div class="table-responsive">
  <table id="tablaCortes" class="table">
    <thead>
      <tr>
        <th style="color:#fff">ID</th>
        <th style="color:#fff">Cajero</th>
        <th style="color:#fff">Fecha inicio</th>
        <th style="color:#fff">Fecha fin</th>
        <th style="color:#fff">Total</th>
        <th style="color:#fff">Detalle</th>
      </tr>
    </thead>
    <tbody>
      <!-- Se llena dinámicamente -->
    </tbody>
  </table>
</div>

</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="corte.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
