function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;


if (typeof catalogoDenominaciones !== 'undefined' && Array.isArray(catalogoDenominaciones) && catalogoDenominaciones.length > 0) {
    console.log('Denominaciones cargadas:', catalogoDenominaciones);
} else {
    console.warn('Denominaciones no disponibles aún');
}


let currentPage = 1;
let limit = 15;
const order = 'fecha DESC';
let searchQuery = '';

async function cargarHistorial(page = currentPage) {
    currentPage = page;
    try {
        const resp = await fetch(
            `../../api/ventas/listar_ventas.php?pagina=${currentPage}&limite=${limit}&orden=${encodeURIComponent(order)}&busqueda=${encodeURIComponent(searchQuery)}`
        );
        const data = await resp.json();
        if (data.success) {
            const tbody = document.querySelector('#historial tbody');
            tbody.innerHTML = '';
            ventasData = {};
            const hoy = new Date().toISOString().split('T')[0];
            (data.resultado.ventas || []).forEach(v => {
                const id = v.venta_id || v.id; // compatibilidad con vista
                ventasData[id] = v;
                const row = document.createElement('tr');
                const fechaParte = (v.fecha || '').split(' ')[0];
                if (fechaParte === hoy) {
                    row.classList.add('table-info');
                }
                const fechaMostrar = fechaParte === hoy ? 'Hoy' : v.fecha;
                const montoRecibidoNum = (v.monto_recibido !== undefined && v.monto_recibido !== null)
                    ? parseFloat(v.monto_recibido)
                    : NaN;
                const totalNetoNum = (v.total_neto !== undefined && v.total_neto !== null)
                    ? parseFloat(v.total_neto)
                    : NaN;
                const totalBase = parseFloat(v.total) || 0;
                const totalMostrarNum = Number.isFinite(montoRecibidoNum)
                    ? montoRecibidoNum
                    : Number.isFinite(totalNetoNum)
                        ? totalNetoNum
                        : totalBase;
                const totalMostrar = totalMostrarNum.toFixed(2);
                const accion = v.estatus !== 'cancelada'
                    ? `<button class=\"btn custom-btn btn-cancelar\" data-id=\"${id}\">Cancelar</button>`
                    : '';
                const btnEnvio = v.cliente_id
                    ? `<button class=\"btn custom-btn btn-ver-envio\" data-id=\"${id}\" data-toggle=\"modal\" data-target=\"#modalClienteEnvio\">Ver envío</button>`
                    : '';
                const destino = v.tipo_entrega === 'mesa'
                    ? v.mesa
                    : v.tipo_entrega === 'domicilio'
                        ? v.repartidor
                        : 'Venta rápida';
                const entregado = v.tipo_entrega === 'domicilio'
                    ? (parseInt(v.entregado) === 1 ? 'Entregado' : 'No entregado')
                    : 'N/A';
                row.innerHTML = `
                    <td>${v.folio ? v.folio : 'N/A'}</td>
                    <td>${fechaMostrar}</td>
                    <td>${totalMostrar}</td>
                    <td>${v.tipo_entrega}</td>
                    <td>${destino || ''}</td>
                    <td>${v.observacion ? String(v.observacion) : ''}</td>
                    <td>${v.estatus}</td>
                    <td>${entregado}</td>
                    <td>
                      <button class=\"btn custom-btn btn-detalle\" data-id=\"${id}\" data-toggle=\"modal\" data-target=\"#modal-detalles\">Ver detalles</button>
                      ${btnEnvio}
                    </td>
                    <td>${accion}</td>
                `;
                tbody.appendChild(row);
            });
            renderPagination(data.resultado.total_paginas, data.resultado.pagina_actual);
        } else {
            alert(data.mensaje);
        }
        await actualizarEstadoBotonCerrarCorte();
    } catch (err) {
        console.error(err);
        alert('Error al cargar ventas');
    }
}

function renderPagination(total, page) {
    const cont = document.getElementById('paginacion');
    cont.innerHTML = '';
    if (total <= 1) return;
    if (page > 1) {
        const prev = document.createElement('button');
        prev.textContent = 'Anterior';
        prev.className = 'btn custom-btn me-1';
        prev.addEventListener('click', () => cargarHistorial(page - 1));
        cont.appendChild(prev);
    }
    for (let i = 1; i <= total; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        btn.className = 'btn custom-btn me-1';
        if (i === page) btn.disabled = true;
        btn.addEventListener('click', () => cargarHistorial(i));
        cont.appendChild(btn);
    }
    if (page < total) {
        const next = document.createElement('button');
        next.textContent = 'Siguiente';
        next.className = 'btn custom-btn';
        next.addEventListener('click', () => cargarHistorial(page + 1));
        cont.appendChild(next);
    }
}

const usuarioId = window.usuarioId || 1; // ID del cajero proveniente de la sesión
const sedeId = window.sedeId || 1;
let corteIdActual = window.corteId || null;
// Catálogo global utilizado por utils/js/buscador.js
let catalogo = window.catalogo || [];
window.catalogo = catalogo;
let productos = [];
let productosData = [];
let ventasData = {};
let repartidores = [];
let ticketRequests = [];
let ventaIdActual = null;
let mesas = [];
let coloniasData = [];
let clientesDomicilio = [];
let clienteSeleccionado = null;
let impresorasData = [];
let impresoraSeleccionada = null;
// Catálogo de promociones para selección en ventas
let catalogoPromocionesVenta = [];
let catalogoPromocionesVentaFiltradas = [];
let panelPromosVentaInicializado = false;
window.__promosVentaSeleccionadas = [];
const promocionesUrlVentas = '../../api/tickets/promociones.php';
let clienteColoniaOriginalId = null;
let clienteSeleccionadoIdRef = null;
const normalizarClienteTexto = (txt) => {
    if (typeof normalizarTexto === 'function') return normalizarTexto(txt);
    return (txt || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
};

// ==== [INICIO BLOQUE valida: validación para cierre de corte] ====
const VENTAS_URL = typeof API_LISTAR_VENTAS !== 'undefined' ? API_LISTAR_VENTAS : '../../api/ventas/listar_ventas.php';
const MESAS_URL = typeof API_LISTAR_MESAS !== 'undefined' ? API_LISTAR_MESAS : '../../api/mesas/listar_mesas.php';

// Devuelve { hayVentasActivas, hayMesasOcupadas, bloqueado }
async function hayBloqueosParaCerrarCorte() {
    const [ventasResp, mesasResp] = await Promise.all([
        fetch(VENTAS_URL, { cache: 'no-store' }).then(r => r.json()),
        fetch(MESAS_URL, { cache: 'no-store' }).then(r => r.json())
    ]);

    const ventas = (ventasResp && ventasResp.resultado && ventasResp.resultado.ventas) || [];
    const hayVentasActivas = ventas.some(v => String(v.estatus || '').toLowerCase() === 'activa');

    const mesas = (mesasResp && mesasResp.resultado) || [];
    const hayMesasOcupadas = mesas.some(m => String(m.estado || '').toLowerCase() === 'ocupada');

    return { hayVentasActivas, hayMesasOcupadas, bloqueado: (hayVentasActivas || hayMesasOcupadas) };
}

function toggleBotonCerrarCorte(bloqueado, detalle = '') {
    const btn = document.getElementById('btnCerrarCorte');
    if (!btn) return;
    btn.disabled = !!bloqueado;
    btn.title = bloqueado
        ? ('No puedes cerrar el corte: hay pendientes' + (detalle ? ' ' + detalle : ''))
        : '';
}

// Ejecuta validación y actualiza estado del botón
async function actualizarEstadoBotonCerrarCorte() {
    try {
        const { hayVentasActivas, hayMesasOcupadas, bloqueado } = await hayBloqueosParaCerrarCorte();
        const partes = [];
        if (hayMesasOcupadas) partes.push('[mesas ocupadas]');
        if (hayVentasActivas) partes.push('[ventas activas]');
        toggleBotonCerrarCorte(bloqueado, partes.join(' '));
    } catch (e) {
        console.error('Validación de corte falló:', e);
        // Falla de validación => bloquear por seguridad
        toggleBotonCerrarCorte(true, '[no se pudo validar]');
    }
}

// Valida y, sólo si no hay bloqueos, abre la modal existente
async function validarYAbrirModalCierre() {
    try {
        const { hayVentasActivas, hayMesasOcupadas, bloqueado } = await hayBloqueosParaCerrarCorte();
        if (bloqueado) {
            const msg = [
                'No puedes cerrar el corte ni mostrar el desglose mientras existan pendientes.',
                hayMesasOcupadas ? '- Mesas: hay al menos una en estado OCUPADA.' : '',
                hayVentasActivas ? '- Ventas: hay al menos una en estatus ACTIVA.' : ''
            ].filter(Boolean).join('\n');
            alert(msg);
            return; // <-- No abrir modal
        }
        if (typeof cerrarCaja === 'function') {
            cerrarCaja();
        } else if (typeof abrirModalDesglose === 'function') {
            abrirModalDesglose();
        } else if (typeof abrirModalCierre === 'function') {
            abrirModalCierre();
        } else {
            console.warn('No se encontró la función para abrir la modal de desglose.');
        }
    } catch (e) {
        console.error(e);
        alert('No fue posible validar el estado de mesas/ventas. Intenta de nuevo.');
    }
}

// Hook al cargar y al hacer click en el botón
document.addEventListener('DOMContentLoaded', () => {
    actualizarEstadoBotonCerrarCorte();

    const btn = document.getElementById('btnCerrarCorte');
    if (btn) {
        btn.addEventListener('click', (ev) => {
            ev.preventDefault();
            validarYAbrirModalCierre();
        });
    }
});
// ==== [FIN BLOQUE valida] ====

// ====== Utilidades de formateo ======
const TKT_WIDTH = 42;

function money(n) {
    const num = Number(n) || 0;
    return '$ ' + num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function repeat(ch, times) { return ch.repeat(Math.max(0, times)); }
function padLeft(txt, w) { const s = String(txt); return (repeat(' ', w) + s).slice(-w); }
function padRight(txt, w) { const s = String(txt); return (s + repeat(' ', w)).slice(0, w); }
function center(txt) {
    const s = String(txt);
    const pad = Math.max(0, Math.floor((TKT_WIDTH - s.length) / 2));
    return repeat(' ', pad) + s;
}
function lineKV(label, value) {
    const L = String(label);
    const V = String(value);
    const spaces = Math.max(1, TKT_WIDTH - L.length - V.length);
    return L + repeat(' ', spaces) + V;
}
function nowISO() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

// ====== Generador de ticket de cierre ======
function buildCorteTicket(resultado, cajeroOpt) {
    // resultado = objeto con la estructura del API: efectivo, boucher, cheque, totales, arrays, etc.
    const r = resultado || {};
    const cajero = cajeroOpt || r.cajero || '';
    const fechaInicio = r.fecha_inicio || '';
    const fechaFin = nowISO();
    const folInicio = r.folio_inicio ?? '';
    const folFin = r.folio_fin ?? '';
    const folCount = r.total_folios ?? '';
    const corteId = r.corte_id ?? '';

    const efectivo = r.efectivo || {};
    const boucher = r.boucher || {};
    const cheque = r.cheque || {};
    const tarjeta = r.tarjeta || {};
    const transferencia = r.transferencia || {};

    const totalProductos = Number(r.total_productos || 0);
    const totalPropinas = Number(r.total_propinas || 0);
    const totalEsperado = Number(r.totalEsperado || 0);
    const totalEsperadoEfectivo = Number(r.totalEsperadoEfectivo || r.esperado_efectivo || 0);
    const totalEsperadoNoEfectivo = Number(r.totalEsperadoNoEfectivo || 0);
    const fondo = Number(r.fondo || 0);
    const totalDepositos = Number(r.total_depositos || 0);
    const totalRetiros = Number(r.total_retiros || 0);
    const totalFinalEfectivo = Number(r.totalFinalEfectivo ?? r.totalFinal ?? 0);
    const totalFinalGeneral = Number(r.totalFinalGeneral || totalFinalEfectivo);
    const dif = totalFinalEfectivo - (totalEsperadoEfectivo + fondo + totalDepositos - totalRetiros);

    const totalMeseros = Array.isArray(r.total_meseros)
        ? r.total_meseros
        : (Array.isArray(r.totales_mesero) ? r.totales_mesero : []);
    const totalRapido = Number(r.total_rapido || 0);
    const totalRepart = Array.isArray(r.total_repartidor)
        ? r.total_repartidor
        : (Array.isArray(r.totales_repartidor) ? r.totales_repartidor : []);

    const desglose = Array.isArray(r.desglose) ? r.desglose : [];
    const desgEfectivo = desglose.filter(x => (x.tipo_pago || '').toLowerCase() === 'efectivo' && Number(x.denominacion) > 0);
    const desgNoEf = desglose.filter(x => (x.tipo_pago || '').toLowerCase() !== 'efectivo');

    // Agrupar no-efectivo por tipo_pago (sumar por 'cantidad' asumiendo que en no-efectivo 'cantidad' representa monto)
    const mapNoEf = {};
    desgNoEf.forEach(x => {
        const key = (x.tipo_pago || 'otro').toLowerCase();
        const monto = Number(x.cantidad || 0);
        mapNoEf[key] = (mapNoEf[key] || 0) + monto;
    });

    let out = '';
    out += repeat('=', TKT_WIDTH) + '\n';
    out += center('CORTE / CIERRE DE CAJA') + '\n';
    out += repeat('=', TKT_WIDTH) + '\n';
    out += lineKV(`Corte ID: ${corteId}`, '') + '\n';
    out += lineKV(`Inicio: ${fechaInicio}`, '') + '\n';
    out += lineKV(`Fin:    ${fechaFin}`, '') + '\n';
    if (folInicio || folFin || folCount) {
        const folText = `Folios: ${folInicio}â€“${folFin} (${folCount})`;
        out += lineKV(folText, '') + '\n';
    }
    out += '\n';

    // Totales por forma de pago (efectivo/boucher/cheque si existen)
    out += '-- Totales por forma de pago ' + repeat('-', TKT_WIDTH - 27) + '\n';
    const printFP = (label, obj) => {
        if (!obj || (obj.productos == null && obj.propina == null && obj.total == null)) return;
        const prod = money(obj.productos || 0);
        const prop = money(obj.propina || 0);
        const tot = money(obj.total || 0);
        out += lineKV(padRight(label, 12) + ' Prod:', padLeft(prod, 12)) + '\n';
        out += lineKV(padRight('', 12) + ' Prop:', padLeft(prop, 12)) + '\n';
        out += lineKV(padRight('', 12) + ' TOTAL:', padLeft(tot, 12)) + '\n';
    };
    printFP('Efectivo', efectivo);
    printFP('Boucher', boucher);
    printFP('Cheque', cheque);
    printFP('Tarjeta', tarjeta);
    printFP('Transf.', transferencia);
    out += '\n';

        // Conciliacion
    out += repeat('-', TKT_WIDTH) + '\n';
    out += lineKV('Total productos:', padLeft(money(totalProductos), 14)) + '\n';
    out += lineKV('Total propinas:', padLeft(money(totalPropinas), 14)) + '\n';
    out += lineKV('Total esperado:', padLeft(money(totalEsperado), 14)) + '\n';
    out += lineKV('Esperado efectivo:', padLeft(money(totalEsperadoEfectivo), 14)) + '\n';
    out += lineKV('Esperado no efectivo:', padLeft(money(totalEsperadoNoEfectivo), 14)) + '\n';
    out += lineKV('Fondo inicial:', padLeft(money(fondo), 14)) + '\n';
    out += lineKV('Depositos:', padLeft(money(totalDepositos), 14)) + '\n';
    out += lineKV('Retiros:', padLeft(money(totalRetiros), 14)) + '\n';
    out += repeat('-', TKT_WIDTH) + '\n';
    out += lineKV('Efectivo esperado en caja:', padLeft(money(totalFinalEfectivo), 14)) + '\n';
    out += lineKV('Total general (ref.):', padLeft(money(totalFinalGeneral), 14)) + '\n';
    const difLabel = 'DIF efectivo (esp - calc):';
    out += lineKV(difLabel, padLeft(money(dif), 14)) + '\n';
    out += repeat('-', TKT_WIDTH) + '\n\n';

    // Meseros
    if (totalMeseros.length) {
        out += '-- Ventas por mesero ' + repeat('-', TKT_WIDTH - 22) + '\n';
        totalMeseros.forEach(m => {
            const name = String(m.nombre || '').slice(0, 24);
            const val = money(Number(m.total || 0));
            out += lineKV(padRight(name, 28), padLeft(val, 12)) + '\n';
        });
        out += '\n';
    }

    // Mostrador / rápido
    if (!isNaN(totalRapido)) {
        out += lineKV('Ventas mostrador/rápido .....', padLeft(money(totalRapido), 12)) + '\n\n';
    }

    // Repartidores
    if (totalRepart.length) {
        out += '-- Repartidores ' + repeat('-', TKT_WIDTH - 16) + '\n';
        totalRepart.forEach(x => {
            const name = String(x.nombre || '').slice(0, 24);
            const val = money(Number(x.total || 0));
            out += lineKV(padRight(name, 28), padLeft(val, 12)) + '\n';
        });
        out += '\n';
    }

    // Desglose
    out += '-- Desglose de denominaciones ' + repeat('-', TKT_WIDTH - 29) + '\n';
    if (desgEfectivo.length) {
        out += '[EFECTIVO]\n';
        desgEfectivo.forEach(x => {
            const den = Number(x.denominacion || 0);
            const cant = Number(x.cantidad || 0);
            const subtotal = den * cant;
            const left = `${money(den)}  x ${padLeft(cant, 5)}  =`;
            out += lineKV(left, padLeft(money(subtotal), 12)) + '\n';
        });
        out += '\n';
    }
    if (Object.keys(mapNoEf).length) {
        out += '[NO EFECTIVO]\n';
        Object.keys(mapNoEf).forEach(k => {
            const label = k.charAt(0).toUpperCase() + k.slice(1);
            out += lineKV(padRight(label, 30), padLeft(money(mapNoEf[k]), 12)) + '\n';
        });
        out += '\n';
    }

    out += repeat('-', TKT_WIDTH) + '\n';
    out += lineKV('Impreso:', nowISO()) + '\n';
    if (cajero) out += lineKV('Cajero:', cajero) + '\n';
    out += repeat('=', TKT_WIDTH) + '\n';
    return out;
}

// ====== Controladores del modal ======
function showCortePreview(resultado, cajero) {
    window.open(urlConImpresora('../../api/corte_caja/imprime_corte.php?datos=' + JSON.stringify(resultado) + '&detalle=' + JSON.stringify(cajero)));
    // try {
    //   const txt = buildCorteTicket(resultado, cajero);
    //   const pre = document.getElementById('corteTicketText');
    //   pre.textContent = txt;
    //   showModal('#modalCortePreview');
    // } catch (e) {
    //   console.error('Error construyendo ticket de corte:', e);
    //   alert('No se pudo generar la previsualización del corte.');
    // }
}

(function wireModalCorte() {
    const btnP = document.getElementById('btnImprimirCorte');
    if (btnP) btnP.addEventListener('click', () => { window.print(); });
})();

function imprimirTicket(ventaId) {
    if (!ventaId) {
        const v = document.getElementById('venta_id');
        ventaId = v ? v.value : '';
    }
    if (!ventaId) {
        alert('No hay venta para imprimir.');
        return;
    }
    window.location.href = `ticket.php?venta=${encodeURIComponent(ventaId)}`;
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="imprimir-ticket"]');
    if (!btn) return;
    e.preventDefault();
    const ventaId = btn.dataset.ventaId || (document.getElementById('venta_id')?.value || '');
    const mesaId = btn.dataset.mesaId;
    if (ventaId && mesaId) {
        imprimirSolicitud(mesaId, ventaId);
        return;
    }
    if (!ventaId) {
        alert('No hay venta para imprimir.');
        return;
    }
    if (mesaId) {
        ticketPrinted(mesaId);
    }
    window.location.href = `ticket.php?venta=${encodeURIComponent(ventaId)}`;
});

