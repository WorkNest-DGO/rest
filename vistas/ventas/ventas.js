const boton = document.getElementById('cargarVentas');
const tabla = document.getElementById('tablaVentas').querySelector('tbody');

boton.addEventListener('click', async () => {
    try {
        const respuesta = await fetch('../../api/ventas/listar_ventas.php');
        const datos = await respuesta.json();
        if (datos.success) {
            renderVentas(datos.resultado);
        } else {
            alert(datos.mensaje);
        }
    } catch (err) {
        console.error(err);
        alert('Error al cargar las ventas');
    }
});

function renderVentas(ventas) {
    tabla.innerHTML = '';
    ventas.forEach(v => {
        const fila = document.createElement('tr');
        fila.innerHTML = `
            <td>${v.id}</td>
            <td>${v.fecha}</td>
            <td>${v.mesa}</td>
            <td>${v.total}</td>
            <td>${v.estatus}</td>
        `;
        tabla.appendChild(fila);
    });
}
