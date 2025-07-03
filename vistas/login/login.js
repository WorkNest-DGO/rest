const form = document.getElementById('formLogin');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const datos = new FormData(form);
    try {
        const resp = await fetch('auth/login.php', { method: 'POST', body: datos });
        const data = await resp.json();
        if (data.success) {
            window.location = 'vistas/nav.php';
        } else {
            document.getElementById('mensaje').textContent = data.mensaje || 'Credenciales incorrectas';
        }
    } catch (err) {
        document.getElementById('mensaje').textContent = 'Error al iniciar sesión';
    }
});
