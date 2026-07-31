<?php
// ============================================
// NO INICIAR SESIÓN AQUÍ - EL HEADER LO HACE
// ============================================
require_once '../config/database.php';
require_once '../includes/functions.php';

// El header ya verifica la sesión, así que no la duplicamos
include 'header.php';

$stats = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM postulantes) as total_postulantes,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'registrado') as registrados,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'documentos_pendientes') as doc_pendientes,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'documentos_revisados') as doc_revisados,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'pago_pendiente') as pago_pendientes,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'pago_verificado') as pago_verificados,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'cita_pendiente') as cita_pendientes,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'cita_aprobada') as cita_aprobadas,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'evaluacion_pendiente') as eval_pendientes,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'evaluacion_aprobada') as eval_aprobadas,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'matriculado') as matriculados,
        (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'lista_espera') as lista_espera,
        (SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin' OR id_rol IS NOT NULL) as total_operadores
");

$ultimos = fetchAll("
    SELECT p.*, g.nombre as grado, s.nombre as sede 
    FROM postulantes p
    JOIN grados g ON p.id_grado = g.id
    JOIN sedes s ON p.id_sede = s.id
    ORDER BY p.fecha_registro DESC LIMIT 10
");

$ultimas_actividades = fetchAll("SELECT * FROM auditoria ORDER BY fecha_registro DESC LIMIT 5");
?>

<h4><i class="bi bi-speedometer2"></i> Panel de Control</h4>
<p class="text-muted">Bienvenido al sistema de administración de admisiones 2027</p>

<div class="row">
    <div class="col-md-3 mb-3"><div class="stat-card bg-primary-dark"><div class="d-flex justify-content-between"><div><div class="number"><?php echo $stats['total_postulantes'] ?? 0; ?></div><div class="label">Total Postulantes</div></div><div class="icon"><i class="bi bi-people"></i></div></div></div></div>
    <div class="col-md-3 mb-3"><div class="stat-card bg-success-dark"><div class="d-flex justify-content-between"><div><div class="number"><?php echo $stats['matriculados'] ?? 0; ?></div><div class="label">Matriculados</div></div><div class="icon"><i class="bi bi-check-circle"></i></div></div></div></div>
    <div class="col-md-3 mb-3"><div class="stat-card bg-warning-dark"><div class="d-flex justify-content-between"><div><div class="number"><?php echo $stats['doc_pendientes'] ?? 0; ?></div><div class="label">Documentos Pendientes</div></div><div class="icon"><i class="bi bi-clock-history"></i></div></div></div></div>
    <div class="col-md-3 mb-3"><div class="stat-card bg-danger-dark"><div class="d-flex justify-content-between"><div><div class="number"><?php echo $stats['pago_pendientes'] ?? 0; ?></div><div class="label">Pagos Pendientes</div></div><div class="icon"><i class="bi bi-credit-card"></i></div></div></div></div>
</div>

<div class="row">
    <div class="col-md-3 mb-3"><div class="stat-card bg-info-dark"><div><div class="number"><?php echo ($stats['cita_pendientes'] ?? 0) + ($stats['cita_aprobadas'] ?? 0); ?></div><div class="label">Citas</div></div></div></div>
    <div class="col-md-3 mb-3"><div class="stat-card bg-purple-dark"><div><div class="number"><?php echo ($stats['eval_pendientes'] ?? 0) + ($stats['eval_aprobadas'] ?? 0); ?></div><div class="label">Evaluaciones</div></div></div></div>
    <div class="col-md-3 mb-3"><div class="stat-card bg-secondary-dark"><div><div class="number"><?php echo $stats['lista_espera'] ?? 0; ?></div><div class="label">Lista de Espera</div></div></div></div>
    <div class="col-md-3 mb-3"><div class="stat-card" style="background:#00796b;"><div><div class="number"><?php echo $stats['total_operadores'] ?? 0; ?></div><div class="label">Operadores Activos</div></div></div></div>
</div>

<div class="row">
    <div class="col-md-7">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-clock-history"></i> Últimos Postulantes</h6><hr>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead><tr><th>Postulante</th><th>DNI</th><th>Grado</th><th>Sede</th><th>Estado</th><th>Fecha</th></tr></thead>
                    <tbody><?php if (empty($ultimos)): ?><tr><td colspan="6" class="text-center text-muted">No hay registros</td></tr><?php else: ?><?php foreach ($ultimos as $p): ?><tr><td><?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?></td><td><?php echo $p['dni']; ?></td><td><?php echo $p['grado']; ?></td><td><?php echo $p['sede']; ?></td><td><span class="badge-estado bg-<?php echo $p['estado_proceso'] == 'matriculado' ? 'success' : ($p['estado_proceso'] == 'documentos_pendientes' ? 'warning' : ($p['estado_proceso'] == 'pago_pendiente' ? 'danger' : 'secondary')); ?> text-white"><?php echo str_replace('_', ' ', $p['estado_proceso']); ?></span></td><td><?php echo date('d/m/Y', strtotime($p['fecha_registro'])); ?></td></tr><?php endforeach; ?><?php endif; ?></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-shield-lock"></i> Última Actividad</h6><hr>
            <?php if (empty($ultimas_actividades)): ?><p class="text-muted text-center py-3">No hay actividad reciente</p><?php else: ?><?php foreach ($ultimas_actividades as $act): ?><div style="padding:8px 12px;border-left:4px solid <?php echo $act['severidad'] == 'informativo' ? '#0d6efd' : ($act['severidad'] == 'advertencia' ? '#f57c00' : '#c62828'); ?>;background:#f8f9fa;border-radius:0 8px 8px 0;margin-bottom:8px;"><div class="d-flex justify-content-between"><div><span class="badge bg-secondary"><?php echo $act['modulo']; ?></span><span class="badge bg-<?php echo $act['severidad'] == 'informativo' ? 'primary' : ($act['severidad'] == 'advertencia' ? 'warning' : 'danger'); ?> text-white"><?php echo $act['accion']; ?></span></div><small class="text-muted"><?php echo date('H:i', strtotime($act['fecha_registro'])); ?></small></div><p class="mb-0 small"><?php echo $act['descripcion']; ?></p><small class="text-muted"><?php echo $act['usuario_nombre'] ?? 'Sistema'; ?></small></div><?php endforeach; ?><?php endif; ?>
            <div class="mt-2"><a href="seguridad.php" class="btn btn-sm btn-outline-primary">Ver toda la bitácora <i class="bi bi-arrow-right"></i></a></div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-star"></i> Accesos Rápidos</h6><hr>
            <div class="d-flex flex-wrap gap-2">
                <a href="postulantes.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people"></i> Ver Postulantes</a>
                <a href="documentos.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-files"></i> Revisar Documentos</a>
                <a href="pagos.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-credit-card"></i> Validar Pagos</a>
                <a href="citas.php" class="btn btn-outline-info btn-sm"><i class="bi bi-calendar"></i> Gestionar Citas</a>
                <a href="evaluaciones.php" class="btn btn-outline-success btn-sm"><i class="bi bi-pencil"></i> Registrar Evaluaciones</a>
                <a href="reportes.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart"></i> Ver Reportes</a>
                <a href="control_usuarios.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-gear"></i> Gestionar Usuarios</a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>