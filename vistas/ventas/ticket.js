function llenarTicket(data) {
        console.log('Datos de ticket recibidos:', data);
        const logoSrc = data.logo_url || '../../utils/logo.png';
        document.getElementById('ticketLogo').src = logoSrc;
        document.getElementById('ventaId').textContent = data.venta_id;
        document.getElementById('fechaHora').textContent = data.fecha_fin || data.fecha || '';
        document.getElementById('folio').textContent = data.folio || '';
        document.getElementById('nombreRestaurante').textContent = data.nombre_negocio || data.restaurante || '';
        document.getElementById('direccionNegocio').textContent = data.direccion_negocio || '';
        document.getElementById('rfcNegocio').textContent = data.rfc_negocio || '';
        document.getElementById('telefonoNegocio').textContent = data.telefono_negocio || '';
        document.getElementById('sedeId').textContent = data.sede_id || '';
        document.getElementById('mesaNombre').textContent = data.mesa_nombre || '';
        document.getElementById('meseroNombre').textContent = data.mesero_nombre || '';
        document.getElementById('tipoEntrega').textContent = data.tipo_entrega || 'N/A';
        document.getElementById('tipoPago').textContent = data.tipo_pago || 'N/A';
        const tInfo = document.getElementById('tarjetaInfo');
        const cInfo = document.getElementById('chequeInfo');
        if (tInfo) tInfo.style.display = 'none';
        if (cInfo) cInfo.style.display = 'none';
        if (data.tipo_pago === 'boucher' && tInfo) {
            tInfo.style.display = 'block';
            document.getElementById('tarjetaMarca').textContent = data.tarjeta || 'No definido';
            document.getElementById('tarjetaBanco').textContent = data.banco_tarjeta || 'No definido';
            document.getElementById('tarjetaBoucher').textContent = data.boucher || 'No definido';
        } else if (data.tipo_pago === 'cheque' && cInfo) {
            cInfo.style.display = 'block';
            document.getElementById('chequeNumero').textContent = data.cheque_numero || 'No definido';
            document.getElementById('chequeBanco').textContent = data.banco_cheque || 'No definido';
        }
        document.getElementById('horaInicio').textContent = (data.fecha_inicio && data.fecha_inicio !== 'N/A') ? new Date(data.fecha_inicio).toLocaleString() : (data.fecha_inicio || 'N/A');
        document.getElementById('horaFin').textContent = (data.fecha_fin && data.fecha_fin !== 'N/A') ? new Date(data.fecha_fin).toLocaleString() : (data.fecha_fin || 'N/A');
        document.getElementById('tiempoServicio').textContent = data.tiempo_servicio ? data.tiempo_servicio + ' min' : 'N/A';
        const tbody = document.querySelector('#productos tbody');
        tbody.innerHTML = '';
        data.productos.forEach(p => {
            const tr = document.createElement('tr');
            const subtotal = p.cantidad * p.precio_unitario;
            tr.innerHTML = `<td>${p.nombre}</td><td>${p.cantidad} x ${p.precio_unitario} = ${subtotal}</td>`;
            tbody.appendChild(tr);
        });
        // document.getElementById('propina').textContent = '$' + parseFloat(data.propina || 0).toFixed(2);
        document.getElementById('cambio').textContent = '$' + parseFloat(data.cambio || 0).toFixed(2);
        document.getElementById('totalVenta').textContent = 'Total: $' + parseFloat(data.total).toFixed(2);
        document.getElementById('totalLetras').textContent = data.total_letras || '';
    }

function imprimirTicket() {
        const ticketContainer = document.getElementById('ticketContainer');
        if (!ticketContainer) return;
        const ticketContent = ticketContainer.innerHTML;
        const printWindow = window.open('', '', 'width=400,height=600');
        printWindow.document.write('<html><head><title>Imprimir Ticket</title>');
        printWindow.document.write('<link rel="stylesheet" href="../../utils/css/style.css">');
        printWindow.document.write('</head><body>');
        printWindow.document.write(ticketContent);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
}

