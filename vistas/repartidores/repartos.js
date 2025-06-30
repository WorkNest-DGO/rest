const params = new URLSearchParams(location.search);
const repartidorId = params.get('id');

async function cargarEntregas() {
    try {
        const url = repartidorId
            ? `../../api/repartidores/listar_entregas.php?repartidor_id=${repartidorId}`
            : '../../api/repartidores/listar_entregas.php';
        const resp = await fetch(url);
        const data = await resp.json();
        if (data.success) {
            const pendientesBody = document.querySelector('#tabla-pendientes tbody');
            const entregadasBody = document.querySelector('#tabla-entregadas tbody');
            pendientesBody.innerHTML = '';
            entregadasBody.innerHTML = '';
            data.resultado.forEach(v => {
                const row = document.createElement('tr');
                const productos = v.productos.map(p => `${p.nombre} (${p.cantidad})`).join(', ');
                row.innerHTML = `
                    <td>${v.id}</td>
                    <td>${v.fecha}</td>
                    <td>${v.total}</td>
                    <td>${v.repartidor}</td>
                    <td>${productos}</td>
                `;
                if (v.estatus === 'activa' && !v.entregado) {
                    const btn = document.createElement('button');
                    btn.textContent = 'Marcar como entregada';
                    btn.addEventListener('click', () => marcarEntregada(v.id));
                    const accionTd = document.createElement('td');
                    accionTd.appendChild(btn);
                    row.appendChild(accionTd);
                    pendientesBody.appendChild(row);
                } else {
                    entregadasBody.appendChild(row);
                }
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar repartos');
    }
}

async function marcarEntregada(id) {
    try {
        const resp = await fetch('../../api/repartidores/marcar_entregado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(id) })
        });
        const data = await resp.json();
        if (data.success) {
            cargarEntregas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al actualizar');
    }
}

document.addEventListener('DOMContentLoaded', cargarEntregas);
