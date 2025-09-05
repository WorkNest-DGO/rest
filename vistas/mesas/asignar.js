function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;

let mesas = [];
let meseros = [];
let filtradas = [];
let paginaAsig = 1;
const itemsPorPaginaAsig = 20;
const usuarioId = window.usuarioId || 0;

async function cargarDatos() {
    const m = await fetch('../../api/mesas/mesas.php').then(r => r.json());
    const u = await fetch('../../api/mesas/meseros.php').then(r => r.json());
    if (!m.success || !u.success) {
        alert('Error al cargar datos');
        return;
    }
    mesas = m.resultado || [];
    meseros = u.resultado || [];
    filtradas = mesas;
    renderTabla(1);
}

function renderTabla(pagina = 1) {
    const tbody = document.querySelector('#tablaAsignacion tbody');
    const pag = document.getElementById('paginadorAsignar');
    if (!tbody || !pag) return;

    paginaAsig = pagina;
    const totalPag = Math.max(1, Math.ceil(filtradas.length / itemsPorPaginaAsig));
    if (paginaAsig > totalPag) paginaAsig = totalPag;
    const ini = (paginaAsig - 1) * itemsPorPaginaAsig;
    const fin = ini + itemsPorPaginaAsig;
    const visibles = filtradas.slice(ini, fin);

    tbody.innerHTML = '';
    visibles.forEach(mesa => {
        const tr = document.createElement('tr');
        const tdMesa = document.createElement('td');
        tdMesa.textContent = mesa.nombre;
        const tdSel = document.createElement('td');
        const select = document.createElement('select');
        select.className = 'form-control selMesero';
        select.dataset.mesa = mesa.id;
        select.innerHTML = '<option value="">Sin asignar</option>';
        meseros.forEach(me => {
            const opt = document.createElement('option');
            opt.value = me.id;
            opt.textContent = me.nombre;
            if (mesa.usuario_id && mesa.usuario_id === parseInt(me.id)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
        if (usuarioId !== mesa.usuario_id && usuarioId !== 1) {
            select.disabled = true;
        }
        select.addEventListener('change', () => asignarMesero(mesa.id, select.value));
        tdSel.appendChild(select);
        tr.appendChild(tdMesa);
        tr.appendChild(tdSel);
        tbody.appendChild(tr);
    });

    // paginador
    pag.innerHTML = '';
    const prevLi = document.createElement('li');
    prevLi.className = 'page-item' + (paginaAsig === 1 ? ' disabled' : '');
    const prevA = document.createElement('a'); prevA.className='page-link'; prevA.href='#'; prevA.textContent='Anterior';
    prevA.addEventListener('click', e => { e.preventDefault(); if (paginaAsig > 1) renderTabla(paginaAsig - 1); });
    prevLi.appendChild(prevA); pag.appendChild(prevLi);
    for (let i=1;i<=totalPag;i++){
        const li=document.createElement('li'); li.className='page-item'+(i===paginaAsig?' active':'');
        const a=document.createElement('a'); a.className='page-link'; a.href='#'; a.textContent=String(i);
        a.addEventListener('click', e=>{e.preventDefault(); renderTabla(i);});
        li.appendChild(a); pag.appendChild(li);
    }
    const nextLi = document.createElement('li');
    nextLi.className = 'page-item' + (paginaAsig === totalPag ? ' disabled' : '');
    const nextA = document.createElement('a'); nextA.className='page-link'; nextA.href='#'; nextA.textContent='Siguiente';
    nextA.addEventListener('click', e => { e.preventDefault(); if (paginaAsig < totalPag) renderTabla(paginaAsig + 1); });
    nextLi.appendChild(nextA); pag.appendChild(nextLi);
}

async function asignarMesero(mesaId, meseroId) {
    const resp = await fetch('../../api/mesas/asignar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mesa_id: mesaId, usuario_id: meseroId ? parseInt(meseroId) : null, usuario_asignador_id: usuarioId })
    });
    const data = await resp.json();
    if (!data.success) {
        alert(data.mensaje);
    } else {
        cargarDatos();
    }
}

document.addEventListener('DOMContentLoaded', () => {
  cargarDatos();
  const buscar = document.getElementById('buscarAsignacion');
  if (buscar) {
    let t;
    buscar.addEventListener('input', e => {
      clearTimeout(t);
      t = setTimeout(() => {
        const q = (e.target.value || '').toLowerCase();
        filtradas = mesas.filter(m => {
          const nombreMesero = (meseros.find(x => x.id === m.usuario_id)?.nombre || '').toLowerCase();
          return (m.nombre || '').toLowerCase().includes(q) || nombreMesero.includes(q);
        });
        renderTabla(1);
      }, 250);
    });
  }
});
