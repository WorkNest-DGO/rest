let corteActual = null;
const usuarioId = 1; // En entorno real, este valor provendría de la sesión

async function verificarCorte() {
    try {
        const resp = await fetch('../../api/corte_caja/verificar_corte_abierto.php?usuario_id=' + usuarioId);
        const data = await resp.json();
        const cont = document.getElementById('corteActual');
        cont.innerHTML = '';
        if (data.success && data.abierto) {
            corteActual = data.corte_id;
            cont.innerHTML = `<p>Inicio: ${data.fecha_inicio}</p><button id="btnCerrar">Cerrar Corte</button> <button id="btnImprimir">Imprimir</button>`;
            document.getElementById('btnCerrar').addEventListener('click', cerrarCorte);
            document.getElementById('btnImprimir').addEventListener('click', imprimirResumen);
        } else {
            corteActual = null;
            cont.innerHTML = '<button id="btnIniciar">Iniciar Corte</button>';
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
        if (!resumen.success || !resumen.resultado.abierto) {
            alert('No hay resumen disponible');
            return;
        }
        const r = resumen.resultado;
        let mensaje = `Ventas: ${r.num_ventas}\nTotal: $${r.total}\nPropinas: $${r.propinas}`;
        const obs = prompt(mensaje + '\nObservaciones:');
        if (obs === null) return;
        const resp = await fetch('../../api/corte_caja/cerrar_corte.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ corte_id: corteActual, usuario_id: usuarioId, observaciones: obs })
        });
        const data = await resp.json();
        if (data.success) {
            const id = corteActual;
            alert(`Ventas: ${data.resultado.ventas_realizadas}\nTotal: $${data.resultado.total}`);
            corteActual = null;
            abrirModalDesglose(id);
            await cargarHistorial();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cerrar corte');
    }
}

async function imprimirResumen() {
    try {
        const resp = await fetch('../../api/corte_caja/resumen_corte_actual.php?usuario_id=' + usuarioId);
        const data = await resp.json();
        if (!data.success || !data.resultado.abierto) {
            alert('No hay corte abierto');
            return;
        }
        const r = data.resultado;
        let html = `<h3>Resumen de corte</h3>`;
        html += `<p>Total: $${r.total}</p>`;
        html += `<p>Ventas: ${r.num_ventas}</p>`;
        html += `<p>Propinas: $${r.propinas}</p>`;
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
            html += '<table border="1"><thead><tr><th>ID</th><th>Fecha</th><th>Total</th><th>Usuario</th><th>Propina</th></tr></thead><tbody>';
            data.resultado.forEach(v => {
                html += `<tr><td>${v.id}</td><td>${v.fecha}</td><td>${v.total}</td><td>${v.usuario}</td><td>${v.propina}</td></tr>`;
            });
            html += '</tbody></table><button id="cerrarDetalle">Cerrar</button>';
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

function abrirModalDesglose(corteId) {
    const modal = document.getElementById('modalDesglose');
    let html = '<div style="background:#fff;border:1px solid #333;padding:10px;">';
    html += '<h3>Desglose de efectivo</h3>';
    html += '<table id="tablaDesglose" border="1"><thead><tr><th>Denominación</th><th>Cantidad</th><th>Tipo</th><th></th></tr></thead><tbody></tbody></table>';
    html += '<button id="addFila">Agregar fila</button> <button id="guardarDesglose">Guardar desglose</button> <button id="cancelarDesglose">Cancelar</button>';
    html += '</div>';
    modal.innerHTML = html;
    modal.style.display = 'block';

    const tbody = modal.querySelector('#tablaDesglose tbody');

    function agregarFila() {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><input type="number" step="0.01" class="denominacion"></td>`+
            `<td><input type="number" min="0" class="cantidad" value="0"></td>`+
            `<td><select class="tipo"><option value="efectivo">efectivo</option><option value="cheque">cheque</option><option value="boucher">boucher</option></select></td>`+
            `<td><button class="delFila">X</button></td>`;
        tbody.appendChild(tr);
        tr.querySelector('.delFila').addEventListener('click', () => tr.remove());
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

        try {
            const resp = await fetch('../../api/corte_caja/guardar_desglose.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ corte_id: corteId, detalle })
            });
            const data = await resp.json();
            if (data.success) {
                alert('Desglose guardado');
                modal.style.display = 'none';
                verificarCorte();
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
                    <td><button class="detalle" data-id="${c.id}">Ver detalle</button> <a href="../../api/corte_caja/exportar_corte_csv.php?corte_id=${c.id}">Exportar</a></td>
                `;
                tbody.appendChild(tr);
            });
            tbody.querySelectorAll('.detalle').forEach(btn => {
                btn.addEventListener('click', () => verDetalle(btn.dataset.id));
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
});
