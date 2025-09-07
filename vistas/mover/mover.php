<?php
@require_once __DIR__ . '/../../config/db.php'; // opcional si tu app lo requiere para sesión/permisos
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
// CSRF para mover
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];
$auth = getenv('MOVER_AUTH_TOKEN') ?: 'dev';
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mover Tickets entre BD</title>
  <link rel="stylesheet" href="../../utils/css/style1.css">
  <style>
    .layout { display: grid; grid-template-columns: 1fr 120px 1fr; gap: 12px; align-items: start; }
    section { background: rgba(255,255,255,0.04); border-radius: 8px; padding: 12px; }
    .center { display:flex; flex-direction:column; align-items:center; gap:12px; }
    .big { font-size:18px; }
    .busy { opacity:.6; pointer-events:none; }
    .controls { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:8px; }
    table { width:100%; border-collapse: collapse; }
    th, td { padding:6px 8px; border-bottom:1px solid #333; font-size: 14px; }
    thead th { background:#111; color:#fff; position: sticky; top: 0; }
    .right { text-align:right; }
    .row-error { background: rgba(255,0,0,0.08); }
    .badge { padding: 2px 8px; border-radius: 999px; font-size: 12px; background:#333; }
    .badge.err { background:#5a0000; color:#ffb3b3; }
    .pagination { display:flex; gap:8px; align-items:center; }
  </style>
  <script>
    window.MOVER_AUTH = <?php echo json_encode($auth, JSON_UNESCAPED_UNICODE); ?>;
    window.CSRF_TOKEN = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;
  </script>
  <script src="mover.js" defer></script>
  <noscript>Se requiere JavaScript.</noscript>
</head>
<body>
  <header>
    <h1>Mover tickets entre BD operativa y espejo</h1>
  </header>
  <main>
    <div class="layout">
      <section>
        <h3>Tickets en BD1 (operativa)</h3>
        <div class="controls">
          <input type="search" id="q-bd1" placeholder="Buscar (id, folio, venta_id)">
          <input type="date" id="from-bd1">
          <input type="date" id="to-bd1">
          <select id="per-bd1"><option>20</option><option>50</option><option>100</option></select>
          <div class="pagination">
            <button id="prev-bd1" class="btn custom-btn">Prev</button>
            <span id="page-bd1" class="badge">1</span>
            <button id="next-bd1" class="btn custom-btn">Next</button>
          </div>
          <label><input type="checkbox" id="selall-bd1"> Seleccionar todo</label>
        </div>
        <div class="table-wrap">
          <table id="tbl-bd1">
            <thead><tr>
              <th><input type="checkbox" id="selall-bd1-head"></th>
              <th>ID</th><th>Venta</th><th>Folio</th><th class="right">Total</th><th class="right">Desc</th><th>Fecha</th><th>Corte</th><th>Factura</th>
            </tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </section>
      <div class="center">
        <button id="btn-to-esp" class="big" disabled>→ Archivar</button>
        <button id="btn-to-op" class="big" disabled>← Desarchivar</button>
      </div>
      <section>
        <h3>Tickets en BD2 (espejo)</h3>
        <div class="controls">
          <input type="search" id="q-bd2" placeholder="Buscar (id, folio, venta_id)">
          <input type="date" id="from-bd2">
          <input type="date" id="to-bd2">
          <select id="per-bd2"><option>20</option><option>50</option><option>100</option></select>
          <div class="pagination">
            <button id="prev-bd2" class="btn custom-btn">Prev</button>
            <span id="page-bd2" class="badge">1</span>
            <button id="next-bd2" class="btn custom-btn">Next</button>
          </div>
          <label><input type="checkbox" id="selall-bd2"> Seleccionar todo</label>
        </div>
        <div class="table-wrap">
          <table id="tbl-bd2">
            <thead><tr>
              <th><input type="checkbox" id="selall-bd2-head"></th>
              <th>ID</th><th>Venta</th><th>Folio</th><th class="right">Total</th><th class="right">Desc</th><th>Fecha</th><th>Corte</th><th>Factura</th>
            </tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</body>
</html>
