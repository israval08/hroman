<?php
require_once 'config/db.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionLink - Carga y Análisis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container my-4">
        <h2 class="text-center">VisionLink - Herramienta de Carga y Análisis</h2>

        <!-- Formulario para Subir CSV -->
        <div class="mb-4">
            <h4>Cargar Archivo CSV</h4>
            <form action="procesar_csv_visionlink.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="csvFile" class="form-label">Subir archivo CSV</label>
                    <input type="file" name="csvFile" id="csvFile" class="form-control" accept=".csv" required>
                </div>
                <button type="submit" class="btn btn-primary">Cargar Datos</button>
            </form>
        </div>

        <hr>

    <!-- Filtros de Búsqueda -->
<div class="mb-4">
    <h4>Filtrar Datos</h4>
    <form id="filterForm">
        <div class="row">
            <div class="col-md-4">
                <label for="patenteFilter" class="form-label">Patente</label>
                <select id="patenteFilter" class="form-control" multiple>
                    <?php
                    // Obtener las patentes únicas desde la base de datos
                    $queryPatentes = "SELECT DISTINCT patente FROM visionlink_data ORDER BY patente";
                    $resultPatentes = $conn->query($queryPatentes);
                    while ($row = $resultPatentes->fetch_assoc()) {
                        echo "<option value=\"{$row['patente']}\">{$row['patente']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="fechaInicio" class="form-label">Fecha Inicio</label>
                <input type="date" id="fechaInicio" class="form-control">
            </div>
            <div class="col-md-4">
                <label for="fechaFin" class="form-label">Fecha Fin</label>
                <input type="date" id="fechaFin" class="form-control">
            </div>
        </div>
        <button type="button" id="applyFilters" class="btn btn-success mt-3">Aplicar Filtros</button>
    </form>
</div>



        <hr>

        <!-- Contenedor de Gráficos -->
        <div id="chartsContainer" class="mb-4">
            <h4>Gráficos Generados</h4>
            <canvas id="kilometrajeChart"></canvas>
            <canvas id="velocidadMaximaChart" class="mt-4"></canvas>
        </div>
    </div>

    <script>
       $(document).ready(function () {
    $('#applyFilters').click(function () {
        const patentes = $('#patenteFilter').val(); // Captura múltiples valores
        const fechaInicio = $('#fechaInicio').val();
        const fechaFin = $('#fechaFin').val();

        // Solicitar datos filtrados al servidor
        $.ajax({
            url: 'filtrar_datos_visionlink.php',
            method: 'POST',
            data: {
                patentes: patentes, // Envía como array
                fechaInicio: fechaInicio,
                fechaFin: fechaFin
            },
            dataType: 'json',
            success: function (response) {
                // Actualizar gráficos con los datos filtrados
                generarGraficos(response);
            },
            error: function () {
                alert('Error al filtrar los datos.');
            }
        });
    });

    // Función para generar gráficos
    function generarGraficos(data) {
        const patentes = data.map(item => item.patente);
        const kilometrajes = data.map(item => item.total_kilometraje);
        const velocidades = data.map(item => item.max_velocidad);

        // Gráfico de Kilometraje Total
        const kilometrajeCtx = document.getElementById('kilometrajeChart').getContext('2d');
        new Chart(kilometrajeCtx, {
            type: 'bar',
            data: {
                labels: patentes,
                datasets: [{
                    label: 'Kilometraje Total',
                    data: kilometrajes,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: { responsive: true }
        });

        // Gráfico de Velocidad Máxima
        const velocidadCtx = document.getElementById('velocidadMaximaChart').getContext('2d');
        new Chart(velocidadCtx, {
            type: 'line',
            data: {
                labels: patentes,
                datasets: [{
                    label: 'Velocidad Máxima',
                    data: velocidades,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: { responsive: true }
        });
    }
});

    </script>
</body>
</html>
