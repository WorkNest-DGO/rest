function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;
const usuarioId = 1; // En producción usar id de sesión
const apiReportes = '../../api/reportes/vistas_db.php';

// ====== Estado para constructor de gráficas (D3) ======
let chartColumns = [];
let chartRows = [];
let chartState = { type: 'bar', agg: 'sum', x: '', y: '' };

async function cargarUsuarios() {
    const sel = document.getElementById('filtroUsuario');
    if (!sel) return;
    const r = await fetch('../../api/usuarios/listar_usuarios.php');
    const d = await r.json();
    sel.innerHTML = '<option value="">--Todos--</option>';
    if (d && (d.success || Array.isArray(d.usuarios))) {
        const arr = Array.isArray(d.resultado) ? d.resultado : (Array.isArray(d.usuarios) ? d.usuarios : []);
        arr.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.nombre;
            sel.appendChild(opt);
        });
    }
}

async function cargarHistorial() {
    const tbody = document.querySelector('#tablaCortes tbody');
    tbody.innerHTML = '<tr><td colspan="11">Cargando...</td></tr>';
    try {
        const params = new URLSearchParams();
        const u = document.getElementById('filtroUsuario').value;
        const i = document.getElementById('filtroInicio').value;
        const f = document.getElementById('filtroFin').value;
        if (u) params.append('usuario_id', u);
        if (i) params.append('inicio', i);
        if (f) params.append('fin', f);
        const resp = await fetch('../../api/corte_caja/listar_cortes.php?' + params.toString());
        const data = await resp.json();
        if (data.success) {
            tbody.innerHTML = '';
            const lista = Array.isArray(data.resultado)
                ? data.resultado
                : (Array.isArray(data.resultado?.cortes) ? data.resultado.cortes : []);
            lista.forEach(c => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${c.id}</td>
                    <td>${c.usuario}</td>
                    <td>${c.fecha_inicio}</td>
                    <td>${c.fecha_fin || ''}</td>
                    <td>${c.total !== null ? c.total : ''}</td>
                    <td>${c.efectivo || ''}</td>
                    <td>${c.boucher || ''}</td>
                    <td>${c.cheque || ''}</td>
                    <td>${c.fondo_inicial || ''}</td>
                    <td>${c.observaciones || ''}</td>
                    <td><button class="btn custom-btn detalle" data-id="${c.id}">Ver detalle</button></td>
                `;
                tbody.appendChild(tr);
            });
            tbody.querySelectorAll('button.detalle').forEach(btn => {
                btn.addEventListener('click', () => verDetalle(btn.dataset.id));
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="7">Error al cargar</td></tr>';
        }
    } catch (err) {
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="7">Error</td></tr>';
    }
}

async function verDetalle(corteId) {
    const modal = document.getElementById('modal');
    modal.innerHTML = 'Cargando...';
    modal.style.display = 'block';
    try {
        const resp = await fetch('../../api/corte_caja/detalle_venta.php?corte_id=' + corteId);
        const data = await resp.json();
        if (data.success) {
            const grupos = {};
            data.detalles.forEach(d => {
                if (!grupos[d.tipo_pago]) grupos[d.tipo_pago] = [];
                grupos[d.tipo_pago].push(d);
            });
            let html = `<h3>Desglose del corte ${corteId}</h3>`;
            ['efectivo', 'boucher', 'cheque'].forEach(tp => {
                const arr = grupos[tp] || [];
                if (!arr.length) return;
                let total = 0;
                html += `<h4>${tp}</h4><table border="1"><thead><tr><th>Descripción</th><th>Cantidad</th><th>Valor</th><th>Subtotal</th></tr></thead><tbody>`;
                arr.forEach(r => {
                    total += r.subtotal;
                    html += `<tr><td>${r.descripcion}</td><td>${r.cantidad}</td><td>${r.valor}</td><td>${r.subtotal}</td></tr>`;
                });
                html += `<tr><td colspan="3"><strong>Total</strong></td><td><strong>${total.toFixed(2)}</strong></td></tr>`;
                html += '</tbody></table>';
            });
            html += '<button class="btn custom-btn" id="cerrarModal">Cerrar</button>';
            modal.innerHTML = html;
            document.getElementById('cerrarModal').addEventListener('click', () => {
                modal.style.display = 'none';
            });
        } else {
            modal.innerHTML = data.mensaje;
        }
    } catch (err) {
        console.error(err);
        modal.innerHTML = 'Error al obtener detalle';
    }
}

async function resumenActual() {
    const modal = document.getElementById('modal');
    modal.innerHTML = 'Cargando...';
    modal.style.display = 'block';
    try {
        const resp = await fetch('../../api/corte_caja/resumen_corte_actual.php?usuario_id=' + usuarioId);
        const data = await resp.json();
        if (!data.success || !data.resultado.abierto) {
            modal.style.display = 'none';
            alert('No hay corte abierto');
            return;
        }
        const r = data.resultado;
        let html = `<h3>Resumen del corte ${r.corte_id}</h3>`;
        html += `<p>Ventas totales: $${r.total}</p>`;
        html += `<p>Número de ventas: ${r.num_ventas}</p>`;
        html += `<p>Total en propinas: $${r.propinas}</p>`;
        if (r.metodos_pago && r.metodos_pago.length) {
            html += '<h4>Métodos de pago</h4><ul>';
            r.metodos_pago.forEach(m => {
                html += `<li>${m.metodo}: $${m.total}</li>`;
            });
            html += '</ul>';
        }
        html += '<button class="btn custom-btn" id="cerrarModal">Cerrar</button>';
        modal.innerHTML = html;
        document.getElementById('cerrarModal').addEventListener('click', () => {
            modal.style.display = 'none';
        });
    } catch (err) {
        console.error(err);
        modal.innerHTML = 'Error al obtener resumen';
    }
}

// --- Reportes dinámicos de vistas/tablas ---
let fuenteActual = '';
let pagina = 1;
let tamPagina = 15;
let termino = '';
let ordenCol = '';
let ordenDir = 'asc';
let debounceTimer;

// Helpers de formato/estilo
function isNumericStr(v) { return typeof v === 'string' && /^-?\d+(?:\.\d+)?$/.test(v.trim()); }
function isNumeric(v) { return typeof v === 'number' || isNumericStr(v); }
function toNumber(v) { return typeof v === 'number' ? v : parseFloat(v); }
function isMoneyColumn(colLower) {
    return /(total|monto|precio|importe|subtotal|propina|fondo|saldo)/.test(colLower);
}
function isCountColumn(colLower) {
    return /(cantidad|numero|número|folio|id|existencia)/.test(colLower);
}
function isDateLike(val) {
    if (val == null) return false;
    if (typeof val !== 'string') return false;
    return /^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/.test(val);
}
function formatDate(val) {
    // Mantén formato YYYY-MM-DD HH:mm
    if (!val) return '';
    const s = String(val);
    if (s.length >= 16) return s.slice(0,16).replace('T',' ');
    if (s.length >= 10) return s.slice(0,10);
    return s;
}
function formatMoney(n) {
    const num = toNumber(n);
    if (!isFinite(num)) return String(n ?? '');
    return '$' + num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatNumber(n, dec = 0) {
    const num = toNumber(n);
    if (!isFinite(num)) return String(n ?? '');
    return num.toLocaleString('es-MX', dec > 0 ? { minimumFractionDigits: dec, maximumFractionDigits: dec } : undefined);
}
function formatCellValue(col, raw) {
    const colL = (col || '').toLowerCase();
    if (raw == null) return '';
    if (isDateLike(raw)) return formatDate(raw);
    if (isMoneyColumn(colL) && isNumeric(raw)) return formatMoney(raw);
    if (isCountColumn(colL) && isNumeric(raw)) return formatNumber(raw, 0);
    if (isNumeric(raw)) {
        // Si tiene decimales, muestra 2; si no, entero
        const hasDec = String(raw).includes('.');
        return formatNumber(raw, hasDec ? 2 : 0);
    }
    const s = String(raw);
    if (s.length > 120) return s.slice(0, 117) + '…';
    return s;
}
function alignForCol(col, raw) {
    const colL = (col || '').toLowerCase();
    if (isDateLike(raw)) return 'center';
    if (isMoneyColumn(colL) || (isNumeric(raw) && !/\D/.test(String(raw)))) return 'right';
    if (/^fecha/.test(colL)) return 'center';
    return 'left';
}

async function listarFuentes() {
    const select = document.getElementById('selectFuente');
    if (!select) return;
    try {
        const resp = await fetch(`${apiReportes}?action=list_sources`);
        const data = await resp.json();
        select.innerHTML = '';
        const ogV = document.createElement('optgroup');
        ogV.label = 'Vistas';
        data.views.forEach(v => {
            const o = document.createElement('option');
            o.value = v;
            o.textContent = v;
            ogV.appendChild(o);
        });
        const ogT = document.createElement('optgroup');
        ogT.label = 'Tablas';
        data.tables.forEach(t => {
            const o = document.createElement('option');
            o.value = t;
            o.textContent = t;
            ogT.appendChild(o);
        });
        if (data.views.length) {
            select.appendChild(ogV);
            select.appendChild(ogT);
            select.value = data.views[0];
        } else {
            select.appendChild(ogV);
            select.appendChild(ogT);
            if (data.tables.length) select.value = data.tables[0];
        }
        fuenteActual = select.value;
        cargarFuente();
    } catch (err) {
        console.error(err);
    }
}

async function cargarFuente() {
    const tabla = document.getElementById('tablaReportes');
    if (!tabla) return;
    const thead = tabla.querySelector('thead');
    const tbody = tabla.querySelector('tbody');
    const loader = document.getElementById('reportesLoader');
    loader.style.display = 'block';
    tbody.innerHTML = '';
    const params = new URLSearchParams({
        action: 'fetch',
        source: fuenteActual,
        page: pagina,
        pageSize: tamPagina
    });
    if (termino) params.append('q', termino);
    if (ordenCol) {
        params.append('sortBy', ordenCol);
        params.append('sortDir', ordenDir);
    }
    try {
        const resp = await fetch(`${apiReportes}?${params.toString()}`);
        const data = await resp.json();
        loader.style.display = 'none';
        if (data.error) {
            thead.innerHTML = '';
            tbody.innerHTML = `<tr><td colspan="1">${data.error}</td></tr>`;
            document.getElementById('infoReportes').textContent = '';
            return;
        }
        // Header
        thead.innerHTML = '';
        const trHead = document.createElement('tr');
        data.columns.forEach(c => {
            const th = document.createElement('th');
            th.dataset.col = c;
            th.textContent = c;
            trHead.appendChild(th);
        });
        thead.appendChild(trHead);

        // Body con formato
        if (!data.rows.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = data.columns.length;
            td.textContent = 'Sin resultados';
            tr.appendChild(td);
            tbody.appendChild(tr);
        } else {
            const frag = document.createDocumentFragment();
            data.rows.forEach(r => {
                const tr = document.createElement('tr');
                data.columns.forEach(c => {
                    const td = document.createElement('td');
                    const val = r[c];
                    td.textContent = formatCellValue(c, val);
                    const align = alignForCol(c, val);
                    if (align === 'right') td.style.textAlign = 'right';
                    else if (align === 'center') td.style.textAlign = 'center';
                    tr.appendChild(td);
                });
                frag.appendChild(tr);
            });
            tbody.appendChild(frag);
        }
        const inicio = data.total ? ((data.page - 1) * data.pageSize + 1) : 0;
        const fin = Math.min(data.page * data.pageSize, data.total);
        document.getElementById('infoReportes').textContent = `Mostrando ${inicio}-${fin} de ${data.total}`;
        document.getElementById('prevReportes').disabled = data.page <= 1;
        document.getElementById('nextReportes').disabled = data.page * data.pageSize >= data.total;

        // Sincronizar datos para gráficas
        syncChartData(data.columns, data.rows);
    } catch (err) {
        loader.style.display = 'none';
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="1">Error al cargar</td></tr>`;
    }
}

function initReportesDinamicos() {
    const select = document.getElementById('selectFuente');
    if (!select) return;
    listarFuentes();
    select.addEventListener('change', () => {
        fuenteActual = select.value;
        pagina = 1;
        cargarFuente();
    });
    const btnExport = document.getElementById('btnExportCSV');
    if (btnExport) {
        btnExport.addEventListener('click', () => {
            if (!fuenteActual) return;
            const params = new URLSearchParams({ action: 'export_csv', source: fuenteActual });
            if (termino) params.append('q', termino);
            if (ordenCol) { params.append('sortBy', ordenCol); params.append('sortDir', ordenDir); }
            const url = `${apiReportes}?${params.toString()}`;
            // Abrir en nueva pestaña para descargar
            window.open(url, '_blank');
        });
    }
    document.getElementById('buscarFuente').addEventListener('input', e => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            termino = e.target.value;
            pagina = 1;
            cargarFuente();
        }, 300);
    });
    document.getElementById('tamPagina').addEventListener('change', e => {
        tamPagina = parseInt(e.target.value, 10);
        pagina = 1;
        cargarFuente();
    });
    document.getElementById('prevReportes').addEventListener('click', () => {
        if (pagina > 1) {
            pagina--;
            cargarFuente();
        }
    });
    document.getElementById('nextReportes').addEventListener('click', () => {
        pagina++;
        cargarFuente();
    });
    document.querySelector('#tablaReportes thead').addEventListener('click', e => {
        if (e.target.tagName === 'TH') {
            const col = e.target.dataset.col;
            if (ordenCol === col) {
                ordenDir = ordenDir === 'asc' ? 'desc' : 'asc';
            } else {
                ordenCol = col;
                ordenDir = 'asc';
            }
            cargarFuente();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios();
    cargarHistorial();
    document.getElementById('btnResumen').addEventListener('click', resumenActual);
    const btn = document.getElementById('aplicarFiltros');
    if (btn) btn.addEventListener('click', cargarHistorial);
    const imp = document.getElementById('btnImprimir');
    if (imp) imp.addEventListener('click', () => window.print());
    initReportesDinamicos();
    initChartBuilder();
    // Export buttons
    const btnPNG = document.getElementById('btnExportPNG');
    const btnSVG = document.getElementById('btnExportSVG');
    if (btnPNG) btnPNG.addEventListener('click', () => exportChartPNG());
    if (btnSVG) btnSVG.addEventListener('click', () => exportChartSVG());
});

// ====== Gráficas con D3 ======
function syncChartData(columns, rows) {
    chartColumns = Array.isArray(columns) ? columns.slice() : [];
    chartRows = Array.isArray(rows) ? rows.slice() : [];
    populateChartSelectors();
}

function populateChartSelectors() {
    const selX = document.getElementById('chartXField');
    const selY = document.getElementById('chartYField');
    const selSeries = document.getElementById('chartSeriesField');
    const selYCat = document.getElementById('chartYCatField');
    const selStart = document.getElementById('chartStartField');
    const selEnd = document.getElementById('chartEndField');
    const selH1 = document.getElementById('chartH1');
    const selH2 = document.getElementById('chartH2');
    const selH3 = document.getElementById('chartH3');
    const selTarget = document.getElementById('chartTargetField');
    if (!selX || !selY) return;
    selX.innerHTML = '';
    selY.innerHTML = '';
    if (selSeries) selSeries.innerHTML = '';
    if (selYCat) selYCat.innerHTML = '';
    if (selStart) selStart.innerHTML = '';
    if (selEnd) selEnd.innerHTML = '';
    if (selH1) selH1.innerHTML = '';
    if (selH2) selH2.innerHTML = '';
    if (selH3) selH3.innerHTML = '';
    if (selTarget) selTarget.innerHTML = '';
    // Heurística: sugerir X fecha/categoría y Y numérico (total/importe/etc)
    let suggestedX = '';
    let suggestedY = '';
    const numCols = [];
    const catCols = [];
    const lowerCols = chartColumns.map(c => c.toLowerCase());
    chartColumns.forEach((c, idx) => {
        // mira primera fila con valor para determinar
        let sample = null;
        for (let i = 0; i < chartRows.length; i++) {
            const v = chartRows[i][c];
            if (v !== undefined && v !== null && v !== '') { sample = v; break; }
        }
        const isNum = isNumeric(sample);
        if (isNum) numCols.push(c); else catCols.push(c);
    });
    // Prioriza columnas con nombre típico
    const pick = (arr, patterns) => arr.find(c => patterns.some(p => c.toLowerCase().includes(p))) || arr[0] || '';
    suggestedX = pick(catCols, ['fecha', 'dia', 'mes', 'categoria', 'producto', 'mesero', 'repartidor', 'metodo', 'forma']);
    suggestedY = pick(numCols, ['total', 'monto', 'importe', 'precio', 'propina', 'cantidad']);
    // opción vacía para campos opcionales
    const addEmpty = (sel) => { if (sel) { const opt = document.createElement('option'); opt.value=''; opt.textContent='(ninguno)'; sel.appendChild(opt); } };
    addEmpty(selSeries); addEmpty(selYCat); addEmpty(selStart); addEmpty(selEnd); addEmpty(selH1); addEmpty(selH2); addEmpty(selH3); addEmpty(selTarget);
    // Construye opciones
    chartColumns.forEach(c => {
        const optX = document.createElement('option'); optX.value = c; optX.textContent = c; if (c === suggestedX) optX.selected = true; selX.appendChild(optX);
        const optY = document.createElement('option'); optY.value = c; optY.textContent = c; if (c === suggestedY) optY.selected = true; selY.appendChild(optY);
        if (selSeries) { const o=document.createElement('option'); o.value=c; o.textContent=c; selSeries.appendChild(o); }
        if (selYCat) { const o=document.createElement('option'); o.value=c; o.textContent=c; selYCat.appendChild(o); }
        if (selStart) { const o=document.createElement('option'); o.value=c; o.textContent=c; selStart.appendChild(o); }
        if (selEnd) { const o=document.createElement('option'); o.value=c; o.textContent=c; selEnd.appendChild(o); }
        if (selH1) { const o=document.createElement('option'); o.value=c; o.textContent=c; selH1.appendChild(o); }
        if (selH2) { const o=document.createElement('option'); o.value=c; o.textContent=c; selH2.appendChild(o); }
        if (selH3) { const o=document.createElement('option'); o.value=c; o.textContent=c; selH3.appendChild(o); }
        if (selTarget) { const o=document.createElement('option'); o.value=c; o.textContent=c; selTarget.appendChild(o); }
    });
    chartState.x = selX.value; chartState.y = selY.value;
    if (selSeries) chartState.series = selSeries.value;
    if (selYCat) chartState.yCat = selYCat.value;
    if (selStart) chartState.start = selStart.value;
    if (selEnd) chartState.end = selEnd.value;
    if (selH1) chartState.h1 = selH1.value;
    if (selH2) chartState.h2 = selH2.value;
    if (selH3) chartState.h3 = selH3.value;
    if (selTarget) chartState.target = selTarget.value;
}

function initChartBuilder() {
    const typeSel = document.getElementById('chartType');
    const aggSel = document.getElementById('chartAgg');
    const selX = document.getElementById('chartXField');
    const selY = document.getElementById('chartYField');
    const selSeries = document.getElementById('chartSeriesField');
    const selYCat = document.getElementById('chartYCatField');
    const selStart = document.getElementById('chartStartField');
    const selEnd = document.getElementById('chartEndField');
    const selH1 = document.getElementById('chartH1');
    const selH2 = document.getElementById('chartH2');
    const selH3 = document.getElementById('chartH3');
    const selTarget = document.getElementById('chartTargetField');
    const rowPct = document.getElementById('chartRowPercent');
    const btn = document.getElementById('btnRenderChart');
    if (!typeSel || !aggSel || !selX || !selY || !btn) return;
    typeSel.addEventListener('change', () => { chartState.type = typeSel.value; updateChartControlsVisibility(); });
    aggSel.addEventListener('change', () => { chartState.agg = aggSel.value; updateYEnable(); });
    selX.addEventListener('change', () => { chartState.x = selX.value; });
    selY.addEventListener('change', () => { chartState.y = selY.value; });
    if (selSeries) selSeries.addEventListener('change', () => { chartState.series = selSeries.value; });
    if (selYCat) selYCat.addEventListener('change', () => { chartState.yCat = selYCat.value; });
    if (selStart) selStart.addEventListener('change', () => { chartState.start = selStart.value; });
    if (selEnd) selEnd.addEventListener('change', () => { chartState.end = selEnd.value; });
    if (selH1) selH1.addEventListener('change', () => { chartState.h1 = selH1.value; });
    if (selH2) selH2.addEventListener('change', () => { chartState.h2 = selH2.value; });
    if (selH3) selH3.addEventListener('change', () => { chartState.h3 = selH3.value; });
    if (selTarget) selTarget.addEventListener('change', () => { chartState.target = selTarget.value; });
    if (rowPct) rowPct.addEventListener('change', () => { chartState.rowPercent = rowPct.checked; });
    btn.addEventListener('click', renderChart);
    updateYEnable();
    updateChartControlsVisibility();
}

function updateYEnable() {
    const aggSel = document.getElementById('chartAgg');
    const selY = document.getElementById('chartYField');
    if (!aggSel || !selY) return;
    const isCount = aggSel.value === 'count';
    selY.disabled = isCount;
}

function showEl(id, show) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = show ? '' : 'none';
}
function showLabel(id, show) { showEl(id, show); }

