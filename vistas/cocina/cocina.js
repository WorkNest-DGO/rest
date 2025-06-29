async function cargarPendientes() {
    try {
        const resp = await fetch('../../api/cocina/listar_pendientes.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#tabla-pendientes tbody');
            tbody.innerHTML = '';
            data.resultado.forEach(p => {
                const tr = document.createElement('tr');
                const inicioVisible = p.estatus === 'pendiente';
                const listoVisible = p.estatus === 'en preparación';
                tr.innerHTML = `
                    <td>${p.mesa}</td>
                    <td>${p.producto}</td>
                    <td>${p.cantidad}</td>
                    <td>${p.hora}</td>
                    <td><span class="${p.estatus.replace(/\s/g,'-')}">${p.estatus}</span></td>
                    <td>
                        ${inicioVisible ? `<button class="iniciar" data-id="${p.detalle_id}">Iniciar</button>` : ''}
                        ${listoVisible ? `<button class="listo" data-id="${p.detalle_id}">Listo</button>` : ''}
                    </td>
                `;
                tbody.appendChild(tr);
            });
            tbody.querySelectorAll('button.iniciar').forEach(btn => {
                btn.addEventListener('click', () => cambiarEstado(btn.dataset.id, 'en preparación'));
            });
            tbody.querySelectorAll('button.listo').forEach(btn => {
                btn.addEventListener('click', () => cambiarEstado(btn.dataset.id, 'listo'));
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar');
    }
}

async function cambiarEstado(detalleId, nuevo) {
    try {
        const resp = await fetch('../../api/cocina/cambiar_estado_producto.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ detalle_id: parseInt(detalleId), nuevo_estado: nuevo })
        });
        const data = await resp.json();
        if (data.success) {
            cargarPendientes();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al actualizar');
    }
}

document.addEventListener('DOMContentLoaded', cargarPendientes);
