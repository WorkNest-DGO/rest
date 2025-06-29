let catalogoInsumos = [];
let catalogoProductos = [];

async function cargarProductos() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            catalogoProductos = data.resultado;
            const select = document.getElementById('producto_id');
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
        alert('Error al cargar productos');
    }
}

async function cargarInsumos() {
    try {
        const resp = await fetch('../../api/insumos/listar_insumos.php');
        const data = await resp.json();
        if (data.success) {
            catalogoInsumos = data.resultado;
            actualizarSelects();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar insumos');
    }
}

function actualizarSelects() {
    document.querySelectorAll('select.insumo').forEach(sel => {
        sel.innerHTML = '<option value="">--Selecciona--</option>';
        catalogoInsumos.forEach(i => {
            const opt = document.createElement('option');
            opt.value = i.id;
            opt.textContent = i.nombre;
            opt.dataset.unidad = i.unidad;
            sel.appendChild(opt);
        });
        sel.addEventListener('change', () => mostrarUnidad(sel));
    });
}

function mostrarUnidad(select) {
    const id = parseInt(select.value);
    const fila = select.closest('tr');
    const unidadCell = fila.querySelector('.unidad');
    const ins = catalogoInsumos.find(i => i.id == id);
    unidadCell.textContent = ins ? ins.unidad : '';
    validarDuplicados();
}

function agregarFila() {
    const tbody = document.querySelector('#tablaReceta tbody');
    const base = tbody.querySelector('tr');
    const nueva = base.cloneNode(true);
    nueva.querySelectorAll('input').forEach(i => i.value = '');
    nueva.querySelectorAll('.unidad').forEach(c => c.textContent = '');
    tbody.appendChild(nueva);
    nueva.querySelector('.eliminar').addEventListener('click', () => nueva.remove());
    actualizarSelects();
}

function validarDuplicados() {
    const ids = [];
    let repetido = false;
    document.querySelectorAll('select.insumo').forEach(sel => {
        const val = parseInt(sel.value);
        if (!isNaN(val)) {
            if (ids.includes(val)) {
                repetido = true;
                sel.value = '';
                mostrarUnidad(sel);
            } else {
                ids.push(val);
            }
        }
    });
    if (repetido) {
        alert('No se puede repetir un insumo en la receta');
    }
}

async function cargarReceta(id) {
    const tbody = document.querySelector('#tablaReceta tbody');
    tbody.innerHTML = '';
    const base = document.createElement('tr');
    base.innerHTML = `
        <td><select class="insumo"></select></td>
        <td><input type="number" step="0.01" class="cantidad"></td>
        <td class="unidad"></td>
        <td><button type="button" class="eliminar">Eliminar</button></td>
    `;
    tbody.appendChild(base);
    actualizarSelects();
    base.querySelector('.eliminar').addEventListener('click', () => base.remove());
    if (!id) return;
    try {
        const resp = await fetch('../../api/recetas/listar_receta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ producto_id: id })
        });
        const data = await resp.json();
        if (data.success) {
            tbody.innerHTML = '';
            data.resultado.forEach(r => {
                const tr = base.cloneNode(true);
                tr.querySelector('.cantidad').value = r.cantidad;
                tbody.appendChild(tr);
                actualizarSelects();
                tr.querySelector('.insumo').value = r.insumo_id;
                mostrarUnidad(tr.querySelector('.insumo'));
                tr.querySelector('.eliminar').addEventListener('click', () => tr.remove());
            });
            if (data.resultado.length === 0) tbody.appendChild(base);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar receta');
    }
}

async function guardarReceta() {
    const producto_id = parseInt(document.getElementById('producto_id').value);
    if (isNaN(producto_id)) {
        alert('Selecciona un producto');
        return;
    }
    const filas = document.querySelectorAll('#tablaReceta tbody tr');
    const receta = [];
    for (const fila of filas) {
        const insumo_id = parseInt(fila.querySelector('.insumo').value);
        const cantidad = parseFloat(fila.querySelector('.cantidad').value);
        if (!isNaN(insumo_id) && !isNaN(cantidad)) {
            if (receta.find(r => r.insumo_id === insumo_id)) {
                alert('No se puede repetir un insumo');
                return;
            }
            receta.push({ insumo_id, cantidad });
        }
    }
    const payload = { producto_id, receta };
    try {
        const resp = await fetch('../../api/recetas/guardar_receta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            alert('Receta guardada');
            await cargarReceta(producto_id);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al guardar receta');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    cargarProductos();
    cargarInsumos();
    document.getElementById('agregarFila').addEventListener('click', agregarFila);
    document.getElementById('guardarReceta').addEventListener('click', guardarReceta);
    document.getElementById('producto_id').addEventListener('change', (e) => {
        const id = parseInt(e.target.value);
        if (!isNaN(id)) cargarReceta(id); else cargarReceta(null);
    });
});