function updateChartControlsVisibility() {
    const t = (document.getElementById('chartType')||{}).value || 'bar';
    // Mostrar/ocultar controles segun tipo
    showLabel('lblChartYField', !['scatter','gantt','cohort','heatmap'].includes(t) || document.getElementById('chartAgg').value==='sum');
    showLabel('lblChartSeriesField', ['bar_stacked','line','scatter','bullet'].includes(t)); showEl('chartSeriesField', ['bar_stacked','line','scatter','bullet'].includes(t));
    showLabel('lblChartYCatField', ['heatmap','cohort'].includes(t)); showEl('chartYCatField', ['heatmap','cohort'].includes(t));
    showLabel('lblChartStartField', t==='gantt'); showEl('chartStartField', t==='gantt');
    showLabel('lblChartEndField', t==='gantt'); showEl('chartEndField', t==='gantt');
    showLabel('lblChartH1', ['treemap','sunburst'].includes(t)); showEl('chartH1', ['treemap','sunburst'].includes(t));
    showLabel('lblChartH2', ['treemap','sunburst'].includes(t)); showEl('chartH2', ['treemap','sunburst'].includes(t));
    showLabel('lblChartH3', ['treemap','sunburst'].includes(t)); showEl('chartH3', ['treemap','sunburst'].includes(t));
    showLabel('lblChartTargetField', t==='bullet'); showEl('chartTargetField', t==='bullet');
    showLabel('lblChartRowPercent', t==='cohort');
}

