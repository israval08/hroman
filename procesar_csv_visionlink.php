<?php
require_once 'config/db.php'; // Incluye tu archivo de conexión

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $file = $_FILES['csvFile']['tmp_name'];

    try {
        // Validar que el archivo existe
        if (!file_exists($file)) {
            die("<p>El archivo no se cargó correctamente. Intenta nuevamente.</p>");
        }

        // Abrir el archivo CSV
        $handle = fopen($file, 'r');
        if ($handle === false) {
            die("<p>No se pudo abrir el archivo CSV.</p>");
        }

        // Preparar la consulta SQL
        $stmt = $conn->prepare("INSERT INTO visionlink_data (numero, patente, fecha, comienzo, posicion_inicial, fin, posicion_final, duracion, kilometraje, velocidad_maxima) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Leer el archivo línea por línea
        $rowIndex = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            // Saltar la primera fila si contiene encabezados
            if ($rowIndex === 0) {
                $rowIndex++;
                continue;
            }

            // Mapear las columnas del CSV a variables
            $numero = $data[0];
            $patente = $data[1];
            $fecha = date('Y-m-d', strtotime($data[2]));
            $comienzo = $data[3];
            $posicion_inicial = $data[4];
            $fin = $data[5];
            $posicion_final = $data[6];
            $duracion = $data[7];
            $kilometraje = intval($data[8]);
            $velocidad_maxima = intval($data[9]);

            // Vincular parámetros y ejecutar
            if (!$stmt->bind_param('ssssssssii', $numero, $patente, $fecha, $comienzo, $posicion_inicial, $fin, $posicion_final, $duracion, $kilometraje, $velocidad_maxima)) {
                echo "<p>Error al vincular parámetros: " . $stmt->error . "</p>";
            }

            if (!$stmt->execute()) {
                echo "<p>Error al insertar la fila: " . $stmt->error . "</p>";
            }

            $rowIndex++;
        }

        // Cerrar el archivo y la conexión
        fclose($handle);
        $stmt->close();
        $conn->close();

        echo "<p>Datos cargados exitosamente desde el archivo CSV.</p>";

    } catch (Exception $e) {
        echo "<p>Error al procesar el archivo: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>No se recibió ningún archivo.</p>";
}
?>
