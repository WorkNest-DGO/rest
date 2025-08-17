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
                const accion = v.estatus !== 'cancelada'
                    ? `<button class=\"btn custom-btn btn-cancelar\" data-id=\"${id}\">Cancelar</button>`
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
                    <td>${id}</td>
                    <td>${fechaMostrar}</td>
                    <td>${v.total}</td>
                    <td>${v.tipo_entrega}</td>
                    <td>${destino || ''}</td>
                    <td>${v.estatus}</td>
                    <td>${entregado}</td>
                    <td><button class=\"btn custom-btn btn-detalle\" data-id=\"${id}\" data-toggle=\"modal\" data-target=\"#modal-detalles\">Ver detalles</button></td>
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
        prev.className = 'btn btn-secondary me-1';
        prev.addEventListener('click', () => cargarHistorial(page - 1));
        cont.appendChild(prev);
    }
    for (let i = 1; i <= total; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        btn.className = 'btn btn-secondary me-1';
        if (i === page) btn.disabled = true;
        btn.addEventListener('click', () => cargarHistorial(i));
        cont.appendChild(btn);
    }
    if (page < total) {
        const next = document.createElement('button');
        next.textContent = 'Siguiente';
        next.className = 'btn btn-secondary';
        next.addEventListener('click', () => cargarHistorial(page + 1));
        cont.appendChild(next);
    }
}

const usuarioId = window.usuarioId || 1; // ID del cajero proveniente de la sesión
const sedeId = window.sedeId || 1;
let corteIdActual = window.corteId || null;
let catalogo = [];
let productos = [];
let ventasData = {};
let repartidores = [];
let ticketRequests = [];
let ventaIdActual = null;
let mesas = [];

// ==== [INICIO BLOQUE valida: validación para cierre de corte] ====
const VENTAS_URL = typeof API_LISTAR_VENTAS !== 'undefined' ? API_LISTAR_VENTAS : '../../api/ventas/listar_ventas.php';
const MESAS_URL  = typeof API_LISTAR_MESAS  !== 'undefined' ? API_LISTAR_MESAS  : '../../api/mesas/listar_mesas.php';

