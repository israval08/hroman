<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está logueado y es superusuario
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'superusuario') {
    echo "Acceso no permitido.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $requerimiento_id = intval($_POST['id']); // Aseguramos que sea un número

    // Validar que el ID sea mayor a 0
    if ($requerimiento_id > 0) {
        // Eliminar el requerimiento de la base de datos
        $sql = "DELETE FROM requerimientos WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $requerimiento_id);

            if ($stmt->execute()) {
                echo "Requerimiento eliminado correctamente.";
            } else {
                echo "Error al eliminar el requerimiento.";
            }

            $stmt->close();
        } else {
            echo "Error al preparar la consulta: " . $conn->error;
        }
    } else {
        echo "ID de requerimiento no válido.";
    }
} else {
    echo "No se recibió un ID de requerimiento.";
}

$conn->close();
