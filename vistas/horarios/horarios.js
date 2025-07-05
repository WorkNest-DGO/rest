async function cargarSeries() {
    const resp = await fetch('../../api/tickets/listar_series.php');
    const data = await resp.json();
    const sel = document.getElementById('serie_id');
    sel.innerHTML = '';
    if (data.success) {
        data.resultado.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.descripcion;
            sel.appendChild(opt);
        });
    } else {
        alert(data.mensaje);
    }
}

async function listarHorarios() {
    const resp = await fetch('../../api/horarios/listar_horarios.php');
    const data = await resp.json();
    const tbody = document.querySelector('#tablaHorarios tbody');
    tbody.innerHTML = '';
    if (data.success) {
        data.resultado.forEach(h => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${h.dia_semana}</td><td>${h.hora_inicio}</td><td>${h.hora_fin}</td><td>${h.serie}</td>`;
            const acc = document.createElement('td');
            const e = document.createElement('button');
            e.textContent = 'Editar';
            e.onclick = () => editar(h);
            const d = document.createElement('button');
            d.textContent = 'Eliminar';
            d.onclick = () => eliminar(h.id);
            acc.appendChild(e);
            acc.appendChild(d);
            tr.appendChild(acc);
            tbody.appendChild(tr);
        });
    } else {
        alert(data.mensaje);
    }
}

function editar(h) {
    document.getElementById('horarioId').value = h.id;
    document.getElementById('dia_semana').value = h.dia_semana;
    document.getElementById('hora_inicio').value = h.hora_inicio;
    document.getElementById('hora_fin').value = h.hora_fin;
    document.getElementById('serie_id').value = h.serie_id;
    document.getElementById('cancelar').style.display = 'inline';
}

document.getElementById('cancelar').onclick = () => {
    document.getElementById('horarioId').value = '';
    document.getElementById('formHorario').reset();
    document.getElementById('cancelar').style.display = 'none';
};

document.getElementById('formHorario').onsubmit = async ev => {
    ev.preventDefault();
    const id = document.getElementById('horarioId').value;
    const payload = {
        id: id ? parseInt(id) : undefined,
        dia_semana: document.getElementById('dia_semana').value,
        hora_inicio: document.getElementById('hora_inicio').value,
        hora_fin: document.getElementById('hora_fin').value,
        serie_id: parseInt(document.getElementById('serie_id').value)
    };
    const url = id ? '../../api/horarios/actualizar_horario.php' : '../../api/horarios/crear_horario.php';
    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await resp.json();
    if (data.success) {
        document.getElementById('formHorario').reset();
        document.getElementById('horarioId').value = '';
        document.getElementById('cancelar').style.display = 'none';
        listarHorarios();
    } else {
        alert(data.mensaje);
    }
};

async function eliminar(id) {
    if (!confirm('Eliminar horario?')) return;
    const resp = await fetch('../../api/horarios/eliminar_horario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    const data = await resp.json();
    if (data.success) {
        listarHorarios();
    } else {
        alert(data.mensaje);
    }
}

window.addEventListener('DOMContentLoaded', () => {
    cargarSeries();
    listarHorarios();
});
