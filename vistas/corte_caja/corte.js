let corteActual = null;
const usuarioId = 1; // En entorno real, este valor provendría de la sesión

function calcularTotalEsperado(resumen) {
    let total = 0;
    if (resumen && typeof resumen === 'object') {
        for (const metodo in resumen) {
            if (Object.prototype.hasOwnProperty.call(resumen, metodo)) {
                const info = resumen[metodo] || {};
                total += (parseFloat(info.total) || 0) + (parseFloat(info.propina) || 0);
            }
        }
    }
    return total;
}

async function verificarCorte() {
    try {
        const resp = await fetch('../../api/corte_caja/verificar_corte_abierto.php?usuario_id=' + usuarioId);
        const data = await resp.json();
        const cont = document.getElementById('corteActual');
        cont.innerHTML = '';
        if (data.success && data.abierto) {
            corteActual = data.corte_id;
            cont.innerHTML = `<p>Inicio: ${data.fecha_inicio}</p><button class="btn custom-btn" id="btnCerrar">Cerrar Corte</button> <button id="btnImprimir">Imprimir</button>`;
            document.getElementById('btnCerrar').addEventListener('click', cerrarCorte);
            document.getElementById('btnImprimir').addEventListener('click', imprimirResumen);
        } else {
            corteActual = null;
            cont.innerHTML = '<button class="btn custom-btn" id="btnIniciar">Iniciar Corte</button>';
            document.getElementById('btnIniciar').addEventListener('click', iniciarCorte);
        }
    } catch (err) {
        console.error(err);
        alert('Error al verificar corte');
    }
}

async function iniciarCorte() {
    try {
        const resp = await fetch('../../api/corte_caja/iniciar_corte.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ usuario_id: usuarioId })
        });
        const data = await resp.json();
        if (data.success) {
            corteActual = data.resultado.corte_id;
            await verificarCorte();
            await cargarHistorial();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al iniciar corte');
    }
}

async function cerrarCorte() {
    if (!corteActual) return;
    try {
        const resResumen = await fetch('../../api/corte_caja/resumen_corte_actual.php?usuario_id=' + usuarioId);
        const resumen = await resResumen.json();
        if (!resumen.success || !resumen.resultado) {
            alert('No hay resumen disponible');
            return;
        }
        abrirModalDesglose(corteActual, resumen.resultado);
    } catch (err) {
        console.error(err);
        alert('Error al obtener resumen');
    }
}

async function imprimirResumen() {
    try {
        const resp = await fetch('../../api/corte_caja/resumen_corte_actual.php?usuario_id=' + usuarioId);
        const data = await resp.json();
        if (!data.success || !data.resultado) {
            alert('No hay corte abierto');
            return;
        }
        const resumen = data.resultado;
        let html = `<h3>Resumen de corte</h3><ul>`;
        for (const metodo in resumen) {
            if (Object.prototype.hasOwnProperty.call(resumen, metodo)) {
                const info = resumen[metodo] || {};
                const subtotal = (parseFloat(info.total) || 0) + (parseFloat(info.propina) || 0);
                html += `<li>${metodo}: $${subtotal.toFixed(2)}</li>`;
            }
        }
        const totalGeneral = calcularTotalEsperado(resumen);
        html += `</ul><p>Total esperado: $${totalGeneral.toFixed(2)}</p>`;
        const w = window.open('', 'print');
        w.document.write(html);
        w.print();
    } catch (err) {
        console.error(err);
        alert('Error al imprimir');
    }
}

