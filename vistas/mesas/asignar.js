function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;

let mesas = [];
let meseros = [];
const usuarioId = window.usuarioId || 0;

async function cargarDatos() {
    const m = await fetch('../../api/mesas/mesas.php').then(r => r.json());
    const u = await fetch('../../api/mesas/meseros.php').then(r => r.json());
    if (!m.success || !u.success) {
        alert('Error al cargar datos');
        return;
    }
    mesas = m.resultado;
    meseros = u.resultado;
    renderTabla();
}

function renderTabla() {
    const tbody = document.querySelector('#tablaAsignacion tbody');
    tbody.innerHTML = '';
    mesas.forEach(mesa => {
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

document.addEventListener('DOMContentLoaded', cargarDatos);
