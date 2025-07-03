async function cargarHistorial() {
    try {
        const resp = await fetch('../../api/ventas/listar_ventas.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#historial tbody');
            tbody.innerHTML = '';
            ventasData = {};
            data.resultado.forEach(v => {
                const id = v.venta_id || v.id; // compatibilidad con vista
                ventasData[id] = v;
                const row = document.createElement('tr');
                const accion = v.estatus !== 'cancelada'
                    ? `<button class="cancelar" data-id="${id}">Cancelar</button>`
                    : '';
                const destino = v.tipo_entrega === 'mesa' ? v.mesa : v.repartidor;
                const entregado = v.tipo_entrega === 'domicilio'
                    ? (parseInt(v.entregado) === 1 ? 'Entregado' : 'No entregado')
                    : 'N/A';
                row.innerHTML = `
                    <td>${id}</td>
                    <td>${v.fecha}</td>
                    <td>${v.total}</td>
                    <td>${v.tipo_entrega}</td>
                    <td>${destino || ''}</td>
                    <td>${v.estatus}</td>
                    <td>${entregado}</td>
                    <td><button class="detalles" data-id="${id}">Ver detalles</button></td>
                    <td>${accion}</td>
                `;
                tbody.appendChild(row);
            });
            tbody.querySelectorAll('button.cancelar').forEach(btn => {
                btn.addEventListener('click', () => cancelarVenta(btn.dataset.id));
            });
            tbody.querySelectorAll('button.detalles').forEach(btn => {
                btn.addEventListener('click', () => verDetalles(btn.dataset.id));
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar ventas');
    }
}

const usuarioId = window.usuarioId || 1; // ID del cajero proveniente de la sesión
let corteIdActual = null;
let catalogo = [];
let productos = [];
let ventasData = {};
let repartidores = [];
let ticketRequests = [];

function deshabilitarCobro() {
    document.querySelectorAll('#formVenta input, #formVenta select, #formVenta button')
        .forEach(el => {
            if (!el.closest('#controlCaja')) {
                el.disabled = true;
            }
        });
}

function habilitarCobro() {
    document.querySelectorAll('#formVenta input, #formVenta select, #formVenta button')
        .forEach(el => {
            if (!el.closest('#controlCaja')) {
                el.disabled = false;
            }
        });
}

async function verificarCorte() {
fetch('../../api/corte_caja/verificar_corte_abierto.php', {
  credentials: 'include'
})
  .then(resp => resp.json())
  .then(data => {
    const cont = document.getElementById('controlCaja');
    cont.innerHTML = '';

    if (data.success && data.resultado.abierto) {
      corteIdActual = data.resultado.corte_id;
      cont.innerHTML = `<button id="btnCerrarCaja">Cerrar caja</button>`;
      document.getElementById('btnCerrarCaja').addEventListener('click', cerrarCaja);
      habilitarCobro();
    } else {
      cont.innerHTML = `<button id="btnAbrirCaja">Abrir caja</button>`;
      document.getElementById('btnAbrirCaja').addEventListener('click', abrirCaja);
      deshabilitarCobro();
    }
  });

}

async function abrirCaja() {
    const monto = prompt('Indica fondo de caja:');
    if (monto === null || monto === '') {
        alert('Debes indicar un monto');
        return;
    }
    try {
        const resp = await fetch('../../api/corte_caja/iniciar_corte.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ usuario_id: usuarioId, fondo_inicial: parseFloat(monto) })
        });
        const data = await resp.json();
        if (data.success) {
            corteIdActual = data.resultado ? data.resultado.corte_id : data.corte_id;
            await verificarCorte();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al abrir caja');
    }
}

async function cerrarCaja() {
    try {
        deshabilitarCobro();
        const resp = await fetch('../../api/corte_caja/cerrar_corte.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ corte_id: corteIdActual, usuario_id: usuarioId, observaciones: '' })
        });
        const data = await resp.json();
        if (data.success) {
            corteIdActual = null;
            alert('Caja cerrada correctamente');
            await verificarCorte();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cerrar caja');
    }
}

async function cargarRepartidores() {
    try {
        const resp = await fetch('../../api/repartidores/listar_repartidores.php');
        const data = await resp.json();
        if (data.success) {
            repartidores = data.resultado;
            const select = document.getElementById('repartidor_id');
            select.innerHTML = '<option value="">--Selecciona--</option>';
            repartidores.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.id;
                opt.textContent = r.nombre;
                select.appendChild(opt);
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar repartidores');
    }
}

async function cargarMeseros() {
    try {
        const resp = await fetch('../../api/usuarios/listar_meseros.php');
        const data = await resp.json();
        if (data.success) {
            const select = document.getElementById('usuario_id');
            select.innerHTML = '<option value="">--Selecciona--</option>';
            data.resultado.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.nombre;
                select.appendChild(opt);
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar meseros');
    }
}

async function cargarProductos() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            catalogo = data.resultado;
            productos = data.resultado;
            const selects = document.querySelectorAll('#productos select.producto');
            selects.forEach(select => {
                select.innerHTML = '<option value="">--Selecciona--</option>';
                catalogo.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nombre;
                    opt.dataset.precio = p.precio;
                    opt.dataset.existencia = p.existencia;
                    select.appendChild(opt);
                });
                select.addEventListener('change', () => {
                    actualizarPrecio(select);
                    const cantInput = select.closest('tr').querySelector('.cantidad');
                    const exist = select.selectedOptions[0].dataset.existencia;
                    if (exist) {
                        cantInput.max = exist;
                    } else {
                        cantInput.removeAttribute('max');
                    }
                    validarInventario();
                });
            });
            document.querySelectorAll('#productos .cantidad').forEach(inp => {
                const select = inp.closest('tr').querySelector('.producto');
                inp.addEventListener('input', () => {
                    manejarCantidad(inp, select);
                    validarInventario();
                });
            });
            validarInventario();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar productos');
    }
}

