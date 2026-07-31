<?php
// ============================================
// INCLUIR CONFIGURACIÓN PRIMERO - SIN SALIDA HTML
// ============================================
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

$seccion = isset($_GET['seccion']) ? $_GET['seccion'] : 'dashboard';
$mensaje = isset($_GET['mensaje']) ? $_GET['mensaje'] : '';

// ============================================
// PROCESAR CARGA MASIVA (PEGAR DESDE EXCEL)
// ============================================
if ($seccion == 'carga' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['procesar_csv'])) {
    $csv_data = $_POST['csv_data'];
    $lines = explode("\n", $csv_data);
    $id_lote = 'BATCH-' . time() . rand(100, 999);
    $total = 0;
    $errores = 0;
    $duplicados = 0;
    $omitidos = 0;
    $observaciones = [];

    // Saltar encabezados (primera línea)
    array_shift($lines);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $data = str_getcsv($line);
        if (count($data) < 7) {
            $errores++;
            $observaciones[] = "Línea incompleta: " . substr($line, 0, 50);
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

        // Buscar nivel y grado
        $nivel_data = fetchOne("SELECT id FROM niveles WHERE nombre LIKE ?", ["%$nivel%"]);
        $grado_data = fetchOne("SELECT id FROM grados WHERE nombre LIKE ?", ["%$grado%"]);

        if (!$nivel_data || !$grado_data) {
            $errores++;
            $observaciones[] = "Nivel o grado no encontrado: $nivel - $grado";
            continue;
        }

        // Verificar duplicado
        $existe = fetchOne("SELECT id FROM alumnos_antiguos WHERE dni = ? OR codigo_alumno = ?", [$dni, $codigo]);
        if ($existe) {
            $duplicados++;
            $omitidos++;
            $observaciones[] = "Duplicado: $dni - $nombres";
            continue;
        }

        // Insertar alumno
        insert("INSERT INTO alumnos_antiguos 
                (dni, codigo_alumno, apellido_paterno, apellido_materno, nombres, 
                 id_nivel, id_grado, deuda, conducta, id_lote, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')",
                [$dni, $codigo, $apellido_paterno, $apellido_materno, $nombres, 
                 $nivel_data['id'], $grado_data['id'], $deuda, $conducta, $id_lote]);
        $total++;
    }

    // Registrar lote
    insert("INSERT INTO lotes_carga 
            (id_lote, archivo, operador, total_alumnos, alumnos_con_error, duplicados, omitidos, observaciones) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id_lote, 'Pegado desde Excel', $_SESSION['user_nombre'] ?? 'Admin', $total, $errores, $duplicados, $omitidos, json_encode($observaciones)]);

    registrarAuditoria('alumnos_antiguos', 'carga_masiva', 'lote', 0, "Carga masiva de alumnos: $total registros, lote $id_lote");
    
    $mensaje = "✅ Carga completada: $total registros, $errores errores, $duplicados duplicados";
    
    // Redirigir con mensaje
    header('Location: alumnos_antiguos.php?seccion=carga&mensaje=' . urlencode($mensaje));
    exit;
}

