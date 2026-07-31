<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

$filtro_busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$filtro_severidad = isset($_GET['severidad']) ? $_GET['severidad'] : '';

$sql = "SELECT a.*, u.nombres as user_nombre FROM auditoria a LEFT JOIN usuarios u ON a.id_usuario = u.id WHERE 1=1";
$params = [];
if ($filtro_busqueda) { $sql .= " AND (a.usuario_correo LIKE ? OR a.descripcion LIKE ?)"; $params[] = "%$filtro_busqueda%"; $params[] = "%$filtro_busqueda%"; }
if ($filtro_severidad) { $sql .= " AND a.severidad = ?"; $params[] = $filtro_severidad; }
$sql .= " ORDER BY a.fecha_registro DESC LIMIT 100";
$auditoria = fetchAll($sql, $params);
$severidades = ['informativo', 'advertencia', 'critico'];
?>

<h4><i class="bi bi-shield-lock"></i> Bitácora de Auditoría</h4>
<p class="text-muted">Registro inalterable y firmado digitalmente de todas las acciones del sistema</p>

<div class="card-dashboard mb-4">
    <form method="GET" class="row g-2"><div class="col-md-4"><input type="text" name="busqueda" class="form-control form-control-sm" placeholder="Buscar..." value="<?php echo $filtro_busqueda; ?>"></div><div class="col-md-3"><select name="severidad" class="form-select form-select-sm"><option value="">Todos</option><?php foreach ($severidades as $s): ?><option value="<?php echo $s; ?>" <?php echo $filtro_severidad==$s?'selected':''; ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button></div><div class="col-md-2"><a href="seguridad.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-arrow-counterclockwise"></i> Limpiar</a></div><div class="col-md-1"><a href="seguridad.php?exportar=1" class="btn btn-success btn-sm w-100"><i class="bi bi-file-excel"></i></a></div></form>
</div>

<div class="card-dashboard">
    <?php if (empty($auditoria)): ?><div class="text-center py-4"><i class="bi bi-inbox" style="font-size:40px;color:#dee2e6;"></i><p class="text-muted mt-2">No hay registros de auditoría</p></div>
    <?php else: ?><?php foreach ($auditoria as $log): ?><div style="padding:10px 15px;border-left:4px solid <?php echo $log['severidad'] == 'informativo' ? '#0d6efd' : ($log['severidad'] == 'advertencia' ? '#f57c00' : '#c62828'); ?>;background:#f8f9fa;border-radius:0 8px 8px 0;margin-bottom:8px;"><div class="d-flex justify-content-between"><div><span class="badge bg-secondary"><?php echo $log['modulo']; ?></span><span class="badge bg-<?php echo $log['severidad'] == 'informativo' ? 'primary' : ($log['severidad'] == 'advertencia' ? 'warning' : 'danger'); ?> text-white"><?php echo ucfirst($log['severidad']); ?></span><span class="badge bg-info"><?php echo $log['accion']; ?></span></div><small class="text-muted"><?php echo date('Y-m-d H:i:s', strtotime($log['fecha_registro'])); ?></small></div><p class="mb-0 small"><strong><?php echo $log['user_nombre'] ?? 'Sistema'; ?></strong> <?php echo $log['descripcion']; ?></p><small class="text-muted">IP: <?php echo $log['ip']; ?> | Firma: <?php echo substr($log['firma_digital'], 0, 20); ?>...</small></div><?php endforeach; ?><?php endif; ?>
</div>

<?php include 'footer.php'; ?>