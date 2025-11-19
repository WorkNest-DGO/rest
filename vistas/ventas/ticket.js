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

    // Validación extra para promociones de tipo "llevar" (versión con nombre de categoría)
    // Evita que 3x209, 2 Té y 2 rollos + té usen más productos de los que hay,
    // y muestra el nombre de la categoría (por ejemplo "Rollo empanizado") en el mensaje.
    function validarPromosAcumulablesLlevar(subIdx) {
        const tipoEntrega = (window.__tipoEntregaVenta || '').toLowerCase();
        if (tipoEntrega !== 'domicilio' && tipoEntrega !== 'rapido' && tipoEntrega !== 'llevar') {
            return { ok: true };
        }
        const subDiv = document.getElementById('sub' + subIdx);
        if (!subDiv) return { ok: true };

        const prodsSub = productos.filter(function (p) { return p.subcuenta === subIdx; });
        if (!prodsSub.length) return { ok: true };

        const selectedIds = Array.from(subDiv.querySelectorAll('select.promo-select'))
            .map(function (s) { return parseInt(s.value || '0', 10); })
            .filter(function (v) { return !!v; });
        if (!selectedIds.length) return { ok: true };

        const promosSel = selectedIds
            .map(function (pid) {
                return (catalogoPromociones || []).find(function (p) {
                    return parseInt(p.id, 10) === pid;
                });
            })
            .filter(function (p) { return !!p; });

        const combos = promosSel.filter(function (p) {
            const tipo = String(p.tipo || '').toLowerCase();
            const monto = Number(p.monto || 0);
            const tipoVenta = String(p.tipo_venta || '').toLowerCase();
            return tipo === 'combo' && monto > 0 && tipoVenta === 'llevar';
        });
        if (!combos.length) return { ok: true };

        const countPromos = function(id) {
            return combos.filter(function (p) { return parseInt(p.id, 10) === id; }).length;
        };
        const promo5Count = countPromos(5);
        const promo6Count = countPromos(6);
        const promo9Count = countPromos(9);
        if (!promo5Count && !promo6Count && !promo9Count) return { ok: true };

        const promo6 = combos.find(function (p) { return parseInt(p.id, 10) === 6; });
        const promo9Obj = combos.find(function (p) { return parseInt(p.id, 10) === 9; }) || {};

        let rollPromo6Ids = [];
        if (promo6Count && promo6 && promo6.regla) {
            let rj;
            try { rj = JSON.parse(promo6.regla); } catch (e) { rj = null; }
            const arr = Array.isArray(rj) ? rj : (rj ? [rj] : []);
            rollPromo6Ids = arr
                .map(function (r) { return parseInt(r.id_producto || 0, 10); })
                .filter(function (v) { return !!v; });
        }

        const categoriasConteo = {};
        let rollSubsetCount = 0;
        let teaCount = 0;
        prodsSub.forEach(function (p) {
            const cant = Number(p.cantidad || 0);
            if (!cant) return;
            const pid = parseInt(p.producto_id || p.id || 0, 10);
            const catId = parseInt(p.categoria_id || 0, 10);
            if (catId) {
                categoriasConteo[catId] = (categoriasConteo[catId] || 0) + cant;
            }
            if (rollPromo6Ids.indexOf(pid) !== -1) rollSubsetCount += cant;
            if (pid === 66) teaCount += cant;
        });

        const extraerCategoriasPromo = function(promo) {
            const ids = [];
            if (!promo) return ids;
            if (Array.isArray(promo.categorias_regla)) {
                promo.categorias_regla.forEach(function(cat) {
                    const cId = parseInt(cat && cat.id, 10);
                    if (cId) ids.push(cId);
                });
            }
            if (!ids.length && promo && promo.regla) {
                let regla;
                try { regla = JSON.parse(promo.regla); } catch (e) { regla = null; }
                const arr = Array.isArray(regla) ? regla : (regla ? [regla] : []);
                arr.forEach(function(r) {
                    const cId = parseInt(r && r.categoria_id, 10);
                    if (cId) ids.push(cId);
                });
            }
            return Array.from(new Set(ids.filter(Boolean)));
        };

        const categoriasPromo9 = extraerCategoriasPromo(promo9Obj);
        const categoriasParaConteo = categoriasPromo9.length ? categoriasPromo9 : [9];
        const catPromoCount = categoriasParaConteo.reduce(function(sum, cId) {
            return sum + (categoriasConteo[cId] || 0);
        }, 0);

        if (!catPromoCount && !rollSubsetCount && !teaCount) {
            return { ok: true };
        }

        const describirPromos = function(nombres) {
            const lista = (nombres || []).filter(Boolean);
            if (!lista.length) return '';
            const fraseBase = lista.length === 1 ? 'La promoción' : 'Las promociones';
            if (lista.length === 1) {
                return `${fraseBase} "${lista[0]}"`;
            }
            if (lista.length === 2) {
                return `${fraseBase} "${lista[0]}" y "${lista[1]}"`;
            }
            const ult = lista.pop();
            return `${fraseBase} "${lista.join('", "')}" y "${ult}"`;
        };

        const errores = [];
        const nombrePromo5 = (combos.find(function (p) { return parseInt(p.id, 10) === 5; }) || {}).nombre || '2 Té';
        const nombrePromo6 = (promo6 || {}).nombre || '2 rollos y té';
        const nombrePromo9 = (promo9Obj && promo9Obj.nombre) || '3x $209 en rollos';
        let nombreCat9 = 'categoría 9';
        if (promo9Obj && Array.isArray(promo9Obj.categorias_regla) && promo9Obj.categorias_regla.length) {
            const nombres = promo9Obj.categorias_regla.map(function (cat) {
                return cat && cat.nombre ? cat.nombre : null;
            }).filter(Boolean);
            if (nombres.length === 1) {
                nombreCat9 = nombres[0];
            } else if (nombres.length > 1) {
                nombreCat9 = nombres.join(', ');
            }
        } else if (categoriasParaConteo.length === 1) {
            nombreCat9 = 'categoría ' + categoriasParaConteo[0];
        } else if (categoriasParaConteo.length > 1) {
            nombreCat9 = 'categorías ' + categoriasParaConteo.join(', ');
        }

        const totalTeaNeeded = (promo6Count * 1) + (promo5Count * 2);
        if ((promo5Count || promo6Count) && totalTeaNeeded > teaCount) {
            errores.push(`${describirPromos([
                promo6Count ? nombrePromo6 : null,
                promo5Count ? nombrePromo5 : null,
            ])} requieren ${totalTeaNeeded} tés y solo hay ${teaCount}.`);
        }

        const totalRollPromo6Needed = promo6Count * 2;
        if (promo6Count && totalRollPromo6Needed > rollSubsetCount) {
            errores.push(`${describirPromos([nombrePromo6])} requiere ${totalRollPromo6Needed} rollos válidos y solo hay ${rollSubsetCount}.`);
        }

        const totalRollsNeeded = totalRollPromo6Needed + (promo9Count * 3);
        if ((promo6Count || promo9Count) && totalRollsNeeded > catPromoCount) {
            errores.push(`${describirPromos([
                promo6Count ? nombrePromo6 : null,
                promo9Count ? nombrePromo9 : null,
            ])} requieren ${totalRollsNeeded} rollos (${nombreCat9}) y solo hay ${catPromoCount}.`);
        }

        if (errores.length) {
            return { ok: false, mensajes: errores };
        }

        return { ok: true };
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
    // Exponer tipo_entrega de la venta de forma global para filtrar promociones por tipo_venta
    window.__tipoEntregaVenta = (datos.tipo_entrega || '').toLowerCase();
    // Exponer promoci&oacute;n seleccionada en la venta (si existe) para preseleccionar en ticket
    window.__promocionVentaId = datos.promocion_id || null;
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
        await cargarPromociones();
        serieActual = await obtenerSerieActual();
        inicializarDividir(datos);
        preseleccionarPromoVenta();
    }
    const btnImprimir = document.getElementById('btnImprimir');
    if (btnImprimir) btnImprimir.addEventListener('click', imprimirTicket);
});


