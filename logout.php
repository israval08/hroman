<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    // Registrar logout en logs
    $stmt = $conn->prepare("INSERT INTO logs (usuario_id, accion) VALUES (?, 'Logout exitoso')");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    
    // Destruir todas las variables de sesión
    $_SESSION = array();
    
    // Destruir la sesión
    session_destroy();
}

header("Location: login.php");
exit();
?>