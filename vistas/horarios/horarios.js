function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;

let horarios = [];
let horariosFiltrados = [];
let paginaHor = 1;
const itemsPorPaginaHor = 20;

async function cargarSeries() {
    const resp = await fetch('../../api/tickets/listar_series.php');
    const data = await resp.json();
    const sel = document.getElementById('serie_id');
    sel.innerHTML = '';
    if (data.success) {
        data.resultado.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.descripcion;
            sel.appendChild(opt);
        });
    } else {
        alert(data.mensaje);
    }
}

async function listarHorarios() {
    const resp = await fetch('../../api/horarios/listar_horarios.php');
    const data = await resp.json();
    if (data.success) {
        horarios = Array.isArray(data.resultado) ? data.resultado : [];
        horariosFiltrados = horarios;
        renderHorarios(1);
    } else {
        alert(data.mensaje);
    }
}

function renderHorarios(pagina = 1) {
    const tbody = document.querySelector('#tablaHorarios tbody');
    const pag = document.getElementById('paginadorHorarios');
    if (!tbody || !pag) return;

    paginaHor = pagina;
    const totalPag = Math.max(1, Math.ceil(horariosFiltrados.length / itemsPorPaginaHor));
    if (paginaHor > totalPag) paginaHor = totalPag;
    const ini = (paginaHor - 1) * itemsPorPaginaHor;
    const fin = ini + itemsPorPaginaHor;
    const visibles = horariosFiltrados.slice(ini, fin);

    tbody.innerHTML = '';
    visibles.forEach(h => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${h.dia_semana}</td><td>${h.hora_inicio}</td><td>${h.hora_fin}</td><td>${h.serie}</td>`;
        const acc = document.createElement('td');
        const e = document.createElement('button');
        e.className='btn custom-btn';
        e.textContent = 'Editar';
        e.onclick = () => editar(h);
        const d = document.createElement('button');
        d.className='btn custom-btn';
        d.textContent = 'Eliminar';
        d.onclick = () => eliminar(h.id);
        acc.appendChild(e);
        acc.appendChild(d);
        tr.appendChild(acc);
        tbody.appendChild(tr);
    });

    // paginador
    pag.innerHTML = '';
    const prevLi = document.createElement('li');
    prevLi.className = 'page-item' + (paginaHor === 1 ? ' disabled' : '');
    const prevA = document.createElement('a'); prevA.className='page-link'; prevA.href='#'; prevA.textContent='Anterior';
    prevA.addEventListener('click', e => { e.preventDefault(); if (paginaHor > 1) renderHorarios(paginaHor - 1); });
    prevLi.appendChild(prevA); pag.appendChild(prevLi);
    for (let i=1;i<=totalPag;i++){
        const li=document.createElement('li'); li.className='page-item'+(i===paginaHor?' active':'');
        const a=document.createElement('a'); a.className='page-link'; a.href='#'; a.textContent=String(i);
        a.addEventListener('click', e=>{e.preventDefault(); renderHorarios(i);});
        li.appendChild(a); pag.appendChild(li);
    }
    const nextLi = document.createElement('li');
    nextLi.className = 'page-item' + (paginaHor === totalPag ? ' disabled' : '');
    const nextA = document.createElement('a'); nextA.className='page-link'; nextA.href='#'; nextA.textContent='Siguiente';
    nextA.addEventListener('click', e => { e.preventDefault(); if (paginaHor < totalPag) renderHorarios(paginaHor + 1); });
    nextLi.appendChild(nextA); pag.appendChild(nextLi);
}

function editar(h) {
    document.getElementById('horarioId').value = h.id;
    document.getElementById('dia_semana').value = h.dia_semana;
    document.getElementById('hora_inicio').value = h.hora_inicio;
    document.getElementById('hora_fin').value = h.hora_fin;
    document.getElementById('serie_id').value = h.serie_id;
    document.getElementById('cancelar').style.display = 'inline';
}

document.getElementById('cancelar').onclick = () => {
    document.getElementById('horarioId').value = '';
    document.getElementById('formHorario').reset();
    document.getElementById('cancelar').style.display = 'none';
};

document.getElementById('formHorario').onsubmit = async ev => {
    ev.preventDefault();
    const id = document.getElementById('horarioId').value;
    const payload = {
        id: id ? parseInt(id) : undefined,
        dia_semana: document.getElementById('dia_semana').value,
        hora_inicio: document.getElementById('hora_inicio').value,
        hora_fin: document.getElementById('hora_fin').value,
        serie_id: parseInt(document.getElementById('serie_id').value)
    };
    const url = id ? '../../api/horarios/actualizar_horario.php' : '../../api/horarios/crear_horario.php';
    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await resp.json();
    if (data.success) {
        document.getElementById('formHorario').reset();
        document.getElementById('horarioId').value = '';
        document.getElementById('cancelar').style.display = 'none';
        listarHorarios();
    } else {
        alert(data.mensaje);
    }
};

async function eliminar(id) {
    if (!confirm('Eliminar horario?')) return;
    const resp = await fetch('../../api/horarios/eliminar_horario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    const data = await resp.json();
    if (data.success) {
        listarHorarios();
    } else {
        alert(data.mensaje);
    }
}

window.addEventListener('DOMContentLoaded', () => {
    cargarSeries();
    listarHorarios();
    const buscar = document.getElementById('buscarHorario');
    if (buscar) {
        let t;
        buscar.addEventListener('input', e => {
            clearTimeout(t);
            t = setTimeout(() => {
                const q = (e.target.value || '').toLowerCase();
                horariosFiltrados = horarios.filter(h => (`${h.dia_semana} ${h.hora_inicio} ${h.hora_fin} ${h.serie}`).toLowerCase().includes(q));
                renderHorarios(1);
            }, 250);
        });
    }
});
