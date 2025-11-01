function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;
// Constante de envío en UI cocina
window.ENVIO_CASA_PRODUCT_ID = window.ENVIO_CASA_PRODUCT_ID || 9001;
window.CARGO_PLATAFORMA_PRODUCT_ID = window.CARGO_PLATAFORMA_PRODUCT_ID || 9000;
window.ultimoDetalleCocina = parseInt(localStorage.getItem('ultimoDetalleCocina') || '0', 10);
(() => {
  const qs = s => document.querySelector(s);
  const qsa = s => Array.from(document.querySelectorAll(s));

  const cols = {
    pendiente: qs('#col-pendiente'),
    en_preparacion: qs('#col-preparacion'),
    listo: qs('#col-listo'),
    entregado: qs('#col-entregado')
  };

  const rolUsuario = document.querySelector('#user-info')?.dataset.rol || '';

  const filtroInput = qs('#txtFiltro');
  const tipoEntregaSel = qs('#selTipoEntrega');
  const btnRefrescar = qs('#btnRefrescar');

  let cache = [];

  const allowedNext = {
    pendiente: 'en_preparacion',
    en_preparacion: 'listo',
    listo: 'entregado'
  };

  const style = document.createElement('style');
  style.textContent = `
    .kanban-item{position:relative;padding-bottom:30px;}
    .kanban-item .btn-ver{position:absolute;bottom:5px;right:5px;}
    #productoModalOverlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:1000;}
    #productoModalOverlay .modal-box{background:rgba(46, 31, 31, 0.5);padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.3);max-width:400px;width:90%;max-height:90%;overflow:auto;position:relative;}
    #productoModalOverlay .close-btn{position:absolute;top:10px;right:10px;background:none;border:none;cursor:pointer;font-size:14px;}
    #productoModalOverlay .modal-box p{margin:4px 0;}
  `;
  document.head.appendChild(style);

  function createCard(it){
    const card = document.createElement('div');
    card.className = 'kanban-item';
    card.draggable = true;
    card.dataset.id = it.detalle_id;
    card.dataset.estado = it.estado;
    card.dataset.productoId = it.producto_id;
    card.innerHTML = `
      <div class='title'>${it.producto} <small>x${it.cantidad}</small></div>
      <div class='meta'>
        <span>${it.destino}</span>
        <span>${formatHora(it.estado === 'entregado' ? it.entregado_hr : it.hora)}</span>
        ${it.observaciones ? `<span>Obs: ${escapeHtml(it.observaciones)}</span>` : ''}
      </div>
      <button class="btn-ver">Ver</button>
    `;
    const verBtn = card.querySelector('.btn-ver');
    verBtn.addEventListener('click', ev => { ev.stopPropagation(); mostrarModalProducto(it); });
    bindDrag(card);
    return card;
  }

  function render(items){
    const PID_ENVIO = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
    const PID_CARGO = Number(window.CARGO_PLATAFORMA_PRODUCT_ID || 9000);
    Object.values(cols).forEach(c => c.innerHTML = '');
    const txt = (filtroInput.value || '').toLowerCase();
    const tipo = (tipoEntregaSel.value || '').toLowerCase();

    const visibles = (items || []).filter(it => {
      const pid = Number(it.producto_id);
      return pid !== PID_ENVIO && pid !== PID_CARGO;
    });
    visibles.forEach(it => {
      const cat = (it.categoria || '').toLowerCase();
      if (rolUsuario === 'barra' && cat !== 'bebida') return;
      if (rolUsuario === 'alimentos' && cat === 'bebida') return;
      if (txt){
        const hay = (it.producto + ' ' + it.destino).toLowerCase().includes(txt);
        if (!hay) return;
      }
      if (tipo && it.tipo !== tipo) return;

      const card = createCard(it);
      (cols[it.estado] || cols.pendiente).appendChild(card);
    });
  }

  function formatHora(s){
    try { return new Date(s.replace(' ', 'T')).toLocaleTimeString(); } catch(e){ return s; }
  }
  function escapeHtml(s){
    const div = document.createElement('div');
    div.innerText = s;
    return div.innerHTML;
  }

  function bindDrag(el){
    el.addEventListener('dragstart', ev => {
      ev.dataTransfer.setData('text/plain', el.dataset.id);
      setTimeout(()=> el.classList.add('dragging'), 0);
    });
    el.addEventListener('dragend', ()=> el.classList.remove('dragging'));
  }

  function mostrarModalProducto(item){
    const overlay = document.createElement('div');
    overlay.id = 'productoModalOverlay';
    const campos = [
      ['Producto','producto'],
      ['Categoría','categoria'],
      ['Cantidad','cantidad'],
      ['Precio unitario','precio_unitario'],
      ['Subtotal','subtotal'],
      ['Estado','estado'],
      ['Hora de creación','hora'],
      ['Hora de entrega','entregado_hr'],
      ['Minutos transcurridos','minutos_transcurridos'],
      ['Mesero','mesero'],
      ['Cajero','cajero'],
      ['Sede','sede'],
      ['Estado de entrega','estado_entrega'],
      ['Observaciones','observaciones'],
      ['Observaciones venta','observaciones_venta'],
      ['Insumos requeridos','insumos_requeridos'],
      ['Prioridad','prioridad']
    ];
    const contenido = campos.map(([lab, key]) => `<p><strong>${lab}:</strong> ${escapeHtml(item[key] != null ? String(item[key]) : '')}</p>`).join('');
    overlay.innerHTML = `<div class="modal-box"><button class="close-btn">Cerrar</button>${contenido}</div>`;
    document.body.appendChild(overlay);
    overlay.querySelector('.close-btn').addEventListener('click', () => overlay.remove());
  }

  qsa('.kanban-dropzone').forEach(zone => {
    zone.addEventListener('dragover', ev => { ev.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', ()=> zone.classList.remove('drag-over'));
    zone.addEventListener('drop', async ev => {
      ev.preventDefault();
      zone.classList.remove('drag-over');
      const id = ev.dataTransfer.getData('text/plain');
      const card = document.querySelector(`.kanban-item[data-id='${id}']`);
      if (!card) return;
      const PID_ENVIO = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
      if (Number(card.dataset.productoId || 0) === PID_ENVIO) {
        return; // no-op sobre envío
      }
      const current = card.dataset.estado;
      const nuevoEstado = zone.closest('.kanban-board').dataset.status;
      if (allowedNext[current] !== nuevoEstado){
        alert('Transición no permitida');
        return;
      }
      const ok = await cambiarEstado(+id, nuevoEstado);
      if (ok){
        card.dataset.estado = nuevoEstado;
        zone.appendChild(card);
        const idx = cache.findIndex(x => x.detalle_id === +id);
        if (idx >= 0) cache[idx].estado = nuevoEstado;
      }
    });
  });

  async function cargar(){
    const res = await fetch(`../../api/cocina/listar_productos_cocina.php`);
    if (!res.ok){ alert('Error al cargar comandas'); return; }
    const json = await res.json();
    if (!json.success){ alert(json.message || 'Error'); return; }
    cache = json.resultado || json.data || json;
    render(cache);
    const ids = cache.map(it => parseInt(it.detalle_id, 10) || 0);
    if (ids.length) {
      window.ultimoDetalleCocina = Math.max.apply(null, ids);
      localStorage.setItem('ultimoDetalleCocina', String(window.ultimoDetalleCocina));
    }
  }

  async function cambiarEstado(detalle_id, nuevo_estado){
    try{
      const res = await fetch(`../../api/cocina/cambiar_estado_producto.php`, {
        method:'POST',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify({ detalle_id, nuevo_estado })
      });
      const data = await res.json();
      if (!res.ok || data.success === false){
        alert(data.message || 'No se pudo cambiar el estado');
        return false;
      }
      return true;
    }catch(e){
      alert('Error de red');
      return false;
    }
  }

  window.cargarDatosCocina = cargar;

  filtroInput.addEventListener('input', ()=> render(cache));
  tipoEntregaSel.addEventListener('change', ()=> render(cache));
  btnRefrescar.addEventListener('click', cargar);
})();

// Long-poll por notificación (versión + ids)
let cocinaVersion = Number(localStorage.getItem('cocinaVersion') || '0');
let cocinaNoChangeTicks = 0;

function columnaSelector(estado){
  return {
    'pendiente':       '#col-pendiente',
    'en_preparacion':  '#col-preparacion',
    'listo':           '#col-listo',
    'entregado':       '#col-entregado'
  }[estado] || '#col-pendiente';
}

function moverTarjetas(estadosMap){
  Object.entries(estadosMap).forEach(([idStr, estado])=>{
    const id = parseInt(idStr,10);
    const card = document.querySelector(`.kanban-item[data-id='${id}']`);
    if (!card) return;
    const actual = card.dataset.estado;
    if (actual !== estado) {
      card.dataset.estado = estado;
      const destinoSel = columnaSelector(estado);
      const destino = document.querySelector(destinoSel);
      if (destino && !destino.contains(card)) destino.prepend(card);
      const badge = card.querySelector('.badge-estado');
      if (badge) badge.textContent = String(estado).replace('_',' ');
    }
  });
}

async function waitCambiosLoop(){
  try {
    const r = await fetch(`../../api/cocina/listen_cambios.php?since=${cocinaVersion}`, { cache:'no-store' });
    const data = await r.json();
    // Siempre sincronizar la versión local con la del servidor,
    // incluso cuando no haya cambios, para evitar quedar "atascado".
    if (data && typeof data.version !== 'undefined') {
      const verSrv = Number(data.version);
      if (!Number.isNaN(verSrv)) {
        cocinaVersion = verSrv;
        localStorage.setItem('cocinaVersion', String(cocinaVersion));
      }
    }
    if (data.changed) {
      cocinaNoChangeTicks = 0;
      const ids = Array.isArray(data.ids) ? data.ids : [];
      if (ids.length) {
        const r2 = await fetch(`../../api/cocina/estados_por_ids.php`, {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ ids })
        });
        const d2 = await r2.json();
        if (d2.ok && d2.estados) moverTarjetas(d2.estados);

        // Agregar tarjetas nuevas (ids que no existen aún en el DOM)
        const missing = ids.filter(id => !document.querySelector(`.kanban-item[data-id='${id}']`));
        if (missing.length) {
          try {
            const r3 = await fetch(`../../api/cocina/detalles_por_ids.php`, {
              method:'POST',
              headers:{'Content-Type':'application/json'},
              body: JSON.stringify({ ids: missing })
            });
            const d3 = await r3.json();
            if (d3.ok && Array.isArray(d3.data)) {
              // Aplicar filtros actuales al insertar
              const txt = (document.querySelector('#txtFiltro')?.value || '').toLowerCase();
              const tipo = (document.querySelector('#selTipoEntrega')?.value || '').toLowerCase();
              d3.data.forEach(it => {
                const PID_ENVIO = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
                const PID_CARGO = Number(window.CARGO_PLATAFORMA_PRODUCT_ID || 9000);
                const pid = Number(it.producto_id);
                if (pid === PID_ENVIO || pid === PID_CARGO) return;
                const cat = (it.categoria || '').toLowerCase();
                if (rolUsuario === 'barra' && cat !== 'bebida') return;
                if (rolUsuario === 'alimentos' && cat === 'bebida') return;
                if (txt){
                  const hay = (it.producto + ' ' + it.destino).toLowerCase().includes(txt);
                  if (!hay) return;
                }
                if (tipo && it.tipo !== tipo) return;
                // Evitar duplicados si aparece casi simultáneo
                if (document.querySelector(`.kanban-item[data-id='${it.detalle_id}']`)) return;
                const card = createCard(it);
                (document.querySelector(columnaSelector(it.estado)) || document.querySelector('#col-pendiente')).prepend(card);
                // Actualizar cache local
                if (!cache.some(x => x.detalle_id === it.detalle_id)) cache.push(it);
              });
              // Actualizar último detalle para persistencia
              const allIds = Array.from(document.querySelectorAll('.kanban-item')).map(n => parseInt(n.dataset.id,10)).filter(Boolean);
              if (allIds.length) {
                window.ultimoDetalleCocina = Math.max.apply(null, allIds);
                localStorage.setItem('ultimoDetalleCocina', String(window.ultimoDetalleCocina));
              }
            }
          } catch (_) { /* noop insertar nuevas */ }
        }
      }
    } else {
      cocinaNoChangeTicks++;
      // Fallback: si no hay cambios varias rondas, recargar lista completa
      if (cocinaNoChangeTicks >= 2 && typeof cargarDatosCocina === 'function') {
        try { await cargarDatosCocina(); } catch(_){}
        cocinaNoChangeTicks = 0;
      }
    }
  } catch (e) {
    await new Promise(res => setTimeout(res, 1000));
  }
  // reabrir long-poll
  waitCambiosLoop();
}

$(document).ready(function () {
  if (typeof cargarDatosCocina === 'function') {
    cargarDatosCocina().then(() => {
      waitCambiosLoop();
    });
  } else {
    waitCambiosLoop();
  }
});
