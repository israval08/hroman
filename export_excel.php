<?php
require 'vendor/autoload.php';
require_once 'config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 1. Conexión a la base de datos
if (!$conn) {
    die("Error: No se pudo conectar a la base de datos.");
}

// 2. Sanitizar parámetros GET
$selectedMachines = isset($_GET['machines']) ? 
    array_map('trim', explode(',', $_GET['machines'])) : [];
$selectedMachines = array_map(function($machine) use ($conn) {
    return mysqli_real_escape_string($conn, $machine);
}, $selectedMachines);

$fechaDesde = mysqli_real_escape_string($conn, $_GET['fecha_desde'] ?? '');
$fechaHasta = mysqli_real_escape_string($conn, $_GET['fecha_hasta'] ?? '');
$ralentiMin = (float)($_GET['ralenti_min'] ?? 0);
$ralentiMax = (float)($_GET['ralenti_max'] ?? 100);
$tipoEquipo = isset($_GET['tipo_equipo']) ? 
    array_map('intval', explode(',', $_GET['tipo_equipo'])) : [];

// 3. Consulta SQL con JOIN por ralenti_id
$dataQuery = "SELECT 
                kd.observaciones AS tipo_equipo,
                kd.numero_serie,
                kd.nombre_operador,
                kd.horas_motor,
                kd.horas_trabajo_real,
                kd.ralenti_real,
                kd.horas_ralenti_ideal,
                kd.porcentaje_ralenti_real,
                ri.porcentaje_ideal_ralenti,
                kd.porcentaje_exceso_ralenti2,
                kd.periodo_desde
              FROM komtrax_data kd
              INNER JOIN ralenti_ideal ri 
                ON kd.ralenti_id = ri.id
              WHERE 1=1";

// 4. Aplicar filtros dinámicos
if (!empty($selectedMachines)) {
    $machineList = "'" . implode("','", $selectedMachines) . "'";
    $dataQuery .= " AND kd.numero_maquina_cliente IN ($machineList)";
}

if ($fechaDesde && $fechaHasta) {
    $dataQuery .= " AND kd.periodo_desde BETWEEN '$fechaDesde' AND '$fechaHasta'";
}

if (!empty($tipoEquipo)) {
    $tipoEquipoList = implode(',', $tipoEquipo);
    $dataQuery .= " AND kd.ralenti_id IN ($tipoEquipoList)";
}

$dataQuery .= " AND kd.porcentaje_exceso_ralenti2 BETWEEN $ralentiMin AND $ralentiMax";
$dataQuery .= " ORDER BY kd.periodo_desde";

// 5. Ejecutar consulta
$dataResult = mysqli_query($conn, $dataQuery);

if (!$dataResult) {
    die("Error SQL: " . mysqli_error($conn));
}

// 6. Crear archivo Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Estilos
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '#007BFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$cellStyle = [
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

// Encabezados
$headers = [
    'Tipo de Equipo', 'N° Serie', 'Operador', 
    'Horas Motor', 'Horas Trabajo Real', 'Ralentí Real (h)',
    'Ralentí Ideal (h)', '% Ralentí Real', '% Ralentí Ideal', 
    '% Exceso Ralentí', 'Fecha'
];

$sheet->fromArray($headers, NULL, 'A1');
$sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

// Datos
$rowIndex = 2;
while ($row = mysqli_fetch_assoc($dataResult)) {
    $sheet->fromArray([
        $row['tipo_equipo'],
        $row['numero_serie'],
        $row['nombre_operador'] ?? 'Sin operador',
        number_format($row['horas_motor'], 2),
        number_format($row['horas_trabajo_real'], 2),
        number_format($row['ralenti_real'], 2),
        number_format($row['horas_ralenti_ideal'], 2),
        number_format(($row['porcentaje_ralenti_real'] ?? 0) * 100, 2) . '%',
        number_format($row['porcentaje_ideal_ralenti'], 2) . '%',
        number_format(($row['porcentaje_exceso_ralenti2'] ?? 0) * 100, 2) . '%',
        date('d-m-Y', strtotime($row['periodo_desde']))
    ], NULL, "A$rowIndex");

    $sheet->getStyle("A$rowIndex:K$rowIndex")->applyFromArray($cellStyle);
    $rowIndex++;
}

// Ajustar columnas
foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Descargar
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="informe_komtrax_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;