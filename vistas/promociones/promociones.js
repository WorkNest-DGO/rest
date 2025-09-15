// Utilidades simples
function showModal(sel) { if ($(sel).modal) { $(sel).modal('show'); } }
function hideModal(sel) { if ($(sel).modal) { $(sel).modal('hide'); } }
function money(n){ const num=Number(n)||0; return '$ ' + num.toLocaleString('es-MX',{minimumFractionDigits:2, maximumFractionDigits:2}); }

let state = { pagina: 1, limite: 20, q: '', tipo: '', activo: '' };

async function listarPromos() {
  const p = new URLSearchParams();
  p.set('pagina', state.pagina);
  p.set('limite', state.limite);
  if (state.q) p.set('q', state.q);
  if (state.tipo) p.set('tipo', state.tipo);
  if (state.activo !== '') p.set('activo', state.activo);
  const resp = await fetch(`../../api/promos/listar.php?${p.toString()}`);
  const data = await resp.json();
  if (!data.success) { alert(data.mensaje || 'Error al listar'); return; }
  renderTabla(data.resultado.promos || []);
  renderPager(data.resultado.total || 0, data.resultado.pagina || 1, data.resultado.limite || state.limite);
}

function renderTabla(items) {
  const tbody = document.querySelector('#tablaPromos tbody');
  tbody.innerHTML = '';
  items.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${row.id}</td>
      <td>${escapeHtml(row.motivo)}</td>
      <td>${row.tipo}</td>
      <td>${money(row.monto)}</td>
      <td>${row.activo ? 'Sí' : 'No'}</td>
      <td>${row.visible_en_ticket ? 'Sí' : 'No'}</td>
      <td>${row.prioridad}</td>
      <td>${row.combinable ? 'Sí' : 'No'}</td>
      <td>
        <button class="btn btn-sm btn-secondary me-1" data-action="edit" data-id="${row.id}">Editar</button>
        <button class="btn btn-sm btn-danger" data-action="del" data-id="${row.id}">Eliminar</button>
      </td>`;
    tbody.appendChild(tr);
  });
}

function renderPager(total, pagina, limite) {
  const cont = document.getElementById('paginador');
  cont.innerHTML = '';
  const totalPag = Math.max(1, Math.ceil(total / limite));
  if (totalPag <= 1) return;
  const mk = (txt, pg, dis=false) => { const b = document.createElement('button'); b.className='btn custom-btn me-1'; b.textContent=txt; b.disabled=!!dis; b.addEventListener('click',()=>{ state.pagina=pg; listarPromos(); }); return b; };
  cont.appendChild(mk('Anterior', Math.max(1, pagina-1), pagina===1));
  cont.appendChild(document.createTextNode(` Página ${pagina} de ${totalPag} `));
  cont.appendChild(mk('Siguiente', Math.min(totalPag, pagina+1), pagina===totalPag));
}

function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }

function limpiarForm(){
  document.getElementById('promoId').value='';
  document.getElementById('motivo').value='';
  document.getElementById('monto').value='0';
  document.getElementById('tipo').value='monto_fijo';
  document.getElementById('prioridad').value='10';
  document.getElementById('activo').checked=true;
  document.getElementById('visible_en_ticket').checked=true;
  document.getElementById('combinable').checked=true;
  document.getElementById('regla').value='{}';
  document.getElementById('promoModalTitle').textContent='Nueva promoción';
}

async function editar(id){
  const resp = await fetch(`../../api/promos/obtener.php?id=${id}`);
  const data = await resp.json();
  if (!data.success) { alert(data.mensaje || 'No se pudo obtener'); return; }
  const r = data.resultado;
  document.getElementById('promoId').value = r.id;
  document.getElementById('motivo').value = r.motivo || '';
  document.getElementById('monto').value = r.monto || 0;
  document.getElementById('tipo').value = r.tipo || 'monto_fijo';
  document.getElementById('prioridad').value = r.prioridad || 10;
  document.getElementById('activo').checked = !!Number(r.activo);
  document.getElementById('visible_en_ticket').checked = !!Number(r.visible_en_ticket);
  document.getElementById('combinable').checked = !!Number(r.combinable);
  document.getElementById('regla').value = (r.regla && typeof r.regla === 'string') ? r.regla : JSON.stringify(r.regla || {}, null, 2);
  document.getElementById('promoModalTitle').textContent='Editar promoción';
  showModal('#promoModal');
}

async function guardar(){
  const id = Number(document.getElementById('promoId').value || 0);
  const payload = {
    id: id || undefined,
    motivo: document.getElementById('motivo').value.trim(),
    monto: Number(document.getElementById('monto').value || 0),
    tipo: document.getElementById('tipo').value,
    prioridad: Number(document.getElementById('prioridad').value || 10),
    activo: document.getElementById('activo').checked,
    visible_en_ticket: document.getElementById('visible_en_ticket').checked,
    combinable: document.getElementById('combinable').checked,
  };
  const reglaTxt = document.getElementById('regla').value.trim();
  try { payload.regla = reglaTxt ? JSON.parse(reglaTxt) : {}; }
  catch(e){ alert('Regla debe ser JSON válido'); return; }

  const resp = await fetch(`../../api/promos/guardar.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
  const data = await resp.json();
  if (!data.success) { alert(data.mensaje || 'Error al guardar'); return; }
  hideModal('#promoModal');
  await listarPromos();
}

