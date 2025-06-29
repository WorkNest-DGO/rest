async function cargarProductos() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#tablaProductos tbody');
            tbody.innerHTML = '';
            data.resultado.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${p.id}</td>
                    <td>${p.nombre}</td>
                    <td>${p.precio}</td>
                    <td><input type="number" class="existencia" data-id="${p.id}" value="${p.existencia}"></td>
                    <td>${p.descripcion || ''}</td>
                    <td>${p.activo == 1 ? 'Sí' : 'No'}</td>
                    <td><button class="actualizar" data-id="${p.id}">Editar existencia</button></td>
                `;
                tbody.appendChild(tr);
            });
            tbody.querySelectorAll('button.actualizar').forEach(btn => {
                btn.addEventListener('click', () => {
                    const input = btn.closest('tr').querySelector('.existencia');
                    actualizarExistencia(btn.dataset.id, input.value);
                });
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar inventario');
    }
}

async function actualizarExistencia(id, valor) {
    try {
        const resp = await fetch('../../api/inventario/actualizar_existencia.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ producto_id: parseInt(id), nueva_existencia: parseInt(valor) })
        });
        const data = await resp.json();
        if (!data.success) {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al actualizar');
    }
}

async function agregarProducto() {
    const nombre = prompt('Nombre del producto:');
    if (!nombre) return;
    const precio = parseFloat(prompt('Precio:', '0')) || 0;
    const descripcion = prompt('Descripción:', '');
    const existencia = parseInt(prompt('Existencia:', '0')) || 0;

    const payload = { nombre, precio, descripcion, existencia };
    try {
        const resp = await fetch('../../api/inventario/agregar_producto.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            alert(data.resultado ? data.resultado.mensaje : 'Producto agregado');
            cargarProductos();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al agregar producto');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    cargarProductos();
    document.getElementById('agregarProducto').addEventListener('click', agregarProducto);
});

