(() => {
  const API = {
    listar: '../../api/facturas/listar.php',
    listar_cliente: '../../api/facturas/listar_cliente.php',
    crear: '../../api/facturas/crear.php',
    cancelar: '../../api/facturas/cancelar.php',
    enviar: '../../api/facturas/enviar.php',
    cliente_guardar: '../../api/facturas/cliente_guardar.php',
    ticket_detalle: '../../api/facturas/ticket_detalle.php',
  };
  const SAT_JSON_URL = '../../config/sat_catalogos_4_0.json';

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
  let ticketEdicion = null;
  let ticketEditCache = { ticketId: null, allowed: false, data: null };
  let clientesCache = [];
  let facturaActualId = null;
  let facturaActualCorreo = '';
  let facturaActualClienteId = null;
  let catRegimenes = [];
  let catUsos = [];
  const catUsosPorRegimen = new Map();
  let catCatalogosPromise = null;
  let satCatalogosRaw = null;

  const normalizarTexto = (str) => (str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const textoCliente = (cli) => {
    if (!cli) return '';
    const nombre = (cli.nombre || cli.razon_social || '').trim();
    const rfc = (cli.rfc || '').trim();
    return [nombre, rfc].filter(Boolean).join(' - ');
  };

  function limpiarSugerenciasClientes() {
    const lista = el('#lista-clientes');
    if (lista) {
      lista.innerHTML = '';
      lista.style.display = 'none';
    }
  }

  function syncClienteSeleccionadoInput() {
    const input = el('#buscador-cliente');
    if (!input) return;
    const selId = Number(el('#select-cliente')?.value || 0);
    const cli = clientesCache.find(c => Number(c.id) === selId);
    input.value = cli ? textoCliente(cli) : '';
  }

  function setClienteSeleccionado(cli) {
    const sel = el('#select-cliente');
    if (sel) {
      if (cli) {
        let opt = sel.querySelector(`option[value="${cli.id}"]`);
        if (!opt) {
          opt = document.createElement('option');
          opt.value = cli.id;
          opt.textContent = textoCliente(cli);
          sel.appendChild(opt);
        }
        sel.value = cli.id;
      } else {
        sel.value = '';
      }
      sel.dispatchEvent(new Event('change'));
    }
    const input = el('#buscador-cliente');
    if (input) input.value = cli ? textoCliente(cli) : '';
    limpiarSugerenciasClientes();
  }

  function renderSugerenciasClientes(term) {
    const lista = el('#lista-clientes');
    if (!lista) return;
    lista.innerHTML = '';
    const q = normalizarTexto(term || '');
    if (!q) {
      lista.style.display = 'none';
      return;
    }
    const coincidencias = clientesCache.filter(c => {
      const nombre = normalizarTexto(c.nombre || c.razon_social || '');
      const rfc = normalizarTexto(c.rfc || '');
      return nombre.includes(q) || rfc.includes(q);
    }).slice(0, 30);
    coincidencias.forEach(c => {
      const li = document.createElement('li');
      li.textContent = textoCliente(c) || `Cliente ${c.id}`;
      li.dataset.id = c.id;
      li.addEventListener('click', () => setClienteSeleccionado(c));
      lista.appendChild(li);
    });
    lista.style.display = coincidencias.length ? 'block' : 'none';
  }

  function renderRegimenesSat() {
    const sel = el('#cliente-regimen');
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">Seleccione...</option>' + catRegimenes.map(r => `<option value="${r.code}">${r.code} - ${r.descripcion}</option>`).join('');
    if (current) sel.value = current;
  }

  function renderUsosSat(allowed) {
    const sel = el('#cliente-uso');
    if (!sel) return;
    const useAllowed = Array.isArray(allowed);
    const allowSet = new Set(useAllowed ? allowed : catUsos.map(u => u.code));
    const current = sel.value;
    sel.innerHTML = '<option value="">Seleccione...</option>' + catUsos.filter(u => allowSet.has(u.code)).map(u => `<option value="${u.code}">${u.code} - ${u.descripcion}</option>`).join('');
    if (current && allowSet.has(current)) sel.value = current;
    const hint = el('#cliente-uso-hint');
    if (hint) hint.textContent = useAllowed && allowed.length ? `Usos permitidos para régimen seleccionado: ${allowed.join(', ')}` : '';
  }

  async function loadSatCatalogos() {
    if (satCatalogosRaw) return satCatalogosRaw;
    const res = await fetch(SAT_JSON_URL, { cache: 'no-store' });
    if (!res.ok) throw new Error('No se pudieron cargar catálogos SAT');
    let j;
    try { j = await res.json(); } catch (e) { throw new Error('Catálogos SAT JSON inválido'); }
    satCatalogosRaw = j || {};
    catRegimenes = Array.isArray(j.regimenes) ? j.regimenes : [];
    catUsos = Array.isArray(j.usos) ? j.usos : [];
    catUsosPorRegimen.clear();
    if (Array.isArray(j.compatibilidad)) {
      j.compatibilidad.forEach(row => {
        if (!row || !row.regimen) return;
        catUsosPorRegimen.set(String(row.regimen), Array.isArray(row.usos) ? row.usos : []);
      });
    }
    return satCatalogosRaw;
  }

  async function cargarRegimenesSat() {
    await loadSatCatalogos();
    renderRegimenesSat();
  }

  async function cargarUsosSat() {
    await loadSatCatalogos();
    renderUsosSat();
  }

  async function cargarUsosPorRegimen(regimen) {
    const reg = (regimen || '').trim();
    await loadSatCatalogos();
    if (!reg) { renderUsosSat(); return; }
    if (catUsosPorRegimen.has(reg)) {
      renderUsosSat(catUsosPorRegimen.get(reg));
    } else {
      renderUsosSat();
    }
  }

  function onRegimenClienteChange() {
    const reg = el('#cliente-regimen')?.value || '';
    cargarUsosPorRegimen(reg).catch((e) => console.error(e));
  }

  function initCatalogosSat() {
    if (catCatalogosPromise) return catCatalogosPromise;
    catCatalogosPromise = Promise.all([cargarRegimenesSat(), cargarUsosSat()]).catch((e) => {
      console.error('No se pudieron cargar catálogos SAT', e);
    });
    return catCatalogosPromise;
  }

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
    if (!lista.length) return { enviados: [], errores: [] };
    const errores = [];
    const enviados = [];
    for (const id of lista) {
      try {
        const res = await fetch(API.enviar, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ factura_id: id })
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.mensaje || 'No se pudo enviar');
        enviados.push(id);
      } catch (e) {
        console.error('Error enviando factura', id, e);
        errores.push({ id, error: e.message });
      }
    }
    return { enviados, errores };
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
      const prev = sel ? sel.value : '';
      sel.innerHTML = '<option value="">Seleccione...</option>';
      clientesCache = Array.isArray(j.resultado?.clientes) ? j.resultado.clientes : [];
      for (const c of clientesCache) {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = textoCliente(c) || (c.id ? `Cliente ${c.id}` : '');
        sel.appendChild(opt);
      }
      if (prev) sel.value = prev;
      syncClienteSeleccionadoInput();
      const busc = el('#buscador-cliente');
      if (busc && busc.value.trim()) renderSugerenciasClientes(busc.value);
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
  const mostrarTipoPago = (tp) => {
    const n = normTipoPago(tp);
    if (n === 'boucher' || n === 'tarjeta') return 'tarjeta';
    if (n === 'cheque') return 'transferencia';
    return tp || '';
  };
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
    actualizarBotonEditarTicketState();
  }

  let processingLocked = false;
  function setProcessingModalState(opts) {
    const modal = el('#modal-procesando');
    if (!modal) return;
    const spinner = el('#procesando-spinner');
    const titulo = el('#procesando-titulo');
    const mensaje = el('#procesando-mensaje');
    const detalle = el('#procesando-detalle');
    const cerrar = el('#btn-cerrar-procesando');
    const show = opts && Object.prototype.hasOwnProperty.call(opts, 'show') ? !!opts.show : true;
    const loading = !!(opts && opts.loading);
    processingLocked = loading;
    modal.style.display = show ? 'flex' : 'none';
    if (spinner) spinner.style.display = loading ? 'block' : 'none';
    if (titulo && opts?.title) titulo.textContent = opts.title;
    if (mensaje && Object.prototype.hasOwnProperty.call(opts || {}, 'message')) {
      mensaje.textContent = opts.message || '';
    }
    if (detalle && Object.prototype.hasOwnProperty.call(opts || {}, 'detail')) {
      detalle.textContent = opts.detail || '';
    }
    if (cerrar) cerrar.style.display = opts?.closable ? 'inline-flex' : 'none';
  }

  const REPARTIDORES_EDITABLES = new Set([1, 2, 3]);

  async function cargarTicketDetalle(ticketId) {
    const url = new URL(API.ticket_detalle, window.location.href);
    url.searchParams.set('ticket_id', String(ticketId));
    const res = await fetch(url.toString(), { cache: 'no-store' });
    const j = await res.json();
    if (!j.success) throw new Error(j.mensaje || 'No se pudo obtener ticket');
    return j.resultado;
  }

  function limpiarEdicionTicketSiNoAplica() {
    if (!ticketEdicion) return;
    if (seleccion.length !== 1 || Number(seleccion[0]) !== Number(ticketEdicion.ticket_id)) {
      ticketEdicion = null;
    }
  }

  function actualizarBotonEditarTicketState() {
    const btn = el('#btn-editar-ticket');
    if (!btn) return;
    limpiarEdicionTicketSiNoAplica();
    if (seleccion.length !== 1) {
      btn.disabled = true;
      btn.title = 'Selecciona un solo ticket';
      return;
    }
    const ticketId = Number(seleccion[0]);
    if (ticketEditCache.ticketId === ticketId && ticketEditCache.data) {
      btn.disabled = !ticketEditCache.allowed;
      btn.title = ticketEditCache.allowed ? '' : 'Solo aplica para repartidor id 1,2,3';
      return;
    }
    ticketEditCache = { ticketId, allowed: false, data: null, loading: true };
    btn.disabled = true;
    btn.title = 'Validando ticket...';
    cargarTicketDetalle(ticketId).then((data) => {
      if (ticketEditCache.ticketId !== ticketId) return;
      const repId = Number(data?.ticket?.repartidor_id || 0);
      const allowed = REPARTIDORES_EDITABLES.has(repId);
      ticketEditCache = { ticketId, allowed, data, loading: false };
      btn.disabled = !allowed;
      btn.title = allowed ? '' : 'Solo aplica para repartidor id 1,2,3';
    }).catch((e) => {
      if (ticketEditCache.ticketId !== ticketId) return;
      ticketEditCache = { ticketId, allowed: false, data: null, loading: false };
      btn.disabled = true;
      btn.title = 'No se pudo validar ticket';
      console.error(e);
    });
  }

  function calcularTotalesEdicion() {
    const tb = el('#tabla-editar-ticket tbody');
    if (!tb) return;
    let subtotal = 0;
    tb.querySelectorAll('tr').forEach((tr) => {
      const qty = Number(tr.querySelector('[data-field="cantidad"]')?.value || 0);
      const price = Number(tr.querySelector('[data-field="precio_unitario"]')?.value || 0);
      const imp = qty * price;
      subtotal += imp;
      const cellImp = tr.querySelector('[data-field="importe"]');
      if (cellImp) cellImp.textContent = fmt(imp);
    });
    const subEl = el('#editar-ticket-subtotal');
    if (subEl) subEl.textContent = fmt(subtotal);
    const totalEl = el('#editar-ticket-total');
    if (totalEl) totalEl.textContent = fmt(subtotal);
  }

  function renderModalEditarTicket(data) {
    const ticket = data?.ticket || {};
    const dets = Array.isArray(data?.detalles) ? data.detalles : [];
    const info = el('#editar-ticket-info');
    if (info) {
      const folio = ticket.folio || ticket.id || '';
      info.textContent = `Ticket ${folio} (ID ${ticket.id})`;
    }
    const tb = el('#tabla-editar-ticket tbody');
    if (!tb) return;
    tb.innerHTML = '';
    dets.forEach((d) => {
      const tr = document.createElement('tr');
      tr.dataset.ticketDetalleId = d.ticket_detalle_id;
      tr.dataset.productoId = d.producto_id;
      tr.dataset.categoriaId = d.categoria_id ?? '';
      tr.innerHTML = `
        <td>${d.producto_id ?? ''}</td>
        <td><input type="text" data-field="descripcion" value="${String(d.descripcion || '').replace(/"/g, '&quot;')}"></td>
        <td class="right"><input type="number" step="1" min="0" data-field="cantidad" value="${Number(d.cantidad || 0)}" style="width:90px;"></td>
        <td class="right"><input type="number" step="0.01" min="0" data-field="precio_unitario" value="${Number(d.precio_unitario || 0)}" style="width:120px;"></td>
        <td class="right" data-field="importe">${fmt(Number(d.cantidad || 0) * Number(d.precio_unitario || 0))}</td>
      `;
      tr.querySelectorAll('input').forEach((inp) => {
        inp.addEventListener('input', calcularTotalesEdicion);
      });
      tb.appendChild(tr);
    });
    calcularTotalesEdicion();
    const modal = el('#modal-editar-ticket');
    if (modal) modal.style.display = 'flex';
  }

  async function aplicarEdicionTicket() {
    if (seleccion.length !== 1) {
      alert('Selecciona un solo ticket para editar.');
      return;
    }
    const ticketId = Number(seleccion[0]);
    const tb = el('#tabla-editar-ticket tbody');
    if (!tb) return;
    const items = [];
    let valido = true;
    tb.querySelectorAll('tr').forEach((tr) => {
      const descripcion = String(tr.querySelector('[data-field="descripcion"]')?.value || '').trim();
      const cantidad = Number(tr.querySelector('[data-field="cantidad"]')?.value || 0);
      const precio = Number(tr.querySelector('[data-field="precio_unitario"]')?.value || 0);
      if (!descripcion || cantidad <= 0 || precio < 0) {
        valido = false;
        return;
      }
      items.push({
        ticket_detalle_id: Number(tr.dataset.ticketDetalleId || 0),
        producto_id: Number(tr.dataset.productoId || 0),
        categoria_id: tr.dataset.categoriaId !== '' ? Number(tr.dataset.categoriaId) : null,
        descripcion,
        cantidad,
        precio_unitario: precio
      });
    });
    if (!valido || !items.length) {
      alert('Revisa cantidades y descripciones antes de aplicar.');
      return;
    }
    ticketEdicion = { ticket_id: ticketId, items };
    renderSeleccion();
    const modal = el('#modal-editar-ticket');
    if (modal) modal.style.display = 'none';
    const clienteId = Number(el('#select-cliente').value || 0);
    if (!clienteId) {
      alert('Selecciona un cliente para facturar.');
      return;
    }
    await facturarUnoAUno();
  }

  async function abrirModalEditarTicket() {
    if (seleccion.length !== 1) {
      alert('Selecciona un solo ticket para editar.');
      return;
    }
    const ticketId = Number(seleccion[0]);
    let data = null;
    try {
      if (ticketEditCache.ticketId === ticketId && ticketEditCache.data) {
        data = ticketEditCache.data;
      } else {
        data = await cargarTicketDetalle(ticketId);
        ticketEditCache = { ticketId, allowed: true, data, loading: false };
      }
    } catch (e) {
      console.error(e);
      alert('No se pudo cargar el ticket para editar.');
      return;
    }
    const repId = Number(data?.ticket?.repartidor_id || 0);
    if (!REPARTIDORES_EDITABLES.has(repId)) {
      alert('Solo aplica para repartidor id 1,2,3.');
      return;
    }
    let payload = data;
    if (ticketEdicion && Number(ticketEdicion.ticket_id) === ticketId) {
      const edits = new Map(ticketEdicion.items.map((it) => [Number(it.ticket_detalle_id), it]));
      payload = {
        ...data,
        detalles: Array.isArray(data.detalles)
          ? data.detalles.map((d) => {
            const edit = edits.get(Number(d.ticket_detalle_id));
            if (!edit) return d;
            return {
              ...d,
              descripcion: edit.descripcion ?? d.descripcion,
              cantidad: edit.cantidad ?? d.cantidad,
              precio_unitario: edit.precio_unitario ?? d.precio_unitario
            };
          })
          : []
      };
    }
    renderModalEditarTicket(payload);
  }

  function buildTicketEditPayload() {
    if (!ticketEdicion || seleccion.length !== 1) return null;
    const ticketId = Number(seleccion[0]);
    if (Number(ticketEdicion.ticket_id) !== ticketId) return null;
    return { ticket_id: ticketId, items: ticketEdicion.items };
  }

  function sumTicketEditItems(items) {
    const rows = Array.isArray(items) ? items : [];
    return rows.reduce((acc, item) => {
      const qty = Number(item?.cantidad || 0);
      const price = Number(item?.precio_unitario || 0);
      return acc + (qty * price);
    }, 0);
  }

  function getTicketEdicionTotal(ticketId) {
    if (!ticketEdicion) return null;
    if (Number(ticketEdicion.ticket_id) !== Number(ticketId)) return null;
    return sumTicketEditItems(ticketEdicion.items);
  }

  function renderPendientes(rows) {
    pendientesData = rows || [];
    pendientesIndex.clear();
    const tb = el('#tabla-pendientes tbody');
    tb.innerHTML = '';
    for (const r of pendientesData) {
      pendientesIndex.set(Number(r.id), r);
      const tr = document.createElement('tr');
      const sedeTxt = r.sede ?? r.sede_nombre ?? r.sede_id ?? r.sedeId ?? '';
      const folio = r.folio || r.id;
      tr.innerHTML = `
        <td>${sedeTxt ?? ''}</td>
        <td>${folio ?? ''}</td>
        <td>${(r.fecha || '').replace('T',' ')}</td>
        <td>${mostrarTipoPago(r.tipo_pago)}</td>
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
      const tipoTxt = r?.tipo_pago ? ` (${mostrarTipoPago(r.tipo_pago)})` : '';
      const editTotal = getTicketEdicionTotal(id);
      const t = editTotal !== null ? editTotal : Number(r?.total || 0);
      const editTag = editTotal !== null ? ' (editado)' : '';
      item.textContent = `Ticket ${id} - ${fmt(t)}${tipoTxt}${editTag}`;
      item.title = 'Click para quitar';
      item.style.cursor = 'pointer';
      item.addEventListener('click', () => toggleSeleccion(id));
      cont.appendChild(item);
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
    const fpPayload = obtenerFormaPagoParaEnvio();
    const body = { modo: 'uno_a_uno', tickets: seleccion.slice(), cliente_id: clienteId };
    const editPayload = buildTicketEditPayload();
    if (editPayload) body.ticket_edit = editPayload;
    if (fpPayload.forma_pago) body.forma_pago = fpPayload.forma_pago;
    if (fpPayload.forma_pago_tarjeta) body.forma_pago_tarjeta = fpPayload.forma_pago_tarjeta;
    let j = null;
    try {
      setProcessingModalState({
        show: true,
        loading: true,
        title: 'Procesando factura',
        message: 'Procesando factura, espere por favor.',
        detail: '',
        closable: false
      });
      const res = await fetch(API.crear, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      j = await res.json();
    } catch (e) {
      setProcessingModalState({ show: false });
      console.error(e);
      alert('Error al facturar 1:1: ' + e.message);
      return;
    }
    if (!j?.success) {
      setProcessingModalState({ show: false });
      alert('Error al facturar 1:1: ' + (j?.mensaje || 'No se pudo crear'));
      return;
    }
    try {
      const ids = extraerIdsFacturas(j.resultado);
      const facturasCount = ids.length || j.resultado?.facturas?.length || 0;
      setProcessingModalState({
        show: true,
        loading: true,
        title: 'Factura creada',
        message: `Facturas creadas: ${facturasCount}`,
        detail: 'Enviando correos...',
        closable: false
      });
      const correoRes = await enviarFacturasPorCorreo(ids);
      const enviados = correoRes?.enviados?.length || 0;
      const errores = correoRes?.errores || [];
      const detalleCorreo = errores.length
        ? `Correos enviados: ${enviados}. Errores: ${errores.map(x => `#${x.id}: ${x.error}`).join('; ')}`
        : `Correos enviados: ${enviados}`;
      setProcessingModalState({
        show: true,
        loading: false,
        title: 'Factura creada',
        message: `Facturas creadas: ${facturasCount}`,
        detail: detalleCorreo,
        closable: true
      });
      seleccion = [];
      ticketEdicion = null;
      await listar();
    } catch (e) {
      setProcessingModalState({ show: false });
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
    const fpPayload = obtenerFormaPagoParaEnvio();
    if (!fpPayload.forma_pago && formaPagoState?.codigo) fpPayload.forma_pago = formaPagoState.codigo;
    if (!fpPayload.forma_pago) {
      alert('No se pudo determinar la forma de pago desde los tickets seleccionados.');
      return;
    }
    const body = { modo: 'global', tickets: seleccion.slice(), cliente_id: clienteId, periodo: { desde, hasta }, forma_pago: fpPayload.forma_pago };
    const editPayload = buildTicketEditPayload();
    if (editPayload) body.ticket_edit = editPayload;
    if (fpPayload.forma_pago_tarjeta) body.forma_pago_tarjeta = fpPayload.forma_pago_tarjeta;
    let j = null;
    try {
      setProcessingModalState({
        show: true,
        loading: true,
        title: 'Procesando factura',
        message: 'Procesando factura, espere por favor.',
        detail: '',
        closable: false
      });
      const res = await fetch(API.crear, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      j = await res.json();
    } catch (e) {
      setProcessingModalState({ show: false });
      console.error(e);
      alert('Error al facturar global: ' + e.message);
      return;
    }
    if (!j?.success) {
      setProcessingModalState({ show: false });
      alert('Error al facturar global: ' + (j?.mensaje || 'No se pudo crear'));
      return;
    }
    try {
      const ids = extraerIdsFacturas(j.resultado);
      const facturaId = ids[0] || j.resultado?.factura_id || '';
      setProcessingModalState({
        show: true,
        loading: true,
        title: 'Factura creada',
        message: `Factura global creada: ${facturaId}`,
        detail: 'Enviando correos...',
        closable: false
      });
      const correoRes = await enviarFacturasPorCorreo(ids);
      const enviados = correoRes?.enviados?.length || 0;
      const errores = correoRes?.errores || [];
      const detalleCorreo = errores.length
        ? `Correos enviados: ${enviados}. Errores: ${errores.map(x => `#${x.id}: ${x.error}`).join('; ')}`
        : `Correos enviados: ${enviados}`;
      setProcessingModalState({
        show: true,
        loading: false,
        title: 'Factura creada',
        message: `Factura global creada: ${facturaId}`,
        detail: detalleCorreo,
        closable: true
      });
      seleccion = [];
      ticketEdicion = null;
      await listar();
    } catch (e) {
      setProcessingModalState({ show: false });
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
      facturaActualClienteId = Number(j.resultado?.cliente_id ?? 0) || null;
      const correoDetalle = (j.resultado?.correo || '').trim();
      const correoCache = clientesCache.find(c => Number(c.id) === facturaActualClienteId)?.correo || '';
      facturaActualCorreo = correoDetalle || correoCache || '';
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
    const modal = el('#modal-reenviar');
    const input = el('#reenviar-correo');
    if (input) input.value = facturaActualCorreo || '';
    if (modal) {
      modal.style.display = 'flex';
      setTimeout(() => input?.focus(), 50);
    }
  }

  async function reenviarFacturaConfirmar() {
    if (!facturaActualId) {
      alert('Abre primero una factura para reenviar.');
      return;
    }
    const input = el('#reenviar-correo');
    const correoTrim = (input?.value || '').trim();
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
      cerrarModalReenviar();
    } catch (e) {
      console.error(e);
      alert('Error al reenviar: ' + e.message);
    }
  }

  function cerrarModalReenviar() {
    const modal = el('#modal-reenviar');
    if (modal) modal.style.display = 'none';
  }

  function toggleEditarCliente() {
    const btnEdit = el('#btn-editar-cliente');
    if (!btnEdit) return;
    const tieneSeleccion = Number(el('#select-cliente')?.value || 0) > 0;
    btnEdit.disabled = !tieneSeleccion;
  }

  async function abrirModalCliente(modo = 'nuevo') {
    const titulo = modo === 'editar' ? 'Editar cliente de facturación' : 'Nuevo cliente de facturación';
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
    await initCatalogosSat();
    el('#cliente-regimen').value = cliente?.regimen || '';
    await cargarUsosPorRegimen(el('#cliente-regimen')?.value || '').catch((e) => console.error(e));
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
      alert('RFC y Razón social son obligatorios');
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
      const cli = clientesCache.find(c => Number(c.id) === Number(newId));
      setClienteSeleccionado(cli || null);
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
    initCatalogosSat();
    el('#btn-buscar').addEventListener('click', () => { pendPage = 1; listar(); });
    const sel = el('#pend-records'); if (sel) sel.addEventListener('change', () => { pendPage = 1; pendPageSize = Number(sel.value||20); listar(); });
    const selectCliente = el('#select-cliente');
    if (selectCliente) selectCliente.addEventListener('change', () => { updateAccionesState(); syncClienteSeleccionadoInput(); });
    const buscadorCliente = el('#buscador-cliente');
    if (buscadorCliente) {
      buscadorCliente.addEventListener('input', () => {
        const term = buscadorCliente.value || '';
        if (!term.trim()) {
          setClienteSeleccionado(null);
          return;
        }
        renderSugerenciasClientes(term);
        if (selectCliente) selectCliente.value = '';
        updateAccionesState();
      });
      buscadorCliente.addEventListener('focus', () => {
        if (buscadorCliente.value.trim()) renderSugerenciasClientes(buscadorCliente.value);
      });
    }
    document.addEventListener('click', (ev) => {
      const cont = el('#selector-cliente');
      if (cont && !cont.contains(ev.target)) limpiarSugerenciasClientes();
    });
    const selRegimen = el('#cliente-regimen');
    if (selRegimen) selRegimen.addEventListener('change', onRegimenClienteChange);
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
    const btnEditarTicket = el('#btn-editar-ticket');
    if (btnEditarTicket) btnEditarTicket.addEventListener('click', abrirModalEditarTicket);
    const btnCerrarEditar = el('#btn-cerrar-modal-editar-ticket');
    if (btnCerrarEditar) btnCerrarEditar.addEventListener('click', () => { const m = el('#modal-editar-ticket'); if (m) m.style.display = 'none'; });
    const btnAplicarEditar = el('#btn-aplicar-edicion-ticket');
    if (btnAplicarEditar) btnAplicarEditar.addEventListener('click', aplicarEdicionTicket);
    el('#modal-editar-ticket')?.addEventListener('click', (ev) => { if (ev.target.id === 'modal-editar-ticket') ev.currentTarget.style.display = 'none'; });
    el('#btn-cerrar-modal').addEventListener('click', () => el('#modal-detalle').style.display = 'none');
    // Cerrar modal al hacer click fuera
    el('#modal-detalle').addEventListener('click', (ev) => { if (ev.target.id === 'modal-detalle') ev.currentTarget.style.display = 'none'; });
    el('#btn-reenviar-factura')?.addEventListener('click', reenviarFactura);
    el('#btn-reenviar-confirmar')?.addEventListener('click', reenviarFacturaConfirmar);
    el('#btn-cerrar-modal-reenviar')?.addEventListener('click', cerrarModalReenviar);
    el('#modal-reenviar')?.addEventListener('click', (ev) => { if (ev.target.id === 'modal-reenviar') ev.currentTarget.style.display = 'none'; });
    el('#btn-cerrar-procesando')?.addEventListener('click', () => setProcessingModalState({ show: false }));
    el('#modal-procesando')?.addEventListener('click', (ev) => {
      if (processingLocked) return;
      if (ev.target.id === 'modal-procesando') setProcessingModalState({ show: false });
    });
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
