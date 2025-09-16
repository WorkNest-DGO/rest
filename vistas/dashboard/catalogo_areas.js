async function cargarAreas() {
    try {
        console.log('Cargando áreas...');
        const response = await fetch('../../api/dashboard/catalogo_areas/listar_catalogo_areas.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Datos recibidos:', data);
        
        if (data.success) {
            const tbody = document.querySelector('#tablaAreas tbody');
            if (!tbody) {
                throw new Error('No se encontró la tabla de áreas');
            }
            
            tbody.innerHTML = '';
            
            if (data.areas && Array.isArray(data.areas)) {
                data.areas.forEach(area => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${area.id}</td>
                            <td>${area.nombre}</td>
                            <td>
                                <button class="btn custom-btn btn-sm editar" 
                                        data-id="${area.id}" 
                                        data-nombre="${area.nombre}">
                                    Editar
                                </button>
                                <button class="btn custom-btn btn-sm ms-2 eliminar" 
                                        data-id="${area.id}">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                document.querySelectorAll('.editar').forEach(btn => {
                    btn.addEventListener('click', () => abrirModalEditarArea(btn.dataset.id, btn.dataset.nombre));
                });
                
                document.querySelectorAll('.eliminar').forEach(btn => {
                    btn.addEventListener('click', () => eliminarArea(btn.dataset.id));
                });
            }
        } else {
            throw new Error(data.mensaje || 'Error al cargar las áreas');
        }
    } catch (error) {
        console.error('Error en cargarAreas:', error);
        mostrarMsgArea('Error al cargar las áreas. Por favor, intente nuevamente.');
    }
}

function mostrarMsgArea(msg) {
    console.log('Mostrando mensaje:', msg);
    const modalBody = document.querySelector('#modalMsgArea .modal-body');
    if (modalBody) {
        modalBody.textContent = msg;
        showModal('#modalMsgArea');
    } else {
        alert(msg);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM cargado, iniciando aplicación...');
    
    cargarAreas();
    
    const btnAgregar = document.getElementById('agregarArea');
    if (btnAgregar) {
        btnAgregar.addEventListener('click', abrirModalAgregarArea);
    }

    const formAgregar = document.getElementById('formAgregarArea');
    if (formAgregar) {
        formAgregar.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const nombre = document.getElementById('nombreArea').value.trim();
            if (!nombre) {
                mostrarMsgArea('El nombre del área es requerido');
                return;
            }

            try {
                console.log('Enviando datos:', { nombre });
                const resp = await fetch('../../api/dashboard/catalogo_areas/crear_catalogo_areas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ nombre })
                });

                if (!resp.ok) {
                    throw new Error(`HTTP error! status: ${resp.status}`);
                }

                const data = await resp.json();
                console.log('Respuesta:', data);

                if (data.success) {
                    mostrarMsgArea('Área agregada correctamente');
                    cerrarModalAgregarArea();
                    await cargarAreas();
                } else {
                    mostrarMsgArea(data.mensaje || 'Error al agregar el área');
                }
            } catch (err) {
                console.error('Error al agregar área:', err);
                mostrarMsgArea('Error al agregar el área. Por favor, intente nuevamente.');
            }
        });
    }

    document.getElementById('formEditarArea').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('editarAreaId').value;
        const nombre = document.getElementById('editarNombreArea').value.trim();
        try {
            const resp = await fetch('../../api/dashboard/catalogo_areas/actualizar_catalogo_areas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id), nombre })
            });
            const data = await resp.json();
            if (data.success) {
                mostrarMsgArea('Área actualizada correctamente');
                cerrarModalEditarArea();
                cargarAreas();
            } else {
                mostrarMsgArea(data.mensaje);
            }
        } catch (err) {
            mostrarMsgArea('Error al actualizar área');
            console.error(err);
        }
    });
});

function showModal(selector) {
    const modal = $(selector);
    if (modal.length) {
        modal.modal('show');
    } else {
        console.error('Modal no encontrado:', selector);
    }
}

function hideModal(selector) {
    const modal = $(selector);
    if (modal.length) {
        modal.modal('hide');
    }
}

function abrirModalAgregarArea() {
    document.getElementById('nombreArea').value = '';
    showModal('#modalAgregarArea');
}

function cerrarModalAgregarArea() {
    hideModal('#modalAgregarArea');
    document.getElementById('formAgregarArea').reset();
}

function abrirModalEditarArea(id, nombre) {
    document.getElementById('editarAreaId').value = id;
    document.getElementById('editarNombreArea').value = nombre;
    showModal('#modalEditarArea');
}

function cerrarModalEditarArea() {
    hideModal('#modalEditarArea');
    document.getElementById('formEditarArea').reset();
}

async function eliminarArea(id) {
    if (!confirm('¿Está seguro de eliminar esta área?')) return;
    
    try {
        const resp = await fetch('../../api/dashboard/catalogo_areas/eliminar_catalogo_areas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
        });
        const data = await resp.json();
        if (data.success) {
            mostrarMsgArea('Área eliminada correctamente');
            cargarAreas();
        } else {
            mostrarMsgArea(data.mensaje);
        }
    } catch (err) {
        mostrarMsgArea('Error al eliminar área');
        console.error(err);
    }
}