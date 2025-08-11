let productos = [];
let meseros = [];
const meseroSeleccionado = {};
const usuarioActual = window.usuarioActual || { id: null, rol: '' };

async function cargarMesas() {
    try {
        const resp = await fetch('../../api/mesas/listar_mesas.php');
        const data = await resp.json();
        if (data.success) {
            const mesas = data.resultado;
            const mapa = {};

            mesas.forEach(m => {
                const uid = parseInt(m.usuario_id);
                if (uid) {
                    if (!mapa[uid]) {
                        mapa[uid] = { id: uid, nombre: m.mesero_nombre };
                    }
                }
            });

            const meserosUnicos = Object.values(mapa).sort((a, b) => a.nombre.localeCompare(b.nombre));
            meseros = meserosUnicos;
            renderKanban(meserosUnicos, mesas);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar mesas');
    }
}


function crearColumnas(container, listaMeseros) {
    if (!container) return;
    if (!Array.isArray(listaMeseros)) return;

    listaMeseros.forEach(me => {
        const li = document.createElement('li');
        li.className = 'drag-column drag-column-on-hold';
        li.dataset.meseroId = me.id;

        const header = document.createElement('span');
        header.className = 'drag-column-header';
        header.innerHTML = `<h2>${me.nombre}</h2>`;
        li.appendChild(header);

        const ul = document.createElement('ul');
        ul.className = 'drag-inner-list';
        li.appendChild(ul);

        container.appendChild(li);
    });

    const liSin = document.createElement('li');
    liSin.className = 'drag-column drag-column-on-hold';
    liSin.dataset.meseroId = 'sin';
    const headerSin = document.createElement('span');
    headerSin.className = 'drag-column-header';
    headerSin.innerHTML = '<h2>No asignado</h2>';
    liSin.appendChild(headerSin);
    const ulSin = document.createElement('ul');
    ulSin.className = 'drag-inner-list';
    liSin.appendChild(ulSin);
    container.appendChild(liSin);
}

/** Crea las columnas por mesero y agrega las tarjetas de mesas */
function renderKanban(listaMeseros, mesas) {
    const cont = document.getElementById('kanban-list');
    if (!cont) {
        console.error('Contenedor #kanban-list no encontrado');
        return;
    }
    cont.innerHTML = '';
    crearColumnas(cont, listaMeseros);

    const uniones = {};
    mesas.forEach(m => {
        if (m.mesa_principal_id) {
            if (!uniones[m.mesa_principal_id]) uniones[m.mesa_principal_id] = [];
            uniones[m.mesa_principal_id].push(m.id);
        }
    });

    const mapaMeseros = listaMeseros.reduce((acc, me) => {
        acc[String(me.id)] = me.nombre;
        return acc;
    }, {});

    // Asignar mesas a columnas

    mesas.forEach(m => {
        let col = cont.querySelector(`li[data-mesero-id="${m.usuario_id}"] .drag-inner-list`);
        if (!col) {
            col = cont.querySelector('li[data-mesero-id="sin"] .drag-inner-list');
        }
        if (!col) return;
        const card = document.createElement('li');
        card.className = 'drag-item mesa';
        card.classList.add(`estado-${m.estado}`);
        card.dataset.mesa = m.id;
        card.dataset.venta = m.venta_id || '';
        card.dataset.estado = m.estado;
        card.dataset.mesero = m.usuario_id && m.usuario_id !== 0 ? m.usuario_id : '';

        const unidas = uniones[m.id] || [];
        let unionTxt = '';
        if (m.mesa_principal_id) {
            unionTxt = `Unida a ${m.mesa_principal_id}`;
        } else if (unidas.length) {
            unionTxt = `Principal de: ${unidas.join(', ')}`;
        }

        const ventaTxt = m.venta_activa ? `Venta activa: ${m.venta_id}` : 'Sin venta';
        const meseroNombre = mapaMeseros[String(m.usuario_id)] || null;
        const meseroTxt = meseroNombre ? `Mesero: ${meseroNombre}` : 'Sin mesero asignado';
        const reservaTxt = m.estado_reserva === 'reservada' ? `Reservada: ${m.nombre_reserva} (${m.fecha_reserva})` : '';
        let ocupacionTxt = '';
        if (m.tiempo_ocupacion_inicio) {
            const inicio = new Date(m.tiempo_ocupacion_inicio.replace(' ', 'T'));
            const diff = Math.floor((Date.now() - inicio.getTime()) / 60000);
            ocupacionTxt = `Ocupada hace ${diff} min`;
        }

        card.innerHTML = `
            <input type="checkbox" class="seleccionar" data-id="${m.id}">
            <h3>${m.nombre}</h3>
            <p>Estado: ${m.estado}</p>
            <p>${ventaTxt}</p>
            <p>${meseroTxt}</p>
            <p>${unionTxt}</p>
            <p>${reservaTxt}</p>
            <p>${ocupacionTxt}</p>
            <button class="detalles">Detalles</button>
            <button class="dividir" data-id="${m.id}">Dividir</button>
            <button class="cambiar" data-id="${m.id}">Cambiar estado</button>
            <button class="ticket" data-mesa="${m.id}" data-nombre="${m.nombre}" data-venta="${m.venta_id || ''}">Enviar ticket</button>
        `;

        const puedeEditar = usuarioActual.rol === 'admin' || (m.usuario_id && parseInt(m.usuario_id) === usuarioActual.id);
        const btnCambiar = card.querySelector('button.cambiar');
        const btnDividir = card.querySelector('button.dividir');

        if (puedeEditar) {
            if (m.estado === 'libre' && m.estado_reserva === 'ninguna') {
                card.addEventListener('click', () => reservarMesa(m.id));
            }
            btnCambiar.addEventListener('click', ev => { ev.stopPropagation(); mostrarMenu(btnCambiar.dataset.id); });
            btnDividir.addEventListener('click', ev => { ev.stopPropagation(); dividirMesa(btnDividir.dataset.id); });
        } else {
            btnCambiar.disabled = true;
            btnDividir.disabled = true;
        }

        const btnDetalles = card.querySelector('button.detalles');
        btnDetalles.addEventListener('click', ev => {
            ev.stopPropagation();
            verVenta(card.dataset.venta, card.dataset.mesa, card.querySelector('h3').textContent, card.dataset.estado, card.dataset.mesero);
        });

        const btnTicket = card.querySelector('button.ticket');
        btnTicket.addEventListener('click', ev => {
            ev.stopPropagation();
            solicitarTicket(card.dataset.mesa, card.querySelector('h3').textContent, card.dataset.venta);
        });

        col.appendChild(card);
    });

    activarDrag();
}

function activarDrag() {
    const lists = Array.from(document.querySelectorAll('.drag-inner-list'));
    dragula(lists, {
        moves: (el) => {
            const mesaUsuarioId = parseInt(el.dataset.mesero) || null;
            return usuarioActual.rol === 'admin' || mesaUsuarioId === usuarioActual.id;
        }
    });
}

function mostrarMenu(id) {
    const nuevo = prompt('Nuevo estado (libre, ocupada, reservada):');
    if (nuevo) {
        cambiarEstado(id, nuevo);
    }
}

async function cambiarEstado(id, estado) {
    try {
        const resp = await fetch('../../api/mesas/cambiar_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mesa_id: parseInt(id), nuevo_estado: estado })
        });
        const data = await resp.json();
        if (data.success) {
            await cargarMesas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cambiar estado');
    }
}

function solicitarTicket(mesaId, nombre, ventaId) {
    if (!ventaId) {
        alert('La mesa no tiene venta activa');
        return;
    }
    fetch('../../api/mesas/enviar_ticket.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mesa_id: parseInt(mesaId) })
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('Ticket solicitado');
            } else {
                alert(d.mensaje);
            }
        })
        .catch(() => alert('Error al solicitar ticket'));
}

