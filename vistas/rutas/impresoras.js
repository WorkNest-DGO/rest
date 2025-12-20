function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;

let impresoras = [];

async function cargarImpresoras() {
    try {
        const resp = await fetch('../../api/rutas/listar_impresoras.php');
        const data = await resp.json();
        const tbody = document.querySelector('#tablaImpresoras tbody');
        tbody.innerHTML = '';
        if (data.success) {
            impresoras = data.resultado || [];
            impresoras.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${r.print_id}</td><td>${r.nombre_logico}</td><td>${r.lugar}</td><td>${r.ip}</td><td>${r.activo == 1 ? 'Si' : 'No'}</td><td>${r.sede ?? ''}</td>`;
                const tdAcc = document.createElement('td');
                const btnE = document.createElement('button');
                btnE.className = 'btn custom-btn me-2';
                btnE.textContent = 'Editar';
                btnE.onclick = () => editarImpresora(r.print_id);
                const btnD = document.createElement('button');
                btnD.className = 'btn custom-btn';
                btnD.textContent = 'Eliminar';
                btnD.onclick = () => eliminarImpresora(r.print_id);
                tdAcc.appendChild(btnE);
                tdAcc.appendChild(btnD);
                tr.appendChild(tdAcc);
                tbody.appendChild(tr);
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar impresoras');
    }
}

document.getElementById('btnAgregar').onclick = () => {
    const form = document.getElementById('formImpresora');
    form.reset();
    document.getElementById('printIdOriginal').value = '';
    const printId = document.getElementById('printId');
    printId.disabled = false;
    showModal('#modalImpresora');
};

document.getElementById('formImpresora').onsubmit = async ev => {
    ev.preventDefault();
    const original = document.getElementById('printIdOriginal').value;
    const payload = {
        print_id: parseInt(document.getElementById('printId').value, 10),
        nombre_logico: document.getElementById('nombreLogico').value,
        lugar: document.getElementById('lugar').value,
        ip: document.getElementById('ip').value,
        activo: parseInt(document.getElementById('activo').value, 10),
        sede: parseInt(document.getElementById('sede').value, 10)
    };
    let url = '../../api/rutas/agregar_impresora.php';
    if (original) {
        payload.print_id = parseInt(original, 10);
        url = '../../api/rutas/editar_impresora.php';
    }
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        alert(data.mensaje);
        if (data.success) {
            hideModal('#modalImpresora');
            cargarImpresoras();
        }
    } catch (err) {
        console.error(err);
        alert('Error al guardar impresora');
    }
};

function editarImpresora(printId) {
    const r = impresoras.find(x => parseInt(x.print_id, 10) === parseInt(printId, 10));
    if (!r) return;
    document.getElementById('printIdOriginal').value = r.print_id;
    const printIdInput = document.getElementById('printId');
    printIdInput.value = r.print_id;
    printIdInput.disabled = true;
    const nombreSelect = document.getElementById('nombreLogico');
    const existe = Array.from(nombreSelect.options).some(opt => opt.value === r.nombre_logico);
    if (!existe && r.nombre_logico) {
        const opt = document.createElement('option');
        opt.value = r.nombre_logico;
        opt.textContent = r.nombre_logico;
        nombreSelect.appendChild(opt);
    }
    nombreSelect.value = r.nombre_logico;
    document.getElementById('lugar').value = r.lugar;
    document.getElementById('ip').value = r.ip;
    document.getElementById('activo').value = r.activo;
    document.getElementById('sede').value = r.sede ?? '';
    showModal('#modalImpresora');
}

async function eliminarImpresora(printId) {
    if (!confirm('Eliminar impresora?')) return;
    try {
        const resp = await fetch('../../api/rutas/eliminar_impresora.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ print_id: parseInt(printId, 10) })
        });
        const data = await resp.json();
        alert(data.mensaje);
        if (data.success) cargarImpresoras();
    } catch (err) {
        console.error(err);
        alert('Error al eliminar impresora');
    }
}

let __IMP_ALL = [];
let __IMP_FIL = [];
let __IMP_PAGE = 1;
const __IMP_PP = 20;

function __impRender() {
    const pag = document.getElementById('paginadorImpresoras');
    const total = Math.max(1, Math.ceil(__IMP_FIL.length / __IMP_PP));
    if (__IMP_PAGE > total) __IMP_PAGE = total;
    const ini = (__IMP_PAGE - 1) * __IMP_PP, fin = ini + __IMP_PP;
    __IMP_ALL.forEach(tr => tr.style.display = 'none');
    __IMP_FIL.forEach((tr, idx) => tr.style.display = (idx >= ini && idx < fin) ? '' : 'none');
    if (!pag) return;
    pag.innerHTML = '';
    const prevLi = document.createElement('li');
    prevLi.className = 'page-item' + (__IMP_PAGE === 1 ? ' disabled' : '');
    const prevA = document.createElement('a');
    prevA.className = 'page-link';
    prevA.href = '#';
    prevA.textContent = 'Anterior';
    prevA.addEventListener('click', e => { e.preventDefault(); if (__IMP_PAGE > 1) { __IMP_PAGE--; __impRender(); } });
    prevLi.appendChild(prevA);
    pag.appendChild(prevLi);
    for (let i = 1; i <= total; i++) {
        const li = document.createElement('li');
        li.className = 'page-item' + (i === __IMP_PAGE ? ' active' : '');
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = String(i);
        a.addEventListener('click', e => { e.preventDefault(); __IMP_PAGE = i; __impRender(); });
        li.appendChild(a);
        pag.appendChild(li);
    }
    const nextLi = document.createElement('li');
    nextLi.className = 'page-item' + (__IMP_PAGE === total ? ' disabled' : '');
    const nextA = document.createElement('a');
    nextA.className = 'page-link';
    nextA.href = '#';
    nextA.textContent = 'Siguiente';
    nextA.addEventListener('click', e => { e.preventDefault(); if (__IMP_PAGE < total) { __IMP_PAGE++; __impRender(); } });
    nextLi.appendChild(nextA);
    pag.appendChild(nextLi);
}

function __impInit() {
    __IMP_ALL = Array.from(document.querySelectorAll('#tablaImpresoras tbody tr'));
    __IMP_FIL = __IMP_ALL.slice();
    __IMP_PAGE = 1;
    __impRender();
}

window.addEventListener('DOMContentLoaded', () => {
    cargarImpresoras();
    const tbody = document.querySelector('#tablaImpresoras tbody');
    if (tbody) {
        const obs = new MutationObserver(() => { __impInit(); });
        obs.observe(tbody, { childList: true });
    }
    const buscar = document.getElementById('buscarImpresora');
    if (buscar) {
        let t;
        buscar.addEventListener('input', e => {
            clearTimeout(t);
            t = setTimeout(() => {
                const q = (e.target.value || '').toLowerCase();
                __IMP_FIL = __IMP_ALL.filter(tr => tr.innerText.toLowerCase().includes(q));
                __IMP_PAGE = 1;
                __impRender();
            }, 250);
        });
    }
});