async function eliminar(id){
  if (!confirm('¿Eliminar promoción #' + id + '?')) return;
  const resp = await fetch(`../../api/promos/eliminar.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id}) });
  const data = await resp.json();
  if (!data.success) { alert(data.mensaje || 'No se pudo eliminar'); return; }
  await listarPromos();
}

async function buscarProductos(){
  const q = document.getElementById('buscarProducto').value.trim();
  const params = new URLSearchParams(); if(q) params.set('q', q);
  const resp = await fetch(`../../api/promos/listar_productos.php?${params.toString()}`);
  const data = await resp.json();
  const div = document.getElementById('listaProductos');
  div.innerHTML = '';
  if (!data.success) { div.textContent = data.mensaje || 'Error'; return; }
  (data.resultado.productos || []).forEach(p => {
    const a = document.createElement('div');
    a.className = 'list-group-item list-group-item-action';
    a.style.cursor = 'pointer';
    a.textContent = `#${p.id} · ${p.nombre} (${money(p.precio)})`;
    a.addEventListener('click', ()=>insertarProductoId(p.id));
    div.appendChild(a);
  });
}

function insertarProductoId(pid){
  const ta = document.getElementById('regla');
  const start = ta.selectionStart;
  const end = ta.selectionEnd;
  const before = ta.value.substring(0, start);
  const after = ta.value.substring(end);
  const insert = String(pid);
  ta.value = before + insert + after;
  ta.focus();
  const pos = before.length + insert.length;
  ta.setSelectionRange(pos, pos);
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btnBuscar').addEventListener('click', ()=>{
    state.q = document.getElementById('buscar').value.trim();
    state.tipo = document.getElementById('filtroTipo').value;
    state.activo = document.getElementById('filtroActivo').value;
    state.pagina = 1;
    listarPromos();
  });
  document.getElementById('btnNueva').addEventListener('click', ()=>{ limpiarForm(); showModal('#promoModal'); });
  document.getElementById('btnGuardarPromo').addEventListener('click', guardar);
  document.getElementById('btnBuscarProd').addEventListener('click', buscarProductos);

  document.querySelector('#tablaPromos tbody').addEventListener('click', (ev)=>{
    const btn = ev.target.closest('button[data-action]');
    if (!btn) return;
    const id = Number(btn.dataset.id);
    if (btn.dataset.action === 'edit') editar(id);
    else if (btn.dataset.action === 'del') eliminar(id);
  });

  listarPromos();
  buscarProductos();
});