// ============================================
// PROCESAR APROBAR SOLICITUD DE RATIFICACIÓN
// ============================================
if (isset($_GET['aprobar_ratificacion']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    query("UPDATE solicitudes_ratificacion SET estado = 'Aprobado' WHERE id = ?", [$id]);
    $solicitud = fetchOne("SELECT id_alumno FROM solicitudes_ratificacion WHERE id = ?", [$id]);
    if ($solicitud) {
        query("UPDATE alumnos_antiguos SET decision_2027 = 'Ratificado' WHERE id = ?", [$solicitud['id_alumno']]);
    }
    registrarAuditoria('ratificaciones', 'aprobar', 'solicitud', $id, "Solicitud de ratificación aprobada ID $id");
    header('Location: alumnos_antiguos.php?seccion=ratificaciones');
    exit;
}

if (isset($_GET['rechazar_ratificacion']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    query("UPDATE solicitudes_ratificacion SET estado = 'Rechazado' WHERE id = ?", [$id]);
    registrarAuditoria('ratificaciones', 'rechazar', 'solicitud', $id, "Solicitud de ratificación rechazada ID $id");
    header('Location: alumnos_antiguos.php?seccion=ratificaciones');
    exit;
}

// ============================================
// ESTADÍSTICAS
// ============================================
$stats = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM alumnos_antiguos) as total,
        (SELECT COUNT(*) FROM alumnos_antiguos WHERE decision_2027 = 'Ratificado') as ratificados,
        (SELECT COUNT(*) FROM alumnos_antiguos WHERE decision_2027 = 'Pendiente' OR decision_2027 IS NULL) as pendientes,
        (SELECT COUNT(*) FROM alumnos_antiguos WHERE validacion_administrativa = 'En revisión') as en_revision,
        (SELECT COUNT(*) FROM alumnos_antiguos WHERE validacion_administrativa = 'Revision manual') as revisiones_manuales
");

// ============================================
// OBTENER ALUMNOS
// ============================================
$alumnos = fetchAll("
    SELECT a.*, 
        n.nombre as nivel_nombre, 
        g.nombre as grado_nombre, 
        s.nombre as sede_nombre,
        u.nombres as padre_nombre,
        u.apellidos as padre_apellidos,
        u.telefono as padre_telefono
    FROM alumnos_antiguos a
    LEFT JOIN niveles n ON a.id_nivel = n.id
    LEFT JOIN grados g ON a.id_grado = g.id
    LEFT JOIN sedes s ON a.id_sede = s.id
    LEFT JOIN usuarios u ON a.id_usuario_padre = u.id
    ORDER BY a.id DESC
");

// ============================================
// OBTENER SOLICITUDES DE RATIFICACIÓN
// ============================================
$solicitudes_ratificacion = fetchAll("
    SELECT sr.*, a.nombres, a.apellido_paterno, a.apellido_materno, a.dni, a.codigo_alumno
    FROM solicitudes_ratificacion sr
    JOIN alumnos_antiguos a ON sr.id_alumno = a.id
    ORDER BY sr.fecha_solicitud DESC
");

// ============================================
// OBTENER REVISIONES MANUALES
// ============================================
$revisiones_manuales = fetchAll("
    SELECT rm.*, a.nombres, a.apellido_paterno, a.dni
    FROM revisiones_manuales rm
    LEFT JOIN alumnos_antiguos a ON rm.id_alumno = a.id
    ORDER BY rm.fecha_registro DESC
");

// ============================================
// OBTENER LOTES DE CARGA
// ============================================
$lotes = fetchAll("SELECT * FROM lotes_carga ORDER BY fecha_registro DESC LIMIT 10");

// ============================================
// FUNCIÓN PARA OBTENER COLOR DE CONDUCTA
// ============================================
function getConductaColor($conducta) {
    if (strpos($conducta, 'A - Excelente') !== false) return 'success';
    if (strpos($conducta, 'B - Regular') !== false) return 'warning';
    if (strpos($conducta, 'C - Observado') !== false) return 'danger';
    return 'secondary';
}
?>

<!-- ========================================== -->
<!-- TABS DE NAVEGACIÓN -->
<!-- ========================================== -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $seccion == 'dashboard' ? 'active' : ''; ?>" href="alumnos_antiguos.php?seccion=dashboard">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $seccion == 'lista' ? 'active' : ''; ?>" href="alumnos_antiguos.php?seccion=lista">
            <i class="bi bi-list"></i> Alumnos Antiguos 2026
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $seccion == 'carga' ? 'active' : ''; ?>" href="alumnos_antiguos.php?seccion=carga">
            <i class="bi bi-upload"></i> Carga Masiva (Excel/CSV)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $seccion == 'ratificaciones' ? 'active' : ''; ?>" href="alumnos_antiguos.php?seccion=ratificaciones">
            <i class="bi bi-check-circle"></i> Solicitudes de Ratificación
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $seccion == 'revisiones' ? 'active' : ''; ?>" href="alumnos_antiguos.php?seccion=revisiones">
            <i class="bi bi-tools"></i> Revisiones Manuales
        </a>
    </li>
</ul>

<!-- ========================================== -->
<!-- SECCIÓN: DASHBOARD -->
<!-- ========================================== -->
<?php if ($seccion == 'dashboard'): ?>

<h4><i class="bi bi-clock-history"></i> Base Alumnos 2026</h4>
<p class="text-muted">Gestión de alumnos antiguos para el proceso de ratificación 2027</p>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card bg-primary-dark">
            <div><div class="number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="label">Cargados en el sistema</div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-success-dark">
            <div><div class="number"><?php echo $stats['ratificados'] ?? 0; ?></div>
            <div class="label">Fichas Ratificadas</div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-warning-dark">
            <div><div class="number"><?php echo $stats['pendientes'] ?? 0; ?></div>
            <div class="label">Pendientes</div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-danger-dark">
            <div><div class="number"><?php echo $stats['en_revision'] ?? 0; ?></div>
            <div class="label">En Revisión Administrativa</div></div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card bg-info-dark">
            <div><div class="number"><?php echo $stats['revisiones_manuales'] ?? 0; ?></div>
            <div class="label">Revisiones Manuales</div></div>
        </div>
    </div>
</div>

<div class="card-dashboard">
    <h6 class="text-primary-dark"><i class="bi bi-list"></i> Resumen de Alumnos</h6>
    <hr>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>DNI / Código</th>
                    <th>Estudiante</th>
                    <th>Situación 2026</th>
                    <th>Propuesta 2027</th>
                    <th>Validación</th>
                    <th>Decisión 2027</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alumnos)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No hay alumnos cargados</td></tr>
                <?php else: ?>
                    <?php foreach ($alumnos as $a): ?>
                    <tr>
                        <td>
                            <strong><?php echo $a['dni']; ?></strong><br>
                            <small class="text-muted">Cód: <?php echo $a['codigo_alumno']; ?></small>
                        </td>
                        <td>
                            <strong><?php echo $a['apellido_paterno'] . ' ' . $a['apellido_materno'] . ', ' . $a['nombres']; ?></strong><br>
                            <small class="text-muted"><?php echo $a['sede_nombre'] ?? 'Sin sede'; ?> - <?php echo $a['grado_nombre'] ?? 'Sin grado'; ?></small>
                        </td>
                        <td><span class="badge bg-<?php echo $a['situacion_2026'] == 'Habilitado regular' ? 'success' : 'warning'; ?>"><?php echo $a['situacion_2026']; ?></span></td>
                        <td><?php echo $a['propuesta_promocion_2027'] ?? '-'; ?></td>
                        <td><span class="badge bg-<?php echo $a['validacion_administrativa'] == 'Aprobado' ? 'success' : ($a['validacion_administrativa'] == 'En revisión' ? 'warning' : 'secondary'); ?>"><?php echo $a['validacion_administrativa']; ?></span></td>
                        <td><span class="badge bg-<?php echo $a['decision_2027'] == 'Ratificado' ? 'success' : 'warning'; ?>"><?php echo $a['decision_2027']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ========================================== -->
<!-- SECCIÓN: LISTA DE ALUMNOS -->
<!-- ========================================== -->
<?php if ($seccion == 'lista'): ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-list"></i> Alumnos Antiguos 2026</h4>
    <div>
        <span class="badge bg-primary">Total: <?php echo count($alumnos); ?></span>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<div class="card-dashboard">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>DNI / Código</th>
                    <th>Estudiante</th>
                    <th>Apoderado</th>
                    <th>Local 2026</th>
                    <th>Grado 2026</th>
                    <th>Deuda</th>
                    <th>Conducta</th>
                    <th>Propuesta 2027</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alumnos)): ?>
                    <tr><td colspan="9" class="text-center text-muted">No hay alumnos cargados</td></tr>
                <?php else: ?>
                    <?php foreach ($alumnos as $a): ?>
                    <tr>
                        <td>
                            <strong><?php echo $a['dni']; ?></strong><br>
                            <small class="text-muted">Cód: <?php echo $a['codigo_alumno']; ?></small><br>
                            <small class="text-muted">Fam: <?php echo $a['id_usuario_padre'] ? 'FAM-' . str_pad($a['id_usuario_padre'], 3, '0', STR_PAD_LEFT) : 'N/A'; ?></small>
                        </td>
                        <td>
                            <strong><?php echo $a['apellido_paterno'] . ' ' . $a['apellido_materno'] . ', ' . $a['nombres']; ?></strong>
                        </td>
                        <td>
                            <?php if ($a['padre_nombre']): ?>
                                <strong><?php echo $a['padre_nombre'] . ' ' . $a['padre_apellidos']; ?></strong><br>
                                <small class="text-muted">Tel: <?php echo $a['padre_telefono']; ?></small>
                            <?php else: ?>
                                <span class="text-muted">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $a['sede_nombre'] ?? '-'; ?><br><small class="text-muted"><?php echo $a['nivel_nombre'] ?? ''; ?></small></td>
                        <td><?php echo $a['grado_nombre'] ?? '-'; ?></td>
                        <td><span class="badge bg-<?php echo $a['deuda'] == 'Al día' ? 'success' : 'danger'; ?>"><?php echo $a['deuda']; ?></span></td>
                        <td><span class="badge bg-<?php echo getConductaColor($a['conducta']); ?>"><?php echo $a['conducta']; ?></span></td>
                        <td>
                            <span class="badge bg-<?php echo $a['propuesta_promocion_2027'] ? 'success' : 'secondary'; ?>">
                                <?php echo $a['propuesta_promocion_2027'] ?? 'Pendiente'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" title="Gestionar Restricciones"><i class="bi bi-shield"></i></button>
                            <button class="btn btn-sm btn-outline-warning" title="Rectificar Grado 2027"><i class="bi bi-arrow-repeat"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ========================================== -->
<!-- SECCIÓN: CARGA MASIVA -->
<!-- ========================================== -->
<?php if ($seccion == 'carga'): ?>

<h4><i class="bi bi-upload"></i> Carga Masiva (Excel/CSV)</h4>
<p class="text-muted">Descargue la plantilla, complete los datos y súbala al sistema para inicializar las ratificaciones 2027.</p>

<?php if ($mensaje): ?>
    <div class="alert alert-<?php echo strpos($mensaje, '✅') !== false ? 'success' : 'danger'; ?>">
        <?php echo $mensaje; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card-dashboard text-center">
            <i class="bi bi-file-earmark-excel" style="font-size: 60px; color: #1a3a6b;"></i>
            <h6 class="mt-3 text-primary-dark">📄 Descargar Plantilla</h6>
            <p class="text-muted">Descarga la plantilla Excel/CSV con el formato correcto</p>
            <a href="descargar_plantilla.php" class="btn btn-success w-100">
                <i class="bi bi-download"></i> Descargar Plantilla
            </a>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-upload"></i> Subir Archivo CSV</h6>
            <p class="text-muted">Selecciona un archivo CSV con los datos de los alumnos antiguos</p>
            <hr>
            <form method="POST" enctype="multipart/form-data" action="subir_csv.php">
                <div class="mb-3">
                    <label class="form-label">Seleccionar archivo CSV</label>
                    <input type="file" name="archivo_csv" class="form-control" accept=".csv,.txt" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Subir y Procesar</button>
            </form>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-file-spreadsheet"></i> O pegar filas directamente desde Excel</h6>
            <p class="text-muted"><small>Debe incluir encabezados: DNI_Alumno, Codigo_Alumno, Apellido_Paterno, Apellido_Materno, Nombres, Nivel, Grado, Deuda, Conducta</small></p>
            <hr>
            <form method="POST">
                <input type="hidden" name="procesar_csv" value="1">
                <div class="mb-3">
                    <textarea name="csv_data" class="form-control" rows="6" placeholder="DNI_Alumno, Codigo_Alumno, Apellido_Paterno, Apellido_Materno, Nombres, Nivel, Grado, Deuda, Conducta&#10;73019283, ALU2026001, Quispe, Ramos, Mateo, Inicial, Inicial 4 años, Al día, A - Excelente"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Analizar y Procesar</button>
            </form>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-clock-history"></i> Control y Trazabilidad de Cargas (Auditoría)</h6>
            <hr>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID Lote</th>
                            <th>Archivo / Fecha</th>
                            <th>Operador</th>
                            <th>Alumnos con errores / Duplicados</th>
                            <th>Cargados</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lotes)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No hay lotes de carga registrados</td></tr>
                        <?php else: ?>
                            <?php foreach ($lotes as $l): ?>
                            <tr>
                                <td><code><?php echo $l['id_lote']; ?></code></td>
                                <td>
                                    <?php echo $l['archivo']; ?><br>
                                    <small class="text-muted"><?php echo date('d/m/Y, H:i:s', strtotime($l['fecha_registro'])); ?></small>
                                </td>
                                <td><?php echo $l['operador']; ?></td>
                                <td>
                                    <span class="badge bg-danger"><?php echo $l['alumnos_con_error']; ?> alumnos</span>
                                    <span class="badge bg-warning"><?php echo $l['duplicados']; ?> casos</span>
                                    <span class="badge bg-secondary"><?php echo $l['omitidos']; ?> omitidos</span>
                                </td>
                                <td><span class="badge bg-success"><?php echo $l['total_alumnos']; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#observacionesModal" 
                                            data-observaciones="<?php echo htmlspecialchars($l['observaciones']); ?>">
                                        <i class="bi bi-eye"></i> Ver
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Observaciones -->
<div class="modal fade" id="observacionesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Observaciones del Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="observacionesContent" style="white-space: pre-wrap; font-size: 14px;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('observacionesModal');
    modal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const observaciones = button.getAttribute('data-observaciones');
        try {
            const data = JSON.parse(observaciones);
            document.getElementById('observacionesContent').textContent = data.join('\n');
        } catch {
            document.getElementById('observacionesContent').textContent = observaciones || 'Sin observaciones';
        }
    });
});
</script>