async function verDetalle(corteId) {
    try {
        const resp = await fetch('../../api/corte_caja/listar_ventas_por_corte.php?corte_id=' + corteId);
        const data = await resp.json();
        if (data.success) {
            const modal = document.getElementById('resumenModal');
            let html = `<h3>Ventas del corte ${corteId}</h3>`;
            html += '<table class="table"><thead><tr><th>ID</th><th>Fecha</th><th>Total</th><th>Usuario</th><th>Propina</th><th></th></tr></thead><tbody>';
            data.resultado.forEach(v => {
                html += `<tr><td>${v.id}</td><td>${v.fecha}</td><td>${v.total}</td><td>${v.usuario}</td><td>${v.propina}</td>` +
                        `<td><button class="btn btn-primary verVenta" data-id="${v.id}">Ver</button></td></tr>`;
            });
            html += '</tbody></table><button class="btn custom-btn" id="cerrarDetalle">Cerrar</button>';
            modal.innerHTML = html;
            modal.style.display = 'block';
            document.getElementById('cerrarDetalle').addEventListener('click', () => {
                modal.style.display = 'none';
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalle');
    }
}

function abrirModalDesglose(corteId, resumen) {
    const totalEsperado = calcularTotalEsperado(resumen);
    const modal = document.getElementById('modalDesglose');
    let html = '<div style="background:#fff;border:1px solid #333;padding:10px;">';
    html += '<h3>Desglose de caja</h3>';
    html += `<p>Total esperado: $${totalEsperado.toFixed(2)}</p>`;
    html += '<p>Total ingresado: $<span id="totalDesglose">0.00</span> | Dif.: $<span id="difDesglose">0.00</span></p>';
    html += '<table id="tablaDesglose" border="1"><thead><tr><th>Denominación</th><th>Cantidad</th><th>Tipo</th><th></th></tr></thead><tbody></tbody></table>';
    html += '<button class="btn custom-btn" id="addFila">Agregar fila</button> <button class="btn custom-btn" id="guardarDesglose">Guardar desglose</button> <button id="cancelarDesglose">Cancelar</button>';
    html += '</div>';
    modal.innerHTML = html;
    modal.style.display = 'block';

    const tbody = modal.querySelector('#tablaDesglose tbody');

    function agregarFila() {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><input type="number" step="0.01" class="denominacion"></td>`+
            `<td><input type="number" min="0" class="cantidad" value="0"></td>`+
            `<td><select class="tipo"><option value="efectivo">efectivo</option><option value="cheque">cheque</option><option value="boucher">boucher</option></select></td>`+
            `<td><button class="btn custom-btn delFila">X</button></td>`;
        tbody.appendChild(tr);
        tr.querySelector('.delFila').addEventListener('click', () => { tr.remove(); calcular(); });
        tr.querySelectorAll('input,select').forEach(el => el.addEventListener('input', calcular));
    }

    function calcular() {
        const filas = Array.from(tbody.querySelectorAll('tr'));
        let total = 0;
        filas.forEach(f => {
            const d = parseFloat(f.querySelector('.denominacion').value) || 0;
            const c = parseInt(f.querySelector('.cantidad').value) || 0;
            const t = f.querySelector('.tipo').value;
            if (t === 'efectivo') {
                total += d * c;
            } else {
                total += d;
            }
        });
        modal.querySelector('#totalDesglose').textContent = total.toFixed(2);
        modal.querySelector('#difDesglose').textContent = (total - totalEsperado).toFixed(2);
    }

    modal.querySelector('#addFila').addEventListener('click', agregarFila);
    agregarFila();

    modal.querySelector('#cancelarDesglose').addEventListener('click', () => {
        modal.style.display = 'none';
        verificarCorte();
    });

    modal.querySelector('#guardarDesglose').addEventListener('click', async () => {
        const filas = Array.from(tbody.querySelectorAll('tr'));
        const detalle = filas.map(tr => ({
            denominacion: parseFloat(tr.querySelector('.denominacion').value) || 0,
            cantidad: parseInt(tr.querySelector('.cantidad').value) || 0,
            tipo_pago: tr.querySelector('.tipo').value
        })).filter(d => d.cantidad > 0 || d.tipo_pago !== 'efectivo');

        if (detalle.length === 0) {
            alert('Agrega al menos una fila válida');
            return;
        }

        try {
            const resp = await fetch('../../api/corte_caja/guardar_desglose.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ corte_id: corteId, detalle })
            });
            const data = await resp.json();
            if (data.success) {
                const cierre = await fetch('../../api/corte_caja/cerrar_corte.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ corte_id: corteId, usuario_id: usuarioId, observaciones: '' })
                });
                const resCierre = await cierre.json();
                if (resCierre.success) {
                    alert('Corte cerrado');
                    corteActual = null;
                    modal.style.display = 'none';
                    await cargarHistorial();
                    verificarCorte();
                } else {
                    alert(resCierre.mensaje || 'Error al cerrar corte');
                }
            } else {
                alert(data.mensaje || 'Error al guardar desglose');
            }
        } catch (err) {
            console.error(err);
            alert('Error al guardar desglose');
        }
    });

    calcular();
}

async function cargarHistorial() {
    try {
        const resp = await fetch('../../api/corte_caja/listar_cortes.php');
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#tablaCortes tbody');
            tbody.innerHTML = '';
            data.resultado.forEach(c => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${c.id}</td>
                    <td>${c.usuario}</td>
                    <td>${c.fecha_inicio}</td>
                    <td>${c.fecha_fin || ''}</td>
                    <td>${c.total !== null ? c.total : ''}</td>
                    <td><button class="btn custom-btn verDetalle" data-id="${c.id}">Ver detalle</button> <button class="btn custom-btn exportarCsv" data-id="${c.id}">Exportar</button></td>
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
    verificarCorte();
    cargarHistorial();

    // Detalle de corte
    $(document).on('click', '.verDetalle', function () {
        const corteId = $(this).data('id');
        if (!corteId) {
            console.error('ID de corte no especificado');
            return;
        }
        $.ajax({
            url: '../../api/corte_caja/detalle_venta.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({ id: parseInt(corteId) })
        }).done(function (resp) {
            const contenedor = document.getElementById('modalDetalleContenido');
            if (resp.success && Array.isArray(resp.detalles)) {
                contenedor.innerHTML = '<h5>Desglose del corte</h5><table class="table table-sm" id="tablaDetalleCorte"></table>';
                const tablaDetalle = document.getElementById('tablaDetalleCorte');
                tablaDetalle.innerHTML = '';
                const thead = `<tr>
                    <th>Denominación</th>
                    <th>Cantidad</th>
                    <th>Tipo de pago</th>
                    <th>Subtotal</th>
                </tr>`;
                tablaDetalle.innerHTML = thead;
                resp.detalles.forEach(item => {
                    const fila = document.createElement('tr');
                    fila.innerHTML = `
                        <td>${item.denominacion}</td>
                        <td>${item.cantidad}</td>
                        <td>${item.tipo_pago}</td>
                        <td>$${parseFloat(item.subtotal).toFixed(2)}</td>
                    `;
                    tablaDetalle.appendChild(fila);
                });
            } else {
                const msg = resp.mensaje || 'Error al obtener detalle';
                contenedor.innerHTML = `<p>${msg}</p>`;
            }
            $('#modalDetalle').modal('show');
        }).fail(function () {
            const contenedor = document.getElementById('modalDetalleContenido');
            contenedor.innerHTML = '<p>Error al obtener detalle</p>';
            $('#modalDetalle').modal('show');
        });
    });

    // Detalle de venta
    $(document).on('click', '.verVenta', function () {
        const ventaId = $(this).data('id');
        if (!ventaId) {
            console.error('ID de venta no especificado');
            return;
        }
        $.ajax({
            url: '../../api/corte_caja/detalle_venta.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({ id: parseInt(ventaId) })
        }).done(function (resp) {
            if (resp.success && Array.isArray(resp.productos)) {
                const info = resp;
                const destino = info.tipo_entrega === 'mesa' ? info.mesa : info.repartidor;
                let html = `<h5>Detalle de venta</h5>`;
                html += `<p>Tipo: ${info.tipo_entrega}<br>Destino: ${destino}<br>Mesero: ${info.mesero}</p>`;
                html += '<table class="table table-sm"><thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th>Estatus</th></tr></thead><tbody>';
                info.productos.forEach(p => {
                    const est = (p.estado_producto || '').replace('_', ' ');
                    html += `<tr><td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td>${p.subtotal}</td><td>${est}</td></tr>`;
                });
                html += '</tbody></table>';
                if (info.foto_entrega) {
                    html += `<p><img src="../../uploads/evidencias/${info.foto_entrega}" class="img-fluid" alt="evidencia"></p>`;
                }
                $('#modalDetalleContenido').html(html);
                $('#modalDetalle').modal('show');
            } else {
                alert(resp.mensaje || 'Error al obtener detalle');
            }
        }).fail(function () {
            alert('Error al obtener detalle');
        });
    });

    // Exportar corte
    $(document).on('click', '.exportarCsv', function () {
        const corteId = $(this).data('id');
        window.open('../../api/corte_caja/exportar_corte_csv.php?id=' + corteId, '_blank');
    });
});