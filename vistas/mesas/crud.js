function showAppMsg(msg) {
  const modal = document.getElementById('appMsgModal');
  if (!modal) { alert(msg); return; }
  const body = modal.querySelector('.modal-body');
  if (body) body.textContent = String(msg);
  if (typeof showModal === 'function') showModal('#appMsgModal');
}

async function cargarMesasCrud() {
  try {
    const base = window.API_LISTAR_MESAS || '../../api/mesas/listar_mesas.php';
    // Resolver relativo desde la ubicación actual para conservar prefijo (/rest)
    const u = base.includes('http') ? new URL(base) : new URL(base, window.location.href);
    if (window.usuarioActual && window.usuarioActual.id) {
      u.searchParams.set('user_id', window.usuarioActual.id);
      u.searchParams.set('usuario_id', window.usuarioActual.id);
    }
    const resp = await fetch(u.toString());
    const data = await resp.json();
    if (!data.success) { showAppMsg(data.mensaje || 'Error al listar'); return; }
    const tbody = document.querySelector('#tablaMesas tbody');
    tbody.innerHTML = '';
    (data.resultado || []).forEach(m => {
      const tr = document.createElement('tr');
      const usuarioTexto = (m.usuario_id != null)
        ? `${m.usuario_id}: ${m.mesero_nombre || m.mesero_usuario || ''}`
        : '';
      const areaTexto = (m.area_id != null)
        ? `${m.area_id}: ${m.area || ''}`
        : (m.area || '');
      const alineacionTexto = (m.alineacion_id != null)
        ? `${m.alineacion_id}: ${m.alineacion_nombre || ''}`
        : '';
      const mesaPrincipalTexto = (m.mesa_principal_id != null)
        ? `${m.mesa_principal_id}: ${m.mesa_principal_nombre || ''}`
        : '';
      tr.innerHTML = `
        <td>${m.nombre || ''}</td>
        <td>${m.estado || ''}</td>
        <td>${m.capacidad ?? ''}</td>
        <td>${mesaPrincipalTexto}</td>
        <td>${areaTexto}</td>
        <td>${usuarioTexto}</td>
        <td>${areaTexto}</td>
        <td>${alineacionTexto}</td>
        <td>
          <button class="btn custom-btn" data-action="edit" data-id="${m.id}">Editar</button>
          <button class="btn custom-btn" data-action="delete" data-id="${m.id}" style="margin-left:6px;">Eliminar</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  } catch (e) {
    console.error(e);
    showAppMsg('Error de red al listar mesas');
  }
}

function limpiarForm() {
  document.getElementById('mesa_id').value = '';
  document.getElementById('nombre').value = '';
  document.getElementById('estado').value = 'libre';
  document.getElementById('capacidad').value = '4';
  document.getElementById('mesa_principal_id').value = '';
  document.getElementById('area').value = '';
  document.getElementById('usuario_id').value = '';
  document.getElementById('area_id').value = '';
  document.getElementById('alineacion_id').value = '';
  document.getElementById('formTitle').textContent = 'Nueva mesa';
}

async function guardarMesa(e) {
  e.preventDefault();
  const payload = {
    id: (document.getElementById('mesa_id').value || '').trim() || null,
    nombre: (document.getElementById('nombre').value || '').trim(),
    estado: document.getElementById('estado').value,
    capacidad: parseInt(document.getElementById('capacidad').value || '4', 10),
    mesa_principal_id: (document.getElementById('mesa_principal_id').value || '').trim(),
    area: (document.getElementById('area').value || '').trim(),
    usuario_id: (document.getElementById('usuario_id').value || '').trim(),
    area_id: (document.getElementById('area_id').value || '').trim(),
    alineacion_id: (document.getElementById('alineacion_id').value || '').trim()
  };
  // Normalizar numéricos vacíos a null
  ['mesa_principal_id','usuario_id','area_id','alineacion_id'].forEach(k => {
    if (payload[k] === '') payload[k] = null;
    else payload[k] = parseInt(payload[k], 10);
  });
  try {
    const resp = await fetch('../../api/mesas/guardar_mesa.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await resp.json();
    if (!data.success) { showAppMsg(data.mensaje || 'No se pudo guardar'); return; }
    showAppMsg('Guardado correctamente');
    limpiarForm();
    cargarMesasCrud();
  } catch (e) {
    console.error(e);
    showAppMsg('Error de red al guardar');
  }
}

async function eliminarMesa(id) {
  if (!confirm('¿Eliminar mesa?')) return;
  try {
    const resp = await fetch('../../api/mesas/eliminar_mesa.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: parseInt(id, 10) })
    });
    const data = await resp.json();
    if (!data.success) { showAppMsg(data.mensaje || 'No se pudo eliminar'); return; }
    showAppMsg('Eliminado');
    cargarMesasCrud();
  } catch (e) {
    console.error(e);
    showAppMsg('Error de red al eliminar');
  }
}

function cargarEnFormulario(m) {
  document.getElementById('mesa_id').value = m.id;
  document.getElementById('nombre').value = m.nombre || '';
  document.getElementById('estado').value = m.estado || 'libre';
  document.getElementById('capacidad').value = m.capacidad ?? 4;
  document.getElementById('mesa_principal_id').value = m.mesa_principal_id ?? '';
  document.getElementById('area').value = m.area || '';
  document.getElementById('usuario_id').value = m.usuario_id ?? '';
  document.getElementById('area_id').value = m.area_id ?? '';
  document.getElementById('alineacion_id').value = m.alineacion_id ?? '';
  document.getElementById('formTitle').textContent = 'Editar mesa';
}

document.addEventListener('DOMContentLoaded', () => {
  cargarMesasCrud();
  document.getElementById('mesaForm').addEventListener('submit', guardarMesa);
  document.getElementById('btnCancelar').addEventListener('click', (e) => { e.preventDefault(); limpiarForm(); });
  document.querySelector('#tablaMesas tbody').addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const id = btn.dataset.id;
    if (btn.dataset.action === 'delete') {
      eliminarMesa(id);
    } else if (btn.dataset.action === 'edit') {
      // Obtener datos actuales desde la fila
      const tr = btn.closest('tr');
      const tds = tr.querySelectorAll('td');
      const mesa = {
        id: parseInt(id, 10),
        nombre: tds[0].textContent,
        estado: tds[1].textContent,
        capacidad: parseInt(tds[2].textContent || '4', 10),
        mesa_principal_id: tds[3].textContent ? parseInt(tds[3].textContent,10) : null,
        area: tds[4].textContent,
        usuario_id: tds[5].textContent ? parseInt(tds[5].textContent,10) : null,
        area_id: tds[6].textContent ? parseInt(tds[6].textContent,10) : null,
        alineacion_id: tds[7].textContent ? parseInt(tds[7].textContent,10) : null
      };
      cargarEnFormulario(mesa);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });
});
