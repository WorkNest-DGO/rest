const catalogo = json_encode($insumos);
let filtrado = catalogo;
let items = 15;
let pagina = 1;
let seleccionados = JSON.parse(localStorage.getItem('qr_actual') || '{}');

function renderTabla(){
    const tbody = document.getElementById('tablaInsumos');
    tbody.innerHTML = '';
    const inicio = (pagina-1)*items;
    const fin = inicio + items;
    filtrado.slice(inicio,fin).forEach(i => {
        const tr = document.createElement('tr');
        const val = seleccionados[i.id] || '';
        tr.innerHTML = `<td>${i.nombre}</td><td>${i.existencia}</td><td>${i.unidad}</td><td><input type="number" step="0.01" min="0" data-id="${i.id}" class="form-control" value="${val}"></td>`;
        tbody.appendChild(tr);
    });
    tbody.querySelectorAll('input').forEach(inp=>{
        inp.addEventListener('input', onInputChange);
    });
}

function onInputChange(e){
    const id = e.target.dataset.id;
    const val = parseFloat(e.target.value);
    if(!isNaN(val) && val > 0){
        seleccionados[id] = val;
    } else {
        delete seleccionados[id];
    }
    actualizarResumen();
}

function actualizarResumen(){
    const body = document.querySelector('#tablaResumen tbody');
    body.innerHTML='';
    Object.entries(seleccionados).forEach(([id,val])=>{
        const ins = catalogo.find(x=>x.id == id);
        if(!ins || !ins.nombre || !ins.unidad || val <= 0) return;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${ins.nombre}</td><td>${val}</td><td>${ins.unidad}</td>`;
        body.appendChild(tr);
    });
    localStorage.setItem('qr_actual', JSON.stringify(seleccionados));
}

function filtrar(){
    const t = document.getElementById('buscarInsumo').value.toLowerCase();
    filtrado = catalogo.filter(i=>i.nombre.toLowerCase().includes(t));
    pagina=1;
    renderTabla();
    actualizarResumen();
}

document.getElementById('buscarInsumo').addEventListener('keyup',filtrar);
document.getElementById('itemsPagina').addEventListener('change', e=>{
    items = parseInt(e.target.value);
    pagina=1;
    renderTabla();
    actualizarResumen();
});
document.getElementById('prevPag').addEventListener('click',()=>{
    if(pagina>1){pagina--;renderTabla();actualizarResumen();}
});
document.getElementById('nextPag').addEventListener('click',()=>{
    const total = Math.ceil(filtrado.length/items); if(pagina<total){pagina++;renderTabla();actualizarResumen();}
});

renderTabla();
actualizarResumen();

document.getElementById('btnGenerar').addEventListener('click', async function(e){
    e.preventDefault();
    const insumos = Object.entries(seleccionados).map(([id,cantidad])=>({id:parseInt(id), cantidad:parseFloat(cantidad)}));
    if(insumos.length === 0){
        alert('Ingresa cantidades válidas');
        return;
    }
    try {
        const resp = await fetch('../../api/bodega/generar_qr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ insumos })
        });
        const text = await resp.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Respuesta no es JSON:", text);
            alert("Error al procesar la respuesta del servidor.");
            return;
        }
        if(data.success){
            const url = data.resultado.url;
            const pdf = '../../' + data.resultado.pdf_url;
            const img = '../../' + data.resultado.qr_url;
            document.getElementById('resultado').innerHTML =
                '<p class="text-white">Escanea el código para recibir:</p>'+
                '<img src="'+img+'" alt="QR" width="200" height="200">'+
                '<p class="mt-2"><a class="btn custom-btn" href="'+pdf+'" target="_blank">Ver PDF</a></p>'+
                '<p class="mt-2"><a class="btn custom-btn" href="../../api/bodega/imprimir_qr.php?qrName='+img+'"  target="_blank">Imprimir PDF</a></p>';
            seleccionados = {};
            localStorage.removeItem('qr_actual');
            renderTabla();
            actualizarResumen();
           
        } else {
            alert(data.mensaje || 'Error');
        }
    } catch(err){
        console.error(err);
        alert('Error de comunicación');
    }
});