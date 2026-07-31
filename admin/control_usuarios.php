<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_usuario'])) {
    $correo = $_POST['correo']; $id_rol = $_POST['id_rol']; $modo_lectura = isset($_POST['modo_lectura']) ? 1 : 0;
    $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
    $existe = fetchOne("SELECT id FROM usuarios WHERE email = ?", [$correo]);
    if ($existe) { $mensaje = "❌ El correo ya está registrado"; } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT); $usuario = 'usr-' . rand(100, 999);
        $id = insert("INSERT INTO usuarios (usuario, password, tipo, email, id_rol, modo_solo_lectura, estado) VALUES (?, ?, 'admin', ?, ?, ?, 1)", [$usuario, $hashed, $correo, $id_rol, $modo_lectura]);
        if (!empty($_POST['sedes'])) { foreach ($_POST['sedes'] as $sede_id) { insert("INSERT INTO usuario_sedes (id_usuario, id_sede) VALUES (?, ?)", [$id, $sede_id]); } }
        if (!empty($_POST['distritos'])) { foreach ($_POST['distritos'] as $distrito_id) { insert("INSERT INTO usuario_distritos (id_usuario, id_distrito) VALUES (?, ?)", [$id, $distrito_id]); } }
        registrarAuditoria('usuarios', 'crear', 'usuario', $id, "Creación de operador $correo");
        $mensaje = "✅ Usuario creado. Contraseña: $password";
    }
}

$operadores = fetchAll("SELECT u.*, r.nombre as rol_nombre FROM usuarios u LEFT JOIN roles r ON u.id_rol = r.id WHERE u.tipo = 'admin' OR u.id_rol IS NOT NULL ORDER BY u.id DESC");
$roles = fetchAll("SELECT * FROM roles WHERE estado = 1 ORDER BY nombre");
$sedes = fetchAll("SELECT * FROM sedes WHERE estado = 1 ORDER BY nombre");
$distritos = fetchAll("SELECT * FROM distritos WHERE estado = 1 ORDER BY nombre");
?>

<h4><i class="bi bi-person-gear"></i> Control de Usuarios</h4>
<p class="text-muted">Gestión de operadores del sistema</p>
<?php if (isset($mensaje)): ?><div class="alert alert-<?php echo strpos($mensaje, '✅') !== false ? 'success' : 'danger'; ?>"><?php echo $mensaje; ?></div><?php endif; ?>

<div class="card-dashboard mb-4">
    <h6 class="text-primary-dark"><i class="bi bi-plus-circle"></i> Crear Nuevo Operador</h6><hr>
    <form method="POST" class="row g-3"><input type="hidden" name="crear_usuario" value="1">
        <div class="col-md-4"><input type="email" name="correo" class="form-control" placeholder="Correo electrónico" required></div>
        <div class="col-md-4"><select name="id_rol" class="form-select" required><option value="">Seleccionar rol...</option><?php foreach ($roles as $r): ?><option value="<?php echo $r['id']; ?>"><?php echo $r['nombre']; ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><div class="form-check mt-2"><input type="checkbox" name="modo_lectura" class="form-check-input" id="modoLectura"><label class="form-check-label" for="modoLectura">Solo lectura</label></div></div>
        <div class="col-md-6"><select name="sedes[]" class="form-select" multiple style="height:80px;"><?php foreach ($sedes as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo $s['nombre']; ?></option><?php endforeach; ?></select><small class="text-muted">Ctrl+clic para múltiples</small></div>
        <div class="col-md-6"><select name="distritos[]" class="form-select" multiple style="height:80px;"><?php foreach ($distritos as $d): ?><option value="<?php echo $d['id']; ?>"><?php echo $d['nombre']; ?></option><?php endforeach; ?></select><small class="text-muted">Ctrl+clic para múltiples</small></div>
        <div class="col-md-12"><button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i> Crear Operador</button></div>
    </form>
</div>

<div class="card-dashboard">
    <h6 class="text-primary-dark"><i class="bi bi-list"></i> Listado de Operadores</h6><hr>
    <?php if (empty($operadores)): ?><p class="text-muted text-center py-3">No hay operadores registrados</p>
    <?php else: ?><?php foreach ($operadores as $op): ?><div style="padding:12px 15px;border-left:4px solid #1a3a6b;background:#f8f9fa;border-radius:0 8px 8px 0;margin-bottom:10px;"><div class="d-flex justify-content-between"><div><h6 class="mb-1"><i class="bi bi-envelope"></i> <?php echo $op['email']; ?> <span class="badge bg-secondary ms-2"><?php echo $op['usuario']; ?></span></h6><p class="mb-0"><span class="badge bg-primary"><?php echo $op['rol_nombre'] ?? 'Sin rol'; ?></span> <?php if ($op['modo_solo_lectura']): ?><span class="badge bg-warning text-dark">🔒 Solo Lectura</span><?php endif; ?></p><?php $sedes_usuario = fetchAll("SELECT s.nombre FROM usuario_sedes us JOIN sedes s ON us.id_sede = s.id WHERE us.id_usuario = ?", [$op['id']]); if (!empty($sedes_usuario)): ?><small><strong>Sedes:</strong> <?php foreach ($sedes_usuario as $su): ?><span class="badge bg-light text-dark"><?php echo $su['nombre']; ?></span> <?php endforeach; ?></small><?php endif; ?></div><div><button class="btn btn-sm btn-outline-danger" onclick="if(confirm('¿Eliminar?')) window.location.href='eliminar_usuario.php?id=<?php echo $op['id']; ?>'"><i class="bi bi-trash"></i></button></div></div></div><?php endforeach; ?><?php endif; ?>
</div>

<?php include 'footer.php'; ?>