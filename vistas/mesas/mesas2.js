async function cargarTablero() {
    const resp = await fetch('../../api/mesas/mesas.php');
    const data = await resp.json();
    if (!data.success) {
        alert('Error al obtener mesas');
        return;
    }
    const grupos = {};
    data.resultado.forEach(m => {
        const nom = m.mesero_nombre || 'Sin mesero';
        if (!grupos[nom]) grupos[nom] = [];
        grupos[nom].push(m);
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
            const div = document.createElement('div');
            div.className = 'task';
            div.textContent = m.nombre;
            col.appendChild(div);
        });
        cont.appendChild(col);
    });
}

document.addEventListener('DOMContentLoaded', cargarTablero);
