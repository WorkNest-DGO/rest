async function cargarMesas() {
    try {
        const resp = await fetch('../../api/mesas/listar_mesas.php');
        const data = await resp.json();
        const tablero = document.getElementById('tablero');
        if (data.success) {
            tablero.innerHTML = '';
            // Calcular mesas unidas
            const uniones = {};
            data.resultado.forEach(m => {
                if (m.mesa_principal_id) {
                    if (!uniones[m.mesa_principal_id]) uniones[m.mesa_principal_id] = [];
                    uniones[m.mesa_principal_id].push(m.id);
                }
            });

            data.resultado.forEach(m => {
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

                card.innerHTML = `
                    <input type="checkbox" class="seleccionar" data-id="${m.id}">
                    <h3>${m.nombre}</h3>
                    <p>Estado: ${m.estado}</p>
                    <p>${ventaTxt}</p>
                    <p>${unionTxt}</p>
                    <button class="detalles" data-venta="${m.venta_id || ''}" data-mesa="${m.id}" data-nombre="${m.nombre}" data-estado="${m.estado}">Detalles</button>
                    <button class="dividir" data-id="${m.id}">Dividir</button>
                    <button class="cambiar" data-id="${m.id}">Cambiar estado</button>
                `;
                tablero.appendChild(card);
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
                        btn.dataset.estado
                    )
                );
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

let productos = [];

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

document.addEventListener('DOMContentLoaded', () => {
    cargarCatalogo();
    cargarMesas();
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

async function verVenta(ventaId, mesaId, mesaNombre, estado) {
    if (!ventaId) {
        mostrarModalDetalle({ mesa: mesaNombre, mesero: '', productos: [] }, null, mesaId, estado);
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
            mostrarModalDetalle(data.resultado, ventaId, mesaId, estado);
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

function mostrarModalDetalle(datos, ventaId, mesaId, estado) {
    const modal = document.getElementById('modal-detalle');
    let html = `<h3>Mesa ${datos.mesa} - Venta ${ventaId || ''}</h3>`;
    html += `<table border="1"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th><th>Estatus</th><th></th><th></th></tr></thead><tbody>`;
    datos.productos.forEach(p => {
        const btnEliminar = (p.estatus_preparacion !== 'en preparación' && p.estatus_preparacion !== 'entregado')
            ? `<button class="eliminar" data-id="${p.id}">Eliminar</button>`
            : '';
        const btnEntregar = p.estatus_preparacion !== 'entregado'
            ? `<button class="entregar" data-id="${p.id}">Marcar como entregado</button>`
            : '';
        html += `<tr><td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td>${p.subtotal}</td><td>${p.estatus_preparacion}</td><td>${btnEliminar}</td><td>${btnEntregar}</td></tr>`;
    });
    html += `</tbody></table>`;
    html += `<h4>Agregar producto</h4>`;
    html += `<select id="nuevo_producto"></select>`;
    html += `<input type="number" id="nuevo_cantidad" value="1" min="1">`;
    const disabled = !ventaId && estado !== 'ocupada' ? 'disabled' : '';
    html += `<button id="agregarProductoVenta" data-venta="${ventaId || ''}" data-mesa="${mesaId}" data-estado="${estado}" ${disabled}>Agregar producto</button>`;
    html += ` <button id="cerrarModal">Cerrar</button>`;
    modal.innerHTML = html;
    modal.style.display = 'block';

    renderSelectProductos(document.getElementById('nuevo_producto'));

    modal.querySelectorAll('.eliminar').forEach(btn => {
        btn.addEventListener('click', () => eliminarProducto(btn.dataset.id, ventaId));
    });
    modal.querySelectorAll('.entregar').forEach(btn => {
        btn.addEventListener('click', () => marcarEntregado(btn.dataset.id, ventaId));
    });
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

async function agregarProductoVenta(ventaId, mesaId, estado) {
    if (!ventaId && estado !== 'ocupada') {
        alert('La mesa debe estar ocupada para iniciar una venta');
        return;
    }

    const select = document.getElementById('nuevo_producto');
    const cantidad = parseInt(document.getElementById('nuevo_cantidad').value);
    const productoId = parseInt(select.value);
    const prod = productos.find(p => p.id === productoId);
    const precio = prod ? parseFloat(prod.precio) : 0;

    if (isNaN(productoId) || isNaN(cantidad) || cantidad <= 0) {
        alert('Producto o cantidad inválida');
        return;
    }

    let currentVentaId = ventaId;

    if (!currentVentaId) {
        const crearPayload = {
            mesa_id: parseInt(mesaId),
            usuario_id: 2,
            productos: [{ producto_id: productoId, cantidad, precio_unitario: precio }]
        };
        try {
            const resp = await fetch('../../api/ventas/crear_venta.php', {
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

    verVenta(currentVentaId, mesaId, '', estado);
    await cargarMesas();
}

document.getElementById('btn-unir').addEventListener('click', unirSeleccionadas);
