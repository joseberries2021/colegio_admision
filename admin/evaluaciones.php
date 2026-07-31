<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

$postulante_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$postulante = null;
if ($postulante_id) {
    $postulante = fetchOne("SELECT p.*, u.nombres as padre_nombre, u.apellidos as padre_apellidos, g.nombre as grado, s.nombre as sede FROM postulantes p JOIN usuarios u ON p.id_usuario_padre = u.id JOIN grados g ON p.id_grado = g.id JOIN sedes s ON p.id_sede = s.id WHERE p.id = ?", [$postulante_id]);
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar'])) {
    $id = $_POST['postulante_id']; $nota = $_POST['nota']; $observaciones = $_POST['observaciones']; $estado = $_POST['estado_evaluacion'];
    $existe = fetchOne("SELECT id FROM evaluaciones WHERE id_postulante = ?", [$id]);
    if ($existe) { query("UPDATE evaluaciones SET nota = ?, observaciones = ?, estado = ? WHERE id_postulante = ?", [$nota, $observaciones, $estado, $id]); }
    else { insert("INSERT INTO evaluaciones (id_postulante, nota, observaciones, estado) VALUES (?, ?, ?, ?)", [$id, $nota, $observaciones, $estado]); }
    if ($estado == 'aprobado') { query("UPDATE postulantes SET estado_proceso = 'evaluacion_aprobada' WHERE id = ?", [$id]); }
    else { query("UPDATE postulantes SET estado_proceso = 'evaluacion_pendiente' WHERE id = ?", [$id]); }
    registrarAuditoria('evaluaciones', 'guardar', 'evaluacion', $id, "Evaluación guardada para postulante ID $id");
    header('Location: evaluaciones.php?id=' . $id);
    exit;
}
$evaluacion = null;
if ($postulante) { $evaluacion = fetchOne("SELECT * FROM evaluaciones WHERE id_postulante = ?", [$postulante_id]); }
$postulantes_lista = fetchAll("SELECT p.id, p.nombres, p.apellido_paterno, p.dni, p.estado_proceso, g.nombre as grado, s.nombre as sede FROM postulantes p JOIN grados g ON p.id_grado = g.id JOIN sedes s ON p.id_sede = s.id WHERE p.estado_proceso IN ('cita_aprobada', 'evaluacion_pendiente', 'evaluacion_aprobada') ORDER BY p.fecha_registro DESC");
?>

<h4><i class="bi bi-pencil"></i> Evaluaciones Académicas</h4>
<p class="text-muted">Registra las notas de la evaluación académica de los postulantes</p>

<div class="row">
    <div class="col-md-4">
        <div class="card-dashboard">
            <h6 class="text-primary-dark"><i class="bi bi-list"></i> Postulantes</h6><hr>
            <?php if (empty($postulantes_lista)): ?><p class="text-muted text-center py-3">No hay postulantes para evaluar</p>
            <?php else: ?><div class="list-group"><?php foreach ($postulantes_lista as $p): ?><a href="evaluaciones.php?id=<?php echo $p['id']; ?>" class="list-group-item list-group-item-action <?php echo ($postulante_id == $p['id']) ? 'active' : ''; ?>"><div class="d-flex justify-content-between"><div><strong><?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?></strong><br><small><?php echo $p['grado']; ?> - <?php echo $p['sede']; ?></small></div><span class="badge bg-<?php echo $p['estado_proceso'] == 'evaluacion_aprobada' ? 'success' : 'warning'; ?>"><?php echo str_replace('_', ' ', $p['estado_proceso']); ?></span></div></a><?php endforeach; ?></div><?php endif; ?>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card-dashboard">
            <?php if ($postulante): ?>
                <div class="d-flex justify-content-between"><div><h5 class="text-primary-dark"><?php echo $postulante['nombres'] . ' ' . $postulante['apellido_paterno']; ?></h5><p class="text-muted">DNI: <?php echo $postulante['dni']; ?> | <?php echo $postulante['grado']; ?> - <?php echo $postulante['sede']; ?></p></div><span class="badge bg-<?php echo $postulante['estado_proceso'] == 'evaluacion_aprobada' ? 'success' : 'warning'; ?> p-2"><?php echo str_replace('_', ' ', $postulante['estado_proceso']); ?></span></div><hr>
                <form method="POST"><input type="hidden" name="postulante_id" value="<?php echo $postulante['id']; ?>">
                    <div class="row"><div class="col-md-6"><label class="fw-bold">Nota</label><input type="number" name="nota" class="form-control" step="0.5" min="0" max="20" required value="<?php echo $evaluacion['nota'] ?? ''; ?>"></div><div class="col-md-6"><label class="fw-bold">Estado</label><select name="estado_evaluacion" class="form-select"><option value="pendiente" <?php echo ($evaluacion['estado'] ?? '') == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option><option value="aprobado" <?php echo ($evaluacion['estado'] ?? '') == 'aprobado' ? 'selected' : ''; ?>>Aprobado</option><option value="reprobado" <?php echo ($evaluacion['estado'] ?? '') == 'reprobado' ? 'selected' : ''; ?>>Reprobado</option></select></div></div>
                    <div class="mt-3"><label class="fw-bold">Observaciones</label><textarea name="observaciones" class="form-control" rows="3"><?php echo $evaluacion['observaciones'] ?? ''; ?></textarea></div>
                    <div class="mt-3"><button type="submit" name="guardar" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Evaluación</button></div>
                </form>
            <?php else: ?>
                <div class="text-center py-5"><i class="bi bi-person" style="font-size:60px;color:#dee2e6;"></i><h5 class="text-muted mt-3">Selecciona un postulante</h5></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>