function textoEstado(e) {
    return (e || '').replace('_', ' ');
}


function renderSelectMeseros(select, seleccionado) {
    select.innerHTML = '<option value="">Sin mesero asignado</option>';
    meseros.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.nombre;
        if (seleccionado && parseInt(seleccionado) === parseInt(m.id)) {
            opt.selected = true;
        }
        select.appendChild(opt);
    });
}

async function cargarCatalogo() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            productos = data.resultado;
        }
    } catch (err) {
        console.error(err);
    }
}

function renderSelectProductos(select) {
    select.innerHTML = '<option value="">--Selecciona--</option>';
    productos.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = `${p.nombre} - $${p.precio}`;
        opt.dataset.precio = p.precio;
        select.appendChild(opt);
    });
}

function cerrarModal() {
    document.getElementById('modal-detalle').style.display = 'none';
}

function mostrarModalDetalle(datos, ventaId, mesaId, estado, meseroId) {
    const modal = document.getElementById('modal-detalle');
    let html = `<h3>Mesa ${datos.mesa} - Venta ${ventaId || ''}</h3>`;
    html += `<table border="1"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th><th>Estatus</th><th></th><th></th></tr></thead><tbody>`;
    datos.productos.forEach(p => {
        const est = p.estado_producto;
        const btnEliminar = (est !== 'en_preparacion' && est !== 'entregado')
            ? `<button class="eliminar" data-id="${p.id}">Eliminar</button>`
            : '';
        const puedeEntregar = est === 'listo';
        const btnEntregar = est !== 'entregado'
            ? `<button class="entregar" data-id="${p.id}" ${puedeEntregar ? '' : 'disabled'}>Marcar como entregado</button>`
            : '';
        html += `<tr><td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td>${p.subtotal}</td><td>${textoEstado(est)}</td><td>${btnEliminar}</td><td>${btnEntregar}</td></tr>`;
    });
    html += `</tbody></table>`;
    html += `<h4>Mesero</h4>`;
    html += `<select id="select_mesero" disabled></select>`;
    html += `<h4>Agregar producto</h4>`;
    html += `<select id="nuevo_producto"></select>`;
    html += `<input type="number" id="nuevo_cantidad" value="1" min="1">`;
    const disabled = !ventaId && estado !== 'ocupada' ? 'disabled' : '';
    html += `<button id="agregarProductoVenta" data-venta="${ventaId || ''}" data-mesa="${mesaId}" data-estado="${estado}" >Agregar producto</button>`;
    if (ventaId) {
        html += ` <button id="guardarMesero" data-venta="${ventaId}" hidden>Actualizar mesero</button>`;
    }
    html += ` <button id="cerrarModal">Cerrar</button>`;
    modal.innerHTML = html;
    modal.style.display = 'block';

    renderSelectMeseros(document.getElementById('select_mesero'), meseroSeleccionado[mesaId] || meseroId);
    renderSelectProductos(document.getElementById('nuevo_producto'));

    modal.querySelectorAll('.eliminar').forEach(btn => {
        btn.addEventListener('click', () => eliminarProducto(btn.dataset.id, ventaId));
    });
    modal.querySelectorAll('.entregar').forEach(btn => {
        btn.addEventListener('click', () => marcarEntregado(btn.dataset.id, ventaId));
    });
    const selMesero = modal.querySelector('#select_mesero');
    selMesero.addEventListener('change', () => {
        if (!ventaId) {
            meseroSeleccionado[mesaId] = parseInt(selMesero.value) || null;
        }
    });
    if (ventaId) {
        modal.querySelector('#guardarMesero').addEventListener('click', () => actualizarMesero(ventaId));
    }
    modal.querySelector('#agregarProductoVenta').addEventListener('click', () => agregarProductoVenta(ventaId, mesaId, estado));
    modal.querySelector('#cerrarModal').addEventListener('click', cerrarModal);
}

