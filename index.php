<?php
session_start();
if (isset($_SESSION['nombre'])) {
    header('Location: vistas/index.php');
} else {
    header('Location: login.html');
}
exit;
?>
