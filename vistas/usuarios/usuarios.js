function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}
window.alert = showAppMsg;

let usuarios = [];

async function cargarUsuarios() {
    try {
        const resp = await fetch('../../api/usuarios/listar_usuarios.php');
        const data = await resp.json();
        const tbody = document.querySelector('#tablaUsuarios tbody');
        tbody.innerHTML = '';
        if (data.success) {
            usuarios = data.usuarios || [];
            usuarios.forEach(u => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${u.id}</td><td>${u.nombre}</td><td>${u.usuario}</td><td>${u.rol}</td><td>${u.activo == 1 ? 'Sí' : 'No'}</td>`;
                const tdAcc = document.createElement('td');
                const btnE = document.createElement('button');
                btnE.className = 'btn custom-btn me-2';
                btnE.textContent = 'Editar';
                btnE.onclick = () => editarUsuario(u.id);
                const btnD = document.createElement('button');
                btnD.className = 'btn custom-btn';
                btnD.textContent = 'Eliminar';
                btnD.onclick = () => eliminarUsuario(u.id);
                tdAcc.appendChild(btnE);
                tdAcc.appendChild(btnD);
                tr.appendChild(tdAcc);
                tbody.appendChild(tr);
            });
        } else {
            alert(data.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar usuarios');
    }
}

document.getElementById('btnAgregar').onclick = () => {
    document.getElementById('formUsuario').reset();
    document.getElementById('usuarioId').value = '';
    showModal('#modalUsuario');
};

document.getElementById('formUsuario').onsubmit = async ev => {
    ev.preventDefault();
    const id = document.getElementById('usuarioId').value;
    const payload = {
        nombre: document.getElementById('nombre').value,
        usuario: document.getElementById('usuario').value,
        contrasena: document.getElementById('contrasena').value,
        rol: document.getElementById('rol').value,
        activo: parseInt(document.getElementById('activo').value)
    };
    let url;
    if (id) {
        payload.id = parseInt(id);
        url = '../../api/usuarios/editar_usuario.php';
    } else {
        url = '../../api/usuarios/agregar_usuario.php';
    }
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        alert(data.mensaje);
        if (data.success) {
            hideModal('#modalUsuario');
            cargarUsuarios();
        }
    } catch (err) {
        console.error(err);
        alert('Error al guardar usuario');
    }
};

function editarUsuario(id) {
    const u = usuarios.find(x => x.id == id);
    if (!u) return;
    document.getElementById('usuarioId').value = u.id;
    document.getElementById('nombre').value = u.nombre;
    document.getElementById('usuario').value = u.usuario;
    document.getElementById('contrasena').value = '';
    document.getElementById('rol').value = u.rol;
    document.getElementById('activo').value = u.activo;
    showModal('#modalUsuario');
}

async function eliminarUsuario(id) {
    if (!confirm('¿Eliminar usuario?')) return;
    try {
        const resp = await fetch('../../api/usuarios/eliminar_usuario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await resp.json();
        alert(data.mensaje);
        if (data.success) cargarUsuarios();
    } catch (err) {
        console.error(err);
        alert('Error al eliminar usuario');
    }
}

window.addEventListener('DOMContentLoaded', cargarUsuarios);
