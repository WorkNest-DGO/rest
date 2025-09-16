function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}

window.alert = showAppMsg; 

async function cargarSedes() {
    try {
        const resp = await fetch('../../api/dashboard/sedes/listar_sedes.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#tablaSedes tbody');
            tbody.innerHTML = '';
            data.resultado.forEach(p => {
                const tr = document.createElement('tr');
                tr.classList.add('table-row');
                tr.innerHTML = `
                    <td class="text-center">#${p.id}</td>
                    <td>${p.nombre}</td>
                    <td>${p.direccion}</td>
                    <td>${p.rfc}</td>
                    <td>${p.telefono}</td>
                    <td>${p.correo}</td>
                    <td>${p.web}</td>
                    <td>${p.activo == 1 ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-danger">No</span>'}</td>
                    <td>
                        <button class="editar btn custom-btn btn-sm" data-id="${p.id}" data-nombre="${p.nombre}" data-direccion="${p.direccion}" data-rfc="${p.rfc}" data-telefono="${p.telefono}" data-correo="${p.correo}" data-web="${p.web}" data-activo="${p.activo}">Editar sede</button>
                        <button class="eliminar btn custom-btn btn-sm" data-id="${p.id}">Eliminar</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Editar
            tbody.querySelectorAll('button.editar').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    const nombre = btn.dataset.nombre;
                    const direccion = btn.dataset.direccion;
                    const rfc = btn.dataset.rfc;
                    const telefono = btn.dataset.telefono;
                    const correo = btn.dataset.correo;
                    const web = btn.dataset.web;
                    const activo = btn.dataset.activo;
                    editarSede(id, nombre, direccion, rfc, telefono, correo, web, activo);
                });
            });

            // Eliminar
            tbody.querySelectorAll('button.eliminar').forEach(btn => {
                btn.addEventListener('click', () => eliminarSede(btn.dataset.id));
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar bancos');
    }
}

document.getElementById('formAgregar').addEventListener('submit', async (e) => {
    e.preventDefault();

    const id = document.getElementById('sedeId').value;
    const nombre = document.getElementById('nombreSede').value;
    const direccion = document.getElementById('direccionSede').value;
    const rfc = document.getElementById('rfcSede').value;
    const telefono = document.getElementById('telefonoSede').value;
    const correo = document.getElementById('correoSede').value;
    const web = document.getElementById('webSede').value;
    const activo = document.getElementById('activoSede').value;

    const payload = { id: id ? parseInt(id) : null, nombre, direccion, rfc, telefono, correo, web, activo: parseInt(activo)};

    const url = id ? '../../api/dashboard/sedes/actualizar_sede.php' : '../../api/dashboard/sedes/agregar_sede.php';

    //console.log(payload);

    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await resp.json();
        if (data.success) {
            alert(data.resultado?.mensaje || (id ? 'Sede actualizada' : 'Sede agregada'));
            cerrarModalAgregar();
            cargarSedes();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error en la operación');
    }
});

function editarSede(id, nombre, direccion, rfc, telefono, correo, web, activo) {
    document.getElementById('sedeId').value = id;
    document.getElementById('nombreSede').value = nombre;
    document.getElementById('direccionSede').value = direccion;
    document.getElementById('rfcSede').value = rfc;
    document.getElementById('telefonoSede').value = telefono;
    document.getElementById('correoSede').value = correo;
    document.getElementById('webSede').value = web;
    document.getElementById('activoSede').value = activo;
    document.getElementById('modalTituloForm').textContent = 'Editar Sede';
    abrirModalAgregar();
}

async function eliminarSede(id) {
    if (!confirm('¿Eliminar Sede?')) return;
    try {
        const resp = await fetch('../../api/dashboard/sedes/eliminar_sede.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
        });
        const data = await resp.json();
        if (data && data.success) {
            alert('Sede eliminada');
            cargarSedes();
        } else {
            alert('Error', (data && data.mensaje) || 'No se pudo eliminar');
        }
    } catch (err) {
        console.error(err);
        alert('Error', 'Error al conectar con el servidor');
    }
}


function abrirModalAgregar() {
    showModal('#modalAgregar');
}

function cerrarModalAgregar() {
    hideModal('#modalAgregar');
    document.getElementById('formAgregar').reset();
    document.getElementById('sedeId').value = '';
    document.getElementById('modalTituloForm').textContent = 'Agregar Banco';
}


document.addEventListener('DOMContentLoaded', () => {
    cargarSedes();
    document.getElementById('agregarSede').addEventListener('click', abrirModalAgregar);
});
