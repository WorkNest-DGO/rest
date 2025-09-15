function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}

window.alert = showAppMsg;

async function cargarTarjetas() {
    try {
        const resp = await fetch('../../api/dashboard/catalogo_tarjetas/listar_tarjetas.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#tablaTarjetas tbody');
            tbody.innerHTML = '';
            data.resultado.forEach(p => {
                const tr = document.createElement('tr');
                tr.classList.add('table-row');
                tr.innerHTML = `
                    <td class="text-center">#${p.id}</td>
                    <td>${p.nombre}</td>
                    <td>
                        <button class="editar btn custom-btn btn-sm" data-id="${p.id}" data-nombre="${p.nombre}">Editar tarjeta</button>
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
                    editarTarjeta(id, nombre);
                });
            });

            // Eliminar
            tbody.querySelectorAll('button.eliminar').forEach(btn => {
                btn.addEventListener('click', () => eliminarTarjeta(btn.dataset.id));
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar tarjetas');
    }
}

document.getElementById('formAgregar').addEventListener('submit', async (e) => {
    e.preventDefault();

    const id = document.getElementById('tarjetaId').value;
    const nombre = document.getElementById('nombreTarjeta').value;

    const payload = { id: id ? parseInt(id) : null, nombre };

    const url = id ? '../../api/dashboard/catalogo_tarjetas/actualizar_tarjeta.php' : '../../api/dashboard/catalogo_tarjetas/agregar_tarjeta.php';

    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await resp.json();
        if (data.success) {
            alert(data.resultado?.mensaje || (id ? 'Tarjeta actualizado' : 'Tarjeta agregado'));
            cerrarModalAgregar();
            cargarTarjetas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error en la operación');
    }
});

function editarTarjeta(id, nombre) {
    document.getElementById('tarjetaId').value = id;
    document.getElementById('nombreTarjeta').value = nombre;
    document.getElementById('modalTituloForm').textContent = 'Editar Tarjeta';
    abrirModalAgregar();
}

async function eliminarTarjeta(id) {
    if (!confirm('¿Eliminar Tarjeta?')) return;
    try {
        const resp = await fetch('../../api/dashboard/catalogo_tarjetas/eliminar_tarjeta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
        });
        const data = await resp.json();
        if (data && data.success) {
            alert('Tarjeta eliminada');
            cargarTarjetas();
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
    document.getElementById('tarjetaId').value = '';
    document.getElementById('modalTituloForm').textContent = 'Agregar Tarjeta';
}

document.addEventListener('DOMContentLoaded', () => {
    cargarTarjetas();
    document.getElementById('agregarTarjeta').addEventListener('click', abrirModalAgregar);
});