function deshabilitarCobro() {
    document.querySelectorAll('#formVenta input, #formVenta select, #formVenta button')
        .forEach(el => {
            if (!el.closest('#controlCaja')) {
                el.disabled = true;
            }
        });
    const btns = ['#btnDeposito', '#btnRetiro', '#btnDetalleMovs'];
    btns.forEach(sel => { const b = document.querySelector(sel); if (b) b.disabled = true; });
}

// ===== Impresoras =====
function getImpresoraSeleccionada() {
    return impresoraSeleccionada || localStorage.getItem('impresora_print_id') || '';
}

function setImpresoraSeleccionada(val) {
    impresoraSeleccionada = val || '';
    localStorage.setItem('impresora_print_id', impresoraSeleccionada);
    const sel = document.getElementById('impresora_id');
    if (sel) sel.value = impresoraSeleccionada;
}

function urlConImpresora(url) {
    const pid = getImpresoraSeleccionada();
    if (!pid) return url;
    return url + (url.includes('?') ? '&' : '?') + 'print_id=' + encodeURIComponent(pid);
}

async function cargarImpresoras() {
    try {
        const res = await fetch('../../api/impresoras/listar.php');
        const data = await res.json();
        if (!data.success) throw new Error(data.mensaje || 'Error al listar impresoras');
        impresorasData = Array.isArray(data.resultado) ? data.resultado : [];
        const sel = document.getElementById('impresora_id');
        if (sel) {
            sel.innerHTML = '<option value="">Selecciona impresora</option>';
            impresorasData.forEach(imp => {
                const opt = document.createElement('option');
                opt.value = imp.print_id;
                opt.textContent = `${imp.lugar} (${imp.ip})`;
                sel.appendChild(opt);
            });
            const stored = getImpresoraSeleccionada();
            if (stored) sel.value = stored;
            sel.addEventListener('change', () => setImpresoraSeleccionada(sel.value));
            // Mostrar select solo si hay datos
            const wrap = sel.closest('.form-group');
            if (wrap) wrap.style.display = '';
        }
    } catch (e) {
        console.error('No se pudieron cargar impresoras', e);
    }
}

async function imprimirComanda(ventaId) {
    if (!ventaId) return;
    try {
        const url = urlConImpresora('../../api/tickets/imprime_comanda.php?venta_id=' + encodeURIComponent(ventaId));
        console.log('[COMANDA] solicitando impresión', url);
        const res = await fetch(url, { method: 'GET' });
        if (!res.ok) {
            console.error('[COMANDA] error HTTP', res.status);
            alert('No se pudo enviar la comanda (' + res.status + ')');
        }
    } catch (e) {
        console.error('[COMANDA] excepción', e);
        alert('No se pudo imprimir la comanda');
    }
}

function mostrarModalVentaRegistrada(ventaId) {
    const ventaTarget = ventaId || window.__ultimaVentaRegistrada || null;
    const overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(0,0,0,0.6)';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.zIndex = '2000';

    const box = document.createElement('div');
    box.style.background = '#111';
    box.style.color = '#fff';
    box.style.padding = '20px';
    box.style.borderRadius = '10px';
    box.style.maxWidth = '360px';
    box.style.width = '90%';
    box.innerHTML = `<h4 style="margin-top:0;">Venta registrada</h4><p>La venta se guardó correctamente.</p>`;

    const btn = document.createElement('button');
    btn.className = 'btn custom-btn';
    btn.textContent = 'Cerrar';
    btn.addEventListener('click', async () => {
        if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
        if (ventaTarget) {
            console.log('[COMANDA][modal] disparando impresión para venta', ventaTarget);
            try { await imprimirComanda(ventaTarget); } catch (e) { console.error('[COMANDA][modal] fallo', e); }
        } else {
            console.warn('[COMANDA][modal] sin ventaId para imprimir');
        }
    });

    box.appendChild(btn);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
}

function habilitarCobro() {
    document.querySelectorAll('#formVenta input, #formVenta select, #formVenta button')
        .forEach(el => {
            if (!el.closest('#controlCaja')) {
                el.disabled = false;
            }
        });
    const btns = ['#btnDeposito', '#btnRetiro', '#btnDetalleMovs'];
    btns.forEach(sel => { const b = document.querySelector(sel); if (b) b.disabled = false; });
}

async function verificarCorte() {
    fetch('../../api/corte_caja/verificar_corte_abierto.php', {
        credentials: 'include'
    })
        .then(resp => resp.json())
        .then(data => {
            const cont = document.getElementById('controlCaja');
            cont.innerHTML = '';

            if (data.success && data.resultado.abierto) {
                cont.innerHTML = `<button class="btn custom-btn" id="btnCerrarCorte">Cerrar corte</button> <button id="btnCorteTemporal" class="btn btn-warning">Corte Temporal</button> <button id="btnPropinasUsuarios" class="btn custom-btn">Propinas por usuario</button> <button id="btnEnviosRepartidor" class="btn custom-btn">Total envios</button>`;
                const btnCerrar = document.getElementById('btnCerrarCorte');
                if (btnCerrar) {
                    btnCerrar.addEventListener('click', (ev) => {
                        ev.preventDefault();
                        validarYAbrirModalCierre();
                    });
                }
                document.getElementById('btnCorteTemporal').addEventListener('click', abrirCorteTemporal);
                const btnProp = document.getElementById('btnPropinasUsuarios');
                if (btnProp) btnProp.addEventListener('click', abrirPropinasPorUsuario);
                const btnEnvios = document.getElementById('btnEnviosRepartidor');
                if (btnEnvios) btnEnvios.addEventListener('click', abrirEnviosRepartidor);
                habilitarCobro();
            } else {
                cont.innerHTML = `<button class="btn custom-btn" id="btnAbrirCaja">Abrir caja</button>`;
                document.getElementById('btnAbrirCaja').addEventListener('click', abrirCaja);
                deshabilitarCobro();
            }
            actualizarEstadoBotonCerrarCorte();
        });

}

async function abrirCaja() {
    const fondoData = await fetch('../../api/corte_caja/consultar_fondo.php?usuario_id=' + usuarioId)
        .then(r => r.json());

    let modal = document.getElementById('modalAbrirCaja');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modalAbrirCaja';
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');

        const dialog = document.createElement('div');
        dialog.className = 'modal-dialog';

        const content = document.createElement('div');
        content.className = 'modal-content';

        const body = document.createElement('div');
        body.className = 'modal-body bg-dark text-white';

        const labelIntro = document.createElement('p');
        labelIntro.textContent = 'Captura el desglose de denominaciones para abrir la caja. El total calculado se usará como fondo de apertura.';
        body.appendChild(labelIntro);

        const contDen = document.createElement('div');
        contDen.id = 'contenedorDenominacionesApertura';
        contDen.className = 'd-flex flex-column gap-2';
        body.appendChild(contDen);

        const resumen = document.createElement('p');
        resumen.innerHTML = 'Total contado: $<strong id="totalAperturaEfectivo">0.00</strong>';
        body.appendChild(resumen);

        const label = document.createElement('label');
        label.textContent = 'Monto de apertura (calculado):';

        const input = document.createElement('input');
        input.type = 'number';
        input.id = 'montoApertura';
        input.className = 'form-control';
        input.readOnly = true;

        body.appendChild(label);
        body.appendChild(input);

        const footer = document.createElement('div');
        footer.className = 'modal-footer';

        const btnAbrir = document.createElement('button');
        btnAbrir.id = 'btnAbrirCajaModal';
        btnAbrir.className = 'btn custom-btn';
        btnAbrir.textContent = 'Abrir Caja';

        const btnCancelar = document.createElement('button');
        btnCancelar.className = 'btn custom-btn';
        btnCancelar.textContent = 'Cancelar';

        footer.appendChild(btnAbrir);
        footer.appendChild(btnCancelar);

        content.appendChild(body);
        content.appendChild(footer);
        dialog.appendChild(content);
        modal.appendChild(dialog);
        document.body.appendChild(modal);

        if (!document.getElementById('modalAbrirCajaStyles')) {
            const style = document.createElement('style');
            style.id = 'modalAbrirCajaStyles';
            style.textContent = '#modalAbrirCaja .modal-footer{display:flex;justify-content:flex-end;gap:0.5rem;}#contenedorDenominacionesApertura .grupo-pago{background:#2b2b2b;border-radius:6px;padding:8px;}';
            document.head.appendChild(style);
        }

        btnCancelar.addEventListener('click', () => hideModal(modal));
    }

    const inputMonto = modal.querySelector('#montoApertura');

    const contDen = modal.querySelector('#contenedorDenominacionesApertura');
    contDen.innerHTML = '';
    const frag = document.createDocumentFragment();

    if (!Array.isArray(catalogoDenominaciones) || !catalogoDenominaciones.length) {
        const alerta = document.createElement('div');
        alerta.className = 'alert alert-warning';
        alerta.textContent = 'No hay denominaciones configuradas. Captura manualmente el monto de apertura.';
        contDen.appendChild(alerta);
        inputMonto.readOnly = false;
    } else {
        catalogoDenominaciones.forEach(d => {
            const did = Number(d.id);
            if (did === 12 || did === 13) return;
            const div = document.createElement('div');
            div.className = 'grupo-pago d-flex align-items-center gap-2';
            div.dataset.tipo = 'efectivo';
            div.innerHTML = `
                <label class="mb-0" style="min-width:160px;">${d.descripcion}</label>
                <input type="number" inputmode="numeric" step="1" class="cantidad form-control form-control-sm"
                       style="max-width:180px; text-align:center;"
                       data-id="${d.id}" data-valor="${d.valor}" data-tipo="efectivo" min="0" value="0">
                <span class="subtotal ms-2">$0.00</span>`;
            frag.appendChild(div);
        });
        contDen.appendChild(frag);
    }

    function recalcularTotalApertura() {
        let total = 0;
        contDen.querySelectorAll('.grupo-pago').forEach(gr => {
            const inp = gr.querySelector('.cantidad');
            const valor = parseFloat(inp.dataset.valor) || 0;
            const cantidad = parseFloat(inp.value) || 0;
            const subtotal = valor * cantidad;
            gr.querySelector('.subtotal').textContent = `$${subtotal.toFixed(2)}`;
            total += subtotal;
        });
        const totalLabel = modal.querySelector('#totalAperturaEfectivo');
        if (totalLabel) totalLabel.textContent = total.toFixed(2);
        if (total) {
            inputMonto.value = total.toFixed(2);
        }
        return total;
    }

    contDen.querySelectorAll('.cantidad').forEach(inp => inp.addEventListener('input', recalcularTotalApertura));
    if (fondoData.existe) {
        inputMonto.value = fondoData.monto;
    }
    recalcularTotalApertura();

    const btnAbrir = modal.querySelector('#btnAbrirCajaModal');
    btnAbrir.onclick = async () => {
        const totalApertura = recalcularTotalApertura();
        const monto = inputMonto.value.trim();
        if ((monto === '' || isNaN(parseFloat(monto))) && totalApertura <= 0) {
            alert('Debes indicar un monto o capturar denominaciones');
            return;
        }
        // Verificar si ya existe un corte abierto de otro usuario
        try {
            const anyCorte = await fetch('../../api/corte_caja/verificar_corte_abierto_global.php', { credentials: 'include' }).then(r=>r.json());
            if (anyCorte && anyCorte.success && anyCorte.resultado.abierto) {
                const u = anyCorte.resultado.usuario || {};
                if (parseInt(u.id) !== parseInt(usuarioId)) {
                    const nombre = u.nombre || u.usuario || 'desconocido';
                    showAppMsg(`Corte de Usuario (${nombre}) abierto, segunda caja no habilitada`);
                    return;
                }
            }
        } catch (e) {
            console.warn('No se pudo validar cortes abiertos globales:', e);
        }
        const montoApertura = parseFloat(monto || totalApertura || 0);
        if (!fondoData.existe) {
            await fetch('../../api/corte_caja/guardar_fondo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ usuario_id: usuarioId, monto: montoApertura })
            });
        }
        try {
            const resp = await fetch('../../api/corte_caja/iniciar_corte.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ usuario_id: usuarioId })
            });
            const data = await resp.json();
            if (data.success) {
                corteIdActual = data.resultado ? data.resultado.corte_id : data.corte_id;
                const detalle = [];
                contDen.querySelectorAll('.grupo-pago').forEach(gr => {
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

                if (detalle.length) {
                    try {
                        await fetch('../../api/corte_caja/guardar_desglose.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ corte_id: corteIdActual, detalle })
                        });
                    } catch (e) {
                        console.warn('No se pudo guardar el desglose de apertura', e);
                    }
                }

                hideModal(modal);
                await verificarCorte();
            } else {
                alert(data.mensaje);
            }
        } catch (err) {
            console.error(err);
            alert('Error al abrir caja');
        }
    };

    showModal(modal);
}

async function cerrarCaja() {
    try {
        const resumenResp = await fetch(`../../api/corte_caja/resumen_corte_actual.php?corte_id=${corteIdActual}`);
        let resumen = null;
        try { resumen = await resumenResp.json(); } catch (_) { resumen = null; }

        // Permitir cierre aunque no haya ventas; sólo detener si la API falla
        if (!resumenResp.ok || !resumen || resumen.success === false) {
            mostrarPendientesCorte(resumen);
            return;
        }

        // Abrir modal de desglose incluso con totales en 0
        mostrarModalDesglose(resumen);
        if (typeof pintarCorteActual === 'function') pintarCorteActual(resumen);
    } catch (error) {
        console.error("Error al obtener resumen:", error);
        alert("Ocurrió un error inesperado al consultar el corte.");
    }
}

function mostrarPendientesCorte(resumen) {
    const ventasActivas = resumen?.detalle?.ventas_activas ?? 0;
    const mesasOcupadas = resumen?.detalle?.mesas_ocupadas ?? 0;
    const vLbl = document.getElementById('lblPendVentas');
    const mLbl = document.getElementById('lblPendMesas');
    if (vLbl) vLbl.textContent = ventasActivas;
    if (mLbl) mLbl.textContent = mesasOcupadas;
    const msg = (resumen && (resumen.mensaje || resumen.error)) || 'No se puede cerrar el corte hasta liberar pendientes.';
    const body = document.querySelector('#modalPendientesCorte .modal-body p');
    if (body) body.textContent = msg;
    showModal('#modalPendientesCorte');
}

function abrirCorteTemporal() {
    fetch('../../api/corte_caja/resumen_corte_temporal.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Error al obtener datos del corte.');
                return;
            }
            const r = data.resultado;
            document.getElementById('corteTemporalDatos').innerHTML = generarHTMLCorte(r);
            if (typeof mostrarCorteTemporalBonito === 'function') mostrarCorteTemporalBonito(data);
            showModal('#modalCorteTemporal');
            document.getElementById('guardarCorteTemporal').onclick = function () {
                guardarCorteTemporal(r);
            };
        });
}

// Consulta y muestra propinas agrupadas por usuario (mesero)
async function abrirPropinasPorUsuario() {
    try {
        const resp = await fetch('../../api/ventas/propinas_por_usuario.php');
        const data = await resp.json();
        if (!data || !data.success) {
            alert((data && data.mensaje) || 'No se pudo consultar propinas');
            return;
        }
        const payload = data.resultado || {};
        let detalle = payload.detalle || [];
        // Mostrar solo usuarios con propina > 0
        detalle = detalle.filter(r => Number(r.total || 0) > 0);
        const tot = payload.totales || {};
        if (!detalle.length) {
            const cont = document.getElementById('propinasUsuariosContenido');
            if (cont) cont.innerHTML = '<div class="text-center text-muted">Sin propinas registradas.</div>';
            showModal('#modalPropinasUsuarios');
            return;
        }

        let html = '<div class="table-responsive">';
        html += '<table class="styled-table">';
        html += '<thead><tr><th>Usuario</th><th>Efectivo</th><th>Transferencia</th><th>Tarjeta</th><th>Total</th></tr></thead><tbody>';
        detalle.forEach(r => {
            html += `<tr>
                        <td>${r.usuario || ''}</td>
                        <td>$${Number(r.propina_efectivo || 0).toFixed(2)}</td>
                        <td>$${Number(r.propina_cheque || 0).toFixed(2)}</td>
                        <td>$${Number(r.propina_tarjeta || 0).toFixed(2)}</td>
                        <td><strong>$${Number(r.total || 0).toFixed(2)}</strong></td>
                     </tr>`;
        });
        html += '</tbody>';
        html += `<tfoot><tr>
                    <th>Total</th>
                    <th>$${Number(tot.efectivo || 0).toFixed(2)}</th>
                    <th>$${Number(tot.cheque || 0).toFixed(2)}</th>
                    <th>$${Number(tot.tarjeta || 0).toFixed(2)}</th>
                    <th>$${Number(tot.total || 0).toFixed(2)}</th>
                 </tr></tfoot>`;
        html += '</table></div>';

        const cont = document.getElementById('propinasUsuariosContenido');
        if (cont) cont.innerHTML = html;
        showModal('#modalPropinasUsuarios');
    } catch (e) {
        console.error(e);
        alert('Error de red al consultar');
    }
}

// Consulta y muestra los totales de envios (producto 9001) agrupados por repartidor
async function abrirEnviosRepartidor() {
    try {
        const corte = (typeof corteIdActual !== 'undefined' && corteIdActual) ? corteIdActual : (window.corteId || null);
        const url = '../../api/ventas/envios_por_repartidor.php' + (corte ? `?corte_id=${encodeURIComponent(corte)}` : '');
        const resp = await fetch(url, { cache: 'no-store' });
        const data = await resp.json();
        if (!data || !data.success) {
            alert((data && data.mensaje) || 'No se pudo consultar los envios');
            return;
        }
        const payload = data.resultado || {};
        const detalle = Array.isArray(payload.repartidores) ? payload.repartidores : [];
        const cont = document.getElementById('enviosRepartidorContenido');
        if (!cont) return;

        if (!detalle.length) {
            cont.innerHTML = '<div class="text-center text-muted">Sin envios registrados.</div>';
            showModal('#modalEnviosRepartidor');
            return;
        }

        const totalGeneral = Number(payload.total_envio_general || 0);
        const totalLineas = Number(payload.total_lineas || 0);
        const totalVentas = detalle.reduce((acc, it) => acc + Number(it.ventas || 0), 0);

        let html = '<div class="table-responsive">';
        html += '<table class="styled-table">';
        html += '<thead><tr><th>Repartidor</th><th>Usuario</th><th>Total envios</th><th>Ventas</th></tr></thead><tbody>';
        detalle.forEach(r => {
            html += `<tr>
                        <td>${r.repartidor || ''}</td>
                        <td>${r.usuario || ''}</td>
                        <td>${money(Number(r.total_envio || 0))}</td>
                        
                        <td>${Number(r.ventas || 0)}</td>
                     </tr>`;
        });
        html += '</tbody>';
        html += `<tfoot><tr><th colspan="2">Total</th><th>${money(totalGeneral)}</th><th>${totalVentas}</th></tr></tfoot>`;
        html += '</table></div>';

        if (payload.corte_id || payload.fecha) {
            const label = payload.corte_id ? `Corte ${payload.corte_id}` : `Fecha ${payload.fecha}`;
            html += `<p class="text-end mb-0 mt-2"><small>${label}</small></p>`;
        }

        cont.innerHTML = html;
        showModal('#modalEnviosRepartidor');
    } catch (e) {
        console.error(e);
        alert('Error de red al consultar envios');
    }
}