function aggregateData(rows, xField, yField, agg) {
    const map = new Map();
    const isCount = agg === 'count';
    rows.forEach(r => {
        const k = r[xField];
        const yv = isCount ? 1 : toNumber(r[yField]);
        if (!isFinite(yv)) return;
        map.set(k, (map.get(k) || 0) + yv);
    });
    // Array de objetos {key, value}
    return Array.from(map.entries()).map(([key, value]) => ({ key, value }));
}

function isDateKey(v) { return isDateLike(v); }

function renderChart() {
    const container = document.getElementById('chartContainer');
    const svg = d3.select('#chartSvg');
    if (!container || svg.empty()) return;
    const type = document.getElementById('chartType').value;
    const agg = document.getElementById('chartAgg').value;
    const xField = document.getElementById('chartXField').value;
    const yField = document.getElementById('chartYField').value;
    const seriesField = (document.getElementById('chartSeriesField')||{}).value || '';
    const yCatField = (document.getElementById('chartYCatField')||{}).value || '';
    const startField = (document.getElementById('chartStartField')||{}).value || '';
    const endField = (document.getElementById('chartEndField')||{}).value || '';
    const h1 = (document.getElementById('chartH1')||{}).value || '';
    const h2 = (document.getElementById('chartH2')||{}).value || '';
    const h3 = (document.getElementById('chartH3')||{}).value || '';
    const targetField = (document.getElementById('chartTargetField')||{}).value || '';
    const rowPercent = !!(document.getElementById('chartRowPercent')||{}).checked;
    svg.selectAll('*').remove();
    if (!chartRows.length) return;
    if (!xField && !['treemap','sunburst','gantt'].includes(type)) return;
    updateChartCaption({ type, agg, xField, yField, seriesField });
    if (['bar','line','pie','donut','waffle','funnel','bar_pareto','bullet'].includes(type)) {
        if (!xField || (!yField && agg !== 'count')) return;
        let dataAgg = aggregateData(chartRows, xField, yField, agg);
        let parsed = dataAgg.map(d => ({ ...d }));
        if (parsed.length && isDateKey(parsed[0].key)) { parsed = parsed.map(d => ({ key: new Date(d.key), value: d.value })); parsed.sort((a,b) => a.key - b.key); }
        else { parsed.sort((a,b) => b.value - a.value); }
        if (type === 'bar') return drawBarChart(svg, parsed, { xLabel: xField, yLabel: (agg==='count'?'Conteo':yField) });
        if (type === 'line') {
            if (seriesField) {
                const multi = aggregateBySeries(chartRows, xField, yField, seriesField, agg);
                return drawMultiLineChart(svg, multi, { xLabel: xField, yLabel: (agg==='count'?'Conteo':yField) });
            }
            return drawLineChart(svg, parsed, { xLabel: xField, yLabel: (agg==='count'?'Conteo':yField) });
        }
        if (type === 'pie') return drawPieChart(svg, parsed);
        if (type === 'donut') return drawDonutChart(svg, parsed);
        if (type === 'waffle') return drawWaffleChart(svg, parsed);
        if (type === 'funnel') return drawFunnelChart(svg, parsed);
        if (type === 'bar_pareto') return drawParetoChart(svg, parsed, { xLabel: xField });
        if (type === 'bullet') {
            const targetCol = targetField && targetField !== yField ? targetField : '';
            const measures = aggregateData(chartRows, xField, yField, 'sum');
            const targets = targetCol ? aggregateData(chartRows, xField, targetCol, 'sum') : [];
            const mapT = new Map(targets.map(d=>[String(d.key), d.value]));
            const items = measures.map(m => ({ category: String(m.key), value: +m.value, target: + (mapT.get(String(m.key)) || 0) }));
            return drawBulletChart(svg, items);
        }
    }
    if (type === 'bar_stacked') {
        if (!xField || !seriesField || (!yField && agg !== 'count')) return;
        const nested = nestByXSeries(chartRows, xField, seriesField, yField, agg);
        return drawStackedBarChart(svg, nested, { xLabel: xField, yLabel: (agg==='count'?'Conteo':yField) });
    }
    if (type === 'scatter') {
        if (!xField || !yField) return;
        const pts = chartRows.map(r => ({ x: toNumber(r[xField]), y: toNumber(r[yField]), s: seriesField ? r[seriesField] : null }))
            .filter(p => isFinite(p.x) && isFinite(p.y));
        return drawScatterChart(svg, pts, { xLabel: xField, yLabel: yField });
    }
    if (type === 'heatmap' || type === 'cohort') {
        if (!xField || !yCatField) return;
        const matrix = crossAggregate(chartRows, xField, yCatField, yField, agg);
        const isPct = (type === 'cohort') && rowPercent;
        return drawHeatmap(svg, matrix, { xLabel: xField, yLabel: yCatField, percentByRow: isPct });
    }
    if (type === 'box' || type === 'violin') {
        if (!xField || !yField) return;
        const groups = groupNumeric(chartRows, xField, yField);
        if (type === 'box') return drawBoxPlot(svg, groups, { xLabel: xField, yLabel: yField });
        return drawViolinPlot(svg, groups, { xLabel: xField, yLabel: yField });
    }
    if (type === 'treemap' || type === 'sunburst') {
        const levels = [h1, h2, h3].filter(Boolean);
        if (!levels.length) return;
        const valField = yField || (agg==='count' ? null : null);
        const root = buildHierarchy(chartRows, levels, valField, agg);
        if (type === 'treemap') return drawTreemap(svg, root);
        return drawSunburst(svg, root);
    }
    if (type === 'gantt') {
        if (!xField || !startField || !endField) return;
        const tasks = chartRows.map(r => ({ label: String(r[xField]), start: parseDate(r[startField]), end: parseDate(r[endField]) }))
            .filter(t => t.start && t.end && t.end >= t.start);
        return drawGantt(svg, tasks);
    }
    updateChartCaption({ type, agg, xField, yField, seriesField });
}

