function showAppMsg(msg) {
  const body = document.querySelector('#appMsgModal .modal-body');
  if (body) body.textContent = String(msg);
  showModal('#appMsgModal');
}
window.alert = showAppMsg;

let currentPage = 1;
let limit = 15;
const order = 'fecha DESC';
let searchQuery = '';

async function cargarHistorial(page = currentPage) {
  currentPage = page;
  try {
    const resp = await fetch(`../../api/ventas/listar_ventas.php?pagina=${currentPage}&limite=${limit}&orden=${encodeURIComponent(order)}&busqueda=${encodeURIComponent(searchQuery)}`);
    const data = await resp.json();
    if (!data.success) { alert(data.mensaje || 'Error'); return; }
    const tbody = document.querySelector('#historial tbody');
    tbody.innerHTML = '';
    (data.resultado.ventas || []).forEach(v => {
      const id = v.venta_id || v.id;
      const row = document.createElement('tr');
      const fecha = v.fecha || '';
      const destino = v.tipo_entrega === 'mesa' ? v.mesa : v.tipo_entrega === 'domicilio' ? v.repartidor : 'Venta rápida';
      row.innerHTML = `
        <td>${id}</td>
        <td>${v.folio ? v.folio : 'N/A'}</td>
        <td>${fecha}</td>
        <td>${v.total}</td>
        <td>${v.tipo_entrega}</td>
        <td>${destino || ''}</td>
        <td>${v.estatus}</td>
      `;
      tbody.appendChild(row);
    });
    renderPagination(data.resultado.total_paginas, data.resultado.pagina_actual);
  } catch (e) {
    console.error(e);
    alert('Error al cargar historial');
  }
}

function renderPagination(total, page) {
  const cont = document.getElementById('paginacion');
  cont.innerHTML = '';
  if (total <= 1) return;
  if (page > 1) { const b=document.createElement('button'); b.className='btn custom-btn me-1'; b.textContent='Anterior'; b.onclick=()=>cargarHistorial(page-1); cont.appendChild(b); }
  for (let i=1;i<=total;i++){ const b=document.createElement('button'); b.className='btn custom-btn me-1'; b.textContent=String(i); if(i===page) b.disabled=true; b.onclick=()=>cargarHistorial(i); cont.appendChild(b); }
  if (page < total) { const b=document.createElement('button'); b.className='btn custom-btn'; b.textContent='Siguiente'; b.onclick=()=>cargarHistorial(page+1); cont.appendChild(b); }
}

document.addEventListener('DOMContentLoaded', () => {
  const select = document.getElementById('recordsPerPage');
  select.addEventListener('change', e => { limit = parseInt(e.target.value); cargarHistorial(1); });
  const busc = document.getElementById('buscadorVentas');
  busc.addEventListener('input', e => { searchQuery = e.target.value.trim(); cargarHistorial(1); });
  cargarHistorial();
});

