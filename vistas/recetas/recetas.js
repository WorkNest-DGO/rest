function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
let catalogoInsumos = [];
let catalogoProductos = [];
const imagenDefault = '../../utils/img/default.jpg';
const normalizarRecetaTexto = (txt) => (txt || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

function mostrarImagenProducto(id) {
    const img = document.getElementById('imgProducto');
    const nom = document.getElementById('nombreProducto');
    const frm = document.getElementById('formImagen');
    if (!id) {
        img.style.display = 'none';
        img.src = '';
        nom.textContent = '';
        frm.style.display = 'none';
        return;
    }
    const prod = catalogoProductos.find(p => p.id == id);
    if (!prod) return;
    nom.textContent = prod.nombre;
    if (prod.imagen) {
        img.src = `../../uploads/productos/${prod.imagen}`;
    } else {
        img.src = imagenDefault;
    }
    img.style.display = 'block';
    frm.style.display = 'block';
}

function filtrarProductosPorTexto(termino = '', lista = catalogoProductos) {
    const needle = normalizarRecetaTexto((termino || '').trim());
    if (!needle) return [];
    return (lista || []).filter(p => normalizarRecetaTexto(p.nombre || '').includes(needle)).slice(0, 50);
}

function mostrarSugerenciasProductos(termino = '', lista = catalogoProductos) {
    const ul = document.getElementById('listaProductosReceta');
    if (!ul) return;
    ul.innerHTML = '';
    const coincidencias = filtrarProductosPorTexto(termino, lista);
    coincidencias.forEach(p => {
        const li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action';
        li.textContent = p.nombre || '';
        li.addEventListener('click', () => seleccionarProductoReceta(p));
        ul.appendChild(li);
    });
    ul.style.display = coincidencias.length ? 'block' : 'none';
}

function seleccionarProductoReceta(prod) {
    const select = document.getElementById('producto_id');
    const busc = document.getElementById('buscarProductoReceta');
    const ul = document.getElementById('listaProductosReceta');
    if (select && prod) {
        let opt = select.querySelector(`option[value="${prod.id}"]`);
        if (!opt) {
            opt = document.createElement('option');
            opt.value = prod.id;
            opt.textContent = prod.nombre || '';
            select.appendChild(opt);
        }
        select.value = prod.id;
        onProductoSeleccionado(prod.id);
    }
    if (busc) busc.value = prod ? (prod.nombre || '') : '';
    if (ul) ul.style.display = 'none';
}

function onProductoSeleccionado(id) {
    const prod = catalogoProductos.find(p => String(p.id) === String(id));
    const busc = document.getElementById('buscarProductoReceta');
    if (busc) busc.value = prod ? (prod.nombre || '') : '';
    const val = parseInt(id);
    if (!isNaN(val)) {
        mostrarImagenProducto(val);
        cargarReceta(val);
    } else {
        mostrarImagenProducto(null);
        cargarReceta(null);
    }
}

async function cargarProductos() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            const lista = Array.isArray(data.resultado) ? data.resultado : ((data.resultado && data.resultado.productos) || []);
            catalogoProductos = lista;
            const select = document.getElementById('producto_id');
            if (select) {
                const valorPrevio = select.value;
                select.innerHTML = '<option value="">--Selecciona--</option>';
                lista.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nombre;
                    select.appendChild(opt);
                });
                if (valorPrevio) select.value = valorPrevio;
            }
            const busc = document.getElementById('buscarProductoReceta');
            if (busc && busc.value.trim()) {
                mostrarSugerenciasProductos(busc.value, lista);
            }
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

function llenarSelectInsumos(sel) {
    sel.innerHTML = '<option value="">--Selecciona--</option>';
    catalogoInsumos.forEach(i => {
        const opt = document.createElement('option');
        opt.value = i.id;
        opt.textContent = i.nombre;
        opt.dataset.unidad = i.unidad;
        sel.appendChild(opt);
    });
    sel.addEventListener('change', () => mostrarUnidad(sel));
}