<?php endif; ?>

<!-- ========================================== -->
<!-- SECCIÓN: SOLICITUDES DE RATIFICACIÓN -->
<!-- ========================================== -->
<?php if ($seccion == 'ratificaciones'): ?>

<h4><i class="bi bi-check-circle"></i> Solicitudes de Ratificación de Alumnos Antiguos 2027</h4>
<p class="text-muted">Revisa y aprueba las solicitudes de ratificación enviadas por los padres</p>

<div class="card-dashboard">
    <?php if (empty($solicitudes_ratificacion)): ?>
        <div class="text-center py-4">
            <i class="bi bi-inbox" style="font-size: 50px; color: #dee2e6;"></i>
            <h5 class="text-muted mt-3">No se registran solicitudes de ratificación enviadas por los padres hasta el momento.</h5>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Estudiante</th>
                        <th>DNI</th>
                        <th>Código</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_ratificacion as $s): ?>
                    <tr>
                        <td><?php echo $s['id']; ?></td>
                        <td><?php echo $s['apellido_paterno'] . ' ' . $s['apellido_materno'] . ', ' . $s['nombres']; ?></td>
                        <td><?php echo $s['dni']; ?></td>
                        <td><code><?php echo $s['codigo_alumno']; ?></code></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($s['fecha_solicitud'])); ?></td>
                        <td><span class="badge bg-<?php echo $s['estado'] == 'Aprobado' ? 'success' : ($s['estado'] == 'Rechazado' ? 'danger' : 'warning'); ?>"><?php echo $s['estado']; ?></span></td>
                        <td>
                            <?php if ($s['estado'] == 'Pendiente'): ?>
                                <a href="alumnos_antiguos.php?seccion=ratificaciones&aprobar_ratificacion=1&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Aprobar esta solicitud?')"><i class="bi bi-check"></i></a>
                                <a href="alumnos_antiguos.php?seccion=ratificaciones&rechazar_ratificacion=1&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Rechazar esta solicitud?')"><i class="bi bi-x"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- ========================================== -->
