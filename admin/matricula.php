<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    $id = $_POST['postulante_id'];
    query("UPDATE postulantes SET estado_proceso = 'matriculado' WHERE id = ?", [$id]);
    registrarAuditoria('matriculas', 'confirmar', 'postulante', $id, "Matrícula confirmada para postulante ID $id");
    header('Location: matricula.php?mensaje=Matriculado');
    exit;
}

$postulantes = fetchAll("SELECT p.*, u.nombres as padre_nombre, u.apellidos as padre_apellidos, g.nombre as grado, s.nombre as sede, e.nota FROM postulantes p JOIN usuarios u ON p.id_usuario_padre = u.id JOIN grados g ON p.id_grado = g.id JOIN sedes s ON p.id_sede = s.id JOIN evaluaciones e ON p.id = e.id_postulante WHERE p.estado_proceso = 'evaluacion_aprobada' ORDER BY p.fecha_registro DESC");
?>

<h4><i class="bi bi-check-circle"></i> Confirmar Matrículas</h4>
<p class="text-muted">Postulantes que han aprobado la evaluación y están listos para matricularse</p>
<?php if (isset($_GET['mensaje'])): ?><div class="alert alert-success">✅ <?php echo $_GET['mensaje']; ?></div><?php endif; ?>

<div class="card-dashboard">
    <?php if (empty($postulantes)): ?>
        <div class="text-center py-5"><i class="bi bi-emoji-smile" style="font-size:60px;color:#2e7d32;"></i><h5 class="text-primary-dark mt-3">No hay postulantes pendientes de matrícula</h5></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Postulante</th><th>DNI</th><th>Padre</th><th>Grado</th><th>Sede</th><th>Nota</th><th>Acción</th></tr></thead>
                <tbody><?php foreach ($postulantes as $p): ?><tr><td><strong><?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?></strong></td><td><?php echo $p['dni']; ?></td><td><?php echo $p['padre_nombre'] . ' ' . $p['padre_apellidos']; ?></td><td><?php echo $p['grado']; ?></td><td><?php echo $p['sede']; ?></td><td><span class="badge bg-<?php echo ($p['nota'] ?? 0) >= 11 ? 'success' : 'danger'; ?>"><?php echo $p['nota'] ?? '--'; ?></span></td><td><form method="POST" class="d-inline"><input type="hidden" name="postulante_id" value="<?php echo $p['id']; ?>"><button type="submit" name="confirmar" class="btn btn-sm btn-success" onclick="return confirm('¿Confirmar matrícula de <?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?>?')"><i class="bi bi-check-circle"></i> Matricular</button></form></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>