function generarHTMLCorte(r) {
    let html = '<table class="table"><tbody>';
    for (const [key, val] of Object.entries(r)) {
        let displayVal = val;
        if (key === 'efectivo' && val && typeof val === 'object') {
            const productos = val.productos ?? 0;
            const propina = val.propina ?? 0;
            const total = val.total ?? 0;
            displayVal = `Efectivo - Productos: ${productos}, Total: ${total}`;
        } else if (key === 'total_meseros' && Array.isArray(val)) {
            displayVal = val.map(m => `${m.nombre}: ${m.total ?? m.total_neto}`).join('<br>');
        } else if (key === 'total_repartidor' && Array.isArray(val)) {
            displayVal = val.map(rp => `${rp.nombre}: ${rp.total ?? rp.total_neto}`).join('<br>');
        }
        html += `<tr><td>${key}</td><td>${displayVal}</td></tr>`;
    }
    html += '</tbody></table>';
    return html;
}

function guardarCorteTemporal(datos) {
    const obs = document.getElementById('observacionesCorteTemp').value;
    const payload = {
        corte_id: datos.corte_id,
        usuario_id: usuarioId,
        total: datos.totalFinal,
        observaciones: obs,
        datos_json: JSON.stringify(datos)
    };

    fetch('../../api/corte_caja/guardar_corte_temporal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.success) {
                alert('Corte temporal guardado.');
                imprimirCorteTemporal(datos);
                hideModal('#modalCorteTemporal');
            } else {
                alert('Error al guardar corte temporal.');
            }
        });
}

function imprimirCorteTemporal(datos) {
    window.open(urlConImpresora('../../api/corte_caja/imprime_corte_temp.php?datos=' + JSON.stringify(datos)));

    // const win = window.open('', '_blank', 'width=600,height=800');
    // if (!win) {
    //     console.error('No fue posible abrir la ventana de impresión');
    //     return;
    // }
    // win.document.write('<html><head><title>Corte Temporal</title>');
    // win.document.write('<style>table{border-collapse:collapse;width:100%;}td,th{border:1px solid #000;padding:4px;font-family:monospace;font-size:12px;}</style>');
    // win.document.write('</head><body>');
    // win.document.write('<h2>Corte Temporal</h2>');
    // win.document.write('<table><tbody>');
    // for (const k in datos) {
    //     const v = datos[k];
    //     if (typeof v === 'object') {
    //         win.document.write(`<tr><th colspan="2">${k}</th></tr>`);
    //         for (const k2 in v) {
    //             const v2 = typeof v[k2] === 'object' ? JSON.stringify(v[k2]) : v[k2];
    //             win.document.write(`<tr><td>${k2}</td><td>${v2}</td></tr>`);
    //         }
    //     } else {
    //         win.document.write(`<tr><td>${k}</td><td>${v}</td></tr>`);
    //     }
    // }
    // win.document.write('</tbody></table>');
    // win.document.write('</body></html>');
    // win.document.close();
    // win.print();
}

function mostrarModalDesglose(dataApi) {
    const r = dataApi?.resultado || {};
    const metodosPago = ['efectivo', 'boucher', 'cheque', 'tarjeta', 'transferencia'];

    // Usa los totales del API si vienen; si no, calcula con metodosPago
    const totalProductos = Number.parseFloat(r.total_productos) ||
        metodosPago.reduce((acc, m) => acc + (Number.parseFloat(r[m]?.productos) || 0), 0);

    const totalPropinas = Number.parseFloat(r.total_propinas) ||
        metodosPago.reduce((acc, m) => acc + (Number.parseFloat(r[m]?.propina) || 0), 0);

    const totalBrutoTickets = Number.parseFloat(r.total_bruto ?? 0);
    const totalDescuentos   = Number.parseFloat(r.total_descuentos ?? 0);
    const totalEsperadoNew  = Number.parseFloat(r.total_esperado ?? (totalBrutoTickets - totalDescuentos));
    const totalEsperado = (isNaN(totalEsperadoNew) || totalEsperadoNew === 0)
        ? (Number.parseFloat(r.totalEsperado) || (totalProductos + totalPropinas))
        : totalEsperadoNew;
    const fondoInicial = Number.parseFloat(r.fondo) || 0;
    const totalDepositos = Number.parseFloat(r.total_depositos ?? 0) || 0;
    const totalRetiros = Number.parseFloat(r.total_retiros ?? 0) || 0;
    const totalEsperadoEfectivo = Number.parseFloat(r.totalEsperadoEfectivo ?? r.esperado_efectivo ?? 0);
    const totalFinalEfectivo = Number.parseFloat(r.totalFinalEfectivo ?? r.totalFinal ?? 0) || 0;
    const totalIngresado = totalFinalEfectivo  + totalDepositos - totalRetiros;
    const cuentasCanceladas = Number.parseInt(
        r.cuentas_canceladas ?? r.cuentas_por_estatus?.cerradas?.cantidad ?? 0,
        10
    ) || 0;
    const totalCanceladas   = Number.parseFloat(
        r.total_cuentas_canceladas ?? r.cuentas_por_estatus?.cerradas?.total ?? 0
    ) || 0;

    const modal = document.getElementById('modalDesglose');
    const body = modal.querySelector('.modal-body');
    let html = '<div class="bg-dark text-white p-3 border">';
    html += '<h3>Detalle de dinero ingresado</h3>';
    // Datos de cabecera del corte (si existen)
    if (r.fecha_inicio) { html += `<p>Fecha inicio: ${r.fecha_inicio}</p>`; }
    if (r.folio_inicio != null) { html += `<p>Folio inicio: ${r.folio_inicio}</p>`; }
    if (r.folio_fin != null) { html += `<p>Folio fin: ${r.folio_fin}</p>`; }
    if (r.total_folios != null) { html += `<p>Total folios: ${r.total_folios}</p>`; }

    // Totales incluyendo descuentos por tickets
    if (!isNaN(totalBrutoTickets) && (totalBrutoTickets > 0 || totalDescuentos >= 0)) {
        html += `<p>Total sin descuentos: $${(totalBrutoTickets || 0).toFixed(2)}</p>`;
        html += `<p>Descuentos acumulados: $<span id="lblCorteActualDescuentos">${(totalDescuentos || 0).toFixed(2)}</span></p>`;
    }
    html += `<p><strong>Total esperado:</strong> $${totalEsperado.toFixed(2)}</p>`;
    html += '<p>Fondo inicial: $<strong id="lblFondo"></strong></p>';
    html += '<p>Depósitos: $<strong id="lblTotalDepositos"></strong></p>';
    html += '<p>Retiros: $<strong id="lblTotalRetiros"></strong></p>';
    html += `<p>Total propinas: $${totalPropinas.toFixed(2)}</p>`;
    html += `<p>Total ingresado: $${totalIngresado.toFixed(2)}</p>`;
    html += `<p>Cuentas canceladas: <strong>${cuentasCanceladas}</strong> (monto: $${totalCanceladas.toFixed(2)})</p>`;
   // html += `<p>Total productos S/descuento: $${totalProductos.toFixed(2)}</p>`;
    //Shtml += `<p>Total productos C/descuento: $${totalEsperado.toFixed(2)}</p>`;
    html += '<p>Totales por tipo de pago:</p><ul>';
    metodosPago.forEach(tipo => {
        const p = r[tipo] || {};
        html += `<li>${tipo}: $${(Number.parseFloat(p.total) || 0).toFixed(2)}</li>`;
    });
    html += '</ul>';
    // Esperado por tipo de pago (post-descuentos) si viene del API
    const esperadoEfec   = Number.parseFloat(r.esperado_efectivo || 0);
    const esperadoBouch  = Number.parseFloat(r.esperado_boucher  || 0);
    const esperadoCheque = Number.parseFloat(r.esperado_cheque   || 0);
    if (esperadoEfec || esperadoBouch || esperadoCheque) {
        html += '<p>Esperado post-descuentos por tipo de pago:</p><ul>';
        html += `<li>efectivo: $${esperadoEfec.toFixed(2)}</li>`;
        html += `<li>boucher: $${esperadoBouch.toFixed(2)}</li>`;
        html += `<li>Transferencia: $${esperadoCheque.toFixed(2)}</li>`;
        html += '</ul>';
    }  
   
    
    html += '<p>Propinas por tipo de pago:</p><ul>';
    html += `<li>Efectivo: $${(Number.parseFloat(r.total_propina_efectivo) || 0).toFixed(2)}</li>`;
    html += `<li>Transferencia: $${(Number.parseFloat(r.total_propina_cheque) || 0).toFixed(2)}</li>`;
    html += `<li>Tarjeta: $${(Number.parseFloat(r.total_propina_tarjeta) || 0).toFixed(2)}</li></ul>`;

    var totalPromos = Number(r.total_descuento_promos ?? 0);
     if (totalPromos > 0) {
        html += '<p>Promociones:</p><ul>';
        html += `<li>Descuento Total Promociones: $${totalPromos.toFixed(2)}</li>`;
        html += `<li>Total ingresado Post-Promociones: $${totalEsperado.toFixed(2)}</li>`;        
        html += '</ul>';
    }

    // metodosPago.forEach(tipo => {
    //     const p = r[tipo] || {};
    //     html += `<li>${tipo}: $${(Number.parseFloat(p.propina) || 0).toFixed(2)}</li></ul>`;
    // });



    // Listado de ventas por mesero (si existe)
    const listaMeseros = Array.isArray(r.total_meseros)
        ? r.total_meseros
        : (Array.isArray(r.totales_mesero) ? r.totales_mesero : []);
    if (listaMeseros.length) {
        html += '<h4>Ventas por mesero</h4><ul>';
        listaMeseros.forEach(m => {
            const nombre = (m?.nombre ?? '').toString();
            const total = Number.parseFloat(m?.total ?? m?.total_neto) || 0;
            html += `<li>${nombre}: $${total.toFixed(2)}</li>`;
        });
        html += '</ul>';
    }

    // Totales por repartidor + mostrador/rapido
    {
        const totalRapido = Number.parseFloat(r.total_rapido || 0);
        const listaRep = Array.isArray(r.total_repartidor)
            ? r.total_repartidor
            : (Array.isArray(r.totales_repartidor) ? r.totales_repartidor : []);
        if ((listaRep && listaRep.length) || totalRapido) {
            html += '<h4>Total por repartidor</h4><ul>';
            if (totalRapido) {
                html += `<li>Mostrador/rapido: $${totalRapido.toFixed(2)}</li>`;
            }
            (listaRep || []).forEach(x => {
                const nombre = (x?.nombre ?? '').toString();
                const total = Number.parseFloat(x?.total ?? x?.total_neto) || 0;
                html += `<li>${nombre}: $${total.toFixed(2)}</li>`;
            });
            html += '</ul>';
        }
    }




    html += '<div id="camposDesglose"></div>';
    html += '<p>Efectivo contado: $<span id="totalEfectivo">0.00</span> | Dif.: $<span id="difIngresado">0.00</span></p>';
    html += '<button class="btn custom-btn" id="guardarDesglose">Guardar</button> <button class="btn custom-btn" id="cancelarDesglose" data-dismiss="modal">Cancelar</button>';
    html += '</div>';
    body.innerHTML = html;
    showModal('#modalDesglose');

    document.getElementById('lblFondo').textContent = fondoInicial.toFixed(2);
    document.getElementById('lblTotalDepositos').textContent = totalDepositos.toFixed(2);
    document.getElementById('lblTotalRetiros').textContent = totalRetiros.toFixed(2);

    if (!Array.isArray(catalogoDenominaciones) || !catalogoDenominaciones.length) {
        console.error('Error al cargar denominaciones');
        modal.querySelector('#cancelarDesglose').addEventListener('click', () => {
            hideModal('#modalDesglose');
            habilitarCobro();
        });
        return;
    }

    const cont = modal.querySelector('#camposDesglose');
    const frag = document.createDocumentFragment();

    catalogoDenominaciones.forEach(d => {
        const did = Number(d.id);
        if (did === 12 || did === 13) {
            return;
        }
        const div = document.createElement('div');
        div.className = 'grupo-pago d-flex align-items-center gap-2';
        div.dataset.tipo = 'efectivo';
        div.innerHTML = `
            <label class="mb-0" style="min-width:160px;">${d.descripcion}</label>
            <input type="number" inputmode="numeric" step="1" class="cantidad form-control form-control-sm" 
                   style="max-width:180px; text-align:center;" 
                   data-id="${d.id}" data-valor="${d.valor}" data-tipo="efectivo" min="0" value="0">
            <span class="subtotal ms-2">$0.00</span>`;
        frag.appendChild(div);
    });

    cont.appendChild(frag);

        function calcular(ev) {
        const efectivoEsperado = (Number.parseFloat(r.totalEsperadoEfectivo ?? r.esperado_efectivo ?? r.totalEsperado ?? r.total_esperado ?? 0) || 0)
            + fondoInicial + totalDepositos - totalRetiros;
        if (ev && ev.target && ev.target.classList.contains('cantidad')) {
            const changed = ev.target;
            const valDen = parseFloat(changed.dataset.valor) || 0;
            let sumaOtros = 0;
            cont.querySelectorAll('.grupo-pago').forEach(gr => {
                const inp = gr.querySelector('.cantidad');
                if (inp !== changed) {
                    const v = parseFloat(inp.dataset.valor) || 0;
                    const c = parseFloat(inp.value) || 0;
                    sumaOtros += v * c;
                }
            });
            const maxRestante = Math.max(0, efectivoEsperado - sumaOtros);
            let cantidad = Math.floor((parseFloat(changed.value) || 0));
            const maxCant = valDen > 0 ? Math.floor(maxRestante / valDen) : 0;
            if (valDen > 0 && cantidad > maxCant) {
                cantidad = maxCant;
                changed.value = String(cantidad);
            }
        }
        let totalEfectivo = 0;
        cont.querySelectorAll('.grupo-pago').forEach(gr => {
            const inp = gr.querySelector('.cantidad');
            const valor = parseFloat(inp.dataset.valor) || 0;
            const cantidad = parseFloat(inp.value) || 0;
            const subtotal = valor * cantidad;
            gr.querySelector('.subtotal').textContent = `${subtotal.toFixed(2)}`;
            totalEfectivo += subtotal;
        });
        document.getElementById('totalEfectivo').textContent = totalEfectivo.toFixed(2);
        let dif = totalEfectivo - efectivoEsperado;
        document.getElementById('difIngresado').textContent = dif.toFixed(2);
    }

    modal.querySelectorAll('.cantidad').forEach(inp => inp.addEventListener('input', calcular));
    calcular();

    modal.querySelector('#cancelarDesglose').addEventListener('click', () => {
        hideModal('#modalDesglose');
        habilitarCobro();
    });

    modal.querySelector('#guardarDesglose').addEventListener('click', async () => {
        calcular();
        const difV = parseFloat(document.getElementById('difIngresado').textContent) || 0;
        if (difV < 0) { alert('La diferencia no puede ser negativa. Verifica el efectivo contado.'); return; }
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

        // Agregar montos de boucher y cheque automáticamente para registro
        if (r.boucher) {
            detalle.push({
                denominacion_id: null,
                cantidad: r.boucher.total,
                tipo_pago: 'boucher',
                denominacion: r.boucher.total
            });
        }
        if (r.cheque) {
            detalle.push({
                denominacion_id: null,
                cantidad: r.cheque.total,
                tipo_pago: 'cheque',
                denominacion: r.cheque.total
            });
        }

        if (!detalle.length) {
            alert('Ingresa al menos una cantidad mayor a cero');
            return;
        }
        try {
            const resp = await fetch('../../api/corte_caja/guardar_desglose.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ corte_id: corteIdActual, detalle })
            });
            const data = await resp.json();
            if (data.success) {
                // Mostrar vista previa del corte

                hideModal('#modalDesglose');
                await finalizarCorte();
                showCortePreview({ ...r, desglose: detalle });
            } else {
                alert(data.mensaje || 'Error al guardar desglose');
            }
        } catch (err) {
            console.error(err);
            alert('Error al guardar desglose');
        }
    });
}

async function finalizarCorte() {
    try {
        const corteIdPrevio = corteIdActual || window.corteId || null;
        const resp = await fetch('../../api/corte_caja/cerrar_corte.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ corte_id: corteIdActual, usuario_id: usuarioId, observaciones: '' })
        });
        const data = await resp.json();
        if (data.success) {
            corteIdActual = null;
            if (false) {
            // Mostrar modal de corte enviado con botón continuar
            try {
              if (typeof showModal === 'function') {
                showModal('#modalCorteEnviado');
              } else if (window.$) {
                $('#modalCorteEnviado').modal('show');
              } else {
                alert('Corte enviado, que tengas un buen día.');
              }
              const btn = document.getElementById('btnContinuarCorte');
              if (btn) {
                btn.onclick = function(){ window.location.href = 'ventas.php'; };
              }

            } catch { console.warn; }
            }
            await verificarCorte();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cerrar corte');
    }
}

async function cargarRepartidores() {
    try {
        const resp = await fetch('../../api/repartidores/listar_repartidores.php');
        const data = await resp.json();
        if (data.success) {
            repartidores = data.resultado;
            const select = document.getElementById('repartidor_id');
            select.innerHTML = '<option value="">--Selecciona--</option>';
            repartidores.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.id;
                opt.textContent = r.nombre;
                select.appendChild(opt);
            });
            aplicarEnvioSiCorresponde();
            toggleSeccionClienteDomicilio();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar repartidores');
    }
}

async function cargarColoniasCatalogo() {
    if (coloniasData.length) return coloniasData;
    try {
        const resp = await fetch('../../api/colonias/listar.php');
        const data = await resp.json();
        if (data.success) {
            coloniasData = data.resultado || [];
            const busc = document.getElementById('buscarColoniaCliente');
            if (busc && busc.value && busc.value.trim()) {
                mostrarSugerenciasColonias(busc.value);
            }
        }
    } catch (err) {
        console.error('No se pudieron cargar las colonias', err);
    }
    return coloniasData;
}

async function cargarClientesDomicilio() {
    try {
        const resp = await fetch('../../api/clientes/listar.php');
        const data = await resp.json();
        if (data.success) {
            clientesDomicilio = data.resultado || [];
            aplicarFiltroClientesDomicilio();
        }
    } catch (err) {
        console.error('No se pudieron cargar los clientes', err);
    }
}

function pintarColoniasSelect(select) {
    if (!select) return;
    select.innerHTML = '<option value="">--Selecciona--</option>';
    coloniasData.forEach(col => {
        const opt = document.createElement('option');
        opt.value = col.id;
        opt.textContent = col.colonia;
        opt.dataset.distancia = col.dist_km_la_forestal ?? '';
        opt.dataset.costoFore = col.costo_fore ?? '';
        select.appendChild(opt);
    });
}

function formatearEtiquetaColonia(col) {
    if (!col) return '';
    const dist = (col.dist_km_la_forestal !== undefined && col.dist_km_la_forestal !== null && col.dist_km_la_forestal !== '')
        ? ` - ${col.dist_km_la_forestal} km` : '';
    const costo = (col.costo_fore !== undefined && col.costo_fore !== null && col.costo_fore !== '')
        ? ` - $${Number(col.costo_fore).toFixed(2)}` : '';
    return `${col.colonia || ''}${dist}${costo}`;
}

