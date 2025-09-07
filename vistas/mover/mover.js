(() => {
  const API = {
    list: '../../api/mover/list.php',
    move: '../../api/mover/move.php',
  };

  const headers = () => ({
    'Content-Type': 'application/json',
    'X-Auth': (window.MOVER_AUTH || '').toString(),
    'X-CSRF': (window.CSRF_TOKEN || '').toString(),
  });

  const $ = (sel, ctx) => (ctx || document).querySelector(sel);
  const fmt = (n) => Number(n || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
  const state = {
    bd1: { page: 1, per_page: 20, rows: [], selected: new Set(), busy: false },
    bd2: { page: 1, per_page: 20, rows: [], selected: new Set(), busy: false },
  };

  async function fetchJSON(url, opts = {}) {
    const res = await fetch(url, { ...opts, headers: { ...(opts.headers || {}), ...headers() } });
    const data = await res.json().catch(() => ({ ok: false, error: 'Respuesta inválida' }));
    if (res.status === 401) throw new Error('No autorizado');
    if (!('ok' in data)) throw new Error('Formato JSON inesperado');
    if (!data.ok) throw new Error(data.error || 'Error');
    return data.data;
  }

  function setBusy(side, val) {
    state[side].busy = !!val;
    const btnToEsp = $('#btn-to-esp');
    const btnToOp  = $('#btn-to-op');
    if (val) {
      btnToEsp.classList.add('busy');
      btnToOp.classList.add('busy');
    } else {
      btnToEsp.classList.remove('busy');
      btnToOp.classList.remove('busy');
    }
    updateButtons();
  }

  function selectionArray(side) {
    return Array.from(state[side].selected.values());
  }

  function updateButtons() {
    $('#btn-to-esp').disabled = state.bd1.busy || selectionArray('bd1').length === 0;
    $('#btn-to-op').disabled  = state.bd2.busy || selectionArray('bd2').length === 0;
  }

  function renderTable(side, rows) {
    const tbody = side === 'bd1' ? $('#tbl-bd1 tbody') : $('#tbl-bd2 tbody');
    tbody.innerHTML = '';
    for (const r of rows) {
      const tr = document.createElement('tr');
      tr.dataset.id = r.id;
      tr.innerHTML = `
        <td><input type="checkbox" class="rowchk" data-id="${r.id}"></td>
        <td>${r.id}</td>
        <td>${r.venta_id ?? ''}</td>
        <td>${r.folio ?? ''}</td>
        <td class="right">${fmt(r.total)}</td>
        <td class="right">${Number(r.descuento || 0).toFixed(2)}</td>
        <td>${(r.fecha || '').replace('T',' ')}</td>
        <td>${r.corte_id ?? ''}</td>
        <td><span class="badge ${r.factura==='SI'?'':'"'}">${r.factura}</span></td>
      `;
      const chk = tr.querySelector('.rowchk');
      chk.checked = state[side].selected.has(r.id);
      chk.addEventListener('change', () => {
        if (chk.checked) state[side].selected.add(r.id); else state[side].selected.delete(r.id);
        updateButtons();
      });
      tbody.appendChild(tr);
    }
    updateButtons();
  }

  async function loadSide(side) {
    const qEl = side === 'bd1' ? $('#q-bd1') : $('#q-bd2');
    const fromEl = side === 'bd1' ? $('#from-bd1') : $('#from-bd2');
    const toEl = side === 'bd1' ? $('#to-bd1') : $('#to-bd2');
    const perEl = side === 'bd1' ? $('#per-bd1') : $('#per-bd2');
    const pageBadge = side === 'bd1' ? $('#page-bd1') : $('#page-bd2');

    const q = qEl.value.trim();
    const from = fromEl.value;
    const to = toEl.value;
    const per = Number(perEl.value || 20);
    state[side].per_page = per;

    const url = new URL(API.list, window.location.href);
    url.searchParams.set('side', side);
    if (q) url.searchParams.set('q', q);
    if (from) url.searchParams.set('from', from);
    if (to) url.searchParams.set('to', to);
    url.searchParams.set('page', String(state[side].page));
    url.searchParams.set('per_page', String(state[side].per_page));

    setBusy(side, true);
    try {
      const data = await fetchJSON(url.toString());
      state[side].rows = data.rows || [];
      renderTable(side, state[side].rows);
      pageBadge.textContent = String(state[side].page);
    } catch (e) {
      alert('Error al listar ' + side + ': ' + e.message);
    } finally {
      setBusy(side, false);
    }
  }

  async function doMove(direction) {
    const side = direction === 'to_espejo' ? 'bd1' : 'bd2';
    const ids = selectionArray(side);
    if (ids.length === 0) return;
    if (!confirm(`Confirmar mover ${ids.length} ticket(s)`)) return;
    setBusy(side, true);
    try {
      const data = await fetchJSON(API.move, {
        method: 'POST',
        body: JSON.stringify({ direction, ticket_ids: ids })
      });
      const failed = data.failed || [];
      const succeed = data.succeed || [];
      if (succeed.length) {
        alert('Éxito: ' + succeed.length + ' ticket(s)');
      }
      if (failed.length) {
        for (const f of failed) {
          const row = document.querySelector(`tr[data-id="${f.ticket_id}"]`);
          if (row) {
            row.classList.add('row-error');
            row.title = f.error || 'Error';
          }
        }
        alert('Fallaron: ' + failed.length + ' ticket(s)');
      }
      // Refrescar ambos lados
      await Promise.all([ loadSide('bd1'), loadSide('bd2') ]);
      state.bd1.selected.clear();
      state.bd2.selected.clear();
      updateButtons();
    } catch (e) {
      alert('Error al mover: ' + e.message);
    } finally {
      setBusy(side, false);
    }
  }

  function wireSideControls(side) {
    const prev = side === 'bd1' ? $('#prev-bd1') : $('#prev-bd2');
    const next = side === 'bd1' ? $('#next-bd1') : $('#next-bd2');
    const per  = side === 'bd1' ? $('#per-bd1')  : $('#per-bd2');
    const qEl  = side === 'bd1' ? $('#q-bd1')    : $('#q-bd2');
    const from = side === 'bd1' ? $('#from-bd1') : $('#from-bd2');
    const to   = side === 'bd1' ? $('#to-bd1')   : $('#to-bd2');
    const selAllHead = side === 'bd1' ? $('#selall-bd1-head') : $('#selall-bd2-head');

    prev.addEventListener('click', () => { if (state[side].page > 1) { state[side].page--; loadSide(side);} });
    next.addEventListener('click', () => { state[side].page++; loadSide(side); });
    per.addEventListener('change', () => { state[side].page = 1; loadSide(side); });
    qEl.addEventListener('change', () => { state[side].page = 1; loadSide(side); });
    from.addEventListener('change', () => { state[side].page = 1; loadSide(side); });
    to.addEventListener('change', () => { state[side].page = 1; loadSide(side); });
    selAllHead.addEventListener('change', (e) => {
      const checked = e.target.checked;
      const checkboxes = (side === 'bd1' ? $('#tbl-bd1') : $('#tbl-bd2')).querySelectorAll('.rowchk');
      state[side].selected.clear();
      checkboxes.forEach(ch => { ch.checked = checked; if (checked) state[side].selected.add(Number(ch.dataset.id)); });
      updateButtons();
    });
  }

  window.addEventListener('DOMContentLoaded', async () => {
    // Adapt buttons to style1.css
    const b1 = document.getElementById('btn-to-esp'); if (b1) b1.classList.add('btn','custom-btn');
    const b2 = document.getElementById('btn-to-op'); if (b2) b2.classList.add('btn','custom-btn');
    wireSideControls('bd1');
    wireSideControls('bd2');
    $('#btn-to-esp').addEventListener('click', () => doMove('to_espejo'));
    $('#btn-to-op').addEventListener('click',  () => doMove('to_operativo'));
    await Promise.all([ loadSide('bd1'), loadSide('bd2') ]);
  });
})();
