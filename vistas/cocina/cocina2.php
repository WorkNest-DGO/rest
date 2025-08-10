<?php
require_once __DIR__ . '/../../utils/cargar_permisos.php';
$path_actual = str_replace('/rest', '', $_SERVER['PHP_SELF']);
if (!in_array($path_actual, $_SESSION['rutas_permitidas'])) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}
$title = 'Cocina (Kanban)';
ob_start();
?>
<div class="page-header mb-0">
  <div class="container">
    <div class="row"><div class="col-12"><h2>Módulo de Cocina (Kanban)</h2></div></div>
  </div>
</div>

<div class="container my-3">
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <input id="txtFiltro" type="text" class="form-control" placeholder="Filtrar por producto/destino" style="max-width:280px">
    <select id="selTipoEntrega" class="form-control" style="max-width:180px">
      <option value="">Todos</option>
      <option value="mesa">Mesa</option>
      <option value="domicilio">Domicilio</option>
      <option value="rapido">Rápido</option>
    </select>
    <button id="btnRefrescar" class="btn btn-primary">Refrescar</button>
  </div>
</div>

<style>
  .kanban-container { display:grid; gap:12px; grid-template-columns: repeat(4, minmax(0, 1fr)); padding: 0 16px 24px; }
  @media (max-width: 1200px){ .kanban-container { grid-template-columns: repeat(2, 1fr);} }
  @media (max-width: 700px){ .kanban-container { grid-template-columns: 1fr;} }

  .kanban-board { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); display:flex; flex-direction:column; min-height: 65vh; }
  .kanban-board h3 { margin:0; padding:12px 14px; font-size:16px; font-weight:700; color:#222; border-bottom:1px solid #eee; border-top-left-radius:10px; border-top-right-radius:10px; }
  .kanban-dropzone { flex:1; padding:10px; min-height:200px; overflow:auto; }

  .kanban-item { background:#fafafa; border:1px solid #e9e9e9; border-left:4px solid transparent; border-radius:8px; padding:10px; margin-bottom:8px; cursor:grab; }
  .kanban-item:active { cursor:grabbing; }
  .kanban-item .title { font-weight:600; line-height:1.2; }
  .kanban-item .meta { font-size:12px; color:#666; display:flex; gap:10px; flex-wrap:wrap; margin-top:6px; }
  .drag-over { outline:2px dashed #999; outline-offset: -6px; }

  /* colores por estado */
  .board-pendiente h3 { background:#ffefef; border-color:#ffd4d4; }
  .board-pendiente .kanban-item { border-left-color:#e74c3c; }
  .board-preparacion h3 { background:#fff4e6; border-color:#ffd9a6; }
  .board-preparacion .kanban-item { border-left-color:#f39c12; }
  .board-listo h3 { background:#e9fbf1; border-color:#c8f0d9; }
  .board-listo .kanban-item { border-left-color:#27ae60; }
  .board-entregado h3 { background:#f5f5f5; border-color:#e5e5e5; }
  .board-entregado .kanban-item { border-left-color:#7f8c8d; opacity:.85; }
</style>

<div id="kanban" class="kanban-container">
  <div class="kanban-board board-pendiente" data-status="pendiente">
    <h3>Pendiente</h3>
    <div class="kanban-dropzone" id="col-pendiente"></div>
  </div>
  <div class="kanban-board board-preparacion" data-status="en_preparacion">
    <h3>En preparación</h3>
    <div class="kanban-dropzone" id="col-preparacion"></div>
  </div>
  <div class="kanban-board board-listo" data-status="listo">
    <h3>Listo</h3>
    <div class="kanban-dropzone" id="col-listo"></div>
  </div>
  <div class="kanban-board board-entregado" data-status="entregado">
    <h3>Entregado</h3>
    <div class="kanban-dropzone" id="col-entregado"></div>
  </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="cocina2.js"></script>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