function getSvgSize(svgSel) {
    const node = svgSel.node();
    const w = node ? node.clientWidth || node.parentNode.clientWidth : 800;
    const h = node ? node.clientHeight || 420 : 420;
    return { width: w, height: h };
}

function updateChartCaption({ type, agg, xField, yField, seriesField }) {
    const el = document.getElementById('chartCaption');
    if (!el) return;
    const t = (document.getElementById('chartTitle')||{}).value || '';
    const d = (document.getElementById('chartDesc')||{}).value || '';
    const tipo = ({bar:'Barras', bar_stacked:'Barras apiladas', bar_pareto:'Pareto', line:'Línea', scatter:'Dispersión', pie:'Pastel', donut:'Dona', waffle:'Waffle', heatmap:'Heatmap', cohort:'Cohort', box:'Box plot', violin:'Violin', bullet:'Bullet', treemap:'Treemap', sunburst:'Sunburst', gantt:'Gantt', funnel:'Funnel'})[type] || type;
    const aggTxt = agg==='count' ? 'conteo' : 'suma';
    const base = `${tipo}: ${aggTxt} por ${xField}${yField?` (valor: ${yField})`:''}${seriesField?` · serie: ${seriesField}`:''}`;
    el.textContent = [t, d, base].filter(Boolean).join(' — ');
}