function mostrarSugerenciasColonias(termino = '', lista = coloniasData) {
    const ul = document.getElementById('listaColoniasCliente');
    if (!ul) return;
    ul.innerHTML = '';
    const needle = normalizarClienteTexto(termino.trim());
    if (!needle) {
        ul.style.display = 'none';
        return;
    }
    const coincidencias = (lista || []).filter(col => normalizarClienteTexto(col.colonia || '').includes(needle)).slice(0, 50);
    coincidencias.forEach(col => {
        const li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action';
        li.textContent = formatearEtiquetaColonia(col);
        li.addEventListener('click', () => seleccionarColoniaDesdeLista(col));
        ul.appendChild(li);
    });
    ul.style.display = coincidencias.length ? 'block' : 'none';
}

function seleccionarColoniaDesdeLista(col) {
    const select = document.getElementById('clienteColoniaSelect');
    const busc = document.getElementById('buscarColoniaCliente');
    const ul = document.getElementById('listaColoniasCliente');
    if (select && col) {
        let opt = select.querySelector(`option[value="${col.id}"]`);
        if (!opt) {
            opt = document.createElement('option');
            opt.value = col.id;
            opt.textContent = col.colonia || '';
            opt.dataset.distancia = col.dist_km_la_forestal ?? '';
            opt.dataset.costoFore = col.costo_fore ?? '';
            select.appendChild(opt);
        }
        select.value = col.id;
        onSeleccionColoniaManual();
    }
    if (busc) busc.value = col ? (col.colonia || '') : '';
    if (ul) ul.style.display = 'none';
}

function sincronizarBuscadorColonia() {
    const select = document.getElementById('clienteColoniaSelect');
    const busc = document.getElementById('buscarColoniaCliente');
    if (!select || !busc) return;
    const opt = select.selectedOptions && select.selectedOptions[0];
    busc.value = opt ? (opt.textContent || '') : '';
}

function formatearEtiquetaCliente(cli) {
    const col = cli.colonia_nombre || cli.colonia_texto || '';
    const tel = cli.telefono ? ` · ${cli.telefono}` : '';
    return `${cli.nombre}${tel}${col ? ' – ' + col : ''}`;
}

function mostrarSugerenciasClientes(termino = '', lista = clientesDomicilio) {
    const listaEl = document.getElementById('listaClientesDomicilio');
    if (!listaEl) return;
    listaEl.innerHTML = '';
    const needle = termino.trim();
    if (!needle) {
        listaEl.style.display = 'none';
        return;
    }
    lista.forEach(cli => {
        const li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action';
        li.textContent = formatearEtiquetaCliente(cli);
        li.addEventListener('click', () => seleccionarCliente(cli));
        listaEl.appendChild(li);
    });
    listaEl.style.display = lista.length ? 'block' : 'none';
}

function filtrarClientesDomicilioPorTexto(termino = '') {
    const needle = termino.trim().toLowerCase();
    if (!needle) return clientesDomicilio;
    return clientesDomicilio.filter(cli => {
        const campos = [
            cli.nombre,
            cli.telefono,
            cli.colonia_nombre,
            cli.colonia_texto,
            cli.calle,
            cli.numero_exterior
        ];
        return campos.some(campo => campo && String(campo).toLowerCase().includes(needle));
    });
}

function aplicarFiltroClientesDomicilio() {
    const buscador = document.getElementById('buscarClienteDomicilio');
    const termino = buscador ? buscador.value : '';
    const hiddenId = document.getElementById('cliente_id');
    const listaFiltrada = filtrarClientesDomicilioPorTexto(termino);
    mostrarSugerenciasClientes(termino, listaFiltrada);
    const seleccionadoId = hiddenId ? hiddenId.value : null;
    if (seleccionadoId) {
        const cli = listaFiltrada.find(c => String(c.id) === String(seleccionadoId))
            || clientesDomicilio.find(c => String(c.id) === String(seleccionadoId));
        actualizarResumenCliente(cli || null);
    }
}

function seleccionarCliente(cliente) {
    const hiddenId = document.getElementById('cliente_id');
    const buscador = document.getElementById('buscarClienteDomicilio');
    const listaEl = document.getElementById('listaClientesDomicilio');
    if (!hiddenId || !buscador) return;

    if (!cliente) {
        hiddenId.value = '';
        buscador.value = '';
        actualizarResumenCliente(null);
        if (listaEl) listaEl.style.display = 'none';
        return;
    }

    hiddenId.value = cliente.id;
    buscador.value = formatearEtiquetaCliente(cliente);
    actualizarResumenCliente(cliente);
    if (listaEl) listaEl.style.display = 'none';
}

function actualizarResumenCliente(cliente) {
    const resumen = document.getElementById('resumenCliente');
    const tel = document.getElementById('clienteTelefono');
    const dir = document.getElementById('clienteDireccion');
    const col = document.getElementById('clienteColonia');
    const dist = document.getElementById('clienteDistancia');
    const costoInput = document.getElementById('costoForeInput');
    const colSelectWrap = document.getElementById('clienteColoniaSelectWrap');
    const colSelect = document.getElementById('clienteColoniaSelect');
    const clienteIdActual = cliente ? Number(cliente.id) : null;
    if (clienteIdActual !== clienteSeleccionadoIdRef) {
        clienteColoniaOriginalId = cliente && cliente.colonia_id ? Number(cliente.colonia_id) : null;
        clienteSeleccionadoIdRef = clienteIdActual;
    }
    clienteSeleccionado = cliente || null;

    if (!resumen) return;

    if (!cliente) {
        resumen.style.display = 'none';
        if (costoInput) costoInput.value = '';
        if (colSelectWrap) colSelectWrap.style.display = 'none';
        if (colSelect) colSelect.value = '';
        // Si no hay cliente seleccionado, usa el costo por defecto del concepto "ENVÍO – Repartidor casa".
        actualizarPrecioEnvio(window.ENVIO_CASA_DEFAULT_PRECIO || 30);
        return;
    }

    if (tel) tel.textContent = cliente.telefono || '-';
    const direccion = [cliente.calle, cliente.numero_exterior].filter(Boolean).join(' ');
    if (dir) dir.textContent = direccion || '-';
    if (col) col.textContent = cliente.colonia_nombre || cliente.colonia_texto || '-';
    const distanciaTxt = cliente.dist_km_la_forestal !== null && cliente.dist_km_la_forestal !== undefined
        ? `${cliente.dist_km_la_forestal} km`
        : 'Sin dato';
    if (dist) dist.textContent = distanciaTxt;
    if (costoInput) costoInput.value = cliente.costo_fore !== null && cliente.costo_fore !== undefined ? cliente.costo_fore : '';

    resumen.style.display = 'block';
    if (cliente.costo_fore !== null && cliente.costo_fore !== undefined) {
        actualizarPrecioEnvio(cliente.costo_fore);
    }
    prepararColoniaSelect(cliente);
    if (colSelect && colSelect.value) {
        onSeleccionColoniaManual();
    }
}

function actualizarPrecioEnvio(monto) {
    const precioInput = document.getElementById('envioPrecio');
    if (precioInput && monto !== null && monto !== undefined && monto !== '') {
        precioInput.value = monto;
        window.recalcularTotalesUI();
    }
}

function prepararColoniaSelect(cliente) {
    const wrap = document.getElementById('clienteColoniaSelectWrap');
    const select = document.getElementById('clienteColoniaSelect');
    const busc = document.getElementById('buscarColoniaCliente');
    const ul = document.getElementById('listaColoniasCliente');
    if (!wrap || !select) return;
    if (!cliente || (cliente.colonia_id && Number(cliente.colonia_id) > 0)) {
        wrap.style.display = 'none';
        select.value = '';
        if (busc) busc.value = '';
        if (ul) ul.style.display = 'none';
        return;
    }
    wrap.style.display = 'block';
    const rellenar = () => {
        pintarColoniasSelect(select);
        const nombreBuscado = (cliente.colonia_nombre || cliente.colonia_texto || '').trim().toLowerCase();
        if (cliente.colonia_id) {
            select.value = cliente.colonia_id;
        }
        if (!select.value && nombreBuscado) {
            const opt = Array.from(select.options).find(o => (o.textContent || '').trim().toLowerCase() === nombreBuscado);
            if (opt) select.value = opt.value;
        }
        if (select.value) onSeleccionColoniaManual();
        sincronizarBuscadorColonia();
        if (busc && busc.value.trim()) {
            mostrarSugerenciasColonias(busc.value);
        }
    };
    if (coloniasData.length) {
        rellenar();
    } else {
        cargarColoniasCatalogo().then(rellenar).catch(() => {});
    }
}

function onSeleccionColoniaManual() {
    const select = document.getElementById('clienteColoniaSelect');
    const spanCol = document.getElementById('clienteColonia');
    const spanDist = document.getElementById('clienteDistancia');
    const costoInput = document.getElementById('costoForeInput');
    if (!select || !select.value) return;
    const opt = select.selectedOptions && select.selectedOptions[0];
    if (!opt) return;
    const nombre = opt.textContent || '-';
    if (spanCol) spanCol.textContent = nombre;
    const dist = opt.dataset?.distancia;
    if (spanDist && dist !== undefined && dist !== '') {
        spanDist.textContent = `${dist} km`;
    }
    const costo = opt.dataset?.costoFore;
    if (costoInput) costoInput.value = (costo !== undefined && costo !== '') ? costo : '';
    if (costo !== undefined && costo !== '') {
        const num = Number(costo);
        if (!Number.isNaN(num)) actualizarPrecioEnvio(num);
    }
    sincronizarBuscadorColonia();
    const ul = document.getElementById('listaColoniasCliente');
    if (ul) ul.style.display = 'none';
    if (clienteSeleccionado) {
        clienteSeleccionado.colonia_id = parseInt(select.value, 10) || null;
        clienteSeleccionado.colonia_nombre = nombre;
        if (costo !== undefined && costo !== '') {
            const num = Number(costo);
            if (!Number.isNaN(num)) clienteSeleccionado.costo_fore = num;
        }
    }
}

function toggleSeccionClienteDomicilio() {
    const seccion = document.getElementById('seccionClienteDomicilio');
    if (!seccion) return;
    if (esDomicilioConRepartidorCasa()) {
        seccion.style.display = 'block';
        if (!clientesDomicilio.length) {
            cargarClientesDomicilio();
        }
    } else {
        seccion.style.display = 'none';
        const sel = document.getElementById('cliente_id');
        const buscador = document.getElementById('buscarClienteDomicilio');
        if (sel) sel.value = '';
        if (buscador) buscador.value = '';
        const listaEl = document.getElementById('listaClientesDomicilio');
        if (listaEl) listaEl.style.display = 'none';
        actualizarResumenCliente(null);
    }
}

// Carga el catálogo de meseros desde el backend de mesas
async function cargarMeseros() {
    try {
        const resp = await fetch('../../api/mesas/meseros.php');
        const data = await resp.json();
        const select = document.getElementById('usuario_id');
        if (!select) return;
        select.innerHTML = '<option value="">--Selecciona--</option>';

        if (data && data.success) {
            (data.resultado || []).forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.nombre;
                select.appendChild(opt);
            });
        } else {
            console.warn(data?.mensaje || 'No se pudieron cargar meseros.');
        }
    } catch (e) {
        console.error('Error al cargar meseros:', e);
    }
}

async function cargarMesas(preserveMesaId) {
    try {
        const resp = await fetch('../../api/mesas/mesas.php');
        const data = await resp.json();
        if (data.success) {
            // Solo mesas disponibles (estado = 'libre')
            mesas = (data.resultado || []).filter(m => String(m.estado || '').toLowerCase() === 'libre');
            const select = document.getElementById('mesa_id');
            const prev = (typeof preserveMesaId !== 'undefined' && preserveMesaId !== null)
                ? String(preserveMesaId)
                : (select ? String(select.value || '') : '');
            select.innerHTML = '<option value="">Seleccione</option>';
            mesas.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = `${m.nombre}`;
                select.appendChild(opt);
            });
            // Restaurar selección si aplica
            if (prev) {
                select.value = prev;
            }
        } else {
            alert(data.mensaje || 'Error al cargar mesas');
        }
        await actualizarEstadoBotonCerrarCorte();
    } catch (err) {
        console.error(err);
        alert('Error de red al cargar mesas');
    }
}

function setLabelUsuario(texto) {
    const lbl = document.querySelector('label[for="usuario_id"]');
    if (lbl) lbl.textContent = texto;
}

async function cargarUsuariosPorRol() {
    try {
        const resp = await fetch('../../api/usuarios/listar_repartidor.php');
        const data = await resp.json();
        const select = document.getElementById('usuario_id');
        if (!select) return;
        select.innerHTML = '<option value="">--Selecciona--</option>';

        if (data && data.success) {
            const lista = data.resultado || [];
            lista.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.nombre;
                select.appendChild(opt);
            });
            // Si la venta es domicilio con repartidor casa, preseleccionar el primer usuario disponible
            if (esDomicilioConRepartidorCasa() && select.options.length > 1 && !select.value) {
                select.selectedIndex = 1; // primer usuario
            }
        } else {
            console.warn(data?.mensaje || 'No se pudieron cargar meseros.');
        }
    } catch (e) {
        console.error('Error al cargar meseros:', e);
    }
}

function esRepartidorCasaSeleccionado() {
    const sel = document.getElementById('repartidor_id');
    if (!sel || sel.selectedIndex < 0) return false;
    const txt = sel.options[sel.selectedIndex].textContent || '';
    return txt.trim().toLowerCase() === 'repartidor casa';
}

// Pintar valor de descuentos en corte actual
function pintarCorteActual(data) {
    if (!data || !data.resultado) return;
    const r = data.resultado;
    const descuentos = Number(r.total_descuentos ?? 0);
    const elDesc = document.getElementById('lblCorteActualDescuentos');
    if (elDesc) elDesc.textContent = n2(descuentos);
}

function pintarTablaSimple(tbodySel, rows, cols) {
    const tb = document.querySelector(tbodySel);
    if (!tb) return;
    tb.innerHTML = '';
    (rows || []).forEach(row => {
        const tr = document.createElement('tr');
        cols.forEach(c => {
            const td = document.createElement('td');
            td.textContent = c.format ? c.format(row[c.key], row) : (row[c.key] ?? '');
            tr.appendChild(td);
        });
        tb.appendChild(tr);
    });
}

// Muestra en modal la información del cliente asociado a la venta (envío)
function mostrarClienteEnvio(ventaId) {
    const v = ventasData[ventaId];
    const clienteId = v ? (v.cliente_id || v.clienteId) : null;
    if (!v || !clienteId) {
        alert('Venta sin cliente asignado.');
        return;
    }
    const cli = clientesDomicilio.find(c => String(c.id) === String(clienteId));
    const data = cli || {
        nombre: v.cliente_nombre,
        telefono: v.cliente_telefono,
        calle: v.cliente_calle,
        numero_exterior: v.cliente_numero_exterior,
        colonia_nombre: v.cliente_colonia_nombre,
        colonia_texto: v.cliente_colonia_texto,
        municipio: v.cliente_municipio,
        entre_calle_1: v.cliente_entre_calle_1,
        entre_calle_2: v.cliente_entre_calle_2,
        referencias: v.cliente_referencias,
        dist_km_la_forestal: v.cliente_dist_km_la_forestal,
        costo_fore: v.cliente_costo_fore
    };
    const contenido = document.getElementById('clienteEnvioContenido');
    if (contenido) {
        const direccion = [data.calle, data.numero_exterior].filter(Boolean).join(' ');
        const entre = [data.entre_calle_1, data.entre_calle_2].filter(Boolean).join(' / ');
        const colonia = data.colonia_nombre || data.colonia_texto || '-';
        const dist = (data.dist_km_la_forestal !== null && data.dist_km_la_forestal !== undefined)
            ? `${data.dist_km_la_forestal} km`
            : '-';
        const costo = (data.costo_fore !== null && data.costo_fore !== undefined)
            ? Number(data.costo_fore).toFixed(2)
            : '-';
        contenido.innerHTML = `
            <p><strong>Nombre:</strong> ${data.nombre || '-'}</p>
            <p><strong>Teléfono:</strong> ${data.telefono || '-'}</p>
            <p><strong>Dirección:</strong> ${direccion || '-'}</p>
            <p><strong>Colonia:</strong> ${colonia}</p>
            <p><strong>Municipio:</strong> ${data.municipio || '-'}</p>
            <p><strong>Entre calles:</strong> ${entre || '-'}</p>
            <p><strong>Referencias:</strong> ${data.referencias || '-'}</p>
            <p><strong>Distancia a La Forestal:</strong> ${dist}</p>
            <p><strong>Costo de envío:</strong> ${costo}</p>
        `;
    }
    showModal('#modalClienteEnvio');
}

