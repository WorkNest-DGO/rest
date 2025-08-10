/*
 * Maneja la tabla dinámica de vistas SQL.
 * Para agregar nuevas vistas, actualiza este mapa y la lista en
 * /api/reportes/vistas_db.php.
 */
const viewLabels = {
    vista_productos_mas_vendidos: 'Productos más vendidos',
    vista_resumen_cortes: 'Resumen de cortes',
    vista_resumen_pagos: 'Resumen de pagos',
    vista_ventas_diarias: 'Ventas diarias',
    vista_ventas_por_mesero: 'Ventas por mesero',
    vw_consumo_insumos: 'Consumo de insumos',
    vw_corte_resumen: 'Corte resumen',
    vw_ventas_detalladas: 'Ventas detalladas',
    logs_accion: 'Log de acciones',
    log_asignaciones_mesas: 'Asignaciones de mesas (log)',
    log_mesas: 'Log de mesas',
    movimientos_insumos: 'Movimientos de insumos',
    fondo: 'Fondo de caja',
    insumos: 'Insumos',
    tickets: 'Tickets',
    ventas: 'Ventas',
    qrs_insumo: 'QR de insumos'
};

let currentView = sessionStorage.getItem('vistas_db_view') || 'vista_productos_mas_vendidos';
let page = 1;
let perPage = parseInt(sessionStorage.getItem('vistas_db_per_page') || '15', 10);
let search = '';
let sortBy = '';
let sortDir = 'asc';

const selectVista = document.getElementById('selectVista');
const searchInput = document.getElementById('searchInput');
const perPageSelect = document.getElementById('perPage');
const thead = document.querySelector('#tablaVista thead');
const tbody = document.querySelector('#tablaVista tbody');
const loader = document.getElementById('loader');
const errorDiv = document.getElementById('error');
const paginacion = document.getElementById('paginacion');
const paginaInfo = document.getElementById('paginaInfo');
const prevBtn = document.getElementById('prevPage');
const nextBtn = document.getElementById('nextPage');

function buildViewOptions() {
    selectVista.innerHTML = '';
    Object.entries(viewLabels).forEach(([value, label]) => {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        if (value === currentView) opt.selected = true;
        selectVista.appendChild(opt);
    });
}

function sanitize(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : text;
    return div.innerHTML;
}

async function fetchColumnsAndData() {
    loader.style.display = 'block';
    errorDiv.style.display = 'none';
    const params = new URLSearchParams({
        view: currentView,
        page: page,
        per_page: perPage
    });
    if (search) params.append('search', search);
    if (sortBy) {
        params.append('sort_by', sortBy);
        params.append('sort_dir', sortDir);
    }
    try {
        const res = await fetch(`../../api/reportes/vistas_db.php?${params.toString()}`);
        if (!res.ok) throw new Error('HTTP');
        const data = await res.json();
        renderTable(data.columns, data.rows);
        renderPagination(data.total, data.page, data.per_page);
    } catch (e) {
        thead.innerHTML = '';
        tbody.innerHTML = '';
        errorDiv.textContent = 'Error al cargar datos';
        errorDiv.style.display = 'block';
        paginacion.style.display = 'none';
    } finally {
        loader.style.display = 'none';
    }
}

function renderTable(columns, rows) {
    const fragHead = document.createDocumentFragment();
    const trHead = document.createElement('tr');
    columns.forEach(col => {
        const th = document.createElement('th');
        th.textContent = col;
        th.dataset.column = col;
        if (sortBy === col) th.classList.add('ordenado', sortDir);
        trHead.appendChild(th);
    });
    fragHead.appendChild(trHead);
    thead.innerHTML = '';
    thead.appendChild(fragHead);

    const fragBody = document.createDocumentFragment();
    if (!rows.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.textContent = 'Sin resultados';
        td.colSpan = columns.length;
        tr.appendChild(td);
        fragBody.appendChild(tr);
    } else {
        rows.forEach(row => {
            const tr = document.createElement('tr');
            columns.forEach(col => {
                const td = document.createElement('td');
                td.innerHTML = sanitize(row[col]);
                const lower = col.toLowerCase();
                if (lower.includes('fecha')) {
                    td.style.textAlign = 'center';
                } else if (/total|monto|propina|precio|cantidad|importe|numero|id/.test(lower)) {
                    td.style.textAlign = 'right';
                }
                tr.appendChild(td);
            });
            fragBody.appendChild(tr);
        });
    }
    tbody.innerHTML = '';
    tbody.appendChild(fragBody);
}

function renderPagination(total, pageNow, perPageNow) {
    const totalPages = Math.max(1, Math.ceil(total / perPageNow));
    paginaInfo.textContent = `Página ${pageNow} de ${totalPages}`;
    prevBtn.disabled = pageNow <= 1;
    nextBtn.disabled = pageNow >= totalPages;
    paginacion.style.display = 'block';
}

function attachEvents() {
    selectVista.addEventListener('change', () => {
        currentView = selectVista.value;
        sessionStorage.setItem('vistas_db_view', currentView);
        page = 1;
        fetchColumnsAndData();
    });

    perPageSelect.value = perPage;
    perPageSelect.addEventListener('change', () => {
        perPage = parseInt(perPageSelect.value, 10);
        sessionStorage.setItem('vistas_db_per_page', perPage);
        page = 1;
        fetchColumnsAndData();
    });

    let debounce;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            search = searchInput.value.trim();
            page = 1;
            fetchColumnsAndData();
        }, 300);
    });

    prevBtn.addEventListener('click', () => {
        if (page > 1) {
            page--;
            fetchColumnsAndData();
        }
    });
    nextBtn.addEventListener('click', () => {
        page++;
        fetchColumnsAndData();
    });

    thead.addEventListener('click', e => {
        if (e.target.tagName === 'TH') {
            const col = e.target.dataset.column;
            if (sortBy === col) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortBy = col;
                sortDir = 'asc';
            }
            fetchColumnsAndData();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    buildViewOptions();
    attachEvents();
    fetchColumnsAndData();
});