// Devuelve { hayVentasActivas, hayMesasOcupadas, bloqueado }
async function hayBloqueosParaCerrarCorte() {
  const [ventasResp, mesasResp] = await Promise.all([
    fetch(VENTAS_URL, { cache: 'no-store' }).then(r => r.json()),
    fetch(MESAS_URL,  { cache: 'no-store' }).then(r => r.json())
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
function padLeft(txt, w)  { const s = String(txt); return (repeat(' ', w) + s).slice(-w); }
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
  const pad = (n)=> String(n).padStart(2,'0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
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
  const boucher  = r.boucher  || {};
  const cheque   = r.cheque   || {};

  const totalProductos = Number(r.total_productos || 0);
  const totalPropinas  = Number(r.total_propinas  || 0);
  const totalEsperado  = Number(r.totalEsperado   || 0);
  const fondo          = Number(r.fondo           || 0);
  const totalDepositos = Number(r.total_depositos || 0);
  const totalRetiros   = Number(r.total_retiros   || 0);
  const totalFinal     = Number(r.totalFinal      || 0);
  const dif            = totalFinal - totalEsperado;

  const totalMeseros   = Array.isArray(r.total_meseros) ? r.total_meseros : [];
  const totalRapido    = Number(r.total_rapido || 0);
  const totalRepart    = Array.isArray(r.total_repartidor) ? r.total_repartidor : [];

  const desglose       = Array.isArray(r.desglose) ? r.desglose : [];
  const desgEfectivo   = desglose.filter(x => (x.tipo_pago || '').toLowerCase() === 'efectivo' && Number(x.denominacion) > 0);
  const desgNoEf       = desglose.filter(x => (x.tipo_pago || '').toLowerCase() !== 'efectivo');

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
    const folText = `Folios: ${folInicio}–${folFin} (${folCount})`;
    out += lineKV(folText, '') + '\n';
  }
  out += '\n';

  // Totales por forma de pago (efectivo/boucher/cheque si existen)
  out += '-- Totales por forma de pago ' + repeat('-', TKT_WIDTH - 27) + '\n';
  const printFP = (label, obj) => {
    if (!obj || (obj.productos == null && obj.propina == null && obj.total == null)) return;
    const prod = money(obj.productos || 0);
    const prop = money(obj.propina   || 0);
    const tot  = money(obj.total     || 0);
    out += lineKV(padRight(label, 12) + ' Prod:', padLeft(prod, 12)) + '\n';
    out += lineKV(padRight('', 12)    + ' Prop:', padLeft(prop, 12)) + '\n';
    out += lineKV(padRight('', 12)    + ' TOTAL:', padLeft(tot, 12)) + '\n';
  };
  printFP('Efectivo', efectivo);
  printFP('Boucher',  boucher);
  printFP('Cheque',   cheque);
  out += '\n';

  // Conciliación
  out += repeat('-', TKT_WIDTH) + '\n';
  out += lineKV('Total productos:',  padLeft(money(totalProductos), 14)) + '\n';
  out += lineKV('Total propinas:',   padLeft(money(totalPropinas), 14)) + '\n';
  out += lineKV('Total esperado:',   padLeft(money(totalEsperado), 14)) + '\n';
  out += lineKV('Fondo inicial:',    padLeft(money(fondo), 14)) + '\n';
  out += lineKV('Depósitos:',        padLeft(money(totalDepositos), 14)) + '\n';
  out += lineKV('Retiros:',          padLeft(money(totalRetiros), 14)) + '\n';
  out += repeat('-', TKT_WIDTH) + '\n';
  out += lineKV('Conteo (total final):', padLeft(money(totalFinal), 14)) + '\n';
  const difLabel = 'DIF (Conteo - Esperado):';
  out += lineKV(difLabel, padLeft(money(dif), 14)) + '\n';
  out += repeat('-', TKT_WIDTH) + '\n\n';

  // Meseros
  if (totalMeseros.length) {
    out += '-- Ventas por mesero ' + repeat('-', TKT_WIDTH - 22) + '\n';
    totalMeseros.forEach(m => {
      const name = String(m.nombre || '').slice(0, 24);
      const val  = money(Number(m.total || 0));
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
      const val  = money(Number(x.total || 0));
      out += lineKV(padRight(name, 28), padLeft(val, 12)) + '\n';
    });
    out += '\n';
  }

  // Desglose
  out += '-- Desglose de denominaciones ' + repeat('-', TKT_WIDTH - 29) + '\n';
  if (desgEfectivo.length) {
    out += '[EFECTIVO]\n';
    desgEfectivo.forEach(x => {
      const den  = Number(x.denominacion || 0);
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
  window.open('../../api/corte_caja/imprime_corte.php?datos='+ JSON.stringify(resultado)+'&detalle='+JSON.stringify(cajero));
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
  const btnP  = document.getElementById('btnImprimirCorte');
  if (btnP) btnP.addEventListener('click', ()=> { window.print(); });
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
}

function habilitarCobro() {
    document.querySelectorAll('#formVenta input, #formVenta select, #formVenta button')
        .forEach(el => {
            if (!el.closest('#controlCaja')) {
                el.disabled = false;
            }
        });
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
      cont.innerHTML = `<button class="btn custom-btn" id="btnCerrarCorte">Cerrar corte</button> <button id="btnCorteTemporal" class="btn btn-warning">Corte Temporal</button>`;
      const btnCerrar = document.getElementById('btnCerrarCorte');
      if (btnCerrar) {
        btnCerrar.addEventListener('click', (ev) => {
          ev.preventDefault();
          validarYAbrirModalCierre();
        });
      }
      document.getElementById('btnCorteTemporal').addEventListener('click', abrirCorteTemporal);
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
        body.className = 'modal-body';

        const label = document.createElement('label');
        label.textContent = 'Monto de apertura:';

        const input = document.createElement('input');
        input.type = 'number';
        input.id = 'montoApertura';
        input.className = 'form-control';

        body.appendChild(label);
        body.appendChild(input);

        const footer = document.createElement('div');
        footer.className = 'modal-footer';

        const btnAbrir = document.createElement('button');
        btnAbrir.id = 'btnAbrirCajaModal';
        btnAbrir.className = 'btn custom-btn';
        btnAbrir.textContent = 'Abrir Caja';

        const btnCancelar = document.createElement('button');
        btnCancelar.className = 'btn btn-secondary';
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
            style.textContent = '#modalAbrirCaja .modal-footer{display:flex;justify-content:flex-end;gap:0.5rem;}';
            document.head.appendChild(style);
        }

        btnCancelar.addEventListener('click', () => hideModal(modal));
    }

    const inputMonto = modal.querySelector('#montoApertura');
    if (fondoData.existe) {
        inputMonto.value = fondoData.monto;
        inputMonto.readOnly = true;
    } else {
        inputMonto.value = '';
        inputMonto.readOnly = false;
    }

    const btnAbrir = modal.querySelector('#btnAbrirCajaModal');
    btnAbrir.onclick = async () => {
        const monto = inputMonto.value.trim();
        if (monto === '' || isNaN(parseFloat(monto))) {
            alert('Debes indicar un monto');
            return;
        }
        if (!fondoData.existe) {
            await fetch('../../api/corte_caja/guardar_fondo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ usuario_id: usuarioId, monto: parseFloat(monto) })
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
        const resumen = await resumenResp.json();

        if (!resumen.success || !resumen.resultado || resumen.resultado.num_ventas === 0) {
            alert("No hay ventas registradas en este corte.");
            return;
        }

        mostrarModalDesglose(resumen);
    } catch (error) {
        console.error("Error al obtener resumen:", error);
        alert("Ocurrió un error inesperado al consultar el corte.");
    }
}

function abrirCorteTemporal() {
    fetch('../../api/corte_caja/resumen_corte_actual.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Error al obtener datos del corte.');
                return;
            }
            const r = data.resultado;
            document.getElementById('corteTemporalDatos').innerHTML = generarHTMLCorte(r);
            showModal('#modalCorteTemporal');
            document.getElementById('guardarCorteTemporal').onclick = function () {
                guardarCorteTemporal(r);
            };
        });
}

function generarHTMLCorte(r) {
    let html = '<table class="table"><tbody>';
    for (const [key, val] of Object.entries(r)) {
        let displayVal = val;
        if (key === 'efectivo' && val && typeof val === 'object') {
            const productos = val.productos ?? 0;
            const propina = val.propina ?? 0;
            const total = val.total ?? 0;
            displayVal = `Efectivo - Productos: ${productos}, Propina: ${propina}, Total: ${total}`;
        } else if (key === 'total_meseros' && Array.isArray(val)) {
            displayVal = val.map(m => `${m.nombre}: ${m.total}`).join('<br>');
        } else if (key === 'total_repartidor' && Array.isArray(val)) {
            displayVal = val.map(rp => `${rp.nombre}: ${rp.total}`).join('<br>');
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
    window.open('../../api/corte_caja/imprime_corte_temp.php?datos='+ JSON.stringify(datos));

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
    const metodosPago = ['efectivo', 'boucher', 'cheque'];

    // Usa los totales del API si vienen; si no, calcula con metodosPago
    const totalProductos = Number.parseFloat(r.total_productos) ||
      metodosPago.reduce((acc, m) => acc + (Number.parseFloat(r[m]?.productos) || 0), 0);

    const totalPropinas = Number.parseFloat(r.total_propinas) ||
      metodosPago.reduce((acc, m) => acc + (Number.parseFloat(r[m]?.propina) || 0), 0);

    const totalEsperado  = Number.parseFloat(r.totalEsperado) || (totalProductos + totalPropinas);
    const fondoInicial   = Number.parseFloat(r.fondo) || 0;
    const totalIngresado = Number.parseFloat(r.totalFinal) || (totalEsperado + fondoInicial);

    const modal = document.getElementById('modalDesglose');
    const body = modal.querySelector('.modal-body');
    let html = '<div class="bg-dark text-white p-3 border">';
    html += '<h3>Desglose de caja</h3>';
    // Datos de cabecera del corte (si existen)
    if (r.fecha_inicio)  { html += `<p>Fecha inicio: ${r.fecha_inicio}</p>`; }
    if (r.folio_inicio != null) { html += `<p>Folio inicio: ${r.folio_inicio}</p>`; }
    if (r.folio_fin != null)    { html += `<p>Folio fin: ${r.folio_fin}</p>`; }
    if (r.total_folios != null) { html += `<p>Total folios: ${r.total_folios}</p>`; }
  
    html += `<p>Total esperado: $${totalEsperado.toFixed(2)}</p>`;
    html += '<p>Fondo inicial: $<strong id="lblFondo"></strong></p>';
    html += '<p>Depósitos: $<strong id="lblTotalDepositos"></strong></p>';
    html += '<p>Retiros: $<strong id="lblTotalRetiros"></strong></p>';
    html += `<p>Total ingresado: $${totalIngresado.toFixed(2)}</p>`;
    html += `<p>Total productos: $${totalProductos.toFixed(2)}</p>`;
        html += '<p>Totales por tipo de pago:</p><ul>';
    metodosPago.forEach(tipo => {
        const p = r[tipo] || {};
        html += `<li>${tipo}: $${(Number.parseFloat(p.total) || 0).toFixed(2)}</li>`;
    });
    html += '</ul>';
    html += `<p>Total propinas: $${totalPropinas.toFixed(2)}</p>`;
    html += '<p>Propinas por tipo de pago:</p><ul>';
    
    metodosPago.forEach(tipo => {
        const p = r[tipo] || {};
        html += `<li>${tipo}: $${(Number.parseFloat(p.propina) || 0).toFixed(2)}</li></ul>`;
    });
    


    // Listado de ventas por mesero (si existe)
    if (Array.isArray(r.total_meseros) && r.total_meseros.length) {
      html += '<h4>Ventas por mesero</h4><ul>';
      r.total_meseros.forEach(m => {
        const nombre = (m?.nombre ?? '').toString();
        const total  = Number.parseFloat(m?.total) || 0;
        html += `<li>${nombre}: $${total.toFixed(2)}</li>`;
      });
      html += '</ul>';
    }

    // Totales por repartidor (si existe)
    if (Array.isArray(r.total_repartidor) && r.total_repartidor.length) {
      html += '<h4>Total por repartidor</h4><ul>';
      r.total_repartidor.forEach(x => {
        const nombre = (x?.nombre ?? '').toString();
        const total  = Number.parseFloat(x?.total) || 0;
        html += `<li>${nombre}: $${total.toFixed(2)}</li>`;
      });
      html += '</ul>';
    }


    html += '<div id="camposDesglose"></div>';
    html += '<p>Efectivo contado: $<span id="totalEfectivo">0.00</span> | Dif.: $<span id="difIngresado">0.00</span></p>';
    html += '<button class="btn custom-btn" id="guardarDesglose">Guardar</button> <button class="btn custom-btn" id="cancelarDesglose" data-dismiss="modal">Cancelar</button>';
    html += '</div>';
    body.innerHTML = html;
    showModal('#modalDesglose');

    document.getElementById('lblFondo').textContent = fondoInicial.toFixed(2);
    document.getElementById('lblTotalDepositos').textContent = (Number.parseFloat(r.total_depositos) || 0).toFixed(2);
    document.getElementById('lblTotalRetiros').textContent = (Number.parseFloat(r.total_retiros) || 0).toFixed(2);

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
        document.getElementById('difIngresado').textContent = (totalIngresado - totalEfectivo).toFixed(2);
    }

    modal.querySelectorAll('.cantidad').forEach(inp => inp.addEventListener('input', calcular));
    calcular();

    modal.querySelector('#cancelarDesglose').addEventListener('click', () => {
        hideModal('#modalDesglose');
        habilitarCobro();
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
        const resp = await fetch('../../api/corte_caja/cerrar_corte.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ corte_id: corteIdActual, usuario_id: usuarioId, observaciones: '' })
        });
        const data = await resp.json();
        if (data.success) {
            corteIdActual = null;
            alert('Caja cerrada correctamente');
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
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar repartidores');
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

async function cargarMesas() {
    try {
        const resp = await fetch('../../api/mesas/mesas.php');
        const data = await resp.json();
        if (data.success) {
            mesas = data.resultado;
            const select = document.getElementById('mesa_id');
            select.innerHTML = '<option value="">Seleccione</option>';
            mesas.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.nombre;
                select.appendChild(opt);
            });
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

async function cargarUsuariosPorRol(rol = 'repartidor') {
  try {
    const resp = await fetch('../../api/usuarios/listar_usuarios.php');
    const data = await resp.json();
    const select = document.getElementById('usuario_id');
    if (!select) return;
    select.innerHTML = '<option value="">--Selecciona--</option>';

    if (data && data.success) {
      (data.resultado || [])
        .filter(u => String(u.rol || '').toLowerCase() === String(rol).toLowerCase())
        .forEach(u => {
          const opt = document.createElement('option');
          opt.value = u.id;
          opt.textContent = u.nombre;
          select.appendChild(opt);
        });
    } else {
      console.warn(data?.mensaje || 'No se pudieron cargar usuarios.');
    }
  } catch (e) {
    console.error('Error al cargar usuarios:', e);
  }
}

function esRepartidorCasaSeleccionado() {
  const sel = document.getElementById('repartidor_id');
  if (!sel || sel.selectedIndex < 0) return false;
  const txt = sel.options[sel.selectedIndex].textContent || '';
  return txt.trim().toLowerCase() === 'repartidor casa';
}

async function actualizarSelectorUsuario() {
  const tipo = (document.getElementById('tipo_entrega')?.value || '').toLowerCase();
  const usuarioSel = document.getElementById('usuario_id');
  if (!usuarioSel) return;

  if (tipo === 'domicilio') {
    usuarioSel.disabled = false;
    if (esRepartidorCasaSeleccionado()) {
      setLabelUsuario('Usuario:');
      await cargarUsuariosPorRol('repartidor');
    } else {
      setLabelUsuario('Mesero:');
      await cargarMeseros();
    }
  } else if (tipo === 'mesa') {
    setLabelUsuario('Mesero:');
    usuarioSel.disabled = true;
    if (typeof asignarMeseroPorMesa === 'function') {
      asignarMeseroPorMesa();
    }
  } else { // 'rapido' u otros
    setLabelUsuario('Mesero:');
    usuarioSel.disabled = false;
    await cargarMeseros();
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
        meseroSelect.disabled = true;
        return;
    }
    const mesa = mesas.find(m => m.id === mesaId);
    if (!mesa) {
        meseroSelect.value = '';
        meseroSelect.disabled = true;
        return;
    }
    if (!mesa.usuario_id) {
        alert('La mesa seleccionada no tiene mesero asignado. Contacta al administrador.');
        mesaSelect.value = '';
        meseroSelect.value = '';
        meseroSelect.disabled = true;
        verificarActivacionProductos();
        return;
    }
    meseroSelect.innerHTML = `<option value="${mesa.usuario_id}">${mesa.mesero_nombre}</option>`;
    meseroSelect.value = mesa.usuario_id;
    meseroSelect.disabled = true;
}

async function cargarProductos() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            catalogo = data.resultado;
            productos = data.resultado;
            const selects = document.querySelectorAll('#productos select.producto');
            selects.forEach(select => {
                select.innerHTML = '<option value="">--Selecciona--</option>';
                catalogo.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nombre;
                    opt.dataset.precio = p.precio;
                    opt.dataset.existencia = p.existencia;
                    select.appendChild(opt);
                });
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
                });
            });
            document.querySelectorAll('#productos .cantidad').forEach(inp => {
                const select = inp.closest('tr').querySelector('.producto');
                inp.addEventListener('input', () => {
                    manejarCantidad(inp, select);
                    validarInventario();
                });
            });
            validarInventario();
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

function manejarCantidad(input, select) {
    let val = parseInt(input.value) || 0;
    if (val === 0) {
        const quitar = confirm('Cantidad es 0. ¿Quitar producto?');
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
    });
    tbody.appendChild(nueva);
    const select = nueva.querySelector('.producto');
    select.innerHTML = '<option value="">--Selecciona--</option>';
    catalogo.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nombre;
        opt.dataset.precio = p.precio;
        opt.dataset.existencia = p.existencia;
        select.appendChild(opt);
    });
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
    });
    const cantidadInput = nueva.querySelector('.cantidad');
    cantidadInput.value = '';
    cantidadInput.addEventListener('input', () => {
        manejarCantidad(cantidadInput, select);
        validarInventario();
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
    const usuario_id = parseInt(document.getElementById('usuario_id').value);
    const observacion = document.getElementById('observacion').value.trim();
    const filas = document.querySelectorAll('#productos tbody tr');
    const productos = [];

    filas.forEach(fila => {
        const producto_id = parseInt(fila.querySelector('.producto').value);
        const cantidad = parseInt(fila.querySelector('.cantidad').value);
        if (!isNaN(producto_id) && !isNaN(cantidad)) {
            const precioInput = fila.querySelector('.precio');
            const precio_unitario = parseFloat(precioInput.dataset.unitario || 0);
            if (precio_unitario > 0) {
                productos.push({ producto_id, cantidad, precio_unitario });
            }
        }
    });

    if (!validarInventario()) {
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
    } else if (tipo !== 'rapido') {
        alert('Tipo de entrega inválido');
        return;
    }

    const payload = {
        tipo,
        mesa_id: tipo === 'mesa' ? mesa_id : null,
        repartidor_id: tipo === 'domicilio' ? repartidor_id : null,
        usuario_id,
        observacion: (tipo === 'domicilio' || tipo === 'rapido') ? observacion : '',
        productos,
        corte_id: corteIdActual,
        sede_id: sedeId
    };

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
            alert('Venta registrada');
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

  if (tipoEntrega) tipoEntrega.value = '';         // vuelve al estado neutro
  if (mesa) mesa.value = '';
  if (rep) rep.value = '';
  if (mesero) { mesero.disabled = false; mesero.value = ''; }
  if (obs) obs.value = '';

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
    // limpia inputs y precio unitario cacheado
    fila.querySelectorAll('input').forEach(inp => {
      inp.value = '';
      if (inp.classList.contains('precio')) delete inp.dataset.unitario;
    });
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
  if (tipoEntrega) tipoEntrega.focus();
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
            const destino = info.tipo_entrega === 'mesa'
                ? info.mesa
                : info.tipo_entrega === 'domicilio'
                    ? info.repartidor
                    : 'Venta rápida';
            let html = `<h3>Detalle de venta</h3>
                        <p>Tipo: ${info.tipo_entrega}<br>Destino: ${destino}<br>Mesero: ${info.mesero}</p>`;
            html += `<table border="1"><thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th>Estatus</th><th></th></tr></thead><tbody>`;
            info.productos.forEach(p => {
                const btnEliminar = p.estado_producto !== 'entregado'
                    ? `<button class="btn custom-btn delDetalle" data-id="${p.id}">Eliminar</button>`
                    : '';
                const btnEntregar = p.estado_producto === 'listo'
                    ? ` <button class="btn btn-success btn-entregar" data-id="${p.id}">Entregar</button>`
                    : '';
                const est = (p.estado_producto || '').replace('_', ' ');
                html += `<tr><td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td>${p.subtotal}</td><td>${est}</td>` +
                        `<td>${btnEliminar}${btnEntregar}</td></tr>`;
            });
            html += `</tbody></table>`;
            if (info.foto_entrega) {
                html += `<p>Evidencia:<br><img src="../../uploads/evidencias/${info.foto_entrega}" width="300"></p>`;
            }
            html += `<h4>Agregar producto</h4>`;
            html += `<select id="detalle_producto"></select>`;
            html += `<input type="number" id="detalle_cantidad" value="1" min="1">`;
            html += `<button class="btn custom-btn" id="addDetalle">Agregar</button>`;
            html += ` <button class="btn custom-btn" id="imprimirTicket">Imprimir ticket</button> <button  class="btn custom-btn" id="cerrarDetalle" data-dismiss="modal">Cerrar</button>`;

            contenedor.innerHTML = html;
            showModal('#modal-detalles');

            const selectProd = document.getElementById('detalle_producto');
            selectProd.innerHTML = '<option value="">--Selecciona--</option>';
            catalogo.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.nombre;
                opt.dataset.precio = p.precio;
                opt.dataset.existencia = p.existencia;
                selectProd.appendChild(opt);
            });
            const cantDetalle = document.getElementById('detalle_cantidad');
            selectProd.addEventListener('change', () => {
                const exist = selectProd.selectedOptions[0].dataset.existencia;
                if (exist) {
                    cantDetalle.max = exist;
                } else {
                    cantDetalle.removeAttribute('max');
                }
            });

            contenedor.querySelectorAll('.delDetalle').forEach(btn => {
                btn.addEventListener('click', () => eliminarDetalle(btn.dataset.id, id));
            });
            document.getElementById('addDetalle').addEventListener('click', () => agregarDetalle(id));
            document.getElementById('cerrarDetalle').addEventListener('click', () => {
                hideModal('#modal-detalles');
            });
            document.getElementById('imprimirTicket').addEventListener('click', () => {
                const venta = ventasData[id] || {};
                const total = venta.total || info.productos.reduce((s, p) => s + parseFloat(p.subtotal), 0);
                let sede = venta.sede_id || sedeId;
                if (!venta.sede_id) {
                    const entrada = prompt('Indica sede', sedeId);
                    if (entrada) sede = parseInt(entrada) || sede;
                }
                const payload = {
                    venta_id: parseInt(id),
                    usuario_id: venta.usuario_id || 1,
                    fecha: venta.fecha || '',
                    productos: info.productos,
                    total,
                    sede_id: sede
                };
                localStorage.setItem('ticketData', JSON.stringify(payload));
                imprimirTicket(id);
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
    }
}

