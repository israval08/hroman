<?php
session_start();
require_once 'config/db.php';

// Verificar conexión a la base de datos
if (!$conn) {
    die("Error: No se pudo conectar a la base de datos.");
}

// Inicializar variables de filtros
$selectedMachines = isset($_POST['machines']) ? $_POST['machines'] : [];
$startDate = isset($_POST['specific_date']) ? $_POST['specific_date'] : '';
$selectedYear = isset($_POST['selected_year']) ? $_POST['selected_year'] : '';
$selectedWeeks = isset($_POST['selected_week']) ? $_POST['selected_week'] : '';

// Verificar si el formulario fue enviado
$isFormSubmitted = $_SERVER['REQUEST_METHOD'] === 'POST';

// Inicializar $dataResult como null
$dataResult = null;

if ($isFormSubmitted) {
    $dataQuery = "SELECT 
        kd.observaciones, 
        kd.numero_serie, 
        kd.nombre_operador, 
        kd.horas_motor, 
        kd.horas_trabajo_real, 
        kd.ralenti_real, 
        kd.horas_ralenti_ideal, 
        kd.porcentaje_ralenti_real, 
        ri.porcentaje_ideal_ralenti AS porcentaje_ideal_ralenti,
        kd.porcentaje_exceso_ralenti2, 
        kd.periodo_desde,
        WEEK(kd.periodo_desde) AS semana 
      FROM komtrax_data kd
      LEFT JOIN ralenti_ideal ri 
      ON kd.observaciones = ri.tipo_equipo
      WHERE 1=1";

    // Filtros
    if (!empty($_POST['tipo_equipo'])) {
        $tipoEquipoList = "'" . implode("','", $_POST['tipo_equipo']) . "'";
        $dataQuery .= " AND kd.observaciones IN ($tipoEquipoList)";
    }

    if (!empty($_POST['operador'])) {
        $operadorList = "'" . implode("','", $_POST['operador']) . "'";
        $dataQuery .= " AND kd.nombre_operador IN ($operadorList)";
    }

    if (!empty($_POST['selected_year'])) {
        $dataQuery .= " AND YEAR(kd.periodo_desde) = '" . $_POST['selected_year'] . "'";
        if (!empty($_POST['selected_week'])) {
            $dataQuery .= " AND WEEK(kd.periodo_desde) = '" . $_POST['selected_week'] . "'";
        }
    }

    if (!empty($_POST['specific_date'])) {
        $dataQuery .= " AND DATE(kd.periodo_desde) = '" . $_POST['specific_date'] . "'";
    }

    if (!empty($_POST['fecha_desde']) && !empty($_POST['fecha_hasta'])) {
        $dataQuery .= " AND kd.periodo_desde BETWEEN '" . $_POST['fecha_desde'] . "' AND '" . $_POST['fecha_hasta'] . "'";
    }

    // Filtro por Porcentaje de Exceso Ralentí
    if (!empty($_POST['ralenti_min']) || !empty($_POST['ralenti_max'])) {
        $ralentiMin = isset($_POST['ralenti_min']) ? (float)$_POST['ralenti_min'] : 0;
        $ralentiMax = isset($_POST['ralenti_max']) ? (float)$_POST['ralenti_max'] : 100;
        $dataQuery .= " AND kd.porcentaje_exceso_ralenti2 BETWEEN $ralentiMin AND $ralentiMax";
    }
    if (!empty($_POST['machines'])) {
        $machineList = "'" . implode("','", $_POST['machines']) . "'";
        $dataQuery .= " AND kd.numero_maquina_cliente IN ($machineList)";
    }

    // Ordenar resultados
    $dataQuery .= " ORDER BY kd.periodo_desde";

    // Ejecutar consulta
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

    <!-- jQuery primero -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap CSS y JS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables CSS y JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <style>
        body {
            display: flex;
            margin: 0;
            background-color: #f9f9f9;
        }
        .sidebar {
            width: 220px;
            height: 100vh;
            background-color: #f8f9fa;
            overflow-y: auto;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .main-content {
            flex-grow: 1;
            margin-left: 0px;
            padding: 0;
            transition: margin-left 0.3s;
        }
        .header {
            text-align: center;
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            padding: 30px 0;
            margin: 0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .header img,
        .header h1,
        .header p {
            animation: fadeIn 2s ease-in-out;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .header h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .header img {
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
        .data-table .d-flex {
            margin-bottom: 1rem;
        }
        .data-table h5 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: bold;
        }
        .data-table a.btn-success {
            font-size: 0.9rem;
            padding: 8px 12px;
        }
        .filter-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .filter-card .card-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #ddd;
        }
        .filter-card .card-body {
            padding: 15px;
        }
        .filter-card .form-check {
            margin-bottom: 10px;
        }
        .filter-card .form-check-label {
            margin-left: 5px;
        }
        .filter-card .input-group {
            margin-bottom: 10px;
        }
        .filter-card .input-group-text {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
        }
        .filter-card .form-control {
            border-radius: 4px;
        }
        .filter-card .btn {
            width: 100%;
            margin-top: 10px;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                box-shadow: none;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Incluir barra lateral -->
    <?php include 'sidebar_super.php'; ?>

    <!-- Contenido principal -->
    <div class="main-content">
        <div class="header">
            <img src="image/hroman.png" alt="Logo">
            <h1>Informe Komtrax</h1>
            <p>Analiza tus datos con filtros avanzados y diseño profesional</p>
        </div>
        <div class="filters-container">
            <h4 class="text-center mb-4 text-primary">Filtros de Análisis</h4>
            <form method="post" action="analisis_komtrax.php">
                <div class="row g-4">
                    <!-- Filtro por Tipo de Equipo -->
                    <div class="col-md-4">
                        <div class="filter-card">
                            <div class="card-header">
                                <h6 class="card-title text-secondary">Tipo de Equipo</h6>
                            </div>
                            <div class="card-body">
                                <div class="overflow-auto" style="max-height: 200px;">
                                    <?php
                                    $equipoQuery = "SELECT DISTINCT tipo_equipo FROM ralenti_ideal ORDER BY tipo_equipo";
                                    $equipoResult = mysqli_query($conn, $equipoQuery);
                                    while ($row = mysqli_fetch_assoc($equipoResult)): ?>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   name="tipo_equipo[]" 
                                                   value="<?php echo $row['tipo_equipo']; ?>"
                                                   <?php echo in_array($row['tipo_equipo'], $_POST['tipo_equipo'] ?? []) ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                <?php echo $row['tipo_equipo']; ?>
                                            </label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro por Operador -->
                    <div class="col-md-4">
                        <div class="filter-card">
                            <div class="card-header">
                                <h6 class="card-title text-secondary">Nombre del Operador</h6>
                            </div>
                            <div class="card-body">
                                <div class="overflow-auto" style="max-height: 200px;">
                                    <?php
                                    $operadorQuery = "SELECT DISTINCT nombre_operador FROM komtrax_data WHERE nombre_operador IS NOT NULL ORDER BY nombre_operador";
                                    $operadorResult = mysqli_query($conn, $operadorQuery);
                                    while ($row = mysqli_fetch_assoc($operadorResult)): ?>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   name="operador[]" 
                                                   value="<?php echo $row['nombre_operador']; ?>"
                                                   <?php echo in_array($row['nombre_operador'], $_POST['operador'] ?? []) ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                <?php echo $row['nombre_operador']; ?>
                                            </label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro por Número de Máquina -->
                    <div class="col-md-4">
                        <div class="filter-card">
                            <div class="card-header">
                                <h6 class="card-title text-secondary">Patente / Número Serie</h6>
                            </div>
                            <div class="card-body">
                                <div class="overflow-auto" style="max-height: 200px;">
                                    <?php
                                    $machineQuery = "SELECT DISTINCT numero_maquina_cliente FROM komtrax_data ORDER BY numero_maquina_cliente";
                                    $machineResult = mysqli_query($conn, $machineQuery);
                                    while ($row = mysqli_fetch_assoc($machineResult)): ?>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input machine-checkbox" 
                                                   name="machines[]" 
                                                   value="<?php echo $row['numero_maquina_cliente']; ?>"
                                                   <?php echo in_array($row['numero_maquina_cliente'], $_POST['machines'] ?? []) ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                <?php echo $row['numero_maquina_cliente']; ?>
                                            </label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="mt-2">
                                    <input type="checkbox" id="select_all" class="form-check-input">
                                    <label for="select_all" class="form-check-label">Seleccionar todo</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro por Rango de Fechas -->
                    <div class="col-md-6">
                        <div class="filter-card">
                            <div class="card-header">
                                <h6 class="card-title text-secondary">Rango de Fechas</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex gap-2">
                                    <input type="date" name="fecha_desde" class="form-control"
                                           value="<?php echo $_POST['fecha_desde'] ?? ''; ?>">
                                    <input type="date" name="fecha_hasta" class="form-control"
                                           value="<?php echo $_POST['fecha_hasta'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro por Porcentaje de Exceso Ralentí -->
                    <div class="col-md-6">
                        <div class="filter-card">
                            <div class="card-header">
                                <h6 class="card-title text-secondary">Porcentaje de Exceso Ralentí</h6>
                            </div>
                            <div class="card-body">
                                <div class="input-group">
                                    <span class="input-group-text">Min</span>
                                    <input type="number" name="ralenti_min" class="form-control" placeholder="0" 
                                           value="<?php echo $_POST['ralenti_min'] ?? ''; ?>">
                                    <span class="input-group-text">Max</span>
                                    <input type="number" name="ralenti_max" class="form-control" placeholder="100" 
                                           value="<?php echo $_POST['ralenti_max'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary px-4 py-2">Filtrar</button>
                    <button type="button" class="btn btn-secondary px-4 py-2" onclick="window.location.href='analisis_komtrax.php'">
                        Limpiar Filtros
                    </button>
                </div>
            </form>
        </div>

        <!-- Resultados del análisis -->
        <div class="data-table">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Resultados del análisis</h5>
                <?php if ($isFormSubmitted && $dataResult && mysqli_num_rows($dataResult) > 0): ?>
    <div>
        <button type="button" onclick="exportarExcel()" class="btn btn-success">
            Descargar Excel
        </button>
    </div>
<?php endif; ?>
            </div>

            <?php if ($isFormSubmitted): ?>
                <?php if ($dataResult && mysqli_num_rows($dataResult) > 0): ?>
                    <table class="table table-striped table-bordered" id="analysisTable">
                        <thead>
                            <tr>
                                <th>Tipo Equipo</th>
                                <th>Patente / N° Serie</th>
                                <th>Operador</th>
                                <th>Horas Motor</th>
                                <th>Trabajo Real</th>
                                <th>Ralentí Real</th>
                                <th>Horas Ralentí Ideal</th>
                                <th>% Ralentí Real</th>
                                <th>% Ralentí Ideal</th>
                                <th>% Exceso Ralentí</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalHorasMotor = 0;
                            $totalTrabajoReal = 0;
                            $totalRalentiReal = 0;
                            $totalHorasRalentiIdeal = 0;
                            $totalPorcentajeRalentiReal = 0;
                            $totalPorcentajeExcesoRalenti = 0;
                            $totalPorcentajeIdealRalenti = 0;
                            $rowCount = 0;

                            while ($row = mysqli_fetch_assoc($dataResult)): 
                                $totalHorasMotor += $row['horas_motor'] ?? 0;
                                $totalTrabajoReal += $row['horas_trabajo_real'] ?? 0;
                                $totalRalentiReal += $row['ralenti_real'] ?? 0;
                                $totalHorasRalentiIdeal += $row['horas_ralenti_ideal'] ?? 0;
                                $totalPorcentajeRalentiReal += $row['porcentaje_ralenti_real'] ?? 0;
                                $totalPorcentajeExcesoRalenti += $row['porcentaje_exceso_ralenti2'] ?? 0;
                                $totalPorcentajeIdealRalenti += $row['porcentaje_ideal_ralenti'] ?? 0;
                                $rowCount++;
                            ?>
                                <tr>
                                    <td><?php echo $row['observaciones'] ?? ''; ?></td>
                                    <td><?php echo $row['numero_serie'] ?? ''; ?></td>
                                    <td><?php echo $row['nombre_operador'] ?? 'Sin Operador'; ?></td>
                                    <td><?php echo number_format($row['horas_motor'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($row['horas_trabajo_real'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($row['ralenti_real'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($row['horas_ralenti_ideal'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format(($row['porcentaje_ralenti_real'] ?? 0) * 100, 2); ?>%</td>
                                    <td><?php echo number_format($row['porcentaje_ideal_ralenti'] ?? 0, 2); ?>%</td>
                                    <td><?php echo number_format($row['porcentaje_exceso_ralenti2'] ?? 0, 2); ?>%</td>
                                    <td><?php echo date('d-m-Y', strtotime($row['periodo_desde'] ?? '')); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Totales / Promedios</th>
                                <th><?php echo number_format($totalHorasMotor, 2); ?></th>
                                <th><?php echo number_format($totalTrabajoReal, 2); ?></th>
                                <th><?php echo number_format($totalRalentiReal, 2); ?></th>
                                <th><?php echo number_format($totalHorasRalentiIdeal, 2); ?></th>
                                <th><?php echo number_format($rowCount > 0 ? $totalPorcentajeRalentiReal / $rowCount : 0, 2); ?>%</th>
                                <th><?php echo number_format($rowCount > 0 ? $totalPorcentajeIdealRalenti / $rowCount : 0, 2); ?>%</th>
                                <th><?php echo number_format($rowCount > 0 ? $totalPorcentajeExcesoRalenti / $rowCount : 0, 2); ?>%</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">No se encontraron resultados para los filtros aplicados.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">Seleccione los filtros deseados y presione "Filtrar" para ver los resultados.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Botón "Seleccionar todo"
            $('#select_all').change(function() {
                // Selecciona o deselecciona todos los checkboxes con la clase "machine-checkbox"
                $('.machine-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Si se deselecciona un checkbox manualmente, desmarcar el botón "Seleccionar todo"
            $('.machine-checkbox').change(function() {
                if (!$(this).prop('checked')) {
                    $('#select_all').prop('checked', false);
                }
            });

            // Inicialización de DataTables
            $('#analysisTable').DataTable({
                "order": [[7, "desc"]],
                "language": {
                    "lengthMenu": "Mostrar _MENU_ registros por página",
                    "zeroRecords": "No se encontraron resultados",
                    "info": "Mostrando página _PAGE_ de _PAGES_",
                    "infoEmpty": "No hay registros disponibles",
                    "infoFiltered": "(filtrado de _MAX_ registros totales)",
                    "search": "Buscar:",
                    "paginate": {
                        "first": "Primero",
                        "last": "Último",
                        "next": "Siguiente",
                        "previous": "Anterior"
                    }
                },
                "pageLength": 10,
                "responsive": true
            });
        });

function exportarExcel() {
    const formOriginal = document.querySelector('form');
    const formTemporal = document.createElement('form');
    
    formTemporal.method = 'POST';
    formTemporal.action = 'export_excel_nuevo.php';
    formTemporal.style.display = 'none';

    // Clonar todos los inputs del formulario original
    Array.from(formOriginal.elements).forEach(element => {
        if ((element.type === 'checkbox' || element.type === 'radio') && !element.checked) return;
        
        const clone = element.cloneNode(true);
        
        // Manejar selects múltiples
        if (element.tagName === 'SELECT' && element.multiple) {
            Array.from(element.selectedOptions).forEach(option => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = element.name;
                hidden.value = option.value;
                formTemporal.appendChild(hidden);
            });
        } else {
            formTemporal.appendChild(clone);
        }
    });

    document.body.appendChild(formTemporal);
    formTemporal.submit();
}

    </script>
</body>
</html>