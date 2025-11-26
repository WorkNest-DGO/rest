(() => {
  const API = {
    listar: '../../api/facturas/listar.php',
    listar_cliente: '../../api/facturas/listar_cliente.php',
    crear: '../../api/facturas/crear.php',
    cancelar: '../../api/facturas/cancelar.php',
    enviar: '../../api/facturas/enviar.php',
    cliente_guardar: '../../api/facturas/cliente_guardar.php',
  };

  const el = (sel) => document.querySelector(sel);
  const fmt = (n) => {
    const v = Number(n || 0);
    return v.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
  };

  let seleccion = []; // array de ticket_ids
  let pendientesData = [];
  let pendientesIndex = new Map(); // ticket_id -> row
  let facturadasData = [];
  let formaPagoState = { codigo: null, tipo: null, tarjeta: null, mixto: false, hayTarjeta: false };
  let clientesCache = [];
  let facturaActualId = null;
  let facturaActualCorreo = '';

  function extraerIdsFacturas(res) {
    const ids = [];
    if (Array.isArray(res?.facturas)) {
      res.facturas.forEach(f => {
        const id = Number(f?.factura_id ?? f?.id ?? 0);
        if (id > 0) ids.push(id);
      });
    }
    const fid = Number(res?.factura_id ?? 0);
    if (fid > 0) ids.push(fid);
    return Array.from(new Set(ids));
  }

  async function enviarFacturasPorCorreo(ids) {
    const lista = Array.isArray(ids) ? ids : [];
    if (!lista.length) return;
    const errores = [];
    for (const id of lista) {
      try {
        const res = await fetch(API.enviar, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ factura_id: id })
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.mensaje || 'No se pudo enviar');
      } catch (e) {
        console.error('Error enviando factura', id, e);
        errores.push({ id, error: e.message });
      }
    }
    if (errores.length) {
      alert('Algunas facturas no se pudieron enviar: ' + errores.map(x => `#${x.id}: ${x.error}`).join('; '));
    }
  }
  // Paginación de pendientes
  let pendPage = 1;
  let pendPageSize = 20;
  let pendTotal = 0;

  const fpInfo = el('#forma-pago-info');
  const fpAyuda = el('#forma-pago-ayuda');
  const fpSelect = el('#forma-pago-select');
  const fpSelectWrap = el('#forma-pago-select-wrap');

  function setDefaultsFechas() {
    const now = new Date();
    const desde = new Date(now.getFullYear(), now.getMonth(), 1);
    // Para la UI mostramos el último día del mes, pero al consultar usaremos hasta exclusivo (+1 día)
    const hastaUi = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    el('#filtro-desde').value = desde.toISOString().slice(0, 10);
    el('#filtro-hasta').value = hastaUi.toISOString().slice(0, 10);
  }

  async function cargarClientes() {
    try {
      const url = new URL(API.listar_cliente, window.location.href);
      // Opcional: puedes pasar ?buscar= y paginar
      url.searchParams.set('limit', '200');
      const res = await fetch(url.toString());
      const j = await res.json();
      const sel = el('#select-cliente');
      sel.innerHTML = '<option value="">Seleccione...</option>';
      clientesCache = Array.isArray(j.resultado?.clientes) ? j.resultado.clientes : [];
      for (const c of clientesCache) {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = (c.nombre || '') + (c.rfc ? ` :" ${c.rfc}` : '');
        sel.appendChild(opt);
      }
      toggleEditarCliente();
    } catch (e) {
      console.error(e);
      alert('No fue posible cargar clientes');
    }
  }

  function getFiltros() {
    const desde = el('#filtro-desde').value;
    const hasta = el('#filtro-hasta').value;
    const buscar = el('#filtro-buscar').value.trim();
    const sede = el('#filtro-sede').value.trim();
    return { desde, hasta, buscar, sede };
  }

  const normTipoPago = (tp) => String(tp || '').trim().toLowerCase();
  const etiquetaFormaPago = (code) => {
    const c = String(code || '').trim();
    if (c === '01') return '01 - Efectivo';
    if (c === '03') return '03 - Transferencia';
    if (c === '04') return '04 - T. Crédito';
    if (c === '28') return '28 - T. Débito';
    return c;
  };
  function obtenerFormaPagoParaEnvio() {
    const payload = {};
    const tarjetaSel = fpSelect ? fpSelect.value : null;
    if (formaPagoState && formaPagoState.codigo) payload.forma_pago = formaPagoState.codigo;
    if (formaPagoState && formaPagoState.hayTarjeta && tarjetaSel) payload.forma_pago_tarjeta = tarjetaSel;
    return payload;
  }

  function updateAccionesState() {
    const hasSel = seleccion.length > 0;
    const cliente = Number(el('#select-cliente').value || 0);
    const bloquearGlobal = formaPagoState?.mixto || (hasSel && !formaPagoState?.codigo);
    el('#btn-uno-a-uno').disabled = !(hasSel && cliente > 0);
    el('#btn-global').disabled = !(hasSel && cliente > 0) || bloquearGlobal;
    toggleEditarCliente();
  }

  function renderPendientes(rows) {
    pendientesData = rows || [];
    pendientesIndex.clear();
    const tb = el('#tabla-pendientes tbody');
    tb.innerHTML = '';
    for (const r of pendientesData) {
      pendientesIndex.set(Number(r.id), r);
      const tr = document.createElement('tr');
      const folio = r.folio || r.id;
      tr.innerHTML = `
        <td>${folio ?? ''}</td>
        <td>${(r.fecha || '').replace('T',' ')}</td>
        <td>${r.tipo_pago ?? ''}</td>
        <td class="right">${fmt(r.total)}</td>
        <td class="right"><input type="checkbox" data-id="${r.id}"></td>
      `;
      const chk = tr.querySelector('input[type="checkbox"]');
      chk.checked = seleccion.includes(Number(r.id));
      chk.addEventListener('change', () => toggleSeleccion(Number(r.id)));
      tb.appendChild(tr);
    }
  }

  function renderPendientesPaginacion() {
    const cont = el('#pend-paginacion');
    if (!cont) return;
    const total = Number(pendTotal || 0);
    const size = Number(pendPageSize || 20);
    const pages = Math.max(1, Math.ceil(total / size));
    const page = Math.min(Math.max(1, Number(pendPage || 1)), pages);
    pendPage = page;
    const prevDisabled = page <= 1 ? 'disabled' : '';
    const nextDisabled = page >= pages ? 'disabled' : '';
    cont.innerHTML = `
      <div class="d-flex align-items-center" style="gap:8px;">
        <button type="button" class="btn custom-btn" id="pend-prev" ${prevDisabled}>Anterior</button>
        <span>Página ${page} de ${pages}</span>
        <button type="button" class="btn custom-btn" id="pend-next" ${nextDisabled}>Siguiente</button>
      </div>
    `;
    const prev = el('#pend-prev');
    const next = el('#pend-next');
    if (prev) prev.addEventListener('click', () => { if (pendPage > 1) { pendPage--; listar(); } });
    if (next) next.addEventListener('click', () => { const pgs = Math.max(1, Math.ceil((pendTotal||0)/(pendPageSize||20))); if (pendPage < pgs) { pendPage++; listar(); } });
  }

  function actualizarFormaPagoUI() {
    const tipos = seleccion.map(id => normTipoPago(pendientesIndex.get(id)?.tipo_pago));
    const uniq = Array.from(new Set(tipos.filter(Boolean)));
    const hayTarjeta = tipos.some(tp => tp === 'tarjeta' || tp === 'boucher');
    const tarjetaSel = hayTarjeta && fpSelect ? (fpSelect.value || '04') : null;
    const state = { codigo: null, tipo: null, tarjeta: tarjetaSel, mixto: false, hayTarjeta };
    let label = 'Selecciona tickets';
    let ayuda = 'Se toma del tipo de pago guardado en el ticket.';
    if (uniq.length === 1) {
      state.tipo = uniq[0];
      if (state.tipo === 'efectivo') { state.codigo = '01'; label = etiquetaFormaPago(state.codigo); }
      else if (state.tipo === 'cheque') { state.codigo = '03'; label = etiquetaFormaPago(state.codigo); }
      else if (state.tipo === 'tarjeta' || state.tipo === 'boucher') {
        state.codigo = tarjetaSel || '04';
        label = `${etiquetaFormaPago(state.codigo)} (tarjeta)`;
        ayuda = 'Tipo de pago del ticket: tarjeta. Elige crédito o débito.';
      } else {
        label = 'Tipo de pago no identificado';
        ayuda = 'Se enviará transferencia por defecto.';
      }
    } else if (uniq.length > 1) {
      state.mixto = true;
      label = 'Varios tipos de pago';
      ayuda = 'Para factura global selecciona tickets con el mismo tipo de pago.';
      if (hayTarjeta && tarjetaSel) ayuda += ' Para los tickets con tarjeta se usará tu selección.';
    }
    formaPagoState = state;
    if (fpSelectWrap) fpSelectWrap.style.display = hayTarjeta ? 'block' : 'none';
    if (hayTarjeta && fpSelect && tarjetaSel) fpSelect.value = tarjetaSel;
    if (fpInfo) fpInfo.value = label;
    if (fpAyuda) fpAyuda.textContent = ayuda;
  }

  function renderSeleccion() {
    const cont = el('#lista-seleccion');
    cont.innerHTML = '';
    let subtotal = 0, impuestos = 0, total = 0;
    for (const id of seleccion) {
      const r = pendientesIndex.get(id);
      const item = document.createElement('div');
      item.className = 'item';
      const tipoTxt = r?.tipo_pago ? ` (${r.tipo_pago})` : '';
      item.textContent = `Ticket ${id} - ${fmt(r?.total || 0)}${tipoTxt}`;
      item.title = 'Click para quitar';
      item.style.cursor = 'pointer';
      item.addEventListener('click', () => toggleSeleccion(id));
      cont.appendChild(item);
      const t = Number(r?.total || 0);
      subtotal += t; total += t;
    }
    el('#totales-subtotal').textContent = fmt(subtotal);
    el('#totales-impuestos').textContent = fmt(impuestos);
    el('#totales-total').textContent = fmt(total);
    actualizarFormaPagoUI();
    updateAccionesState();
  }

