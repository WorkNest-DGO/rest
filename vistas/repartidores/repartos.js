function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;
const userDiv = document.getElementById('user-info');
const USER_ID = parseInt(userDiv?.dataset.usuarioId || '0', 10);
const USER_ROL = userDiv?.dataset.rol || '';
const IS_PRIVILEGED = USER_ROL === 'admin' || USER_ROL === 'cajero';

if (!IS_PRIVILEGED) {
    const alertBox = document.getElementById('limit-alert');
    if (alertBox) alertBox.classList.remove('d-none');
}

const params = new URLSearchParams(location.search);
const repartidorId = params.get('id');

function diffMins(a, b) {
    const t1 = new Date(a).getTime();
    const t2 = new Date(b).getTime();
    return Math.round((t2 - t1) / 60000);
}

async function cargarEntregas() {
    try {
        let url = '../../api/repartidores/listar_entregas.php';
        const qs = new URLSearchParams();
        if (repartidorId) {
            qs.set('repartidor_id', repartidorId);
        }
        if (!IS_PRIVILEGED) {
            qs.set('usuario_id', String(USER_ID));
        }
        if ([...qs].length) {
            url += '?' + qs.toString();
        }
        const resp = await fetch(url);
        const data = await resp.json();
        if (data.success) {
            const pendientesBody = document.querySelector('#tabla-pendientes tbody');
            const entregadasBody = document.querySelector('#tabla-entregadas tbody');
            pendientesBody.innerHTML = '';
            entregadasBody.innerHTML = '';
            let registros = data.resultado || [];
            if (!IS_PRIVILEGED) {
                registros = registros.filter(v => parseInt(v.usuario_id) === USER_ID);
            }
            registros.forEach(v => {
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
    let modal = document.getElementById('modalMarcarEntregado');

    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modalMarcarEntregado';
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');

        const dialog = document.createElement('div');
        dialog.className = 'modal-dialog';

        const content = document.createElement('div');
        content.className = 'modal-content';

        const body = document.createElement('div');
        body.className = 'modal-body';

        // Campo Seudónimo
        const lblSeudo = document.createElement('label');
        lblSeudo.setAttribute('for', 'seudonimoCliente');
        lblSeudo.textContent = 'Seudónimo del cliente:';

        const inSeudo = document.createElement('input');
        inSeudo.type = 'text';
        inSeudo.id = 'seudonimoCliente';
        inSeudo.className = 'form-control';
        inSeudo.placeholder = 'Opcional';

        // Espaciador
        const spacer = document.createElement('div');
        spacer.style.height = '10px';

        // Campo Foto evidencia
        const lblFoto = document.createElement('label');
        lblFoto.setAttribute('for', 'fotoEntrega');
        lblFoto.textContent = 'Foto evidencia (opcional):';

        const inFoto = document.createElement('input');
        inFoto.type = 'file';
        inFoto.id = 'fotoEntrega';
        inFoto.accept = 'image/*';

        body.appendChild(lblSeudo);
        body.appendChild(inSeudo);
        body.appendChild(spacer);
        body.appendChild(lblFoto);
        body.appendChild(inFoto);

        const footer = document.createElement('div');
        footer.className = 'modal-footer';

        const btnConfirmar = document.createElement('button');
        btnConfirmar.id = 'btnConfirmarEntregado';
        btnConfirmar.className = 'btn custom-btn';
        btnConfirmar.textContent = 'Confirmar entrega';

        const btnCancelar = document.createElement('button');
        btnCancelar.className = 'btn custom-btn';
        btnCancelar.textContent = 'Cancelar';

        footer.appendChild(btnConfirmar);
        footer.appendChild(btnCancelar);

        content.appendChild(body);
        content.appendChild(footer);
        dialog.appendChild(content);
        modal.appendChild(dialog);
        document.body.appendChild(modal);

        // Estilo de footer como en ventas (fondo caja)
        if (!document.getElementById('modalMarcarEntregadoStyles')) {
            const style = document.createElement('style');
            style.id = 'modalMarcarEntregadoStyles';
            style.textContent = '#modalMarcarEntregado .modal-footer{display:flex;justify-content:flex-end;gap:0.5rem;}';
            document.head.appendChild(style);
        }

        btnCancelar.addEventListener('click', () => hideModal(modal));
    }

    // Limpiar valores previos
    modal.querySelector('#seudonimoCliente').value = '';
    const fotoEl = modal.querySelector('#fotoEntrega');
    if (fotoEl) fotoEl.value = '';

    const btnOk = modal.querySelector('#btnConfirmarEntregado');
    btnOk.onclick = async () => {
        const seudonimo = modal.querySelector('#seudonimoCliente').value || '';
        const foto = modal.querySelector('#fotoEntrega').files[0];

        const fd = new FormData();
        fd.append('venta_id', id);
        fd.append('accion', 'entregado');
        fd.append('seudonimo', seudonimo);
        if (foto) fd.append('foto', foto);

        try {
            const resp = await fetch('../../api/repartidores/marcar_entregado.php', {
                method: 'POST',
                body: fd
            });
            const data = await resp.json();
            if (data.success) {
                hideModal(modal);
                cargarEntregas();
            } else {
                alert(data.mensaje);
            }
        } catch (err) {
            console.error(err);
            alert('Error al actualizar');
        }
    };

    showModal(modal);
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
    const modal = document.getElementById('modal-detalles');
    const contenedor = modal.querySelector('.modal-body');
    let html = `<h3 style="color:#b80000;">Productos entregados</h3>`;
    html += `<ul style="list-style-type: none; padding: 0;">`;

    info.productos.forEach(p => {
        const sub = p.cantidad * p.precio_unitario;
        html += `<li style="padding: 5px 0; border-bottom: 1px solid #ccc;">
                    <strong>${p.nombre}</strong> - ${p.cantidad} x $${p.precio_unitario.toFixed(2)} = $${sub.toFixed(2)}
                 </li>`;
    });

    html += `</ul>`;
    if (info.observacion) {
        html += `<p style="margin-top:10px;"><strong>Observación:</strong> ${info.observacion}</p>`;
    }

    if (info.foto_entrega) {
        html += `<div style="margin-top: 15px;">
                    <p>Evidencia:</p>
                    <img src="../../uploads/evidencias/${info.foto_entrega}" alt="Evidencia" style="max-width: 100%; height: auto; border: 1px solid #ccc;">
                 </div>`;
    }

    html += `<p style="margin-top: 15px;"><strong>Total:</strong> $${info.total.toFixed(2)}</p>`;
    contenedor.innerHTML = html;

    showModal('#modal-detalles');
}


document.addEventListener('DOMContentLoaded', () => {
    if (!IS_PRIVILEGED) {
        const sel = document.querySelector('select[name="usuario_id"], #usuario_id');
        if (sel) {
            sel.value = USER_ID;
            sel.disabled = true;
        }
    }
    cargarEntregas();
});
