let areaFiltro = 'todas';

async function cargarMesas() {
    try {
        const resp = await fetch('../../api/mesas/listar_mesas.php');
        const data = await resp.json();
        const tablero = document.getElementById('tablero');
        if (data.success) {
            tablero.innerHTML = '';
            const uniones = {};
            data.resultado.forEach(m => {
                if (m.mesa_principal_id) {
                    if (!uniones[m.mesa_principal_id]) uniones[m.mesa_principal_id] = [];
                    uniones[m.mesa_principal_id].push(m.id);
                }
            });

            const areas = {};
            data.resultado.forEach(m => {
                const nombre = m.area || 'Sin área';
                const key = m.area_id !== null ? String(m.area_id) : nombre;
                if (!areas[key]) areas[key] = { nombre, mesas: [] };
                areas[key].mesas.push(m);
            });

            const selectArea = document.getElementById('filtro-area');
            selectArea.innerHTML = '<option value="todas">Todas las áreas</option>';
            Object.entries(areas).forEach(([key, a]) => {
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = a.nombre;
                if (key === areaFiltro) opt.selected = true;
                selectArea.appendChild(opt);
            });

            Object.entries(areas).forEach(([key, areaInfo]) => {
                if (areaFiltro !== 'todas' && areaFiltro !== key) return;
                const seccion = document.createElement('section');
                const h2 = document.createElement('h2');
                h2.textContent = areaInfo.nombre;
                seccion.appendChild(h2);
                const cont = document.createElement('div');
                seccion.appendChild(cont);
                tablero.appendChild(seccion);

                areaInfo.mesas.forEach(m => {
                    const card = document.createElement('div');
                    card.className = 'mesa';

                    const unidas = uniones[m.id] || [];
                    let unionTxt = '';
                    if (m.mesa_principal_id) {
                        unionTxt = `Unida a ${m.mesa_principal_id}`;
                    } else if (unidas.length) {
                        unionTxt = `Principal de: ${unidas.join(', ')}`;
                    }

                    const ventaTxt = m.venta_activa ? `Venta activa: ${m.venta_id}` : 'Sin venta';
                    const meseroTxt = m.mesero_nombre ? `Mesero: ${m.mesero_nombre}` : 'Sin mesero asignado';
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
                        <button class="detalles" data-venta="${m.venta_id || ''}" data-mesa="${m.id}" data-nombre="${m.nombre}" data-estado="${m.estado}" data-mesero="${m.mesero_id || ''}">Detalles</button>
                        <button class="dividir" data-id="${m.id}">Dividir</button>
                        <button class="cambiar" data-id="${m.id}">Cambiar estado</button>
                        <button class="ticket" data-mesa="${m.id}" data-nombre="${m.nombre}" data-venta="${m.venta_id || ''}">Enviar ticket</button>
                    `;

                    if (m.estado === 'libre' && m.estado_reserva === 'ninguna') {
                        card.addEventListener('click', () => reservarMesa(m.id));
                    }

                    cont.appendChild(card);
                });
            });

            tablero.querySelectorAll('button.cambiar').forEach(btn => {
                btn.addEventListener('click', () => mostrarMenu(btn.dataset.id));
            });
            tablero.querySelectorAll('button.dividir').forEach(btn => {
                btn.addEventListener('click', () => dividirMesa(btn.dataset.id));
            });
            tablero.querySelectorAll('button.detalles').forEach(btn => {
                btn.addEventListener('click', () =>
                    verVenta(
                        btn.dataset.venta,
                        btn.dataset.mesa,
                        btn.dataset.nombre,
                        btn.dataset.estado,
                        btn.dataset.mesero
                    )
                );
            });
            tablero.querySelectorAll('button.ticket').forEach(btn => {
                btn.addEventListener('click', () => solicitarTicket(btn.dataset.mesa, btn.dataset.nombre, btn.dataset.venta));
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar mesas');
    }
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

const meseroSeleccionado = {};

async function fetchMeseros() {
    try {
        const resp = await fetch('../../api/usuarios/listar_meseros.php');
        const data = await resp.json();
        return data.success ? data.resultado : [];
    } catch (err) {
        console.error(err);
        return [];
    }
}

async function fetchCatalogo() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        return data.success ? data.resultado : [];
    } catch (err) {
        console.error(err);
        return [];
    }
}

function renderSelectMeseros(select, meseros, seleccionado) {
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

function textoEstado(e) {
    return (e || '').replace('_', ' ');
}

document.addEventListener('DOMContentLoaded', () => {
    cargarMesas();
    document.getElementById('filtro-area').addEventListener('change', e => {
        areaFiltro = e.target.value;
        cargarMesas();
    });
});

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
        await mostrarModalDetalle({ mesa: mesaNombre, mesero: '', productos: [] }, null, mesaId, estado, meseroId);
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
            await mostrarModalDetalle(data.resultado, ventaId, mesaId, estado, meseroId);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
    }
}

function cerrarModal() {
    document.getElementById('modal-detalle').style.display = 'none';
}

function renderSelectProductos(select, productos) {
    select.innerHTML = '<option value="">--Selecciona--</option>';
    productos.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = `${p.nombre} - $${p.precio}`;
        opt.dataset.precio = p.precio;
        select.appendChild(opt);
    });
}

async function mostrarModalDetalle(datos, ventaId, mesaId, estado, meseroId) {
    const modal = document.getElementById('modal-detalle');
    let html = `<h3>Mesa ${datos.mesa} - Venta ${ventaId || ''}</h3>`;
    html += `<table border="1"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th><th>Estatus</th><th></th><th></th></tr></thead><tbody>`;
    datos.productos.forEach(p => {
        const estado = p.estado_producto;
        const btnEliminar = (estado !== 'en_preparacion' && estado !== 'entregado')
            ? `<button class="eliminar" data-id="${p.id}">Eliminar</button>`
            : '';
        const puedeEntregar = estado === 'listo';
        const btnEntregar = estado !== 'entregado'
            ? `<button class="entregar" data-id="${p.id}" ${puedeEntregar ? '' : 'disabled'}>Marcar como entregado</button>`
            : '';
        html += `<tr><td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td>${p.subtotal}</td><td>${textoEstado(estado)}</td><td>${btnEliminar}</td><td>${btnEntregar}</td></tr>`;
    });
    html += `</tbody></table>`;
    html += `<h4>Mesero</h4>`;
    html += `<select id="select_mesero"></select>`;
    html += `<h4>Agregar producto</h4>`;
    html += `<select id="nuevo_producto"></select>`;
    html += `<input type="number" id="nuevo_cantidad" value="1" min="1">`;
    const disabled = !ventaId && estado !== 'ocupada' ? 'disabled' : '';
    html += `<button id="agregarProductoVenta" data-venta="${ventaId || ''}" data-mesa="${mesaId}" data-estado="${estado}" ${disabled}>Agregar producto</button>`;
    if (ventaId) {
        html += ` <button id="guardarMesero" data-venta="${ventaId}">Actualizar mesero</button>`;
    }
    html += ` <button id="cerrarModal">Cerrar</button>`;
    modal.innerHTML = html;
    modal.style.display = 'block';

    const [meseros, productos] = await Promise.all([fetchMeseros(), fetchCatalogo()]);

    renderSelectMeseros(
        document.getElementById('select_mesero'),
        meseros,
        meseroSeleccionado[mesaId] || meseroId
    );
    renderSelectProductos(
        document.getElementById('nuevo_producto'),
        productos
    );

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
            const resp = await fetch('../../api/ventas/crear_venta_mesa.php', {
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
