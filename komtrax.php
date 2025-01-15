<?php
session_start();
require_once 'config/db.php'; // Asegúrate de que este archivo existe y configura $conn correctamente

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Komtrax - Cargar CSV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Cargar Archivo CSV - Komtrax</h1>
        <!-- Formulario para subir el CSV -->
        <form action="komtrax.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="csv_file" class="form-label">Selecciona el archivo CSV</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" name="submit" class="btn btn-primary">Cargar</button>
        </form>

        <?php
        // Procesar la carga del archivo CSV
        if (isset($_POST['submit'])) {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
                $fileName = $_FILES['csv_file']['tmp_name'];

                // Convertir el archivo a UTF-8 (si es necesario)
                $fileContent = file_get_contents($fileName);
                $fileContent = mb_convert_encoding($fileContent, 'UTF-8', 'UTF-16LE');
                file_put_contents($fileName, $fileContent);

                // Abrir el archivo para leer
                $file = fopen($fileName, 'r');
                $rowCount = 0;
                $skippedCount = 0;

                while (($column = fgetcsv($file, 1000, "\t")) !== FALSE) {
                    if (count($column) >= 9) {
                        $tipo_maquina = mysqli_real_escape_string($conn, $column[0]);
                        $modelo = mysqli_real_escape_string($conn, $column[1]);
                        $numero_serie = mysqli_real_escape_string($conn, $column[2]);
                        $numero_maquina_cliente = mysqli_real_escape_string($conn, $column[3]);
                        $nombre_operador = mysqli_real_escape_string($conn, $column[4]);
                        $observaciones = mysqli_real_escape_string($conn, $column[5]);
                        $horas_motor = mysqli_real_escape_string($conn, $column[6]);
                        $horas_trabajo_real = mysqli_real_escape_string($conn, $column[7]);
                        $periodo_desde = mysqli_real_escape_string($conn, $column[8]);

                        // Verificar duplicados
                        $checkQuery = "SELECT * FROM komtrax_data 
                                       WHERE numero_serie = '$numero_serie' 
                                       AND periodo_desde = '$periodo_desde'";
                        $result = mysqli_query($conn, $checkQuery);

                        if (mysqli_num_rows($result) == 0) {
                            $insertQuery = "INSERT INTO komtrax_data (
                                tipo_maquina, modelo, numero_serie, numero_maquina_cliente,
                                nombre_operador, observaciones, horas_motor, horas_trabajo_real,
                                periodo_desde
                            ) VALUES (
                                '$tipo_maquina', '$modelo', '$numero_serie', '$numero_maquina_cliente',
                                '$nombre_operador', '$observaciones', '$horas_motor', '$horas_trabajo_real',
                                '$periodo_desde'
                            )";
                            mysqli_query($conn, $insertQuery);
                            $rowCount++;
                        } else {
                            $skippedCount++;
                        }
                    }
                }
                fclose($file);

                echo "<div class='alert alert-success mt-3'>
                        Carga completada: $rowCount registros insertados, $skippedCount registros omitidos.
                      </div>";
            } else {
                echo "<div class='alert alert-danger mt-3'>Error al cargar el archivo. Intenta nuevamente.</div>";
            }
        }
        ?>
        <!-- Botón para ir al análisis -->
        <div class="mt-4">
            <a href="analisis_komtrax.php" class="btn btn-success">Ir al Análisis de Datos</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>