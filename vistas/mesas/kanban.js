// Datos de ejemplo para meseros y mesas
const meseros = [
  { id: 1, nombre: "Carlos Mesero" },
  { id: 2, nombre: "Ana Repartidora" }
];

const mesas = [
  { id: 101, nombre: "Mesa 1", estado: "ocupada", usuario_id: 1 },
  { id: 102, nombre: "Mesa 2", estado: "libre", usuario_id: 2 }
];

function crearColumnas() {
  const lista = document.getElementById('kanban-list');
  lista.innerHTML = '';

  meseros.forEach(mesero => {
    const li = document.createElement('li');
    li.className = 'drag-column drag-column-on-hold';

    const header = document.createElement('span');
    header.className = 'drag-column-header';
    header.innerHTML = `
      <h2>${mesero.nombre}</h2>
      <svg class="drag-header-more" data-target="options_${mesero.id}" fill="#FFFFFF" height="24" viewBox="0 0 24 24" width="24">
        <path d="M0 0h24v24H0z" fill="none" />
        <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
      </svg>`;
    li.appendChild(header);

    const options = document.createElement('div');
    options.className = 'drag-options';
    options.id = `options_${mesero.id}`;
    li.appendChild(options);

    const ul = document.createElement('ul');
    ul.className = 'drag-inner-list';
    ul.id = String(mesero.id);
    li.appendChild(ul);

    lista.appendChild(li);
  });
}

function agregarMesas() {
  mesas.forEach(mesa => {
    const card = document.createElement('li');
    card.className = 'drag-item';
    card.setAttribute('draggable', 'true');
    card.innerHTML = `
      <div class="task">
        <h3>${mesa.nombre}</h3>
        <p>Estado: ${mesa.estado}</p>
        <button>Detalles</button>
        <button>Dividir</button>
        <button>Cambiar Estado</button>
      </div>`;
    const contenedor = document.getElementById(mesa.usuario_id);
    if (contenedor) {
      contenedor.appendChild(card);
    }
  });
}

function activarDrag() {
  const lists = Array.from(document.querySelectorAll('.drag-inner-list'));
  dragula(lists);
}

document.addEventListener('DOMContentLoaded', () => {
  crearColumnas();
  agregarMesas();
  activarDrag();
});
