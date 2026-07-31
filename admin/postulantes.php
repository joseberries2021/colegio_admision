<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

include 'header.php';

$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_sede = isset($_GET['sede']) ? (int)$_GET['sede'] : 0;
$filtro_nivel = isset($_GET['nivel']) ? (int)$_GET['nivel'] : 0;
$filtro_grado = isset($_GET['grado']) ? (int)$_GET['grado'] : 0;
$filtro_busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Construir consulta con filtros
$sql = "SELECT p.*, u.nombres as padre_nombre, u.apellidos as padre_apellidos, 
        g.nombre as grado, s.nombre as sede, n.nombre as nivel
        FROM postulantes p
        JOIN usuarios u ON p.id_usuario_padre = u.id
        JOIN grados g ON p.id_grado = g.id
        JOIN sedes s ON p.id_sede = s.id
        JOIN niveles n ON p.id_nivel = n.id
        WHERE 1=1";

$params = [];

if ($filtro_estado) {
    $sql .= " AND p.estado_proceso = ?";
    $params[] = $filtro_estado;
}

if ($filtro_sede) {
    $sql .= " AND p.id_sede = ?";
    $params[] = $filtro_sede;
}

if ($filtro_nivel) {
    $sql .= " AND p.id_nivel = ?";
    $params[] = $filtro_nivel;
}

if ($filtro_grado) {
    $sql .= " AND p.id_grado = ?";
    $params[] = $filtro_grado;
}

if ($filtro_busqueda) {
    $sql .= " AND (p.nombres LIKE ? OR p.apellido_paterno LIKE ? OR p.dni LIKE ? OR u.dni LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

if ($filtro_fecha_desde) {
    $sql .= " AND DATE(p.fecha_registro) >= ?";
    $params[] = $filtro_fecha_desde;
}

if ($filtro_fecha_hasta) {
    $sql .= " AND DATE(p.fecha_registro) <= ?";
    $params[] = $filtro_fecha_hasta;
}

$sql .= " ORDER BY p.fecha_registro DESC";

$postulantes = fetchAll($sql, $params);

// Obtener datos para los filtros
$sedes = fetchAll("SELECT * FROM sedes WHERE estado = 1 ORDER BY nombre");
$niveles = fetchAll("SELECT * FROM niveles WHERE estado = 1 ORDER BY id");
$grados = fetchAll("SELECT * FROM grados WHERE estado = 1 ORDER BY orden");

// Estados disponibles
$estados = [
    'registrado' => 'Registrado',
    'documentos_pendientes' => 'Documentos Pendientes',
    'documentos_revisados' => 'Documentos Revisados',
    'pago_pendiente' => 'Pago Pendiente',
    'pago_verificado' => 'Pago Verificado',
    'cita_pendiente' => 'Cita Pendiente',
    'cita_aprobada' => 'Cita Aprobada',
    'evaluacion_pendiente' => 'Evaluación Pendiente',
    'evaluacion_aprobada' => 'Evaluación Aprobada',
    'matriculado' => 'Matriculado',
    'lista_espera' => 'Lista de Espera'
];
?>

<h4><i class="bi bi-people"></i> Alumnos & Postulantes</h4>
<p class="text-muted">Gestión completa de postulantes y alumnos</p>

<!-- Filtros -->
<div class="card-dashboard mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small">Buscar</label>
            <input type="text" name="busqueda" class="form-control form-control-sm" 
                   placeholder="Nombre, DNI..." value="<?php echo $filtro_busqueda; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($estados as $key => $nombre): ?>
                    <option value="<?php echo $key; ?>" <?php echo $filtro_estado == $key ? 'selected' : ''; ?>>
                        <?php echo $nombre; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Sede</label>
            <select name="sede" class="form-select form-select-sm">
                <option value="0">Todas</option>
                <?php foreach ($sedes as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $filtro_sede == $s['id'] ? 'selected' : ''; ?>>
                        <?php echo $s['nombre']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Nivel</label>
            <select name="nivel" class="form-select form-select-sm" id="filtroNivel">
                <option value="0">Todos</option>
                <?php foreach ($niveles as $n): ?>
                    <option value="<?php echo $n['id']; ?>" <?php echo $filtro_nivel == $n['id'] ? 'selected' : ''; ?>>
                        <?php echo $n['nombre']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Grado</label>
            <select name="grado" class="form-select form-select-sm" id="filtroGrado">
                <option value="0">Todos</option>
                <?php foreach ($grados as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $filtro_grado == $g['id'] ? 'selected' : ''; ?>>
                        <?php echo $g['nombre']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-12">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label small">Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?php echo $filtro_fecha_desde; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?php echo $filtro_fecha_hasta; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="postulantes.php" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                    </a>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button onclick="window.location.href='reportes.php?exportar=excel'" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-file-excel"></i> Exportar
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="card-dashboard">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-primary-dark"><i class="bi bi-list"></i> Lista de Postulantes</h6>
        <span class="badge bg-primary">Total: <?php echo count($postulantes); ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Postulante</th>
                    <th>DNI</th>
                    <th>Padre</th>
                    <th>Grado</th>
                    <th>Sede</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($postulantes)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox" style="font-size: 30px; display: block;"></i>
                            No hay postulantes que coincidan con los filtros
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($postulantes as $p): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td>
                                <strong><?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?></strong>
                            </td>
                            <td><?php echo $p['dni']; ?></td>
                            <td><?php echo $p['padre_nombre'] . ' ' . $p['padre_apellidos']; ?></td>
                            <td><?php echo $p['grado']; ?></td>
                            <td><?php echo $p['sede']; ?></td>
                            <td>
                                <span class="badge-estado bg-<?php 
                                    echo $p['estado_proceso'] == 'matriculado' ? 'success' : 
                                        ($p['estado_proceso'] == 'documentos_pendientes' ? 'warning' : 
                                        ($p['estado_proceso'] == 'pago_pendiente' ? 'danger' : 
                                        ($p['estado_proceso'] == 'cita_pendiente' ? 'info' : 
                                        ($p['estado_proceso'] == 'lista_espera' ? 'secondary' : 'primary')))); 
                                ?> text-white">
                                    <?php echo $estados[$p['estado_proceso']] ?? $p['estado_proceso']; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($p['fecha_registro'])); ?></td>
                            <td>
                                <!-- Botón Documentos -->
                                <a href="ver_documentos.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary" title="Ver documentos">
                                    <i class="bi bi-files"></i>
                                </a>
                                <!-- Botón Ojo - Ver detalle completo -->
                                <a href="ver_postulante.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Ver detalle completo">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>