(() => {
  const API_BASE = '/api/cocina';
  const qs = s => document.querySelector(s);
  const qsa = s => Array.from(document.querySelectorAll(s));

  const cols = {
    pendiente: qs('#col-pendiente'),
    en_preparacion: qs('#col-preparacion'),
    listo: qs('#col-listo'),
    entregado: qs('#col-entregado')
  };

  const filtroInput = qs('#txtFiltro');
  const tipoEntregaSel = qs('#selTipoEntrega');
  const btnRefrescar = qs('#btnRefrescar');

  let cache = [];

  const allowedNext = {
    pendiente: 'en_preparacion',
    en_preparacion: 'listo',
    listo: 'entregado'
  };

  function render(items){
    Object.values(cols).forEach(c => c.innerHTML = '');
    const txt = (filtroInput.value || '').toLowerCase();
    const tipo = (tipoEntregaSel.value || '').toLowerCase();

    items.forEach(it => {
      if (txt){
        const hay = (it.producto + ' ' + it.destino).toLowerCase().includes(txt);
        if (!hay) return;
      }
      if (tipo && it.tipo !== tipo) return;

      const card = document.createElement('div');
      card.className = 'kanban-item';
      card.draggable = true;
      card.dataset.id = it.detalle_id;
      card.dataset.estado = it.estado;
      card.innerHTML = `
        <div class='title'>${it.producto} <small>x${it.cantidad}</small></div>
        <div class='meta'>
          <span>${it.destino}</span>
          <span>${formatHora(it.hora)}</span>
          ${it.observaciones ? `<span>Obs: ${escapeHtml(it.observaciones)}</span>` : ''}
        </div>
      `;
      bindDrag(card);
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

  qsa('.kanban-dropzone').forEach(zone => {
    zone.addEventListener('dragover', ev => { ev.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', ()=> zone.classList.remove('drag-over'));
    zone.addEventListener('drop', async ev => {
      ev.preventDefault();
      zone.classList.remove('drag-over');
      const id = ev.dataTransfer.getData('text/plain');
      const card = document.querySelector(`.kanban-item[data-id='${id}']`);
      if (!card) return;

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
    const res = await fetch(`${API_BASE}/listar_productos_cocina.php`);
    if (!res.ok){ alert('Error al cargar comandas'); return; }
    const json = await res.json();
    if (!json.success){ alert(json.message || 'Error'); return; }
    cache = json.resultado || json.data || json;
    render(cache);
  }

  async function cambiarEstado(detalle_id, nuevo_estado){
    try{
      const res = await fetch(`${API_BASE}/cambiar_estado_producto.php`, {
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

  filtroInput.addEventListener('input', ()=> render(cache));
  tipoEntregaSel.addEventListener('change', ()=> render(cache));
  btnRefrescar.addEventListener('click', cargar);

  cargar();
})();