function actualizarSelects() {
    document.querySelectorAll('select.insumo').forEach(sel => {
        llenarSelectInsumos(sel);
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

function crearFila(detalle) {
    if (detalle === undefined && arguments.length > 0) {
        console.error('crearFila: objeto de receta indefinido');
        return;
    }

    const insumoId = detalle && detalle.insumo_id ? detalle.insumo_id : '';
    const cantidad = detalle && detalle.cantidad ? detalle.cantidad : '';

    const tr = document.createElement('tr');

    const tdInsumo = document.createElement('td');
    const sel = document.createElement('select');
    sel.className = 'insumo';
    tdInsumo.appendChild(sel);

    const tdCantidad = document.createElement('td');
    const inputCant = document.createElement('input');
    inputCant.type = 'number';
    inputCant.step = '0.01';
    inputCant.className = 'cantidad';
    tdCantidad.appendChild(inputCant);

    const tdUnidad = document.createElement('td');
    tdUnidad.className = 'unidad';

    const tdAccion = document.createElement('td');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn custom-btn btnEliminar';
    btn.textContent = 'Eliminar';
    tdAccion.appendChild(btn);

    tr.appendChild(tdInsumo);
    tr.appendChild(tdCantidad);
    tr.appendChild(tdUnidad);
    tr.appendChild(tdAccion);

    document.querySelector('#tablaReceta tbody').appendChild(tr);

    llenarSelectInsumos(sel);

    btn.addEventListener('click', () => tr.remove());

    if (insumoId) {
        sel.value = insumoId;
        inputCant.value = cantidad;
        mostrarUnidad(sel);
    }
}

function agregarFila() {
    crearFila();
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
    if (!id) {
        crearFila();
        return;
    }
    try {
        const resp = await fetch('../../api/recetas/listar_receta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ producto_id: id })
        });
        const data = await resp.json();
        if (data.success) {
            if (data.resultado.length === 0) {
                crearFila();
            } else {
                data.resultado.forEach(r => crearFila(r));
            }
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

async function copiarReceta(origenId) {
    try {
        const resp = await fetch('../../api/recetas/listar_receta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ producto_id: origenId })
        });
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#tablaReceta tbody');
            tbody.innerHTML = '';
            if (data.resultado.length === 0) {
                crearFila();
            } else {
                data.resultado.forEach(r => crearFila(r));
            }
            alert('Receta copiada, guarda para aplicar los cambios');
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al copiar receta');
    }
}

async function subirImagenProducto() {
    const producto_id = parseInt(document.getElementById('producto_id').value);
    const file = document.getElementById('imagenProducto').files[0];
    if (isNaN(producto_id) || !file) {
        alert('Selecciona un producto e imagen');
        return;
    }
    const fd = new FormData();
    fd.append('producto_id', producto_id);
    fd.append('imagen', file);
    try {
        const resp = await fetch('../../api/inventario/subir_imagen_producto.php', {
            method: 'POST',
            body: fd
        });
        const data = await resp.json();
        if (data.success) {
            alert('Imagen actualizada');
            const prod = catalogoProductos.find(p => p.id == producto_id);
            if (prod) prod.imagen = data.resultado.ruta.split('/').pop();
            mostrarImagenProducto(producto_id);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al subir imagen');
    }
}

function abrirModalCopiar() {
    const modal = document.getElementById('modalCopiarReceta');
    const body = modal.querySelector('.modal-body');
    const footer = modal.querySelector('.modal-footer');
    let html = '<select id="producto_copiar" class="form-control"><option value="">--Selecciona--</option>';
    catalogoProductos.forEach(p => {
        html += `<option value="${p.id}">${p.nombre}</option>`;
    });
    html += '</select>';
    body.innerHTML = html;
    footer.innerHTML = '<button class="btn custom-btn" id="btnCopiarAhora">Copiar</button> <button class="btn btn-secondary" data-dismiss="modal">Cerrar</button>';
    showModal('#modalCopiarReceta');
    document.getElementById('btnCopiarAhora').addEventListener('click', () => {
        const id = parseInt(document.getElementById('producto_copiar').value);
        if (!isNaN(id)) {
            copiarReceta(id);
            hideModal('#modalCopiarReceta');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    cargarProductos();
    cargarInsumos();
    const listaProd = document.getElementById('listaProductosReceta');
    if (listaProd) listaProd.style.display = 'none';
    document.getElementById('agregarFila').addEventListener('click', agregarFila);
    document.getElementById('guardarReceta').addEventListener('click', guardarReceta);
    document.getElementById('subirImagen').addEventListener('click', subirImagenProducto);
    document.getElementById('producto_id').addEventListener('change', (e) => onProductoSeleccionado(e.target.value));
    const buscadorProd = document.getElementById('buscarProductoReceta');
    if (buscadorProd) {
        buscadorProd.addEventListener('input', () => mostrarSugerenciasProductos(buscadorProd.value));
        buscadorProd.addEventListener('focus', () => {
            if (buscadorProd.value.trim()) mostrarSugerenciasProductos(buscadorProd.value);
        });
        buscadorProd.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') {
                const coincidencias = filtrarProductosPorTexto(buscadorProd.value, catalogoProductos);
                if (coincidencias.length) {
                    seleccionarProductoReceta(coincidencias[0]);
                    ev.preventDefault();
                }
            }
        });
    }
    document.addEventListener('click', (e) => {
        const wrap = document.querySelector('.selector-producto-receta');
        const ul = document.getElementById('listaProductosReceta');
        if (wrap && ul && !wrap.contains(e.target)) {
            ul.style.display = 'none';
        }
    });
    document.getElementById('copiarReceta').addEventListener('click', abrirModalCopiar);
});