function exportChartSVG() {
    const svg = document.getElementById('chartSvg');
    if (!svg) return;
    const clone = svg.cloneNode(true);
    const bbox = svg.getBoundingClientRect();
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    clone.setAttribute('width', bbox.width);
    clone.setAttribute('height', bbox.height);
    const data = new XMLSerializer().serializeToString(clone);
    const blob = new Blob([data], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'grafica.svg'; a.click();
    URL.revokeObjectURL(url);
}

function exportChartPNG() {
    const svg = document.getElementById('chartSvg');
    if (!svg) return;
    const bbox = svg.getBoundingClientRect();
    const w = Math.max(1, Math.floor(bbox.width));
    const h = Math.max(1, Math.floor(bbox.height));
    const clone = svg.cloneNode(true);
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    clone.setAttribute('width', w);
    clone.setAttribute('height', h);
    const data = new XMLSerializer().serializeToString(clone);
    const img = new Image();
    const svgUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(data);
    img.onload = function() {
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0,0,w,h);
        ctx.drawImage(img, 0, 0);
        const png = canvas.toDataURL('image/png');
        const a = document.createElement('a'); a.href = png; a.download = 'grafica.png'; a.click();
    };
    img.src = svgUrl;
}

function drawBarChart(svg, data, { xLabel, yLabel }) {
    const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 18, bottom: 64, left: 64 };
    const w = Math.max(200, width - margin.left - margin.right);
    const h = Math.max(160, height - margin.top - margin.bottom);
    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
    // Escalas
    const keys = data.map(d => d.key);
    const x = d3.scaleBand().domain(keys).range([0, w]).padding(0.15);
    const y = d3.scaleLinear().domain([0, d3.max(data, d => d.value) || 0]).nice().range([h, 0]);
    // Ejes
    g.append('g').attr('transform', `translate(0,${h})`).call(d3.axisBottom(x).tickFormat(k => typeof k === 'string' ? k.slice(0, 14) : d3.timeFormat('%Y-%m-%d')(k)))
      .selectAll('text').attr('transform','rotate(-35)').style('text-anchor','end');
    g.append('g').call(d3.axisLeft(y));
    // Barras
    g.selectAll('rect').data(data).enter().append('rect')
      .attr('x', d => x(d.key))
      .attr('y', d => y(d.value))
      .attr('width', x.bandwidth())
      .attr('height', d => h - y(d.value))
      .attr('fill', '#0d6efd')
      .append('title').text(d => `${xLabel}: ${formatCellValue(xLabel,d.key)}\n${yLabel}: ${formatNumber(d.value, 2)}`);
    // Labels ejes
    svg.append('text').attr('x', margin.left + w/2).attr('y', height - 6).attr('text-anchor','middle').text(xLabel);
    svg.append('text').attr('transform', `translate(12, ${margin.top + h/2}) rotate(-90)`).attr('text-anchor','middle').text(yLabel);
}

function drawLineChart(svg, data, { xLabel, yLabel }) {
    const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 18, bottom: 40, left: 64 };
    const w = Math.max(200, width - margin.left - margin.right);
    const h = Math.max(160, height - margin.top - margin.bottom);
    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
    // Si no son fechas, trata X como ordinal index
    const isTime = data.length && data[0].key instanceof Date;
    const x = isTime
      ? d3.scaleTime().domain(d3.extent(data, d => d.key)).range([0, w])
      : d3.scalePoint().domain(data.map((_,i)=>i)).range([0, w]);
    const y = d3.scaleLinear().domain([0, d3.max(data, d => d.value) || 0]).nice().range([h, 0]);
    const line = d3.line()
      .x((d,i) => isTime ? x(d.key) : x(i))
      .y(d => y(d.value))
      .curve(d3.curveMonotoneX);
    g.append('path').datum(data).attr('fill','none').attr('stroke','#0d6efd').attr('stroke-width',2).attr('d', line);
    // puntos
    g.selectAll('circle').data(data).enter().append('circle')
      .attr('cx', (d,i)=> isTime? x(d.key) : x(i))
      .attr('cy', d => y(d.value))
      .attr('r', 3)
      .attr('fill', '#0d6efd')
      .append('title').text(d => `${xLabel}: ${formatCellValue(xLabel, d.key)}\n${yLabel}: ${formatNumber(d.value, 2)}`);
    // Ejes
    const xAxis = isTime ? d3.axisBottom(x).ticks(6).tickFormat(d3.timeFormat('%Y-%m-%d')) : d3.axisBottom(x).ticks(6);
    g.append('g').attr('transform', `translate(0,${h})`).call(xAxis);
    g.append('g').call(d3.axisLeft(y));
    // Labels
    svg.append('text').attr('x', margin.left + w/2).attr('y', height - 6).attr('text-anchor','middle').text(xLabel);
    svg.append('text').attr('transform', `translate(12, ${margin.top + h/2}) rotate(-90)`).attr('text-anchor','middle').text(yLabel);
}

function aggregateBySeries(rows, xField, yField, sField, agg) {
    const seriesMap = new Map();
    const isCount = agg === 'count';
    const isTime = rows.some(r => isDateLike(r[xField]));
    rows.forEach(r => {
        const s = r[sField] ?? '(sin serie)';
        const x = r[xField];
        const yv = isCount ? 1 : toNumber(r[yField]);
        if (!isFinite(yv)) return;
        if (!seriesMap.has(s)) seriesMap.set(s, new Map());
        const inner = seriesMap.get(s);
        inner.set(x, (inner.get(x) || 0) + yv);
    });
    // unify x domain
    const xKeys = Array.from(new Set(Array.from(seriesMap.values()).flatMap(m => Array.from(m.keys()))));
    // sort
    if (isTime) xKeys.sort((a,b) => new Date(a) - new Date(b)); else xKeys.sort();
    const seriesKeys = Array.from(seriesMap.keys());
    const seriesData = seriesKeys.map(k => {
        const m = seriesMap.get(k);
        const arr = xKeys.map(x => ({ key: isTime ? new Date(x) : x, value: m.get(x) || 0 }));
        return { key: k, values: arr };
    });
    return { isTime, xKeys: xKeys.map(x => isTime ? new Date(x) : x), seriesKeys, seriesData };
}

function drawMultiLineChart(svg, multi, { xLabel, yLabel }) {
    const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 18, bottom: 40, left: 64 };
    const w = Math.max(200, width - margin.left - margin.right);
    const h = Math.max(160, height - margin.top - margin.bottom);
    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
    const x = multi.isTime
      ? d3.scaleTime().domain(d3.extent(multi.xKeys)).range([0, w])
      : d3.scalePoint().domain(multi.xKeys.map((_,i)=>i)).range([0, w]);
    const y = d3.scaleLinear().domain([0, d3.max(multi.seriesData.flatMap(s => s.values.map(v => v.value))) || 0]).nice().range([h, 0]);
    const color = d3.scaleOrdinal().domain(multi.seriesKeys).range(d3.schemeTableau10);
    const line = d3.line()
      .x((d,i) => multi.isTime ? x(d.key) : x(i))
      .y(d => y(d.value))
      .curve(d3.curveMonotoneX);
    // lines
    g.selectAll('path.line').data(multi.seriesData).enter().append('path')
      .attr('class','line').attr('fill','none').attr('stroke-width',2)
      .attr('stroke', d=> color(d.key)).attr('d', d => line(d.values));
    // points
    multi.seriesData.forEach(s => {
      g.selectAll(`circle.pt-${CSS.escape(String(s.key))}`).data(s.values).enter().append('circle')
        .attr('cx', (d,i)=> multi.isTime? x(d.key) : x(i))
        .attr('cy', d => y(d.value))
        .attr('r', 2.5).attr('fill', color(s.key))
        .append('title').text(d => `Serie: ${s.key}\n${xLabel}: ${formatCellValue(xLabel, d.key)}\n${yLabel}: ${formatNumber(d.value, 2)}`);
    });
    // axes
    const xAxis = multi.isTime ? d3.axisBottom(x).ticks(6).tickFormat(d3.timeFormat('%Y-%m-%d')) : d3.axisBottom(x).ticks(6);
    g.append('g').attr('transform', `translate(0,${h})`).call(xAxis);
    g.append('g').call(d3.axisLeft(y));
    // labels
    svg.append('text').attr('x', margin.left + w/2).attr('y', height - 6).attr('text-anchor','middle').text(xLabel);
    svg.append('text').attr('transform', `translate(12, ${margin.top + h/2}) rotate(-90)`).attr('text-anchor','middle').text(yLabel);
    // legend
    drawLegend(svg, color, multi.seriesKeys);
}

