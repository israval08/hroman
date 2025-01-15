<?php
require_once 'config/db.php';

$patente = $_POST['patente'] ?? '';
$fechaInicio = $_POST['fechaInicio'] ?? '';
$fechaFin = $_POST['fechaFin'] ?? '';

$query = "SELECT patente, SUM(kilometraje) AS total_kilometraje, MAX(velocidad_maxima) AS max_velocidad 
          FROM visionlink_data WHERE 1";

if (!empty($patente)) {
    $query .= " AND patente = '$patente'";
}

if (!empty($fechaInicio)) {
    $query .= " AND fecha >= '$fechaInicio'";
}

if (!empty($fechaFin)) {
    $query .= " AND fecha <= '$fechaFin'";
}

$query .= " GROUP BY patente";

$result = $conn->query($query);
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>
