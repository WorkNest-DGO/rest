<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Ticket';
ob_start();
?>
<div id="dividir" style="display:none;">
    <h2>Dividir venta</h2>
    <table id="tablaProductos" border="1">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant</th>
                <th>Precio</th>
                <th>Subcuenta</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
    <button id="agregarSub">Agregar subcuenta</button>
    <button id="guardarSub">Guardar Tickets</button>
    <div id="subcuentas"></div>
</div>

<div id="imprimir" style="display:none;">
    <h2 id="nombreRestaurante">Mi Restaurante</h2>
    <div id="fechaHora"></div>
    <div>Folio: <span id="folio"></span></div>
    <div>Venta: <span id="ventaId"></span></div>
    <table id="productos">
        <tbody></tbody>
    </table>
    <div id="propina"></div>
    <div id="totalVenta"></div>
    <p>Gracias por su compra</p>
    <button id="btnImprimir" onclick="window.print()">Imprimir</button>
</div>
<style>
        body {
            width: 58mm;
            margin: 0 auto;
            font-family: Courier, monospace;
            font-size: 12px;
            text-align: center;
        }
        table {
            width: 100%;
        }
        #productos td {
            text-align: left;
        }
        #productos td:last-child {
            text-align: right;
        }
        #totalVenta {
            font-size: 1.2em;
            font-weight: bold;
            margin-top: 10px;
        }
        @media print {
            #btnImprimir {
                display: none;
            }
        }
</style>
<script>
function llenarTicket(data) {
    document.getElementById('ventaId').textContent = data.venta_id;
    document.getElementById('fechaHora').textContent = data.fecha;
    document.getElementById('folio').textContent = data.folio || '';
    if (data.restaurante) {
        document.getElementById('nombreRestaurante').textContent = data.restaurante;
    }
    const tbody = document.querySelector('#productos tbody');
    tbody.innerHTML = '';
    data.productos.forEach(p => {
        const tr = document.createElement('tr');
        const subtotal = p.cantidad * p.precio_unitario;
        tr.innerHTML = `<td>${p.nombre}</td><td>${p.cantidad} x ${p.precio_unitario} = ${subtotal}</td>`;
        tbody.appendChild(tr);
    });
    if (typeof data.propina !== 'undefined') {
        document.getElementById('propina').textContent = 'Propina: $' + data.propina.toFixed(2);
    }
    document.getElementById('totalVenta').textContent = 'Total: $' + data.total;
}

document.addEventListener('DOMContentLoaded', async () => {
    const params = new URLSearchParams(window.location.search);
    const imprimir = params.get('print') === '1';
    const almacenado = localStorage.getItem('ticketData');
    if (!almacenado) return;
    const datos = JSON.parse(almacenado);
    if (imprimir) {
        document.getElementById('imprimir').style.display = 'block';
        llenarTicket(datos);
        liberarMesa(datos.venta_id);
    } else {
        await cargarSeries();
        inicializarDividir(datos);
    }
});

let series = [];
async function cargarSeries() {
    try {
        const resp = await fetch('../../api/tickets/listar_series.php');
        const data = await resp.json();
        if (data.success) {
            series = data.resultado;
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar series');
    }
}

let productos = [];
let numSub = 1;
let ticketsGuardados = [];
const seleccionSeries = {};

function inicializarDividir(data) {
    document.getElementById('dividir').style.display = 'block';
    productos = data.productos.map(p => Object.assign({ subcuenta: 1 }, p));
    renderProductos();
    renderSubcuentas();
    document.getElementById('agregarSub').addEventListener('click', () => {
        numSub++;
        actualizarSelects();
        renderSubcuentas();
    });
    document.getElementById('guardarSub').addEventListener('click', guardarSubcuentas);
}

function renderProductos() {
    const tbody = document.querySelector('#tablaProductos tbody');
    tbody.innerHTML = '';
    productos.forEach(p => {
        const tr = document.createElement('tr');
        const sel = document.createElement('select');
        for (let i = 1; i <= numSub; i++) {
            const opt = document.createElement('option');
            opt.value = i;
            opt.textContent = i;
            sel.appendChild(opt);
        }
        sel.value = p.subcuenta;
        sel.addEventListener('change', () => {
            p.subcuenta = parseInt(sel.value);
            renderSubcuentas();
        });
        tr.innerHTML = `<td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td>`;
        const td = document.createElement('td');
        td.appendChild(sel);
        tr.appendChild(td);
        tbody.appendChild(tr);
    });
}

function actualizarSelects() {
    document.querySelectorAll('#tablaProductos select').forEach(sel => {
        const val = sel.value;
        sel.innerHTML = '';
        for (let i = 1; i <= numSub; i++) {
            const opt = document.createElement('option');
            opt.value = i;
            opt.textContent = i;
            sel.appendChild(opt);
        }
        sel.value = val;
    });
}

function renderSubcuentas() {
    const cont = document.getElementById('subcuentas');
    cont.innerHTML = '';
    for (let i = 1; i <= numSub; i++) {
        const div = document.createElement('div');
        div.id = 'sub' + i;
        const prods = productos.filter(p => p.subcuenta === i);
        let html = `<h3>Subcuenta ${i}</h3><table><tbody>`;
        prods.forEach(p => {
            html += `<tr><td>${p.nombre}</td><td>${p.cantidad} x ${p.precio_unitario}</td></tr>`;
        });
        html += '</tbody></table>';
        html += `Serie: <select id="serie${i}" class="serie"></select>`;
        html += ` Propina: <input type="number" step="0.01" id="propina${i}" value="0"><div id="tot${i}"></div>`;
        div.innerHTML = html;
        cont.appendChild(div);
        const sel = div.querySelector('select.serie');
        series.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.descripcion;
            sel.appendChild(opt);
        });
        if (seleccionSeries[i]) {
            sel.value = seleccionSeries[i];
        }
        sel.addEventListener('change', () => {
            seleccionSeries[i] = parseInt(sel.value);
        });
    }
    mostrarTotal();
}

function mostrarTotal() {
    for (let i = 1; i <= numSub; i++) {
        const prods = productos.filter(p => p.subcuenta === i);
        let total = prods.reduce((s, p) => s + p.cantidad * p.precio_unitario, 0);
        const prop = parseFloat(document.getElementById('propina' + i).value || 0);
        total += prop;
        document.getElementById('tot' + i).textContent = 'Total: ' + total.toFixed(2);
    }
}

function guardarSubcuentas() {
    const info = JSON.parse(localStorage.getItem('ticketData'));
    const payload = { venta_id: info.venta_id, usuario_id: info.usuario_id || 1, subcuentas: [] };
    for (let i = 1; i <= numSub; i++) {
        const prods = productos
            .filter(p => p.subcuenta === i)
            .map(p => {
                if (!p.producto_id) {
                    alert('Producto sin ID en subcuenta ' + i);
                    throw new Error('Producto sin id');
                }
                return {
                    producto_id: p.producto_id,
                    cantidad: p.cantidad,
                    precio_unitario: p.precio_unitario,
                    subcuenta: i,
                    propina: parseFloat(document.getElementById('propina' + i).value || 0),
                    serie_id: parseInt(document.getElementById('serie' + i).value)
                };
            });
        payload.subcuentas.push({ numero: i, productos: prods });
    }
    localStorage.setItem('subTickets', JSON.stringify(payload));
    window.open(`ticket.html?venta=${info.venta_id}&print=1`, '_blank');
}

function liberarMesa(ventaId) {
    // Placeholder
}
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
