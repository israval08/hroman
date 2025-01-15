<?php
session_start();
require_once 'config/db.php';

// Verificar conexión
if (!$conn) {
    die("Error: No se pudo conectar a la base de datos.");
}

// Simular inicio de sesión o toma de datos de sesión
$usuario_tipo = $_SESSION['usuario_tipo'] ?? ''; // Obtiene el tipo de usuario desde la sesión

// Inicializar variables de filtros
$selectedMachines = $_POST['machines'] ?? [];
$selectedWeeks = $_POST['selected_week'] ?? '';
$startDate = $_POST['specific_date'] ?? '';
$selectedYear = $_POST['selected_year'] ?? '';

// Verificar si el formulario fue enviado
$isFormSubmitted = $_SERVER['REQUEST_METHOD'] === 'POST';

// Inicializar $dataResult como null
$dataResult = null;

// Solo ejecutar la consulta si el formulario fue enviado
if ($isFormSubmitted) {
    $dataQuery = "SELECT 
                    kd.tipo_maquina, 
                    kd.numero_serie, 
                    kd.nombre_operador, 
                    kd.horas_motor, 
                    kd.horas_trabajo_real, 
                    kd.horas_ralenti, 
                    kd.horas_ralenti_ideal, 
                    kd.porcentaje_ralenti_real, 
                    ri.porcentaje_ideal_ralenti, 
                    kd.porcentaje_exceso_ralenti, 
                    kd.periodo_desde,
                    WEEK(kd.periodo_desde) AS semana 
                  FROM komtrax_data kd
                  LEFT JOIN ralenti_ideal ri ON kd.tipo_maquina = ri.tipo_equipo
                  WHERE 1=1";

    // Filtro por Número de Máquina
    if (!empty($selectedMachines)) {
        $machineList = "'" . implode("','", $selectedMachines) . "'";
        $dataQuery .= " AND kd.numero_maquina_cliente IN ($machineList)";
    }

    // Filtro por Fecha Única (prioridad sobre otros filtros)
    if (!empty($startDate)) {
        $dataQuery .= " AND DATE(kd.periodo_desde) = '$startDate'";
    } else {
        // Filtro por Año
        if (!empty($selectedYear)) {
            $dataQuery .= " AND YEAR(kd.periodo_desde) = $selectedYear";
        }

        // Filtro por Semana
        if (!empty($selectedWeeks)) {
            $dataQuery .= " AND WEEK(kd.periodo_desde) = $selectedWeeks";
        }
    }

    $dataQuery .= " ORDER BY kd.periodo_desde";
    $dataResult = mysqli_query($conn, $dataQuery);

    if (!$dataResult) {
        die("Error en la consulta SQL: " . mysqli_error($conn));
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe Komtrax</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body {
            background-color: #f9f9f9;
        }
        .header {
            text-align: center;
            background-color: #007bff;
            color: white;
            padding: 20px 0;
            margin-bottom: 20px;
            position: relative;
        }
        .header img {
            position: absolute;
            top: 10px;
            left: 20px;
            height: 60px;
        }
        .filters-container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .data-table {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        table th {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="image/hroman.png" alt="Logo">
        <h1>Informe Komtrax</h1>
        <p>Analiza tus datos con filtros avanzados y diseño profesional</p>
    </div>

    <div class="container">
        <div class="filters-container">
            <h5>Filtros de Análisis</h5>
            <div class="row">
                <div class="col-md-12 text-end">
                    <?php if ($usuario_tipo === 'superusuario'): ?>
                        <a href="super.php" class="btn btn-primary mb-3">Ir a Superusuario</a>
                    <?php elseif ($usuario_tipo === 'supervisor'): ?>
                        <a href="supervisor.php" class="btn btn-primary mb-3">Ir a Supervisor</a>
                    <?php elseif ($usuario_tipo === 'control'): ?>
                        <a href="control.php" class="btn btn-primary mb-3">Ir a Control</a>
                    <?php else: ?>
                        <p class="text-danger">No tienes permisos asignados.</p>
                    <?php endif; ?>
                </div>
            </div>
            <form method="post" action="analisis_komtrax.php">
                <div class="row">
                    <!-- Filtro por Número de Máquina -->
                    <div class="col-md-4">
                        <h6>Número de Máquina</h6>
                        <div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="select_all">
                                <label class="form-check-label">Seleccionar todo</label>
                            </div>
                        </div>
                        <div class="overflow-auto" style="max-height: 200px;">
                            <?php
                            $machineQuery = "SELECT DISTINCT numero_maquina_cliente FROM komtrax_data ORDER BY numero_maquina_cliente";
                            $machineResult = mysqli_query($conn, $machineQuery);
                            while ($row = mysqli_fetch_assoc($machineResult)): ?>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input machine_checkbox" name="machines[]" 
                                           value="<?php echo $row['numero_maquina_cliente']; ?>" 
                                           <?php echo in_array($row['numero_maquina_cliente'], $selectedMachines) ? 'checked' : ''; ?>>
                                    <label class="form-check-label"><?php echo $row['numero_maquina_cliente']; ?></label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <!-- Filtro por Año -->
                    <div class="col-md-4">
                        <h6>Año</h6>
                        <select name="selected_year" class="form-select" id="year_filter">
                            <option value="">Seleccionar...</option>
                            <?php
                            $yearQuery = "SELECT DISTINCT YEAR(periodo_desde) AS anio FROM komtrax_data ORDER BY anio";
                            $yearResult = mysqli_query($conn, $yearQuery);
                            while ($row = mysqli_fetch_assoc($yearResult)): ?>
                                <option value="<?php echo $row['anio']; ?>" 
                                    <?php echo ($row['anio'] == $selectedYear) ? 'selected' : ''; ?>>
                                    <?php echo $row['anio']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Filtro por Semana -->
                    <div class="col-md-4">
                        <h6>Semana</h6>
                        <select name="selected_week" class="form-select" id="week_filter" 
                                <?php echo empty($selectedYear) ? 'disabled' : ''; ?>>
                            <option value="">Seleccionar...</option>
                            <?php
                            if (!empty($selectedYear)) {
                                $weekQuery = "SELECT DISTINCT WEEK(periodo_desde) AS semana 
                                              FROM komtrax_data 
                                              WHERE YEAR(periodo_desde) = $selectedYear 
                                              ORDER BY semana";
                                $weekResult = mysqli_query($conn, $weekQuery);
                                while ($row = mysqli_fetch_assoc($weekResult)): ?>
                                    <option value="<?php echo $row['semana']; ?>" 
                                        <?php echo ($row['semana'] == $selectedWeeks) ? 'selected' : ''; ?>>
                                        Semana <?php echo $row['semana']; ?>
                                    </option>
                                <?php endwhile;
                            } ?>
                        </select>
                    </div>

                    <!-- Filtro por Fecha Única -->
                    <div class="col-md-4 mt-4">
                        <h6>Fecha Única</h6>
                        <input type="date" name="specific_date" class="form-control" id="day_filter" 
                               value="<?php echo $startDate; ?>" 
                               <?php echo !empty($selectedYear) ? 'disabled' : ''; ?>>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="button" class="btn btn-secondary" id="clear_filters">Desmarcar Todo</button>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
        <div class="data-table">
            <?php if ($isFormSubmitted): ?>
                <?php if ($dataResult && mysqli_num_rows($dataResult) > 0): ?>
                    <h5>Resultados del análisis</h5>
                    <table class="table table-striped table-bordered" id="analysisTable">
                        <thead>
                            <tr>
                                <th>Tipo de Equipo</th>
                                <th>Patente / N° Serie</th>
                                <th>Operador</th>
                                <th>Horas de Motor</th>
                                <th>Horas de Trabajo Real</th>
                                <th>Horas de Ralentí Real</th>
                                <th>Horas de Ralentí Ideal</th>
                                <th>% Ralentí Real</th>
                                <th>% Ralentí Ideal</th>
                                <th>% Exceso de Ralentí</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($dataResult)): ?>
                                <tr>
                                    <td><?php echo $row['tipo_maquina'] ?? ''; ?></td>
                                    <td><?php echo $row['numero_serie'] ?? ''; ?></td>
                                    <td><?php echo $row['nombre_operador'] ?? 'Sin Operador'; ?></td>
                                    <td><?php echo number_format($row['horas_motor'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($row['horas_trabajo_real'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($row['horas_ralenti'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($row['horas_ralenti_ideal'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($row['porcentaje_ralenti_real'] ?? 0, 2); ?>%</td>
                                    <td><?php echo number_format($row['porcentaje_ideal_ralenti'] ?? 0, 2); ?>%</td>
                                    <td><?php echo number_format($row['porcentaje_exceso_ralenti'] ?? 0, 2); ?>%</td>
                                    <td><?php echo !empty($row['periodo_desde']) ? date("d-m-Y", strtotime($row['periodo_desde'])) : ''; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-results">No se encontraron resultados para los filtros aplicados.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-results">Seleccione los filtros deseados y presione "Filtrar" para ver los resultados.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    const yearFilter = document.getElementById('year_filter');
    const weekFilter = document.getElementById('week_filter');
    const dayFilter = document.getElementById('day_filter');
    const selectAllCheckbox = document.getElementById('select_all');
    const machineCheckboxes = document.querySelectorAll('.machine_checkbox');

    // Manejo de exclusividad entre Año/Semana y Día
    yearFilter.addEventListener('change', function () {
        if (this.value) {
            weekFilter.disabled = false;
            dayFilter.disabled = true;
        } else {
            weekFilter.disabled = true;
            dayFilter.disabled = false;
        }
    });

    dayFilter.addEventListener('change', function () {
        if (this.value) {
            yearFilter.disabled = true;
            weekFilter.disabled = true;
        } else {
            yearFilter.disabled = false;
            weekFilter.disabled = false;
        }
    });

    // Funcionalidad "Seleccionar todo"
    selectAllCheckbox.addEventListener('change', function () {
        machineCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
    });

    const clearFiltersButton = document.getElementById('clear_filters');
    clearFiltersButton.addEventListener('click', function () {
        // Desmarcar todos los checkboxes
        machineCheckboxes.forEach(checkbox => checkbox.checked = false);

        // Restablecer los selects
        yearFilter.value = '';
        weekFilter.value = '';
        dayFilter.value = '';

        // Habilitar los filtros bloqueados
        yearFilter.disabled = false;
        weekFilter.disabled = true;
        dayFilter.disabled = false;

        // Desmarcar "Seleccionar todo"
        selectAllCheckbox.checked = false;
    });

    // Inicializar DataTables para ordenar columnas
    $(document).ready(function () {
        $('#analysisTable').DataTable({
            order: [[7, 'desc']] // Ordena la tabla por la columna "% Ralentí Real" (índice 7)
        });
    });
</script>
</html>
