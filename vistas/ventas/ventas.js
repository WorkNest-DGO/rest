if (Array.isArray(window.catalogoDenominaciones) && catalogoDenominaciones.length > 0) {
    console.log('Denominaciones cargadas:', catalogoDenominaciones);
} else {
    console.error('Error al cargar denominaciones');
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
                    <td><button class=\"btn custom-btn btn-detalle\" data-id=\"${id}\">Ver detalles</button></td>
                    <td>${accion}</td>
                `;
                tbody.appendChild(row);
            });
            renderPagination(data.resultado.total_paginas, data.resultado.pagina_actual);
        } else {
            alert(data.mensaje);
        }
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
let modalMovimientoCaja;

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
      corteIdActual = data.resultado.corte_id;
      cont.innerHTML = `<button class="btn custom-btn" id="btnCerrarCaja">Cerrar caja</button> <button id="btnCorteTemporal" class="btn btn-warning">Corte Temporal</button>`;
      document.getElementById('btnCerrarCaja').addEventListener('click', cerrarCaja);
      document.getElementById('btnCorteTemporal').addEventListener('click', abrirCorteTemporal);
      habilitarCobro();
    } else {
      cont.innerHTML = `<button class="btn custom-btn" id="btnAbrirCaja">Abrir caja</button>`;
      document.getElementById('btnAbrirCaja').addEventListener('click', abrirCaja);
      deshabilitarCobro();
    }
  });

}

async function abrirCaja() {
    let fondoData = await fetch('../../api/corte_caja/consultar_fondo.php?usuario_id=' + usuarioId)
        .then(r => r.json());
    let monto = fondoData.existe ? fondoData.monto : prompt('Indica fondo de caja:');
    if (monto === null || monto === '' || isNaN(parseFloat(monto))) {
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
            await verificarCorte();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al abrir caja');
    }
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
            document.getElementById('modalCorteTemporal').style.display = 'block';
            document.getElementById('guardarCorteTemporal').onclick = function () {
                guardarCorteTemporal(r);
            };
        });
}

function generarHTMLCorte(r) {
    let html = '<table class="table"><tbody>';
    for (const [key, val] of Object.entries(r)) {
        html += `<tr><td>${key}</td><td>${val}</td></tr>`;
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
                document.getElementById('modalCorteTemporal').style.display = 'none';
            } else {
                alert('Error al guardar corte temporal.');
            }
        });
}

function imprimirCorteTemporal(datos) {
    const win = window.open('', '_blank', 'width=600,height=800');
    if (!win) {
        console.error('No fue posible abrir la ventana de impresión');
        return;
    }
    win.document.write('<html><head><title>Corte Temporal</title>');
    win.document.write('<style>table{border-collapse:collapse;width:100%;}td,th{border:1px solid #000;padding:4px;font-family:monospace;font-size:12px;}</style>');
    win.document.write('</head><body>');
    win.document.write('<h2>Corte Temporal</h2>');
    win.document.write('<table><tbody>');
    for (const k in datos) {
        const v = datos[k];
        if (typeof v === 'object') {
            win.document.write(`<tr><th colspan="2">${k}</th></tr>`);
            for (const k2 in v) {
                const v2 = typeof v[k2] === 'object' ? JSON.stringify(v[k2]) : v[k2];
                win.document.write(`<tr><td>${k2}</td><td>${v2}</td></tr>`);
            }
        } else {
            win.document.write(`<tr><td>${k}</td><td>${v}</td></tr>`);
        }
    }
    win.document.write('</tbody></table>');
    win.document.write('</body></html>');
    win.document.close();
    win.print();
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
    let html = '<div style="background:#000;border:1px solid #333;padding:10px;">';
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
    html += '<button class="btn custom-btn" id="guardarDesglose">Guardar</button> <button id="cancelarDesglose">Cancelar</button>';
    html += '</div>';
    modal.innerHTML = html;
    modal.style.display = 'block';

    document.getElementById('lblFondo').textContent = fondoInicial.toFixed(2);
    document.getElementById('lblTotalDepositos').textContent = (Number.parseFloat(r.total_depositos) || 0).toFixed(2);
    document.getElementById('lblTotalRetiros').textContent = (Number.parseFloat(r.total_retiros) || 0).toFixed(2);

    if (!Array.isArray(catalogoDenominaciones) || !catalogoDenominaciones.length) {
        console.error('Error al cargar denominaciones');
        modal.querySelector('#cancelarDesglose').addEventListener('click', () => {
            modal.style.display = 'none';
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
        modal.style.display = 'none';
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
                // Imprimir resumen y desglose en nueva ventana
                imprimirResumenDesglose(r, detalle);
                modal.style.display = 'none';
                await finalizarCorte();
            } else {
                alert(data.mensaje || 'Error al guardar desglose');
            }
        } catch (err) {
            console.error(err);
            alert('Error al guardar desglose');
        }
    });
}

function imprimirResumenDesglose(resumen, desglose) {
    const data = { ...resumen, desglose };
    const html = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    const win = window.open('', '_blank');
    if (win) {
        win.document.write(html);
        win.document.close();
        win.focus();
        win.print();
    } else {
        console.error('No fue posible abrir ventana para impresión');
    }
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

async function cancelarVenta(id) {
    if (!confirm('¿Seguro de cancelar la venta?')) return;
    try {
        const resp = await fetch('../../api/ventas/cancelar_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(id) })
        });
        const data = await resp.json();
        if (data.success) {
            alert('Venta cancelada');
            await cargarHistorial();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cancelar la venta');
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
            const contenedor = document.getElementById('modal-detalles');
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
            html += ` <button class="btn custom-btn" id="imprimirTicket">Imprimir ticket</button> <button id="cerrarDetalle">Cerrar</button>`;

            contenedor.innerHTML = html;
            contenedor.style.display = 'block';

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
                contenedor.style.display = 'none';
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
    modalMovimientoCaja.show();
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
            modalMovimientoCaja.hide();
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

    document.getElementById('closeModalCorteTemporal').addEventListener('click', () => {
        document.getElementById('modalCorteTemporal').style.display = 'none';
    });

    document.getElementById('recordsPerPage').addEventListener('change', e => {
        limit = parseInt(e.target.value);
        cargarHistorial(1);
    });

    document.getElementById('buscadorVentas').addEventListener('input', e => {
        searchQuery = e.target.value.trim();
        cargarHistorial(1);
    });

    modalMovimientoCaja = new bootstrap.Modal(document.getElementById('modalMovimientoCaja'));
    document.getElementById('btnDeposito').addEventListener('click', () => abrirModalMovimiento('deposito'));
    document.getElementById('btnRetiro').addEventListener('click', () => abrirModalMovimiento('retiro'));
    document.getElementById('guardarMovimiento').addEventListener('click', guardarMovimientoCaja);

    // Delegación de eventos con jQuery para botones dinámicos
$(document).on('click', '.btn-detalle', function () {
        const id = $(this).data('id');
        verDetalles(id);
    });

    $(document).on('click', '.btn-cancelar', function () {
        const id = $(this).data('id');
        if (!confirm('¿Seguro de cancelar la venta?')) return;
        $.ajax({
            url: '../../api/ventas/cancelar_venta.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({ venta_id: parseInt(id) })
        }).done(function (resp) {
            alert(resp.mensaje || (resp.success ? 'Venta cancelada' : 'Error'));
            location.reload();
        }).fail(function () {
            alert('Error al cancelar la venta');
        });
    });

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