<?php
@require_once __DIR__ . '/../../utils/cargar_permisos.php';
@require_once __DIR__ . '/../../config/db.php';

$title = 'Facturación Masiva';
ob_start();
?>

<!-- Page Header Start -->
<div class="page-header mb-0">
  <div class="container">
    <div class="row">
      <div class="col-12"><h2>Facturación Masiva</h2></div>
      <div class="col-12"><a href="../ventas/ventas.php">Ventas</a> <a href="masiva.php">Facturación</a></div>
    </div>
  </div>
</div>
<!-- Page Header End -->

<div class="container mt-4">
  <style>
    section { background: rgba(255,255,255,0.04); padding: 16px; margin-bottom: 12px; border-radius: 8px; }
    .row { display: flex; gap: 12px; flex-wrap: wrap; }
    .row > * { flex: 1; min-width: 220px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #333; padding: 8px; text-align: left; }
    thead th { background: #111; color: #fff; position: sticky; top: 0; }
    .right { text-align: right; }
    .actions { display: flex; gap: 8px; }
    button { padding: 8px 12px; }
    button[disabled] { opacity: .5; cursor: not-allowed; }
    input, select { padding: 8px; border: 1px solid #444; background:#1a1a1a; color:#f0f0f0; border-radius: 6px; width: 100%; box-sizing: border-box; }
    .tag { display:inline-block; padding:2px 8px; border-radius:999px; background:#333; font-size:12px; }
    .muted { color: #bbb; font-size: 12px; }
    .list { display:flex; flex-wrap:wrap; gap:8px; }
    .list .item { background:#2a2a2a; padding:6px 10px; border-radius:6px; }
    .totales { display:flex; gap:16px; justify-content:flex-end; }
    .totales div { min-width: 160px; text-align:right; }
    .modal { position: fixed; inset:0; display:none; align-items:center; justify-content:center; background: rgba(0,0,0,.5); }
    .modal .box { background:#111; width:min(900px, 96vw); max-height:90vh; overflow:auto; border-radius:10px; padding:16px; }
  </style>

  <!-- 1) Filtros del periodo -->
  <section>
    <h3>Filtros</h3>
    <div class="row">
      <div>
        <label>Desde</label>
        <input type="date" id="filtro-desde">
      </div>
      <div>
        <label>Hasta</label>
        <input type="date" id="filtro-hasta">
      </div>
      <div>
        <label>Sede</label>
        <input type="number" id="filtro-sede" placeholder="sede_id (opcional)">
      </div>
      <div>
        <label>Buscar</label>
        <input type="search" id="filtro-buscar" placeholder="Folio, RFC o Cliente">
      </div>
      <div style="align-self:flex-end;">
        <button id="btn-buscar" type="button" class="btn custom-btn">Buscar</button>
      </div>
    </div>
  </section>

  <!-- 2) Pendientes -->
  <section>
    <h3>Pendientes por facturar</h3>
    <div class="mb-2 d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center" style="gap:8px;">
        <label for="pend-records" class="me-2">Registros por página:</label>
        <select id="pend-records" class="form-select w-auto">
          <option value="20" selected>20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
      <div id="pend-paginacion"></div>
    </div>
    <div class="table-responsive">
      <table id="tabla-pendientes">
        <thead>
          <tr>
            <th>Folio Ticket</th>
            <th>Fecha</th>
            <th>Tipo pago</th>
            <th class="right">Total</th>
            <th>Seleccionar</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

  <!-- 3) Selección -->
  <section>
    <h3>Selección para facturar</h3>
    <div id="lista-seleccion" class="list"></div>
    <div class="totales">
      <div>Subtotal: <strong id="totales-subtotal">0.00</strong></div>
      <div>Impuestos: <strong id="totales-impuestos">0.00</strong></div>
      <div>Total: <strong id="totales-total">0.00</strong></div>
    </div>
  </section>

  <!-- 3.1) Forma de pago -->
  <section>
    <h3>Forma de pago</h3>
    <div class="row">
      <div>
        <label>Detectado</label>
        <input type="text" id="forma-pago-info" placeholder="Selecciona tickets" readonly>
        <div class="muted" id="forma-pago-ayuda">Se toma del tipo de pago guardado en el ticket.</div>
      </div>
      <div id="forma-pago-select-wrap" style="display:none;">
        <label>Si es tarjeta, elige</label>
        <select id="forma-pago-select">
          <option value="04">04 - T. Cr&eacute;dito</option>
          <option value="28">28 - T. D&eacute;bito</option>
        </select>
      </div>
    </div>
  </section>

  <!-- 4) Datos del receptor -->
  <section>
    <h3>Datos del receptor</h3>
    <div class="row" style="align-items:flex-end; gap:8px;">
      <div style="flex:2;">
        <label>Cliente</label>
        <select id="select-cliente"><option value="">Seleccione...</option></select>
      </div>
      <div style="flex:1; display:flex; gap:8px;">
        <button type="button" class="btn custom-btn" id="btn-nuevo-cliente">Nuevo</button>
        <button type="button" class="btn custom-btn" id="btn-editar-cliente" disabled>Editar</button>
      </div>
    </div>
  </section>

  <!-- 5) Acciones -->
  <section>
    <h3>Acciones</h3>
    <div class="actions">
      <button id="btn-uno-a-uno" type="button" class="btn custom-btn" disabled>Facturar 1:1</button>
      <button id="btn-global" type="button" class="btn custom-btn" disabled>Factura global 1:muchos</button>
    </div>
    <p class="muted">Los botones se habilitan al elegir cliente y tickets.</p>
  </section>

  <!-- 6) Facturas emitidas -->
  <section>
    <h3>Facturas emitidas</h3>
    <div class="table-responsive">
      <table id="tabla-facturadas">
        <thead>
          <tr>
            <th>Folio</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th class="right">Importe</th>
            <th class="right">#Tickets</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

  <!-- 7) Modal Detalle -->
  <div class="modal" id="modal-detalle">
    <div class="box">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <div style="display:flex; align-items:center; gap:8px;">
          <h3 style="margin-right:8px;">Detalle de factura</h3>
          <a id="btn-desc-xml" href="#" class="btn custom-btn" download>Descargar XML</a>
          <a id="btn-desc-pdf" href="#" class="btn custom-btn" download>Descargar PDF</a>
          <button type="button" id="btn-reenviar-factura" class="btn custom-btn">Reenviar al cliente</button>
        </div>
        <button type="button" id="btn-cerrar-modal" class="btn custom-btn">Cerrar</button>
      </div>
      <div id="detalle-contenido">
        <p class="muted">Cargando...</p>
      </div>
    </div>
  </div>

  <!-- Modal Cliente (alta/edici��n) -->
  <div class="modal" id="modal-cliente">
    <div class="box">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <h3 id="modal-cliente-titulo">Cliente facturaci��n</h3>
        <button type="button" id="btn-cerrar-modal-cliente" class="btn custom-btn">Cerrar</button>
      </div>
      <form id="form-cliente">
        <input type="hidden" id="cliente-id">
        <div class="row">
          <div>
            <label>RFC</label>
            <input type="text" id="cliente-rfc" required maxlength="13">
          </div>
          <div>
            <label>Raz��n social</label>
            <input type="text" id="cliente-razon" required>
          </div>
        </div>
        <div class="row">
          <div>
            <label>Correo</label>
            <input type="email" id="cliente-correo">
          </div>
          <div>
            <label>Tel��fono</label>
            <input type="text" id="cliente-telefono">
          </div>
        </div>
        <div class="row">
          <div>
            <label>CP</label>
            <input type="text" id="cliente-cp">
          </div>
          <div>
            <label>R��gimen</label>
            <input type="text" id="cliente-regimen">
          </div>
          <div>
            <label>Uso CFDI</label>
            <input type="text" id="cliente-uso">
          </div>
        </div>
        <div class="row">
          <div>
            <label>Calle</label>
            <input type="text" id="cliente-calle">
          </div>
          <div>
            <label>N��mero ext.</label>
            <input type="text" id="cliente-numero-ext">
          </div>
          <div>
            <label>N��mero int.</label>
            <input type="text" id="cliente-numero-int">
          </div>
        </div>
        <div class="row">
          <div>
            <label>Colonia</label>
            <input type="text" id="cliente-colonia">
          </div>
          <div>
            <label>Municipio</label>
            <input type="text" id="cliente-municipio">
          </div>
          <div>
            <label>Estado</label>
            <input type="text" id="cliente-estado">
          </div>
          <div>
            <label>Pa��s</label>
            <input type="text" id="cliente-pais">
          </div>
        </div>
        <div class="mt-3" style="display:flex; justify-content:flex-end; gap:8px;">
          <button type="button" class="btn custom-btn" id="btn-guardar-cliente">Guardar</button>
        </div>
      </form>
    </div>
  </div>
  <script src="masiva.js"></script>
</div>
  <?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
