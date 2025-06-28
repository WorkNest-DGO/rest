async function cargarHistorial() {
    try {
        const resp = await fetch('../../../api/ventas/listar_ventas.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#historial tbody');
            tbody.innerHTML = '';
            data.resultado.forEach(v => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${v.id}</td>
                    <td>${v.fecha}</td>
                    <td>${v.total}</td>
                    <td>${v.estatus}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar ventas');
    }
}

async function registrarVenta() {
    const mesa_id = parseInt(document.getElementById('mesa_id').value);
    const usuario_id = parseInt(document.getElementById('usuario_id').value);
    const filas = document.querySelectorAll('#productos tbody tr');
    const productos = [];

    filas.forEach(fila => {
        const producto_id = parseInt(fila.querySelector('.producto_id').value);
        const cantidad = parseInt(fila.querySelector('.cantidad').value);
        const precio_unitario = parseFloat(fila.querySelector('.precio_unitario').value);
        if (!isNaN(producto_id) && !isNaN(cantidad) && !isNaN(precio_unitario)) {
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

document.addEventListener('DOMContentLoaded', () => {
    cargarHistorial();
    document.getElementById('registrarVenta').addEventListener('click', registrarVenta);
});