<!-- SECCIÓN: REVISIONES MANUALES -->
<!-- ========================================== -->
<?php if ($seccion == 'revisiones'): ?>

<h4><i class="bi bi-tools"></i> Solicitudes de Revisión de Alumnos Antiguos No Encontrados</h4>
<p class="text-muted">Gestiona las solicitudes de revisión manual de alumnos que no fueron encontrados en la base de datos</p>

<div class="card-dashboard">
    <?php if (empty($revisiones_manuales)): ?>
        <div class="text-center py-4">
            <i class="bi bi-check-circle" style="font-size: 50px; color: #2e7d32;"></i>
            <h5 class="text-muted mt-3">No se registran solicitudes de revisión manual pendientes.</h5>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID / Registro</th>
                        <th>Estudiante Solicitado</th>
                        <th>Contacto Apoderado</th>
                        <th>Explicación / Justificación</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($revisiones_manuales as $r): ?>
                    <tr>
                        <td><?php echo $r['id']; ?></td>
                        <td>
                            <strong><?php echo $r['nombres_solicitado']; ?></strong><br>
                            <small class="text-muted">DNI: <?php echo $r['dni_solicitado']; ?></small>
                        </td>
                        <td><?php echo $r['contacto_apoderado']; ?></td>
                        <td><?php echo substr($r['explicacion'], 0, 100) . '...'; ?></td>
                        <td><span class="badge bg-<?php echo $r['estado'] == 'Aprobado' ? 'success' : ($r['estado'] == 'Rechazado' ? 'danger' : 'warning'); ?>"><?php echo $r['estado']; ?></span></td>
                        <td>
                            <?php if ($r['estado'] == 'Pendiente'): ?>
                                <a href="alumnos_antiguos.php?seccion=revisiones&aprobar_revision=1&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-success"><i class="bi bi-check"></i></a>
                                <a href="alumnos_antiguos.php?seccion=revisiones&rechazar_revision=1&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-danger"><i class="bi bi-x"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include 'footer.php'; ?>