function mostrarCorteTemporalBonito(data) {
    const cont = document.getElementById('corteTemporalBonito');
    if (!cont) return;
    const pre = document.getElementById('corteTemporalDatos');
    if (pre) {
        pre.style.display = 'none';
        pre.innerHTML = '';
    }
    cont.style.display = '';
    const r = (data && data.resultado) ? data.resultado : {};
    // Normaliza campos del API de resumen temporal para la vista
    if (!r.total_productos && r.productos && typeof r.productos.total !== 'undefined') {
        r.total_productos = r.productos.total;
    }
    if (!r.total_descuento_promos && r.promociones_aplicadas) {
        r.total_descuento_promos = r.promociones_aplicadas.total_descuento || 0;
    }
    const fp = r.formas_pago_resumen || {};
    if (!r.efectivo && fp.efectivo) {
        r.efectivo = { total: fp.efectivo.total_neto ?? fp.efectivo.total_bruto ?? 0 };
    }
    if (!r.tarjeta && fp.tarjeta) {
        r.tarjeta = { total: fp.tarjeta.total_neto ?? fp.tarjeta.total_bruto ?? 0 };
    }
    if (!r.boucher && fp.otros) {
        r.boucher = { total: fp.otros.total_neto ?? fp.otros.total_bruto ?? 0 };
    }
    const metodosPago = ['efectivo', 'boucher', 'cheque', 'tarjeta', 'transferencia'];
    const totalProductos = Number(r.total_productos ?? 0) ||
        metodosPago.reduce((acc, m) => acc + (Number(r[m]?.productos) || 0), 0);
    const totalPropinas = Number(r.total_propinas ?? 0) ||
        metodosPago.reduce((acc, m) => acc + (Number(r[m]?.propina) || 0), 0);

    const totalBruto      = Number.isFinite(Number(r.total_bruto)) ? Number(r.total_bruto) : totalProductos;
    const totalDescuentos = Number(r.total_descuentos ?? 0) - Number(r.total_descuento_promos ?? 0);
    const totalPromos = Number(r.total_descuento_promos ?? 0);
    let totalEsperado     = Number(r.total_esperado ?? (totalBruto - totalDescuentos - totalPromos ));
    if (!Number.isFinite(totalEsperado) || totalEsperado === 0) {
        totalEsperado = Number(r.totalEsperado ?? (totalProductos + totalPropinas) ?? 0);
    }
    
    const totalEsperadoVisible = totalPromos > 0 ? (totalEsperado ) : totalEsperado;

    const fondo = Number(r.fondo ?? 0);
    const totalDepositos = Number(r.total_depositos ?? 0);
    const totalRetiros = Number(r.total_retiros ?? 0);
    const totalFinalEfectivo = Number(r.totalFinalEfectivo ?? r.totalFinal ?? 0) || 0;
    const totalFinalGeneral = Number(r.totalFinalGeneral ?? 0) || 0;
    const totalIngresado = totalFinalEfectivo + totalDepositos - totalRetiros;

    const propEfectivo = Number(r.total_propina_efectivo ?? 0);
    const propTransfer = Number(r.total_propina_cheque ?? 0);
    const propTarjeta  = Number(r.total_propina_tarjeta ?? 0);

    const esperadoPorPago = {
        efectivo: Number(r.esperado_efectivo || 0),
        boucher: Number(r.esperado_boucher || 0),
        cheque: Number(r.esperado_cheque || 0),
        tarjeta: Number(r.esperado_tarjeta || 0),
        transferencia: Number(r.esperado_transferencia || 0)
    };

    const totalesPorPago = {};
    metodosPago.forEach(tipo => {
        totalesPorPago[tipo] = Number(r[tipo]?.total ?? 0);
    });

    setText('#lblTmpCorteId', r.corte_id ?? '');
    setText('#lblTmpFechaInicio', r.fecha_inicio || '-');
    const folioInicio = r.folio_inicio ?? '';
    const folioFin = r.folio_fin ?? '';
    const totalFolios = r.total_folios ?? '';
    const foliosTxt = (folioInicio || folioFin || totalFolios)
        ? `${folioInicio || '-'} - ${folioFin || '-'} (${totalFolios || 0})`
        : '-';
    setText('#lblTmpFolios', foliosTxt);

    setText('#lblTmpTotalBruto', fmtMoneda(totalBruto));
    setText('#lblTmpTotalDescuentos', fmtMoneda(totalDescuentos));
    const promoRow = document.getElementById('promocionesA');
    if (totalPromos > 0) {
        setText('#lblTmpTotalPromociones', fmtMoneda(totalPromos));
        if (promoRow) promoRow.style.display = 'block';
    } else if (promoRow) {
        promoRow.style.display = 'none';
        setText('#lblTmpTotalPromociones', fmtMoneda(0));
    }
    setText('#lblTmpTotalEsperado', fmtMoneda(totalEsperadoVisible));

    setText('#lblTmpFondo', fmtMoneda(fondo));
    setText('#lblTmpDepositos', fmtMoneda(totalDepositos));
    setText('#lblTmpRetiros', fmtMoneda(totalRetiros));
    setText('#lblTmpTotalPropinas', fmtMoneda(totalPropinas));
    setText('#lblTmpTotalFinalEfectivo', fmtMoneda(totalFinalEfectivo));
    setText('#lblTmpTotalFinalGeneral', fmtMoneda(totalFinalGeneral));
    //setText('#lblTmpTotalIngresado', fmtMoneda(totalIngresado));

    setText('#lblTmpTotalPagoEfectivo', fmtMoneda(totalesPorPago.efectivo));
    // setText('#lblTmpTotalPagoBoucher', fmtMoneda(totalesPorPago.boucher));
    setText('#lblTmpTotalPagoCheque', fmtMoneda(esperadoPorPago.cheque));
    setText('#lblTmpTotalPagoTarjeta', fmtMoneda(totalesPorPago.tarjeta));
    // setText('#lblTmpTotalPagoTransfer', fmtMoneda(totalesPorPago.transferencia));

    setText('#lblTmpEsperadoEfectivo', fmtMoneda(esperadoPorPago.efectivo));
    // setText('#lblTmpEsperadoBoucher',  fmtMoneda(esperadoPorPago.boucher));
    setText('#lblTmpEsperadoCheque',   fmtMoneda(esperadoPorPago.cheque));
    setText('#lblTmpEsperadoTarjeta',  fmtMoneda(esperadoPorPago.tarjeta));
    // setText('#lblTmpEsperadoTransfer', fmtMoneda(esperadoPorPago.transferencia));

    setText('#lblTmpPropinaEfectivo', fmtMoneda(propEfectivo));
    setText('#lblTmpPropinaTransfer', fmtMoneda(propTransfer));
    setText('#lblTmpPropinaTarjeta', fmtMoneda(propTarjeta));

    const cuentasActivas = Number(r.cuentas_activas ?? r.cuentas_por_estatus?.abiertas?.cantidad ?? 0);
    const totalActivas   = Number(r.total_cuentas_activas ?? r.cuentas_por_estatus?.abiertas?.total ?? 0);
    const cuentasCanc    = Number(r.cuentas_canceladas ?? r.cuentas_por_estatus?.cerradas?.cantidad ?? 0);
    const totalCanc      = Number(r.total_cuentas_canceladas ?? r.cuentas_por_estatus?.cerradas?.total ?? 0);
    setText('#lblTmpCuentasActivas', cuentasActivas);
    setText('#lblTmpTotalActivas', fmtMoneda(totalActivas));
    setText('#lblTmpCuentasCanceladas', cuentasCanc);
    setText('#lblTmpTotalCanceladas', fmtMoneda(totalCanc));
    const meseros = Array.isArray(r.total_meseros)
        ? r.total_meseros
        : (Array.isArray(r.totales_mesero) ? r.totales_mesero : []);
    pintarTablaSimple('#tblTmpMeseros tbody', meseros, [
        { key: 'nombre' },
        { key: 'total', format: (v, row) => fmtMoneda(v ?? row.total_neto) }
    ]);
    const reps = Array.isArray(r.total_repartidor)
        ? r.total_repartidor.slice()
        : (Array.isArray(r.totales_repartidor) ? r.totales_repartidor.slice() : []);
    const totalRapido = Number(r.total_rapido || 0);
    if (totalRapido) {
        reps.unshift({ nombre: 'Mostrador/rapido', total: totalRapido });
    }
    pintarTablaSimple('#tblTmpRepartidores tbody', reps, [
        { key: 'nombre' },
        { key: 'total', format: (v, row) => fmtMoneda(v ?? row.total_neto) }
    ]);

    // Totales por producto (alimentos/bebidas)
    const prod = r.productos || {};
    setText('#lblTmpProdAlimentos', fmtMoneda(prod.alimentos ?? 0));
    setText('#lblTmpProdBebidas', fmtMoneda(prod.bebidas ?? 0));
    setText('#lblTmpProdTotal', fmtMoneda(prod.total ?? r.total_productos ?? 0));

    // Totales por servicio (comedor, domicilio, rapido)
    const serv = r.por_servicio || {};
    setText('#lblTmpServComedor', fmtMoneda(serv.comedor ?? 0));
    setText('#lblTmpServDomicilio', fmtMoneda(serv.domicilio ?? 0));
    setText('#lblTmpServRapido', fmtMoneda(serv.rapido ?? 0));

    // Totales por plataforma (didi/rappi/uber)
    const plataformas = (r.resumen && typeof r.resumen === 'object')
        ? Object.values(r.resumen)
        : [];
    pintarTablaSimple('#tblTmpPlataformas tbody', plataformas, [
        { key: 'nombre' },
        { key: 'total_bruto', format: v => fmtMoneda(v ?? 0) },
        { key: 'total_descuento', format: v => fmtMoneda(v ?? 0) },
        { key: 'total_neto', format: v => fmtMoneda(v ?? 0) }
    ]);
}

// ====== Helpers de formato para corte ======
function n2(v) {
    const x = Number(v ?? 0);
    return Number.isFinite(x) ? x.toFixed(2) : '0.00';
}
function fmtMoneda(v) {
    try {
        return (Number(v ?? 0)).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
    } catch(e) {
        return '$ ' + n2(v);
    }
}
function setText(sel, val){ const el = document.querySelector(sel); if (el) el.textContent = String(val); }

// ====== Enví­o automático: helpers ======
function esDomicilioConRepartidorCasa() {
    const tipo = (document.getElementById('tipo_entrega')?.value || '').toLowerCase();
    return tipo === 'domicilio' && esRepartidorCasaSeleccionado();
}
// Redefinir para que no cree filas en la tabla; delega al panel
function aplicarEnvioSiCorresponde() {
    if (typeof window.syncEnvioConCarrito === 'function') {
        window.syncEnvioConCarrito();
    }
}

async function actualizarSelectorUsuario() {
    const tipo = (document.getElementById('tipo_entrega')?.value || '').toLowerCase();
    const usuarioSel = document.getElementById('usuario_id');
    const campoMesero = document.getElementById('campoMesero');
    if (!usuarioSel) return;
    if (tipo === 'domicilio' && !esRepartidorCasaSeleccionado()) {
        if (campoMesero) campoMesero.style.display = 'none';
        usuarioSel.disabled = true;
        usuarioSel.value = '';
        if (typeof verificarActivacionProductos === 'function') {
            verificarActivacionProductos();
        }
        return;
    }

    if (tipo === 'domicilio') {
        usuarioSel.disabled = false;
        if (esRepartidorCasaSeleccionado()) {
            setLabelUsuario('Usuario:');
            await cargarUsuariosPorRol();
            // Si sigue vac�o, intenta seleccionar algo por defecto
            if (!usuarioSel.value && usuarioSel.options.length > 1) {
                usuarioSel.selectedIndex = 1;
            }
        } else {
            setLabelUsuario('Mesero:');
            await cargarMeseros();
        }
        if (campoMesero) campoMesero.style.display = 'block';
    } else if (tipo === 'mesa') {
        setLabelUsuario('Mesero:');
        // Cargar mesas disponibles y catálogo de meseros
        await cargarMesas();
        await cargarMeseros();
        usuarioSel.disabled = false;
        if (typeof asignarMeseroPorMesa === 'function') {
            asignarMeseroPorMesa();
        }
        if (campoMesero) campoMesero.style.display = 'block';
    } else { // 'rapido' u otros
        // En venta rapida no se requiere seleccionar mesero
        if (campoMesero) campoMesero.style.display = 'none';
        usuarioSel.disabled = true;
        usuarioSel.value = '';
    }

    if (typeof verificarActivacionProductos === 'function') {
        verificarActivacionProductos();
    }
}

function asignarMeseroPorMesa() {
    const tipo = document.getElementById('tipo_entrega').value;
    const mesaSelect = document.getElementById('mesa_id');
    const meseroSelect = document.getElementById('usuario_id');
    const mesaId = parseInt(mesaSelect.value);
    if (tipo !== 'mesa') {
        return;
    }
    if (isNaN(mesaId)) {
        meseroSelect.value = '';
        meseroSelect.disabled = false;
        return;
    }
    const mesa = mesas.find(m => m.id === mesaId);
    if (!mesa) {
        meseroSelect.value = '';
        meseroSelect.disabled = false;
        return;
    }
    // Mesa libre: permitir seleccionar cualquier mesero del catálogo cargado
    meseroSelect.disabled = false;
}

// Asignar mesero a mesa al seleccionar ambos
async function asignarMeseroSeleccionado() {
    const tipo = (document.getElementById('tipo_entrega')?.value || '').toLowerCase();
    if (tipo !== 'mesa') return;
    const mesaId = parseInt(document.getElementById('mesa_id').value || '0', 10);
    const usuarioSel = document.getElementById('usuario_id');
    const meseroId = parseInt(usuarioSel?.value || '0', 10);
    if (!mesaId || !meseroId) return;
    try {
        const resp = await fetch('../../api/mesas/asignar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mesa_id: mesaId, usuario_id: meseroId })
        });
        const data = await resp.json();
        if (!data.success) {
            alert(data.mensaje || 'No se pudo asignar el mesero a la mesa');
            return;
        }
        // Mantener habilitado para permitir correcciones de asignación si es necesario
        // Refrescar listado de mesas disponibles pero conservando la selección actual
        await cargarMesas(String(mesaId));
    } catch (e) {
        console.error(e);
        alert('Error al asignar mesero');
    }
}

// Inicializa el nuevo selector de productos con buscador
function inicializarBuscadorProducto(select) {
    const cont = select.closest('.selector-producto');
    if (!cont) return;
    const input = cont.querySelector('.buscador-producto');
    const lista = cont.querySelector('.lista-productos');
    if (!input || !lista || input.dataset.autocompleteInitialized) return;
    input.dataset.autocompleteInitialized = 'true';

    input.addEventListener('input', () => {
        const val = normalizarTexto(input.value);
        lista.innerHTML = '';
        if (!val) {
            lista.style.display = 'none';
            return;
        }
        const PID = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
        const coincidencias = catalogo.filter(p => normalizarTexto(p.nombre).includes(val) && parseInt(p.id) !== PID);
        coincidencias.forEach(p => {
            const li = document.createElement('li');
            li.className = 'list-group-item list-group-item-action';
            li.textContent = p.nombre;
            li.addEventListener('click', () => {
                input.value = p.nombre;
                select.value = p.id;
                lista.innerHTML = '';
                lista.style.display = 'none';
                select.dispatchEvent(new Event('change'));
            });
            lista.appendChild(li);
        });
        lista.style.display = coincidencias.length ? 'block' : 'none';
    });

    document.addEventListener('click', e => {
        if (!cont.contains(e.target)) {
            lista.style.display = 'none';
        }
    });
}

async function cargarProductos() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            catalogo = data.resultado;
            window.catalogo = catalogo;
            productos = data.resultado;

            // Inyectar producto de enví­o si no viene del catálogo
            try {
                const pidEnvio = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
                const precioEnvio = Number(window.ENVIO_CASA_DEFAULT_PRECIO || 30);
                const yaExiste = (catalogo || []).some(p => parseInt(p.id) === pidEnvio);
                if (!yaExiste) {
                    const envioProd = { id: pidEnvio, nombre: 'ENVíO â€“ Repartidor casa', precio: precioEnvio, existencia: 99999, activo: 1 };
                    catalogo.push(envioProd);
                    productos.push(envioProd);
                }
            } catch (_) {}
            const selects = document.querySelectorAll('#productos select.producto');
            selects.forEach(select => {
                select.innerHTML = '<option value="">--Selecciona--</option>';
                const PID = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
                catalogo.forEach(p => {
                    if (parseInt(p.id) === PID) return; // ocultar enví­o
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nombre;
                    opt.dataset.precio = p.precio;
                    opt.dataset.existencia = p.existencia;
                    opt.dataset.categoriaId = p.categoria_id || '';
                    select.appendChild(opt);
                });
                actualizarEstiloSelect(select);
                select.addEventListener('change', () => {
                    actualizarPrecio(select);
                    const cantInput = select.closest('tr').querySelector('.cantidad');
                    const exist = select.selectedOptions[0].dataset.existencia;
                    if (exist) {
                        cantInput.max = exist;
                    } else {
                        cantInput.removeAttribute('max');
                    }
                    validarInventario();
                    actualizarEstiloSelect(select);
                    revalidarPromocionesVenta({ silencioso: true });
                });
                inicializarBuscadorProducto(select); // buscar productos por nombre
            });
            document.querySelectorAll('#productos .cantidad').forEach(inp => {
                const select = inp.closest('tr').querySelector('.producto');
                inp.addEventListener('input', () => {
                    manejarCantidad(inp, select);
                    validarInventario();
                    revalidarPromocionesVenta({ silencioso: true });
                });
            });
            validarInventario();
            aplicarEnvioSiCorresponde();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar productos');
    }
}

function actualizarPrecio(select) {
    const row = select.closest('tr');
    const precioInput = row.querySelector('.precio');
    const cantidadInput = row.querySelector('.cantidad');
    const productoId = select.value;
    const producto = productos.find(p => parseInt(p.id) === parseInt(productoId));
    if (producto) {
        const cant = parseInt(cantidadInput.value) || 1;
        precioInput.dataset.unitario = producto.precio;
        precioInput.value = (cant * parseFloat(producto.precio)).toFixed(2);
        if (!cantidadInput.value || parseInt(cantidadInput.value) === 0) {
            cantidadInput.value = 1;
        }
        cantidadInput.max = producto.existencia;
    } else {
        precioInput.value = '';
        delete precioInput.dataset.unitario;
        cantidadInput.removeAttribute('max');
    }
}

function actualizarEstiloSelect(select) {
    const wrap = select.parentElement;
    if (wrap && wrap.classList.contains('sel')) {
        if (select.value) {
            wrap.classList.add('sel-active');
        } else {
            wrap.classList.remove('sel-active');
        }
    }
}

function manejarCantidad(input, select) {
    let val = parseInt(input.value) || 0;
    if (val === 0) {
        const quitar = confirm('Cantidad es 0. Â¿Quitar producto?');
        if (quitar) {
            input.closest('tr').remove();
            return;
        }
        val = 1;
        input.value = 1;
    }
    const max = parseInt(input.max || 0);
    if (max && val > max) {
        alert(`Solo hay ${max} unidades disponibles`);
        val = max;
        input.value = max;
    }
    actualizarPrecio(select || input.closest('tr').querySelector('.producto'));
    validarInventario();
}

function validarInventario() {
    const rows = document.querySelectorAll('#productos tbody tr');
    const totales = {};
    rows.forEach(r => {
        const id = parseInt(r.querySelector('.producto').value);
        const cant = parseInt(r.querySelector('.cantidad').value) || 0;
        if (!isNaN(id)) {
            totales[id] = (totales[id] || 0) + cant;
        }
    });
    let ok = true;
    for (const id in totales) {
        const prod = productos.find(p => parseInt(p.id) === parseInt(id));
        if (prod && totales[id] > parseInt(prod.existencia)) {
            const excedente = totales[id] - parseInt(prod.existencia);
            let restante = excedente;
            rows.forEach(r => {
                const sid = parseInt(r.querySelector('.producto').value);
                if (sid === parseInt(id) && restante > 0) {
                    const inp = r.querySelector('.cantidad');
                    const val = parseInt(inp.value) || 0;
                    const nuevo = Math.max(val - restante, 0);
                    restante -= val - nuevo;
                    inp.value = nuevo;
                    actualizarPrecio(r.querySelector('.producto'));
                }
            });
            alert(`No hay existencia suficiente de ${prod.nombre}`);
            ok = false;
        }
    }
    return ok;
}

function agregarFilaProducto() {
    const tbody = document.querySelector('#productos tbody');
    const base = tbody.querySelector('tr');
    const nueva = base.cloneNode(true);
    nueva.querySelectorAll('input').forEach(inp => {
        inp.value = '';
        if (inp.classList.contains('precio')) delete inp.dataset.unitario;
        if (inp.classList.contains('buscador-producto')) delete inp.dataset.autocompleteInitialized;
    });
    const lista = nueva.querySelector('.lista-productos');
    if (lista) {
        lista.innerHTML = '';
        lista.style.display = 'none';
    }
    tbody.appendChild(nueva);
    const select = nueva.querySelector('.producto');
    select.innerHTML = '<option value="">--Selecciona--</option>';
    select.removeAttribute('id');
    if (select.parentElement) {
        select.parentElement.classList.remove('sel-active');
    }
    const PID = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
    catalogo.forEach(p => {
        if (parseInt(p.id) === PID) return; // ocultar enví­o
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nombre;
        opt.dataset.precio = p.precio;
        opt.dataset.existencia = p.existencia;
        opt.dataset.categoriaId = p.categoria_id || '';
        select.appendChild(opt);
    });
    actualizarEstiloSelect(select);
    select.addEventListener('change', () => {
        actualizarPrecio(select);
        const cantInput = select.closest('tr').querySelector('.cantidad');
        const exist = select.selectedOptions[0].dataset.existencia;
        if (exist) {
            cantInput.max = exist;
        } else {
            cantInput.removeAttribute('max');
        }
        validarInventario();
        actualizarEstiloSelect(select);
        revalidarPromocionesVenta({ silencioso: true });
    });
    inicializarBuscadorProducto(select); // habilita buscador en nueva fila
    const cantidadInput = nueva.querySelector('.cantidad');
    cantidadInput.value = '';
    cantidadInput.addEventListener('input', () => {
        manejarCantidad(cantidadInput, select);
        validarInventario();
        revalidarPromocionesVenta({ silencioso: true });
    });
}

async function validarMesaLibre(id) {
    try {
        const resp = await fetch('../../api/mesas/listar_mesas.php');
        const data = await resp.json();
        if (!data.success) {
            alert(data.mensaje);
            return false;
        }
        const mesa = data.resultado.find(m => parseInt(m.id) === parseInt(id));
        if (!mesa) {
            alert('Mesa no encontrada');
            return false;
        }
        if (mesa.estado !== 'libre') {
            alert('La mesa seleccionada no está libre');
            return false;
        }
        return true;
    } catch (err) {
        console.error(err);
        alert('Error al verificar mesa');
        return false;
    }
}

