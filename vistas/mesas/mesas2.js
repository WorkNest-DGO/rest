async function cargarTablero() {
    const resp = await fetch('../../api/mesas/listar_mesas.php');
    const data = await resp.json();
    if (!data.success) {
        alert('Error al obtener mesas');
        return;
    }
    const grupos = {};
    const uniones = {};
    data.resultado.forEach(m => {
        const nom = m.mesero_nombre || 'Sin mesero';
        if (!grupos[nom]) grupos[nom] = [];
        grupos[nom].push(m);
        if (m.mesa_principal_id) {
            if (!uniones[m.mesa_principal_id]) uniones[m.mesa_principal_id] = [];
            uniones[m.mesa_principal_id].push(m.id);
        }
    });
    const cont = document.getElementById('tablero-meseros');
    cont.innerHTML = '';
    Object.entries(grupos).forEach(([mesero, mesas]) => {
        const col = document.createElement('div');
        col.className = 'project-column';
        const head = document.createElement('div');
        head.className = 'project-column-heading';
        head.innerHTML = `<h2 class='project-column-heading__title'>${mesero}</h2>`;
        col.appendChild(head);
        mesas.forEach(m => {
            const card = document.createElement('div');
            card.className = 'task';

            const unidas = uniones[m.id] || [];
            let unionTxt = '';
            if (m.mesa_principal_id) {
                unionTxt = `Unida a ${m.mesa_principal_id}`;
            } else if (unidas.length) {
                unionTxt = `Principal de: ${unidas.join(', ')}`;
            }

            const ventaTxt = m.venta_activa ? `Venta activa: ${m.venta_id}` : 'Sin venta';
            const meseroTxt = m.mesero_nombre ? `Mesero: ${m.mesero_nombre}` : 'Sin mesero asignado';
            const reservaTxt = m.estado_reserva === 'reservada' ? `Reservada: ${m.nombre_reserva} (${m.fecha_reserva})` : '';

            let ocupacionTxt = '';
            if (m.tiempo_ocupacion_inicio) {
                const inicio = new Date(m.tiempo_ocupacion_inicio.replace(' ', 'T'));
                const diff = Math.floor((Date.now() - inicio.getTime()) / 60000);
                ocupacionTxt = `Ocupada hace ${diff} min`;
            }

            card.innerHTML = `
                <header class="task__tags">
                    <h3>${m.nombre}</h3>
                    <input type="checkbox" class="seleccionar" data-id="${m.id}">
                </header>
                <div class="task__body">
                    <p>Estado: ${m.estado}</p>
                    <p>${ventaTxt}</p>
                    <p>${meseroTxt}</p>
                    <p>${unionTxt}</p>
                    <p>${reservaTxt}</p>
                    <p>${ocupacionTxt}</p>
                </div>
                <footer class="task__stats">
                    <button class="detalles" data-venta="${m.venta_id || ''}" data-mesa="${m.id}" data-nombre="${m.nombre}" data-estado="${m.estado}" data-mesero="${m.mesero_id || ''}">Detalles</button>
                    <button class="dividir" data-id="${m.id}">Dividir</button>
                    <button class="cambiar" data-id="${m.id}">Cambiar estado</button>
                    <button class="ticket" data-mesa="${m.id}" data-nombre="${m.nombre}" data-venta="${m.venta_id || ''}">Enviar ticket</button>
                </footer>
            `;

            col.appendChild(card);
        });
        cont.appendChild(col);
    });
}

document.addEventListener('DOMContentLoaded', cargarTablero);
