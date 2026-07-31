<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$filename = 'plantilla_alumnos_antiguos_2026.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Agregar BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados
$headers = [
    'DNI_Alumno',
    'Codigo_Alumno',
    'Apellido_Paterno',
    'Apellido_Materno',
    'Nombres',
    'Nivel',
    'Grado',
    'Deuda',
    'Conducta'
];
fputcsv($output, $headers);

// Datos de ejemplo
$ejemplos = [
    ['73019283', 'ALU2026001', 'Quispe', 'Ramos', 'Mateo', 'Inicial', 'Inicial 4 años', 'Al día', 'A - Excelente'],
    ['60192835', 'ALU2026002', 'Quispe', 'Ramos', 'Juan Manuel', 'Primaria', '1ro de Primaria', 'Con deuda', 'B - Regular'],
    ['60192836', 'ALU2026003', 'Quispe', 'Ramos', 'Sofía', 'Primaria', '6to de Primaria', 'Al día', 'C - Observado'],
    ['71234567', 'ALU2026004', 'Paredes', 'Soto', 'Lucía', 'Secundaria', '4to de Secundaria', 'Al día', 'A - Excelente'],
    ['74321098', 'ALU2026005', 'Gómez', 'Vargas', 'Renato', 'Secundaria', '5to de Secundaria', 'Al día', 'B - Regular']
];

foreach ($ejemplos as $ejemplo) {
    fputcsv($output, $ejemplo);
}

fclose($output);
exit;
?>