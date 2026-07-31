<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_documento'])) {
    $archivo = $_FILES['archivo'];
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_archivo = 'doc_' . time() . '.' . $extension;
    $ruta = '../uploads/documentos/' . $nombre_archivo;
    if (move_uploaded_file($archivo['tmp_name'], $ruta)) {
        insert("INSERT INTO contratos (titulo, tipo, descripcion, archivo, estado) VALUES (?, ?, ?, ?, ?)", [$_POST['titulo'], $_POST['tipo'], $_POST['descripcion'], $nombre_archivo, isset($_POST['estado']) ? 1 : 0]);
        registrarAuditoria('contratos', 'crear', 'contrato', $pdo->lastInsertId(), "Subida de documento: " . $_POST['titulo']);
        header('Location: contratos.php?mensaje=Subido');
        exit;
    }
}
if (isset($_GET['eliminar']) && isset($_GET['id'])) { $id = (int)$_GET['id']; $doc = fetchOne("SELECT archivo FROM contratos WHERE id = ?", [$id]); if ($doc && $doc['archivo'] && file_exists('../uploads/documentos/' . $doc['archivo'])) unlink('../uploads/documentos/' . $doc['archivo']); query("DELETE FROM contratos WHERE id = ?", [$id]); registrarAuditoria('contratos', 'eliminar', 'contrato', $id, "Eliminación de documento ID $id"); header('Location: contratos.php?mensaje=Eliminado'); exit; }

$contratos = fetchAll("SELECT * FROM contratos ORDER BY id DESC");
?>

<h4><i class="bi bi-file-text"></i> Contratos y Reglamentos</h4>
<p class="text-muted">Gestión de documentos legales, contratos de matrícula y reglamentos</p>
<?php if (isset($_GET['mensaje'])): ?><div class="alert alert-success">✅ <?php echo $_GET['mensaje']; ?></div><?php endif; ?>

<div class="card-dashboard mb-4">
    <h6 class="text-primary-dark"><i class="bi bi-upload"></i> Subir Nuevo Documento</h6><hr>
    <form method="POST" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="crear_documento" value="1">
        <div class="col-md-3"><input type="text" name="titulo" class="form-control" placeholder="Título" required></div>
        <div class="col-md-2"><select name="tipo" class="form-select"><option value="contrato">Contrato</option><option value="reglamento">Reglamento</option><option value="otros">Otros</option></select></div>
        <div class="col-md-3"><input type="file" name="archivo" class="form-control" accept=".pdf" required></div>
        <div class="col-md-2"><input type="checkbox" name="estado" checked> Activo</div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Subir</button></div>
        <div class="col-md-12"><textarea name="descripcion" class="form-control" rows="2" placeholder="Descripción"></textarea></div>
    </form>
</div>

<div class="card-dashboard">
    <h6 class="text-primary-dark"><i class="bi bi-list"></i> Documentos</h6><hr>
    <?php if (empty($contratos)): ?><p class="text-muted text-center py-3">No hay documentos subidos</p>
    <?php else: ?><?php foreach ($contratos as $c): ?><div style="padding:12px 15px;border-left:4px solid #1a3a6b;background:#f8f9fa;border-radius:0 8px 8px 0;margin-bottom:8px;"><div class="d-flex justify-content-between"><div><h6 class="mb-1"><?php echo $c['titulo']; ?></h6><p class="mb-0"><span class="badge bg-primary"><?php echo ucfirst($c['tipo']); ?></span> <span class="badge bg-<?php echo $c['estado'] ? 'success' : 'danger'; ?>"><?php echo $c['estado'] ? 'Activo' : 'Inactivo'; ?></span> <small class="text-muted"><?php echo date('d/m/Y', strtotime($c['fecha_registro'])); ?></small></p></div><div><a href="../uploads/documentos/<?php echo $c['archivo']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a><a href="contratos.php?eliminar=1&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash"></i></a></div></div></div><?php endforeach; ?><?php endif; ?>
</div>

<?php include 'footer.php'; ?>