function drawLegend(svg, color, labels) {
    if (!labels || !labels.length) return;
    const { width } = getSvgSize(svg);
    const g = svg.append('g').attr('class','legend').attr('transform', 'translate(8,8)');
    const itemH = 16; const pad = 6;
    labels.forEach((lab, i) => {
        const y = i * (itemH + 4);
        g.append('rect').attr('x',0).attr('y', y).attr('width', 14).attr('height', 14).attr('fill', color(lab));
        g.append('text').attr('x', 22).attr('y', y + 12).text(String(lab));
    });
}

function drawPieChart(svg, data) {
    const { width, height } = getSvgSize(svg);
    const radius = Math.min(width, height) / 2 - 10;
    const g = svg.append('g').attr('transform', `translate(${width/2},${height/2})`);
    const color = d3.scaleOrdinal().domain(data.map(d=>d.key)).range(d3.schemeCategory10);
    const pie = d3.pie().sort(null).value(d => d.value);
    const arc = d3.arc().innerRadius(0).outerRadius(radius);
    const arcs = g.selectAll('path').data(pie(data)).enter().append('path')
      .attr('fill', d => color(d.data.key)).attr('d', arc).append('title')
      .text(d => `${d.data.key}: ${formatNumber(d.data.value, 2)}`);
    drawLegend(svg, color, data.map(d=>d.key));
}

function drawDonutChart(svg, data) {
    const { width, height } = getSvgSize(svg);
    const radius = Math.min(width, height) / 2 - 10;
    const g = svg.append('g').attr('transform', `translate(${width/2},${height/2})`);
    const color = d3.scaleOrdinal().domain(data.map(d=>d.key)).range(d3.schemeTableau10);
    const pie = d3.pie().sort(null).value(d => d.value);
    const arc = d3.arc().innerRadius(Math.max(30, radius*0.5)).outerRadius(radius);
    g.selectAll('path').data(pie(data)).enter().append('path')
      .attr('fill', d => color(d.data.key)).attr('d', arc)
      .append('title').text(d => `${d.data.key}: ${formatNumber(d.data.value, 2)}`);
    drawLegend(svg, color, data.map(d=>d.key));
}

function drawWaffleChart(svg, data) {
    const total = d3.sum(data, d=>d.value) || 1;
    const n = 100; const cols = 10; const size = 18; const gap=2;
    const { width, height } = getSvgSize(svg);
    svg.attr('height', Math.max(height, Math.ceil(n/cols)*(size+gap)+20));
    const squares = [];
    data.forEach(d => { const count = Math.round(d.value / total * n); for (let i=0;i<count;i++) squares.push({ key: d.key }); });
    squares.length = Math.min(squares.length, n);
    const color = d3.scaleOrdinal().domain(data.map(d=>d.key)).range(d3.schemeTableau10);
    const g = svg.append('g').attr('transform', 'translate(10,10)');
    g.selectAll('rect').data(squares).enter().append('rect')
      .attr('x', (d,i)=> (i%cols)*(size+gap))
      .attr('y', (d,i)=> Math.floor(i/cols)*(size+gap))
      .attr('width', size).attr('height', size)
      .attr('fill', d=>color(d.key));
    drawLegend(svg, color, data.map(d=>d.key));
}

function drawFunnelChart(svg, data) {
    data = data.slice().sort((a,b)=>b.value-a.value);
    const { width, height } = getSvgSize(svg);
    const margin = { top: 20, right: 20, bottom: 20, left: 20 };
    const w = width - margin.left - margin.right;
    const h = height - margin.top - margin.bottom;
    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
    const x = d3.scaleLinear().domain([0, d3.max(data,d=>d.value)||1]).range([0, w]);
    const stepH = h / data.length;
    const color = d3.scaleOrdinal().domain(data.map(d=>d.key)).range(d3.schemeTableau10);
    data.forEach((d,i)=>{
        const ww = x(d.value);
        const x0 = (w-ww)/2;
        g.append('rect').attr('x', x0).attr('y', i*stepH + 4)
         .attr('width', ww).attr('height', stepH - 8)
         .attr('fill', color(d.key)).append('title').text(`${d.key}: ${formatNumber(d.value,2)}`);
        g.append('text').attr('x', w/2).attr('y', i*stepH + stepH/2).attr('text-anchor','middle').attr('dominant-baseline','middle')
         .text(`${d.key} (${formatNumber(d.value,0)})`);
    });
    drawLegend(svg, color, data.map(d=>d.key));
}

function drawParetoChart(svg, data, { xLabel }) {
    const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 48, bottom: 64, left: 64 };
    const w = width - margin.left - margin.right;
    const h = height - margin.top - margin.bottom;
    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
    const x = d3.scaleBand().domain(data.map(d=>d.key)).range([0, w]).padding(0.15);
    const max = d3.max(data, d=>d.value)||0;
    const y = d3.scaleLinear().domain([0, max]).nice().range([h,0]);
    const cum = []; let acc=0; const total = d3.sum(data,d=>d.value)||1;
    data.forEach(d=>{ acc+=d.value; cum.push(acc/total*100); });
    const y2 = d3.scaleLinear().domain([0,100]).range([h,0]);
    g.append('g').attr('transform',`translate(0,${h})`).call(d3.axisBottom(x)).selectAll('text').attr('transform','rotate(-35)').style('text-anchor','end');
    g.append('g').call(d3.axisLeft(y));
    g.append('g').attr('transform',`translate(${w},0)`).call(d3.axisRight(y2));
    g.selectAll('rect').data(data).enter().append('rect')
      .attr('x', d=>x(d.key)).attr('y', d=>y(d.value))
      .attr('width', x.bandwidth()).attr('height', d=>h-y(d.value)).attr('fill','#0d6efd');
    const line = d3.line().x((d,i)=> x(data[i].key)+x.bandwidth()/2).y(d=>y2(d)).curve(d3.curveMonotoneX);
    g.append('path').datum(cum).attr('fill','none').attr('stroke','#dc3545').attr('stroke-width',2).attr('d', line);
}

function nestByXSeries(rows, xField, sField, yField, agg) {
    const map = new Map();
    rows.forEach(r=>{ const x = r[xField]; const s = r[sField]; const val = agg==='count' ? 1 : toNumber(r[yField]); if (!isFinite(val)) return; if (!map.has(x)) map.set(x, new Map()); const inner = map.get(x); inner.set(s, (inner.get(s)||0) + val); });
    const seriesKeys = Array.from(new Set(Array.from(map.values()).flatMap(m=>Array.from(m.keys()))));
    const xs = Array.from(map.keys());
    const arr = xs.map(x=>{ const byS = map.get(x); const obj = { x }; seriesKeys.forEach(s=>{ obj[s] = byS.get(s)||0; }); return obj; });
    return { data: arr, xKeys: xs, seriesKeys };
}

