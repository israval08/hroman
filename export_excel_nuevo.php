<?php
session_start();
require_once 'config/db.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, Color, Conditional};
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// =============================================
// 1. CONSTRUIR CONSULTA CON FILTROS
// =============================================
$baseQuery = "SELECT 
    kd.observaciones, 
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
LEFT JOIN ralenti_ideal ri 
    ON kd.observaciones = ri.tipo_equipo
WHERE 1=1";

$filters = [
    'tipo_equipo' => ['type' => 'list', 'field' => 'kd.observaciones'],
    'operador' => ['type' => 'list', 'field' => 'kd.nombre_operador'],
    'selected_year' => ['type' => 'value', 'field' => 'YEAR(kd.periodo_desde)'],
    'selected_week' => ['type' => 'value', 'field' => 'WEEK(kd.periodo_desde)'],
    'specific_date' => ['type' => 'date', 'field' => 'DATE(kd.periodo_desde)'],
    'fecha_desde' => ['type' => 'range', 'field' => 'kd.periodo_desde', 'start' => 'fecha_desde', 'end' => 'fecha_hasta'],
    'ralenti_min' => ['type' => 'range', 'field' => 'kd.porcentaje_exceso_ralenti2', 'start' => 'ralenti_min', 'end' => 'ralenti_max'],
    'machines' => ['type' => 'list', 'field' => 'kd.numero_maquina_cliente']
];

foreach ($filters as $key => $config) {
    if (!empty($_POST[$key])) {
        switch($config['type']) {
            case 'list':
                $values = array_map([$conn, 'real_escape_string'], (array)$_POST[$key]);
                $baseQuery .= " AND {$config['field']} IN ('" . implode("','", $values) . "')";
                break;
                
            case 'value':
                $value = $conn->real_escape_string($_POST[$key]);
                $baseQuery .= " AND {$config['field']} = '{$value}'";
                break;
                
            case 'date':
                $date = $conn->real_escape_string($_POST[$key]);
                $baseQuery .= " AND {$config['field']} = '{$date}'";
                break;
                
            case 'range':
                $start = $conn->real_escape_string($_POST[$config['start']] ?? '');
                $end = $conn->real_escape_string($_POST[$config['end']] ?? '');
                if ($start && $end) {
                    $baseQuery .= " AND {$config['field']} BETWEEN '{$start}' AND '{$end}'";
                }
                break;
        }
    }
}

$baseQuery .= " ORDER BY kd.periodo_desde";
$result = mysqli_query($conn, $baseQuery);

if (!$result) {
    die("Error en consulta: " . mysqli_error($conn));
}

