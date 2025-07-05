function tiempoTranscurrido(fecha) {
    const minutos = Math.floor((Date.now() - new Date(fecha).getTime()) / 60000);
    return {
        texto: `Hace ${minutos} min`,
        minutos
    };
}

function colorPorTiempo(min) {
    if (min < 5) return 'lightgreen';
    if (min < 10) return 'khaki';
    return '#f8d7da';
}

function botonPorEstado(estado, id, tipo) {
    switch (estado) {
        case 'pendiente':
            return `<button class="cambiar" data-id="${id}" data-sig="en_preparacion">Iniciar</button>`;
        case 'en_preparacion':
            return `<button class="cambiar" data-id="${id}" data-sig="listo">Listo</button>`;
        case 'listo':
            if (tipo === 'domicilio') {
                return `<button class="cambiar" data-id="${id}" data-sig="entregado">Entregar</button>`;
            }
            return '';
        default:
            return '';
    }
}

async function cargarPendientes() {
    try {
        const resp = await fetch('../../api/cocina/listar_pendientes.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#tabla-pendientes tbody');
            tbody.innerHTML = '';
            let grupo = '';
            data.resultado.forEach(p => {
                if (p.destino !== grupo) {
                    const trg = document.createElement('tr');
                    trg.innerHTML = `<td colspan="5"><strong>${p.destino}</strong></td>`;
                    tbody.appendChild(trg);
                    grupo = p.destino;
                }
                const tr = document.createElement('tr');
                const t = tiempoTranscurrido(p.hora);
                const obs = p.observaciones ? ` <span title="${p.observaciones}">🛈</span>` : '';
                tr.innerHTML = `
                    <td>${p.producto}${obs}</td>
                    <td>${p.cantidad}</td>
                    <td>${t.texto}</td>
                    <td>${p.estado}</td>
                    <td>${botonPorEstado(p.estado, p.detalle_id, p.tipo)}</td>`;
                tr.style.backgroundColor = colorPorTiempo(t.minutos);
                tbody.appendChild(tr);
            });
            tbody.querySelectorAll('button.cambiar').forEach(btn => {
                btn.addEventListener('click', () => cambiarEstado(btn.dataset.id, btn.dataset.sig));
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar');
    }
}

async function cargarEntregados() {
    try {
        const resp = await fetch('../../api/cocina/listar_entregados.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#tabla-entregados tbody');
            tbody.innerHTML = '';
            let grupo = '';
            data.resultado.forEach(p => {
                if (p.destino !== grupo) {
                    const trg = document.createElement('tr');
                    trg.innerHTML = `<td colspan="4"><strong>${p.destino}</strong></td>`;
                    tbody.appendChild(trg);
                    grupo = p.destino;
                }
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${p.producto}</td><td>${p.cantidad}</td><td>${p.hora}</td>`;
                tbody.appendChild(tr);
            });
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar entregados');
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

document.addEventListener('DOMContentLoaded', () => {
    cargarPendientes();
    document.getElementById('btn-pendientes').addEventListener('click', () => {
        document.getElementById('seccion-pendientes').style.display = 'block';
        document.getElementById('seccion-entregados').style.display = 'none';
        cargarPendientes();
    });
    document.getElementById('btn-entregados').addEventListener('click', () => {
        document.getElementById('seccion-pendientes').style.display = 'none';
        document.getElementById('seccion-entregados').style.display = 'block';
        cargarEntregados();
    });
});