function drawStackedBarChart(svg, nested, { xLabel, yLabel }) {
    const { data, xKeys, seriesKeys } = nested;
    const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 18, bottom: 64, left: 64 };
    const w = width - margin.left - margin.right;
    const h = height - margin.top - margin.bottom;
    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
    const x = d3.scaleBand().domain(xKeys).range([0, w]).padding(0.15);
    const stack = d3.stack().keys(seriesKeys);
    const stacked = stack(data);
    const y = d3.scaleLinear().domain([0, d3.max(stacked[stacked.length-1], d=>d[1])||0]).nice().range([h,0]);
    const color = d3.scaleOrdinal().domain(seriesKeys).range(d3.schemeTableau10);
    g.append('g').attr('transform',`translate(0,${h})`).call(d3.axisBottom(x)).selectAll('text').attr('transform','rotate(-35)').style('text-anchor','end');
    g.append('g').call(d3.axisLeft(y));
    const layer = g.selectAll('.layer').data(stacked).enter().append('g').attr('fill', d=>color(d.key));
    layer.selectAll('rect').data(d=>d).enter().append('rect')
        .attr('x', (d,i)=> x(data[i].x))
        .attr('y', d=> y(d[1]))
        .attr('height', d=> y(d[0]) - y(d[1]))
        .attr('width', x.bandwidth());
    drawLegend(svg, color, seriesKeys);
}

function drawScatterChart(svg, points, { xLabel, yLabel }) {
    const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 18, bottom: 48, left: 56 };
    const w = width - margin.left - margin.right;
    const h = height - margin.top - margin.bottom;
    const g = svg.append('g').attr('transform',`translate(${margin.left},${margin.top})`);
    const x = d3.scaleLinear().domain(d3.extent(points,d=>d.x)).nice().range([0,w]);
    const y = d3.scaleLinear().domain(d3.extent(points,d=>d.y)).nice().range([h,0]);
    const seriesVals = Array.from(new Set(points.map(p=>p.s).filter(Boolean)));
    const color = d3.scaleOrdinal().domain(seriesVals).range(d3.schemeTableau10);
    g.append('g').attr('transform',`translate(0,${h})`).call(d3.axisBottom(x));
    g.append('g').call(d3.axisLeft(y));
    g.selectAll('circle').data(points).enter().append('circle')
      .attr('cx', d=>x(d.x)).attr('cy', d=>y(d.y)).attr('r', 3.5)
      .attr('fill', d=> d.s ? color(d.s) : '#0d6efd')
      .append('title').text(d=>`${xLabel}: ${d.x}\n${yLabel}: ${d.y}${d.s?`\nSerie: ${d.s}`:''}`);
    if (seriesVals.length) drawLegend(svg, color, seriesVals);
}

function groupNumeric(rows, xField, yField) {
    const map = new Map(); rows.forEach(r=>{ const k = r[xField]; const v = toNumber(r[yField]); if (!isFinite(v)) return; if (!map.has(k)) map.set(k, []); map.get(k).push(v); }); return map;
}

function drawBoxPlot(svg, groups, { xLabel, yLabel }) {
    const cats = Array.from(groups.keys());
    const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 18, bottom: 64, left: 64 };
    const w = width - margin.left - margin.right;
    const h = height - margin.top - margin.bottom;
    const g = svg.append('g').attr('transform',`translate(${margin.left},${margin.top})`);
    const all = Array.from(groups.values()).flat();
    const y = d3.scaleLinear().domain(d3.extent(all)).nice().range([h,0]);
    const x = d3.scaleBand().domain(cats).range([0,w]).paddingInner(0.3).paddingOuter(0.2);
    g.append('g').attr('transform',`translate(0,${h})`).call(d3.axisBottom(x)).selectAll('text').attr('transform','rotate(-35)').style('text-anchor','end');
    g.append('g').call(d3.axisLeft(y));
    cats.forEach(cat=>{
        const arr = groups.get(cat).sort((a,b)=>a-b);
        if (!arr.length) return;
        const q1 = d3.quantile(arr, 0.25); const med = d3.quantile(arr, 0.5); const q3 = d3.quantile(arr, 0.75);
        const iqr = (q3 - q1);
        const w1 = Math.max(d3.min(arr), q1 - 1.5*iqr);
        const w2 = Math.min(d3.max(arr), q3 + 1.5*iqr);
        const cx = x(cat) + x.bandwidth()/2; const boxW = Math.max(10, x.bandwidth()*0.6);
        g.append('line').attr('x1',cx).attr('x2',cx).attr('y1', y(w1)).attr('y2', y(w2)).attr('stroke','#555');
        g.append('rect').attr('x', cx - boxW/2).attr('y', y(q3)).attr('width', boxW).attr('height', y(q1)-y(q3)).attr('fill','#cfe2ff').attr('stroke','#0d6efd');
        g.append('line').attr('x1',cx - boxW/2).attr('x2', cx + boxW/2).attr('y1', y(med)).attr('y2', y(med)).attr('stroke','#0d6efd').attr('stroke-width',2);
    });
}

function kde(kernel, thresholds, data) { return thresholds.map(t => [t, d3.mean(data, d => kernel(t - d))]); }
function epanechnikov(k) { return v => Math.abs(v /= k) <= 1 ? 0.75 * (1 - v * v) / k : 0; }

function drawViolinPlot(svg, groups, { xLabel, yLabel }) {
    const cats = Array.from(groups.keys());
    const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 18, bottom: 64, left: 64 };
    const w = width - margin.left - margin.right;
    const h = height - margin.top - margin.bottom;
    const g = svg.append('g').attr('transform',`translate(${margin.left},${margin.top})`);
    const all = Array.from(groups.values()).flat();
    const y = d3.scaleLinear().domain(d3.extent(all)).nice().range([h,0]);
    const x = d3.scaleBand().domain(cats).range([0,w]).paddingInner(0.3).paddingOuter(0.2);
    g.append('g').attr('transform',`translate(0,${h})`).call(d3.axisBottom(x)).selectAll('text').attr('transform','rotate(-35)').style('text-anchor','end');
    g.append('g').call(d3.axisLeft(y));
    const kdeX = d3.range(y.domain()[0], y.domain()[1], (y.domain()[1]-y.domain()[0])/50);
    cats.forEach(cat=>{
        const arr = groups.get(cat).sort((a,b)=>a-b);
        if (!arr.length) return;
        const density = kde(epanechnikov( (d3.deviation(arr)||1) * 0.3 ), kdeX, arr);
        const xNum = d3.scaleLinear().range([0, x.bandwidth()]).domain([0, d3.max(density, d=>d[1])||1]);
        const area = d3.area()
            .x0(d => x(cat) + x.bandwidth()/2 - xNum(d[1]))
            .x1(d => x(cat) + x.bandwidth()/2 + xNum(d[1]))
            .y(d => y(d[0]))
            .curve(d3.curveCatmullRom);
        g.append('path').datum(density).attr('fill', '#cfe2ff').attr('stroke', '#0d6efd').attr('d', area);
    });
}

function drawBulletChart(svg, items) {
    const { width, height } = getSvgSize(svg);
    const margin = { top: 16, right: 32, bottom: 16, left: 120 };
    const w = width - margin.left - margin.right;
    const bulletH = 26; const gap = 12;
    const h = items.length * (bulletH + gap) + 20;
    svg.attr('height', h + margin.top + margin.bottom);
    const g = svg.append('g').attr('transform',`translate(${margin.left},${margin.top})`);
    const max = d3.max(items, d=>Math.max(d.value, d.target||0)) || 1;
    const x = d3.scaleLinear().domain([0,max]).range([0,w]);
    items.forEach((it,i)=>{
        const y = i*(bulletH+gap);
        g.append('text').attr('x', -8).attr('y', y + bulletH/2).attr('text-anchor','end').attr('dominant-baseline','middle').text(it.category);
        g.append('rect').attr('x',0).attr('y', y+6).attr('width', w).attr('height', bulletH-12).attr('fill','#f1f3f5');
        g.append('rect').attr('x',0).attr('y', y+8).attr('width', x(it.value)).attr('height', bulletH-16).attr('fill','#0d6efd')
          .append('title').text(`${it.category}: ${formatNumber(it.value,2)}${isFinite(it.target)?` (obj: ${formatNumber(it.target,2)})`:''}`);
        if (isFinite(it.target) && it.target>0) { const tx = x(it.target); g.append('line').attr('x1',tx).attr('x2',tx).attr('y1', y+4).attr('y2', y+bulletH-4).attr('stroke','#dc3545').attr('stroke-width',2); }
    });
}

