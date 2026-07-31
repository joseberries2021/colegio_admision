<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cargar_codigos') {
    $codigos = explode("\n", $_POST['codigos']); $monto = $_POST['monto']; $contador = 0;
    foreach ($codigos as $codigo) { $codigo = trim($codigo); if (!empty($codigo)) { $existe = fetchOne("SELECT id FROM codigos_pago WHERE codigo = ?", [$codigo]); if (!$existe) { insert("INSERT INTO codigos_pago (codigo, monto, usado) VALUES (?, ?, 0)", [$codigo, $monto]); $contador++; } } }
    registrarAuditoria('codigos_pago', 'cargar', 'codigo', 0, "Carga de $contador códigos de pago");
    header('Location: codigos_pago.php?mensaje=Cargados ' . $contador);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'eliminar_usado') { query("DELETE FROM codigos_pago WHERE usado = 1"); header('Location: codigos_pago.php?mensaje=Usados eliminados'); exit; }

$codigos = fetchAll("SELECT * FROM codigos_pago ORDER BY id DESC");
$total = count($codigos); $usados = 0; foreach ($codigos as $c) { if ($c['usado']) $usados++; }
?>

<h4><i class="bi bi-upc-scan"></i> Códigos de Pago</h4>
<p class="text-muted">Gestión de códigos únicos para el pago de derecho de admisión</p>
<?php if (isset($_GET['mensaje'])): ?><div class="alert alert-success">✅ <?php echo $_GET['mensaje']; ?></div><?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3"><div class="stat-card bg-primary-dark"><div><div class="number"><?php echo $total; ?></div><div class="label">Total Códigos</div></div></div></div>
    <div class="col-md-3"><div class="stat-card bg-success-dark"><div><div class="number"><?php echo $total - $usados; ?></div><div class="label">Disponibles</div></div></div></div>
    <div class="col-md-3"><div class="stat-card bg-warning-dark"><div><div class="number"><?php echo $usados; ?></div><div class="label">Usados</div></div></div></div>
</div>

<div class="card-dashboard mb-4">
    <h6 class="text-primary-dark"><i class="bi bi-plus-circle"></i> Cargar Códigos</h6><hr>
    <form method="POST"><input type="hidden" name="action" value="cargar_codigos">
        <div class="row"><div class="col-md-8"><textarea name="codigos" class="form-control" rows="4" placeholder="COD-001&#10;COD-002&#10;COD-003"></textarea></div>
        <div class="col-md-4"><input type="number" name="monto" class="form-control" step="0.01" value="100.00"><button type="submit" class="btn btn-primary mt-2 w-100"><i class="bi bi-upload"></i> Cargar Códigos</button></div></div>
    </form>
</div>

<div class="card-dashboard">
    <div class="d-flex justify-content-between"><h6 class="text-primary-dark"><i class="bi bi-list"></i> Lista de Códigos</h6><form method="POST"><input type="hidden" name="action" value="eliminar_usado"><button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar todos los códigos usados?')"><i class="bi bi-trash"></i> Limpiar Usados</button></form></div><hr>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th>#</th><th>Código</th><th>Monto</th><th>Estado</th><th>Asignado a</th><th>Fecha</th></tr></thead>
            <tbody><?php foreach ($codigos as $c): ?><tr><td><?php echo $c['id']; ?></td><td><code><?php echo $c['codigo']; ?></code></td><td>S/. <?php echo number_format($c['monto'], 2); ?></td><td><span class="badge bg-<?php echo $c['usado'] ? 'danger' : 'success'; ?>"><?php echo $c['usado'] ? 'Usado' : 'Disponible'; ?></span></td><td><?php if ($c['id_postulante']) { $post = fetchOne("SELECT nombres, apellido_paterno FROM postulantes WHERE id = ?", [$c['id_postulante']]); echo $post ? $post['nombres'] . ' ' . $post['apellido_paterno'] : 'N/A'; } else { echo '-'; } ?></td><td><?php echo $c['fecha_asignacion'] ? date('d/m/Y', strtotime($c['fecha_asignacion'])) : '-'; ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>