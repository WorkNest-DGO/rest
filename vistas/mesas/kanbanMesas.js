function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;

let productos = [];
let meseros = [];
let mesasCache = [];
const usuarioActual = window.usuarioActual || { id: null, rol: '' };
let ventaIdActual = null;
let mesaIdActual = null;
let estadoMesaActual = null;
let mesaMeseroIdActual = null;
let huboCambios = false;
let catalogoPromosMesa = [];
let productosVentaMesaActual = [];
let tipoEntregaMesaActual = '';
let totalPromoMesaActual = 0;
// Autorización temporal para cambio de estado
window.__mesaAuthTemp = null; // { mesaId, pass }
// Estado global de corte abierto (de cualquier usuario)
window.__corteAbiertoGlobal = false;

async function verificarCorteAbiertoGlobal() {
    try {
        const resp = await fetch('../../api/corte_caja/verificar_corte_abierto_global.php', { credentials: 'include', cache: 'no-store' });
        const data = await resp.json();
        window.__corteAbiertoGlobal = !!(data && data.success && data.resultado && data.resultado.abierto);
    } catch (e) {
        console.error('No se pudo verificar corte global:', e);
        window.__corteAbiertoGlobal = false;
    }
    return window.__corteAbiertoGlobal;
}

// Long poll: detecta si el estado de caja cambia y refresca la UI sin recargar la página
function iniciarLongPollCorte(intervalMs = 5000) {
    let ultimo = !!window.__corteAbiertoGlobal;
    const tick = async () => {
        try {
            const resp = await fetch('../../api/corte_caja/verificar_corte_abierto_global.php', { credentials: 'include', cache: 'no-store' });
            const data = await resp.json();
            const actual = !!(data && data.success && data.resultado && data.resultado.abierto);
            if (actual !== ultimo) {
                ultimo = actual;
                window.__corteAbiertoGlobal = actual;
                // Re-render de mesas para que aparezca/desaparezca el botón Cambiar estado
                await cargarMesas();
            }
        } catch (e) {
            // No interrumpir el ciclo por errores transitorios
            console.warn('Long poll corte falló:', e);
        } finally {
            setTimeout(tick, intervalMs);
        }
    };
    setTimeout(tick, intervalMs);
}

