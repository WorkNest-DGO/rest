let corteActual = null;
const usuarioId = 1; // En entorno real, este valor provendría de la sesión
let pagina = 1;
let limite = 15;
let busqueda = '';
let catalogoDenominaciones = [];

function formatearMoneda(valor) {
    const num = Number(valor);
    if (isNaN(num)) return '$0.00';
    return '$' + num.toFixed(2);
}

async function cargarDenominaciones() {
    try {
        const resp = await fetch('../../api/corte_caja/listar_denominaciones.php');
        const data = await resp.json();
        if (data.success && Array.isArray(data.resultado)) {
            catalogoDenominaciones = data.resultado;
            console.log('Denominaciones cargadas:', catalogoDenominaciones);
        } else {
            console.error('Error al cargar denominaciones');
        }
    } catch (err) {
        console.error('Error al cargar denominaciones');
        console.error(err);
        catalogoDenominaciones = [];
    }
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
        await abrirModalDesglose(corteActual, resumen);
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
                const subtotal = parseFloat(info.total) || 0;
                html += `<li>${metodo}: $${subtotal.toFixed(2)}</li>`;
            }
        }
        const totalGeneral = parseFloat(data.totalFinal || 0);
        html += `</ul><p>Total final: $${totalGeneral.toFixed(2)}</p>`;
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