async function registrarVenta() {
    const tipo = document.getElementById('tipo_entrega').value;
    const mesa_id = parseInt(document.getElementById('mesa_id').value);
    const repartidor_id = parseInt(document.getElementById('repartidor_id').value);
    let usuario_id = (tipo === 'rapido')
        ? (parseInt(window.usuarioId || '0', 10) || null)
        : parseInt(document.getElementById('usuario_id').value);
    const observacion = document.getElementById('observacion').value.trim();
    // Obtener carrito (incluye enví­o si el panel está activo)
    const productos = obtenerCarritoActual();
    let cliente_id = null;
    let costoForeCapturado = null;
    let clienteColoniaSeleccionadaId = null;

    if (!validarInventario()) {
        return;
    }

    if (!revalidarPromocionesVenta()) {
        return;
    }

    if (tipo === 'mesa') {
        if (isNaN(mesa_id) || !mesa_id) {
            alert('Selecciona una mesa válida');
            return;
        }
        if (isNaN(usuario_id) || !usuario_id) {
            alert('La mesa seleccionada no tiene mesero asignado. Contacta al administrador.');
            return;
        }
        const libre = await validarMesaLibre(mesa_id);
        if (!libre) {
            return;
        }
    } else if (tipo === 'domicilio') {
        if (isNaN(repartidor_id) || !repartidor_id) {
            alert('Selecciona un repartidor válido');
            return;
        }
        if (esDomicilioConRepartidorCasa()) {
            // Forzar usuario por defecto cuando es reparto casa
            if (isNaN(usuario_id) || !usuario_id) {
                const selUsr = document.getElementById('usuario_id');
                if (selUsr && selUsr.options.length > 1) {
                    selUsr.selectedIndex = 1;
                    usuario_id = parseInt(selUsr.value);
                }
            }
            if (isNaN(usuario_id) || !usuario_id) {
                alert('Selecciona un usuario para el reparto casa.');
                return;
            }
            const cliSel = document.getElementById('cliente_id');
            const costoInput = document.getElementById('costoForeInput');
            cliente_id = parseInt(cliSel?.value || '');
            // La selección del cliente es informativa y opcional.
            if (isNaN(cliente_id) || !cliente_id) {
                cliente_id = null;
            }
            if (cliente_id) {
                costoForeCapturado = costoInput ? Number(costoInput.value || 0) : null;
                if (!costoForeCapturado || costoForeCapturado <= 0) {
                    alert('Captura el costo de envío para la colonia seleccionada');
                    return;
                }
                actualizarPrecioEnvio(costoForeCapturado);
            } else {
                // Sin cliente, usar el precio configurado en el panel de envío (concepto default).
                const precioConcepto = Number(
                    (document.getElementById('envioPrecio')?.value) || (window.ENVIO_CASA_DEFAULT_PRECIO || 30)
                );
                costoForeCapturado = Number.isFinite(precioConcepto) ? precioConcepto : (window.ENVIO_CASA_DEFAULT_PRECIO || 30);
                actualizarPrecioEnvio(costoForeCapturado);
            }
        }
    } else if (tipo !== 'rapido') {
        alert('Tipo de entrega inválido');
        return;
    }

    if (cliente_id && clienteSeleccionado && Number(clienteSeleccionado.id) === Number(cliente_id) && !clienteColoniaOriginalId) {
        const cand = parseInt(clienteSeleccionado.colonia_id, 10);
        if (!Number.isNaN(cand) && cand > 0) {
            clienteColoniaSeleccionadaId = cand;
        }
    }

    const payload = {
        tipo,
        mesa_id: tipo === 'mesa' ? mesa_id : null,
        repartidor_id: tipo === 'domicilio' ? repartidor_id : null,
        usuario_id,
        observacion,
        productos,
        corte_id: corteIdActual,
        sede_id: sedeId
    };
    if (cliente_id) {
        payload.cliente_id = cliente_id;
    }
    if (clienteColoniaSeleccionadaId) {
        payload.cliente_colonia_id = clienteColoniaSeleccionadaId;
    }
    if (cliente_id && costoForeCapturado !== null && !isNaN(costoForeCapturado)) {
        payload.costo_fore = costoForeCapturado;
    }
    if (costoForeCapturado !== null && !isNaN(costoForeCapturado)) {
        payload.precio_envio = costoForeCapturado;
    }
    // Promociones seleccionadas (opcional)
    try {
        const promosSel = obtenerPromocionesSeleccionadasVenta();
        if (promosSel.length) {
            payload.promocion_id = promosSel[0];
            payload.promociones_ids = promosSel;
        }
    } catch (_) {}

    // Si el panel de enví­o está activo, incluir precio_envio y envio_cantidad explí­citos para blindaje en backend
    try {
        const panel = document.getElementById('panelEnvioCasa');
        const PID = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
        if (panel && panel.style.display !== 'none') {
            const c = Math.max(0, Number(document.getElementById('envioCantidad')?.value || 0));
            const p = Math.max(0, Number(document.getElementById('envioPrecio')?.value || (window.ENVIO_CASA_DEFAULT_PRECIO || 30)));
            if (c > 0) {
                payload.envio_cantidad = c;
                payload.precio_envio = p;
                // Evitar duplicado en productos: si no existe aún, podemos optar por dejar que backend inserte
                // pero si ya está en productos (por alguna razón), el backend actualizará flags/valores.
                const ya = productos.find(it => Number(it.producto_id) === PID);
                if (!ya) {
                    // Opcional: podrí­amos empujar al array, pero delegamos al backend para insertar/actualizar
                }
            }
        }
    } catch (_) {}

    try {
        const resp = await fetch('../../api/ventas/crear_venta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            const resultado = data.resultado || {};
            const vid = resultado.venta_id || resultado.id || data.venta_id || data.id || null;
            window.__ultimaVentaRegistrada = vid;
            mostrarModalVentaRegistrada(vid);
            const ultimoDetalle = resultado.ultimo_detalle_id || data.ultimo_detalle_id || null;
            if (ultimoDetalle) {
                window.ultimoDetalleCocina = ultimoDetalle;
                localStorage.setItem('ultimoDetalleCocina', String(ultimoDetalle));
            }
            await cargarHistorial();
            await resetFormularioVenta();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al registrar venta');
    }
}
async function resetFormularioVenta() {
    // Limpiar selects visibles
    const tipoEntrega = document.getElementById('tipo_entrega');
    const mesa = document.getElementById('mesa_id');
    const rep = document.getElementById('repartidor_id');
    const mesero = document.getElementById('usuario_id');
    const obs = document.getElementById('observacion');
    const selPromo = document.getElementById('promocion_id');

    if (tipoEntrega) tipoEntrega.value = '';         // vuelve al estado neutro
    if (mesa) mesa.value = '';
    if (rep) rep.value = '';
    if (mesero) { mesero.disabled = false; mesero.value = ''; }
    if (obs) obs.value = '';
    if (selPromo) selPromo.value = '';
    limpiarPanelPromosVenta();
    const selCliente = document.getElementById('cliente_id');
    const costoFore = document.getElementById('costoForeInput');
    if (selCliente) selCliente.value = '';
    if (costoFore) costoFore.value = '';
    actualizarResumenCliente(null);

    // Forzar placeholder y repintar selects clave
    ;['tipo_entrega','mesa_id','repartidor_id','usuario_id'].forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      if (el.querySelector && el.querySelector('option[value=""]')) el.value = '';
      try { el.dispatchEvent(new Event('change')); } catch(_) {}
      if (typeof actualizarEstiloSelect === 'function') try { actualizarEstiloSelect(el); } catch(_) {}
    });

    // Oculta secciones dependientes y revalida
    const campoMesa = document.getElementById('campoMesa');
    const campoRep = document.getElementById('campoRepartidor');
    const campoObs = document.getElementById('campoObservacion');
    if (campoMesa) campoMesa.style.display = 'none';
    if (campoRep) campoRep.style.display = 'none';
    if (campoObs) campoObs.style.display = 'none';

    // Reset de tabla de productos a UNA fila limpia
    const tbody = document.querySelector('#productos tbody');
    if (tbody) {
        const base = tbody.querySelector('tr');
        tbody.innerHTML = ''; // limpia todo
        const fila = base.cloneNode(true);
        // limpia inputs, cache de precio y flags de autocomplete
        fila.querySelectorAll('input').forEach(inp => {
            inp.value = '';
            if (inp.classList.contains('precio')) delete inp.dataset.unitario;
            if (inp.classList.contains('buscador-producto')) delete inp.dataset.autocompleteInitialized;
        });
        const listaAuto = fila.querySelector('.lista-productos');
        if (listaAuto) { listaAuto.innerHTML = ''; listaAuto.style.display = 'none'; }
        // repuebla el select de producto con el catálogo actual
        const selProd = fila.querySelector('select.producto');
        if (selProd) {
            selProd.innerHTML = '<option value="">--Selecciona--</option>';
            (catalogo || []).forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.nombre;
                opt.dataset.precio = p.precio;
                opt.dataset.existencia = p.existencia;
                selProd.appendChild(opt);
            });

            // reengancha eventos de la fila
            selProd.addEventListener('change', () => {
                actualizarPrecio(selProd);
                verificarActivacionProductos();
            });
            // re-activa autocomplete en la fila base
            inicializarBuscadorProducto(selProd);
            const cant = fila.querySelector('.cantidad');
            if (cant) {
                cant.value = '';
                cant.addEventListener('input', () => {
                    manejarCantidad(cant, selProd);
                    validarInventario();
                });
            }
        }
        tbody.appendChild(fila);
    }

    // Recargar opciones desde el backend (mesas/repartidores/meseros) por si cambió algo
    await Promise.allSettled([
        cargarMesas(),
        cargarRepartidores(),
        cargarMeseros()
    ]);

    verificarActivacionProductos();
    actualizarComboPromociones();
    if (tipoEntrega) tipoEntrega.focus();
}

function onClienteSeleccionadoChange() {
    const hiddenId = document.getElementById('cliente_id');
    const cli = hiddenId ? clientesDomicilio.find(c => String(c.id) === hiddenId.value) : null;
    actualizarResumenCliente(cli || null);
}

function abrirModalNuevoCliente() {
    cargarColoniasCatalogo().then(() => {
        pintarColoniasSelect(document.getElementById('nuevoClienteColonia'));
        if (typeof showModal === 'function') {
            showModal('#modalNuevoCliente');
        } else if (window.$) {
            $('#modalNuevoCliente').modal('show');
        }
    });
}

function cerrarModalNuevoCliente() {
    if (typeof hideModal === 'function') {
        hideModal('#modalNuevoCliente');
    } else if (window.$) {
        $('#modalNuevoCliente').modal('hide');
    }
}

async function guardarNuevoCliente() {
    const nombre = document.getElementById('nuevoClienteNombre')?.value.trim();
    const telefono = document.getElementById('nuevoClienteTelefono')?.value.trim();
    const colonia_id = parseInt(document.getElementById('nuevoClienteColonia')?.value || '');
    const calle = document.getElementById('nuevoClienteCalle')?.value.trim();
    const numero_exterior = document.getElementById('nuevoClienteNumero')?.value.trim();
    const entre_calle_1 = document.getElementById('nuevoClienteEntre1')?.value.trim();
    const entre_calle_2 = document.getElementById('nuevoClienteEntre2')?.value.trim();
    const referencias = document.getElementById('nuevoClienteReferencias')?.value.trim();
    const costo_fore_val = document.getElementById('nuevoClienteCostoFore')?.value;

    if (!nombre || !colonia_id) {
        alert('Captura al menos el nombre y la colonia');
        return;
    }

    const payload = {
        nombre,
        telefono,
        colonia_id,
        calle,
        numero_exterior,
        entre_calle_1,
        entre_calle_2,
        referencias
    };
    if (costo_fore_val !== null && costo_fore_val !== undefined && costo_fore_val !== '') {
        payload.costo_fore = Number(costo_fore_val);
    }

    try {
        const resp = await fetch('../../api/clientes/crear.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            const nuevo = data.resultado || data;
            if (nuevo) {
                clientesDomicilio.push(nuevo);
                seleccionarCliente(nuevo);
                if (nuevo.costo_fore !== null && nuevo.costo_fore !== undefined) {
                    actualizarPrecioEnvio(nuevo.costo_fore);
                }
            }
            cerrarModalNuevoCliente();
            document.getElementById('formNuevoCliente')?.reset();
        } else {
            alert(data.mensaje || 'No se pudo crear el cliente');
        }
    } catch (err) {
        console.error('Error al crear cliente', err);
        alert('No se pudo crear el cliente');
    }
}

async function verDetalles(id) {
    ventaIdActual = id;
    try {
        const resp = await fetch('../../api/ventas/detalle_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(id) })
        });
        const data = await resp.json();
        if (data.success) {
            const info = data.resultado || data;
            const modal = document.getElementById('modal-detalles');
            const contenedor = modal.querySelector('.modal-body');
            const ventaListado = ventasData[id] || {};
            const estatusVenta = String(ventaListado.estatus || info.estatus || '').toLowerCase();
            const ventaCerrada = estatusVenta === 'cerrada';
            const botonTicketTexto = ventaCerrada ? 'Agregar propina' : 'Imprimir ticket';
            const destino = info.tipo_entrega === 'mesa'
                ? info.mesa
                : info.tipo_entrega === 'domicilio'
                    ? info.repartidor
                    : 'Venta rápida';
            let html = `
                        <p>Tipo: ${info.tipo_entrega}<br>Destino: ${destino}<br>Mesero: ${info.mesero}</p>`;
            html += `<table class="styled-table" border="1"><thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th>Estatus</th><th></th></tr></thead><tbody>`;
            info.productos.forEach(p => {
                const btnEliminar = p.estado_producto !== 'entregado'
                    ? (ventaCerrada
                        ? `<button class="btn custom-btn delDetalle" data-id="${p.id}" disabled title="Venta cerrada">Eliminar</button>`
                        : `<button class="btn custom-btn delDetalle" data-id="${p.id}">Eliminar</button>`)
                    : '';
                const btnEntregar = p.estado_producto === 'listo'
                    ? ` <button class="btn btn-success btn-entregar" data-id="${p.id}">Entregar</button>`
                    : '';
                const est = (p.estado_producto || '').replace('_', ' ');
                html += `<tr><td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td>${p.subtotal}</td><td>${est}</td>` +
                    `<td>${btnEliminar}${btnEntregar}</td></tr>`;
            });
            html += `<tr id="detalle_nuevo">
                <td>
                  <div class="selector-producto position-relative">
                    <input type="text" id="detalle_buscador" class="form-control buscador-producto" placeholder="Buscar producto...">
                    <select id="detalle_producto" class="d-none"></select>
                    <ul id="detalle_lista" class="list-group lista-productos position-absolute w-100"></ul>
                  </div>
                </td>
                <td><input type="number" id="detalle_cantidad" class="form-control" min="1" step="1" value="1"></td>
                <td colspan="3"></td>
                <td><button class="btn custom-btn" id="addDetalle" ${ventaCerrada ? 'disabled title="Venta cerrada: no se puede agregar"' : ''}>Agregar</button></td>
            </tr>`;
            html += `</tbody></table>`;
            if (ventaCerrada) {
                html += `<div class="mt-2 alert alert-warning">La venta está <strong>cerrada</strong>. No es posible agregar productos.</div>`;
            }
            if (info.foto_entrega) {
                html += `<p>Evidencia:<br><img src="../../uploads/evidencias/${info.foto_entrega}" width="300"></p>`;
            }
            html += `<div class="mt-2">
                        <button class="btn custom-btn" id="imprimirTicket">${botonTicketTexto}</button>
                        ${ventaCerrada ? '<button class="btn custom-btn" id="reimprimirTicket" style="margin-left:8px;">Reimprimir ticket</button>' : ''}
                        <button class="btn custom-btn" id="imprimirComandaDetalle" style="margin-left:8px;">Comanda</button>
                        <button hidden class="btn custom-btn" id="cerrarDetalle" data-dismiss="modal">Cerrar</button>
                      </div>`;

            contenedor.innerHTML = html;
            showModal('#modal-detalles');

            contenedor.querySelectorAll('.delDetalle').forEach(btn => {
                btn.addEventListener('click', () => eliminarDetalle(btn.dataset.id, id));
            });
            inicializarBuscadorDetalle();
            const addBtn = document.getElementById('addDetalle');
            const qtyInput = document.getElementById('detalle_cantidad');
            if (qtyInput) {
                qtyInput.addEventListener('input', () => {
                    let v = parseInt(qtyInput.value, 10);
                    if (isNaN(v) || v < 1) v = 1;
                    qtyInput.value = String(v);
                });
            }
            if (addBtn) {
                addBtn.addEventListener('click', () => agregarDetalle(id));
            }
            $('#cerrarDetalle').on('click', () => {
                hideModal('#modal-detalles');
            });
            $('#imprimirTicket').on('click', () => {
                const venta = ventasData[id] || {};
                const total = venta.total || info.productos.reduce((s, p) => s + parseFloat(p.subtotal), 0);
                let sede = venta.sede_id || sedeId;
                if (!venta.sede_id) {
                    const entrada = prompt('Indica sede', sedeId);
                    if (entrada) sede = parseInt(entrada) || sede;
                }
                  const payload = {
                      venta_id: parseInt(id),
                      usuario_id: venta.usuario_id || info.usuario_id || 1,
                      fecha: venta.fecha || info.fecha || '',
                      tipo_entrega: venta.tipo_entrega || info.tipo_entrega || '',
                      repartidor: venta.repartidor || info.repartidor || '',
                      propina_efectivo: info.propina_efectivo,
                      propina_cheque: info.propina_cheque,
                      propina_tarjeta: info.propina_tarjeta,
                      productos: info.productos,
                      total,
                      sede_id: venta.sede_id || info.sede_id || sede,
                      promocion_id: venta.promocion_id || info.promocion_id || null,
                      promocion_descuento: info.promocion_descuento || 0,
                      promociones_ids: Array.isArray(info.promociones_ids) ? info.promociones_ids : []
                  };
                localStorage.setItem('ticketData', JSON.stringify(payload));
                imprimirTicket(id);
            });

            // Botón de reimpresión: visible solo si la venta está cerrada
            if (ventaCerrada) {
                const btnReimp = document.getElementById('reimprimirTicket');
                if (btnReimp) {
                    btnReimp.addEventListener('click', () => {
                        // Reimprime los tickets ya generados para esta venta desde el backend
                        window.open(urlConImpresora('../../api/tickets/reimprime_ticket.php?venta_id=' + encodeURIComponent(id)));
                    });
                }
            }
            const btnComanda = document.getElementById('imprimirComandaDetalle');
            if (btnComanda) {
                btnComanda.addEventListener('click', () => imprimirComanda(id));
            }
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
    }
}

async function eliminarDetalle(detalleId, ventaId) {
    const ventaListado = ventasData[ventaId] || {};
    const estatusVenta = String(ventaListado.estatus || '').toLowerCase();
    if (estatusVenta === 'cerrada') {
        alert('Venta cerrada: no se pueden eliminar productos');
        return;
    }
    if (!confirm('¿Eliminar producto?')) return;

    try {
        const resp = await fetch('../../api/mesas/eliminar_producto_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ detalle_id: Number(detalleId) })
        });

        const contentType = resp.headers.get('content-type') || '';
        const raw = await resp.text(); // leemos SIEMPRE como texto primero

        if (!resp.ok) {
            console.error('HTTP error:', resp.status, raw);
            alert(`Error del servidor (HTTP ${resp.status}).`);
            return;
        }

        let data;
        if (contentType.includes('application/json')) {
            try {
                data = JSON.parse(raw);
            } catch (e) {
                console.error('JSON inválido:', raw);
                alert('Respuesta no válida del servidor.');
                return;
            }
        } else {
            console.error('No es JSON, cuerpo:', raw);
            alert('El servidor no devolvió JSON.');
            return;
        }

        if (data.success) {
            verDetalles(ventaId);
            await cargarHistorial();
        } else {
            alert(data.mensaje || 'Operación no exitosa.');
        }
    } catch (err) {
        console.error(err);
        alert('Error al eliminar');
    }
}


