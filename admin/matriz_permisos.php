<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_permisos'])) {
    $id_rol = $_POST['id_rol']; $permisos = $_POST['permisos'] ?? [];
    query("DELETE FROM permisos WHERE id_rol = ?", [$id_rol]);
    foreach ($permisos as $modulo => $acciones) { foreach ($acciones as $permiso => $valor) { insert("INSERT INTO permisos (id_rol, modulo, permiso, valor) VALUES (?, ?, ?, ?)", [$id_rol, $modulo, $permiso, $valor ? 1 : 0]); } }
    registrarAuditoria('permisos', 'editar', 'rol', $id_rol, "Actualización de permisos para rol ID $id_rol");
    header('Location: matriz_permisos.php?rol=' . $id_rol . '&mensaje=Actualizado');
    exit;
}

$roles = fetchAll("SELECT * FROM roles WHERE estado = 1 ORDER BY nombre");
$modulos = ['usuarios', 'sedes', 'distritos', 'postulantes', 'documentos', 'pagos', 'citas', 'evaluaciones', 'matriculas', 'reportes', 'configuracion', 'auditoria', 'seguridad'];
$permisos_lista = ['ver', 'crear', 'editar', 'aprobar', 'observar', 'rechazar', 'eliminar', 'restaurar', 'exportar', 'sensible', 'ver_auditoria', 'configurar'];

$rol_seleccionado = isset($_GET['rol']) ? (int)$_GET['rol'] : ($roles[0]['id'] ?? 0);
$permisos_rol = [];
if ($rol_seleccionado) { $permisos_rol = fetchAll("SELECT modulo, permiso, valor FROM permisos WHERE id_rol = ?", [$rol_seleccionado]); }
?>

<h4><i class="bi bi-table"></i> Matriz de Permisos</h4>
<p class="text-muted">Configure los permisos por rol para cada módulo del sistema</p>
<?php if (isset($_GET['mensaje'])): ?><div class="alert alert-success">✅ <?php echo $_GET['mensaje']; ?></div><?php endif; ?>

<div class="card-dashboard">
    <div class="d-flex justify-content-between mb-3"><div><label class="fw-bold me-2">Rol:</label><select class="form-select d-inline-block" style="width:auto;" onchange="window.location.href='matriz_permisos.php?rol='+this.value"><?php foreach ($roles as $r): ?><option value="<?php echo $r['id']; ?>" <?php echo $rol_seleccionado == $r['id'] ? 'selected' : ''; ?>><?php echo $r['nombre']; ?></option><?php endforeach; ?></select></div></div>
    <form method="POST"><input type="hidden" name="id_rol" value="<?php echo $rol_seleccionado; ?>"><input type="hidden" name="guardar_permisos" value="1">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th style="min-width:120px;">Módulo</th><?php foreach ($permisos_lista as $p): ?><th style="font-size:10px;min-width:60px;"><?php echo ucfirst(str_replace('_', ' ', $p)); ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach ($modulos as $modulo): ?><tr><td class="fw-bold"><?php echo ucfirst($modulo); ?></td><?php foreach ($permisos_lista as $permiso): $valor = 0; foreach ($permisos_rol as $pr) { if ($pr['modulo'] == $modulo && $pr['permiso'] == $permiso) { $valor = $pr['valor']; break; } } ?><td class="text-center"><input type="checkbox" name="permisos[<?php echo $modulo; ?>][<?php echo $permiso; ?>]" value="1" <?php echo $valor ? 'checked' : ''; ?>></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
            </table>
        </div>
        <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Permisos</button></div>
    </form>
</div>

<?php include 'footer.php'; ?>