function actualizarPrecio(select) {
    const row = select.closest('tr');
    const precioInput = row.querySelector('.precio');
    const cantidadInput = row.querySelector('.cantidad');
    const productoId = select.value;
    const producto = productos.find(p => parseInt(p.id) === parseInt(productoId));
    if (producto) {
        const cant = parseInt(cantidadInput.value) || 1;
        precioInput.dataset.unitario = producto.precio;
        precioInput.value = (cant * parseFloat(producto.precio)).toFixed(2);
        if (!cantidadInput.value || parseInt(cantidadInput.value) === 0) {
            cantidadInput.value = 1;
        }
        cantidadInput.max = producto.existencia;
    } else {
        precioInput.value = '';
        delete precioInput.dataset.unitario;
        cantidadInput.removeAttribute('max');
    }
}

function manejarCantidad(input, select) {
    let val = parseInt(input.value) || 0;
    if (val === 0) {
        const quitar = confirm('Cantidad es 0. ¿Quitar producto?');
        if (quitar) {
            input.closest('tr').remove();
            return;
        }
        val = 1;
        input.value = 1;
    }
    const max = parseInt(input.max || 0);
    if (max && val > max) {
        alert(`Solo hay ${max} unidades disponibles`);
        val = max;
        input.value = max;
    }
    actualizarPrecio(select || input.closest('tr').querySelector('.producto'));
    validarInventario();
}

function validarInventario() {
    const rows = document.querySelectorAll('#productos tbody tr');
    const totales = {};
    rows.forEach(r => {
        const id = parseInt(r.querySelector('.producto').value);
        const cant = parseInt(r.querySelector('.cantidad').value) || 0;
        if (!isNaN(id)) {
            totales[id] = (totales[id] || 0) + cant;
        }
    });
    let ok = true;
    for (const id in totales) {
        const prod = productos.find(p => parseInt(p.id) === parseInt(id));
        if (prod && totales[id] > parseInt(prod.existencia)) {
            const excedente = totales[id] - parseInt(prod.existencia);
            let restante = excedente;
            rows.forEach(r => {
                const sid = parseInt(r.querySelector('.producto').value);
                if (sid === parseInt(id) && restante > 0) {
                    const inp = r.querySelector('.cantidad');
                    const val = parseInt(inp.value) || 0;
                    const nuevo = Math.max(val - restante, 0);
                    restante -= val - nuevo;
                    inp.value = nuevo;
                    actualizarPrecio(r.querySelector('.producto'));
                }
            });
            alert(`No hay existencia suficiente de ${prod.nombre}`);
            ok = false;
        }
    }
    return ok;
}

function agregarFilaProducto() {
    const tbody = document.querySelector('#productos tbody');
    const base = tbody.querySelector('tr');
    const nueva = base.cloneNode(true);
    nueva.querySelectorAll('input').forEach(inp => {
        inp.value = '';
        if (inp.classList.contains('precio')) delete inp.dataset.unitario;
    });
    tbody.appendChild(nueva);
    const select = nueva.querySelector('.producto');
    select.innerHTML = '<option value="">--Selecciona--</option>';
    catalogo.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nombre;
        opt.dataset.precio = p.precio;
        opt.dataset.existencia = p.existencia;
        select.appendChild(opt);
    });
    select.addEventListener('change', () => {
        actualizarPrecio(select);
        const cantInput = select.closest('tr').querySelector('.cantidad');
        const exist = select.selectedOptions[0].dataset.existencia;
        if (exist) {
            cantInput.max = exist;
        } else {
            cantInput.removeAttribute('max');
        }
        validarInventario();
    });
    const cantidadInput = nueva.querySelector('.cantidad');
    cantidadInput.value = '';
    cantidadInput.addEventListener('input', () => {
        manejarCantidad(cantidadInput, select);
        validarInventario();
    });
}

