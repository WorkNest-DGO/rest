const params = new URLSearchParams(location.search);
const repartidorId = params.get('id');

function diffMins(a, b) {
    const t1 = new Date(a).getTime();
    const t2 = new Date(b).getTime();
    return Math.round((t2 - t1) / 60000);
}

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
                const asign = v.fecha_asignacion || '';
                const inicio = v.fecha_inicio || '';
                const entrega = v.fecha_entrega || '';
                const totalMin = v.fecha_asignacion && v.fecha_entrega ? diffMins(v.fecha_asignacion, v.fecha_entrega) : (v.fecha_asignacion && !v.fecha_entrega ? diffMins(v.fecha_asignacion, Date.now()) : '');
                const caminoMin = v.fecha_inicio && v.fecha_entrega ? diffMins(v.fecha_inicio, v.fecha_entrega) : (v.fecha_inicio && !v.fecha_entrega ? diffMins(v.fecha_inicio, Date.now()) : '');

                row.innerHTML = `
                    <td>${v.id}</td>
                    <td>${v.fecha}</td>
                    <td>${v.total}</td>
                    <td>${v.repartidor}</td>
                    <td>${productos}</td>
                    <td>${v.observacion || ''}</td>
                    <td>${asign}</td>
                    <td>${inicio}</td>
                    <td>${entrega}</td>
                    <td>${totalMin}</td>
                    <td>${caminoMin}</td>
                `;

                if (v.estado_entrega === 'pendiente') {
                    const btn = document.createElement('button');
                    btn.className='btn custom-btn';
                    btn.textContent = 'En camino';
                    btn.addEventListener('click', () => marcarEnCamino(v.id));
                    const accionTd = document.createElement('td');
                    accionTd.appendChild(btn);
                    row.appendChild(accionTd);
                    pendientesBody.appendChild(row);
                } else if (v.estado_entrega === 'en_camino') {
                    const btn = document.createElement('button');
                    btn.className='btn custom-btn';
                    btn.textContent = 'Marcar entregado';
                    btn.addEventListener('click', () => marcarEntregada(v.id));
                    const accionTd = document.createElement('td');
                    accionTd.appendChild(btn);
                    row.appendChild(accionTd);
                    pendientesBody.appendChild(row);
                } else {
                    const btn = document.createElement('button');
                    btn.className='btn custom-btn';
                    btn.textContent = 'Ver detalle';
                    btn.addEventListener('click', () => mostrarDetalle(v));
                    const detTd = document.createElement('td');
                    detTd.appendChild(btn);
                    row.appendChild(detTd);
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
    const seudonimo = prompt('Seud\u00f3nimo del cliente:') || '';
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.addEventListener('change', async () => {
        const fd = new FormData();
        fd.append('venta_id', id);
        fd.append('accion', 'entregado');
        fd.append('seudonimo', seudonimo);
        if (input.files[0]) fd.append('foto', input.files[0]);
        try {
            const resp = await fetch('../../api/repartidores/marcar_entregado.php', {
                method: 'POST',
                body: fd
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
    });
    input.click();
}

async function marcarEnCamino(id) {
    try {
        const resp = await fetch('../../api/repartidores/marcar_entregado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(id), accion: 'en_camino' })
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

function mostrarDetalle(info) {
    const contenedor = document.getElementById('modal-detalles');
    let html = `
        <div class="container mt-5 mb-5 custom-modal">
            <h3 style="color:#b80000;">Productos entregados</h3>
            <ul style="list-style-type: none; padding: 0;">`;
    
    info.productos.forEach(p => {
        const sub = p.cantidad * p.precio_unitario;
        html += `<li style="padding: 5px 0; border-bottom: 1px solid #ccc; ">
                    <strong >${p.nombre}</strong> - ${p.cantidad} x $${p.precio_unitario.toFixed(2)} = $${sub.toFixed(2)}
                 </li>`;
    });

    html += `</ul>`;
    if (info.observacion) {
        html += `<p style="margin-top:10px;"><strong>Observación:</strong> ${info.observacion}</p>`;
    }

    if (info.foto_entrega) {
        html += `<div style="margin-top: 15px;">
                    <p >Evidencia:</p>
                    <img src="../../uploads/evidencias/${info.foto_entrega}" alt="Evidencia" style="max-width: 100%; height: auto; border: 1px solid #ccc;">
                 </div>`;
    }

    html += `<p style="margin-top: 15px; "><strong >Total:</strong> $${info.total.toFixed(2)}</p>
             <div style="text-align: right; margin-top: 20px;">
                 <button class="btn custom-btn" id="cerrarDetalle">Cerrar</button>
             </div>
        </div>`;

    contenedor.innerHTML = html;
    contenedor.style.display = 'block';

    document.getElementById('cerrarDetalle').addEventListener('click', () => {
        contenedor.style.display = 'none';
    });
}


document.addEventListener('DOMContentLoaded', cargarEntregas);
