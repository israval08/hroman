<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está logueado y es superusuario
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'superusuario') {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Obtener los requerimientos junto con el nombre del usuario
$sql = "
    SELECT r.id AS requerimiento_id, r.titulo, r.descripcion, r.estado, r.created_at, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
    FROM requerimientos r
    JOIN usuarios u ON r.usuario_id = u.id
    ORDER BY r.created_at DESC
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Requerimientos</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="mb-4 text-center">Lista de Requerimientos</h2>

        <!-- Tabla de requerimientos -->
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Requerimiento</th>
                    <th>Solicitado por</th>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['titulo']); ?></td>
                        <td><?php echo htmlspecialchars($row['usuario_nombre']) . ' ' . htmlspecialchars($row['usuario_apellido']); ?></td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($row['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                        <td>
                            <button class="btn btn-danger btn-sm eliminar" data-id="<?php echo $row['requerimiento_id']; ?>">Eliminar</button>
                            <button class="btn btn-primary btn-sm asignar" data-id="<?php echo $row['requerimiento_id']; ?>">Asignar</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No hay requerimientos disponibles.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Manejar la acción de eliminar
        document.querySelectorAll('.eliminar').forEach(button => {
            button.addEventListener('click', function () {
                const requerimientoId = this.getAttribute('data-id');
                if (confirm('¿Estás seguro de que deseas eliminar este requerimiento?')) {
                    fetch(`eliminar_requerimiento.php?id=${requerimientoId}`, {
                        method: 'DELETE'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Requerimiento eliminado correctamente.');
                            location.reload();
                        } else {
                            alert('Error al eliminar el requerimiento.');
                        }
                    })
                    .catch(error => console.error('Error al eliminar el requerimiento:', error));
                }
            });
        });

        // Manejar la acción de asignar
        document.querySelectorAll('.asignar').forEach(button => {
            button.addEventListener('click', function () {
                const requerimientoId = this.getAttribute('data-id');
                // Aquí puedes implementar la lógica para asignar el requerimiento
                alert(`Asignar funcionalidad en construcción para ID: ${requerimientoId}`);
            });
        });
    });
    </script>
</body>
</html>

<?php
$conn->close();
?>