async function registrarVenta() {
    const tipo = document.getElementById('tipo_entrega').value;
    const mesa_id = parseInt(document.getElementById('mesa_id').value);
    const repartidor_id = parseInt(document.getElementById('repartidor_id').value);
    const usuario_id = parseInt(document.getElementById('usuario_id').value);
    const filas = document.querySelectorAll('#productos tbody tr');
    const productos = [];

    filas.forEach(fila => {
        const producto_id = parseInt(fila.querySelector('.producto').value);
        const cantidad = parseInt(fila.querySelector('.cantidad').value);
        if (!isNaN(producto_id) && !isNaN(cantidad)) {
            const precioInput = fila.querySelector('.precio');
            const precio_unitario = parseFloat(precioInput.dataset.unitario || 0);
            if (precio_unitario > 0) {
                productos.push({ producto_id, cantidad, precio_unitario });
            }
        }
    });

    if (!validarInventario()) {
        return;
    }

    if (tipo === 'mesa') {
        if (isNaN(mesa_id) || !mesa_id) {
            alert('Selecciona una mesa válida');
            return;
        }
    } else {
        if (isNaN(repartidor_id) || !repartidor_id) {
            alert('Selecciona un repartidor válido');
            return;
        }
    }

    const payload = {
        tipo,
        mesa_id: tipo === 'mesa' ? mesa_id : null,
        repartidor_id: tipo === 'domicilio' ? repartidor_id : null,
        usuario_id,
        productos,
        corte_id: corteIdActual
    };

    try {
        const resp = await fetch('../../api/ventas/crear_venta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            alert('Venta registrada');
            await cargarHistorial();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al registrar venta');
    }
}

async function cancelarVenta(id) {
    if (!confirm('¿Seguro de cancelar la venta?')) return;
    try {
        const resp = await fetch('../../api/ventas/cancelar_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(id) })
        });
        const data = await resp.json();
        if (data.success) {
            alert('Venta cancelada');
            await cargarHistorial();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cancelar la venta');
    }
}

async function verDetalles(id) {
    try {
        const resp = await fetch('../../api/ventas/detalle_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(id) })
        });
        const data = await resp.json();
        if (data.success) {
            const info = data.resultado || data;
            const contenedor = document.getElementById('modal-detalles');
            const destino = info.tipo_entrega === 'mesa' ? info.mesa : info.repartidor;
            let html = `<h3>Detalle de venta</h3>
                        <p>Tipo: ${info.tipo_entrega}<br>Destino: ${destino}<br>Mesero: ${info.mesero}</p>`;
            html += `<table border="1"><thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th>Estatus</th><th></th></tr></thead><tbody>`;
            info.productos.forEach(p => {
                const btn = p.estatus_preparacion !== 'entregado'
                    ? `<button class="delDetalle" data-id="${p.id}">Eliminar</button>`
                    : '';
                html += `<tr><td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td>${p.subtotal}</td><td>${p.estatus_preparacion || ''}</td>` +
                        `<td>${btn}</td></tr>`;
            });
            html += `</tbody></table>`;
            if (info.foto_entrega) {
                html += `<p>Evidencia:<br><img src="../../uploads/evidencias/${info.foto_entrega}" width="300"></p>`;
            }
            html += `<h4>Agregar producto</h4>`;
            html += `<select id="detalle_producto"></select>`;
            html += `<input type="number" id="detalle_cantidad" value="1" min="1">`;
            html += `<button id="addDetalle">Agregar</button>`;
            html += ` <button id="imprimirTicket">Imprimir ticket</button> <button id="cerrarDetalle">Cerrar</button>`;

            contenedor.innerHTML = html;
            contenedor.style.display = 'block';

            const selectProd = document.getElementById('detalle_producto');
            selectProd.innerHTML = '<option value="">--Selecciona--</option>';
            catalogo.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.nombre;
                opt.dataset.precio = p.precio;
                opt.dataset.existencia = p.existencia;
                selectProd.appendChild(opt);
            });
            const cantDetalle = document.getElementById('detalle_cantidad');
            selectProd.addEventListener('change', () => {
                const exist = selectProd.selectedOptions[0].dataset.existencia;
                if (exist) {
                    cantDetalle.max = exist;
                } else {
                    cantDetalle.removeAttribute('max');
                }
            });

            contenedor.querySelectorAll('.delDetalle').forEach(btn => {
                btn.addEventListener('click', () => eliminarDetalle(btn.dataset.id, id));
            });
            document.getElementById('addDetalle').addEventListener('click', () => agregarDetalle(id));
            document.getElementById('cerrarDetalle').addEventListener('click', () => {
                contenedor.style.display = 'none';
            });
            document.getElementById('imprimirTicket').addEventListener('click', () => {
                const venta = ventasData[id] || {};
                const total = venta.total || info.productos.reduce((s, p) => s + parseFloat(p.subtotal), 0);
                const payload = {
                    venta_id: parseInt(id),
                    usuario_id: venta.usuario_id || 1,
                    fecha: venta.fecha || '',
                    productos: info.productos,
                    total
                };
                localStorage.setItem('ticketData', JSON.stringify(payload));
                const mesaParam = venta.mesa_id ? `&mesa=${venta.mesa_id}` : '';
                window.open(`ticket.php?venta=${id}${mesaParam}`, '_blank');
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
    }
}

async function eliminarDetalle(detalleId, ventaId) {
    if (!confirm('¿Eliminar producto?')) return;
    try {
        const resp = await fetch('../../api/mesas/eliminar_producto_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ detalle_id: parseInt(detalleId) })
        });
        const data = await resp.json();
        if (data.success) {
            verDetalles(ventaId);
            await cargarHistorial();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al eliminar');
    }
}

async function agregarDetalle(ventaId) {
    const select = document.getElementById('detalle_producto');
    const cantidad = parseInt(document.getElementById('detalle_cantidad').value);
    const productoId = parseInt(select.value);
    const prod = catalogo.find(p => parseInt(p.id) === productoId);
    const precio = prod ? parseFloat(prod.precio) : 0;
    if (isNaN(productoId) || isNaN(cantidad) || cantidad <= 0) {
        alert('Producto o cantidad inválida');
        return;
    }
    if (prod && cantidad > parseInt(prod.existencia)) {
        alert(`Solo hay ${prod.existencia} disponibles de ${prod.nombre}`);
        return;
    }
    try {
        const resp = await fetch('../../api/mesas/agregar_producto_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                venta_id: parseInt(ventaId),
                producto_id: productoId,
                cantidad,
                precio_unitario: precio
            })
        });
        const data = await resp.json();
        if (data.success) {
            verDetalles(ventaId);
            await cargarHistorial();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al agregar');
    }
}