async function abrirModalDesglose(corteId, dataApi) {
    if (!catalogoDenominaciones.length) {
        await cargarDenominaciones();
    }
    const resumen = dataApi.resultado || {};
    const totalFinal = parseFloat(dataApi.totalFinal) || 0;
    const totalAEntregar = parseFloat(dataApi.totalAEntregar) || 0;

    let montoBoucher = 0;
    let montoCheque = 0;
    try {
        const respNE = await fetch('../../api/corte_caja/totales_no_efectivo.php');
        const dataNE = await respNE.json();
        if (dataNE.success) {
            montoBoucher = parseFloat(dataNE.boucher) || 0;
            montoCheque  = parseFloat(dataNE.cheque) || 0;
        }
    } catch (e) {
        console.error('No se pudo precargar boucher/cheque', e);
    }

    const modal = document.getElementById('modalDesglose');
    let html = '<div style="background:#fff;border:1px solid #333;padding:10px;">';
    html += '<h3>Desglose de caja</h3>';
    html += `<p>Total final: $${totalFinal.toFixed(2)}</p>`;
    html += `<p>Total a entregar (efectivo): $${totalAEntregar.toFixed(2)}</p>`;
    html += '<div id="camposDesglose"></div>';
    html += '<p>Efectivo: $<span id="totalEfectivo">0.00</span> | Boucher: $<span id="totalBoucher">0.00</span> | Cheque: $<span id="totalCheque">0.00</span></p>';
    html += '<p>Total contado: $<span id="totalDesglose">0.00</span> | Dif. total: $<span id="difDesglose">0.00</span></p>';
    html += '<p>Dif. efectivo: $<span id="difEfectivo">0.00</span></p>';
    html += '<button class="btn custom-btn" id="guardarDesglose">Guardar</button> <button id="cancelarDesglose">Cancelar</button>';
    html += '</div>';
    modal.innerHTML = html;
    modal.style.display = 'block';

    if (!catalogoDenominaciones.length) {
        console.error('Error al cargar denominaciones');
        const btnCerrar = modal.querySelector('#cancelarDesglose');
        btnCerrar.addEventListener('click', () => {
            modal.style.display = 'none';
            verificarCorte();
        });
        return;
    }

    const cont = modal.querySelector('#camposDesglose');
    const frag = document.createDocumentFragment();

    catalogoDenominaciones.forEach(d => {
        const div = document.createElement('div');
        div.className = 'grupo-pago';
        div.dataset.tipo = 'efectivo';
        div.innerHTML = `<label>${d.descripcion}</label>` +
            `<input type="number" class="cantidad" data-id="${d.id}" data-valor="${d.valor}" data-tipo="efectivo" min="0" value="0">` +
            '<span class="subtotal">$0.00</span>';
        frag.appendChild(div);
    });

    cont.appendChild(frag);

    function calcular() {
        let totalEfectivo = 0;
        cont.querySelectorAll('.grupo-pago').forEach(gr => {
            const inp = gr.querySelector('.cantidad');
            const valor = parseFloat(inp.dataset.valor) || 0;
            const cantidad = parseFloat(inp.value) || 0;
            const subtotal = valor * cantidad;
            gr.querySelector('.subtotal').textContent = `$${subtotal.toFixed(2)}`;
            totalEfectivo += subtotal;
        });
        document.getElementById('totalEfectivo').textContent = totalEfectivo.toFixed(2);
        document.getElementById('totalBoucher').textContent = montoBoucher.toFixed(2);
        document.getElementById('totalCheque').textContent = montoCheque.toFixed(2);
        const totalGeneral = totalEfectivo + montoBoucher + montoCheque;
        document.getElementById('totalDesglose').textContent = totalGeneral.toFixed(2);
        document.getElementById('difDesglose').textContent = (totalGeneral - totalFinal).toFixed(2);
        document.getElementById('difEfectivo').textContent = (totalAEntregar - totalEfectivo).toFixed(2);
    }

    modal.querySelectorAll('.cantidad').forEach(inp => inp.addEventListener('input', calcular));
    calcular();

    modal.querySelector('#cancelarDesglose').addEventListener('click', () => {
        modal.style.display = 'none';
        verificarCorte();
    });

    modal.querySelector('#guardarDesglose').addEventListener('click', async () => {
        calcular();
        const detalle = [];
        cont.querySelectorAll('.grupo-pago').forEach(gr => {
            const inp = gr.querySelector('.cantidad');
            const cantidad = parseFloat(inp.value) || 0;
            if (cantidad <= 0) return;
            detalle.push({
                denominacion_id: parseInt(inp.dataset.id, 10),
                cantidad: parseInt(cantidad, 10),
                tipo_pago: 'efectivo',
                denominacion: parseFloat(inp.dataset.valor)
            });
        });
        if (!detalle.length) {
            alert('Ingresa al menos una cantidad mayor a cero');
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
}

async function cargarHistorial() {
    try {
        const offset = (pagina - 1) * limite;
        const params = new URLSearchParams({ limit: limite, offset, search: busqueda });
        const fi = document.getElementById('fechaInicio')?.value;
        const ff = document.getElementById('fechaFin')?.value;
        if (fi && ff && fi > ff) {
            alert('La fecha de inicio no puede ser mayor que la fecha fin');
            return;
        }
        if (fi) params.append('fecha_inicio', fi);
        if (ff) params.append('fecha_fin', ff);
        const resp = await fetch('../../api/corte_caja/listar_cortes.php?' + params.toString());
        const data = await resp.json();
        const tbody = document.querySelector('#tablaCortes tbody');
        tbody.innerHTML = '';
        if (data.success) {
            if (Array.isArray(data.resultado.cortes) && data.resultado.cortes.length) {
                data.resultado.cortes.forEach(c => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${c.id}</td>
                        <td>${c.fecha_inicio}</td>
                        <td>${c.fecha_fin || ''}</td>
                        <td>${c.usuario}</td>
                        <td>${formatearMoneda(c.efectivo)}</td>
                        <td>${formatearMoneda(c.boucher)}</td>
                        <td>${formatearMoneda(c.cheque)}</td>
                        <td>${formatearMoneda(c.fondo_inicial)}</td>
                        <td>${formatearMoneda(c.total)}</td>
                        <td>${c.observaciones || ''}</td>
                        <td><button class="btn custom-btn verDetalle" data-id="${c.id}">Ver detalle</button> <button class="btn custom-btn exportarCsv" data-id="${c.id}">Exportar</button></td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="11">Sin resultados</td></tr>';
            }
            construirPaginacion(data.resultado.total);
        } else {
            tbody.innerHTML = '<tr><td colspan="11">Error al cargar</td></tr>';
        }
    } catch (err) {
        console.error(err);
        const tbody = document.querySelector('#tablaCortes tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="11">Error al cargar historial</td></tr>';
    }
}

function construirPaginacion(total) {
    const cont = document.getElementById('paginacion');
    if (!cont) return;
    cont.innerHTML = '';
    const totalPaginas = Math.ceil(total / limite);
    for (let i = 1; i <= totalPaginas; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        btn.className = 'btn custom-btn pagina';
        btn.dataset.pagina = i;
        if (i === pagina) {
            btn.disabled = true;
        }
        cont.appendChild(btn);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#tablaDesglose, .tablaDesglose, .filaDenominacion').forEach(e => e.remove());
    cargarDenominaciones();
    verificarCorte();
    cargarHistorial();

    const sel = document.getElementById('selectRegistros');
    const input = document.getElementById('buscarCorte');
    const pagDiv = document.getElementById('paginacion');
    const btnFiltro = document.getElementById('btnFiltrar');

    if (sel) {
        sel.value = limite;
        sel.addEventListener('change', () => {
            limite = parseInt(sel.value, 10);
            pagina = 1;
            cargarHistorial();
        });
    }

    if (input) {
        input.addEventListener('input', () => {
            busqueda = input.value;
            pagina = 1;
            cargarHistorial();
        });
    }

    if (pagDiv) {
        pagDiv.addEventListener('click', e => {
            if (e.target.classList.contains('pagina')) {
                pagina = parseInt(e.target.dataset.pagina, 10);
                cargarHistorial();
            }
        });
    }

    if (btnFiltro) {
        btnFiltro.addEventListener('click', () => {
            pagina = 1;
            cargarHistorial();
        });
    }

    // Detalle de corte
    $(document).on('click', '.verDetalle', function () {
        const corteId = $(this).data('id');
        if (!corteId) {
            console.error('ID de corte no especificado');
            return;
        }
        fetch(`../../api/corte_caja/resumen_corte_actual.php?corte_id=${corteId}`)
            .then(r => r.json())
            .then(data => {
                const cont = document.getElementById('modalDetalleContenido');
                if (!cont) return;
                if (data.success) {
                    const resumen = data.resultado || {};
                    let html = '<h5>Desglose del Corte</h5>';
                    let totalMontos = 0;
                    let totalPropinas = 0;
                    for (const metodo in resumen) {
                        if (!Object.prototype.hasOwnProperty.call(resumen, metodo)) continue;
                        const info = resumen[metodo] || {};
                        const productos = parseFloat(info.productos) || 0;
                        const propina = parseFloat(info.propina) || 0;
                        totalMontos += productos;
                        totalPropinas += propina;
                        const totalMetodo = productos + propina;
                        html += `<p>${metodo}: $${totalMetodo.toFixed(2)} (Total: $${productos.toFixed(2)} + Propina: $${propina.toFixed(2)})</p>`;
                    }
                    const fondo = parseFloat(data.fondo || 0);
                    const totalEsperado = totalMontos + totalPropinas;
                    const totalFinal = parseFloat(data.totalFinal || (totalEsperado + fondo));
                    html = `<p>Total esperado: $${totalEsperado.toFixed(2)}</p>` + html;
                    html += `<p>Fondo Inicial: $${fondo.toFixed(2)}</p>`;
                    html += `<p><strong>Total Final: $${totalFinal.toFixed(2)}</strong></p>`;
                    cont.innerHTML = html;
                } else {
                    const msg = data.mensaje || 'Error al obtener resumen';
                    cont.innerHTML = `<p class="text-center">${msg}</p>`;
                }
                $('#modalDetalle').modal('show');
            })
            .catch(() => {
                const cont = document.getElementById('modalDetalleContenido');
                if (cont) cont.innerHTML = '<p class="text-center">Error al obtener resumen</p>';
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
        window.open('../../api/corte_caja/exportar_corte_csv.php?corte_id=' + corteId, '_blank');
    });
});
