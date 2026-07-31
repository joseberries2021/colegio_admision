<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cita_id = $_POST['cita_id']; $action = $_POST['action'];
    if ($action == 'confirmar') { query("UPDATE citas SET estado = 'confirmada' WHERE id = ?", [$cita_id]); $cita = fetchOne("SELECT id_postulante FROM citas WHERE id = ?", [$cita_id]); if ($cita) query("UPDATE postulantes SET estado_proceso = 'cita_aprobada' WHERE id = ?", [$cita['id_postulante']]); registrarAuditoria('citas', 'confirmar', 'cita', $cita_id, "Cita confirmada ID $cita_id"); }
    elseif ($action == 'cancelar') { query("UPDATE citas SET estado = 'cancelada' WHERE id = ?", [$cita_id]); registrarAuditoria('citas', 'cancelar', 'cita', $cita_id, "Cita cancelada ID $cita_id"); }
    header('Location: citas.php');
    exit;
}

$citas = fetchAll("SELECT c.*, p.nombres, p.apellido_paterno, p.dni, u.nombres as padre_nombre, u.apellidos as padre_apellidos, g.nombre as grado, s.nombre as sede FROM citas c JOIN postulantes p ON c.id_postulante = p.id JOIN usuarios u ON p.id_usuario_padre = u.id JOIN grados g ON p.id_grado = g.id JOIN sedes s ON p.id_sede = s.id ORDER BY c.fecha ASC, c.hora ASC");
$pendientes = array_filter($citas, function($c) { return $c['estado'] == 'pendiente'; });
$confirmadas = array_filter($citas, function($c) { return $c['estado'] == 'confirmada'; });
?>

<h4><i class="bi bi-calendar"></i> Citas Psicológicas</h4>
<p class="text-muted">Gestión de citas psicopedagógicas y académicas</p>

<div class="row mb-4">
    <div class="col-md-3"><div class="stat-card bg-warning-dark"><div><div class="number"><?php echo count($pendientes); ?></div><div class="label">Pendientes</div></div></div></div>
    <div class="col-md-3"><div class="stat-card bg-success-dark"><div><div class="number"><?php echo count($confirmadas); ?></div><div class="label">Confirmadas</div></div></div></div>
</div>

<div class="card-dashboard">
    <?php if (empty($citas)): ?><p class="text-muted text-center py-3">No hay citas registradas</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Postulante</th><th>DNI</th><th>Tipo</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody><?php foreach ($citas as $c): ?><tr><td><?php echo $c['nombres'] . ' ' . $c['apellido_paterno']; ?></td><td><?php echo $c['dni']; ?></td><td><span class="badge bg-info"><?php echo $c['tipo']; ?></span></td><td><?php echo date('d/m/Y', strtotime($c['fecha'])); ?></td><td><?php echo $c['hora']; ?></td><td><span class="badge bg-<?php echo $c['estado'] == 'confirmada' ? 'success' : ($c['estado'] == 'cancelada' ? 'danger' : 'warning'); ?>"><?php echo $c['estado']; ?></span></td><td><?php if ($c['estado'] == 'pendiente'): ?><form method="POST" class="d-inline"><input type="hidden" name="cita_id" value="<?php echo $c['id']; ?>"><button type="submit" name="action" value="confirmar" class="btn btn-sm btn-success"><i class="bi bi-check"></i></button> <button type="submit" name="action" value="cancelar" class="btn btn-sm btn-danger"><i class="bi bi-x"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>