<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

$postulante_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$postulante_id) {
    header('Location: postulantes.php');
    exit;
}

$postulante = fetchOne("
    SELECT p.*, 
           u.nombres as padre_nombre, 
           u.apellidos as padre_apellidos, 
           u.dni as padre_dni,
           u.email as padre_email,
           u.telefono as padre_telefono,
           g.nombre as grado, 
           s.nombre as sede, 
           n.nombre as nivel,
           e.nota as nota_eval,
           e.estado as eval_estado,
           e.observaciones as eval_observaciones
    FROM postulantes p
    JOIN usuarios u ON p.id_usuario_padre = u.id
    JOIN grados g ON p.id_grado = g.id
    JOIN sedes s ON p.id_sede = s.id
    JOIN niveles n ON p.id_nivel = n.id
    LEFT JOIN evaluaciones e ON p.id = e.id_postulante
    WHERE p.id = ?
", [$postulante_id]);

if (!$postulante) {
    header('Location: postulantes.php');
    exit;
}

// Obtener documentos del postulante
$documentos = fetchAll("
    SELECT dr.nombre_documento, ds.nombre_archivo, ds.ruta, ds.estado, ds.fecha_subida
    FROM documentos_requeridos dr
    LEFT JOIN documentos_subidos ds ON dr.id = ds.id_documento_requerido AND ds.id_postulante = ?
    WHERE (dr.id_nivel IS NULL OR dr.id_nivel = ?) 
    AND (dr.id_grado IS NULL OR dr.id_grado = ?)
    ORDER BY dr.id
", [$postulante_id, $postulante['id_nivel'], $postulante['id_grado']]);

// Estados disponibles
$estados = [
    'registrado' => 'Registrado',
    'documentos_pendientes' => 'Documentos Pendientes',
    'documentos_revisados' => 'Documentos Revisados',
    'pago_pendiente' => 'Pago Pendiente',
    'pago_verificado' => 'Pago Verificado',
    'cita_pendiente' => 'Cita Pendiente',
    'cita_aprobada' => 'Cita Aprobada',
    'evaluacion_pendiente' => 'Evaluación Pendiente',
    'evaluacion_aprobada' => 'Evaluación Aprobada',
    'matriculado' => 'Matriculado',
    'lista_espera' => 'Lista de Espera'
];

// Obtener código familiar
$padre = fetchOne("SELECT usuario FROM usuarios WHERE id = ?", [$postulante['id_usuario_padre']]);
$codigo_familia = $padre ? $padre['usuario'] : 'N/A';
?>

<h4><i class="bi bi-person-vcard"></i> Detalle del Postulante</h4>
<p class="text-muted">Información completa del postulante y su proceso de admisión</p>

<div class="row">
    <!-- Información del postulante -->
    <div class="col-md-6">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-person"></i> Datos del Postulante</h6>
            <hr>
            <table class="table table-borderless table-sm">
                <tr><td width="140"><strong>Nombres:</strong></td><td><?php echo $postulante['nombres']; ?></td></tr>
                <tr><td><strong>Apellidos:</strong></td><td><?php echo $postulante['apellido_paterno'] . ' ' . $postulante['apellido_materno']; ?></td></tr>
                <tr><td><strong>DNI:</strong></td><td><?php echo $postulante['dni']; ?></td></tr>
                <tr><td><strong>Fecha Nacimiento:</strong></td><td><?php echo date('d/m/Y', strtotime($postulante['fecha_nacimiento'])); ?></td></tr>
                <tr><td><strong>Nivel:</strong></td><td><?php echo $postulante['nivel']; ?></td></tr>
                <tr><td><strong>Grado:</strong></td><td><?php echo $postulante['grado']; ?></td></tr>
                <tr><td><strong>Sede:</strong></td><td><?php echo $postulante['sede']; ?></td></tr>
                <tr><td><strong>Estado Proceso:</strong></td><td>
                    <span class="badge bg-<?php 
                        echo $postulante['estado_proceso'] == 'matriculado' ? 'success' : 
                            ($postulante['estado_proceso'] == 'documentos_pendientes' ? 'warning' : 
                            ($postulante['estado_proceso'] == 'pago_pendiente' ? 'danger' : 
                            ($postulante['estado_proceso'] == 'cita_pendiente' ? 'info' : 'secondary'))); 
                    ?> text-white" style="font-size:14px;">
                        <?php echo $estados[$postulante['estado_proceso']] ?? $postulante['estado_proceso']; ?>
                    </span>
                </td></tr>
                <tr><td><strong>Fecha Registro:</strong></td><td><?php echo date('d/m/Y H:i', strtotime($postulante['fecha_registro'])); ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Información del apoderado -->
    <div class="col-md-6">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-person-badge"></i> Datos del Apoderado</h6>
            <hr>
            <table class="table table-borderless table-sm">
                <tr><td width="140"><strong>Nombres:</strong></td><td><?php echo $postulante['padre_nombre']; ?></td></tr>
                <tr><td><strong>Apellidos:</strong></td><td><?php echo $postulante['padre_apellidos']; ?></td></tr>
                <tr><td><strong>DNI:</strong></td><td><?php echo $postulante['padre_dni']; ?></td></tr>
                <tr><td><strong>Email:</strong></td><td><?php echo $postulante['padre_email']; ?></td></tr>
                <tr><td><strong>Teléfono:</strong></td><td><?php echo $postulante['padre_telefono']; ?></td></tr>
                <tr><td><strong>Código Familiar:</strong></td><td><code><?php echo $codigo_familia; ?></code></td></tr>
            </table>
        </div>
    </div>
</div>

<!-- Información adicional -->
<div class="row mt-3">
    <div class="col-md-6">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-info-circle"></i> Información Adicional</h6>
            <hr>
            <table class="table table-borderless table-sm">
                <tr><td width="180"><strong>Colegio Procedencia:</strong></td><td><?php echo $postulante['colegio_procedencia'] ?? 'No especificado'; ?></td></tr>
                <tr><td><strong>Tipo Colegio:</strong></td><td><?php echo ucfirst($postulante['tipo_colegio'] ?? 'No especificado'); ?></td></tr>
                <tr><td><strong>Religión:</strong></td><td><?php echo ucfirst($postulante['religion'] ?? 'No especificada'); ?></td></tr>
                <tr><td><strong>Iglesia:</strong></td><td><?php echo $postulante['iglesia'] ?? 'No especifica'; ?></td></tr>
                <tr><td><strong>Bautizado:</strong></td><td><?php echo $postulante['bautizado'] ? 'Sí' : 'No'; ?></td></tr>
                <tr><td><strong>Primera Comunión:</strong></td><td><?php echo $postulante['primera_comunion'] ? 'Sí' : 'No'; ?></td></tr>
                <tr><td><strong>Seguro:</strong></td><td><?php echo $postulante['seguro'] ?? 'No especifica'; ?></td></tr>
                <tr><td><strong>Diagnóstico:</strong></td><td><?php echo $postulante['diagnostico'] ?? 'No especifica'; ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Evaluación -->
    <div class="col-md-6">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-pencil"></i> Evaluación Académica</h6>
            <hr>
            <?php if ($postulante['nota_eval'] !== null): ?>
                <table class="table table-borderless table-sm">
                    <tr><td width="140"><strong>Nota:</strong></td><td>
                        <span class="badge bg-<?php echo ($postulante['nota_eval'] ?? 0) >= 11 ? 'success' : 'danger'; ?>" style="font-size:20px;padding:8px 16px;">
                            <?php echo $postulante['nota_eval']; ?>
                        </span>
                    </td></tr>
                    <tr><td><strong>Estado:</strong></td><td>
                        <span class="badge bg-<?php 
                            echo $postulante['eval_estado'] == 'aprobado' ? 'success' : 
                                ($postulante['eval_estado'] == 'reprobado' ? 'danger' : 'warning'); 
                        ?> text-white" style="font-size:14px;">
                            <?php echo ucfirst($postulante['eval_estado'] ?? 'Pendiente'); ?>
                        </span>
                    </td></tr>
                    <tr><td><strong>Observaciones:</strong></td><td><?php echo $postulante['eval_observaciones'] ?? 'Sin observaciones'; ?></td></tr>
                </table>
            <?php else: ?>
                <p class="text-muted text-center py-3">No hay evaluación registrada</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Documentos -->
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-files"></i> Documentos Subidos</h6>
            <hr>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Archivo</th>
                            <th>Estado</th>
                            <th>Fecha Subida</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documentos)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No hay documentos subidos</td></tr>
                        <?php else: ?>
                            <?php foreach ($documentos as $doc): ?>
                                <tr>
                                    <td><?php echo $doc['nombre_documento']; ?></td>
                                    <td>
                                        <?php if ($doc['nombre_archivo']): ?>
                                            <span class="text-success">✅ Subido</span>
                                        <?php else: ?>
                                            <span class="text-muted">No subido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($doc['estado']): ?>
                                            <span class="badge bg-<?php 
                                                echo $doc['estado'] == 'aprobado' ? 'success' : 
                                                    ($doc['estado'] == 'rechazado' ? 'danger' : 'warning'); 
                                            ?> text-white">
                                                <?php echo ucfirst($doc['estado']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $doc['fecha_subida'] ? date('d/m/Y H:i', strtotime($doc['fecha_subida'])) : '-'; ?></td>
                                    <td>
                                        <?php if ($doc['ruta']): ?>
                                            <a href="<?php echo $doc['ruta']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Ver
                                            </a>
                                        <?php endif; ?>
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

<!-- Acciones -->
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-gear"></i> Acciones</h6>
            <hr>
            <div class="d-flex gap-2 flex-wrap">
                <a href="ver_documentos.php?id=<?php echo $postulante_id; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-files"></i> Revisar Documentos
                </a>
                <a href="evaluaciones.php?id=<?php echo $postulante_id; ?>" class="btn btn-outline-success">
                    <i class="bi bi-pencil"></i> Evaluación
                </a>
                <a href="pagos.php" class="btn btn-outline-warning">
                    <i class="bi bi-credit-card"></i> Ver Pagos
                </a>
                <a href="citas.php" class="btn btn-outline-info">
                    <i class="bi bi-calendar"></i> Ver Citas
                </a>
                <a href="postulantes.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>