document.addEventListener('DOMContentLoaded', async () => {
    const params = new URLSearchParams(window.location.search);
    const imprimir = params.get('print') === '1';
    const almacenado = localStorage.getItem('ticketData');
    const sinDatos = document.getElementById('sinDatos');
    if (!almacenado) {
        if (sinDatos) sinDatos.style.display = 'block';
        return;
    }
    if (sinDatos) sinDatos.style.display = 'none';
    const datos = JSON.parse(almacenado);
    const totalPropinas = parseFloat(datos.propina_efectivo) + parseFloat(datos.propina_cheque) + parseFloat(datos.propina_tarjeta);
    if (parseFloat(totalPropinas) > parseFloat(0.00)){
        document.getElementById('divReimprimir').style.display = 'block';
        const btnReimprimir = document.getElementById('btnReimprimir');
        if (btnReimprimir){
          btnReimprimir.addEventListener('click', reimprimirTicket);  
        } 
        liberarMesa(datos.venta_id);
    } else {
        const ok = await cargarDenominacionesPago();
        if (!ok) return;
        await cargarCatalogosTarjeta();
        serieActual = await obtenerSerieActual();
        inicializarDividir(datos);
    }
    const btnImprimir = document.getElementById('btnImprimir');
    if (btnImprimir) btnImprimir.addEventListener('click', imprimirTicket);
});


    let serieActual = null;
    let denomBoucherId = null;
    let denomChequeId = null;
    let catalogoTarjetas = [];
    let catalogoBancos = [];

    async function cargarCatalogosTarjeta() {
        try {
            const resp = await fetch(catalogosUrl);
            const data = await resp.json();
            if (data.success) {
                catalogoBancos = Array.isArray(data.bancos) ? data.bancos : [];
                catalogoTarjetas = Array.isArray(data.tarjetas) ? data.tarjetas : [];
            }
        } catch (e) {
            console.error('Error cargando catálogos', e);
        }
    }

    async function cargarDenominacionesPago() {
        try {
            const resp = await fetch(denominacionesUrl);
            const data = await resp.json();
            if (data.success && Array.isArray(data.resultado)) {
                data.resultado.forEach(d => {
                    if (d.descripcion === 'Pago Boucher') denomBoucherId = d.id;
                    if (d.descripcion === 'Pago Cheque') denomChequeId = d.id;
                });
            }
        } catch (e) {
            console.error(e);
        }
        if (!denomBoucherId || !denomChequeId) {
            alert('Faltan denominaciones para pagos con tarjeta o cheque');
            return false;
        }
        return true;
    }

    async function obtenerSerieActual() {
        try {
            const resp = await fetch('../../api/horarios/serie_actual.php');
            const data = await resp.json();
            if (data.success) return data.resultado;
            alert(data.mensaje);
        } catch (err) {
            console.error(err);
            alert('Error al obtener serie');
        }
        return null;
    }

    let productos = [];
    let numSub = 1;
    let banderaEfec = false;
    let banderaCheq = false;
    let banderaTarj = false;
    function inicializarDividir(data) {
        document.getElementById('dividir').style.display = 'block';
        productos = data.productos.map(p => Object.assign({
            subcuenta: 1
        }, p));
        renderProductos();
        renderSubcuentas();
        document.getElementById('agregarSub').addEventListener('click', () => {
            numSub++;
            actualizarSelects();
            renderSubcuentas();
        });
        const btnGuardar = document.getElementById('btnGuardarTicket');
        if (btnGuardar) btnGuardar.addEventListener('click', capturaPropinas);
        const btnCrear = document.getElementById('btnCrearTicket');
        if (btnCrear) btnCrear.addEventListener('click', guardarSubcuentas);
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
            const serieDesc = serieActual ? serieActual.descripcion : '';
            html += `Serie: <span class="serie">${serieDesc}</span>`;
            // html += ` Propina: <input type="number" step="0.01" id="propina${i}" value="0">`;
            html += ` <select id="pago${i}" class="pago">
                        <option value="">Pago</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="boucher">Tarjeta</option>
                        <option value="cheque">Cheque</option>
                    </select>`;
            html += ` <div id="extraPago${i}" class="mt-2"></div>`;
            html += ` <input type="number" step="0.01" id="recibido${i}" class="recibido" placeholder="Recibido">`;
            html += ` Cambio: <span id="cambio${i}">0</span>`;
            html += `<div id="tot${i}"></div>`;
            div.innerHTML = html;
            cont.appendChild(div);
            // div.querySelector('#propina' + i).addEventListener('input', mostrarTotal);
            div.querySelector('#pago' + i).addEventListener('change', () => { mostrarTotal(); mostrarCamposPago(i); });
            div.querySelector('#recibido' + i).addEventListener('input', mostrarTotal);
            mostrarCamposPago(i);
        }
        mostrarTotal();
    }
    async function capturaPropinas() {
        const cont = document.getElementById('regPropinas');
        const info2 = JSON.parse(localStorage.getItem('ticketData'));
        const propina_efectivo = parseFloat(document.getElementById('propinaEfectivo').value || 0.00);
        const propina_cheque = parseFloat(document.getElementById('propinaCheque').value || 0.00);
        const propina_tarjeta = parseFloat(document.getElementById('propinaTarjeta').value || 0.00);
        const payload2 = {
            venta_id: info2.venta_id,
            propina_efectivo: propina_efectivo ,
            propina_cheque: propina_cheque,
            propina_tarjeta: propina_tarjeta
        };
        try {
            const resp = await fetch('../../api/ventas/guardar_propina_venta.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload2)
            });
            const d = await resp.json();
            if (d.success) {
                document.getElementById('regPropinas').style.display = 'none';
                alert('Propina actualizada correctamente');
                await liberarMesa(info2.venta_id);
            } else {
                alert(d.mensaje);
            }
        } catch (e) {
            alert('Error al guardar');
        }

    }
    function mostrarTotal() {
        for (let i = 1; i <= numSub; i++) {
            const prods = productos.filter(p => p.subcuenta === i);
            let total = prods.reduce((s, p) => s + p.cantidad * p.precio_unitario, 0);
            // const prop = parseFloat(document.getElementById('propina' + i).value || 0);
            //total += prop;
            document.getElementById('tot' + i).textContent = 'Total: ' + total.toFixed(2);
            const tipo = document.getElementById('pago' + i).value;
            const inp = document.getElementById('recibido' + i);
            if (tipo !== 'efectivo') {
                inp.disabled = true;
                document.getElementById('cambio' + i).textContent = '0.00';
            } else {
                inp.disabled = false;
                const rec = parseFloat(inp.value || 0);
                const cambio = rec - total;
                document.getElementById('cambio' + i).textContent = cambio >= 0 ? cambio.toFixed(2) : '0.00';
                if (rec < total) {
                    inp.style.background = '#fdd';
                } else {
                    inp.style.background = '';
                }
            }
        }
    }

    function mostrarCamposPago(i) {
        const tipo = document.getElementById('pago' + i).value;
        const cont = document.getElementById('extraPago' + i);
        if (!cont) return;
        let html = '';
        if (tipo === 'boucher') {
            html += 'Marca: <select id="tarjetaMarca' + i + '"><option value="">Seleccione</option>';
            catalogoTarjetas.forEach(t => { html += `<option value="${t.id}">${t.nombre}</option>`; });
            html += '</select> ';
            html += 'Banco: <select id="tarjetaBanco' + i + '"><option value="">Seleccione</option>';
            catalogoBancos.forEach(b => { html += `<option value="${b.id}">${b.nombre}</option>`; });
            html += '</select> ';
            html += 'Boucher: <input type="text" id="boucher' + i + '">';
            cont.innerHTML = html;
            cont.style.display = 'block';
        } else if (tipo === 'cheque') {
            html += 'No. Cheque: <input type="text" id="chequeNumero' + i + '"> ';
            html += 'Banco: <select id="chequeBanco' + i + '"><option value="">Seleccione</option>';
            catalogoBancos.forEach(b => { html += `<option value="${b.id}">${b.nombre}</option>`; });
            html += '</select>';
            cont.innerHTML = html;
            cont.style.display = 'block';
        } else {
            cont.innerHTML = '';
            cont.style.display = 'none';
        }
    }

    function crearTeclado(input) {
        const cont = document.getElementById('teclado');
        cont.innerHTML = '';
        const teclas = ['7', '8', '9', '4', '5', '6', '1', '2', '3', '0', '.', 'Borrar'];
        teclas.forEach(t => {
            const b = document.createElement('button');
            b.textContent = t;
            b.addEventListener('click', () => {
                if (t === 'Borrar') {
                    input.value = input.value.slice(0, -1);
                } else {
                    input.value += t;
                }
                input.dispatchEvent(new Event('input'));
            });
            cont.appendChild(b);
        });
    }

    document.addEventListener('focusin', e => {
        if (e.target.classList.contains('recibido')) {
            crearTeclado(e.target);
        }
    });

    async function guardarSubcuentas() {
        const info = JSON.parse(localStorage.getItem('ticketData'));
        if (!denomBoucherId || !denomChequeId) {
            alert('No hay denominaciones configuradas para tarjeta o cheque');
            return;
        }
        const payload = {
            venta_id: info.venta_id,
            usuario_id: info.usuario_id || 1,
            sede_id: info.sede_id || null,
            subcuentas: []
        };
        for (let i = 1; i <= numSub; i++) {
            const prods = productos
                .filter(p => p.subcuenta === i)
                .map(p => {
                    if (!p.producto_id) {
                        alert('Producto sin ID en subcuenta ' + i);
                        throw new Error('Producto sin id');
                    }
                    return {
                        producto_id: Number(p.producto_id),
                        cantidad: p.cantidad,
                        precio_unitario: p.precio_unitario
                    };
                });
            if (prods.length === 0) continue;
            // const prop = parseFloat(document.getElementById('propina' + i).value || 0);
            if (!serieActual) {
                serieActual = await obtenerSerieActual();
                if (!serieActual) return;
            }
            const serie = serieActual.id;
            const tipo = document.getElementById('pago' + i).value;
            const recibido = parseFloat(document.getElementById('recibido' + i).value || 0);
            const total = prods.reduce((s, p) => s + p.cantidad * p.precio_unitario, 0) ;
            if (!tipo) {
                alert('Selecciona tipo de pago en subcuenta ' + i);
                return;
            }
            if (tipo === 'efectivo' && recibido < total) {
                alert('Monto insuficiente en subcuenta ' + i);
                return;
            }
             if (tipo === 'efectivo' ) {
                banderaEfec=true;
                document.getElementById('propinaEfectivo').disabled = false;
                document.getElementById('propinaEfectivoD').style.display ='block';
            }
            const extra = {};
            if (tipo === 'boucher') {
                extra.tarjeta_marca_id = parseInt(document.getElementById('tarjetaMarca' + i).value) || null;
                extra.tarjeta_banco_id = parseInt(document.getElementById('tarjetaBanco' + i).value) || null;
                extra.boucher = document.getElementById('boucher' + i).value || '';
                if (!extra.tarjeta_marca_id || !extra.tarjeta_banco_id || !extra.boucher) {
                    alert('Completa datos de tarjeta en subcuenta ' + i);
                    return;
                }
                 banderaTarj=true;
                 document.getElementById('propinaTarjeta').disabled = false;
                 document.getElementById('propinaTarjetaD').style.display ='block';
            } else if (tipo === 'cheque') {
                extra.cheque_numero = document.getElementById('chequeNumero' + i).value || '';
                extra.cheque_banco_id = parseInt(document.getElementById('chequeBanco' + i).value) || null;
                if (!extra.cheque_numero || !extra.cheque_banco_id) {
                    alert('Completa datos de cheque en subcuenta ' + i);
                    return;
                }
                banderaCheq=true;
                document.getElementById('propinaCheque').disabled = false;
                document.getElementById('propinaChequeD').style.display ='block';
            }
            payload.subcuentas.push({
                productos: prods,
                serie_id: serie,
                tipo_pago: tipo,
                monto_recibido: recibido,
                ...extra
            });
        }
        try {
            const resp = await fetch('../../api/tickets/guardar_ticket.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const d = await resp.json();
            if (d.success) {
                document.getElementById('regPropinas').style.display = 'block';
                await registrarDesglosePagos(payload.subcuentas);
                await imprimirTicketsVenta(payload.venta_id);
            } else {
                alert(d.mensaje);
            }
        } catch (e) {
            alert('Error al guardar');
        }
    }

    async function registrarDesglosePagos(subcuentas) {
        const totales = { boucher: 0, cheque: 0 };
        subcuentas.forEach(sc => {
            const total = sc.productos.reduce((s, p) => s + p.cantidad * p.precio_unitario, 0) ;
            if (sc.tipo_pago === 'boucher') totales.boucher += total;
            if (sc.tipo_pago === 'cheque') totales.cheque += total;
        });
        const detalle = [];
        if (totales.boucher > 0 && denomBoucherId) {
            detalle.push({ tipo_pago: 'boucher', denominacion_id: denomBoucherId, cantidad: totales.boucher, denominacion: 1 });
        }
        if (totales.cheque > 0 && denomChequeId) {
            detalle.push({ tipo_pago: 'cheque', denominacion_id: denomChequeId, cantidad: totales.cheque, denominacion: 1 });
        }
        if (!detalle.length) return;
        try {
            await fetch('../../api/corte_caja/guardar_desglose.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ detalle })
            });
        } catch (err) {
            console.error('Error al registrar desglose', err);
        }
    }

    async function imprimirTicketsVenta(ventaId) {
        try {
            window.open('../../api/tickets/imprime_ticket.php?venta_id='+ventaId);
            // const resp = await fetch('../../api/tickets/reimprimir_ticket.php', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({ venta_id: ventaId })
            // });
            // const data = await resp.json();

            // if (!data.success) {
            //     alert(data.mensaje || 'Error al obtener tickets');
            //     return;
            // }
            // const tickets = data.resultado.tickets || [];
            // if (!tickets.length) {
            //     alert('No hay tickets para imprimir');
            //     return;
            // }

            // let html = '<html><head><title>Tickets</title><link rel="stylesheet" href="../../utils/css/style.css"></head><body>';
            // tickets.forEach(t => {
            //     html += generarTicketHTML(t) + '<hr>';
            // });
            // html += '<script>window.onload=function(){window.print();}</script></body></html>';
            // console.log(html);
            // const blob = new Blob([html], { type: 'text/html' });
            // const url = URL.createObjectURL(blob);
            // window.open(url, '_blank');
        } catch (err) {
            console.error('Error al imprimir tickets', err);
        }
    }
   async function reimprimirTicket() {
        const almacenado2 = localStorage.getItem('ticketData');
        const datos2 = JSON.parse(almacenado2);
        ventaId=datos2.venta_id;
        try {
            window.open('../../api/tickets/reimprime_ticket.php?venta_id='+ventaId);
        } catch (err) {
            console.error('Error al imprimir tickets', err);
        }
    }

    function generarTicketHTML(data) {
        const productosHtml = (data.productos || []).map(p => {
            const subtotal = p.cantidad * p.precio_unitario;
            return `<tr><td>${p.nombre}</td><td>${p.cantidad} x ${p.precio_unitario} = ${subtotal}</td></tr>`;
        }).join('');
        let extra = '';
        if (data.tipo_pago === 'boucher') {
            extra = `<div><strong>Marca tarjeta:</strong> ${data.tarjeta || 'No definido'}</div>` +
                    `<div><strong>Banco:</strong> ${data.banco_tarjeta || 'No definido'}</div>` +
                    `<div><strong>Boucher:</strong> ${data.boucher || 'No definido'}</div>`;
        } else if (data.tipo_pago === 'cheque') {
            extra = `<div><strong>No. Cheque:</strong> ${data.cheque_numero || 'No definido'}</div>` +
                    `<div><strong>Banco:</strong> ${data.banco_cheque || 'No definido'}</div>`;
        }
        return `<div>
            <img src="${data.logo_url || '../../utils/logo.png'}" style="max-width:100px;">
            <h2>${data.nombre_negocio || data.restaurante || ''}</h2>
            <div>${data.direccion_negocio || ''}</div>
            <div>${data.rfc_negocio || ''}</div>
            <div>${data.telefono_negocio || ''}</div>
            <div style="margin-bottom:10px;">${data.fecha_fin || data.fecha || ''}</div>
            <div><strong>Folio:</strong> ${data.folio || ''}</div>
            <div><strong>Venta:</strong> ${data.venta_id}</div>
            <div><strong>Sede:</strong> ${data.sede_id || ''}</div>
            <div><strong>Mesa:</strong> ${data.mesa_nombre || ''}</div>
            <div><strong>Mesero:</strong> ${data.mesero_nombre || ''}</div>
            <div><strong>Tipo entrega:</strong> ${data.tipo_entrega || 'N/A'}</div>
            <div><strong>Tipo pago:</strong> ${data.tipo_pago || 'N/A'}</div>
            ${extra}
            <div><strong>Inicio:</strong> ${data.fecha_inicio || ''}</div>
            <div><strong>Fin:</strong> ${data.fecha_fin || ''}</div>
            <div><strong>Tiempo:</strong> ${data.tiempo_servicio ? data.tiempo_servicio + ' min' : 'N/A'}</div>
            <table class="styled-table" style="margin-top: 10px;"><tbody>${productosHtml}</tbody></table>
            
            <div class="mt-2"><strong>Cambio:</strong> $${parseFloat(data.cambio || 0).toFixed(2)}</div>
            <div class="mt-2 mb-2">Total: $${parseFloat(data.total || 0).toFixed(2)}</div>
            <div>${data.total_letras || ''}</div>
            <p>Gracias por su compra</p>
        </div>`;
    }

    async function liberarMesa(ventaId) {
        if (!ventaId) return;
        try {
            await fetch('../../api/mesas/liberar_mesa_de_venta.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    venta_id: parseInt(ventaId)
                })
            });
        } catch (e) {
            console.error('Error al liberar mesa');
        }
    }