// =============================================
// 2. CONFIGURACIÓN DE EXCEL
// =============================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Estilos corporativos
$styles = [
    'header' => [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2c3e50']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ],
    'body' => [
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D3D3D3']]]
    ],
    'totals' => [
        'font' => ['bold' => true, 'color' => ['rgb' => '2c3e50']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f8f9fa']],
        'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]
];

// =============================================
// 3. LOGO Y ENCABEZADO
// =============================================
$logoPath = realpath('image/hroman.png');
if (file_exists($logoPath)) {
    $drawing = new Drawing();
    $drawing->setName('Logo')
            ->setPath($logoPath)
            ->setResizeProportional(true)
            ->setWidth(180)
            ->setHeight(55)
            ->setCoordinates('A1')
            ->setOffsetX(15)
            ->setWorksheet($sheet);
}

$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getRowDimension(1)->setRowHeight(60);

$sheet->mergeCells('B1:K1');
$sheet->setCellValue('B1', 'INFORME KOMTRAX - HROMAN')
      ->getStyle('B1')->applyFromArray([
          'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '2c3e50']],
          'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
      ]);

// =============================================
// 4. ENCABEZADOS
// =============================================
$headers = [
    'Tipo Equipo', 
    'Patente/N° Serie', 
    'Operador', 
    'Horas Motor (h)', 
    'Trabajo Real (h)', 
    'Ralentí Real (h)', 
    'Horas Ideal (h)', 
    '% R. Real', 
    '% R. Ideal', 
    '% Exceso', 
    'Fecha'
];

$sheet->fromArray($headers, null, 'A3');
$sheet->getStyle('A3:K3')->applyFromArray($styles['header']);

// =============================================
// 5. LLENADO DE DATOS CON FORMATO
// =============================================
$row = 4;
$totals = [
    'horas_motor' => 0,
    'trabajo_real' => 0,
    'ralenti_real' => 0,
    'horas_ideal' => 0,
    'porc_real' => 0,
    'porc_exceso' => 0
];

while ($data = mysqli_fetch_assoc($result)) {
    // Validar y formatear fecha
    $fecha = 'Fecha inválida';
    if (!empty($data['periodo_desde']) && strtotime($data['periodo_desde'])) {
        $fecha = date('d/m/Y', strtotime($data['periodo_desde']));
    }

    // Formatear valores con 1 decimal y manejar nulos
    $porcReal = isset($data['porcentaje_ralenti_real']) ? 
        round((float)$data['porcentaje_ralenti_real'] * 100, 1) . '%' : '0.0%';
    
    $porcIdeal = isset($data['porcentaje_ideal_ralenti']) ? 
        round((float)$data['porcentaje_ideal_ralenti'], 1) . '%' : '0.0%';  // Valor fijo
    
    $porcExceso = isset($data['porcentaje_exceso_ralenti2']) ? 
        round((float)$data['porcentaje_exceso_ralenti2'], 1) . '%' : '0.0%';

    // Insertar datos
    $sheet->fromArray([
        $data['observaciones'],
        $data['numero_serie'],
        $data['nombre_operador'] ?? 'Sin Operador',
        number_format($data['horas_motor'], 1),
        number_format($data['horas_trabajo_real'], 1),
        number_format($data['ralenti_real'], 1),
        number_format($data['horas_ralenti_ideal'], 1),
        $porcReal,
        $porcIdeal,  // Valor fijo
        $porcExceso,
        $fecha
    ], null, "A{$row}");

    // Formato condicional CORREGIDO
    $conditional = new Conditional();
    $conditional->setConditionType(Conditional::CONDITION_CELLIS)
                ->setOperatorType(Conditional::OPERATOR_GREATERTHANOREQUAL)
                ->addCondition('INDIRECT("I'.$row.'")')
                ->getStyle()->applyFromArray([
                    'font' => ['color' => ['rgb' => 'FF0000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEEEE']]
                ]);
    
    $sheet->getStyle("H{$row}")->setConditionalStyles([$conditional]);

    // Acumular totales (excluyendo % Ideal)
    $totals['horas_motor'] += (float)$data['horas_motor'];
    $totals['trabajo_real'] += (float)$data['horas_trabajo_real'];
    $totals['ralenti_real'] += (float)$data['ralenti_real'];
    $totals['horas_ideal'] += (float)$data['horas_ralenti_ideal'];
    $totals['porc_real'] += (float)str_replace('%', '', $porcReal);
    $totals['porc_exceso'] += (float)str_replace('%', '', $porcExceso);
    
    $row++;
}

// =============================================
// 6. SECCIÓN DE TOTALES/PROMEDIOS
// =============================================
$totalRow = $row + 1;
$numRegistros = max($row - 4, 1);

// Encabezado de totales
$sheet->mergeCells("A{$totalRow}:C{$totalRow}");
$sheet->setCellValue("A{$totalRow}", 'RESUMEN FINAL')
      ->getStyle("A{$totalRow}:K{$totalRow}")->applyFromArray($styles['totals']);

// Cálculos finales
$sheet->setCellValue("D{$totalRow}", number_format($totals['horas_motor'], 1))
      ->setCellValue("E{$totalRow}", number_format($totals['trabajo_real'], 1))
      ->setCellValue("F{$totalRow}", number_format($totals['ralenti_real'] / $numRegistros, 1))
      ->setCellValue("G{$totalRow}", number_format($totals['horas_ideal'], 1))
      ->setCellValue("H{$totalRow}", number_format($totals['porc_real'] / $numRegistros, 1) . '%')
      ->setCellValue("I{$totalRow}", 'N/A')  // % Ideal no se calcula
      ->setCellValue("J{$totalRow}", number_format($totals['porc_exceso'] / $numRegistros, 1) . '%');

// =============================================
// 7. NOTAS Y AJUSTES FINALES
// =============================================
// Leyenda explicativa
$sheet->setCellValue('L3', 'Leyenda:')
      ->setCellValue('L4', 'Rojo = % R. Real ≥ % R. Ideal (Valor fijo por tipo de equipo)')
      ->getStyle('L4')->getFont()->setColor(new Color('FF0000'));

$sheet->getStyle('L3:L4')->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    'font' => ['italic' => true]
]);

// Ajustes de formato
foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getColumnDimension('L')->setWidth(35);

// Centrar todo el contenido
$sheet->getStyle("A4:K{$totalRow}")->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// =============================================
// 8. GENERAR ARCHIVO
// =============================================
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Informe_Komtrax_'.date('Ymd_His').'.xlsx"');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;