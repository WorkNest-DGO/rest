<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.html');
    exit;
}
$title = 'Horarios';
ob_start();
?>
<h1>Horarios de Series</h1>
<form id="formHorario">
    <input type="hidden" id="horarioId">
    <label>Día:</label>
    <select id="dia_semana">
        <option value="Lunes">Lunes</option>
        <option value="Martes">Martes</option>
        <option value="Miercoles">Miércoles</option>
        <option value="Jueves">Jueves</option>
        <option value="Viernes">Viernes</option>
        <option value="Sabado">Sábado</option>
        <option value="Domingo">Domingo</option>
    </select>
    <label>Inicio:</label><input type="time" id="hora_inicio">
    <label>Fin:</label><input type="time" id="hora_fin">
    <label>Serie:</label>
    <select id="serie_id"></select>
    <button type="submit">Guardar</button>
    <button type="button" id="cancelar" style="display:none;">Cancelar</button>
</form>
<table id="tablaHorarios" border="1">
    <thead>
        <tr><th>Día</th><th>Inicio</th><th>Fin</th><th>Serie</th><th>Acciones</th></tr>
    </thead>
    <tbody></tbody>
</table>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script src="horarios.js"></script>
    </body>
</html>
<?php
$content = ob_get_clean();
include __DIR__ . '/../nav.php';
?>