async function eliminarProducto(detalleId, ventaId) {
    if (!confirm('¿Eliminar producto?')) return;
    try {
        const resp = await fetch('../../api/mesas/eliminar_producto_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ detalle_id: parseInt(detalleId) })
        });
        const data = await resp.json();
        if (data.success) {
            verVenta(ventaId);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al eliminar');
    }
}

async function marcarEntregado(detalleId, ventaId) {
    try {
        const resp = await fetch('../../api/mesas/marcar_entregado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ detalle_id: parseInt(detalleId) })
        });
        const data = await resp.json();
        if (data.success) {
            verVenta(ventaId);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al actualizar');
    }
}

async function actualizarMesero(ventaId, usuarioId) {
    const select = document.getElementById('select_mesero');
    const valor = usuarioId !== undefined ? usuarioId : select.value;
    try {
        const resp = await fetch('../../api/ventas/cambiar_mesero.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                venta_id: parseInt(ventaId),
                usuario_id: valor === '' ? null : parseInt(valor)
            })
        });
        const data = await resp.json();
        if (!data.success) {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al actualizar mesero');
    }
}

async function agregarProductoVenta(ventaId, mesaId, estado) {
    if (!ventaId && estado !== 'ocupada') {
        alert('La mesa debe estar ocupada para iniciar una venta');
        return;
    }

    const select = document.getElementById('nuevo_producto');
    const cantidad = parseInt(document.getElementById('nuevo_cantidad').value);
    const productoId = parseInt(select.value);
    const selected = select.selectedOptions[0];
    const precio = selected ? parseFloat(selected.dataset.precio || '0') : 0;

    if (isNaN(productoId) || isNaN(cantidad) || cantidad <= 0) {
        alert('Producto o cantidad inválida');
        return;
    }

    let currentVentaId = ventaId;
    const meseroSelect = document.getElementById('select_mesero');
    const usuarioId = parseInt(meseroSelect.value) || meseroSeleccionado[mesaId];

    if (!currentVentaId) {
        try {
            const corteResp = await fetch('../../api/corte_caja/verificar_corte_abierto.php', {
                credentials: 'include'
            });
            const corteData = await corteResp.json();
            if (!corteData.success || !corteData.resultado.abierto) {
                alert('Debe abrir caja antes de iniciar una venta.');
                return;
            }
        } catch (err) {
            console.error(err);
            alert('Error al verificar caja');
            return;
        }
        meseroSeleccionado[mesaId] = usuarioId || null;
        const crearPayload = {
            mesa_id: parseInt(mesaId),
            productos: [{ producto_id: productoId, cantidad, precio_unitario: precio }]
        };
        try {
            const resp = await fetch('../../api/ventas/crear_venta_mesas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(crearPayload)
            });
            const data = await resp.json();
            if (data.success) {
                currentVentaId = data.resultado.venta_id;
            } else {
                alert(data.mensaje);
                return;
            }
        } catch (err) {
            console.error(err);
            alert('Error al crear venta');
            return;
        }
    } else {
        if (usuarioId && usuarioId !== meseroSeleccionado[mesaId]) {
            await actualizarMesero(currentVentaId, usuarioId);
            meseroSeleccionado[mesaId] = usuarioId;
        }
        const payload = {
            venta_id: currentVentaId,
            producto_id: productoId,
            cantidad,
            precio_unitario: precio
        };
        try {
            const resp = await fetch('../../api/mesas/agregar_producto_venta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();
            if (!data.success) {
                alert(data.mensaje);
                return;
            }
        } catch (err) {
            console.error(err);
            alert('Error al agregar producto');
            return;
        }
    }

    verVenta(currentVentaId, mesaId, '', estado, meseroSeleccionado[mesaId]);
    await cargarMesas();
}

