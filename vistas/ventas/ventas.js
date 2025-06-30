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

let catalogo = [];
let productos = [];
let ventasData = {};
let repartidores = [];
let ticketRequests = [];

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
                    select.appendChild(opt);
                });
                select.addEventListener('change', () => actualizarPrecio(select));
            });
            document.querySelectorAll('#productos .cantidad').forEach(inp => {
                const select = inp.closest('tr').querySelector('.producto');
                inp.addEventListener('input', () => manejarCantidad(inp, select));
            });
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
    } else {
        precioInput.value = '';
        delete precioInput.dataset.unitario;
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
    actualizarPrecio(select || input.closest('tr').querySelector('.producto'));
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
        select.appendChild(opt);
    });
    select.addEventListener('change', () => actualizarPrecio(select));
    const cantidadInput = nueva.querySelector('.cantidad');
    cantidadInput.addEventListener('input', () => manejarCantidad(cantidadInput, select));
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
        productos
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
                        <p>Tipo: ${info.tipo_entrega}<br>Destino: ${destino}<br>Mesero: ${info.mesero}</p>
                        <ul>`;
            info.productos.forEach(p => {
                html += `<li>${p.nombre} - ${p.cantidad} x ${p.precio_unitario} = ${p.subtotal}</li>`;
            });
            html += '</ul><button id="imprimirTicket">Imprimir ticket</button> <button id="cerrarDetalle">Cerrar</button>';
            contenedor.innerHTML = html;
            contenedor.style.display = 'block';
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
                window.open(`ticket.html?venta=${id}${mesaParam}`, '_blank');
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
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
            const w = window.open(`ticket.html?venta=${ventaId}&mesa=${mesaId}`, '_blank');
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
    }
});

document.addEventListener('DOMContentLoaded', () => {
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
