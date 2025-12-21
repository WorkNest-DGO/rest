function showAppMsg(msg) {
    const body = document.querySelector('#appMsgModal .modal-body');
    if (body) body.textContent = String(msg);
    showModal('#appMsgModal');
}

window.alert = showAppMsg;

let sedeActualId = null;
const formUsuario = document.getElementById('formUsuario');
const IS_ADMIN = formUsuario?.dataset?.admin === '1';

async function cargarSedes() {
    const select = document.getElementById('sede');
    if (!select) return;
    try {
        const resp = await fetch('../../api/dashboard/sedes/listar_sedes.php', { cache: 'no-store' });
        const data = await resp.json();
        if (!data.success) {
            alert(data.mensaje || 'No se pudieron cargar las sedes');
            return;
        }
        select.innerHTML = '<option value="">Seleccione</option>';
        (data.resultado || []).forEach(s => {
            const opt = document.createElement('option');
            opt.value = String(s.id);
            opt.textContent = s.nombre;
            select.appendChild(opt);
        });
        if (sedeActualId !== null && sedeActualId !== undefined) {
            select.value = String(sedeActualId);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar sedes');
    }
}

async function cargarUsuario() {
    try {
        const resp = await fetch('../../api/dashboard/usuarios/obtener_usuario.php', { cache: 'no-store' });
        const data = await resp.json();
        if (!data.success) {
            alert(data.mensaje || 'No se pudo cargar el usuario');
            return;
        }
        const u = data.resultado || {};
        const nombre = document.getElementById('nombre');
        const usuario = document.getElementById('usuario');
        const rol = document.getElementById('rol');
        if (nombre) nombre.value = u.nombre || '';
        if (usuario) usuario.value = u.usuario || '';
        if (rol) rol.value = u.rol || '';
        sedeActualId = (u.sede_id !== null && u.sede_id !== undefined) ? Number(u.sede_id) : null;
        const sedeSelect = document.getElementById('sede');
        if (sedeSelect && sedeActualId !== null) {
            sedeSelect.value = String(sedeActualId);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar el usuario');
    }
}

async function guardarUsuario(e) {
    e.preventDefault();
    const nombre = document.getElementById('nombre')?.value?.trim() || '';
    const pass1 = document.getElementById('contrasena')?.value || '';
    const pass2 = document.getElementById('contrasena2')?.value || '';
    const sedeSel = document.getElementById('sede');
    const sedeId = sedeSel ? parseInt(sedeSel.value || '0', 10) : 0;

    if (!nombre) {
        alert('Nombre requerido');
        return;
    }
    if (IS_ADMIN && (!sedeId || Number.isNaN(sedeId))) {
        alert('Selecciona una sede');
        return;
    }
    if (pass1 || pass2) {
        if (pass1 !== pass2) {
            alert('Las contrasenas no coinciden');
            return;
        }
    }

    const payload = { nombre };
    if (IS_ADMIN) {
        payload.sede_id = sedeId;
    }
    if (pass1) payload.contrasena = pass1;

    try {
        const resp = await fetch('../../api/dashboard/usuarios/actualizar_usuario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            alert(data.resultado?.mensaje || data.mensaje || 'Usuario actualizado');
            document.getElementById('contrasena').value = '';
            document.getElementById('contrasena2').value = '';
            cargarUsuario();
        } else {
            alert(data.mensaje || 'No se pudo actualizar');
        }
    } catch (err) {
        console.error(err);
        alert('Error al actualizar');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    cargarUsuario();
    cargarSedes();
    if (formUsuario) formUsuario.addEventListener('submit', guardarUsuario);
});