async function verVenta(ventaId, mesaId, mesaNombre, estado, meseroId) {
    if (!ventaId) {
        try {
            const resp = await fetch('../../api/corte_caja/verificar_corte_abierto.php', {
                credentials: 'include'
            });
            const corte = await resp.json();
            if (!corte.success || !corte.resultado.abierto) {
                alert('Debe abrir caja antes de iniciar una venta.');
                return;
            }
        } catch (err) {
            console.error(err);
            alert('Error al verificar caja');
            return;
        }
        mostrarModalDetalle({ mesa: mesaNombre, mesero: '', productos: [] }, null, mesaId, estado, meseroId);
        return;
    }
    try {
        const resp = await fetch('../../api/mesas/detalle_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(ventaId) })
        });
        const data = await resp.json();
        if (data.success) {
            mostrarModalDetalle(data.resultado, ventaId, mesaId, estado, meseroId);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
    }
}

async function dividirMesa(id) {
    try {
        const resp = await fetch('../../api/mesas/dividir_mesa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mesa_id: parseInt(id) })
        });
        const data = await resp.json();
        if (data.success) {
            await cargarMesas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al dividir mesa');
    }
}

async function unirSeleccionadas() {
    const seleccionadas = Array.from(document.querySelectorAll('.seleccionar:checked')).map(c => parseInt(c.dataset.id));
    if (seleccionadas.length < 2) {
        alert('Selecciona al menos dos mesas');
        return;
    }
    const principal = parseInt(prompt('ID de mesa principal', seleccionadas[0]));
    const otras = seleccionadas.filter(id => id !== principal);
    if (otras.length === 0) {
        alert('Debes seleccionar mesas adicionales aparte de la principal');
        return;
    }
    try {
        const resp = await fetch('../../api/mesas/unir_mesas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ principal_id: principal, mesas: otras })
        });
        const data = await resp.json();
        if (data.success) {
            await cargarMesas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al unir mesas');
    }
}

document.getElementById('btn-unir').addEventListener('click', unirSeleccionadas);

async function reservarMesa(id) {
    const nombre = prompt('Nombre para la reserva:');
    if (!nombre) return;
    const fecha = prompt('Fecha y hora (YYYY-MM-DD HH:MM):');
    if (!fecha) return;
    try {
        const resp = await fetch('../../api/mesas/cambiar_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mesa_id: parseInt(id),
                nuevo_estado: 'reservada',
                nombre_reserva: nombre,
                fecha_reserva: fecha
            })
        });
        const data = await resp.json();
        if (data.success) {
            cargarMesas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al reservar mesa');
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        await cargarCatalogo();
        await cargarMesas();
    } catch (err) {
        console.error(err);
    }
});