async function agregarDetalle(ventaId) {
    const select = document.getElementById('detalle_producto');
    // Bloqueo si la venta está cerrada
    const ventaListado = ventasData[ventaId] || {};
    const estatusVenta = String(ventaListado.estatus || '').toLowerCase();
    if (estatusVenta === 'cerrada') {
        alert('Venta cerrada: no se pueden agregar productos');
        return;
    }
    const cantidad = parseInt(document.getElementById('detalle_cantidad').value, 10);
    const productoId = parseInt(select.value);
    const prod = catalogo.find(p => parseInt(p.id) === productoId);
    const precio = prod ? parseFloat(prod.precio) : parseFloat(select.selectedOptions[0]?.dataset.precio || 0);
    if (isNaN(productoId) || isNaN(cantidad) || cantidad < 1) {
        alert('Producto o cantidad inválida');
        return;
    }
    if (prod && cantidad > parseFloat(prod.existencia)) {
        alert(`Solo hay ${prod.existencia} disponibles de ${prod.nombre}`);
        return;
    }
    try {
        const resp = await fetch('../../api/mesas/agregar_producto_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                venta_id: parseInt(ventaId),
                producto_id: productoId,
                cantidad,
                precio_unitario: precio
            })
        });
        const data = await resp.json();
        if (data.success) {
            verDetalles(ventaId);
            await cargarHistorial();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al agregar');
    }
}