async function cargarMesas() {
    try {
    const base = window.API_LISTAR_MESAS || '../../api/mesas/listar_mesas.php';
    // Resolver rutas relativas usando la URL actual para no perder el prefijo (/rest)
    const u = base.includes('http') ? new URL(base) : new URL(base, window.location.href);
    if (window.usuarioActual && window.usuarioActual.id) {
        u.searchParams.set('user_id', window.usuarioActual.id);
        u.searchParams.set('usuario_id', window.usuarioActual.id);
    }
    const resp = await fetch(u.toString());
        const data = await resp.json();
        if (data.success) {
            const mesas = data.resultado;
            mesasCache = mesas;
            // Tablero único: todas las mesas en un solo panel
            renderTableroUnico(mesas);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar mesas');
    }
}


function crearColumnaUnica(container) {
    if (!container) return;
    const li = document.createElement('li');
    li.className = 'drag-column drag-column-on-hold kanban-board';
    li.dataset.meseroId = 'todas';
    const header = document.createElement('span');
    header.className = 'drag-column-header';
    header.innerHTML = '<h3>Todas las mesas</h3>';
    li.appendChild(header);
    const ul = document.createElement('ul');
    ul.className = 'drag-inner-list kanban-dropzone';
    li.appendChild(ul);
    container.appendChild(li);
}

/** Crea las columnas por mesero y agrega las tarjetas de mesas */
function renderTableroUnico(mesas) {
    const cont = document.getElementById('kanban-list');
    if (!cont) {
        console.error('Contenedor #kanban-list no encontrado');
        return;
    }
    cont.innerHTML = '';
    crearColumnaUnica(cont);

    const uniones = {};
    mesas.forEach(m => {
        if (m.mesa_principal_id) {
            if (!uniones[m.mesa_principal_id]) uniones[m.mesa_principal_id] = [];
            uniones[m.mesa_principal_id].push(m.id);
        }
    });

    const mapaMeseros = {}; // nombre dinámico si viene en la mesa

    // Colocar todas las mesas en la única columna
    const col = cont.querySelector('li[data-mesero-id="todas"] .drag-inner-list');
    mesas.forEach(m => {
        if (!col) return;
        const card = document.createElement('li');
        card.className = 'drag-item mesa kanban-item';
        card.classList.add(`estado-${m.estado}`);
        card.dataset.mesa = m.id;
        card.dataset.venta = m.venta_id || '';
        card.dataset.estado = m.estado;
        card.dataset.mesero = m.usuario_id && m.usuario_id !== 0 ? m.usuario_id : '';

        const unidas = uniones[m.id] || [];
        let unionTxt = '';
        if (m.mesa_principal_id) {
            unionTxt = `Unida a ${m.mesa_principal_id}`;
        } else if (unidas.length) {
            unionTxt = `Principal de: ${unidas.join(', ')}`;
        }

        const ventaTxt = m.venta_activa ? `Venta activa: ${m.venta_id}` : 'Sin venta';
        const meseroNombre = m.mesero_nombre || null;
        const meseroTxt = meseroNombre ? `Mesero: ${meseroNombre}` : 'Sin mesero asignado';
        const reservaTxt = m.estado_reserva === 'reservada' ? `Reservada: ${m.nombre_reserva} (${m.fecha_reserva})` : '';
        let ocupacionTxt = '';
        if (m.tiempo_ocupacion_inicio) {
            const inicio = new Date(m.tiempo_ocupacion_inicio.replace(' ', 'T'));
            const diff = Math.floor((Date.now() - inicio.getTime()) / 60000);
            ocupacionTxt = `Ocupada hace ${diff} min`;
        }

        const detallesBtn = (m.estado === 'ocupada') ? '<button class="detalles">Detalles</button>' : '';
        const asignarBtnHTML = m.usuario_id ? `<button class="asignar" data-id="${m.id}" hidden>Asignar mesero</button>`
                                            : `<button class="asignar" data-id="${m.id}">Asignar mesero</button>`;
        const cambiarBtnHTML = `<button class="cambiar" data-id="${m.id}" hidden>Cambiar estado</button>`;
        const botoneraHTML = `
            ${detallesBtn}
            ${asignarBtnHTML}
            <button class="dividir" data-id="${m.id}" hidden>Dividir</button>
            ${cambiarBtnHTML}
            <button class="ticket" data-mesa="${m.id}" data-nombre="${m.nombre}" data-venta="${m.venta_id || ''}" HIDDEN>Enviar ticket</button>`;

        card.innerHTML = `
            <input type="checkbox" class="seleccionar" data-id="${m.id}" hidden>
            <div class="title" style="color:#fff"> ${m.nombre}</div>
            <div class="meta">
                <span>Estado: ${m.estado}</span>
                <span>${ventaTxt}</span>
                <span>${meseroTxt}</span>
                ${unionTxt ? `<span>${unionTxt}</span>` : ''}
                ${reservaTxt ? `<span>${reservaTxt}</span>` : ''}
                ${ocupacionTxt ? `<span>${ocupacionTxt}</span>` : ''}
            </div>
            <div class="acciones">${botoneraHTML}</div>
        `;

        const puedeEditar = usuarioActual.rol === 'admin' || (m.usuario_id && parseInt(m.usuario_id) === usuarioActual.id);
        const btnCambiar = card.querySelector('button.cambiar');
        const btnDividir = card.querySelector('button.dividir');
        const btnAsignar = card.querySelector('button.asignar');

        if (!window.__corteAbiertoGlobal) {
            // Sin corte abierto: no permitir cambios de estado. Mostrar leyenda en lugar del botón.
            if (btnCambiar) {
                const leyenda = document.createElement('span');
                leyenda.className = 'badge bg-warning text-dark ms-2';
                leyenda.textContent = 'Se requiere abrir caja para ventas';
                btnCambiar.replaceWith(leyenda);
            }
            // Evitar acciones de reserva/cambio/dividir
            btnDividir && (btnDividir.disabled = true);
        } else {
            // Con corte abierto: al seleccionar la mesa, primero asignar mesero si no tiene
            card.addEventListener('click', () => {
                if (!m.usuario_id) {
                    abrirAsignarMesero(m);
                } else if (puedeEditar) {
                    // Permitir flujos existentes
                    if (m.estado === 'libre' && m.estado_reserva === 'ninguna') {
                        reservarMesa(m.id);
                    }
                } else {
                    // No es el mesero asignado: requiere pass para cambiar estado
                    // pero ver detalles sigue con botón dedicado
                }
            });
            if (btnAsignar) {
                btnAsignar.addEventListener('click', ev => { ev.stopPropagation(); abrirAsignarMesero(m); });
            }
            btnCambiar.addEventListener('click', ev => { ev.stopPropagation(); abrirCambioEstado(m); });
            btnDividir.addEventListener('click', ev => { ev.stopPropagation(); dividirMesa(btnDividir.dataset.id); });
        }

        const btnDetalles = card.querySelector('button.detalles');
        if (btnDetalles) {
            btnDetalles.addEventListener('click', ev => {
                ev.stopPropagation();
                abrirDetalles(m);
            });
        }

        const btnTicket = card.querySelector('button.ticket');
        btnTicket.addEventListener('click', ev => {
            ev.stopPropagation();
            solicitarTicket(card.dataset.mesa, card.querySelector('.title').textContent, card.dataset.venta);
        });

        col.appendChild(card);
    });

    // Drag irrelevante con una columna; se puede omitir o dejar activo
    // activarDrag();
    try { ajustarAlturasKanban(); } catch(_) {}
}

// Ajuste de alturas responsive para columnas
function debounce(fn, wait){ let t; return function(){ const ctx=this, args=arguments; clearTimeout(t); t=setTimeout(()=>fn.apply(ctx,args), wait); }; }
function ajustarAlturasKanban(){
  const headerH = document.querySelector('.navbar')?.offsetHeight || 0;
  const hdr = document.querySelector('.page-header')?.offsetHeight || 0;
  const maxH = Math.max(260, window.innerHeight - (headerH + hdr + 80));
  document.querySelectorAll('.kanban-dropzone').forEach(z=>{ z.style.maxHeight = maxH + 'px'; });
}
window.addEventListener('resize', debounce(ajustarAlturasKanban, 150));

function activarDrag() {
    const lists = Array.from(document.querySelectorAll('.drag-inner-list'));
    dragula(lists, {
        moves: (el) => {
            const mesaUsuarioId = parseInt(el.dataset.mesero) || null;
            return usuarioActual.rol === 'admin' || mesaUsuarioId === usuarioActual.id;
        }
    });
}

// Modal de asignación de mesero
async function abrirAsignarMesero(mesa) {
    try {
        // Cargar meseros si no están en cache
        if (!Array.isArray(window.__meserosCat) || !window.__meserosCat?.length) {
            const r = await fetch('../../api/usuarios/listar_meseros.php');
            const j = await r.json();
            if (j && j.success) {
                window.__meserosCat = j.resultado || [];
            } else {
                alert('No fue posible cargar meseros');
                return;
            }
        }
        const modal = document.getElementById('modalAsignarMesero');
        if (!modal) { alert('No se encontró el modal de asignación'); return; }
        modal.dataset.mesaId = String(mesa.id);
        const sel = modal.querySelector('#selMeseroAsignar');
        const info = modal.querySelector('#infoAsignarMesero');
        const pass = modal.querySelector('#passMeseroAsignar');
        if (info) info.textContent = `Mesa: ${mesa.nombre}`;
        if (pass) pass.value = '';
        if (sel) {
            sel.innerHTML = '<option value="">Seleccione mesero</option>' +
                (window.__meserosCat || []).map(me => `<option value="${me.id}">${me.nombre}</option>`).join('');
        }
        const btn = modal.querySelector('#btnConfirmarAsignacion');
        if (btn) {
            btn.onclick = async () => {
                const meseroId = parseInt(sel.value || '0', 10);
                const pwd = (pass.value || '').trim();
                if (!meseroId) { alert('Seleccione un mesero'); return; }
                if (!pwd) { alert('Ingrese la contraseña del mesero'); return; }
                // Verificar contraseña
                const v = await fetch('../../api/usuarios/verificar_password.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ usuario_id: meseroId, contrasena: pwd })
                }).then(r => r.json()).catch(() => null);
                if (!v || !v.success) { alert((v && v.mensaje) || 'Contraseña incorrecta'); return; }
                // Asignar mesa
                const a = await fetch('../../api/mesas/asignar.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mesa_id: parseInt(mesa.id), usuario_id: meseroId, usuario_asignador_id: (usuarioActual?.id || null) })
                }).then(r => r.json()).catch(() => null);
                if (!a || !a.success) { alert((a && a.mensaje) || 'No se pudo asignar'); return; }
                // Guardar autorización temporal y abrir directamente la modal de cambio de estado
                window.__mesaAuthTemp = { mesaId: parseInt(mesa.id), pass: pwd };
                hideModal('#modalAsignarMesero');
                // Simular que ya está asignada en el objeto actual para el flujo
                try {
                    const ms = (window.__meserosCat || []).find(x => parseInt(x.id) === meseroId);
                    mesa.usuario_id = meseroId;
                    mesa.mesero_nombre = ms ? ms.nombre : (mesa.mesero_nombre || '');
                } catch(_) {}
                // Ocultar botón Asignar en la tarjeta pertinente
                try {
                    const card = document.querySelector(`.kanban-item[data-mesa="${mesa.id}"]`);
                    const btnAsign = card && card.querySelector('button.asignar');
                    if (btnAsign) btnAsign.hidden = true;
                } catch(_) {}
                // Abrir modal de cambio de estado sin solicitar nuevamente contraseña
                abrirCambioEstado(mesa);
            };
        }
        showModal('#modalAsignarMesero');
    } catch (e) {
        console.error(e);
        alert('No fue posible iniciar la asignación');
    }
}

function abrirCambioEstado(mesa) {
    try {
        const mesaId = parseInt(mesa.id);
        const asignadoA = mesa.usuario_id ? parseInt(mesa.usuario_id) : null;
        const requiereAuth = (usuarioActual.rol !== 'admin') && (asignadoA && asignadoA !== usuarioActual.id);

        // Preparar modal de selección de estado
        const estadoModal = document.getElementById('modalCambioEstado');
        if (!estadoModal) { alert('No se encontró el modal de cambio de estado'); return; }
        estadoModal.dataset.mesaId = String(mesaId);

        // Inicializar checkboxes (solo una selección)
        const checks = Array.from(estadoModal.querySelectorAll('#estadoOpciones input[type="checkbox"]'));
        checks.forEach(cb => {
            cb.checked = (cb.value === String(mesa.estado));
            cb.onchange = () => {
                if (cb.checked) {
                    checks.forEach(otro => { if (otro !== cb) otro.checked = false; });
                }
                const esReservada = checks.find(x => x.value === 'reservada')?.checked;
                const rc = document.getElementById('reservaCampos');
                if (rc) rc.style.display = esReservada ? '' : 'none';
            };
        });
        // Mostrar/ocultar campos reserva según selección inicial
        const rc = document.getElementById('reservaCampos');
        if (rc) rc.style.display = (mesa.estado === 'reservada') ? '' : 'none';

        // Botón guardar: re-asignar manejador cada vez
        const btnGuardar = document.getElementById('btnGuardarEstadoMesa');
        if (btnGuardar) {
            btnGuardar.onclick = async () => {
                const sel = checks.find(c => c.checked);
                if (!sel) { alert('Seleccione un estado'); return; }
                const nuevo = sel.value;
                let nombre_reserva = null, fecha_reserva = null;
                if (nuevo === 'reservada') {
                    nombre_reserva = (document.getElementById('reservaNombre')?.value || '').trim();
                    fecha_reserva = (document.getElementById('reservaFecha')?.value || '').trim();
                    if (!nombre_reserva || !fecha_reserva) {
                        alert('Ingrese nombre y fecha de la reserva');
                        return;
                    }
                }
                const authPass = (window.__mesaAuthTemp && window.__mesaAuthTemp.mesaId === mesaId) ? window.__mesaAuthTemp.pass : null;
                const ok = await cambiarEstado(mesaId, nuevo, authPass, { nombre_reserva, fecha_reserva });
                if (ok) {
                    hideModal('#modalCambioEstado');
                    window.__mesaAuthTemp = null;
                    await cargarMesas();
                }
            };
        }

        if (requiereAuth) {
            const tmpAuth = window.__mesaAuthTemp;
            if (tmpAuth && tmpAuth.mesaId === mesaId && tmpAuth.pass) {
                showModal('#modalCambioEstado');
                return;
            }
            const authModal = document.getElementById('modalAuthMesa');
            if (!authModal) { alert('No se encontró el modal de autorización'); return; }
            authModal.dataset.mesaId = String(mesaId);
            const passInput = authModal.querySelector('#authMesaPass');
            const info = authModal.querySelector('#authMesaInfo');
            if (passInput) passInput.value = '';
            if (info) info.textContent = mesa.mesero_nombre ? `Mesero asignado: ${mesa.mesero_nombre}` : '';
            const btnContinuar = document.getElementById('btnAuthMesaContinuar');
            if (btnContinuar) {
                btnContinuar.onclick = () => {
                    const val = (authModal.querySelector('#authMesaPass')?.value || '').trim();
                    if (!val) { alert('Ingrese la contraseña'); return; }
                    window.__mesaAuthTemp = { mesaId, pass: val };
                    hideModal('#modalAuthMesa');
                    setTimeout(() => showModal('#modalCambioEstado'), 200);
                };
            }
            showModal('#modalAuthMesa');
        } else {
            showModal('#modalCambioEstado');
        }
    } catch (e) {
        console.error(e);
        alert('No fue posible iniciar el cambio de estado');
    }
}

