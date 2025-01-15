<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está logueado y es superusuario
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'superusuario') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $usuario_id = $_SESSION['user_id']; // Asumimos que el requerimiento es creado por el usuario logueado

    // Validar y sanitizar los datos
    $titulo = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $descripcion = htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8');

    // Insertar el requerimiento en la base de datos
    $sql = "INSERT INTO requerimientos (usuario_id, titulo, descripcion) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $usuario_id, $titulo, $descripcion);

    if ($stmt->execute()) {
        echo "Requerimiento creado exitosamente.";
    } else {
        echo "Error al crear el requerimiento: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Método no permitido.";
}
?>
