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

async function cargarInsumos() {
    try {
        const resp = await fetch('../../api/insumos/listar_insumos.php');
        const data = await resp.json();
        if (data.success) {
            catalogo = data.resultado;
            actualizarSelectsProducto();
            mostrarCatalogo();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar insumos');
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
        sel.addEventListener('change', () => mostrarTipoEnFila(sel));
    });
}

function mostrarTipoEnFila(select) {
    const id = parseInt(select.value);
    const fila = select.closest('tr');
    const tipoCell = fila.querySelector('.tipo');
    const unidadesInput = fila.querySelector('.unidades');
    const cantidadInput = fila.querySelector('.cantidad');
    const precioInput = fila.querySelector('.precio');
    const encontrado = catalogo.find(c => c.id == id);
    if (encontrado) {
        tipoCell.textContent = encontrado.tipo_control;
        if (encontrado.tipo_control === 'desempaquetado') {
            unidadesInput.style.display = '';
        } else {
            unidadesInput.style.display = 'none';
            unidadesInput.value = '';
        }

        if (encontrado.tipo_control === 'unidad_completa' || encontrado.tipo_control === 'desempaquetado') {
            cantidadInput.step = 1;
            cantidadInput.min = 1;
        } else {
            cantidadInput.step = '0.01';
            cantidadInput.min = 0;
        }

        if (encontrado.tipo_control === 'no_controlado') {
            cantidadInput.disabled = true;
            precioInput.disabled = true;
        } else {
            cantidadInput.disabled = false;
            precioInput.disabled = false;
        }
    } else {
        tipoCell.textContent = '';
        unidadesInput.style.display = 'none';
        unidadesInput.value = '';
        cantidadInput.disabled = false;
        precioInput.disabled = false;
    }
}

function agregarFila() {
    const tbody = document.querySelector('#tablaProductos tbody');
    const base = tbody.querySelector('tr');
    const nueva = base.cloneNode(true);
    nueva.querySelectorAll('input').forEach(i => i.value = '');
    tbody.appendChild(nueva);
    actualizarSelectsProducto();
}

function mostrarCatalogo() {
    const tbody = document.querySelector('#listaInsumos tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    catalogo.forEach(i => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${i.nombre}</td>
            <td>${i.unidad}</td>
            <td>${i.existencia}</td>
            <td>${i.tipo_control}</td>
        `;
        tbody.appendChild(tr);
    });
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
        const insumo_id = parseInt(f.querySelector('.producto').value);
        const cantidad = parseInt(f.querySelector('.cantidad').value);
        const precio_unitario = parseFloat(f.querySelector('.precio').value) || 0;
        const unidades = parseFloat(f.querySelector('.unidades').value) || 0;
        if (!isNaN(insumo_id) && !isNaN(cantidad)) {
            const obj = { insumo_id, cantidad, precio_unitario };
            if (unidades > 0) obj.unidades = unidades;
            productos.push(obj);
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
    cargarInsumos();
    cargarHistorial();
    document.getElementById('agregarFila').addEventListener('click', agregarFila);
    document.getElementById('registrarEntrada').addEventListener('click', registrarEntrada);
    document.getElementById('btnNuevoProveedor').addEventListener('click', nuevoProveedor);
});