async function cambiarEstado(id, estado, authPass = null, extras = {}) {
    try {
        const payload = { mesa_id: parseInt(id), nuevo_estado: estado };
        if (authPass) payload.pass_asignado = authPass;
        if (estado === 'reservada') {
            if (extras && extras.nombre_reserva) payload.nombre_reserva = extras.nombre_reserva;
            if (extras && extras.fecha_reserva) payload.fecha_reserva = extras.fecha_reserva;
        }
        const resp = await fetch('../../api/mesas/cambiar_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            return true;
        } else {
            alert(data.mensaje);
            return false;
        }
    } catch (err) {
        console.error(err);
        alert('Error al cambiar estado');
        return false;
    }
}

function solicitarTicket(mesaId, nombre, ventaId) {
    if (!ventaId) {
        alert('La mesa no tiene venta activa');
        return;
    }
    fetch('../../api/mesas/enviar_ticket.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mesa_id: parseInt(mesaId) })
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('Ticket solicitado');
            } else {
                alert(d.mensaje);
            }
        })
        .catch(() => alert('Error al solicitar ticket'));
}

async function imprimirComanda(ventaId) {
    if (!ventaId) {
        alert('No hay venta para imprimir');
        return;
    }
    try {
        const res = await fetch('../../api/tickets/imprime_comanda.php?venta_id=' + encodeURIComponent(ventaId));
        if (!res.ok) {
            alert('No se pudo enviar la comanda (' + res.status + ')');
        }
    } catch (e) {
        console.error('[COMANDA] No se pudo imprimir', e);
        alert('No se pudo imprimir la comanda');
    }
}

async function imprimirComandaDetalle(ventaId, detalleId) {
    if (!ventaId || !detalleId) {
        alert('Faltan datos para imprimir');
        return;
    }
    try {
        const url = '../../api/tickets/imprime_comanda.php?venta_id=' + encodeURIComponent(ventaId) + '&detalle_id=' + encodeURIComponent(detalleId);
        const res = await fetch(url);
        if (!res.ok) {
            alert('No se pudo imprimir el producto (' + res.status + ')');
        }
    } catch (e) {
        console.error('[COMANDA DETALLE] No se pudo imprimir', e);
        alert('No se pudo imprimir el producto');
    }
}

function normalizarProductosVentaMesa(lista) {
    if (!Array.isArray(lista)) return [];
    return lista.map(p => {
        const cantidad = Number(p.cantidad || 0);
        const precio = Number(p.precio_unitario || 0);
        const prodId = parseInt(p.producto_id || p.id_producto || p.id || 0, 10);
        const catId = parseInt(p.categoria_id || 0, 10);
        const subtotal = (typeof p.subtotal !== 'undefined')
            ? Number(p.subtotal || 0)
            : Number((cantidad * precio).toFixed(2));
        return Object.assign({}, p, {
            producto_id: prodId,
            categoria_id: catId,
            cantidad,
            precio_unitario: precio,
            subtotal
        });
    });
}

function mostrarErrorPromosMesa(mensajes) {
    const body = document.getElementById('promoErrorMsg');
    if (body) {
        if (Array.isArray(mensajes)) {
            body.innerHTML = '<p>La promocion no aplica porque:</p><ul>' +
                mensajes.map(m => '<li>' + m + '</li>').join('') +
                '</ul>';
        } else {
            body.textContent = mensajes || 'La promocion no aplica';
        }
    }
    try {
        if (window.jQuery && $('#modalPromoError').modal) {
            $('#modalPromoError').modal('show');
            return;
        }
    } catch (_) { /* sin modal */ }
    const texto = Array.isArray(mensajes) ? mensajes.join('\n') : (mensajes || 'La promocion no aplica');
    alert(texto);
}

function actualizarResumenPromoMesa(total) {
    totalPromoMesaActual = Number(total || 0);
    const resumen = document.getElementById('promoMesaResumen');
    if (resumen) {
        if (totalPromoMesaActual > 0) {
            resumen.textContent = 'Descuento promociones aplicado: $' + totalPromoMesaActual.toFixed(2);
        } else {
            resumen.textContent = 'Sin promociones aplicadas';
        }
    }
}

function distribuirDescuentoPorPromo(ids, total) {
    const res = [];
    const totalDesc = Number(total || 0);
    if (!Array.isArray(ids) || !ids.length || totalDesc <= 0) return res;
    const count = ids.length;
    const base = Number((totalDesc / count).toFixed(2));
    let acumulado = 0.0;
    ids.forEach((id, idx) => {
        const promoId = parseInt(id, 10);
        if (!promoId) return;
        let monto = base;
        if (idx === count - 1) {
            monto = Number((totalDesc - acumulado).toFixed(2));
        } else {
            acumulado += base;
        }
        res.push({ promo_id: promoId, descuento_aplicado: monto });
    });
    return res;
}

