<?php
// sidebar_supervisor.php
?>
<div class="d-flex flex-column p-3 text-bg-dark" style="width: 250px; min-height: 100vh;">
    <div class="text-center mb-4">
        <img src="image/hroman.png" alt="HROMAN Logo" class="img-fluid mb-3" style="max-width: 120px;">
        <h5><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></h5>
        <p class="text-muted">Supervisor</p>
    </div>
    <hr class="text-secondary">
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="#" class="nav-link text-light">
                <i class="bi bi-person"></i> Usuario
            </a>
        </li>
        <li>
            <a href="#" class="nav-link text-light">
                <i class="bi bi-list-task"></i> Tareas
            </a>
        </li>
        <li>
            <a href="#" class="nav-link text-light">
                <i class="bi bi-envelope"></i> Solicitudes
            </a>
        </li>
        <li>
            <a href="#" class="nav-link text-light">
                <i class="bi bi-chat"></i> Respuestas
            </a>
        </li>
        <li>
            <a class="nav-link dropdown-toggle text-light" href="#informesSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="informesSubmenu">
                <i class="bi bi-bar-chart"></i> Informes
            </a>
            <div class="collapse" id="informesSubmenu">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ms-3">
                    <li><a href="analisis_komtrax.php" class="nav-link text-light">Komtrax</a></li>
                    <li><a href="visionlink.php" class="nav-link text-light">Vision-Link</a></li>
                </ul>
            </div>
        </li>
    </ul>
    <hr class="text-secondary">
    <div class="text-center">
        <a href="logout.php" class="nav-link text-danger">
            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
        </a>
    </div>
</div>