function cargarSolicitudes() {
    const tbody = document.querySelector('#solicitudes tbody');
    if (!tbody) return;
    ticketRequests = JSON.parse(localStorage.getItem('ticketRequests') || '[]');
    tbody.innerHTML = '';
    ticketRequests.forEach(req => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${req.nombre}</td><td><button class="printReq" data-mesa="${req.mesa_id}" data-venta="${req.venta_id}">Imprimir</button></td>`;
        tbody.appendChild(tr);
    });
    tbody.querySelectorAll('button.printReq').forEach(btn => {
        btn.addEventListener('click', () => imprimirSolicitud(btn.dataset.mesa, btn.dataset.venta));
    });
}

async function imprimirSolicitud(mesaId, ventaId) {
    try {
        const resp = await fetch('../../api/ventas/detalle_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(ventaId) })
        });
        const data = await resp.json();
        if (data.success) {
            const info = data.resultado || data;
            const venta = ventasData[ventaId] || {};
            const total = venta.total || info.productos.reduce((s, p) => s + parseFloat(p.subtotal), 0);
            const payload = {
                venta_id: parseInt(ventaId),
                usuario_id: venta.usuario_id || 1,
                fecha: venta.fecha || '',
                productos: info.productos,
                total
            };
            localStorage.setItem('ticketData', JSON.stringify(payload));
            const w = window.open(`ticket.php?venta=${ventaId}&mesa=${mesaId}`, '_blank');
            if (w) w.focus();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
    }
}

function ticketPrinted(mesaId) {
    let reqs = JSON.parse(localStorage.getItem('ticketRequests') || '[]');
    reqs = reqs.filter(r => parseInt(r.mesa_id) !== parseInt(mesaId));
    localStorage.setItem('ticketRequests', JSON.stringify(reqs));
    cargarSolicitudes();
}
window.ticketPrinted = ticketPrinted;

document.addEventListener("change", function (e) {
    if (e.target.classList.contains("producto")) {
        actualizarPrecio(e.target);
        validarInventario();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    verificarCorte();
    cargarMeseros();
    cargarProductos();
    cargarRepartidores();
    cargarHistorial();
    cargarSolicitudes();
    document.getElementById('registrarVenta').addEventListener('click', registrarVenta);
    document.getElementById('agregarProducto').addEventListener('click', agregarFilaProducto);
    document.getElementById('tipo_entrega').addEventListener('change', () => {
        const tipo = document.getElementById('tipo_entrega').value;
        document.getElementById('campoMesa').style.display = tipo === 'mesa' ? 'block' : 'none';
        document.getElementById('campoRepartidor').style.display = tipo === 'domicilio' ? 'block' : 'none';
    });
});
