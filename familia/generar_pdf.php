<?php
session_start();

// Verificar que el registro esté completo
if (!isset($_SESSION['registro_completo']) || !$_SESSION['registro_completo']) {
    die('No hay datos de registro para generar el PDF');
}

require_once '../config/database.php';

// Obtener datos de sesión
$p = $_SESSION['postulante_guardado'] ?? [];
$ap = $p['apoderado'] ?? [];
$post = $p['postulante'] ?? [];
$credenciales = $_SESSION['credenciales'] ?? [];
$usuario = $credenciales['usuario'] ?? 'FAM-0001';

// Obtener datos adicionales
$sede_info = fetchOne("SELECT * FROM sedes WHERE id = ?", [$p['id_sede'] ?? 0]);
$nivel_info = fetchOne("SELECT * FROM niveles WHERE id = ?", [$p['id_nivel'] ?? 0]);
$grado_info = fetchOne("SELECT * FROM grados WHERE id = ?", [$p['id_grado'] ?? 0]);

// Verificar que hay datos
if (empty($post) || empty($ap)) {
    die("No hay datos suficientes para generar el PDF");
}

// ============================================
// CONFIGURAR CABECERAS PARA DESCARGA
// ============================================
// Limpiar cualquier salida previa
if (ob_get_level()) ob_end_clean();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Expediente_Admision_' . $usuario . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// ============================================
// GENERAR CONTENIDO DEL PDF
// ============================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Expediente de Admisión</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            font-size: 11px; 
            padding: 25px 30px; 
            color: #1a1a1a;
            line-height: 1.4;
        }
        .header { 
            text-align: center; 
            border-bottom: 3px solid #1a3a6b; 
            padding-bottom: 10px; 
            margin-bottom: 15px; 
        }
        .header h1 { 
            color: #1a3a6b; 
            font-size: 16px; 
            margin: 0; 
            letter-spacing: 1px;
        }
        .header h2 { 
            font-size: 11px; 
            color: #333; 
            margin: 2px 0; 
            font-weight: normal;
        }
        .header .subtitle { 
            font-size: 9px; 
            color: #666; 
        }
        .header .expediente { 
            font-size: 15px; 
            font-weight: bold; 
            color: #1a3a6b; 
            margin-top: 5px; 
        }
        .section { 
            margin-bottom: 12px; 
        }
        .section-title { 
            background: #1a3a6b; 
            color: white; 
            padding: 3px 10px; 
            font-size: 11px; 
            font-weight: bold; 
            margin-bottom: 6px; 
            border-radius: 2px; 
        }
        .row { 
            display: flex; 
            margin-bottom: 2px; 
            padding: 2px 0; 
            border-bottom: 1px dotted #f0f0f0; 
        }
        .row:last-child { 
            border-bottom: none; 
        }
        .label { 
            width: 180px; 
            font-weight: bold; 
            color: #333; 
            font-size: 10px; 
            flex-shrink: 0; 
        }
        .value { 
            flex: 1; 
            color: #000; 
            font-size: 10px; 
        }
        .two-column { 
            display: flex; 
            gap: 20px; 
        }
        .two-column .col { 
            flex: 1; 
        }
        .badge-pendiente { 
            display: inline-block; 
            background: #f57c00; 
            color: white; 
            padding: 1px 8px; 
            border-radius: 3px; 
            font-size: 9px; 
            font-weight: bold; 
        }
        .badge-nuevo { 
            display: inline-block; 
            background: #1a3a6b; 
            color: white; 
            padding: 1px 8px; 
            border-radius: 3px; 
            font-size: 9px; 
            font-weight: bold; 
        }
        .firma-row { 
            display: flex; 
            justify-content: space-between; 
            margin-top: 12px; 
        }
        .firma-box { 
            text-align: center; 
            width: 45%; 
        }
        .firma-box .linea { 
            border-top: 1px solid #333; 
            width: 100%; 
            margin: 25px 0 5px 0; 
        }
        .firma-box .label-firma { 
            font-size: 10px; 
            font-weight: bold; 
        }
        .firma-box .sub-firma { 
            font-size: 9px; 
            color: #666; 
        }
        .footer { 
            text-align: center; 
            font-size: 9px; 
            color: #666; 
            border-top: 1px solid #ccc; 
            padding-top: 8px; 
            margin-top: 18px; 
        }
        .observacion-box { 
            background: #f8f9fa; 
            padding: 6px 10px; 
            border-radius: 4px; 
            border-left: 3px solid #1a3a6b; 
            font-size: 10px; 
        }
        .nota { 
            font-size: 9px; 
            color: #666; 
            font-style: italic; 
            margin-bottom: 4px; 
        }
        .section-title-small { 
            background: #e8f0fe; 
            color: #1a3a6b; 
            padding: 2px 8px; 
            font-size: 10px; 
            font-weight: bold; 
            margin-bottom: 3px; 
            border-radius: 2px; 
            border-left: 3px solid #1a3a6b; 
        }
        .value-italic {
            font-style: italic;
            color: #888;
        }
        @page { 
            margin: 0.5cm; 
            size: A4;
        }
        @media print {
            body { padding: 15px; }
        }
    </style>
