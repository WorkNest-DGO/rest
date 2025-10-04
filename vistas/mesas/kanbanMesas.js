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
let huboCambios = false;
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
        const resp = await fetch('../../api/mesas/listar_mesas.php');
        const data = await resp.json();
        if (data.success) {
            const mesas = data.resultado;
            mesasCache = mesas;
            const mapa = {};

            mesas.forEach(m => {
                const uid = parseInt(m.usuario_id);
                if (uid) {
                    if (!mapa[uid]) {
                        mapa[uid] = { id: uid, nombre: m.mesero_nombre };
                    }
                }
            });

            const meserosUnicos = Object.values(mapa).sort((a, b) => a.nombre.localeCompare(b.nombre));
            meseros = meserosUnicos;
            renderKanban(meserosUnicos, mesas);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar mesas');
    }
}


function crearColumnas(container, listaMeseros) {
    if (!container) return;
    if (!Array.isArray(listaMeseros)) return;

    listaMeseros.forEach(me => {
        const li = document.createElement('li');
        li.className = 'drag-column drag-column-on-hold';
        li.classList.add('kanban-board');
        li.dataset.meseroId = me.id;

        const header = document.createElement('span');
        header.className = 'drag-column-header';
        header.innerHTML = `<h3>${me.nombre}</h3>`;
        li.appendChild(header);

        const ul = document.createElement('ul');
        ul.className = 'drag-inner-list kanban-dropzone';
        li.appendChild(ul);

        container.appendChild(li);
    });

    const liSin = document.createElement('li');
    liSin.className = 'drag-column drag-column-on-hold';
    liSin.classList.add('kanban-board');
    liSin.dataset.meseroId = 'sin';
    const headerSin = document.createElement('span');
    headerSin.className = 'drag-column-header';
    headerSin.innerHTML = '<h3>No asignado</h3>';
    liSin.appendChild(headerSin);
    const ulSin = document.createElement('ul');
    ulSin.className = 'drag-inner-list kanban-dropzone';
    liSin.appendChild(ulSin);
    container.appendChild(liSin);
}

/** Crea las columnas por mesero y agrega las tarjetas de mesas */
function renderKanban(listaMeseros, mesas) {
    const cont = document.getElementById('kanban-list');
    if (!cont) {
        console.error('Contenedor #kanban-list no encontrado');
        return;
    }
    cont.innerHTML = '';
    crearColumnas(cont, listaMeseros);

    const uniones = {};
    mesas.forEach(m => {
        if (m.mesa_principal_id) {
            if (!uniones[m.mesa_principal_id]) uniones[m.mesa_principal_id] = [];
            uniones[m.mesa_principal_id].push(m.id);
        }
    });

    const mapaMeseros = listaMeseros.reduce((acc, me) => {
        acc[String(me.id)] = me.nombre;
        return acc;
    }, {});

    // Asignar mesas a columnas

    mesas.forEach(m => {
        let col = cont.querySelector(`li[data-mesero-id="${m.usuario_id}"] .drag-inner-list`);
        if (!col) {
            col = cont.querySelector('li[data-mesero-id="sin"] .drag-inner-list');
        }
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
        const meseroNombre = mapaMeseros[String(m.usuario_id)] || null;
        const meseroTxt = meseroNombre ? `Mesero: ${meseroNombre}` : 'Sin mesero asignado';
        const reservaTxt = m.estado_reserva === 'reservada' ? `Reservada: ${m.nombre_reserva} (${m.fecha_reserva})` : '';
        let ocupacionTxt = '';
        if (m.tiempo_ocupacion_inicio) {
            const inicio = new Date(m.tiempo_ocupacion_inicio.replace(' ', 'T'));
            const diff = Math.floor((Date.now() - inicio.getTime()) / 60000);
            ocupacionTxt = `Ocupada hace ${diff} min`;
        }

        const detallesBtn = (m.estado === 'ocupada') ? '<button class="detalles">Detalles</button>' : '';
        const botoneraHTML = `
            ${detallesBtn}
            <button class="dividir" data-id="${m.id}" hidden>Dividir</button>
            <button class="cambiar" data-id="${m.id}">Cambiar estado</button>
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
            // Con corte abierto: comportamiento normal con permisos
            if (puedeEditar) {
                if (m.estado === 'libre' && m.estado_reserva === 'ninguna') {
                    card.addEventListener('click', () => reservarMesa(m.id));
                }
                btnCambiar.addEventListener('click', ev => { ev.stopPropagation(); abrirCambioEstado(m); });
                btnDividir.addEventListener('click', ev => { ev.stopPropagation(); dividirMesa(btnDividir.dataset.id); });
            } else {
                // Permitir cambiar estado con validación de contraseña del mesero asignado
                btnCambiar.addEventListener('click', ev => { ev.stopPropagation(); abrirCambioEstado(m); });
                // Mantener restringido dividir
                btnDividir.disabled = true;
            }
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

    activarDrag();
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
            const header = `<p>Fecha inicio: ${info.fecha || ''}<br>Mesero: ${info.mesero || ''}<br>Destino: ${info.mesa || ''}</p>`;
            let html = header + crearTablaProductos(info.productos);
            html += `<button class="btn custom-btn" id="imprimirTicket">Imprimir ticket</button>`;
            contenedor.innerHTML = html;
            contenedor.querySelectorAll('.eliminar').forEach(btn => {
                btn.addEventListener('click', () => eliminarDetalle(btn.dataset.id, ventaId));
            });
            contenedor.querySelectorAll('.entregar').forEach(btn => {
                btn.addEventListener('click', () => marcarEntregado(btn.dataset.id, ventaId));
            });
            inicializarBuscadorDetalle();
            modal.querySelector('#btnAgregarDetalle').addEventListener('click', agregarDetalle);
            const btnImp = contenedor.querySelector('#imprimirTicket');
            if (btnImp) {
                btnImp.addEventListener('click', () => {
                    try {
                        const total = Array.isArray(info.productos)
                            ? info.productos.reduce((s, p) => s + parseFloat(p.subtotal || 0), 0)
                            : 0;
                        const payload = {
                            venta_id: parseInt(ventaId),
                            productos: info.productos || [],
                            total
                        };
                        localStorage.setItem('ticketData', JSON.stringify(payload));
                    } catch (_) { /* noop */ }
                    window.location.href = `../ventas/ticket.php?venta=${encodeURIComponent(ventaId)}`;
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
    let html = '<table class="styled-table" border="1"><thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th>Estatus</th><th>Hora</th><th></th><th></th></tr></thead><tbody>';
    productos.forEach(p => {
        const est = textoEstado(p.estado_producto);
        const btnEliminar = (p.estado_producto !== 'entregado' && p.estado_producto !== 'en_preparacion') ? `<button class="eliminar" data-id="${p.id}">Eliminar</button>` : '';
        const puedeEntregar = p.estado_producto === 'listo';
        const btnEntregar = p.estado_producto !== 'entregado' ? `<button class="entregar" data-id="${p.id}" ${puedeEntregar ? '' : 'disabled'}>Entregar</button>` : '';
        const hora = p.entregado_hr ? p.entregado_hr : '';
        html += `<tr><td>${p.nombre}</td><td>${p.cantidad}</td><td>${p.precio_unitario}</td><td>${p.subtotal}</td><td>${est}</td><td>${hora}</td><td>${btnEliminar}</td><td>${btnEntregar}</td></tr>`;
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
