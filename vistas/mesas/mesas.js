async function cargarMesas() {
    try {
        const resp = await fetch('../../api/mesas/listar_mesas.php');
        const data = await resp.json();
        const tablero = document.getElementById('tablero');
        if (data.success) {
            tablero.innerHTML = '';
            data.resultado.forEach(m => {
                const card = document.createElement('div');
                card.className = 'mesa';
                card.innerHTML = `
                    <h3>${m.nombre}</h3>
                    <p>Estado: ${m.estado}</p>
                    <button data-id="${m.id}">Cambiar estado</button>
                `;
                tablero.appendChild(card);
            });
            tablero.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => mostrarMenu(btn.dataset.id));
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
