<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está logueado y es superusuario
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'superusuario') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Superusuario - HROMAN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .nav-link {
            color: #333;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .nav-link:hover {
            background-color: #f8f9fa;
        }
        .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .main-content {
            padding: 20px;
        }
        .bi {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Barra lateral -->
            <div class="col-md-3 col-lg-2 bg-light sidebar p-3">
                <div class="text-center mb-4">
                    <img src="image/hroman.png" alt="HROMAN Logo" class="img-fluid mb-3" style="max-width: 150px;">
                    <h5><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></h5>
                    <p class="text-muted">Superusuario</p>
                </div>
                <hr>
                <nav class="nav flex-column">
                    <a href="#" class="nav-link active" data-page="dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a href="#" class="nav-link" data-page="crear-requerimiento">
                        <i class="bi bi-plus-circle"></i> Crear Requerimiento
                    </a>
                    <a href="#" class="nav-link" data-page="ver-requerimiento">
                        <i class="bi bi-list-check"></i> Ver Requerimientos
                    </a>
                    <a href="#" class="nav-link" data-page="crear-reporte">
                        <i class="bi bi-file-earmark-plus"></i> Crear Reporte
                    </a>
                    <a href="#" class="nav-link" data-page="ver-reporte">
                        <i class="bi bi-files"></i> Ver Reportes
                    </a>
                    <!-- Menú desplegable Informes -->
                    <a class="nav-link dropdown-toggle" href="#informesSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="informesSubmenu">
                        <i class="bi bi-bar-chart"></i> Informes
                    </a>
                    <div class="collapse" id="informesSubmenu">
                        <a href="#" class="nav-link ms-3" data-page="komtrax">
                            <i class="bi bi-circle"></i> Komtrax
                        </a>
                        <a href="#" class="nav-link ms-3" data-page="visionlink">
                            <i class="bi bi-circle"></i> VisionLink
                        </a>
                    </div>
                    <hr>
                    <a href="logout.php" class="nav-link text-danger">
                        <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                    </a>
                </nav>
            </div>

            <!-- Contenido principal -->
            <div class="col-md-9 col-lg-10 main-content">
                <div id="contenido-dinamico">
                    <!-- Contenido por defecto del Dashboard -->
                    <h2>Bienvenido al Panel de Control</h2>
                    <p>Selecciona una opción del menú lateral para comenzar.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const navLinks = document.querySelectorAll('.nav-link[data-page]');

        // Manejar clics en los enlaces del menú
        navLinks.forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();

                // Quitar clase active de todos los enlaces y añadirla al enlace actual
                navLinks.forEach(link => link.classList.remove('active'));
                this.classList.add('active');

                // Cargar la página seleccionada dinámicamente
                const page = this.getAttribute('data-page');
                fetch(`${page}.php`)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('contenido-dinamico').innerHTML = html;
                    })
                    .catch(error => console.error('Error al cargar la página:', error));
            });
        });
    });
    </script>
</body>
</html>