function validarPromoSeleccionMesa(selectEl) {
    const promoId = parseInt((selectEl && selectEl.value) || '0', 10);
    if (!promoId) {
        return { ok: true };
    }
    const promo = (catalogoPromosMesa || []).find(p => parseInt(p.id, 10) === promoId);
    if (!promo) return { ok: true };
    let reglasArray = [];
    if (promo.regla) {
        try {
            const rj = JSON.parse(promo.regla);
            reglasArray = Array.isArray(rj) ? rj : (rj ? [rj] : []);
        } catch (_) { reglasArray = []; }
    }
    const tipoPromo = String(promo.tipo || '').toLowerCase();
    if (promoId === 6 && tipoPromo === 'combo') {
        const rollIds = reglasArray.map(r => parseInt(r.id_producto || 0, 10)).filter(Boolean);
        const teaId = 66;
        let rollUnits = 0;
        let teaUnits = 0;
        productosVentaMesaActual.forEach(p => {
            const pid = parseInt(p.producto_id || p.id || 0, 10);
            const cant = Number(p.cantidad || 0);
            if (rollIds.includes(pid)) rollUnits += cant;
            if (pid === teaId) teaUnits += cant;
        });
        if (rollUnits < 2 || teaUnits < 1) {
            const msg = 'La promocion es la combinacion de 2 rollos mas te. Faltan productos para aplicarla.';
            mostrarErrorPromosMesa(msg);
            if (selectEl) selectEl.value = '0';
            return { ok: false, mensajes: [msg] };
        }
    }

    const mensajes = [];
    reglasArray.forEach(r => {
        const reqCant = parseInt(r.cantidad || 0, 10) || 0;
        if (!reqCant) return;

        if (r.id_producto) {
            const pid = parseInt(r.id_producto, 10);
            const exist = productosVentaMesaActual.reduce((s, p) => {
                const pId = parseInt(p.producto_id || p.id || 0, 10);
                const cant = Number(p.cantidad || 0);
                return s + (pId === pid ? cant : 0);
            }, 0);

            if (exist < reqCant) {
                let nombre = null;
                if (promo && Array.isArray(promo.productos_regla)) {
                    const prodRegla = promo.productos_regla.find(pr => parseInt(pr.id, 10) === pid);
                    if (prodRegla && prodRegla.nombre) {
                        nombre = prodRegla.nombre;
                    }
                }
                mensajes.push(`Producto ${nombre ? nombre : 'ID ' + pid}: se requieren ${reqCant}, solo hay ${exist}.`);
            }
        } else if (r.categoria_id) {
            const cid = parseInt(r.categoria_id, 10);
            const exist = productosVentaMesaActual.reduce((s, p) => {
                const cId = parseInt(p.categoria_id || 0, 10);
                const cant = Number(p.cantidad || 0);
                return s + (cId === cid ? cant : 0);
            }, 0);

            if (exist < reqCant) {
                mensajes.push(`Categoria ${cid}: se requieren ${reqCant}, solo hay ${exist}.`);
            }
        }
    });

    if (mensajes.length) {
        mostrarErrorPromosMesa(mensajes);
        if (selectEl) selectEl.value = '0';
        return { ok: false, mensajes };
    }
    return { ok: true };
}

