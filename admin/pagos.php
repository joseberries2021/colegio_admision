<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $pago_id = $_POST['pago_id'];
    $estado = $_POST['action'] == 'verificar' ? 'verificado' : 'rechazado';
    query("UPDATE pagos SET estado = ? WHERE id = ?", [$estado, $pago_id]);
    $pago = fetchOne("SELECT id_postulante FROM pagos WHERE id = ?", [$pago_id]);
    if ($pago && $estado == 'verificado') query("UPDATE postulantes SET estado_proceso = 'pago_verificado' WHERE id = ?", [$pago['id_postulante']]);
    registrarAuditoria('pagos', $estado, 'pago', $pago_id, "Pago $estado para postulante ID " . ($pago['id_postulante'] ?? ''));
    header('Location: pagos.php');
    exit;
}

$pagos = fetchAll("SELECT p.*, po.nombres, po.apellido_paterno, po.dni, u.nombres as padre_nombre, u.apellidos as padre_apellidos, g.nombre as grado, s.nombre as sede FROM pagos p JOIN postulantes po ON p.id_postulante = po.id JOIN usuarios u ON po.id_usuario_padre = u.id JOIN grados g ON po.id_grado = g.id JOIN sedes s ON po.id_sede = s.id WHERE p.estado = 'pendiente' ORDER BY p.fecha_pago DESC");
?>

<h4><i class="bi bi-credit-card"></i> Validación de Pagos</h4>
<p class="text-muted">Verifica los vouchers de pago de los postulantes</p>

<div class="card-dashboard">
    <?php if (empty($pagos)): ?>
        <div class="text-center py-4"><i class="bi bi-check-circle" style="font-size:40px;color:#2e7d32;"></i><p class="text-muted mt-2">No hay pagos pendientes de verificar</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Postulante</th><th>DNI</th><th>Padre</th><th>Grado</th><th>Sede</th><th>Voucher</th><th>Fecha</th><th>Acciones</th></tr></thead>
                <tbody><?php foreach ($pagos as $p): ?><tr><td><strong><?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?></strong></td><td><?php echo $p['dni']; ?></td><td><?php echo $p['padre_nombre'] . ' ' . $p['padre_apellidos']; ?></td><td><?php echo $p['grado']; ?></td><td><?php echo $p['sede']; ?></td><td><?php if ($p['voucher']): ?><a href="../uploads/vouchers/<?php echo $p['voucher']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a><?php else: ?><span class="text-muted">No subido</span><?php endif; ?></td><td><?php echo date('d/m/Y H:i', strtotime($p['fecha_pago'])); ?></td><td><form method="POST" class="d-inline"><input type="hidden" name="pago_id" value="<?php echo $p['id']; ?>"><button type="submit" name="action" value="verificar" class="btn btn-sm btn-success"><i class="bi bi-check"></i></button> <button type="submit" name="action" value="rechazar" class="btn btn-sm btn-danger"><i class="bi bi-x"></i></button></form></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>