function cargarSolicitudes() {
    const tbody = document.querySelector('#solicitudes tbody');
    if (!tbody) return;
    fetch('../../api/mesas/listar_mesas.php')
        .then(r => r.json())
        .then(d => {
            if (!d.success) { alert(d.mensaje); return; }
            tbody.innerHTML = '';
            ticketRequests = d.resultado.filter(m => m.ticket_enviado);
            ticketRequests.forEach(req => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>âš ï¸ ${req.nombre}</td><td><button class="btn custom-btn" data-action="imprimir-ticket" data-venta-id="${req.venta_id}" data-mesa-id="${req.id}">Imprimir</button></td>`;
                tbody.appendChild(tr);
            });
        })
        .catch(() => alert('Error al cargar solicitudes'));
}

async function imprimirSolicitud(mesaId, ventaId) {
    try {
        const resp = await fetch('../../api/ventas/detalle_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(ventaId) })
        });
        const data = await resp.json();
        if (data.success) {
            const info = data.resultado || data;
            const venta = ventasData[ventaId] || {};
            const total = venta.total || info.productos.reduce((s, p) => s + parseFloat(p.subtotal), 0);
            let sede = venta.sede_id || sedeId;
            if (!venta.sede_id) {
                const entrada = prompt('Indica sede', sedeId);
                if (entrada) sede = parseInt(entrada) || sede;
            }
              const payload = {
                  venta_id: parseInt(ventaId),
                  usuario_id: venta.usuario_id || 1,
                  fecha: venta.fecha || '',
                  tipo_entrega: venta.tipo_entrega || '',
                  repartidor: venta.repartidor || info.repartidor || '',
                  productos: info.productos,
                  total,
                  sede_id: sede,
                  promocion_id: venta.promocion_id || info.promocion_id || null,
                  promocion_descuento: info.promocion_descuento || 0,
                  promociones_ids: Array.isArray(info.promociones_ids) ? info.promociones_ids : []
              };
            localStorage.setItem('ticketData', JSON.stringify(payload));
            ticketPrinted(mesaId);
            imprimirTicket(ventaId);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
    }
}

function ticketPrinted(mesaId) {
    fetch('../../api/mesas/limpiar_ticket.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mesa_id: parseInt(mesaId) })
    }).finally(cargarSolicitudes);
}
window.ticketPrinted = ticketPrinted;

document.addEventListener("change", function (e) {
    if (e.target.classList.contains("producto")) {
        actualizarPrecio(e.target);
        validarInventario();
        actualizarEstiloSelect(e.target);
    }
});
function verificarActivacionProductos() {
    const mesa = document.getElementById('mesa_id').value;
    const repartidor = document.getElementById('repartidor_id').value;
    const tipoEntrega = document.getElementById('tipo_entrega').value;
    const seccionProductos = document.getElementById('seccionProductos');

    // Mostrar si hay alguno seleccionado según tipo de entrega
    if (
        (tipoEntrega === 'mesa' && mesa) ||
        (tipoEntrega === 'domicilio' && repartidor) ||
        tipoEntrega === 'rapido'
    ) {
        seccionProductos.style.display = 'block';
    } else {
        seccionProductos.style.display = 'none';
    }
}

function obtenerPromosPanelSelects() {
    const panel = document.getElementById('panelPromosVenta');
    if (!panel) return [];
    return Array.from(panel.querySelectorAll('select.promo-select'));
}

function obtenerPromocionesSeleccionadasVenta() {
    return obtenerPromosPanelSelects()
        .map(sel => parseInt(sel.value || '0', 10))
        .filter(v => !isNaN(v) && v > 0);
}

function actualizarResumenPromosVenta() {
    const seleccionadas = obtenerPromocionesSeleccionadasVenta();
    window.__promosVentaSeleccionadas = seleccionadas;
    const lbl = document.getElementById('lblPromosActivasVenta');
    if (lbl) {
        lbl.textContent = seleccionadas.length;
    }
}

function mostrarModalPromoError(contenidoHtml, opts = {}) {
    if (opts && opts.silencioso) return;
    const body = document.getElementById('promoErrorMsg');
    if (body) {
        body.innerHTML = contenidoHtml;
    }
    try {
        if (window.jQuery && window.jQuery('#modalPromoError').modal) {
            window.jQuery('#modalPromoError').modal('show');
        } else {
            const tmp = document.createElement('div');
            tmp.innerHTML = contenidoHtml;
            alert(tmp.textContent || 'La promoción no aplica');
        }
    } catch (_) {
        alert('La promoción no aplica');
    }
}

function construirOpcionesPromoVenta(select, promos) {
    if (!select) return;
    select.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = 'Sin promoci\u00f3n';
    select.appendChild(opt0);
    (promos || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nombre;
        select.appendChild(opt);
    });
}

function limpiarPanelPromosVenta() {
    const extras = document.getElementById('promosVentaDinamicas');
    if (extras) {
        extras.innerHTML = '';
    }
    const base = document.getElementById('promocion_id');
    if (base) {
        base.value = '';
    }
    actualizarResumenPromosVenta();
}

function agregarSelectPromoVenta() {
    const cont = document.getElementById('promosVentaDinamicas');
    if (!cont || !catalogoPromocionesVentaFiltradas.length) return;
    const row = document.createElement('div');
    row.className = 'promo-row d-flex flex-wrap gap-2 align-items-center mt-2';
    const select = document.createElement('select');
    select.className = 'form-control promo-select flex-grow-1';
    construirOpcionesPromoVenta(select, catalogoPromocionesVentaFiltradas);
    select.addEventListener('change', () => validarPromoVenta(select));
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-danger';
    btn.textContent = 'Quitar';
    btn.addEventListener('click', () => {
        row.remove();
        actualizarResumenPromosVenta();
        revalidarPromocionesVenta({ silencioso: true });
    });
    row.appendChild(select);
    row.appendChild(btn);
    cont.appendChild(row);
}

function inicializarPanelPromosVenta() {
    const campo = document.getElementById('campoPromocion');
    const panel = document.getElementById('panelPromosVenta');
    const base = document.getElementById('promocion_id');
    if (!campo || !panel || !base) return;
    if (!catalogoPromocionesVentaFiltradas.length) {
        campo.style.display = 'none';
        limpiarPanelPromosVenta();
        return;
    }
    construirOpcionesPromoVenta(base, catalogoPromocionesVentaFiltradas);
    if (!panelPromosVentaInicializado) {
        base.addEventListener('change', () => validarPromoVenta(base));
        const btnAdd = document.getElementById('btnAgregarPromoVenta');
        if (btnAdd) {
            btnAdd.addEventListener('click', agregarSelectPromoVenta);
        }
        panelPromosVentaInicializado = true;
    }
    limpiarPanelPromosVenta();
    panel.style.display = 'block';
    campo.style.display = 'block';
}

function validarPromosAcumulablesLlevarVenta(selectedIds, carrito) {
    const tipoEl = document.getElementById('tipo_entrega');
    const tipoEntrega = (tipoEl ? tipoEl.value : '').toLowerCase();
    if (!['domicilio', 'rapido', 'llevar'].includes(tipoEntrega)) {
        return { ok: true };
    }
    if (!Array.isArray(selectedIds) || !selectedIds.length) {
        return { ok: true };
    }

    const promosSel = selectedIds
        .map(pid => (catalogoPromocionesVenta || []).find(p => parseInt(p.id, 10) === pid))
        .filter(Boolean);
    if (!promosSel.length) {
        return { ok: true };
    }

    const combos = promosSel.filter(p => {
        const tipo = String(p.tipo || '').toLowerCase();
        const monto = Number(p.monto || 0);
        const tipoVenta = String(p.tipo_venta || '').toLowerCase();
        return tipo === 'combo' && monto > 0 && (tipoVenta === 'llevar');
    });
    if (!combos.length) {
        return { ok: true };
    }

    const countPromos = id => combos.filter(p => parseInt(p.id, 10) === id).length;
    const promo5Count = countPromos(5);
    const promo6Count = countPromos(6);
    const promo9Count = countPromos(9);
    if (!promo5Count && !promo6Count && !promo9Count) {
        return { ok: true };
    }

    const promo6 = combos.find(p => parseInt(p.id, 10) === 6);
    const promo9Obj = combos.find(p => parseInt(p.id, 10) === 9) || {};

    let rollPromo6Ids = [];
    if (promo6Count && promo6 && promo6.regla) {
        let rj;
        try { rj = JSON.parse(promo6.regla); } catch (e) { rj = null; }
        const arr = Array.isArray(rj) ? rj : (rj ? [rj] : []);
        rollPromo6Ids = arr
            .map(r => parseInt(r.id_producto || 0, 10))
            .filter(Boolean);
    }

    let cat9Count = 0;
    let rollSubsetCount = 0;
    let teaCount = 0;
    carrito.forEach(item => {
        const cant = Number(item.cantidad || 0);
        if (!cant) return;
        const pid = parseInt(item.producto_id || 0, 10);
        const catId = parseInt(item.categoria_id || 0, 10);
        if (catId === 9) cat9Count += cant;
        if (rollPromo6Ids.includes(pid)) rollSubsetCount += cant;
        if (pid === 66) teaCount += cant;
    });

    if (!cat9Count && !rollSubsetCount && !teaCount) {
        return { ok: true };
    }

    const describirPromos = (nombres = []) => {
        const lista = nombres.filter(Boolean);
        if (!lista.length) return '';
        const fraseBase = lista.length === 1 ? 'La promoción' : 'Las promociones';
        if (lista.length === 1) {
            return `${fraseBase} "${lista[0]}"`;
        }
        if (lista.length === 2) {
            return `${fraseBase} "${lista[0]}" y "${lista[1]}"`;
        }
        const ult = lista.pop();
        return `${fraseBase} "${lista.join('", "')}" y "${ult}"`;
    };

    const errores = [];
    const nombrePromo5 = (combos.find(p => parseInt(p.id, 10) === 5) || {}).nombre || '2 Té';
    const nombrePromo6 = (promo6 || {}).nombre || '2 rollos y té';
    const nombrePromo9 = promo9Obj.nombre || '3x $209 en rollos';
    let nombreCat9 = 'categoría 9';
    if (promo9Obj && Array.isArray(promo9Obj.categorias_regla) && promo9Obj.categorias_regla.length) {
        nombreCat9 = promo9Obj.categorias_regla[0].nombre || nombreCat9;
    }

    const totalTeaNeeded = (promo6Count * 1) + (promo5Count * 2);
    if ((promo5Count || promo6Count) && totalTeaNeeded > teaCount) {
        errores.push(`${describirPromos([
            promo6Count ? nombrePromo6 : null,
            promo5Count ? nombrePromo5 : null,
        ])} requieren ${totalTeaNeeded} tés y solo hay ${teaCount}.`);
    }

    const totalRollPromo6Needed = promo6Count * 2;
    if (promo6Count && totalRollPromo6Needed > rollSubsetCount) {
        errores.push(`${describirPromos([nombrePromo6])} requiere ${totalRollPromo6Needed} rollos válidos y solo hay ${rollSubsetCount}.`);
    }

    const totalRollsNeeded = totalRollPromo6Needed + (promo9Count * 3);
    if ((promo6Count || promo9Count) && totalRollsNeeded > cat9Count) {
        errores.push(`${describirPromos([
            promo6Count ? nombrePromo6 : null,
            promo9Count ? nombrePromo9 : null,
        ])} requieren ${totalRollsNeeded} rollos (${nombreCat9}) y solo hay ${cat9Count}.`);
    }

    if (errores.length) {
        return { ok: false, mensajes: errores };
    }
    return { ok: true };
}

function validarPromoVenta(selectEl, opts = {}) {
    if (!selectEl) return true;
    const promoId = parseInt(selectEl.value || '0', 10);
    if (!promoId) {
        actualizarResumenPromosVenta();
        return true;
    }
    const fuente = catalogoPromocionesVentaFiltradas.length ? catalogoPromocionesVentaFiltradas : catalogoPromocionesVenta;
    const promo = (fuente || []).find(p => parseInt(p.id, 10) === promoId);
    if (!promo || !promo.regla) {
        actualizarResumenPromosVenta();
        return true;
    }

    let reglaJson;
    try { reglaJson = JSON.parse(promo.regla); } catch (_) { reglaJson = null; }
    const reglasArray = Array.isArray(reglaJson) ? reglaJson : (reglaJson ? [reglaJson] : []);
    if (!reglasArray.length) {
        actualizarResumenPromosVenta();
        return true;
    }

    const carrito = opts.carrito || obtenerCarritoActual();
    const tipoPromo = String(promo.tipo || '').toLowerCase();
    const promoIdInt = parseInt(promo.id || 0, 10);

    if (promoIdInt === 6 && tipoPromo === 'combo') {
        const rollIds = reglasArray.map(r => parseInt(r.id_producto || 0, 10)).filter(Boolean);
        const teaId = 66;
        let rollUnits = 0;
        let teaUnits = 0;
        carrito.forEach(item => {
            const pid = parseInt(item.producto_id || 0, 10);
            const cant = Number(item.cantidad || 0);
            if (rollIds.includes(pid)) rollUnits += cant;
            if (pid === teaId) teaUnits += cant;
        });
        if (rollUnits < 2 || teaUnits < 1) {
            const msg = '<p>La promoción es la combinación de 2 rollos más té, en la selección de Chiquilin, Maki Carne, Mar y Tierra.</p>';
            mostrarModalPromoError(msg, opts);
            selectEl.value = '';
            actualizarResumenPromosVenta();
            return false;
        }
    } else {
        const mensajes = [];
        reglasArray.forEach(r => {
            const reqCant = parseInt(r.cantidad || 0, 10) || 0;
            if (!reqCant) return;
            if (r.id_producto) {
                const pid = parseInt(r.id_producto, 10);
                const exist = carrito.reduce((s, item) => {
                    const pidItem = parseInt(item.producto_id || 0, 10);
                    const cant = Number(item.cantidad || 0);
                    return s + (pidItem === pid ? cant : 0);
                }, 0);
                if (exist < reqCant) {
                    const prodItem = carrito.find(it => parseInt(it.producto_id || 0, 10) === pid);
                    let nombre = prodItem && prodItem.nombre ? prodItem.nombre : null;
                    if (!nombre && promo && Array.isArray(promo.productos_regla)) {
                        const prodRegla = promo.productos_regla.find(pr => parseInt(pr.id, 10) === pid);
                        if (prodRegla && prodRegla.nombre) {
                            nombre = prodRegla.nombre;
                        }
                    }
                    if (!nombre) {
                        nombre = `ID ${pid}`;
                    }
                    mensajes.push(`Producto ${nombre}: se requieren ${reqCant}, solo hay ${exist}.`);
                }
            } else if (r.categoria_id) {
                const cid = parseInt(r.categoria_id, 10);
                const exist = carrito.reduce((s, item) => {
                    const cId = parseInt(item.categoria_id || 0, 10);
                    const cant = Number(item.cantidad || 0);
                    return s + (cId === cid ? cant : 0);
                }, 0);
                if (exist < reqCant) {
                    let nombreCat = `Categoría ${cid}`;
                    if (promo && Array.isArray(promo.categorias_regla)) {
                        const catRegla = promo.categorias_regla.find(cr => parseInt(cr.id, 10) === cid);
                        if (catRegla && catRegla.nombre) {
                            nombreCat = catRegla.nombre;
                        }
                    }
                    mensajes.push(`${nombreCat}: se requieren ${reqCant}, solo hay ${exist}.`);
                }
            }
        });
        if (mensajes.length) {
            const contenido = '<p>La promoci\u00f3n no aplica porque:</p><ul>' +
                mensajes.map(m => `<li>${m}</li>`).join('') +
                '</ul>';
            mostrarModalPromoError(contenido, opts);
            selectEl.value = '';
            actualizarResumenPromosVenta();
            return false;
        }
    }

    actualizarResumenPromosVenta();

    const comboVal = validarPromosAcumulablesLlevarVenta(obtenerPromocionesSeleccionadasVenta(), carrito);
    if (!comboVal.ok) {
        const contenido = '<p>La combinaci\u00f3n de promociones seleccionadas no es v\u00e1lida porque:</p><ul>' +
            (comboVal.mensajes || []).map(m => `<li>${m}</li>`).join('') +
            '</ul>';
        mostrarModalPromoError(contenido, opts);
        selectEl.value = '';
        actualizarResumenPromosVenta();
        return false;
    }

    return true;
}

function revalidarPromocionesVenta(opts = {}) {
    const selects = obtenerPromosPanelSelects();
    if (!selects.length) return true;
    const carrito = opts.carrito || obtenerCarritoActual();
    let ok = true;
    selects.forEach(sel => {
        const val = parseInt(sel.value || '0', 10);
        if (!val) return;
        const valido = validarPromoVenta(sel, Object.assign({}, opts, { carrito }));
        if (!valido) {
            ok = false;
        }
    });
    return ok;
}

// Carga catálogo de promociones para ventas y actualiza el combo según tipo de entrega
async function cargarPromocionesVenta() {
    try {
        const resp = await fetch(promocionesUrlVentas);
        const data = await resp.json();
        if (data && data.success && Array.isArray(data.promociones)) {
            catalogoPromocionesVenta = data.promociones;
            actualizarComboPromociones();
        }
    } catch (e) {
        console.error('Error cargando promociones para ventas', e);
    }
}

function actualizarComboPromociones() {
    const campo = document.getElementById('campoPromocion');
    const tipoEl = document.getElementById('tipo_entrega');
    if (!campo || !tipoEl) return;

    const tipo = (tipoEl.value || '').toLowerCase();

    if (!tipo || !Array.isArray(catalogoPromocionesVenta) || !catalogoPromocionesVenta.length) {
        catalogoPromocionesVentaFiltradas = [];
        campo.style.display = 'none';
        limpiarPanelPromosVenta();
        return;
    }

    let promos = catalogoPromocionesVenta.slice();
    if (tipo === 'mesa') {
        promos = promos.filter(p => String(p.tipo_venta || '').toLowerCase() === 'mesa' || !p.tipo_venta);
    } else if (tipo === 'domicilio' || tipo === 'rapido') {
        promos = promos.filter(p => String(p.tipo_venta || '').toLowerCase() === 'llevar' || !p.tipo_venta);
    }

    if (!promos.length) {
        campo.style.display = 'none';
        catalogoPromocionesVentaFiltradas = [];
        limpiarPanelPromosVenta();
        return;
    }
    catalogoPromocionesVentaFiltradas = promos;
    inicializarPanelPromosVenta();
}

// Listener tipo_entrega: deja tu lógica de mostrar/ocultar divs y AL FINAL llama a actualizarSelectorUsuario()
const tipoEntregaEl = document.getElementById('tipo_entrega');
if (tipoEntregaEl) {
    tipoEntregaEl.addEventListener('change', function () {
        const tipo = this.value;
        const campoMesa = document.getElementById('campoMesa');
        const campoRepartidor = document.getElementById('campoRepartidor');
        const campoObservacion = document.getElementById('campoObservacion');

        if (campoMesa) campoMesa.style.display = (tipo === 'mesa') ? 'block' : 'none';
        if (campoRepartidor) campoRepartidor.style.display = (tipo === 'domicilio') ? 'block' : 'none';
        const campoMesero = document.getElementById('campoMesero');
        if (campoMesero) campoMesero.style.display = (tipo === 'mesa') ? 'block' : 'none';
        if (campoObservacion) campoObservacion.style.display = (tipo === 'domicilio' || tipo === 'rapido' || tipo === 'mesa') ? 'block' : 'none';

        actualizarSelectorUsuario();
        aplicarEnvioSiCorresponde();
        actualizarComboPromociones();
        toggleSeccionClienteDomicilio();
    });
}

// ===== Panel de Enví­o: helpers de carrito mí­nimos =====
function obtenerCarritoActual() {
    const PID = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
    const items = [];
    const filas = document.querySelectorAll('#productos tbody tr');
    filas.forEach(fila => {
        const sel = fila.querySelector('.producto');
        const cantInp = fila.querySelector('.cantidad');
        const precioInp = fila.querySelector('.precio');
        const producto_id = parseInt(sel?.value);
        const cantidad = parseInt(cantInp?.value);
        const unit = parseFloat(precioInp?.dataset?.unitario || 0);
        if (!isNaN(producto_id) && !isNaN(cantidad) && unit > 0 && producto_id !== PID) {
            const infoProducto = (catalogo || []).find(p => parseInt(p.id) === producto_id) || null;
            const nombre = infoProducto?.nombre || sel?.selectedOptions?.[0]?.textContent || '';
            let categoriaId = null;
            if (infoProducto && typeof infoProducto.categoria_id !== 'undefined') {
                categoriaId = infoProducto.categoria_id;
            } else if (sel?.selectedOptions?.[0]?.dataset?.categoriaId) {
                const raw = parseInt(sel.selectedOptions[0].dataset.categoriaId, 10);
                categoriaId = isNaN(raw) ? null : raw;
            }
            items.push({ producto_id, cantidad, precio_unitario: unit, nombre, categoria_id: categoriaId });
        }
    });
    // Añadir envío desde panel si activo
    const panel = document.getElementById('panelEnvioCasa');
    if (panel && panel.style.display !== 'none') {
        const c = Math.max(0, Number(document.getElementById('envioCantidad')?.value || 0));
        const p = Math.max(0, Number(document.getElementById('envioPrecio')?.value || (window.ENVIO_CASA_DEFAULT_PRECIO || 30)));
        if (c > 0) {
            items.push({
                producto_id: PID,
                nombre: window.ENVIO_CASA_NOMBRE || 'ENVÍO – Repartidor casa',
                cantidad: c,
                precio_unitario: p,
                es_envio: true,
                categoria_id: null
            });
        }
    }
    return items;
}

function agregarItemAlCarrito(item) {
    const PID = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
    if (!item) return;
    if (Number(item.producto_id) === PID) {
        const panel = document.getElementById('panelEnvioCasa');
        if (panel) panel.style.display = '';
        const c = document.getElementById('envioCantidad');
        const p = document.getElementById('envioPrecio');
        if (c) c.value = Math.max(1, Number(item.cantidad || 1));
        if (p) p.value = Math.max(0, Number(item.precio_unitario || (window.ENVIO_CASA_DEFAULT_PRECIO || 30)));
        // actualizar subtotal
        const s = document.getElementById('envioSubtotal');
        if (s && c && p) s.textContent = (Number(c.value) * Number(p.value)).toFixed(2);
    }
}

function eliminarItemDelCarrito(producto_id) {
    const PID = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);
    if (Number(producto_id) === PID) {
        const c = document.getElementById('envioCantidad');
        const s = document.getElementById('envioSubtotal');
        if (c) c.value = 0;
        if (s) s.textContent = '0.00';
        const panel = document.getElementById('panelEnvioCasa');
        if (panel) panel.style.display = 'none';
    }
}

// Stubs para compatibilidad
window.renderCarrito = window.renderCarrito || function() {};
window.recalcularTotalesUI = window.recalcularTotalesUI || function() {
    const c = document.getElementById('envioCantidad');
    const p = document.getElementById('envioPrecio');
    const s = document.getElementById('envioSubtotal');
    if (c && p && s) s.textContent = (Math.max(0, Number(c.value||0)) * Math.max(0, Number(p.value||0))).toFixed(2);
};

// ===== Lógica del panel de enví­o y sincronización =====
(function(){
  const PID = Number(window.ENVIO_CASA_PRODUCT_ID || 9001);

  function esDomicilioConRepartidorCasa() {
    const tipo = document.getElementById('tipo_entrega')?.value || '';
    const repSel = document.getElementById('repartidor_id')?.value || '';
    const textoRep = (document.getElementById('repartidor_id')?.selectedOptions?.[0]?.text || '').trim().toLowerCase();
    return tipo === 'domicilio' && (textoRep === 'repartidor casa' || repSel === '4');
  }

  function buscarItemEnvio(items){
    return (items || []).find(it => String(it.producto_id) === String(PID));
  }

  const $panel = document.getElementById('panelEnvioCasa');
  const $nombre = document.getElementById('envioNombre');
  const $cant   = document.getElementById('envioCantidad');
  const $precio = document.getElementById('envioPrecio');
  const $subtot = document.getElementById('envioSubtotal');
  const $btnQuitar = document.getElementById('btnQuitarEnvio');

  if ($nombre) $nombre.textContent = window.ENVIO_CASA_NOMBRE || 'ENVíO â€“ Repartidor casa';

  function actualizarSubtotalEnvioUI(){
    const c = Math.max(0, Number($cant?.value || 0));
    const p = Math.max(0, Number($precio?.value || 0));
    if ($subtot) $subtot.textContent = (c * p).toFixed(2);
  }

  function syncEnvioConCarrito() {
    const items = obtenerCarritoActual();
    const item = buscarItemEnvio(items);
    if (esDomicilioConRepartidorCasa()) {
      if ($panel) $panel.style.display = '';
      if (!item) {
        agregarItemAlCarrito({
          producto_id: PID,
          nombre: window.ENVIO_CASA_NOMBRE || 'ENVíO â€“ Repartidor casa',
          cantidad: 1,
          precio_unitario: Number(window.ENVIO_CASA_DEFAULT_PRECIO || 30),
          es_envio: true
        });
      }
      const items2 = obtenerCarritoActual();
      const envio = buscarItemEnvio(items2);
      if (envio) {
        if ($cant)  $cant.value  = envio.cantidad;
        if ($precio) $precio.value = envio.precio_unitario;
        actualizarSubtotalEnvioUI();
      }
    } else {
      if ($panel) $panel.style.display = 'none';
      eliminarItemDelCarrito(PID);
    }
    window.renderCarrito();
    window.recalcularTotalesUI();
  }

  window.syncEnvioConCarrito = syncEnvioConCarrito;

  $cant && $cant.addEventListener('input', () => {
    const c = Math.max(0, Number($cant.value || 0));
    const p = Math.max(0, Number($precio?.value || 0));
    if (c === 0) {
      eliminarItemDelCarrito(PID);
      actualizarSubtotalEnvioUI();
      window.renderCarrito();
      window.recalcularTotalesUI();
      return;
    }
    const items = obtenerCarritoActual();
    const existe = buscarItemEnvio(items);
    if (!existe) {
      agregarItemAlCarrito({ producto_id: PID, nombre: window.ENVIO_CASA_NOMBRE || 'ENVíO â€“ Repartidor casa', cantidad: c, precio_unitario: p, es_envio: true });
    } else {
      existe.cantidad = c;
      existe.precio_unitario = p;
    }
    actualizarSubtotalEnvioUI();
    window.renderCarrito();
    window.recalcularTotalesUI();
  });

  $precio && $precio.addEventListener('input', () => {
    const c = Math.max(0, Number($cant?.value || 0));
    const p = Math.max(0, Number($precio.value || 0));
    const items = obtenerCarritoActual();
    const existe = buscarItemEnvio(items);
    if (existe) {
      existe.precio_unitario = p;
      if (c === 0) existe.cantidad = 1;
    } else {
      if (c > 0) {
        agregarItemAlCarrito({ producto_id: PID, nombre: window.ENVIO_CASA_NOMBRE || 'ENVíO â€“ Repartidor casa', cantidad: Math.max(1, c), precio_unitario: p, es_envio: true });
      }
    }
    actualizarSubtotalEnvioUI();
    window.renderCarrito();
    window.recalcularTotalesUI();
  });

  $btnQuitar && $btnQuitar.addEventListener('click', () => {
    eliminarItemDelCarrito(PID);
    if ($cant) $cant.value = 0;
    actualizarSubtotalEnvioUI();
    window.renderCarrito();
    window.recalcularTotalesUI();
  });

  document.getElementById('tipo_entrega')?.addEventListener('change', syncEnvioConCarrito);
  document.getElementById('repartidor_id')?.addEventListener('change', syncEnvioConCarrito);

  syncEnvioConCarrito();
})();

// Detecta cambios en mesa o repartidor
document.getElementById('mesa_id').addEventListener('change', () => {
    asignarMeseroPorMesa();
    verificarActivacionProductos();
});
// Nota: no asignar mesero a mesa en este punto; se asigna al registrar la venta
// Listener repartidor_id: cada vez que cambie, recalcula si es "Repartidor casa"
const repartidorEl = document.getElementById('repartidor_id');
if (repartidorEl) {
    repartidorEl.addEventListener('change', () => {
        actualizarSelectorUsuario();
        aplicarEnvioSiCorresponde();
        toggleSeccionClienteDomicilio();
        const sel = document.getElementById('cliente_id');
        if (sel && sel.value) {
            const cli = clientesDomicilio.find(c => String(c.id) === sel.value);
            actualizarResumenCliente(cli || null);
        }
    });
}

function actualizarFechaMovimiento() {
    const now = new Date();
    const formatted = now.toLocaleString();
    const fechaInput = document.getElementById('fechaMovimiento');
    if (fechaInput) {
        fechaInput.value = formatted;
    }
}

function abrirModalMovimiento(tipo) {
    const tipoSelect = document.getElementById('tipoMovimiento');
    const montoInput = document.getElementById('montoMovimiento');
    const motivoInput = document.getElementById('motivoMovimiento');
    if (tipoSelect) tipoSelect.value = tipo;
    if (montoInput) montoInput.value = '';
    if (motivoInput) motivoInput.value = '';
    actualizarFechaMovimiento();
    showModal('#modalMovimientoCaja');
}

async function guardarMovimientoCaja() {
    const tipo = document.getElementById('tipoMovimiento').value;
    const monto = parseFloat(document.getElementById('montoMovimiento').value);
    const motivo = document.getElementById('motivoMovimiento').value.trim();
    if (!tipo || isNaN(monto) || monto <= 0 || !motivo) {
        alert('Completa todos los campos obligatorios');
        return;
    }
    try {
        const resp = await fetch('../../api/corte_caja/movimiento_caja.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tipo_movimiento: tipo, monto, motivo })
        });
        const data = await resp.json();
        if (data.success) {
            hideModal('#modalMovimientoCaja');
            alert('Movimiento registrado correctamente');
            location.reload();
        } else {
            alert(data.error || data.mensaje || 'Error al registrar movimiento');
        }
    } catch (err) {
        console.error(err);
        alert('Error al registrar movimiento');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#tablaDesglose, .tablaDesglose, .filaDenominacion').forEach(e => e.remove());
    verificarCorte();
    cargarMesas();
    cargarProductos();
    cargarRepartidores();
    cargarHistorial();
    cargarSolicitudes();
    cargarPromocionesVenta();
    cargarImpresoras();
    cargarColoniasCatalogo();
    cargarClientesDomicilio();
    document.getElementById('registrarVenta').addEventListener('click', registrarVenta);
    document.getElementById('agregarProducto').addEventListener('click', agregarFilaProducto);
    actualizarSelectorUsuario();
    // Sincronizar panel de enví­o al iniciar
    setTimeout(() => aplicarEnvioSiCorresponde(), 0);

    document.getElementById('recordsPerPage').addEventListener('change', e => {
        limit = parseInt(e.target.value);
        cargarHistorial(1);
    });

    document.getElementById('buscadorVentas').addEventListener('input', e => {
        searchQuery = e.target.value.trim();
        cargarHistorial(1);
    });

    document.getElementById('btnDeposito').addEventListener('click', () => abrirModalMovimiento('deposito'));
    document.getElementById('btnRetiro').addEventListener('click', () => abrirModalMovimiento('retiro'));
    document.getElementById('guardarMovimiento').addEventListener('click', guardarMovimientoCaja);
    const btnDet = document.getElementById('btnDetalleMovs');
    if (btnDet) btnDet.addEventListener('click', abrirDetalleMovimientos);
    const clienteHidden = document.getElementById('cliente_id');
    if (clienteHidden) clienteHidden.addEventListener('change', onClienteSeleccionadoChange);
    const buscadorCliente = document.getElementById('buscarClienteDomicilio');
    if (buscadorCliente) {
        buscadorCliente.addEventListener('input', () => {
            if (clienteHidden) clienteHidden.value = '';
            actualizarResumenCliente(null);
            aplicarFiltroClientesDomicilio();
        });
        buscadorCliente.addEventListener('focus', () => aplicarFiltroClientesDomicilio());
    }
    const buscadorColonia = document.getElementById('buscarColoniaCliente');
    if (buscadorColonia) {
        buscadorColonia.addEventListener('input', () => mostrarSugerenciasColonias(buscadorColonia.value));
        buscadorColonia.addEventListener('focus', () => {
            if (buscadorColonia.value.trim()) mostrarSugerenciasColonias(buscadorColonia.value);
        });
    }
    document.addEventListener('click', e => {
        const listaEl = document.getElementById('listaClientesDomicilio');
        const seccion = document.getElementById('seccionClienteDomicilio');
        if (listaEl && seccion && !seccion.contains(e.target)) {
            listaEl.style.display = 'none';
        }
    });
    document.addEventListener('click', e => {
        const wrap = document.getElementById('clienteColoniaSelectWrap');
        const ul = document.getElementById('listaColoniasCliente');
        if (wrap && ul && !wrap.contains(e.target)) {
            ul.style.display = 'none';
        }
    });
    const btnNuevoCliente = document.getElementById('btnNuevoCliente');
    if (btnNuevoCliente) btnNuevoCliente.addEventListener('click', abrirModalNuevoCliente);
    const btnGuardarCliente = document.getElementById('guardarNuevoCliente');
    if (btnGuardarCliente) btnGuardarCliente.addEventListener('click', guardarNuevoCliente);
    const clienteColoniaSelect = document.getElementById('clienteColoniaSelect');
    if (clienteColoniaSelect) clienteColoniaSelect.addEventListener('change', onSeleccionColoniaManual);
    const costoForeInput = document.getElementById('costoForeInput');
    if (costoForeInput) {
        costoForeInput.addEventListener('input', () => {
            const val = Number(costoForeInput.value || 0);
            if (val > 0) {
                actualizarPrecioEnvio(val);
            }
        });
    }

    // Delegación de eventos con JavaScript puro para botones dinámicos
    const cancelModal = document.getElementById('cancelVentaModal');
    const confirmCancelBtn = document.getElementById('confirmCancelVenta');

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-detalle')) {
            const id = e.target.dataset.id;
            verDetalles(id);
        } else if (e.target.classList.contains('btn-ver-envio')) {
            const id = e.target.dataset.id;
            mostrarClienteEnvio(id);
        } else if (e.target.classList.contains('btn-cancelar')) {
            const id = e.target.dataset.id;
            if (cancelModal) {
                cancelModal.dataset.id = id;
                showModal('#cancelVentaModal');
            }
        }
    });

    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', function () {
            const id = cancelModal && cancelModal.dataset.id;
            hideModal('#cancelVentaModal');
            fetch('../../api/ventas/cancelar_venta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ venta_id: parseInt(id, 10) })
            })
                .then(r => r.json())
                .then(resp => {
                    alert(resp.mensaje || (resp.success ? 'Venta cancelada' : 'Error'));
                    if (resp.success) location.reload();
                })
                .catch(() => alert('Error al cancelar la venta'));
        });
    }

});

// Long-poll: refrescar historial de ventas cuando haya cambios
(function iniciarLongPollVentas(){
    let since = 0;
    async function tick(){
        try {
            const resp = await fetch('../../api/ventas/listen_cambios.php?since=' + since, { cache: 'no-store' });
            const data = await resp.json();
            if (data && typeof data.version !== 'undefined') since = parseInt(data.version) || since;
            if (data && data.changed) {
                try { await cargarHistorial(); } catch(_){}
            }
        } catch (e) {
            // no romper el ciclo por errores transitorios
        } finally {
            setTimeout(tick, 1500);
        }
    }
    // arrancar tras la carga inicial
    document.addEventListener('DOMContentLoaded', () => setTimeout(tick, 1500));
})();

async function abrirDetalleMovimientos() {
    try {
        const resp = await fetch('../../api/corte_caja/listar_movimientos.php', { credentials: 'include', cache: 'no-store' });
        const data = await resp.json();
        if (!data.success) {
            alert(data.mensaje || data.error || 'No se pudieron obtener los movimientos');
            return;
        }
        const movs = (data.resultado && data.resultado.movimientos) || [];
        const tbody = document.querySelector('#tablaMovimientos tbody');
        if (tbody) {
            tbody.innerHTML = '';
            movs.forEach(m => {
                const tr = document.createElement('tr');
                const tipo = (m.tipo || '').charAt(0).toUpperCase() + (m.tipo || '').slice(1);
                tr.innerHTML = `
                    <td>${m.fecha || ''}</td>
                    <td>${tipo}</td>
                    <td>$${Number(m.monto || 0).toFixed(2)}</td>
                    <td>${m.motivo || ''}</td>
                    <td>${m.usuario || ''}</td>`;
                tbody.appendChild(tr);
            });
        }
        showModal('#modalMovimientos');
    } catch (e) {
        console.error(e);
        alert('Error al obtener movimientos');
    }
}

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-entregar')) {
        const id = e.target.dataset.id;
        fetch('../../api/ventas/cambiar_estado_producto.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ detalle_id: id, estado: 'entregado' })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Producto marcado como entregado');
                    if (ventaIdActual) {
                        verDetalles(ventaIdActual);
                        cargarHistorial();
                    }
                } else {
                    alert(data.mensaje || 'Error al actualizar estado');
                }
            })
            .catch(() => alert('Error al actualizar estado'));
    }
});
