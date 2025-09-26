(() => {
  const API = {
    listar: '../../api/facturas/listar.php',
    listar_cliente: '../../api/facturas/listar_cliente.php',
    crear: '../../api/facturas/crear.php',
    cancelar: '../../api/facturas/cancelar.php',
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

  function setDefaultsFechas() {
    const now = new Date();
    const desde = new Date(now.getFullYear(), now.getMonth(), 1);
    const hasta = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    el('#filtro-desde').value = desde.toISOString().slice(0, 10);
    el('#filtro-hasta').value = hasta.toISOString().slice(0, 10);
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
      if (j.success && j.resultado && Array.isArray(j.resultado.clientes)) {
        for (const c of j.resultado.clientes) {
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = (c.nombre || '') + (c.rfc ? ` â€” ${c.rfc}` : '');
          sel.appendChild(opt);
        }
      }
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

  function updateAccionesState() {
    const hasSel = seleccion.length > 0;
    const cliente = Number(el('#select-cliente').value || 0);
    el('#btn-uno-a-uno').disabled = !(hasSel && cliente > 0);
    el('#btn-global').disabled = !(hasSel && cliente > 0);
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
        <td class="right">${fmt(r.total)}</td>
        <td class="right"><input type="checkbox" data-id="${r.id}"></td>
      `;
      const chk = tr.querySelector('input[type="checkbox"]');
      chk.checked = seleccion.includes(Number(r.id));
      chk.addEventListener('change', () => toggleSeleccion(Number(r.id)));
      tb.appendChild(tr);
    }
  }

  function renderSeleccion() {
    const cont = el('#lista-seleccion');
    cont.innerHTML = '';
    let subtotal = 0, impuestos = 0, total = 0;
    for (const id of seleccion) {
      const r = pendientesIndex.get(id);
      const item = document.createElement('div');
      item.className = 'item';
      item.textContent = `Ticket ${id} â€” ${fmt(r?.total || 0)}`;
      item.title = 'Click para quitar';
      item.style.cursor = 'pointer';
      item.addEventListener('click', () => toggleSeleccion(id));
      cont.appendChild(item);
      // Asumimos totales: impuestos 0 si no hay info
      const t = Number(r?.total || 0);
      subtotal += t; total += t;
    }
    el('#totales-subtotal').textContent = fmt(subtotal);
    el('#totales-impuestos').textContent = fmt(impuestos);
    el('#totales-total').textContent = fmt(total);
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
        <td>${r.cliente_id ?? ''}</td>
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
      if (hasta) url.searchParams.set('hasta', hasta);
      url.searchParams.set('estado', 'todas');
      if (buscar) url.searchParams.set('buscar', buscar);
      if (sede) url.searchParams.set('sede_id', sede);
      const res = await fetch(url.toString());
      const j = await res.json();
      if (!j.success) throw new Error(j.mensaje || 'Fallo en listar');
      renderPendientes(j.resultado?.pendientes || []);
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
      const body = { modo: 'uno_a_uno', tickets: seleccion.slice(), cliente_id: clienteId };
      const res = await fetch(API.crear, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.mensaje || 'No se pudo crear');
      alert('Facturas creadas: ' + (j.resultado?.facturas?.length || 0));
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
    const { desde, hasta } = getFiltros();
    try {
      const body = { modo: 'global', tickets: seleccion.slice(), cliente_id: clienteId, periodo: { desde, hasta } };
      const res = await fetch(API.crear, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.mensaje || 'No se pudo crear');
      alert('Factura global creada: ID ' + (j.resultado?.factura_id));
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
      head.innerHTML = `<p><strong>Factura #${id}</strong> â€” Tickets: ${tks.map(x => x.ticket_id).join(', ') || '(n/d)'} â€” Total calc: ${fmt(sum)}</p>`;
      cont.appendChild(head);
      const table = document.createElement('table');
      table.innerHTML = `
        <thead><tr>
          <th>Producto</th><th>DescripciÃ³n</th><th class="right">Cantidad</th><th class="right">P.Unit</th><th class="right">Importe</th>
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
    el('#btn-buscar').addEventListener('click', listar);
    el('#select-cliente').addEventListener('change', updateAccionesState);
    el('#btn-uno-a-uno').addEventListener('click', facturarUnoAUno);
    el('#btn-global').addEventListener('click', facturarGlobal);
    el('#btn-cerrar-modal').addEventListener('click', () => el('#modal-detalle').style.display = 'none');
    // Cerrar modal al hacer click fuera
    el('#modal-detalle').addEventListener('click', (ev) => { if (ev.target.id === 'modal-detalle') ev.currentTarget.style.display = 'none'; });
  });

  // Exponer helpers si se requiere en consola
  window.listar = listar;
  window.toggleSeleccion = toggleSeleccion;
  window.facturarUnoAUno = facturarUnoAUno;
  window.facturarGlobal = facturarGlobal;
  window.verFactura = verFactura;
  window.cancelarFactura = cancelarFactura;
})();