function validarPromosAcumulablesMesa(selectedIds = []) {
    const tipoEntrega = (tipoEntregaMesaActual || '').toLowerCase();
    if (tipoEntrega !== 'domicilio' && tipoEntrega !== 'rapido' && tipoEntrega !== 'llevar') {
        return { ok: true };
    }
    if (!Array.isArray(selectedIds) || !selectedIds.length) return { ok: true };

    const promosSel = selectedIds
        .map(pid => (catalogoPromosMesa || []).find(p => parseInt(p.id, 10) === pid))
        .filter(Boolean);

    const combos = promosSel.filter(p => {
        const tipo = String(p.tipo || '').toLowerCase();
        const monto = Number(p.monto || 0);
        const tipoVenta = String(p.tipo_venta || '').toLowerCase();
        return tipo === 'combo' && monto > 0 && tipoVenta === 'llevar';
    });
    if (!combos.length) return { ok: true };

    const countPromos = (id) => combos.filter(p => parseInt(p.id, 10) === id).length;
    const promo5Count = countPromos(5);
    const promo6Count = countPromos(6);
    const promo9Count = countPromos(9);
    if (!promo5Count && !promo6Count && !promo9Count) return { ok: true };

    const promo6 = combos.find(p => parseInt(p.id, 10) === 6);
    const promo9Obj = combos.find(p => parseInt(p.id, 10) === 9) || {};

    let rollPromo6Ids = [];
    if (promo6Count && promo6 && promo6.regla) {
        let rj;
        try { rj = JSON.parse(promo6.regla); } catch (e) { rj = null; }
        const arr = Array.isArray(rj) ? rj : (rj ? [rj] : []);
        rollPromo6Ids = arr
            .map(r => parseInt(r.id_producto || 0, 10))
            .filter(v => !!v);
    }

    const categoriasConteo = {};
    let rollSubsetCount = 0;
    let teaCount = 0;
    productosVentaMesaActual.forEach(p => {
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
            promo.categorias_regla.forEach(cat => {
                const cId = parseInt(cat && cat.id, 10);
                if (cId) ids.push(cId);
            });
        }
        if (!ids.length && promo && promo.regla) {
            let regla;
            try { regla = JSON.parse(promo.regla); } catch (e) { regla = null; }
            const arr = Array.isArray(regla) ? regla : (regla ? [regla] : []);
            arr.forEach(r => {
                const cId = parseInt(r && r.categoria_id, 10);
                if (cId) ids.push(cId);
            });
        }
        return Array.from(new Set(ids.filter(Boolean)));
    };

    const categoriasPromo9 = extraerCategoriasPromo(promo9Obj);
    const categoriasParaConteo = categoriasPromo9.length ? categoriasPromo9 : [9];
    const catPromoCount = categoriasParaConteo.reduce((sum, cId) => sum + (categoriasConteo[cId] || 0), 0);

    if (!catPromoCount && !rollSubsetCount && !teaCount) {
        return { ok: true };
    }

    const describirPromos = function(nombres) {
        const lista = (nombres || []).filter(Boolean);
        if (!lista.length) return '';
        const fraseBase = lista.length === 1 ? 'La promocion' : 'Las promociones';
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
    const nombrePromo5 = (combos.find(p => parseInt(p.id, 10) === 5) || {}).nombre || '2 Tes';
    const nombrePromo6 = (promo6 || {}).nombre || '2 rollos y te';
    const nombrePromo9 = (promo9Obj && promo9Obj.nombre) || '3x $209 en rollos';
    let nombreCat9 = 'categoria 9';
    if (promo9Obj && Array.isArray(promo9Obj.categorias_regla) && promo9Obj.categorias_regla.length) {
        const nombres = promo9Obj.categorias_regla.map(cat => cat && cat.nombre ? cat.nombre : null).filter(Boolean);
        if (nombres.length === 1) {
            nombreCat9 = nombres[0];
        } else if (nombres.length > 1) {
            nombreCat9 = nombres.join(', ');
        }
    } else if (categoriasParaConteo.length === 1) {
        nombreCat9 = 'categoria ' + categoriasParaConteo[0];
    } else if (categoriasParaConteo.length > 1) {
        nombreCat9 = 'categorias ' + categoriasParaConteo.join(', ');
    }

    const totalTeaNeeded = (promo6Count * 1) + (promo5Count * 2);
    if ((promo5Count || promo6Count) && totalTeaNeeded > teaCount) {
        errores.push(`${describirPromos([
            promo6Count ? nombrePromo6 : null,
            promo5Count ? nombrePromo5 : null,
        ])} requieren ${totalTeaNeeded} tes y solo hay ${teaCount}.`);
    }

    const totalRollPromo6Needed = promo6Count * 2;
    if (promo6Count && totalRollPromo6Needed > rollSubsetCount) {
        errores.push(`${describirPromos([nombrePromo6])} requiere ${totalRollPromo6Needed} rollos validos y solo hay ${rollSubsetCount}.`);
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

function calcularDescuentoCombosEspecialesMesa(prodsSub, promoCountMap) {
    try {
        const countPromo5 = promoCountMap[5] || 0;
        const countPromo6 = promoCountMap[6] || 0;
        const countPromo9 = promoCountMap[9] || 0;
        if (!countPromo5 && !countPromo6 && !countPromo9) {
            return 0;
        }
        const buscarPromo = (id) => (catalogoPromosMesa || []).find(p => parseInt(p.id, 10) === id) || null;
        const promo5 = countPromo5 ? buscarPromo(5) : null;
        const promo6 = countPromo6 ? buscarPromo(6) : null;
        const promo9 = countPromo9 ? buscarPromo(9) : null;

        const promo6RollIds = [];
        if (promo6 && promo6.regla) {
            try {
                const regla6 = JSON.parse(promo6.regla);
                const arr = Array.isArray(regla6) ? regla6 : (regla6 ? [regla6] : []);
                arr.forEach(r => {
                    const pid = parseInt(r && r.id_producto, 10);
                    if (pid) promo6RollIds.push(pid);
                });
            } catch (_) {}
        }

        const teaId = 66;
        const unidadesRollCat9 = [];
        const unidadesRollPromo6 = [];
        const unidadesTea = [];
        let unique = 0;
        prodsSub.forEach(p => {
            const pid = parseInt(p.producto_id || p.id || 0, 10);
            const cat = parseInt(p.categoria_id || 0, 10);
            const qty = Math.max(1, parseInt(p.cantidad || 1, 10));
            const price = Number(p.precio_unitario || 0);
            for (let i = 0; i < qty; i++) {
                const unit = { pid, cat, price, key: `${pid}-${cat}-${unique++}` };
                if (cat === 9) unidadesRollCat9.push(unit);
                if (promo6RollIds.includes(pid)) unidadesRollPromo6.push(unit);
                if (pid === teaId) unidadesTea.push(unit);
            }
        });

        if (!unidadesRollCat9.length && !unidadesRollPromo6.length && !unidadesTea.length) {
            return 0;
        }

        const sortDesc = arr => arr.sort((a, b) => Number(b.price || 0) - Number(a.price || 0));
        sortDesc(unidadesRollCat9);
        sortDesc(unidadesRollPromo6);
        sortDesc(unidadesTea);

        const tomarUnidades = (arr, cantidad) => {
            if (cantidad <= 0) return [];
            return arr.splice(0, Math.min(cantidad, arr.length));
        };
        const eliminarDe = (arr, unidades) => {
            unidades.forEach(u => {
                const idx = arr.findIndex(item => item.key === u.key);
                if (idx !== -1) arr.splice(idx, 1);
            });
        };

        let totalDescuento = 0;

        if (promo6 && countPromo6 > 0) {
            const monto6 = Number(promo6.monto || 0);
            const maxCombos6 = Math.min(
                countPromo6,
                Math.floor(unidadesRollPromo6.length / 2),
                unidadesTea.length
            );
            for (let i = 0; i < maxCombos6; i++) {
                const rollos = tomarUnidades(unidadesRollPromo6, 2);
                eliminarDe(unidadesRollCat9, rollos);
                const tes = tomarUnidades(unidadesTea, 1);
                const sumaGrupo = rollos.concat(tes).reduce((s, u) => s + Number(u.price || 0), 0);
                totalDescuento += Math.max(0, sumaGrupo - monto6);
            }
        }

        if (promo9 && countPromo9 > 0) {
            const monto9 = Number(promo9.monto || 0);
            const maxCombos9 = Math.min(countPromo9, Math.floor(unidadesRollCat9.length / 3));
            for (let i = 0; i < maxCombos9; i++) {
                const rollos = tomarUnidades(unidadesRollCat9, 3);
                const sumaGrupo = rollos.reduce((s, u) => s + Number(u.price || 0), 0);
                totalDescuento += Math.max(0, sumaGrupo - monto9);
            }
        }

        if (promo5 && countPromo5 > 0) {
            const monto5 = Number(promo5.monto || 0);
            const maxCombos5 = Math.min(countPromo5, Math.floor(unidadesTea.length / 2));
            for (let i = 0; i < maxCombos5; i++) {
                const tes = tomarUnidades(unidadesTea, 2);
                const sumaGrupo = tes.reduce((s, u) => s + Number(u.price || 0), 0);
                totalDescuento += Math.max(0, sumaGrupo - monto5);
            }
        }

        return totalDescuento;
    } catch (err) {
        console.error('Error al calcular combos especiales', err);
        return 0;
    }
}

function calcularDescuentoPromosMesa(selectedIds = []) {
    const prods = Array.isArray(productosVentaMesaActual) ? productosVentaMesaActual : [];
    const totalBruto = prods.reduce((s, p) => s + (Number(p.cantidad || 0) * Number(p.precio_unitario || 0)), 0);
    const promoCountMap = {};
    selectedIds.forEach(id => {
        const pid = parseInt(id, 10);
        if (!pid) return;
        promoCountMap[pid] = (promoCountMap[pid] || 0) + 1;
    });
    if (!selectedIds.length) {
        return { ok: true, totalPromo: 0, totalBruto };
    }

    const resAcum = validarPromosAcumulablesMesa(selectedIds);
    if (resAcum && resAcum.ok === false) {
        return { ok: false, mensajes: resAcum.mensajes || [] };
    }

    let totalPromo = 0;
    try {
        const poolByPromo = (promo) => {
            let reglaJson = {};
            try { reglaJson = promo.regla ? JSON.parse(promo.regla) : {}; } catch(_) { reglaJson = {}; }
            const reglasArray = Array.isArray(reglaJson) ? reglaJson : [reglaJson];
            const prodIds  = reglasArray.map(r => parseInt(r.id_producto || 0, 10)).filter(Boolean);
            const catIds   = reglasArray.map(r => parseInt(r.categoria_id || 0, 10)).filter(Boolean);
            const items = prods.filter(p => (prodIds.length ? prodIds.includes(parseInt(p.producto_id || p.id || 0, 10)) : true)
                                          && (catIds.length ? catIds.includes(parseInt(p.categoria_id || 0, 10)) : true));
            const unitPrices = [];
            items.forEach(it => {
                const qty = Math.max(1, parseInt(it.cantidad || 1, 10));
                const price = Number(it.precio_unitario || 0);
                for (let k = 0; k < qty; k++) unitPrices.push(price);
            });
            unitPrices.sort((a,b)=>Number(a)-Number(b));
            return unitPrices;
        };
        let sumNonMonto = 0;
        let montoMin = null;
        selectedIds.forEach(pid => {
            const promo = (catalogoPromosMesa || []).find(p => parseInt(p.id) === pid);
            if (!promo) return;
            const tipo = String(promo.tipo||'').toLowerCase();
            const monto = Number(promo.monto||0);
            let reglaJson = {};
            try { reglaJson = promo.regla ? JSON.parse(promo.regla) : {}; } catch(_) { reglaJson = {}; }
            const cantidad = parseInt((Array.isArray(reglaJson) ? (reglaJson[0]?.cantidad) : (reglaJson?.cantidad)) || '0', 10) || (tipo==='bogo'?2:1);
            if (tipo === 'combo' && monto > 0) {
                return;
            }
            if (monto>0 && (tipo==='monto_fijo' || tipo==='bogo')) {
                montoMin = (montoMin===null) ? monto : Math.min(montoMin, monto);
            } else {
                const pool = poolByPromo(promo);
                if (pool.length >= cantidad) {
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
            discMonto = Math.max(0, Number((totalBruto - montoMin).toFixed(2)));
        }
        totalPromo = Math.min(totalBruto, Number((discMonto + sumNonMonto).toFixed(2)));
    } catch(_) { totalPromo = 0; }

    try {
        const selectedIdsCat = Array.from(new Set(selectedIds));
        let totalPromoCatCombo = 0;
        selectedIdsCat.forEach(pid => {
            const promo = (catalogoPromosMesa || []).find(p => parseInt(p.id) === pid);
            if (!promo) return;
            const tipo = String(promo.tipo || '').toLowerCase();
            const monto = Number(promo.monto || 0);
            if (tipo !== 'combo' || !promo.regla) return;
            if (parseInt(promo.id, 10) === 9) return;
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
            unitPricesCat.sort((a, b) => Number(b) - Number(a));
            const grupos = Math.floor(unitPricesCat.length / cantidadReq);
            const maxAplicaciones = Math.min(grupos, promoCountMap[pid] || 0);
            for (let g = 0; g < maxAplicaciones; g++) {
                const offset = g * cantidadReq;
                const grupo = unitPricesCat.slice(offset, offset + cantidadReq);
                const sumaGrupo = grupo.reduce((s, x) => s + Number(x || 0), 0);
                const desc = Math.max(0, sumaGrupo - monto);
                totalPromoCatCombo += desc;
            }
        });
        if (totalPromoCatCombo > 0) {
            totalPromo = Math.min(totalBruto, Number((totalPromo + totalPromoCatCombo).toFixed(2)));
        }
    } catch(_) {}
    try {
        const descuentoEspecial = calcularDescuentoCombosEspecialesMesa(prods, promoCountMap);
        if (descuentoEspecial > 0) {
            totalPromo = Math.min(totalBruto, Number((totalPromo + descuentoEspecial).toFixed(2)));
        }
    } catch (_) {}
    return { ok: true, totalPromo: Number((totalPromo || 0).toFixed(2)), totalBruto };
}

function validarPromosMesaYRecalcular() {
    const ids = obtenerPromosSeleccionadasMesa();
    if (!ids.length) {
        actualizarResumenPromoMesa(0);
        return { ok: true, ids, totalPromo: 0 };
    }
    const selects = Array.from(document.querySelectorAll('#listaPromosMesa select.promo-mesa-select'));
    for (const sel of selects) {
        const res = validarPromoSeleccionMesa(sel);
        if (!res.ok) {
            actualizarResumenPromoMesa(0);
            return { ok: false };
        }
    }
    const calc = calcularDescuentoPromosMesa(ids);
    if (!calc || calc.ok === false) {
        mostrarErrorPromosMesa((calc && calc.mensajes) || ['La promocion seleccionada no es valida']);
        actualizarResumenPromoMesa(0);
        return { ok: false };
    }
    actualizarResumenPromoMesa(calc.totalPromo || 0);
    return { ok: true, ids, totalPromo: calc.totalPromo || 0 };
}

async function cargarPromocionesMesa() {
    if (Array.isArray(catalogoPromosMesa) && catalogoPromosMesa.length) {
        return catalogoPromosMesa;
    }
    try {
        const resp = await fetch('../../api/tickets/promociones.php');
        const data = await resp.json();
        if (data && data.success && Array.isArray(data.promociones)) {
            catalogoPromosMesa = data.promociones.filter(p => {
                const tipo = String(p.tipo_venta || '').toLowerCase();
                return !tipo || tipo === 'mesa';
            });
        } else {
            catalogoPromosMesa = [];
        }
    } catch (e) {
        console.error('No se pudieron cargar promociones', e);
        catalogoPromosMesa = [];
    }
    return catalogoPromosMesa;
}

function construirOpcionesPromoMesa(selectEl) {
    if (!selectEl) return;
    selectEl.innerHTML = '<option value=\"\">Seleccione promoción</option>';
    (catalogoPromosMesa || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nombre;
        selectEl.appendChild(opt);
    });
}

function agregarSelectPromoMesa(valor = '') {
    const cont = document.getElementById('listaPromosMesa');
    if (!cont) return;
    const row = document.createElement('div');
    row.className = 'd-flex align-items-center gap-2 mb-2';
    const sel = document.createElement('select');
    sel.className = 'form-control promo-mesa-select';
    sel.style.maxWidth = '320px';
    construirOpcionesPromoMesa(sel);
    if (valor && sel.querySelector(`option[value=\"${valor}\"]`)) {
        sel.value = valor;
    }
    sel.addEventListener('change', () => validarPromosMesaYRecalcular());
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-danger btn-sm';
    btn.textContent = 'Quitar';
    btn.addEventListener('click', () => {
        row.remove();
        validarPromosMesaYRecalcular();
    });
    row.appendChild(sel);
    row.appendChild(btn);
    cont.appendChild(row);
    validarPromosMesaYRecalcular();
}

function preseleccionarPromosMesa(ids = []) {
    const cont = document.getElementById('listaPromosMesa');
    if (!cont) return;
    cont.innerHTML = '';
    if (!Array.isArray(ids) || !ids.length) {
        agregarSelectPromoMesa();
        return;
    }
    ids.forEach(id => agregarSelectPromoMesa(id));
    validarPromosMesaYRecalcular();
}

function obtenerPromosSeleccionadasMesa() {
    const sels = Array.from(document.querySelectorAll('#listaPromosMesa select.promo-mesa-select'));
    const ids = [];
    sels.forEach(sel => {
        const val = parseInt(sel.value || '0', 10);
        if (val > 0) ids.push(val);
    });
    return Array.from(new Set(ids));
}

async function guardarPromocionesMesa(ventaId, silencioso = false) {
    const panel = document.getElementById('panelPromosMesa');
    if (!panel) return true;
    if (panel.style.display === 'none') return true;
    if (!ventaId) {
        if (!silencioso) alert('No hay venta para guardar promociones');
        return false;
    }
    const validacion = validarPromosMesaYRecalcular();
    if (!validacion || !validacion.ok) {
        return false;
    }
    const seleccionadas = Array.isArray(validacion.ids) ? validacion.ids : obtenerPromosSeleccionadasMesa();
    const totalDesc = Number(validacion.totalPromo || 0);
    const payload = {
        venta_id: parseInt(ventaId, 10),
        promociones_ids: seleccionadas,
        descuento_total: totalDesc,
        promociones_detalle: distribuirDescuentoPorPromo(seleccionadas, totalDesc)
    };
    if (seleccionadas.length) {
        payload.promocion_id = seleccionadas[0];
    }
    try {
        const resp = await fetch('../../api/mesas/actualizar_promociones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (!data || !data.success) {
            if (!silencioso) alert((data && data.mensaje) || 'No se pudieron guardar las promociones');
            return false;
        }
        if (!silencioso) alert('Promociones guardadas');
        return true;
    } catch (e) {
        console.error('No se pudieron guardar promociones', e);
        if (!silencioso) alert('Error al guardar promociones');
        return false;
    }
}

async function imprimirTicketMesa(ventaId) {
    if (!ventaId) {
        alert('No hay venta para imprimir');
        return;
    }
    try {
        const res = await fetch('../../api/tickets/imprime_ticket_mesa.php?venta_id=' + encodeURIComponent(ventaId));
        const raw = await res.text();
        let ok = res.ok;
        let mensaje = 'Ticket enviado a la impresora';
        if (raw) {
            try {
                const data = JSON.parse(raw);
                if (data && typeof data === 'object') {
                    ok = !!data.success;
                    if (data.success) {
                        if (data.resultado && data.resultado.mensaje) {
                            mensaje = data.resultado.mensaje;
                        }
                    } else {
                        mensaje = data.mensaje || mensaje;
                    }
                }
            } catch (_) {
                // respuesta plana, continuar
            }
        }
        if (!ok) {
            throw new Error(mensaje || 'No se pudo imprimir el ticket');
        }
        alert(mensaje);
    } catch (e) {
        console.error('[TICKET MESA] No se pudo imprimir', e);
        alert(e.message || 'No se pudo imprimir el ticket');
    }
}

async function guardarObservacionVenta(ventaId, silent = false) {
    const obsInput = document.getElementById('ventaObservacion');
    if (!obsInput) return true;
    const observacion = (obsInput.value || '').trim();
    try {
        const resp = await fetch('../../api/ventas/actualizar_observacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(ventaId), observacion })
        });
        const data = await resp.json();
        if (data.success) {
            const info = document.getElementById('observacionInfo');
            if (info) {
                info.textContent = 'Comentarios guardados';
                setTimeout(() => { info.textContent = ''; }, 2000);
            }
            if (!silent) alert('Comentarios guardados');
            return true;
        }
        alert(data.mensaje || 'No se pudo guardar comentarios');
        return false;
    } catch (e) {
        console.error('No se pudo guardar observación', e);
        if (!silent) alert('Error al guardar comentarios');
        return false;
    }
}

function textoEstado(e) {
    return (e || '').replace('_', ' ');
}


async function cargarCatalogo() {
    try {
        const resp = await fetch('../../api/inventario/listar_productos.php');
        const data = await resp.json();
        if (data.success) {
            productos = data.resultado;
            window.catalogo = data.resultado;
        }
    } catch (err) {
        console.error(err);
    }
}
async function eliminarDetalle(detalleId, ventaId) {
    if (!confirm('¿Eliminar producto?')) return;

    try {
        const resp = await fetch('../../api/mesas/eliminar_producto_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ detalle_id: Number(detalleId) })
        });

        const contentType = resp.headers.get('content-type') || '';
        const raw = await resp.text(); // leemos SIEMPRE como texto primero

        if (!resp.ok) {
            console.error('HTTP error:', resp.status, raw);
            alert(`Error del servidor (HTTP ${resp.status}).`);
            return;
        }

        let data;
        if (contentType.includes('application/json')) {
            try {
                data = JSON.parse(raw);
            } catch (e) {
                console.error('JSON inválido:', raw);
                alert('Respuesta no válida del servidor.');
                return;
            }
        } else {
            console.error('No es JSON, cuerpo:', raw);
            alert('El servidor no devolvió JSON.');
            return;
        }

        if (data.success) {
            verDetalles(ventaId);
            await cargarHistorial();
        } else {
            alert(data.mensaje || 'Operación no exitosa.');
        }
    } catch (err) {
        console.error(err);
        alert('Error al eliminar');
    }
}
async function cargarHistorial() {
  // esta función está vacía pero evita el error
  // si luego quieres cargar algo tipo historial de movimientos, puedes implementarlo aquí
}


async function marcarEntregado(detalleId, ventaId) {
    try {
        const resp = await fetch('../../api/mesas/marcar_entregado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ detalle_id: parseInt(detalleId) })
        });
        const data = await resp.json();
        if (data.success) {
            huboCambios = true;
            verDetalles(ventaId, mesaIdActual, '', estadoMesaActual);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al actualizar');
    }
}
async function agregarDetalle() {
    if (!ventaIdActual && estadoMesaActual !== 'ocupada') {
        alert('La mesa debe estar ocupada para iniciar una venta');
        return;
    }

    const select = document.getElementById('detalle_producto');
    const cantidad = parseFloat(document.getElementById('detalle_cantidad').value);
    const productoId = parseInt(select.value);
    const prod = (window.catalogo || []).find(p => parseInt(p.id) === productoId);
    const precio = prod ? parseFloat(prod.precio) : parseFloat(select.selectedOptions[0]?.dataset.precio || 0);

    if (isNaN(productoId) || isNaN(cantidad) || cantidad <= 0) {
        alert('Producto o cantidad inválida');
        return;
    }

    if (prod && prod.existencia && cantidad > parseFloat(prod.existencia)) {
        alert(`Solo hay ${prod.existencia} disponibles de ${prod.nombre}`);
        return;
    }

    let currentVentaId = ventaIdActual;

    if (!currentVentaId) {
        try {
            const corteResp = await fetch('../../api/corte_caja/verificar_corte_cajero_abierto.php', { credentials: 'include' });
            const corteData = await corteResp.json();
            if (!corteData.success || !corteData.resultado.abierto) {
                alert('No hay corte abierto de un cajero. Nadie puede vender.');
                return;
            }
        } catch (err) {
            console.error(err);
            alert('Error al verificar caja');
            return;
        }
        const crearPayload = {
            mesa_id: parseInt(mesaIdActual),
            usuario_id: mesaMeseroIdActual ? parseInt(mesaMeseroIdActual) : null,
            productos: [{ producto_id: productoId, cantidad, precio_unitario: precio }]
        };
        try {
            const resp = await fetch('../../api/ventas/crear_venta_mesa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(crearPayload)
            });
            const data = await resp.json();
            if (data.success) {
                currentVentaId = data.resultado.venta_id;
                ventaIdActual = currentVentaId;
            } else {
                alert(data.mensaje);
                return;
            }
        } catch (err) {
            console.error(err);
            alert('Error al crear venta');
            return;
        }
    } else {
        const payload = {
            venta_id: currentVentaId,
            producto_id: productoId,
            cantidad,
            precio_unitario: precio
        };
        try {
            const resp = await fetch('../../api/mesas/agregar_producto_venta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();
            if (!data.success) {
                alert(data.mensaje);
                return;
            }
        } catch (err) {
            console.error(err);
            alert('Error al agregar producto');
            return;
        }
    }

    huboCambios = true;
    verDetalles(currentVentaId, mesaIdActual, '', estadoMesaActual);
    await cargarMesas();
}

function abrirDetalles(mesa) {
    try {
        const mesaId = parseInt(mesa.id);
        const asignadoA = mesa.usuario_id ? parseInt(mesa.usuario_id) : null;
        const requiereAuth = (usuarioActual.rol !== 'admin') && (asignadoA && asignadoA !== usuarioActual.id);
        mesaMeseroIdActual = asignadoA || null;

        const continuar = async () => {
            try {
                const resp = await fetch('../../api/corte_caja/verificar_corte_cajero_abierto.php', { credentials: 'include' });
                const data = await resp.json();
                if (!data.success || !data.resultado.abierto) {
                    alert('No hay corte abierto de un cajero. Nadie puede vender.');
                    return;
                }
            } catch (e) {
                console.error(e);
                alert('Error al verificar el estado de caja');
                return;
            }
            verDetalles(mesa.venta_id || '', String(mesa.id), String(mesa.nombre || ''), String(mesa.estado || ''));
        };

        if (requiereAuth) {
            const authModal = document.getElementById('modalAuthMesa');
            if (!authModal) { alert('No se encontró el modal de autorización'); return; }
            authModal.dataset.mesaId = String(mesaId);
            const passInput = authModal.querySelector('#authMesaPass');
            const info = authModal.querySelector('#authMesaInfo');
            if (passInput) passInput.value = '';
            if (info) info.textContent = mesa.mesero_nombre ? `Mesero asignado: ${mesa.mesero_nombre}` : '';
            const btnContinuar = document.getElementById('btnAuthMesaContinuar');
            if (btnContinuar) {
                btnContinuar.onclick = () => {
                    const val = (authModal.querySelector('#authMesaPass')?.value || '').trim();
                    if (!val) { alert('Ingrese la contraseña'); return; }
                    // Verificar contraseña del mesero asignado antes de continuar
                    fetch('../../api/usuarios/verificar_password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ usuario_id: asignadoA, contrasena: val })
                    })
                        .then(r => r.json())
                        .then(resp => {
                            if (!resp.success) { alert(resp.mensaje || 'Contraseña incorrecta'); return; }
                            window.__mesaAuthTemp = { mesaId, pass: val };
                            hideModal('#modalAuthMesa');
                            setTimeout(() => continuar(), 150);
                        })
                        .catch(() => alert('Error al verificar contraseña'));
                };
            }
            showModal('#modalAuthMesa');
        } else {
            continuar();
        }
    } catch (e) {
        console.error(e);
        alert('No fue posible abrir los detalles');
    }
}

async function verDetalles(ventaId, mesaId, mesaNombre, estado) {
    ventaIdActual = ventaId;
    mesaIdActual = mesaId;
    estadoMesaActual = estado;
    if (!mesaMeseroIdActual && mesaId) {
        try {
            const mesaCache = (mesasCache || []).find(m => String(m.id) === String(mesaId));
            if (mesaCache && mesaCache.usuario_id) {
                mesaMeseroIdActual = parseInt(mesaCache.usuario_id);
            }
        } catch (_) { /* noop */ }
    }

    // Verificar cajero con corte abierto siempre antes de mostrar detalles
    try {
        const resp = await fetch('../../api/corte_caja/verificar_corte_cajero_abierto.php', { credentials: 'include' });
        const corte = await resp.json();
        if (!corte.success || !corte.resultado.abierto) {
            alert('No hay corte abierto de un cajero. Nadie puede vender.');
            return;
        }
    } catch (err) {
        console.error(err);
        alert('Error al verificar caja');
        return;
    }

    const modal = document.getElementById('modalVenta');
    const contenedor = modal.querySelector('.modal-body');

    if (!ventaId) {
        productosVentaMesaActual = [];
        tipoEntregaMesaActual = '';
        actualizarResumenPromoMesa(0);
        contenedor.innerHTML = `<p>Destino: ${mesaNombre}</p>` + crearTablaProductos([]);
        inicializarBuscadorDetalle();
        modal.querySelector('#btnAgregarDetalle').addEventListener('click', agregarDetalle);
        showModal('#modalVenta');
        return;
    }

    try {
        const resp = await fetch('../../api/mesas/detalle_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(ventaId) })
        });
        const data = await resp.json();
        if (data.success) {
            const info = data.resultado;
            productosVentaMesaActual = normalizarProductosVentaMesa(info.productos || []);
            tipoEntregaMesaActual = String(info.tipo_entrega || '').toLowerCase();
            actualizarResumenPromoMesa(0);
            const header = `<p>Fecha inicio: ${info.fecha || ''}<br>Mesero: ${info.mesero || ''}<br>Destino: ${info.mesa || ''}</p>`;
            let html = header;
            html += crearTablaProductos(info.productos);
            html += `<br>`;
            html += `<div class="mb-2">`;
            html += `<button  class="btn custom-btn" id="imprimirTicket">Imprimir ticket</button>`;
            html += `<button  class="btn custom-btn" id="imprimirComanda" style="margin-left:8px;">Comanda</button>`;
            html += `</div>`;
            html += `
                <div class="mt-2">
                    <label for="ventaObservacion"><strong>Comentarios para comanda</strong></label>
                    <textarea id="ventaObservacion" class="form-control" rows="3" placeholder="Notas para cocina / comanda"></textarea>
                    <small id="observacionInfo" class="text-muted"></small>
                </div>
                <div class="mt-3" id="panelPromosMesa" style="display:none;">
                    <label><strong>Promociones</strong></label>
                    <div id="listaPromosMesa" class="mt-2"></div>
                    <div class="d-flex" style="gap:8px; margin-top:6px;">
                        <button type="button" class="btn btn-secondary" id="btnAgregarPromoMesa">Agregar promocion</button>
                        <button type="button" class="btn custom-btn" id="btnGuardarPromosMesa">Guardar promociones</button>
                    </div>
                    <div id="promoMesaResumen" class="mt-2 text-muted"></div>
                </div>
            `;
            contenedor.innerHTML = html;
            const obsInput = contenedor.querySelector('#ventaObservacion');
            if (obsInput) {
                obsInput.value = info.observacion || '';
            }
            try {
                await cargarPromocionesMesa();
                const panelPromos = contenedor.querySelector('#panelPromosMesa');
                if (panelPromos) {
                    if (Array.isArray(catalogoPromosMesa) && catalogoPromosMesa.length) {
                        panelPromos.style.display = '';
                        const seleccionadas = (Array.isArray(info.promociones_ids) && info.promociones_ids.length)
                            ? info.promociones_ids
                            : (info.promocion_id ? [info.promocion_id] : []);
                        preseleccionarPromosMesa(seleccionadas);
                        const btnAddPromo = contenedor.querySelector('#btnAgregarPromoMesa');
                        if (btnAddPromo) btnAddPromo.addEventListener('click', () => agregarSelectPromoMesa());
                        const btnGuardarPromo = contenedor.querySelector('#btnGuardarPromosMesa');
                        if (btnGuardarPromo) btnGuardarPromo.addEventListener('click', async () => {
                            await guardarPromocionesMesa(ventaId);
                        });
                    } else {
                        panelPromos.style.display = 'none';
                    }
                }
            } catch (e) {
                console.error('No se pudieron inicializar promociones', e);
            }
            contenedor.querySelectorAll('.eliminar').forEach(btn => {
                btn.addEventListener('click', () => eliminarDetalle(btn.dataset.id, ventaId));
            });
            contenedor.querySelectorAll('.entregar').forEach(btn => {
                btn.addEventListener('click', () => marcarEntregado(btn.dataset.id, ventaId));
            });
            contenedor.querySelectorAll('.imprimir-detalle').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const ok = await guardarObservacionVenta(ventaId, true);
                    if (ok) imprimirComandaDetalle(ventaId, btn.dataset.id);
                });
            });
            inicializarBuscadorDetalle();
            modal.querySelector('#btnAgregarDetalle').addEventListener('click', agregarDetalle);
            const btnImp = contenedor.querySelector('#imprimirTicket');
            if (btnImp) {
                btnImp.addEventListener('click', async () => {
                    const okObs = await guardarObservacionVenta(ventaId, true);
                    if (!okObs) return;
                    const okPromos = await guardarPromocionesMesa(ventaId, true);
                    if (!okPromos) return;
                    await imprimirTicketMesa(ventaId);
                });
            }
            const btnComanda = contenedor.querySelector('#imprimirComanda');
            if (btnComanda) {
                btnComanda.addEventListener('click', async () => {
                    const ok = await guardarObservacionVenta(ventaId, true);
                    if (ok) imprimirComanda(ventaId);
                });
            }
            showModal('#modalVenta');
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
    }
}

