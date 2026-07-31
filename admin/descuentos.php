<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_descuento'])) {
    insert("INSERT INTO descuentos (nombre, tipo, valor, fecha_inicio, fecha_fin, aplica_todos, estado) VALUES (?, ?, ?, ?, ?, ?, ?)", [$_POST['nombre'], $_POST['tipo'], $_POST['valor'], $_POST['fecha_inicio'], $_POST['fecha_fin'], isset($_POST['aplica_todos']) ? 1 : 0, isset($_POST['estado']) ? 1 : 0]);
    registrarAuditoria('descuentos', 'crear', 'descuento', $pdo->lastInsertId(), "Creación de descuento: " . $_POST['nombre']);
    header('Location: descuentos.php?mensaje=Creado');
    exit;
}
if (isset($_GET['eliminar']) && isset($_GET['id'])) { $id = (int)$_GET['id']; query("DELETE FROM descuentos WHERE id = ?", [$id]); registrarAuditoria('descuentos', 'eliminar', 'descuento', $id, "Eliminación de descuento ID $id"); header('Location: descuentos.php?mensaje=Eliminado'); exit; }

$descuentos = fetchAll("SELECT * FROM descuentos ORDER BY id DESC");
?>

<h4><i class="bi bi-percent"></i> Descuentos y Campañas</h4>
<p class="text-muted">Gestión de descuentos, promociones y campañas especiales</p>
<?php if (isset($_GET['mensaje'])): ?><div class="alert alert-success">✅ <?php echo $_GET['mensaje']; ?></div><?php endif; ?>

<div class="card-dashboard mb-4">
    <h6 class="text-primary-dark"><i class="bi bi-plus-circle"></i> Nuevo Descuento</h6><hr>
    <form method="POST" class="row g-3"><input type="hidden" name="crear_descuento" value="1">
        <div class="col-md-3"><input type="text" name="nombre" class="form-control" placeholder="Nombre" required></div>
        <div class="col-md-2"><select name="tipo" class="form-select"><option value="porcentaje">%</option><option value="fijo">S/.</option></select></div>
        <div class="col-md-2"><input type="number" name="valor" class="form-control" step="0.01" placeholder="Valor" required></div>
        <div class="col-md-2"><input type="date" name="fecha_inicio" class="form-control" required></div>
        <div class="col-md-2"><input type="date" name="fecha_fin" class="form-control" required></div>
        <div class="col-md-2"><input type="checkbox" name="estado" checked> Activo</div>
        <div class="col-md-12"><button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Crear Descuento</button></div>
    </form>
</div>

<div class="card-dashboard">
    <h6 class="text-primary-dark"><i class="bi bi-list"></i> Descuentos Activos</h6><hr>
    <?php if (empty($descuentos)): ?><p class="text-muted text-center py-3">No hay descuentos configurados</p>
    <?php else: ?><?php foreach ($descuentos as $d): ?><div style="padding:12px 15px;border-left:4px solid <?php echo $d['estado'] ? '#2e7d32' : '#dc3545'; ?>;background:#f8f9fa;border-radius:0 8px 8px 0;margin-bottom:8px;"><div class="d-flex justify-content-between"><div><h6 class="mb-1"><?php echo $d['nombre']; ?></h6><p class="mb-0"><span class="badge bg-primary"><?php echo $d['tipo'] == 'porcentaje' ? $d['valor'] . '%' : 'S/. ' . number_format($d['valor'], 2); ?></span> <span class="badge bg-<?php echo $d['estado'] ? 'success' : 'danger'; ?>"><?php echo $d['estado'] ? 'Activo' : 'Inactivo'; ?></span> <small class="text-muted"><?php echo date('d/m/Y', strtotime($d['fecha_inicio'])); ?> - <?php echo date('d/m/Y', strtotime($d['fecha_fin'])); ?></small></p></div><div><a href="descuentos.php?eliminar=1&id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash"></i></a></div></div></div><?php endforeach; ?><?php endif; ?>
</div>

<?php include 'footer.php'; ?>