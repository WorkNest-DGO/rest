let catalogo = [];

async function cargarProveedores() {
    try {
        const resp = await fetch('../../api/insumos/listar_proveedores.php');
        const data = await resp.json();
        if (data.success) {
            const select = document.getElementById('proveedor');
            select.innerHTML = '<option value="">--Selecciona--</option>';
            data.resultado.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.nombre;
                select.appendChild(opt);
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar proveedores');
    }
}

async function cargarProductos() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            catalogo = data.resultado;
            actualizarSelectsProducto();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar productos');
    }
}

function actualizarSelectsProducto() {
    document.querySelectorAll('select.producto').forEach(sel => {
        sel.innerHTML = '<option value="">--Selecciona--</option>';
        catalogo.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.nombre;
            sel.appendChild(opt);
        });
    });
}

function agregarFila() {
    const tbody = document.querySelector('#tablaProductos tbody');
    const base = tbody.querySelector('tr');
    const nueva = base.cloneNode(true);
    nueva.querySelectorAll('input').forEach(i => i.value = '');
    tbody.appendChild(nueva);
    actualizarSelectsProducto();
}

async function nuevoProveedor() {
    const nombre = prompt('Nombre del proveedor:');
    if (!nombre) return;
    const telefono = prompt('Teléfono:') || '';
    const direccion = prompt('Dirección:') || '';
    try {
        const resp = await fetch('../../api/insumos/agregar_proveedor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre, telefono, direccion })
        });
        const data = await resp.json();
        if (data.success) {
            alert('Proveedor agregado');
            cargarProveedores();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al agregar proveedor');
    }
}

async function registrarEntrada() {
    const proveedor_id = parseInt(document.getElementById('proveedor').value);
    if (isNaN(proveedor_id)) {
        alert('Selecciona un proveedor');
        return;
    }
    const filas = document.querySelectorAll('#tablaProductos tbody tr');
    const productos = [];
    filas.forEach(f => {
        const producto_id = parseInt(f.querySelector('.producto').value);
        const cantidad = parseInt(f.querySelector('.cantidad').value);
        const precio_unitario = parseFloat(f.querySelector('.precio').value) || 0;
        if (!isNaN(producto_id) && !isNaN(cantidad)) {
            productos.push({ producto_id, cantidad, precio_unitario });
        }
    });
    const payload = { proveedor_id, productos };
    try {
        const resp = await fetch('../../api/insumos/crear_entrada.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            alert('Entrada registrada');
            cargarHistorial();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al registrar entrada');
    }
}

async function cargarHistorial() {
    try {
        const resp = await fetch('../../api/insumos/listar_entradas.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#historial tbody');
            tbody.innerHTML = '';
            data.resultado.forEach(e => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${e.id}</td>
                    <td>${e.proveedor}</td>
                    <td>${e.fecha}</td>
                    <td>${e.total}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar historial');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    cargarProveedores();
    cargarProductos();
    cargarHistorial();
    document.getElementById('agregarFila').addEventListener('click', agregarFila);
    document.getElementById('registrarEntrada').addEventListener('click', registrarEntrada);
    document.getElementById('btnNuevoProveedor').addEventListener('click', nuevoProveedor);
});
