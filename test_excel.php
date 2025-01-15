<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Ruta al archivo Excel de prueba
$filePath = "C:\\Users\\israv\\Downloads\\666666666666.xlsx";

try {
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray();

    echo "<pre>";
    print_r($data);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error al leer el archivo Excel: " . $e->getMessage();
}
