function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}

window.alert = showAppMsg;

async function cargarBancos() {
    try {
        const resp = await fetch('../../api/dashboard/bancos/listar_bancos.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#tablaBancos tbody');
            tbody.innerHTML = '';
            data.resultado.forEach(p => {
                const tr = document.createElement('tr');
                tr.classList.add('table-row');
                tr.innerHTML = `
                    <td class="text-center">#${p.id}</td>
                    <td>${p.nombre}</td>
                    <td>
                        <button class="editar btn custom-btn btn-sm" data-id="${p.id}" data-nombre="${p.nombre}">Editar banco</button>
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
                    editarBanco(id, nombre);
                });
            });

            // Eliminar
            tbody.querySelectorAll('button.eliminar').forEach(btn => {
                btn.addEventListener('click', () => eliminarBanco(btn.dataset.id));
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

    const id = document.getElementById('bancoId').value;
    const nombre = document.getElementById('nombreBanco').value;

    const payload = { id: id ? parseInt(id) : null, nombre };

    const url = id ? '../../api/dashboard/bancos/actualizar_banco.php' : '../../api/dashboard/bancos/agregar_banco.php';

    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await resp.json();
        if (data.success) {
            alert(data.resultado?.mensaje || (id ? 'Banco actualizado' : 'Banco agregado'));
            cerrarModalAgregar();
            cargarBancos();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error en la operación');
    }
});

function editarBanco(id, nombre) {
    document.getElementById('bancoId').value = id;
    document.getElementById('nombreBanco').value = nombre;
    document.getElementById('modalTituloForm').textContent = 'Editar Banco';
    abrirModalAgregar();
}

async function eliminarBanco(id) {
    if (!confirm('¿Eliminar Banco?')) return;
    try {
        const resp = await fetch('../../api/dashboard/bancos/eliminar_banco.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
        });
        const data = await resp.json();
        if (data && data.success) {
            alert('Banco Eliminado');
            cargarBancos();
        } else {
            alert('Error', (data && data.mensaje) || 'No se pudo eliminar')
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
    document.getElementById('bancoId').value = '';
    document.getElementById('modalTituloForm').textContent = 'Agregar Banco';
}

document.addEventListener('DOMContentLoaded', () => {
    cargarBancos();
    document.getElementById('agregarBanco').addEventListener('click', abrirModalAgregar);
});