// PROMOS: ejemplo de integración futura desde ticket (no activo)
// fetch('../../api/tickets/guardar_ticket.php?accion=aplicar_promo_catalogo', {
//   method: 'POST',
//   headers: { 'Content-Type': 'application/json' },
//   body: JSON.stringify({ ticket_id: 123, catalogo_promo_id: 45, usuario_id: 1 })
// }).then(r=>r.json()).then(d=>{ /* manejar respuesta */ });


    let serieActual = null;
    let denomBoucherId = null;
    let denomChequeId = null;
    let catalogoTarjetas = [];
    let catalogoBancos = [];
    let catalogoPromociones = [];
    let descuentoPromocion = 0;
    let idPromocion = 0;    
    let banderaPromo = false;
    // Para combos con precio fijo (monto): se aplica como totalEsperado1 - monto
    let promoMontoFijo = 0;
    let promoSubIdx = null;
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
      async function cargarPromociones() {
          try {
              const resp = await fetch(promocionesUrl);
              const data = await resp.json();
              if (data.success) {
                  let promos = Array.isArray(data.promociones) ? data.promociones : [];
                  const tipoEntrega = (window.__tipoEntregaVenta || '').toLowerCase();
                  if (tipoEntrega === 'mesa') {
                      promos = promos.filter(p => String(p.tipo_venta || '').toLowerCase() === 'mesa');
                  } else if (tipoEntrega === 'domicilio' || tipoEntrega === 'rapido') {
                      promos = promos.filter(p => String(p.tipo_venta || '').toLowerCase() === 'llevar');
                  }
                  catalogoPromociones = promos;
              }
          } catch (e) {
              console.error('Error cargando promociones', e);
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
    let promociones = [];
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

    // Si la venta ya viene con una promoci&oacute;n seleccionada, tratar de preseleccionarla
    function preseleccionarPromoVenta() {
        try {
            const baseId = window.__promocionVentaId ? parseInt(window.__promocionVentaId, 10) : 0;
            if (!baseId) return;
            const selects = document.querySelectorAll('.promos-panel select.promo-select');
            if (!selects.length) return;
            let aplicada = false;
            selects.forEach(sel => {
                if (aplicada) return;
                if (sel.querySelector(`option[value=\"${baseId}\"]`)) {
                    sel.value = String(baseId);
                    const subDiv = sel.closest('[id^=\"sub\"]');
                    if (subDiv && typeof recalcSub === 'function') {
                        const subIdx = parseInt(subDiv.id.replace('sub', ''), 10) || 1;
                        try { recalcSub(subIdx); } catch (_) {}
                    }
                    aplicada = true;
                }
            });
        } catch (_) {}
    }

    // === Descuentos y cortesías ===
    (function(){
      const $porc = document.getElementById('descuentoPorcentaje');
      const $lblC = document.getElementById('lblDescCortesias');
      const $lblP = document.getElementById('lblDescPorcentaje');
      const $lblMF = document.getElementById('lblDescMontoFijo');
      const $lblT = document.getElementById('lblDescTotal');
      const $lblE = document.getElementById('lblTotalEsperado');
      const $montoFijo = document.getElementById('descuentoMontoFijo');
      const cortesias = new Set();

      function getTotalBruto(){
        let sum = 0;
        document.querySelectorAll('#tablaProductos tbody tr[data-subtotal]').forEach(tr=>{
          sum += Number(tr.getAttribute('data-subtotal')||0);
        });
        return Number(sum.toFixed(2));
      }
      function getCortesiasTotal(){
        let sum = 0;
        cortesias.forEach(detId=>{
          const tr = document.querySelector(`#tablaProductos tbody tr[data-detalle-id="${detId}"]`);
          if (tr) sum += Number(tr.getAttribute('data-subtotal')||0);
        });
        return Number(sum.toFixed(2));
      }
      function clampPct(v){ v = Number(v||0); if (v<0) v=0; if (v>100) v=100; return v; }

      function recalc(){
        const totalBruto = getTotalBruto();
        const cort = getCortesiasTotal();
        const pct = clampPct($porc?.value);
        const montoFijo = Math.max(0, Number($montoFijo?.value || 0));
        const base = Math.max(0, totalBruto - cort);
        const descPctMonto = Number((base * (pct/100)).toFixed(2));
        const descTotal = Math.min(totalBruto, Number((cort + descPctMonto + montoFijo).toFixed(2)));
        const totalEsperado = Math.max(0, Number((totalBruto - descTotal).toFixed(2)));

        if ($lblC) $lblC.textContent = cort.toFixed(2);
        if ($lblP) $lblP.textContent = descPctMonto.toFixed(2);
        if ($lblMF) $lblMF.textContent = montoFijo.toFixed(2);
        if ($lblT) $lblT.textContent = descTotal.toFixed(2);
        if ($lblE) $lblE.textContent = totalEsperado.toFixed(2);

        window.__DESC_DATA__ = { totalBruto, cort, pct, montoFijo, descPctMonto, descTotal, totalEsperado, cortesias: Array.from(cortesias) };
        // Actualizar monto global mostrado considerando descuento global
        try {
          const g = document.getElementById('lblMontoActualGlobal');
          if (g) {
            // Si hay subcuentas calculadas, partir de su suma; si no, usar totalEsperado global
            let suma = 0;
            if (window.__SUBS__ && Object.keys(window.__SUBS__).length) {
              Object.values(window.__SUBS__).forEach(sub => { suma += Number(sub.totalEsperado || 0); });
            } else {
              suma = totalBruto;
            }
            const neto = Math.max(0, suma - descTotal);
            g.textContent = neto.toFixed(2);
          }
        } catch(_) {}
        // Recalcular totales y cambio por subcuenta
        try { mostrarTotal(); } catch(_) {}
      }

      document.addEventListener('change', (e)=>{
        const chk = e.target.closest('.chk-cortesia');
        if (!chk) return;
        const detId = Number(chk.getAttribute('data-detalle-id')||0);
        if (!detId) return;
        if (chk.checked) cortesias.add(detId); else cortesias.delete(detId);
        recalc();
      });
      $porc && $porc.addEventListener('input', recalc);
      $montoFijo && $montoFijo.addEventListener('input', recalc);
      window.__ticketRecalcDescuentos = recalc; // hook para cuando se re-renderice
      recalc();
    })();

    function renderProductos() {
        const tbody = document.querySelector('#tablaProductos tbody');
        tbody.innerHTML = '';
        productos.forEach((p, idx) => {
            const tr = document.createElement('tr');
            const detalleId = p.id || p.detalle_id || p.venta_detalle_id || null;
            const subtotal = (parseFloat(p.cantidad) || 0) * (parseFloat(p.precio_unitario) || 0);
            if (detalleId) tr.setAttribute('data-detalle-id', String(detalleId));
            tr.setAttribute('data-subtotal', String(subtotal.toFixed(2)));
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
            const chkHtml = `<input type="checkbox" class="chk-cortesia" ${detalleId ? `data-detalle-id="${detalleId}"` : ''}>`;
            tr.innerHTML = `<td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td class="text-center">${chkHtml}</td>`;
            const td = document.createElement('td');
            td.appendChild(sel);
            const btnDiv = document.createElement('button');
            btnDiv.type = 'button';
            btnDiv.className = 'btn btn-sm btn-secondary';
            btnDiv.style.marginLeft = '6px';
            btnDiv.textContent = 'Dividir';
            btnDiv.disabled = !(Number(p.cantidad) > 1);
            btnDiv.addEventListener('click', () => {
                const cant = Number(p.cantidad) || 0;
                if (cant <= 1) return;
                p.cantidad = cant - 1;
                const nuevo = Object.assign({}, p, { cantidad: 1 });
                productos.splice(idx + 1, 0, nuevo);
                renderProductos();
                renderSubcuentas();
            });
            td.appendChild(btnDiv);
            tr.appendChild(td);
            tbody.appendChild(tr);
        });
        // Recalcular descuentos al renderizar
        if (typeof window.__ticketRecalcDescuentos === 'function') {
            window.__ticketRecalcDescuentos();
        }
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
            window.__SUBS__ = window.__SUBS__ || [];
            let html = `<h3>Subcuenta ${i}</h3><table><thead><tr><th>Producto</th><th>Cant x Precio</th><th>Cortesía</th></tr></thead><tbody>`;
            prods.forEach(p => {
                const detId = p.id || p.detalle_id || p.venta_detalle_id || 0;
                html += `<tr data-detalle-id="${detId}" data-subtotal="${(p.cantidad * p.precio_unitario).toFixed(2)}"><td>${p.nombre}</td><td>${p.cantidad} x ${p.precio_unitario}</td><td class=\"text-center\"><input type=\"checkbox\" class=\"chk-cortesia-sub\" data-sub=\"${i}\" data-detalle-id=\"${detId}\"></td></tr>`;
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
            html += ` <div id=\"extraPago${i}\" class=\"mt-2\"></div>`;
            html += `
              <div class=\"descuentosPanel\" style=\"margin-top:8px;\">
                <div>Subtotal cortesías: <span id=\"lblDescCortesias_sub${i}\">0.00</span></div>
                <div>Descuento % (1-100): <input id=\"descuentoPorcentaje_sub${i}\" type=\"number\" min=\"0\" max=\"100\" step=\"0.01\" value=\"0\"></div>
                <div>Descuento monto ($): <input id=\"descuentoMontoFijo_sub${i}\" type=\"number\" min=\"0\" step=\"0.01\" value=\"0.00\"></div>
                <div>Descuento % monto: <span id=\"lblDescPorcentaje_sub${i}\">0.00</span></div>
                <div>Descuento monto fijo: <span id=\"lblDescMontoFijo_sub${i}\">0.00</span></div>
                <div><strong>Descuento total:</strong> <span id=\"lblDescTotal_sub${i}\">0.00</span></div>                        
                <div><strong>Monto actual (a cobrar):</strong> <span id=\"lblTotalEsperado_sub${i}\">0.00</span></div>
              </div>
            `;
             html += 'Promoción: <select id="promociones' + i + '"><option value="0">Seleccione</option>';
            catalogoPromociones.forEach(c => { html += `<option value="${c.id}">${c.nombre}</option>`; });
            html += `</select> <div>Descuento promocion aplicada: <span id=\"lblDescPromocion_sub${i}\">0.00</span></div>`;            
            html += ` <input type="number" step="0.01" id="recibido${i}" class="recibido" placeholder="Recibido">`;
            html += ` Cambio: <span id="cambio${i}">0</span>`;
            html += `<div id="tot${i}"></div>`;
            div.innerHTML = html;
            // Convertir select único de promoción en panel acumulable
            try {
                const selSingle = div.querySelector('#promociones' + i);
                const descWrap = div.querySelector('#lblDescPromocion_sub' + i)?.parentElement || null;
                if (selSingle && descWrap && descWrap.parentNode) {
                    const panel = document.createElement('div');
                    panel.className = 'promos-panel';
                    panel.id = 'promosPanel' + i;
                    const row = document.createElement('div');
                    row.className = 'promo-row';
                    const lab = document.createElement('label');
                    lab.textContent = 'Promoción:';
                    selSingle.classList.add('promo-select');
                    row.appendChild(lab);
                    row.appendChild(selSingle);
                    const add = document.createElement('button');
                    add.type = 'button';
                    add.className = 'btn btn-secondary btn-add-promo';
                    add.dataset.sub = String(i);
                    add.textContent = 'Agregar';
                    row.appendChild(add);
                    panel.appendChild(row);
                    // Insertar panel antes del resumen de descuento
                    descWrap.parentNode.insertBefore(panel, descWrap);
                    // Reetiquetar resumen
                    descWrap.firstChild && (descWrap.firstChild.textContent = 'Descuento promociones aplicado: ');
                    const wire = () => {
                        panel.querySelectorAll('select.promo-select').forEach(s => {
                            s.onchange = () => validarPromoSeleccion(i, s);
                        });
                    };
                    wire();
                    add.addEventListener('click', () => {
                        const rows = panel.querySelectorAll('select.promo-select').length;
                        const r = document.createElement('div'); r.className = 'promo-row';
                        const txt = document.createTextNode('Promoción:');
                        const s2 = document.createElement('select'); s2.className = 'promo-select'; s2.id = 'promociones' + i + '_' + rows;
                        const opt0 = document.createElement('option'); opt0.value = '0'; opt0.textContent = 'Seleccione'; s2.appendChild(opt0);
                        (catalogoPromociones || []).forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.nombre; s2.appendChild(o); });
                        const rm = document.createElement('button'); rm.type = 'button'; rm.className = 'btn btn-danger btn-rm-promo'; rm.textContent = 'Quitar';
                        rm.addEventListener('click', () => { r.remove(); recalcSub(i); });
                        r.appendChild(txt); r.appendChild(s2); r.appendChild(rm);
                        panel.appendChild(r);
                        wire();
                        recalcSub(i);
                    });
                }
            } catch(_) {}
            // Agregar campo de motivo de descuento dentro del panel de descuentos por subcuenta
            try {
                const panel = div.querySelector('.descuentosPanel');
                if (panel) {
                    const motivoDiv = document.createElement('div');
                    motivoDiv.innerHTML = 'Motivo descuento: <input id="motivo_sub' + i + '" type="text" maxlength="255" placeholder="Describe el motivo">';
                    panel.appendChild(motivoDiv);
                }
            } catch(_) {}
            cont.appendChild(div);
            // div.querySelector('#propina' + i).addEventListener('input', mostrarTotal);
            div.querySelector('#pago' + i).addEventListener('change', () => { mostrarTotal(); mostrarCamposPago(i); });
            div.querySelector('#recibido' + i).addEventListener('input', mostrarTotal);
            div.querySelectorAll('.chk-cortesia-sub').forEach(chk => {
                chk.addEventListener('change', () => { recalcSub(i); mostrarTotal(); });
            });
            const $pi = div.querySelector('#descuentoPorcentaje_sub'+i);
            const $mi = div.querySelector('#descuentoMontoFijo_sub'+i);
            if ($pi) $pi.addEventListener('input', () => { recalcSub(i); mostrarTotal(); });
            if ($mi) $mi.addEventListener('input', () => { recalcSub(i); mostrarTotal(); });
            mostrarCamposPago(i);
            recalcSub(i);
        }
        mostrarTotal();
    }

    function recalcSub(i){
        const idx = Number(i);
        const subDiv = document.getElementById('sub'+idx);
        if (!subDiv) return;
        const prods = productos.filter(p => p.subcuenta === idx);
        const totalBruto = prods.reduce((s, p) => s + (Number(p.cantidad)||0) * (Number(p.precio_unitario)||0), 0);
        const cortSet = new Set();
        subDiv.querySelectorAll('.chk-cortesia-sub').forEach(chk => { if (chk.checked) cortSet.add(Number(chk.getAttribute('data-detalle-id')||0)); });
        let cortTotal = 0;
        let totalPromo = 0; 
        cortSet.forEach(detId => {
            const tr = subDiv.querySelector(`tr[data-detalle-id="${detId}"]`);
            if (tr) cortTotal += Number(tr.getAttribute('data-subtotal')||0);
        });
        const pct = Math.max(0, Math.min(100, Number((subDiv.querySelector('#descuentoPorcentaje_sub'+idx)?.value)||0)));
        const montoFijo = Math.max(0, Number((subDiv.querySelector('#descuentoMontoFijo_sub'+idx)?.value)||0));
        const base = Math.max(0, totalBruto - cortTotal);
        const descPctMonto = Number((base * (pct/100)).toFixed(2));
        const descuentoTotal = Math.min(totalBruto, Number((cortTotal + descPctMonto + montoFijo).toFixed(2)));
        const totalEsperado1 = Math.max(0, Number((totalBruto - descuentoTotal).toFixed(2)));
        // Validar combinación de promociones \"llevar\" (3x209, 2 TǸ, 2 rollos + tǸ)
        try {
            if (!window.__validandoPromosAcumulables) {
                const resAcum = validarPromosAcumulablesLlevar(idx);
                if (resAcum && resAcum.ok === false) {
                    const subDivLocal = document.getElementById('sub' + idx);
                    const mensajes = resAcum.mensajes || [];
                    const body = document.getElementById('promoErrorMsg');
                    if (body) {
                        body.innerHTML = '<p>La combinación de promociones seleccionadas no es válida porque:</p><ul>' +
                            mensajes.map(m => '<li>' + m + '</li>').join('') +
                            '</ul>';
                    }
                    const texto = 'La combinación de promociones seleccionadas no es válida:\n\n' + mensajes.join('\n');
                    try {
                        if (window.jQuery && $('#modalPromoError').modal) {
                            $('#modalPromoError').modal('show');
                        } else {
                            alert(texto);
                        }
                    } catch(_) {
                        alert(texto);
                    }
                    if (subDivLocal) {
                        subDivLocal.querySelectorAll('select.promo-select').forEach(s => {
                            const val = parseInt(s.value || '0', 10);
                            if (val === 6) s.value = '0';
                        });
                    }
                    window.__validandoPromosAcumulables = true;
                    recalcSub(idx);
                    window.__validandoPromosAcumulables = false;
                    return;
                }
            }
        } catch(_) {}
        // Calcular descuentos por promociones acumulables seleccionadas en esta subcuenta
        try {
            const selectedIds = Array.from(subDiv.querySelectorAll('select.promo-select'))
                .map(s => parseInt(s.value || '0', 10))
                .filter(Boolean);
            if (selectedIds.length) {
                // pool de precios por unidad por producto de la subcuenta (para matches por categoría/id)
                const poolByPromo = (promo) => {
                    let reglaJson = {};
                    try { reglaJson = promo.regla ? JSON.parse(promo.regla) : {}; } catch(_) { reglaJson = {}; }
                    const reglasArray = Array.isArray(reglaJson) ? reglaJson : [reglaJson];
                    const prodIds  = reglasArray.map(r => parseInt(r.id_producto || 0, 10)).filter(Boolean);
                    const catIds   = reglasArray.map(r => parseInt(r.categoria_id || 0, 10)).filter(Boolean);
                    const items = prods.filter(p => (prodIds.length ? prodIds.includes(parseInt(p.producto_id || p.id || 0, 10)) : true)
                                                  && (catIds.length ? catIds.includes(parseInt(p.categoria_id || 0, 10)) : true));
                    const unitPrices = [];
                    items.forEach(it => { const qty = Math.max(1, parseInt(it.cantidad || 1, 10)); const price = Number(it.precio_unitario || 0); for (let k=0;k<qty;k++) unitPrices.push(price); });
                    unitPrices.sort((a,b)=>Number(a)-Number(b));
                    return unitPrices;
                };
                let sumNonMonto = 0;
                let montoMin = null;
                selectedIds.forEach(pid => {
                    const promo = (catalogoPromociones || []).find(p => parseInt(p.id) === pid);
                    if (!promo) return;
                    const tipo = String(promo.tipo||'').toLowerCase();
                    const monto = Number(promo.monto||0);
                    let reglaJson = {};
                    try { reglaJson = promo.regla ? JSON.parse(promo.regla) : {}; } catch(_) { reglaJson = {}; }
                    const cantidad = parseInt((Array.isArray(reglaJson) ? (reglaJson[0]?.cantidad) : (reglaJson?.cantidad)) || '0', 10) || (tipo==='bogo'?2:1);
                    // Combos con monto fijo (ej. 3x209, 2x49) se calculan en el bloque especial
                    if (tipo === 'combo' && monto > 0) {
                        return;
                    }
                    if (monto>0 && (tipo==='monto_fijo' || tipo==='bogo')) {
                        montoMin = (montoMin===null) ? monto : Math.min(montoMin, monto);
                    } else {
                        const pool = poolByPromo(promo);
                        if (pool.length >= cantidad) {
                            // Para bogo N:1, descuento = el más barato del grupo requerido
                            // Para categoria_gratis cantidad n: tomar n unidades más baratas
                            if (tipo==='categoria_gratis') {
                                const take = pool.slice(0, cantidad);
                                sumNonMonto += take.reduce((s,x)=>s+Number(x||0),0);
                            } else {
                                sumNonMonto += Number(pool[0]||0);
                            }
                        }
                    }
                });
                let discMonto = 0;
                if (montoMin!==null) {
                    discMonto = Math.max(0, Number((totalEsperado1 - montoMin).toFixed(2)));
                }
                totalPromo = Math.min(totalEsperado1, Number((discMonto + sumNonMonto).toFixed(2)));
            } else {
                totalPromo = 0;
            }
        } catch(_) { totalPromo = 0; }
        // Ajuste específico para combos de categoría con precio fijo (por ejemplo 3x209 en categoría 9)
        try {
            const selectedIdsCat = Array.from(new Set(Array.from(subDiv.querySelectorAll('select.promo-select'))
                .map(s => parseInt(s.value || '0', 10))
                .filter(Boolean)));
            let totalPromoCatCombo = 0;
            selectedIdsCat.forEach(pid => {
                const promo = (catalogoPromociones || []).find(p => parseInt(p.id) === pid);
                if (!promo) return;
                const tipo = String(promo.tipo || '').toLowerCase();
                const monto = Number(promo.monto || 0);
                if (tipo !== 'combo' || !promo.regla) return;
                let reglaJson = {};
                try { reglaJson = JSON.parse(promo.regla); } catch(_) { reglaJson = {}; }
                const reglasArray = Array.isArray(reglaJson) ? reglaJson : [reglaJson];
                const categoriaIds = reglasArray.map(r => parseInt(r.categoria_id || 0, 10)).filter(Boolean);
                if (!categoriaIds.length) return;
                const categoriaId = categoriaIds[0];
                let cantidadReq = reglasArray.reduce((s, r) => s + (parseInt(r.cantidad || 0, 10) || 0), 0);
                if (!cantidadReq) cantidadReq = 1;
                const unitPricesCat = [];
                prods.forEach(p => {
                    if (parseInt(p.categoria_id || 0, 10) === categoriaId) {
                        const qty = Math.max(1, parseInt(p.cantidad || 1, 10));
                        const price = Number(p.precio_unitario || 0);
                        for (let k = 0; k < qty; k++) unitPricesCat.push(price);
                    }
                });
                if (!unitPricesCat.length) return;
                unitPricesCat.sort((a, b) => Number(b) - Number(a)); // más caros primero
                const grupos = Math.floor(unitPricesCat.length / cantidadReq);
                for (let g = 0; g < grupos; g++) {
                    const offset = g * cantidadReq;
                    const grupo = unitPricesCat.slice(offset, offset + cantidadReq);
                    const sumaGrupo = grupo.reduce((s, x) => s + Number(x || 0), 0);
                    const desc = Math.max(0, sumaGrupo - monto);
                    totalPromoCatCombo += desc;
                }
            });
            if (totalPromoCatCombo > 0) {
                totalPromo = Math.min(totalEsperado1, Number((totalPromo + totalPromoCatCombo).toFixed(2)));
            }
        } catch(_) {}
        // Ajuste adicional para combos de precio fijo por producto (por ejemplo 2x49 de T� id 66)
        try {
            const selectedIdsProd = Array.from(new Set(Array.from(subDiv.querySelectorAll('select.promo-select'))
                .map(s => parseInt(s.value || '0', 10))
                .filter(Boolean)));
            let totalPromoProdCombo = 0;
            selectedIdsProd.forEach(pid => {
                const promo = (catalogoPromociones || []).find(p => parseInt(p.id) === pid);
                if (!promo || !promo.regla) return;
                const tipo = String(promo.tipo || '').toLowerCase();
                const monto = Number(promo.monto || 0);
                if (tipo !== 'combo' || monto <= 0) return;
                // Solo aplicar este bloque a la promo 2 Te (id=5)
                if (parseInt(promo.id) !== 5) return;
                let reglaJson = {};
                try { reglaJson = JSON.parse(promo.regla); } catch(_) { reglaJson = {}; }
                const reglasArray = Array.isArray(reglaJson) ? reglaJson : [reglaJson];
                const rule = reglasArray[0] || {};
                const prodIdRegla = parseInt(rule.id_producto || 0, 10);
                let cantidadReq = parseInt(rule.cantidad || 0, 10) || 0;
                if (!prodIdRegla || !cantidadReq) return;
                const unitPrices = [];
                prods.forEach(p => {
                    const prodId = parseInt(p.producto_id || p.id || 0, 10);
                    if (prodId !== prodIdRegla) return;
                    const qty = Math.max(1, parseInt(p.cantidad || 1, 10));
                    const price = Number(p.precio_unitario || 0);
                    for (let k = 0; k < qty; k++) unitPrices.push(price);
                });
                if (!unitPrices.length) return;
                unitPrices.sort((a, b) => Number(b) - Number(a)); // m�s caros primero
                const grupos = Math.floor(unitPrices.length / cantidadReq);
                for (let g = 0; g < grupos; g++) {
                    const offset = g * cantidadReq;
                    const grupo = unitPrices.slice(offset, offset + cantidadReq);
                    const sumaGrupo = grupo.reduce((s, x) => s + Number(x || 0), 0);
                    const desc = Math.max(0, sumaGrupo - monto);
                    totalPromoProdCombo += desc;
                }
            });
            if (totalPromoProdCombo > 0) {
                totalPromo = Math.min(totalEsperado1, Number((totalPromo + totalPromoProdCombo).toFixed(2)));
            }
        } catch(_) {}
        // Ajuste especial para promo "2 rollos y té" (id=6)
        try {
            const selectedIdsRollTea = Array.from(subDiv.querySelectorAll('select.promo-select'))
                .map(s => parseInt(s.value || '0', 10))
                .filter(Boolean);
            let totalPromoRollTea = 0;
            selectedIdsRollTea.forEach(pid => {
                const promo = (catalogoPromociones || []).find(p => parseInt(p.id) === pid);
                if (!promo || !promo.regla) return;
                const tipo = String(promo.tipo || '').toLowerCase();
                const monto = Number(promo.monto || 0);
                if (tipo !== 'combo' || monto <= 0 || parseInt(promo.id) !== 6) return;
                let reglaJson = {};
                try { reglaJson = JSON.parse(promo.regla); } catch(_) { reglaJson = {}; }
                const reglasArray = Array.isArray(reglaJson) ? reglaJson : [reglaJson];
                const rollIds = reglasArray.map(r => parseInt(r.id_producto || 0, 10)).filter(Boolean);
                const teaId = 66;
                const rollPrices = [];
                const teaPrices = [];
                prods.forEach(p => {
                    const pidProd = parseInt(p.producto_id || p.id || 0, 10);
                    const qty = Math.max(1, parseInt(p.cantidad || 1, 10));
                    const price = Number(p.precio_unitario || 0);
                    for (let k = 0; k < qty; k++) {
                        if (rollIds.includes(pidProd)) rollPrices.push(price);
                        if (pidProd === teaId) teaPrices.push(price);
                    }
                });
                if (!rollPrices.length || !teaPrices.length) return;
                rollPrices.sort((a, b) => Number(b) - Number(a));
                teaPrices.sort((a, b) => Number(b) - Number(a));
                const grupos = Math.min(Math.floor(rollPrices.length / 2), teaPrices.length);
                for (let g = 0; g < grupos; g++) {
                    const r1 = rollPrices[g * 2];
                    const r2 = rollPrices[g * 2 + 1];
                    const t  = teaPrices[g];
                    const sumaGrupo = Number(r1 || 0) + Number(r2 || 0) + Number(t || 0);
                    const desc = Math.max(0, sumaGrupo - monto);
                    totalPromoRollTea += desc;
                }
            });
            if (totalPromoRollTea > 0) {
                totalPromo = Math.min(totalEsperado1, Number((totalPromo + totalPromoRollTea).toFixed(2)));
            }
        } catch(_) {}
        const totalEsperado = Math.max(0, Number((totalEsperado1 - totalPromo).toFixed(2)));
        const setText = (sel, val) => { const el = subDiv.querySelector(sel); if (el) el.textContent = val.toFixed(2); };
        setText('#lblDescCortesias_sub'+idx, cortTotal);
        setText('#lblDescPorcentaje_sub'+idx, descPctMonto);
        setText('#lblDescMontoFijo_sub'+idx, montoFijo);
        setText('#lblDescTotal_sub'+idx, descuentoTotal);
        setText('#lblDescPromocion_sub'+idx, totalPromo);
        setText('#lblTotalEsperado_sub'+idx, totalEsperado);
        // Marcar motivo como requerido si hay descuento aplicado en esta subcuenta
        try {
            const motivoEl = subDiv.querySelector('#motivo_sub'+idx);
            if (motivoEl) {
                motivoEl.required = (descuentoTotal > 0);
            }
        } catch(_) {}
        const detalleIds = prods.map(p => p.id || p.detalle_id || p.venta_detalle_id).filter(Boolean);
        window.__SUBS__ = window.__SUBS__ || [];
        window.__SUBS__[idx] = { idx, detalleIds, cortesias: Array.from(cortSet), pct, montoFijo, totalBruto: Number(totalBruto.toFixed(2)), descuentoTotal, totalEsperado, promoTotal: totalPromo };
        // Actualizar monto actual global (suma de subcuentas)
        try {
            let suma = 0;
            Object.values(window.__SUBS__).forEach(sub => { suma += Number(sub.totalEsperado || 0); });
            // Si existe descuento global calculado, restarlo a la suma
            const descGlobal = (window.__DESC_DATA__ && typeof window.__DESC_DATA__.descTotal === 'number')
              ? Number(window.__DESC_DATA__.descTotal || 0)
              : 0;
            const neto = Math.max(0, suma - descGlobal);
            const g = document.getElementById('lblMontoActualGlobal');
            if (g) g.textContent = neto.toFixed(2);
        } catch(_) {}
        // Sincroniza input recibido según tipo de pago
        try { mostrarTotal(); } catch(_) {}
    }

// Valida que una promoción tenga match con los productos de la subcuenta.
// Si no cumple, muestra modal indicando el insumo/categoría ausente y revierte la selección.
function validarPromoSeleccion(subIdx, selectEl) {
    const promoId = parseInt(selectEl.value || '0', 10);
    if (!promoId) {
        recalcSub(subIdx);
        return;
    }

    const promo = (catalogoPromociones || []).find(p => parseInt(p.id) === promoId);
    if (!promo || !promo.regla) {
        recalcSub(subIdx);
        return;
    }

    const promoIdInt = parseInt(promo.id || 0, 10);
    const tipoPromo = String(promo.tipo || '').toLowerCase();

    let reglaJson;
    try { reglaJson = JSON.parse(promo.regla); } catch(_) { reglaJson = null; }
    const reglasArray = Array.isArray(reglaJson) ? reglaJson : (reglaJson ? [reglaJson] : []);
    if (!reglasArray.length) {
        recalcSub(subIdx);
        return;
    }

    const prodsSub = productos.filter(p => p.subcuenta === subIdx);

    // Caso especial: promo 2 rollos + té (id=6)
    if (promoIdInt === 6 && tipoPromo === 'combo') {
        const rollIds = reglasArray.map(r => parseInt(r.id_producto || 0, 10)).filter(Boolean);
        const teaId = 66;
        let rollUnits = 0;
        let teaUnits = 0;

        prodsSub.forEach(p => {
            const pid = parseInt(p.producto_id || p.id || 0, 10);
            const cant = Number(p.cantidad || 0);
            if (rollIds.includes(pid)) rollUnits += cant;
            if (pid === teaId) teaUnits += cant;
        });

        if (rollUnits < 2 || teaUnits < 1) {
            const msg = 'La promoción es la combinación de 2 rollos más té, en la selección de Chiquilin, Maki Carne, Mar y Tierra.';
            const body = document.getElementById('promoErrorMsg');
            if (body) { body.textContent = msg; }

            try {
                if (window.jQuery && $('#modalPromoError').modal) {
                    $('#modalPromoError').modal('show');
                } else {
                    alert(msg);
                }
            } catch(_) {
                alert(msg);
            }

            selectEl.value = '0';
            recalcSub(subIdx);
            return;
        }

        // Cumple combinación especial
        recalcSub(subIdx);
        return;
    }

    // Validación genérica para el resto de promos
    const mensajes = [];
    reglasArray.forEach(r => {
        const reqCant = parseInt(r.cantidad || 0, 10) || 0;
        if (!reqCant) return;

        if (r.id_producto) {
            const pid = parseInt(r.id_producto, 10);
            const exist = prodsSub.reduce((s, p) => {
                const pId = parseInt(p.producto_id || p.id || 0, 10);
                const cant = Number(p.cantidad || 0);
                return s + (pId === pid ? cant : 0);
            }, 0);

            if (exist < reqCant) {
                const prod = prodsSub.find(p => parseInt(p.producto_id || p.id || 0, 10) === pid);
                let nombre = prod && prod.nombre ? prod.nombre : null;

                // Si el producto no está en la subcuenta, usar el nombre del catálogo de la promo
                if (!nombre && promo && Array.isArray(promo.productos_regla)) {
                    const prodRegla = promo.productos_regla.find(pr => parseInt(pr.id, 10) === pid);
                    if (prodRegla && prodRegla.nombre) {
                        nombre = prodRegla.nombre;
                    }
                }

                if (!nombre) {
                    nombre = `ID ${pid}`;
                }

                mensajes.push(`Producto ${nombre}: se requieren ${reqCant}, solo hay ${exist}.`);
            }
        } else if (r.categoria_id) {
            const cid = parseInt(r.categoria_id, 10);
            const exist = prodsSub.reduce((s, p) => {
                const cId = parseInt(p.categoria_id || 0, 10);
                const cant = Number(p.cantidad || 0);
                return s + (cId === cid ? cant : 0);
            }, 0);

            if (exist < reqCant) {
                mensajes.push(`Categoría ${cid}: se requieren ${reqCant}, solo hay ${exist}.`);
            }
        }
    });

    if (mensajes.length) {
        const body = document.getElementById('promoErrorMsg');
        if (body) {
            body.innerHTML = '<p>La promoción no aplica porque:</p><ul>' +
                mensajes.map(m => '<li>' + m + '</li>').join('') +
                '</ul>';
        }
        try {
            if (window.jQuery && $('#modalPromoError').modal) {
                $('#modalPromoError').modal('show');
            } else {
                alert('La promoción no aplica:\n\n' + mensajes.join('\n'));
            }
        } catch(_) {
            alert('La promoción no aplica:\n\n' + mensajes.join('\n'));
        }
        selectEl.value = '0';
        recalcSub(subIdx);
        return;
    }

    // Todo OK, recalcular normalmente
    recalcSub(subIdx);
}


    // Agrega un recalculador global para preparar payload con descuentos acumulados de promociones
    window.__ticketRecalcDescuentos = function() {
        try {
            let sumPromo = 0;
            let firstId = 0;
            for (let i = 1; i <= numSub; i++) {
                const subDiv = document.getElementById('sub' + i);
                if (!subDiv) continue;
                const state = window.__SUBS__ && window.__SUBS__[i] ? window.__SUBS__[i] : null;
                if (state && typeof state.promoTotal === 'number') {
                    sumPromo += Number(state.promoTotal || 0);
                }
                if (!firstId) {
                    const sel = subDiv.querySelector('select.promo-select');
                    const id = sel ? parseInt(sel.value || '0', 10) : 0;
                    if (id) firstId = id;
                }
            }
            descuentoPromocion = Number(sumPromo.toFixed(2));
            banderaPromo = descuentoPromocion > 0;
            idPromocion = firstId || 0;
        } catch(_) {}
    };
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
        const state = window.__SUBS__ && window.__SUBS__[i] ? window.__SUBS__[i] : null;
        let total = state ? Number(state.totalEsperado || 0) : prods.reduce((s, p) => s + p.cantidad * p.precio_unitario, 0);
        // Ajustar por descuento global (panel superior) si existe
        try {
            const descGlobal = (window.__DESC_DATA__ && typeof window.__DESC_DATA__.descTotal === 'number')
                ? Number(window.__DESC_DATA__.descTotal || 0)
                : 0;
            if (descGlobal > 0) {
                if (numSub === 1) {
                    // Todo el descuento se aplica a la única subcuenta
                    total = Math.max(0, total - descGlobal);
                } else {
                    // Distribuir el descuento proporcionalmente al total bruto de cada subcuenta
                    let sumaBruta = 0;
                    Object.values(window.__SUBS__ || {}).forEach(sub => { sumaBruta += Number(sub.totalBruto || 0); });
                    const brutoSub = state ? Number(state.totalBruto || 0) : total;
                    const factor = sumaBruta > 0 ? (brutoSub / sumaBruta) : 0;
                    const descAsignado = descGlobal * factor;
                    total = Math.max(0, total - descAsignado);
                }
            }
        } catch(_) {}
        // const prop = parseFloat(document.getElementById('propina' + i).value || 0);
        //total += prop;
        document.getElementById('tot' + i).textContent = 'Total (a cobrar): ' + total.toFixed(2);
        const tipo = document.getElementById('pago' + i).value;
        const inp = document.getElementById('recibido' + i);
        if (tipo !== 'efectivo') {
            // Autollenar y bloquear con Total Esperado por subcuenta
            if (inp) {
                inp.readOnly = true;
                inp.value = total.toFixed(2);
                inp.classList.add('readonly');
            }
            document.getElementById('cambio' + i).textContent = '0.00';
        } else {
            if (inp) {
                inp.readOnly = false;
                inp.classList.remove('readonly');
            }
            const rec = parseFloat(inp?.value || 0);
            const cambio = rec - total;
            document.getElementById('cambio' + i).textContent = cambio >= 0 ? cambio.toFixed(2) : '0.00';
            if (rec < total) {
                if (inp) inp.style.background = '#fdd';
            } else {
                if (inp) inp.style.background = '';
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

     function vvalidaPromocion(i) { try { recalcSub(i); } catch(_) {} }

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
        // Recalcular descuentos antes de armar payload
        if (typeof window.__ticketRecalcDescuentos === 'function') {
            window.__ticketRecalcDescuentos();
        }
        const payload = {
            venta_id: info.venta_id,
            usuario_id: info.usuario_id || 1,
            sede_id: info.sede_id || null,
            subcuentas: [],
            promocion_id: idPromocion,
            promocion_descuento: descuentoPromocion,
            bandera_promo: banderaPromo,
            // Extras de descuentos
            descuento_porcentaje: window.__DESC_DATA__?.pct || 0,
            descuento_total: window.__DESC_DATA__?.descTotal || 0,
            cortesias: window.__DESC_DATA__?.cortesias || []
        };
        for (let i = 1; i <= numSub; i++) {
            const prodsRaw = productos.filter(p => p.subcuenta === i);
            const prods = prodsRaw.map(p => {
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
            let recibido = parseFloat(document.getElementById('recibido' + i).value || 0);
            const stateSub = window.__SUBS__ && window.__SUBS__[i] ? window.__SUBS__[i] : null;
            const total = stateSub ? Number(stateSub.totalEsperado || 0) : prods.reduce((s, p) => s + p.cantidad * p.precio_unitario, 0) ;
            // Validar que se capture motivo cuando hay descuento aplicado
            const motivoEl = document.getElementById('motivo_sub' + i);
            const requiereMotivo = stateSub && Number(stateSub.descuentoTotal || 0) > 0;
            if (requiereMotivo) {
                const valorMotivo = (motivoEl && motivoEl.value) ? motivoEl.value.trim() : '';
                if (!valorMotivo) {
                    alert('Captura el motivo del descuento en la subcuenta ' + i);
                    if (motivoEl) { motivoEl.focus(); }
                    return;
                }
            }
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
            // Asegura IDs de detalle reales desde los productos crudos de la subcuenta
            const detalleIds = prodsRaw
              .map(p => p.id || p.detalle_id || p.venta_detalle_id)
              .filter(Boolean);
            // Toma la selección de cortesías calculada en recalcSub(i)
            const cortesiasSub = (window.__SUBS__ && window.__SUBS__[i]) ? (window.__SUBS__[i].cortesias || []) : [];
            // Asegura que las cortesías pertenezcan a esta subcuenta
            const cortesiasSubFiltradas = cortesiasSub.filter(id => detalleIds.includes(id));
            const pctSub = (window.__SUBS__ && window.__SUBS__[i]) ? (window.__SUBS__[i].pct || 0) : 0;
            const montoFijoSub = (window.__SUBS__ && window.__SUBS__[i]) ? (window.__SUBS__[i].montoFijo || 0) : 0;
            // monto recibido autollenado/locked para boucher/cheque vía UI; no reasignar aquí
            payload.subcuentas.push({
                productos: prods,
                detalle_ids: detalleIds,
                cortesias: cortesiasSubFiltradas,
                descuento_porcentaje: pctSub,
                descuento_monto_fijo: montoFijoSub,
                serie_id: serie,
                tipo_pago: tipo,
                monto_recibido: recibido,
                desc_des: (document.getElementById('motivo_sub' + i)?.value || '').trim(),
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
                console.log(d);
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
            // Si la venta está asociada a una mesa, liberarla al imprimir
            try { await liberarMesa(ventaId); } catch(_) {}
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

    // Validación extra para promociones de tipo "llevar"
    // Evita que 3x209, 2 TǸ y 2 rollos + tǸ usen mǭs productos de los que hay.
    function validarPromosAcumulablesLlevar_OLD(subIdx) {
        const tipoEntrega = (window.__tipoEntregaVenta || '').toLowerCase();
        if (tipoEntrega !== 'domicilio' && tipoEntrega !== 'rapido' && tipoEntrega !== 'llevar') {
            return { ok: true };
        }
        const subDiv = document.getElementById('sub' + subIdx);
        if (!subDiv) return { ok: true };

        const prodsSub = productos.filter(p => p.subcuenta === subIdx);
        if (!prodsSub.length) return { ok: true };

        const selectedIds = Array.from(subDiv.querySelectorAll('select.promo-select'))
            .map(s => parseInt(s.value || '0', 10))
            .filter(Boolean);
        if (!selectedIds.length) return { ok: true };

        const promosSel = selectedIds
            .map(pid => (catalogoPromociones || []).find(p => parseInt(p.id) === pid))
            .filter(Boolean);

        const combos = promosSel.filter(p => {
            const tipo = String(p.tipo || '').toLowerCase();
            const monto = Number(p.monto || 0);
            const tipoVenta = String(p.tipo_venta || '').toLowerCase();
            return tipo === 'combo' && monto > 0 && tipoVenta === 'llevar';
        });
        if (!combos.length) return { ok: true };

        const countPromos = id => combos.filter(p => parseInt(p.id, 10) === id).length;
        const promo6Count = countPromos(6);
        if (!promo6Count) return { ok: true };

        const promo6 = combos.find(p => parseInt(p.id, 10) === 6);
        const promo5Count = countPromos(5);
        const promo9Count = countPromos(9);

        let rollPromo6Ids = [];
        if (promo6 && promo6.regla) {
            let rj;
            try { rj = JSON.parse(promo6.regla); } catch(_) { rj = null; }
            const arr = Array.isArray(rj) ? rj : (rj ? [rj] : []);
            rollPromo6Ids = arr.map(r => parseInt(r.id_producto || 0, 10)).filter(Boolean);
        }

        let cat9Count = 0;
        let rollSubsetCount = 0;
        let teaCount = 0;
        prodsSub.forEach(p => {
            const cant = Number(p.cantidad || 0);
            if (!cant) return;
            const pid = parseInt(p.producto_id || p.id || 0, 10);
            const catId = parseInt(p.categoria_id || 0, 10);
            if (catId === 9) cat9Count += cant;
            if (rollPromo6Ids.includes(pid)) rollSubsetCount += cant;
            if (pid === 66) teaCount += cant;
        });

        if (!cat9Count && !rollSubsetCount && !teaCount) {
            return { ok: true };
        }

        const errores = [];
        const nombrePromo5 = (combos.find(p => parseInt(p.id, 10) === 5) || {}).nombre || '2 TǸ';
        const nombrePromo6 = promo6.nombre || '2 rollos y tǸ';
        const nombrePromo9 = (combos.find(p => parseInt(p.id, 10) === 9) || {}).nombre || '3x $209 en rollos';

        const totalTeaNeeded = (promo6Count * 1) + (promo5Count * 2);
        if (totalTeaNeeded > teaCount && (promo5Count > 0 || promo6Count > 0)) {
            errores.push(`Las promociones "${nombrePromo6}"${promo5Count ? ' y "' + nombrePromo5 + '"' : ''} requieren ${totalTeaNeeded} tés y solo hay ${teaCount}.`);
        }

        const totalRollPromo6Needed = promo6Count * 2;
        if (totalRollPromo6Needed > rollSubsetCount) {
            errores.push(`La promoción "${nombrePromo6}" requiere ${totalRollPromo6Needed} rollos válidos y solo hay ${rollSubsetCount}.`);
        }

        const totalRollsNeeded = totalRollPromo6Needed + (promo9Count * 3);
        if (totalRollsNeeded > cat9Count && (promo9Count > 0 || promo6Count > 0)) {
            errores.push(`Las promociones "${nombrePromo6}"${promo9Count ? ' y "' + nombrePromo9 + '"' : ''} requieren ${totalRollsNeeded} rollos (categoría 9) y solo hay ${cat9Count}.`);
        }

        if (errores.length) {
            return { ok: false, mensajes: errores };
        }

        return { ok: true };
    }