async function eliminarDetalle(detalleId, ventaId) {
    if (!confirm('¿Eliminar producto?')) return;
    try {
        const resp = await fetch('../../api/mesas/eliminar_producto_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ detalle_id: parseInt(detalleId) })
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
        alert('Error al eliminar');
    }
}

async function agregarDetalle(ventaId) {
    const select = document.getElementById('detalle_producto');
    const cantidad = parseInt(document.getElementById('detalle_cantidad').value);
    const productoId = parseInt(select.value);
    const prod = catalogo.find(p => parseInt(p.id) === productoId);
    const precio = prod ? parseFloat(prod.precio) : 0;
    if (isNaN(productoId) || isNaN(cantidad) || cantidad <= 0) {
        alert('Producto o cantidad inválida');
        return;
    }
    if (prod && cantidad > parseInt(prod.existencia)) {
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
                tr.innerHTML = `<td>⚠️ ${req.nombre}</td><td><button class="btn custom-btn" data-action="imprimir-ticket" data-venta-id="${req.venta_id}" data-mesa-id="${req.id}">Imprimir</button></td>`;
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
                productos: info.productos,
                total,
                sede_id: sede
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
    if (campoObservacion) campoObservacion.style.display = (tipo === 'domicilio' || tipo === 'rapido') ? 'block' : 'none';

    actualizarSelectorUsuario();
  });
}

// Detecta cambios en mesa o repartidor
document.getElementById('mesa_id').addEventListener('change', () => {
  asignarMeseroPorMesa();
  verificarActivacionProductos();
});
// Listener repartidor_id: cada vez que cambie, recalcula si es "Repartidor casa"
const repartidorEl = document.getElementById('repartidor_id');
if (repartidorEl) {
  repartidorEl.addEventListener('change', actualizarSelectorUsuario);
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
    document.getElementById('registrarVenta').addEventListener('click', registrarVenta);
    document.getElementById('agregarProducto').addEventListener('click', agregarFilaProducto);
    actualizarSelectorUsuario();

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

    // Delegación de eventos con JavaScript puro para botones dinámicos
    const cancelModal = document.getElementById('cancelVentaModal');
    const confirmCancelBtn = document.getElementById('confirmCancelVenta');

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-detalle')) {
            const id = e.target.dataset.id;
            verDetalles(id);
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