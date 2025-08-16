function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;

let rutas = [];

async function cargarRutas() {
    try {
        const resp = await fetch('../../api/rutas/listar_rutas.php');
        const data = await resp.json();
        const tbody = document.querySelector('#tablaRutas tbody');
        tbody.innerHTML = '';
        if (data.success) {
            rutas = data.resultado || [];
            rutas.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${r.nombre}</td><td>${r.path}</td><td>${r.tipo}</td><td>${r.grupo ?? ''}</td><td>${r.orden}</td>`;
                const tdAcc = document.createElement('td');
                const btnE = document.createElement('button');
                btnE.className = 'btn custom-btn me-2';
                btnE.textContent = 'Editar';
                btnE.onclick = () => editarRuta(r.nombre);
                const btnD = document.createElement('button');
                btnD.className = 'btn custom-btn';
                btnD.textContent = 'Eliminar';
                btnD.onclick = () => eliminarRuta(r.nombre);
                tdAcc.appendChild(btnE);
                tdAcc.appendChild(btnD);
                tr.appendChild(tdAcc);
                tbody.appendChild(tr);
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar rutas');
    }
}

document.getElementById('btnAgregar').onclick = () => {
    document.getElementById('formRuta').reset();
    document.getElementById('nombreOriginal').value = '';
    document.getElementById('orden').value = 0;
    showModal('#modalRuta');
};

document.getElementById('formRuta').onsubmit = async ev => {
    ev.preventDefault();
    const nombreOriginal = document.getElementById('nombreOriginal').value;
    const payload = {
        nombre: nombreOriginal || document.getElementById('nombre').value,
        nuevo_nombre: document.getElementById('nombre').value,
        path: document.getElementById('path').value,
        tipo: document.getElementById('tipo').value,
        grupo: document.getElementById('grupo').value,
        orden: parseInt(document.getElementById('orden').value) || 0
    };
    let url = '../../api/rutas/agregar_ruta.php';
    if (nombreOriginal) {
        url = '../../api/rutas/editar_ruta.php';
    } else {
        delete payload.nuevo_nombre; // when adding not needed
    }
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        alert(data.mensaje);
        if (data.success) {
            hideModal('#modalRuta');
            cargarRutas();
        }
    } catch (err) {
        console.error(err);
        alert('Error al guardar ruta');
    }
};

function editarRuta(nombre) {
    const r = rutas.find(x => x.nombre === nombre);
    if (!r) return;
    document.getElementById('nombreOriginal').value = r.nombre;
    document.getElementById('nombre').value = r.nombre;
    document.getElementById('path').value = r.path;
    document.getElementById('tipo').value = r.tipo;
    document.getElementById('grupo').value = r.grupo ?? '';
    document.getElementById('orden').value = r.orden;
    showModal('#modalRuta');
}

async function eliminarRuta(nombre) {
    if (!confirm('¿Eliminar ruta?')) return;
    try {
        const resp = await fetch('../../api/rutas/eliminar_ruta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre })
        });
        const data = await resp.json();
        alert(data.mensaje);
        if (data.success) cargarRutas();
    } catch (err) {
        console.error(err);
        alert('Error al eliminar ruta');
    }
}

window.addEventListener('DOMContentLoaded', cargarRutas);
