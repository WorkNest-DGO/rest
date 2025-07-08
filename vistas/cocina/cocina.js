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
            const contCards = document.getElementById('pendientes-cards');
            contCards.innerHTML = '';
            let grupo = '';
            data.resultado.forEach(p => {
                if (p.destino !== grupo) {
                    const header = document.createElement('h5');
                    header.textContent = p.destino;
                    contCards.appendChild(header);
                    grupo = p.destino;
                }
                const t = tiempoTranscurrido(p.hora);
                const obs = p.observaciones ? ` <span title="${p.observaciones}">🛈</span>` : '';
                const card = document.createElement('div');
                card.className = 'menu-item';
                card.innerHTML = `
                    <div class="menu-img">
                        <img src="../../utils/img/menu-burger.jpg" alt="Image">
                    </div>
                    <div class="menu-text">
                        <h3>
                            <span>${p.producto}${obs}</span>
                            <strong style="background-color:${colorPorTiempo(t.minutos)}">${p.estado}</strong>
                        </h3>
                        <p>${p.cantidad} unidades - ${t.texto}</p>
                        ${botonPorEstado(p.estado, p.detalle_id, p.tipo)}
                    </div>`;
                contCards.appendChild(card);
                card.querySelectorAll('button.cambiar').forEach(btn => {
                    btn.addEventListener('click', () => cambiarEstado(btn.dataset.id, btn.dataset.sig));
                });
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
            const contCards = document.getElementById('entregados-cards');
            contCards.innerHTML = '';
            let grupo = '';
            data.resultado.forEach(p => {
                if (p.destino !== grupo) {
                    const header = document.createElement('h5');
                    header.textContent = p.destino;
                    contCards.appendChild(header);
                    grupo = p.destino;
                }
                const t = tiempoTranscurrido(p.hora);
                const card = document.createElement('div');
                card.className = 'menu-item';
                card.innerHTML = `
                    <div class="menu-img">
                        <img src="../../utils/img/menu-burger.jpg" alt="Image">
                    </div>
                    <div class="menu-text">
                        <h3>
                            <span>${p.producto}</span>
                            <strong style="background-color:${colorPorTiempo(t.minutos)}">${p.estado}</strong>
                        </h3>
                        <p>${p.cantidad} unidades - ${t.texto}</p>
                    </div>`;
                contCards.appendChild(card);
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
    document.querySelector('a[href="#Pendientes"]').addEventListener('click', () => {
        setTimeout(cargarPendientes, 10);
    });
    document.querySelector('a[href="#Entregados"]').addEventListener('click', () => {
        setTimeout(cargarEntregados, 10);
    });
});
