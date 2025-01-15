<?php
session_start();
require_once 'config/db.php';

// Verificar si el ID fue enviado y es un número válido
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $requerimiento_id = $_GET['id'];

    // Obtener los detalles del requerimiento
    $sql = "
        SELECT r.titulo, r.descripcion, r.estado, r.created_at, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
        FROM requerimientos r
        JOIN usuarios u ON r.usuario_id = u.id
        WHERE r.id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $requerimiento_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Obtener los datos del requerimiento
        $requerimiento = $result->fetch_assoc();
        echo json_encode($requerimiento); // Devolver los datos como JSON
    } else {
        echo json_encode(['error' => 'Requerimiento no encontrado']);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'ID inválido']);
}

$conn->close();
?>