function crearTablaProductos(productos) {
    let html = '<table class="styled-table" border="1"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th><th>Estatus</th><th>Hora</th><th></th><th></th></tr></thead><tbody>';
    productos.forEach(p => {
        const est = textoEstado(p.estado_producto);
        const btnEliminar = (p.estado_producto !== 'entregado' && p.estado_producto !== 'en_preparacion') ? `<button class="eliminar" data-id="${p.id}">Eliminar</button>` : '';
        const puedeEntregar = p.estado_producto === 'listo';
        const btnEntregar = p.estado_producto !== 'entregado' ? `<button class="entregar" data-id="${p.id}" ${puedeEntregar ? '' : 'disabled'}>Entregar</button>` : '';
        const btnImprimir = `<button class="imprimir-detalle" data-id="${p.id}" style="margin-left:6px;">Imprimir</button>`;
        const hora = p.entregado_hr ? p.entregado_hr : '';
        html += `<tr><td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td>${p.subtotal}</td><td>${est}</td><td>${hora}</td><td>${btnEliminar}</td><td>${btnEntregar} ${btnImprimir}</td></tr>`;
    });
    html += `<tr id="detalle_nuevo">
        <td>
            <div class="selector-producto position-relative">
                <input type="text" id="detalle_buscador" class="form-control" placeholder="Buscar producto...">
                <select id="detalle_producto" class="d-none"></select>
                <ul id="detalle_lista" class="list-group position-absolute w-100 lista-productos"></ul>
            </div>
        </td>
        <td><input type="number" id="detalle_cantidad" class="form-control" min="1" step="1" value="1"></td>
        <td colspan="4"></td>
        <td colspan="2"><button class="btn custom-btn" id="btnAgregarDetalle">Agregar</button></td>
    </tr>`;
    html += '</tbody></table>';
    return html;
}