function renderFacturadas(rows) {
    facturadasData = rows || [];
    const tb = el('#tabla-facturadas tbody');
    tb.innerHTML = '';
    for (const r of facturadasData) {
      const tr = document.createElement('tr');
      const estado = r.status || 'emitida';
      const ticketsCnt = Number(r.tickets_cnt || 0);
      tr.innerHTML = `
        <td>${r.folio ?? r.factura_id}</td>
        <td>${(r.fecha || '').replace('T',' ')}</td>
        <td>${r.cliente ?? r.razon_social ?? r.cliente_id ?? ''}</td>
        <td class="right">${fmt(r.total)}</td>
        <td class="right">${ticketsCnt}</td>
        <td><span class="tag">${estado}</span></td>
        <td class="actions">
          <button data-accion="ver" data-id="${r.factura_id}">Ver</button>
          <button data-accion="cancelar" data-id="${r.factura_id}" ${String(estado).toLowerCase().startsWith('cancel') ? 'disabled' : ''}>Cancelar</button>
        </td>
      `;
      tr.querySelector('[data-accion="ver"]').addEventListener('click', () => verFactura(Number(r.factura_id)));
      tr.querySelector('[data-accion="cancelar"]').addEventListener('click', () => cancelarFactura(Number(r.factura_id)));
      tb.appendChild(tr);
    }
  }

  function toggleSeleccion(ticketId) {
    ticketId = Number(ticketId);
    const idx = seleccion.indexOf(ticketId);
    if (idx >= 0) {
      seleccion.splice(idx, 1);
    } else {
      seleccion.push(ticketId);
    }
    // Sync checkboxes state
    document.querySelectorAll('#tabla-pendientes input[type="checkbox"]').forEach(chk => {
      const id = Number(chk.getAttribute('data-id'));
      chk.checked = seleccion.includes(id);
    });
    renderSeleccion();
  }

  async function listar() {
    try {
      const { desde, hasta, buscar, sede } = getFiltros();
      const url = new URL(API.listar, window.location.href);
      if (desde) url.searchParams.set('desde', desde);
      // El API usa rango [desde, hasta) (hasta exclusivo). Si el usuario pone una fecha "Hasta"
      // queremos incluir ese día completo, por lo que enviamos hasta + 1 día.
      if (hasta) {
        const d = new Date(`${hasta}T00:00:00`);
        d.setDate(d.getDate() + 1);
        url.searchParams.set('hasta', d.toISOString().slice(0, 10));
      }
      url.searchParams.set('estado', 'todas');
      if (buscar) url.searchParams.set('buscar', buscar);
      if (sede) url.searchParams.set('sede_id', sede);
      // Paginación de pendientes
      const size = Number(el('#pend-records')?.value || pendPageSize || 20);
      pendPageSize = size;
      const offset = (Math.max(1, pendPage) - 1) * size;
      url.searchParams.set('pend_limit', String(size));
      url.searchParams.set('pend_offset', String(offset));
      url.searchParams.set('pend_count', '1');
      const res = await fetch(url.toString());
      const j = await res.json();
      if (!j.success) throw new Error(j.mensaje || 'Fallo en listar');
      renderPendientes(j.resultado?.pendientes || []);
      pendTotal = Number(j.resultado?.pendientes_total ?? 0);
      renderPendientesPaginacion();
      renderFacturadas(j.resultado?.facturadas || []);
      renderSeleccion();
    } catch (e) {
      console.error(e);
      alert('Error al listar: ' + e.message);
    }
  }

  async function facturarUnoAUno() {
    const clienteId = Number(el('#select-cliente').value || 0);
    if (!clienteId || seleccion.length === 0) return;
    try {
      const fpPayload = obtenerFormaPagoParaEnvio();
      const body = { modo: 'uno_a_uno', tickets: seleccion.slice(), cliente_id: clienteId };
      if (fpPayload.forma_pago) body.forma_pago = fpPayload.forma_pago;
      if (fpPayload.forma_pago_tarjeta) body.forma_pago_tarjeta = fpPayload.forma_pago_tarjeta;
      const res = await fetch(API.crear, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.mensaje || 'No se pudo crear');
      const ids = extraerIdsFacturas(j.resultado);
      alert('Facturas creadas: ' + (ids.length || j.resultado?.facturas?.length || 0));
      await enviarFacturasPorCorreo(ids);
      seleccion = [];
      await listar();
    } catch (e) {
      console.error(e);
      alert('Error al facturar 1:1: ' + e.message);
    }
  }

  async function facturarGlobal() {
    const clienteId = Number(el('#select-cliente').value || 0);
    if (!clienteId || seleccion.length === 0) return;
    if (formaPagoState?.mixto) {
      alert('Selecciona tickets con el mismo tipo de pago para factura global.');
      return;
    }
    const { desde, hasta } = getFiltros();
    try {
      const fpPayload = obtenerFormaPagoParaEnvio();
      if (!fpPayload.forma_pago && formaPagoState?.codigo) fpPayload.forma_pago = formaPagoState.codigo;
      if (!fpPayload.forma_pago) {
        alert('No se pudo determinar la forma de pago desde los tickets seleccionados.');
        return;
      }
      const body = { modo: 'global', tickets: seleccion.slice(), cliente_id: clienteId, periodo: { desde, hasta }, forma_pago: fpPayload.forma_pago };
      if (fpPayload.forma_pago_tarjeta) body.forma_pago_tarjeta = fpPayload.forma_pago_tarjeta;
      const res = await fetch(API.crear, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.mensaje || 'No se pudo crear');
      const ids = extraerIdsFacturas(j.resultado);
      alert('Factura global creada: ID ' + (ids[0] || j.resultado?.factura_id || ''));
      await enviarFacturasPorCorreo(ids);
      seleccion = [];
      await listar();
    } catch (e) {
      console.error(e);
      alert('Error al facturar global: ' + e.message);
    }
  }

  async function verFactura(id) {
    try {
      const url = new URL(API.listar, window.location.href);
      url.searchParams.set('factura_id', String(id));
      const res = await fetch(url.toString());
      const j = await res.json();
      if (!j.success) throw new Error(j.mensaje || 'No se pudo obtener detalle');
      facturaActualId = id;
      facturaActualCorreo = (j.resultado?.correo || '').trim();
      // Set download links
      const base = new URL('../../api/facturas/descargar.php', window.location.href).toString();
      el('#btn-desc-xml').setAttribute('href', base + '?factura_id=' + encodeURIComponent(String(id)) + '&tipo=xml');
      el('#btn-desc-pdf').setAttribute('href', base + '?factura_id=' + encodeURIComponent(String(id)) + '&tipo=pdf');
      const dets = j.resultado?.detalles || [];
      const tks = j.resultado?.tickets || [];
      const cont = el('#detalle-contenido');
      cont.innerHTML = '';
      const sum = dets.reduce((acc, d) => acc + Number(d.importe || (d.cantidad * d.precio_unitario) || 0), 0);
      const head = document.createElement('div');
      const clienteTxt = j.resultado?.cliente ? ` :" Cliente: ${j.resultado.cliente}` : '';
      const correoTxt = facturaActualCorreo ? ` :" Correo: ${facturaActualCorreo}` : '';
      head.innerHTML = `<p><strong>Factura #${id}</strong> :" Tickets: ${tks.map(x => x.ticket_id).join(', ') || '(n/d)'} :" Total calc: ${fmt(sum)}${clienteTxt}${correoTxt}</p>`;
      cont.appendChild(head);
      const table = document.createElement('table');
      table.innerHTML = `
        <thead><tr>
          <th>Producto</th><th>Descripción</th><th class="right">Cantidad</th><th class="right">P.Unit</th><th class="right">Importe</th>
        </tr></thead><tbody></tbody>`;
      const tb = table.querySelector('tbody');
      for (const d of dets) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${d.producto_id ?? ''}</td>
          <td>${d.nombre ?? d.descripcion ?? ''}</td>
          <td class="right">${Number(d.cantidad || 0)}</td>
          <td class="right">${fmt(d.precio_unitario)}</td>
          <td class="right">${fmt(d.importe ?? (Number(d.cantidad||0)*Number(d.precio_unitario||0)))}</td>
        `;
        tb.appendChild(tr);
      }
      cont.appendChild(table);
      el('#modal-detalle').style.display = 'flex';
    } catch (e) {
      console.error(e);
      alert('Error al obtener detalle: ' + e.message);
    }
  }

  async function reenviarFactura() {
    if (!facturaActualId) {
      alert('Abre primero una factura para reenviar.');
      return;
    }
    const correo = prompt('Correo destino', facturaActualCorreo || '');
    if (correo === null) return;
    const correoTrim = correo.trim();
    if (!correoTrim) {
      alert('Debes capturar un correo destino');
      return;
    }
    try {
      const res = await fetch(API.enviar, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ factura_id: facturaActualId, correo: correoTrim })
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.mensaje || 'No se pudo reenviar');
      facturaActualCorreo = correoTrim;
      alert('Factura enviada a ' + (j.resultado?.enviado_a || correoTrim));
    } catch (e) {
      console.error(e);
      alert('Error al reenviar: ' + e.message);
    }
  }

  function toggleEditarCliente() {
    const btnEdit = el('#btn-editar-cliente');
    if (!btnEdit) return;
    const tieneSeleccion = Number(el('#select-cliente')?.value || 0) > 0;
    btnEdit.disabled = !tieneSeleccion;
  }

  function abrirModalCliente(modo = 'nuevo') {
    const titulo = modo === 'editar' ? 'Editar cliente de facturaci�n' : 'Nuevo cliente de facturaci�n';
    const modal = el('#modal-cliente');
    if (!modal) return;
    el('#modal-cliente-titulo').textContent = titulo;
    const selId = Number(el('#select-cliente')?.value || 0);
    const cliente = clientesCache.find(c => Number(c.id) === selId);
    el('#cliente-id').value = modo === 'editar' && cliente ? cliente.id : '';
    el('#cliente-rfc').value = cliente?.rfc || '';
    el('#cliente-razon').value = cliente?.nombre || cliente?.razon_social || '';
    el('#cliente-correo').value = cliente?.correo || '';
    el('#cliente-telefono').value = cliente?.telefono || '';
    el('#cliente-cp').value = cliente?.cp || '';
    el('#cliente-regimen').value = cliente?.regimen || '';
    el('#cliente-uso').value = cliente?.uso_cfdi || '';
    el('#cliente-calle').value = cliente?.calle || '';
    el('#cliente-numero-ext').value = cliente?.numero_ext || '';
    el('#cliente-numero-int').value = cliente?.numero_int || '';
    el('#cliente-colonia').value = cliente?.colonia || '';
    el('#cliente-municipio').value = cliente?.municipio || '';
    el('#cliente-estado').value = cliente?.estado || '';
    el('#cliente-pais').value = cliente?.pais || '';
    modal.style.display = 'flex';
  }

  function cerrarModalCliente() {
    const modal = el('#modal-cliente');
    if (modal) modal.style.display = 'none';
  }

  async function guardarCliente() {
    const id = Number(el('#cliente-id').value || 0);
    const payload = {
      accion: id > 0 ? 'editar' : 'crear',
      id: id > 0 ? id : undefined,
      rfc: el('#cliente-rfc').value.trim(),
      razon_social: el('#cliente-razon').value.trim(),
      correo: el('#cliente-correo').value.trim(),
      telefono: el('#cliente-telefono').value.trim(),
      cp: el('#cliente-cp').value.trim(),
      regimen: el('#cliente-regimen').value.trim(),
      uso_cfdi: el('#cliente-uso').value.trim(),
      calle: el('#cliente-calle').value.trim(),
      numero_ext: el('#cliente-numero-ext').value.trim(),
      numero_int: el('#cliente-numero-int').value.trim(),
      colonia: el('#cliente-colonia').value.trim(),
      municipio: el('#cliente-municipio').value.trim(),
      estado: el('#cliente-estado').value.trim(),
      pais: el('#cliente-pais').value.trim(),
    };
    if (!payload.rfc || !payload.razon_social) {
      alert('RFC y Raz�n social son obligatorios');
      return;
    }
    try {
      const res = await fetch(API.cliente_guardar, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.mensaje || 'No se pudo guardar');
      const newId = j.resultado?.id || id;
      await cargarClientes();
      el('#select-cliente').value = newId;
      updateAccionesState();
      cerrarModalCliente();
      alert('Cliente guardado');
    } catch (e) {
      console.error(e);
      alert('Error al guardar cliente: ' + e.message);
    }
  }

      async function cancelarFactura(id) {
    if (!confirm("¿Cancelar la factura #" + id + "?")) return;
    const baseBody = { factura_id: id, motivo: "Cancelación solicitada desde módulo masivo", usuario_id: 1, type: "issued", motive: "02" };
    try {
      let res = await fetch(API.cancelar, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(baseBody)
      });
      let j;
      try { j = await res.json(); } catch(_) { j = { success:false, mensaje: "Respuesta no válida del servidor" }; }
      if (!j.success) {
        const msg = String(j.mensaje || "No se pudo cancelar");
        if (/Facturama|PAC|HTTP\s*500|método http/i.test(msg)) {
          const ok = confirm(msg + "\n\n¿Forzar cancelación local para liberar tickets?");
          if (ok) {
            const res2 = await fetch(API.cancelar, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ ...baseBody, force: true })
            });
            const j2 = await res2.json();
            if (!j2.success) throw new Error(j2.mensaje || "No se pudo cancelar (forzado)");
            alert("Factura cancelada (forzado).");
            await listar();
            return;
          }
        }
        throw new Error(msg);
      }
      alert("Factura cancelada");
      await listar();
    } catch (e) {
      console.error(e);
      alert("Error al cancelar: " + e.message);
    }
  }  // Eventos iniciales
  window.addEventListener('DOMContentLoaded', () => {
    setDefaultsFechas();
    listar();
    cargarClientes();
    el('#btn-buscar').addEventListener('click', () => { pendPage = 1; listar(); });
    const sel = el('#pend-records'); if (sel) sel.addEventListener('change', () => { pendPage = 1; pendPageSize = Number(sel.value||20); listar(); });
    el('#select-cliente').addEventListener('change', updateAccionesState);
    const btnNuevoCli = el('#btn-nuevo-cliente');
    if (btnNuevoCli) btnNuevoCli.addEventListener('click', () => abrirModalCliente('nuevo'));
    const btnEditCli = el('#btn-editar-cliente');
    if (btnEditCli) btnEditCli.addEventListener('click', () => abrirModalCliente('editar'));
    const btnCerrarCli = el('#btn-cerrar-modal-cliente');
    if (btnCerrarCli) btnCerrarCli.addEventListener('click', cerrarModalCliente);
    const btnGuardarCli = el('#btn-guardar-cliente');
    if (btnGuardarCli) btnGuardarCli.addEventListener('click', guardarCliente);
    el('#modal-cliente')?.addEventListener('click', (ev) => { if (ev.target.id === 'modal-cliente') ev.currentTarget.style.display = 'none'; });
    if (fpSelect) fpSelect.addEventListener('change', () => { actualizarFormaPagoUI(); updateAccionesState(); });
    el('#btn-uno-a-uno').addEventListener('click', facturarUnoAUno);
    el('#btn-global').addEventListener('click', facturarGlobal);
    el('#btn-cerrar-modal').addEventListener('click', () => el('#modal-detalle').style.display = 'none');
    // Cerrar modal al hacer click fuera
    el('#modal-detalle').addEventListener('click', (ev) => { if (ev.target.id === 'modal-detalle') ev.currentTarget.style.display = 'none'; });
    el('#btn-reenviar-factura')?.addEventListener('click', reenviarFactura);
  });

  // Exponer helpers si se requiere en consola
  window.listar = listar;
  window.toggleSeleccion = toggleSeleccion;
  window.facturarUnoAUno = facturarUnoAUno;
  window.facturarGlobal = facturarGlobal;
  window.verFactura = verFactura;
  window.cancelarFactura = cancelarFactura;
  window.reenviarFactura = reenviarFactura;
})();
