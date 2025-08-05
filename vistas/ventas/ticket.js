 function llenarTicket(data) {
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
        document.getElementById('propina').textContent = '$' + parseFloat(data.propina || 0).toFixed(2);
        document.getElementById('cambio').textContent = '$' + parseFloat(data.cambio || 0).toFixed(2);
        document.getElementById('totalVenta').textContent = 'Total: $' + parseFloat(data.total).toFixed(2);
        document.getElementById('totalLetras').textContent = data.total_letras || '';
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
            serieActual = await obtenerSerieActual();
            inicializarDividir(datos);
        }
    });


    let serieActual = null;
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
    let ticketsGuardados = [];

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
            const serieDesc = serieActual ? serieActual.descripcion : '';
            html += `Serie: <span class="serie">${serieDesc}</span>`;
            html += ` Propina: <input type="number" step="0.01" id="propina${i}" value="0">`;
            html += ` <select id="pago${i}" class="pago">
                        <option value="">Pago</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="boucher">Tarjeta</option>
                        <option value="cheque">Cheque</option>
                    </select>`;
            html += ` <input type="number" step="0.01" id="recibido${i}" class="recibido" placeholder="Recibido">`;
            html += ` Cambio: <span id="cambio${i}">0</span>`;
            html += `<div id="tot${i}"></div>`;
            div.innerHTML = html;
            cont.appendChild(div);
            div.querySelector('#propina' + i).addEventListener('input', mostrarTotal);
            div.querySelector('#pago' + i).addEventListener('change', mostrarTotal);
            div.querySelector('#recibido' + i).addEventListener('input', mostrarTotal);
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
            const prop = parseFloat(document.getElementById('propina' + i).value || 0);
            if (!serieActual) {
                serieActual = await obtenerSerieActual();
                if (!serieActual) return;
            }
            const serie = serieActual.id;
            const tipo = document.getElementById('pago' + i).value;
            const recibido = parseFloat(document.getElementById('recibido' + i).value || 0);
            const total = prods.reduce((s, p) => s + p.cantidad * p.precio_unitario, 0) + prop;
            if (!tipo) {
                alert('Selecciona tipo de pago en subcuenta ' + i);
                return;
            }
            if (tipo === 'efectivo' && recibido < total) {
                alert('Monto insuficiente en subcuenta ' + i);
                return;
            }
            payload.subcuentas.push({
                productos: prods,
                propina: prop,
                serie_id: serie,
                tipo_pago: tipo,
                monto_recibido: recibido
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
                ticketsGuardados = d.resultado.tickets || [];
                alert('Tickets guardados');
                await mostrarBotonesImprimir(payload, true);
            } else {
                alert(d.mensaje);
            }
        } catch (e) {
            alert('Error al guardar');
        }
    }

    async function mostrarBotonesImprimir(payload, auto) {
        const params = new URLSearchParams(window.location.search);
        const mesaId = params.get('mesa');
        for (let i = 0; i < ticketsGuardados.length; i++) {
            const t = ticketsGuardados[i];
            let ticketInfo = null;
            try {
                const r = await fetch('../../api/tickets/reimprimir_ticket.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ folio: t.folio })
                });
                const jd = await r.json();
                if (jd.success && jd.resultado.tickets && jd.resultado.tickets.length) {
                    ticketInfo = jd.resultado.tickets[0];
                }
            } catch (e) {}
            if (!ticketInfo) continue;
            const url = mesaId ? `ticket.php?print=1&mesa=${mesaId}` : 'ticket.php?print=1';
            const imprimir = () => {
                localStorage.setItem('ticketData', JSON.stringify(ticketInfo));
                window.open(url, '_blank');
            };
            const div = document.getElementById('sub' + (i + 1));
            if (div) {
                const btn = document.createElement('button');
                btn.textContent = 'Imprimir Ticket';
                btn.addEventListener('click', imprimir);
                div.appendChild(btn);
            }
            if (auto) imprimir();
        }
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

    window.ticketPrinted = function(mesaId) {
        if (window.opener && window.opener.ticketPrinted) {
            window.opener.ticketPrinted(mesaId);
        }
    };

    window.addEventListener('beforeunload', () => {
        const params = new URLSearchParams(window.location.search);
        const mesaId = params.get('mesa');
        if (window.opener && window.opener.ticketPrinted && mesaId) {
            window.opener.ticketPrinted(parseInt(mesaId));
        }
    });