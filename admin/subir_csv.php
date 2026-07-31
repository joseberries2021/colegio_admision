<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];
    
    if ($archivo['error'] == 0) {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, ['csv', 'txt'])) {
            $id_lote = 'BATCH-' . time() . rand(100, 999);
            $total = 0;
            $errores = 0;
            $duplicados = 0;
            $omitidos = 0;
            $observaciones = [];

            $handle = fopen($archivo['tmp_name'], 'r');
            
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) < 7) {
                    $errores++;
                    $observaciones[] = "Línea incompleta: " . substr(implode(',', $data), 0, 50);
                    continue;
                }

                $dni = trim($data[0]);
                $codigo = trim($data[1]);
                $apellido_paterno = trim($data[2]);
                $apellido_materno = trim($data[3]);
                $nombres = trim($data[4]);
                $nivel = trim($data[5]);
                $grado = trim($data[6]);
                $deuda = trim($data[7] ?? 'Al día');
                $conducta = trim($data[8] ?? 'A - Excelente');

                $nivel_data = fetchOne("SELECT id FROM niveles WHERE nombre LIKE ?", ["%$nivel%"]);
                $grado_data = fetchOne("SELECT id FROM grados WHERE nombre LIKE ?", ["%$grado%"]);

                if (!$nivel_data || !$grado_data) {
                    $errores++;
                    $observaciones[] = "Nivel o grado no encontrado: $nivel - $grado";
                    continue;
                }

                $existe = fetchOne("SELECT id FROM alumnos_antiguos WHERE dni = ? OR codigo_alumno = ?", [$dni, $codigo]);
                if ($existe) {
                    $duplicados++;
                    $omitidos++;
                    $observaciones[] = "Duplicado: $dni - $nombres";
                    continue;
                }

                insert("INSERT INTO alumnos_antiguos 
                        (dni, codigo_alumno, apellido_paterno, apellido_materno, nombres, 
                         id_nivel, id_grado, deuda, conducta, id_lote, estado) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')",
                        [$dni, $codigo, $apellido_paterno, $apellido_materno, $nombres, 
                         $nivel_data['id'], $grado_data['id'], $deuda, $conducta, $id_lote]);
                $total++;
            }

            fclose($handle);

            insert("INSERT INTO lotes_carga 
                    (id_lote, archivo, operador, total_alumnos, alumnos_con_error, duplicados, omitidos, observaciones) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$id_lote, $archivo['name'], $_SESSION['user_nombre'] ?? 'Admin', $total, $errores, $duplicados, $omitidos, json_encode($observaciones)]);

            registrarAuditoria('alumnos_antiguos', 'carga_masiva', 'lote', 0, "Carga masiva desde archivo: $total registros, lote $id_lote");
            
            $mensaje = "✅ Carga completada: $total registros, $errores errores, $duplicados duplicados";
        } else {
            $mensaje = "❌ Formato no permitido. Usa archivos CSV o TXT";
        }
    } else {
        $mensaje = "❌ Error al cargar el archivo";
    }
}

header('Location: alumnos_antiguos.php?seccion=carga&mensaje=' . urlencode($mensaje));
exit;
?>