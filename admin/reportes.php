<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

$stats = fetchOne("SELECT (SELECT COUNT(*) FROM postulantes) as total, (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'registrado') as registrados, (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'matriculado') as matriculados, (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'lista_espera') as lista_espera, (SELECT COUNT(*) FROM postulantes WHERE estado_proceso = 'pago_pendiente') as pago_pendientes");
$por_sede = fetchAll("SELECT s.nombre as sede, COUNT(p.id) as total FROM sedes s LEFT JOIN postulantes p ON s.id = p.id_sede GROUP BY s.id ORDER BY total DESC");
$por_grado = fetchAll("SELECT g.nombre as grado, COUNT(p.id) as total FROM grados g LEFT JOIN postulantes p ON g.id = p.id_grado GROUP BY g.id ORDER BY g.orden");
$lista_espera = fetchAll("SELECT p.*, u.nombres as padre_nombre, u.apellidos as padre_apellidos, g.nombre as grado, s.nombre as sede FROM postulantes p JOIN usuarios u ON p.id_usuario_padre = u.id JOIN grados g ON p.id_grado = g.id JOIN sedes s ON p.id_sede = s.id WHERE p.estado_proceso = 'lista_espera' ORDER BY p.fecha_registro ASC");
?>

<h4><i class="bi bi-bar-chart"></i> Reportes & Descargas</h4>
<p class="text-muted">Estadísticas y reportes del proceso de admisión</p>

<div class="row mb-4">
    <div class="col-md-3"><div class="stat-card bg-primary-dark"><div><div class="number"><?php echo $stats['total'] ?? 0; ?></div><div class="label">Total Postulantes</div></div></div></div>
    <div class="col-md-3"><div class="stat-card bg-success-dark"><div><div class="number"><?php echo $stats['matriculados'] ?? 0; ?></div><div class="label">Matriculados</div></div></div></div>
    <div class="col-md-3"><div class="stat-card bg-danger-dark"><div><div class="number"><?php echo $stats['pago_pendientes'] ?? 0; ?></div><div class="label">Pagos Pendientes</div></div></div></div>
    <div class="col-md-3"><div class="stat-card bg-secondary-dark"><div><div class="number"><?php echo $stats['lista_espera'] ?? 0; ?></div><div class="label">Lista de Espera</div></div></div></div>
</div>

<div class="card-dashboard mb-4">
    <h6 class="text-primary-dark"><i class="bi bi-clock-history"></i> Lista de Espera</h6><hr>
    <?php if (empty($lista_espera)): ?><p class="text-muted text-center py-3">No hay postulantes en lista de espera</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Postulante</th><th>DNI</th><th>Padre</th><th>Grado</th><th>Sede</th><th>Fecha</th></tr></thead><tbody><?php foreach ($lista_espera as $p): ?><tr><td><?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?></td><td><?php echo $p['dni']; ?></td><td><?php echo $p['padre_nombre'] . ' ' . $p['padre_apellidos']; ?></td><td><?php echo $p['grado']; ?></td><td><?php echo $p['sede']; ?></td><td><?php echo date('d/m/Y', strtotime($p['fecha_registro'])); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<div class="row">
    <div class="col-md-6"><div class="card-dashboard"><h6 class="text-primary-dark"><i class="bi bi-building"></i> Por Sede</h6><hr><?php foreach ($por_sede as $s): ?><div class="d-flex justify-content-between"><span><?php echo $s['sede'] ?? 'Sin sede'; ?></span><span class="badge bg-primary"><?php echo $s['total']; ?></span></div><div class="progress mb-2"><div class="progress-bar" style="width: <?php echo ($stats['total'] > 0) ? ($s['total'] / $stats['total'] * 100) : 0; ?>%;"></div></div><?php endforeach; ?></div></div>
    <div class="col-md-6"><div class="card-dashboard"><h6 class="text-primary-dark"><i class="bi bi-book"></i> Por Grado</h6><hr><?php foreach ($por_grado as $g): ?><div class="d-flex justify-content-between"><span><?php echo $g['grado'] ?? 'Sin grado'; ?></span><span class="badge bg-secondary"><?php echo $g['total']; ?></span></div><div class="progress mb-2"><div class="progress-bar bg-secondary" style="width: <?php echo ($stats['total'] > 0) ? ($g['total'] / $stats['total'] * 100) : 0; ?>%;"></div></div><?php endforeach; ?></div></div>
</div>

<?php include 'footer.php'; ?>