async function dividirMesa(id) {
    try {
        const resp = await fetch('../../api/mesas/dividir_mesa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mesa_id: parseInt(id) })
        });
        const data = await resp.json();
        if (data.success) {
            await cargarMesas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al dividir mesa');
    }
}

async function unirSeleccionadas() {
    const seleccionadas = Array.from(document.querySelectorAll('.seleccionar:checked')).map(c => parseInt(c.dataset.id));
    if (seleccionadas.length < 2) {
        alert('Selecciona al menos dos mesas');
        return;
    }
    const principal = parseInt(prompt('ID de mesa principal', seleccionadas[0]));
    const otras = seleccionadas.filter(id => id !== principal);
    if (otras.length === 0) {
        alert('Debes seleccionar mesas adicionales aparte de la principal');
        return;
    }
    try {
        const resp = await fetch('../../api/mesas/unir_mesas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ principal_id: principal, mesas: otras })
        });
        const data = await resp.json();
        if (data.success) {
            await cargarMesas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al unir mesas');
    }
}

document.getElementById('btn-unir').addEventListener('click', unirSeleccionadas);

async function reservarMesa(id) {
    const nombre = prompt('Nombre para la reserva:');
    if (!nombre) return;
    const fecha = prompt('Fecha y hora (YYYY-MM-DD HH:MM):');
    if (!fecha) return;
    try {
        const resp = await fetch('../../api/mesas/cambiar_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mesa_id: parseInt(id),
                nuevo_estado: 'reservada',
                nombre_reserva: nombre,
                fecha_reserva: fecha
            })
        });
        const data = await resp.json();
        if (data.success) {
            cargarMesas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al reservar mesa');
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const modal = document.getElementById('modalVenta');
    modal.addEventListener('modal:hidden', () => {
        const body = modal.querySelector('.modal-body');
        if (body) body.innerHTML = '';
        productosVentaMesaActual = [];
        tipoEntregaMesaActual = '';
        totalPromoMesaActual = 0;
        if (huboCambios) {
            cargarMesas();
            huboCambios = false;
        }
    });
    try {
        await cargarCatalogo();
        await verificarCorteAbiertoGlobal();
        await cargarMesas();
        iniciarLongPollCorte(4000);
    } catch (err) {
        console.error(err);
    }
});