</head>
<body>

    <!-- ========================================== -->
    <!-- ENCABEZADO -->
    <!-- ========================================== -->
    <div class="header">
        <h1>I.E. JUVENTUD CIENTÍFICA</h1>
        <h2>"Ciencia, Disciplina y Valores de Alto Rendimiento"</h2>
        <div class="subtitle">Autorización Ministerial N° 0451-2015-MINEDU</div>
        <div style="margin-top:8px; font-size:15px; font-weight:bold; color:#1a3a6b;">EXPEDIENTE COMPLETO DEL POSTULANTE</div>
        <div class="expediente">EXPEDIENTE: <?php echo $usuario; ?></div>
    </div>

    <!-- ========================================== -->
    <!-- 1. INFORMACIÓN GENERAL DEL PROCESO -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">1. INFORMACIÓN GENERAL DEL PROCESO</div>
        <div class="row"><span class="label">Código de Familia:</span><span class="value"><?php echo $usuario; ?></span></div>
        <div class="row"><span class="label">Año Escolar / Lectivo:</span><span class="value">2027</span></div>
        <div class="row"><span class="label">Estado de la Ficha:</span><span class="value"><span class="badge-pendiente">Pre-Inscrito (Pendiente de Validación)</span></span></div>
        <div class="row"><span class="label">Código del Postulante:</span><span class="value">N/A (Alumno Nuevo)</span></div>
        <div class="row"><span class="label">Fecha de Registro:</span><span class="value"><?php echo date('d/m/Y'); ?></span></div>
        <div class="row"><span class="label">Canal de Captación:</span><span class="value" class="value-italic">No especificado</span></div>
    </div>

    <!-- ========================================== -->
    <!-- 2. DATOS DE LA POSTULACIÓN -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">2. DATOS DE LA POSTULACIÓN</div>
        <div class="row"><span class="label">Nivel Educativo:</span><span class="value"><?php echo $nivel_info['nombre'] ?? 'No especificado'; ?></span></div>
        <div class="row"><span class="label">Sede / Local Elegido:</span><span class="value"><?php echo $sede_info['nombre'] ?? 'No especificado'; ?></span></div>
        <div class="row"><span class="label">Turno de Preferencia:</span><span class="value">Mañana</span></div>
        <div class="row"><span class="label">Grado de Ingreso:</span><span class="value"><?php echo $grado_info['nombre'] ?? 'No especificado'; ?></span></div>
        <div class="row"><span class="label">Distrito de la Sede:</span><span class="value"><?php echo $sede_info['distrito'] ?? 'No especificado'; ?></span></div>
        <div class="row"><span class="label">Tipo de Postulante:</span><span class="value"><span class="badge-nuevo">NUEVO</span></span></div>
    </div>

    <!-- ========================================== -->
    <!-- 3. DATOS PERSONALES DEL POSTULANTE -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">3. DATOS PERSONALES DEL POSTULANTE</div>
        <div class="row"><span class="label">Nombres Completos:</span><span class="value"><?php echo strtoupper($post['nombres'] ?? ''); ?></span></div>
        <div class="row"><span class="label">Apellido Paterno:</span><span class="value"><?php echo strtoupper($post['apellido_paterno'] ?? ''); ?></span></div>
        <div class="row"><span class="label">Apellido Materno:</span><span class="value"><?php echo strtoupper($post['apellido_materno'] ?? ''); ?></span></div>
        <div class="row"><span class="label">Documento Identidad:</span><span class="value"><?php echo ($post['tipo_documento'] ?? 'DNI') . ' - ' . ($post['numero_documento'] ?? ''); ?></span></div>
        <div class="row"><span class="label">Género:</span><span class="value"><?php echo $post['genero'] ?? ''; ?></span></div>
        <div class="row"><span class="label">Fecha Nacimiento:</span><span class="value"><?php echo $post['fecha_nacimiento'] ?? ''; ?></span></div>
        <div class="row"><span class="label">Tipo de Colegio:</span><span class="value"><?php echo ucfirst($post['tipo_colegio'] ?? 'No especificado'); ?></span></div>
        <div class="row"><span class="label">Colegio de Procedencia:</span><span class="value"><?php echo $post['colegio_procedencia'] ?? 'No especificado'; ?></span></div>
        <div class="row"><span class="label">Distrito del Colegio:</span><span class="value"><?php echo $post['distrito_colegio'] ?? 'No especificado'; ?></span></div>
    </div>

    <!-- ========================================== -->
    <!-- 4. LUGAR DE NACIMIENTO & DOMICILIO -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">4. LUGAR DE NACIMIENTO &amp; DOMICILIO</div>
        <div class="two-column">
            <div class="col">
                <div class="row"><span class="label">País de Nacimiento:</span><span class="value" class="value-italic">Perú</span></div>
                <div class="row"><span class="label">Departamento / Región:</span><span class="value" class="value-italic">Lima</span></div>
                <div class="row"><span class="label">Provincia:</span><span class="value" class="value-italic">Lima</span></div>
                <div class="row"><span class="label">Distrito Nacimiento:</span><span class="value" class="value-italic">No especificado</span></div>
                <div class="row"><span class="label">Lugar de Nacimiento:</span><span class="value" class="value-italic">No especificado</span></div>
            </div>
            <div class="col">
                <div class="row"><span class="label">Dirección Domiciliaria:</span><span class="value" class="value-italic">No declarada</span></div>
                <div class="row"><span class="label">Urbanización / Zona:</span><span class="value" class="value-italic">No declarada</span></div>
                <div class="row"><span class="label">Distrito Residencia:</span><span class="value"><?php echo $ap['distrito'] ?? 'No especificado'; ?></span></div>
                <div class="row"><span class="label">Estado Civil Padres:</span><span class="value" class="value-italic">No declarado</span></div>
                <div class="row"><span class="label">Teléfono Fijo / Fam:</span><span class="value" class="value-italic">No registrado</span></div>
                <div class="row"><span class="label">Correo de Contacto:</span><span class="value"><?php echo $ap['email'] ?? 'No especificado'; ?></span></div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 5. INFORMACIÓN COMPLEMENTARIA Y SALUD -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">5. INFORMACIÓN COMPLEMENTARIA Y SALUD DEL ESTUDIANTE</div>
        <div class="two-column">
            <div class="col">
                <div class="row"><span class="label">Cuenta con Seguro:</span><span class="value"><?php echo ($post['seguro'] ?? 0) ? 'Sí' : 'No'; ?></span></div>
                <div class="row"><span class="label">Compañía Aseguradora:</span><span class="value"><?php echo $post['seguro_compania'] ?? 'No especifica'; ?></span></div>
                <div class="row"><span class="label">Diagnóstico Médico/Psiq:</span><span class="value"><?php echo ($post['diagnostico'] ?? 0) ? 'Sí' : 'No'; ?></span></div>
                <div class="row"><span class="label">Detalle de Diagnóstico:</span><span class="value"><?php echo $post['diagnostico_descripcion'] ?? 'No aplica'; ?></span></div>
            </div>
            <div class="col">
                <div class="row"><span class="label">Religión:</span><span class="value"><?php echo $post['religion'] ?? 'No especifica'; ?></span></div>
                <div class="row"><span class="label">Asiste a Iglesia:</span><span class="value"><?php echo ($post['asiste_iglesia'] ?? 0) ? 'Sí' : 'No'; ?></span></div>
                <div class="row"><span class="label">Nombre de Iglesia:</span><span class="value"><?php echo $post['iglesia_nombre'] ?? 'No especifica'; ?></span></div>
                <div class="row"><span class="label">Bautizado:</span><span class="value"><?php echo ($post['bautizado'] ?? 0) ? 'Sí (Declarado)' : 'No'; ?></span></div>
                <div class="row"><span class="label">1ra Comunión:</span><span class="value"><?php echo ($post['primera_comunion'] ?? 0) ? 'Sí (Declarado)' : 'No'; ?></span></div>
                <div class="row"><span class="label">Con quién vive el menor:</span><span class="value" class="value-italic">Padres</span></div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 6. DATOS DEL APODERADO LEGAL -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">6. DATOS DEL APODERADO LEGAL (RESPONSABLE DE MATRÍCULA)</div>
        <div class="nota">Nota: El apoderado legal asume toda responsabilidad civil, económica y académica ante la institución.</div>
        <div class="two-column">
            <div class="col">
                <div class="row"><span class="label">Nombres del Apoderado:</span><span class="value"><?php echo strtoupper($ap['nombres'] ?? ''); ?></span></div>
                <div class="row"><span class="label">Apellido Paterno:</span><span class="value"><?php echo strtoupper($ap['apellido_paterno'] ?? ''); ?></span></div>
                <div class="row"><span class="label">Apellido Materno:</span><span class="value"><?php echo strtoupper($ap['apellido_materno'] ?? ''); ?></span></div>
                <div class="row"><span class="label">Documento Identidad:</span><span class="value"><?php echo ($ap['tipo_documento'] ?? 'DNI') . ': ' . ($ap['numero_documento'] ?? ''); ?></span></div>
                <div class="row"><span class="label">Parentesco / Relación:</span><span class="value"><?php echo $ap['relacion'] ?? 'No especificado'; ?></span></div>
                <div class="row"><span class="label">Fecha de Nacimiento:</span><span class="value" class="value-italic">No registrada</span></div>
            </div>
            <div class="col">
                <div class="row"><span class="label">WhatsApp:</span><span class="value"><?php echo $ap['whatsapp'] ?? ''; ?></span></div>
                <div class="row"><span class="label">Correo Electrónico:</span><span class="value"><?php echo $ap['email'] ?? ''; ?></span></div>
                <div class="row"><span class="label">País de Origen:</span><span class="value"><?php echo $ap['pais'] ?? 'Perú'; ?></span></div>
                <div class="row"><span class="label">Departamento / Región:</span><span class="value"><?php echo strtoupper($ap['departamento'] ?? ''); ?></span></div>
                <div class="row"><span class="label">Provincia:</span><span class="value"><?php echo strtoupper($ap['provincia'] ?? ''); ?></span></div>
                <div class="row"><span class="label">Distrito Residencia:</span><span class="value"><?php echo strtoupper($ap['distrito'] ?? ''); ?></span></div>
            </div>
        </div>
        <div style="margin-top:6px;">
            <div class="row"><span class="label">Grado de Instrucción:</span><span class="value" class="value-italic">Superior Universitaria</span></div>
            <div class="row"><span class="label">Profesión / Ocupación:</span><span class="value" class="value-italic">No declarada</span></div>
            <div class="row"><span class="label">Centro de Trabajo:</span><span class="value" class="value-italic">No declarado</span></div>
            <div class="row"><span class="label">Cargo que Desempeña:</span><span class="value" class="value-italic">No declarado</span></div>
            <div class="row"><span class="label">Horario Laboral:</span><span class="value" class="value-italic">No declarado</span></div>
            <div class="row"><span class="label">Ingresos Mensuales Prom.:</span><span class="value" class="value-italic">No declarados</span></div>
            <div class="row"><span class="label">Fallecido:</span><span class="value">No</span></div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 7. PADRES BIOLÓGICOS -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">7. INFORMACIÓN DE LOS PADRES BIOLÓGICOS</div>
        <div class="two-column">
            <div class="col">
                <div class="section-title-small">DATOS DE LA MADRE (MAMÁ)</div>
                <div class="row"><span class="label">Nombre:</span><span class="value" class="value-italic">No especificado</span></div>
                <div class="row"><span class="label">Documento:</span><span class="value" class="value-italic">DNI No declarado</span></div>
                <div class="row"><span class="label">Celular:</span><span class="value" class="value-italic">No registrado</span></div>
                <div class="row"><span class="label">Correo:</span><span class="value" class="value-italic">No registrado</span></div>
                <div class="row"><span class="label">Profesión:</span><span class="value" class="value-italic">No declarada</span></div>
                <div class="row"><span class="label">Estado:</span><span class="value">Con Vida</span></div>
            </div>
            <div class="col">
                <div class="section-title-small">DATOS DEL PADRE (PAPÁ)</div>
                <div class="row"><span class="label">Nombre:</span><span class="value" class="value-italic">No especificado</span></div>
                <div class="row"><span class="label">Documento:</span><span class="value" class="value-italic">DNI No declarado</span></div>
                <div class="row"><span class="label">Celular:</span><span class="value" class="value-italic">No registrado</span></div>
                <div class="row"><span class="label">Correo:</span><span class="value" class="value-italic">No registrado</span></div>
                <div class="row"><span class="label">Profesión:</span><span class="value" class="value-italic">No declarada</span></div>
                <div class="row"><span class="label">Estado:</span><span class="value">Con Vida</span></div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 8. DOCUMENTOS -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">8. ESTADO DE REQUISITOS Y CARGA DE DOCUMENTOS</div>
        <div class="row"><span class="label">DNI del Postulante:</span><span class="value"><span class="badge-pendiente">No presentado</span></span></div>
        <div class="row"><span class="label">Libreta de Notas:</span><span class="value"><span class="badge-pendiente">No presentado</span></span></div>
        <div class="row"><span class="label">Revisión Documentaria:</span><span class="value" class="value-italic">Documentación Completamente Verificada y Aprobada</span></div>
    </div>

    <!-- ========================================== -->
    <!-- 9. PAGOS Y EVALUACIONES -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">9. ESTADO DEL PAGO &amp; CONTROL DE EVALUACIONES</div>
        <div class="row"><span class="label">Estado del Pago:</span><span class="value"><span class="badge-pendiente">Pendiente de Pago</span></span></div>
        <div class="row"><span class="label">Código de Pago:</span><span class="value" class="value-italic">Pendiente / No registrado</span></div>
        <div class="row"><span class="label">Cita Psicopedagógica:</span><span class="value" class="value-italic">Pendiente de Reserva / Programación</span></div>
        <div class="row"><span class="label">Evaluación Académica:</span><span class="value" class="value-italic">No corresponde</span></div>
        <div class="row"><span class="label">Estado Final:</span><span class="value"><span class="badge-pendiente">PRE-INSCRITO (PENDIENTE DE VALIDACIÓN)</span></span></div>
    </div>

    <!-- ========================================== -->
    <!-- 10. OBSERVACIONES -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">10. OBSERVACIONES DEL EXPEDIENTE</div>
        <div class="observacion-box">
            <strong>EXPEDIENTE SIN OBSERVACIONES / CONFORMIDAD:</strong> No se registran penalidades u observaciones administrativas en la postulación. El expediente fluye con normalidad a través de los pasos regulares del proceso de matrícula e inducción psicopedagógica. Cualquier actualización será enviada directamente al correo electrónico de contacto registrado.
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 11. DECLARACIÓN JURADA -->
    <!-- ========================================== -->
    <div class="section">
        <div class="section-title">11. DECLARACIÓN JURADA DE VERACIDAD DE LA INFORMACIÓN</div>
        <p style="font-size:10px; text-align:justify;">El apoderado firmante declara bajo juramento que todos los datos consignados en esta ficha oficial de admisión y expediente completo son rigurosamente verdaderos y se ajustan a la realidad, asumiendo plena responsabilidad administrativa, civil y legal en caso de falsedad u omisión, conforme a las directivas del reglamento interno de admisión de la Institución Educativa Colegio Juventud Científica.</p>
        <div class="firma-row">
            <div class="firma-box">
                <div class="linea"></div>
                <div class="label-firma" style="font-size:10px; font-weight:bold;">Firma del Apoderado Legal</div>
                <div class="sub-firma">DNI: <?php echo $ap['numero_documento'] ?? ''; ?></div>
            </div>
            <div class="firma-box">
                <div class="linea"></div>
                <div class="label-firma" style="font-size:10px; font-weight:bold;">Comisión de Admisión</div>
                <div class="sub-firma">Juventud Científica</div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- FOOTER -->
    <!-- ========================================== -->
    <div class="footer">
        <p>Fecha y hora de generación oficial: Lima, <?php echo date('d \d\e F \d\e Y \a \l\a\s H:i a'); ?></p>
        <p>Este documento es un comprobante de inscripción oficial. Guarde esta copia para sus registros.</p>
    </div>

</body>
</html>