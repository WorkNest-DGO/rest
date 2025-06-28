async function cargarHistorial() {
    try {
        const resp = await fetch('../../../api/ventas/listar_ventas.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#historial tbody');
            tbody.innerHTML = '';
            data.resultado.forEach(v => {
                const row = document.createElement('tr');
                const accion = v.estatus !== 'cancelada'
                    ? `<button class="cancelar" data-id="${v.id}">Cancelar</button>`
                    : '';
                row.innerHTML = `
                    <td>${v.id}</td>
                    <td>${v.fecha}</td>
                    <td>${v.total}</td>
                    <td>${v.estatus}</td>
                    <td>${accion}</td>
                `;
                tbody.appendChild(row);
            });
            tbody.querySelectorAll('button.cancelar').forEach(btn => {
                btn.addEventListener('click', () => cancelarVenta(btn.dataset.id));
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

async function cargarProductos() {
    try {
        const resp = await fetch('../../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            catalogo = data.resultado;
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
                inp.addEventListener('input', () => {
                    const select = inp.closest('tr').querySelector('.producto');
                    actualizarPrecio(select);
                });
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
    const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
    const precioInput = row.querySelector('.precio');
    const option = select.selectedOptions[0];
    if (option && option.dataset.precio) {
        const precioUnitario = parseFloat(option.dataset.precio);
        precioInput.value = (precioUnitario * cantidad).toFixed(2);
    } else {
        precioInput.value = '';
    }
}

async function registrarVenta() {
    const mesa_id = parseInt(document.getElementById('mesa_id').value);
    const usuario_id = parseInt(document.getElementById('usuario_id').value);
    const filas = document.querySelectorAll('#productos tbody tr');
    const productos = [];

    filas.forEach(fila => {
        const producto_id = parseInt(fila.querySelector('.producto').value);
        const cantidad = parseInt(fila.querySelector('.cantidad').value);
        if (!isNaN(producto_id) && !isNaN(cantidad)) {
            const prod = catalogo.find(p => p.id === producto_id);
            const precio_unitario = prod ? parseFloat(prod.precio) : 0;
            productos.push({ producto_id, cantidad, precio_unitario });
        }
    });

    const payload = { mesa_id, usuario_id, productos };

    try {
        const resp = await fetch('../../../api/ventas/crear_venta.php', {
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
        const resp = await fetch('../../../api/ventas/cancelar_venta.php', {
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

document.addEventListener('DOMContentLoaded', () => {
    cargarProductos();
    cargarHistorial();
    document.getElementById('registrarVenta').addEventListener('click', registrarVenta);
});