function crossAggregate(rows, xField, yCatField, yField, agg) {
    const xCats = Array.from(new Set(rows.map(r=>r[xField])));
    const yCats = Array.from(new Set(rows.map(r=>r[yCatField])));
    const map = new Map(); xCats.forEach(x=>{ const inner=new Map(); yCats.forEach(y=>inner.set(y,0)); map.set(x, inner); });
    rows.forEach(r=>{ const x=r[xField]; const y=r[yCatField]; if(!map.has(x)||!map.get(x).has(y))return; const add = agg==='count'?1:toNumber(r[yField]); if(!isFinite(add) && agg!=='count') return; map.get(x).set(y, (map.get(x).get(y)||0) + (isFinite(add)?add:0)); });
    return { xCats, yCats, map };
}

function drawHeatmap(svg, matrix, { xLabel, yLabel, percentByRow=false }) {
    const { xCats, yCats, map } = matrix;
    const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 18, bottom: 64, left: 100 };
    const w = width - margin.left - margin.right; const h = height - margin.top - margin.bottom;
    const g = svg.append('g').attr('transform',`translate(${margin.left},${margin.top})`);
    const x = d3.scaleBand().domain(xCats).range([0,w]).padding(0.05);
    const y = d3.scaleBand().domain(yCats).range([0,h]).padding(0.05);
    let vals = []; xCats.forEach(xc=>{ const inner = map.get(xc); let rowSum=0; if(percentByRow) inner.forEach(v=>rowSum+=v); yCats.forEach(yc=>{ const raw=inner.get(yc)||0; const v = percentByRow && rowSum>0 ? (raw/rowSum*100) : raw; vals.push(v); }); });
    const color = d3.scaleSequential(d3.interpolateYlOrRd).domain([0, d3.max(vals)||1]);
    g.append('g').attr('transform',`translate(0,${h})`).call(d3.axisBottom(x)).selectAll('text').attr('transform','rotate(-35)').style('text-anchor','end');
    g.append('g').call(d3.axisLeft(y));
    const cells = []; xCats.forEach(xc=>{ yCats.forEach(yc=>{ const val = map.get(xc).get(yc)||0; const denom = percentByRow? Array.from(map.get(xc).values()).reduce((a,b)=>a+b,0):1; const v = percentByRow && denom>0 ? val/denom*100 : val; cells.push({xc,yc,v}); }); });
    g.selectAll('rect').data(cells).enter().append('rect')
      .attr('x', d=>x(d.xc)).attr('y', d=>y(d.yc)).attr('width', x.bandwidth()).attr('height', y.bandwidth())
      .attr('fill', d=>color(d.v))
      .append('title').text(d=>`${xLabel}: ${d.xc}\n${yLabel}: ${d.yc}\n${percentByRow? 'Pct': 'Valor'}: ${formatNumber(d.v, percentByRow?0:2)}`);
}

function parseDate(v) { if (!v) return null; const s = String(v); const d = new Date(s); if (!isNaN(d)) return d; return null; }

function drawGantt(svg, tasks) {
    if (!tasks.length) return; const { width, height } = getSvgSize(svg);
    const margin = { top: 24, right: 18, bottom: 40, left: 160 };
    const w = width - margin.left - margin.right; const rowH = 22; const gap=6;
    const h = tasks.length*(rowH+gap)+10; svg.attr('height', h + margin.top + margin.bottom);
    const g = svg.append('g').attr('transform',`translate(${margin.left},${margin.top})`);
    const min = d3.min(tasks, t=>t.start); const max = d3.max(tasks, t=>t.end);
    const x = d3.scaleTime().domain([min,max]).range([0,w]); const y = d3.scaleBand().domain(tasks.map(t=>t.label)).range([0,h]).padding(0.2);
    g.append('g').attr('transform',`translate(0,${h})`).call(d3.axisBottom(x)); g.append('g').call(d3.axisLeft(y));
    g.selectAll('rect').data(tasks).enter().append('rect')
      .attr('x', d=>x(d.start)).attr('y', d=>y(d.label))
      .attr('width', d=>x(d.end)-x(d.start)).attr('height', y.bandwidth())
      .attr('fill','#0d6efd')
      .append('title').text(d=>`${d.label}: ${d.start.toISOString().slice(0,10)} → ${d.end.toISOString().slice(0,10)}`);
}

function buildHierarchy(rows, levels, valueField, agg) {
    function addNode(node, path, val) { if (!path.length) { node.value = (node.value||0) + val; return; } const k = path[0] || '(vacío)'; node.children = node.children || []; let child = node.children.find(c=>c.name===k); if (!child) { child = { name: k }; node.children.push(child); } addNode(child, path.slice(1), val); }
    const root = { name: 'root', children: [] };
    rows.forEach(r=>{ const path = levels.map(l=> String(r[l])); const val = agg==='count' || !valueField ? 1 : toNumber(r[valueField]); if (!isFinite(val)) return; addNode(root, path, val); });
    return root;
}

function drawTreemap(svg, rootData) {
    const { width, height } = getSvgSize(svg);
    const root = d3.hierarchy(rootData).sum(d=>d.value||0).sort((a,b)=>b.value-a.value);
    d3.treemap().size([width, height]).padding(2)(root);
    const color = d3.scaleOrdinal().domain(root.children? root.children.map(c=>c.data.name):[]).range(d3.schemeTableau10);
    const g = svg.append('g'); const leaves = root.leaves();
    const nodes = g.selectAll('g.leaf').data(leaves).enter().append('g').attr('class','leaf').attr('transform', d=>`translate(${d.x0},${d.y0})`);
    nodes.append('rect').attr('width', d=>d.x1-d.x0).attr('height', d=>d.y1-d.y0).attr('fill', d=> color(d.ancestors()[1]?.data.name || ''));
    nodes.append('title').text(d=> `${d.ancestors().map(a=>a.data.name).reverse().slice(1).join(' / ')}: ${formatNumber(d.value,2)}`);
    nodes.append('text').attr('x',4).attr('y',14).text(d=> d.data.name).attr('fill','#000');
}

function drawSunburst(svg, rootData) {
    const { width, height } = getSvgSize(svg); const radius = Math.min(width, height)/2 - 4;
    const root = d3.hierarchy(rootData).sum(d=>d.value||0).sort((a,b)=>b.value-a.value);
    d3.partition().size([2*Math.PI, radius])(root);
    const color = d3.scaleOrdinal().domain(root.children? root.children.map(c=>c.data.name):[]).range(d3.schemeTableau10);
    const g = svg.append('g').attr('transform',`translate(${width/2},${height/2})`);
    const arc = d3.arc().startAngle(d=>d.x0).endAngle(d=>d.x1).innerRadius(d=>d.y0).outerRadius(d=>d.y1);
    g.selectAll('path').data(root.descendants().filter(d=>d.depth)).enter().append('path')
      .attr('d', arc).attr('fill', d=> color(d.ancestors()[1]?.data.name || ''))
      .append('title').text(d=> `${d.ancestors().map(a=>a.data.name).reverse().slice(1).join(' / ')}: ${formatNumber(d.value,2)}`);
}
