async function cargarMesas() {
    try {
        const resp = await fetch('../../api/mesas/listar_mesas.php');
        const data = await resp.json();
        const tablero = document.getElementById('tablero');
        if (data.success) {
            tablero.innerHTML = '';
            // Calcular mesas unidas
            const uniones = {};
            data.resultado.forEach(m => {
                if (m.mesa_principal_id) {
                    if (!uniones[m.mesa_principal_id]) uniones[m.mesa_principal_id] = [];
                    uniones[m.mesa_principal_id].push(m.id);
                }
            });

            data.resultado.forEach(m => {
                const card = document.createElement('div');
                card.className = 'mesa';

                const unidas = uniones[m.id] || [];
                let unionTxt = '';
                if (m.mesa_principal_id) {
                    unionTxt = `Unida a ${m.mesa_principal_id}`;
                } else if (unidas.length) {
                    unionTxt = `Principal de: ${unidas.join(', ')}`;
                }

                const ventaTxt = m.venta_activa ? `Venta activa: ${m.venta_id}` : 'Sin venta';

                card.innerHTML = `
                    <input type="checkbox" class="seleccionar" data-id="${m.id}">
                    <h3>${m.nombre}</h3>
                    <p>Estado: ${m.estado}</p>
                    <p>${ventaTxt}</p>
                    <p>${unionTxt}</p>
                    <button class="detalles" data-venta="${m.venta_id}" style="display:${m.venta_activa ? 'inline' : 'none'}">Detalles</button>
                    <button class="dividir" data-id="${m.id}">Dividir</button>
                    <button class="cambiar" data-id="${m.id}">Cambiar estado</button>
                `;
                tablero.appendChild(card);
            });

            tablero.querySelectorAll('button.cambiar').forEach(btn => {
                btn.addEventListener('click', () => mostrarMenu(btn.dataset.id));
            });
            tablero.querySelectorAll('button.dividir').forEach(btn => {
                btn.addEventListener('click', () => dividirMesa(btn.dataset.id));
            });
            tablero.querySelectorAll('button.detalles').forEach(btn => {
                btn.addEventListener('click', () => verVenta(btn.dataset.venta));
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar mesas');
    }
}

function mostrarMenu(id) {
    const nuevo = prompt('Nuevo estado (libre, ocupada, reservada):');
    if (nuevo) {
        cambiarEstado(id, nuevo);
    }
}

async function cambiarEstado(id, estado) {
    try {
        const resp = await fetch('../../api/mesas/cambiar_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mesa_id: parseInt(id), nuevo_estado: estado })
        });
        const data = await resp.json();
        if (data.success) {
            await cargarMesas();
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cambiar estado');
    }
}

document.addEventListener('DOMContentLoaded', cargarMesas);

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

async function verVenta(id) {
    try {
        const resp = await fetch('../../api/mesas/detalle_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: parseInt(id) })
        });
        const data = await resp.json();
        if (data.success) {
            let msg = `Mesa: ${data.resultado.mesa}\nMesero: ${data.resultado.mesero}\n`;
            data.resultado.productos.forEach(p => {
                msg += `\n${p.nombre} - ${p.cantidad} x ${p.precio_unitario} (${p.estatus_preparacion})`;
            });
            alert(msg);
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al obtener detalles');
    }
}

document.getElementById('btn-unir').addEventListener('click', unirSeleccionadas);
