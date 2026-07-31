<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$mensaje = '';
$filtro_busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$filtro_distrito = isset($_GET['distrito']) ? (int)$_GET['distrito'] : 0;
$filtro_sede = isset($_GET['sede']) ? (int)$_GET['sede'] : 0;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Obtener datos para filtros
$sedes = fetchAll("SELECT * FROM sedes WHERE estado = 1 ORDER BY nombre");
$distritos = fetchAll("SELECT * FROM distritos ORDER BY nombre");

// ============================================
// CONSULTA PRINCIPAL CON FILTROS - CORREGIDA
// CON COLLATION FORZADA PARA config_documentos
// ============================================
$sql = "SELECT p.*, 
               u.nombres as padre_nombre, 
               u.apellidos as padre_apellidos,
               g.nombre as grado, 
               s.nombre as sede,
               n.nombre as nivel,
               p.tipo_colegio,
               (SELECT COUNT(*) FROM config_documentos cd 
                WHERE (cd.id_nivel IS NULL OR cd.id_nivel = p.id_nivel) 
                AND (cd.id_grado IS NULL OR cd.id_grado = p.id_grado)
                AND (cd.tipo_colegio = 'ambos' OR cd.tipo_colegio COLLATE utf8mb4_general_ci = p.tipo_colegio)
                AND (cd.tipo_alumno = 'ambos' OR cd.tipo_alumno COLLATE utf8mb4_general_ci = 'nuevo')
                AND cd.estado = 1) as total_requeridos,
               (SELECT COUNT(*) FROM documentos_subidos ds 
                WHERE ds.id_postulante = p.id AND ds.estado = 'aprobado') as docs_aprobados,
               (SELECT COUNT(*) FROM documentos_subidos ds 
                WHERE ds.id_postulante = p.id AND ds.estado = 'pendiente') as docs_pendientes
        FROM postulantes p
        JOIN usuarios u ON p.id_usuario_padre = u.id
        JOIN grados g ON p.id_grado = g.id
        JOIN sedes s ON p.id_sede = s.id
        JOIN niveles n ON p.id_nivel = n.id
        WHERE 1=1";

$params = [];

if ($filtro_busqueda) {
    $sql .= " AND (p.nombres LIKE ? OR p.apellido_paterno LIKE ? OR p.dni LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

if ($filtro_sede) {
    $sql .= " AND p.id_sede = ?";
    $params[] = $filtro_sede;
}

if ($filtro_distrito) {
    $sql .= " AND s.distrito_id = ?";
    $params[] = $filtro_distrito;
}

if ($filtro_estado) {
    $sql .= " AND p.estado_proceso = ?";
    $params[] = $filtro_estado;
}

if ($filtro_tipo) {
    $sql .= " AND p.tipo_colegio = ?";
    $params[] = $filtro_tipo;
}

$sql .= " GROUP BY p.id ORDER BY p.fecha_registro DESC";

$postulantes = fetchAll($sql, $params);

// ============================================
// ESTADOS DISPONIBLES
// ============================================
$estados_proceso = [
    'registrado' => 'Postulante',
    'documentos_pendientes' => 'En evaluación',
    'documentos_revisados' => 'Ingresante',
    'pago_pendiente' => 'Lista de espera',
    'pago_verificado' => 'Interesado',
    'matriculado' => 'Matriculado',
    'cita_pendiente' => 'Bloqueado por restricción'
];

// ============================================
// BANDEJA: LISTOS PARA MATRÍCULA
// ============================================
$listos_matricula = fetchAll("
    SELECT p.*, 
           u.nombres as padre_nombre, 
           u.apellidos as padre_apellidos,
           g.nombre as grado, 
           s.nombre as sede
    FROM postulantes p
    JOIN usuarios u ON p.id_usuario_padre = u.id
    JOIN grados g ON p.id_grado = g.id
    JOIN sedes s ON p.id_sede = s.id
    WHERE p.estado_proceso IN ('pago_verificado', 'evaluacion_aprobada')
    ORDER BY p.fecha_registro DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; }
        .btn-primary { background: #1a3a6b; border: none; }
        .btn-primary:hover { background: #2d6bb8; }
        .text-primary-dark { color: #1a3a6b; }
        .card-dashboard { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .filtros-card { background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .nav-tabs .nav-link.active { background: #1a3a6b; color: white; border-color: #1a3a6b; }
        .nav-tabs .nav-link { color: #1a3a6b; }
        .badge-estado { font-size: 11px; padding: 4px 10px; border-radius: 20px; }
        .sidebar-link {
            color: #333; text-decoration: none; padding: 8px 15px; display: block; border-radius: 8px; transition: all 0.3s; font-size: 14px;
        }
        .sidebar-link:hover { background: #e8f0fe; color: #1a3a6b; }
        .sidebar-link.active { background: #1a3a6b; color: white; }
        .sidebar-link i { margin-right: 10px; width: 20px; text-align: center; }
        .nav-link-custom {
            color: #333; text-decoration: none; padding: 8px 15px 8px 35px; display: block; border-radius: 8px; transition: all 0.3s; font-size: 13px;
        }
        .nav-link-custom:hover { background: #e8f0fe; color: #1a3a6b; }
        .nav-link-custom.active { background: #1a3a6b; color: white; }
        .nav-link-custom i { margin-right: 8px; width: 16px; text-align: center; }
        .sidebar-title { font-size: 11px; text-transform: uppercase; color: #6c757d; letter-spacing: 1px; padding: 8px 15px; font-weight: 700; }
        .sidebar { background: white; border-radius: 16px; padding: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: sticky; top: 20px; }
        .table-sortable th { cursor: pointer; user-select: none; }
        .table-sortable th:hover { background: #e8f0fe; }
    </style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: #1a3a6b;">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <img src="../assets/img/LOGO%201000X1000%20EN%20BLANCO.png" alt="Logo" height="40" class="d-inline-block align-text-top">
            Admisión 2027 - Admin
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3">
                <i class="bi bi-person-circle"></i> <?php echo $_SESSION['user_nombre'] ?? 'Administrador'; ?>
            </span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2">
            <div class="sidebar">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-grid-3x3-gap-fill text-primary-dark me-2"></i>
                    <span class="fw-bold text-primary-dark">Menú</span>
                </div>
                <hr>
                <a href="index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="postulantes.php" class="sidebar-link"><i class="bi bi-people"></i> Alumnos & Postulantes</a>
                <a href="alumnos_antiguos.php" class="sidebar-link"><i class="bi bi-clock-history"></i> Alumnos Antiguos (Fase 3)</a>
                <a href="documentos.php" class="sidebar-link active"><i class="bi bi-files"></i> Revisión de Documentos</a>
                <div class="sidebar-link" style="cursor: default;"><i class="bi bi-credit-card"></i> Pagos</div>
                <a href="pagos.php" class="nav-link-custom"><i class="bi bi-check-circle"></i> Validación de Pagos</a>
                <a href="codigos_pago.php" class="nav-link-custom"><i class="bi bi-upc-scan"></i> Códigos de Pago</a>
                <a href="citas.php" class="sidebar-link"><i class="bi bi-calendar"></i> Citas Psicológicas</a>
                <a href="configuracion.php" class="sidebar-link"><i class="bi bi-geo-alt"></i> Sedes, Distritos y Vacantes</a>
                <a href="config_documentos.php" class="sidebar-link"><i class="bi bi-file-earmark-text"></i> Configurar Documentos</a>
                <a href="descuentos.php" class="sidebar-link"><i class="bi bi-percent"></i> Descuentos y Campañas</a>
                <a href="contratos.php" class="sidebar-link"><i class="bi bi-file-text"></i> Contratos y Reglamentos</a>
                <a href="seguridad.php" class="sidebar-link"><i class="bi bi-shield-lock"></i> Bitácora de Auditoría</a>
                <a href="reportes.php" class="sidebar-link"><i class="bi bi-bar-chart"></i> Reportes & Descargas</a>
                <hr>
                <div class="sidebar-title">Control de Usuarios</div>
                <a href="control_usuarios.php" class="sidebar-link"><i class="bi bi-person-gear"></i> Control de Usuarios</a>
                <a href="matriz_permisos.php" class="sidebar-link"><i class="bi bi-table"></i> Matriz de Permisos</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-10">
            
            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#verificacion">
                        <i class="bi bi-clipboard-check"></i> Verificación de Expedientes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#listos_matricula">
                        <i class="bi bi-check-circle"></i> Listos para Matrícula
                        <span class="badge bg-success ms-1"><?php echo count($listos_matricula); ?></span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- TAB 1: VERIFICACIÓN DE EXPEDIENTES -->
                <div class="tab-pane fade show active" id="verificacion">
                    <h4><i class="bi bi-clipboard-check"></i> Verificación de Expedientes Documentales</h4>
                    <p class="text-muted">Revisión de documentos de postulantes y alumnos en proceso de ratificación</p>

                    <!-- FILTROS -->
                    <div class="filtros-card mb-4">
                        <h6 class="text-primary-dark"><i class="bi bi-funnel"></i> Filtros de Validación Documental</h6>
                        <hr>
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small">Buscar</label>
                                <input type="text" name="busqueda" class="form-control form-control-sm" 
                                       placeholder="DNI o nombres..." value="<?php echo $filtro_busqueda; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Distritos</label>
                                <select name="distrito" class="form-select form-select-sm">
                                    <option value="0">Todos los Distritos</option>
                                    <?php foreach ($distritos as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo $filtro_distrito == $d['id'] ? 'selected' : ''; ?>><?php echo $d['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Sedes</label>
                                <select name="sede" class="form-select form-select-sm">
                                    <option value="0">Todas las Sedes</option>
                                    <?php foreach ($sedes as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo $filtro_sede == $s['id'] ? 'selected' : ''; ?>><?php echo $s['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Estado</label>
                                <select name="estado" class="form-select form-select-sm">
                                    <option value="">Todos los Estados</option>
                                    <?php foreach ($estados_proceso as $key => $nombre): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filtro_estado == $key ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Tipo Colegio</label>
                                <select name="tipo" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="particular" <?php echo $filtro_tipo == 'particular' ? 'selected' : ''; ?>>Particular</option>
                                    <option value="estatal" <?php echo $filtro_tipo == 'estatal' ? 'selected' : ''; ?>>Estatal</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button>
                            </div>
                        </form>
                    </div>

                    <!-- LISTA DE POSTULANTES -->
                    <div class="card-dashboard">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-primary-dark">
                                <i class="bi bi-people"></i> Estudiantes en Proceso de Postulación / Ratificación 
                                <span class="badge bg-primary"><?php echo count($postulantes); ?></span>
                            </h6>
                        </div>
                        
                        <?php if (empty($postulantes)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox" style="font-size: 50px; color: #dee2e6;"></i>
                                <h5 class="text-muted mt-3">No hay estudiantes en proceso</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sortable" id="tablaDocumentos">
                                    <thead>
                                        <tr>
                                            <th onclick="ordenarTabla('tablaDocumentos', 0)">DNI / Estudiante <i class="bi bi-arrow-up-down"></i></th>
                                            <th onclick="ordenarTabla('tablaDocumentos', 1)">Tipo / Grado 2027 <i class="bi bi-arrow-up-down"></i></th>
                                            <th onclick="ordenarTabla('tablaDocumentos', 2)">Jurisdicción Sede <i class="bi bi-arrow-up-down"></i></th>
                                            <th onclick="ordenarTabla('tablaDocumentos', 3)">Estado Expediente <i class="bi bi-arrow-up-down"></i></th>
                                            <th onclick="ordenarTabla('tablaDocumentos', 4)">Documentación <i class="bi bi-arrow-up-down"></i></th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($postulantes as $p): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?></strong><br>
                                                    <small class="text-muted">DNI: <?php echo $p['dni']; ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $p['id_usuario_padre'] ? 'primary' : 'info'; ?>">
                                                        <?php echo $p['id_usuario_padre'] ? 'ALUMNO REGULAR' : 'NUEVO INGRESO'; ?>
                                                    </span>
                                                    <br>
                                                    <span class="badge bg-<?php echo $p['tipo_colegio'] == 'particular' ? 'info' : 'warning'; ?>">
                                                        <?php echo ucfirst($p['tipo_colegio'] ?? 'particular'); ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted"><?php echo $p['grado']; ?></small>
                                                </td>
                                                <td><?php echo $p['sede']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $p['estado_proceso'] == 'matriculado' ? 'success' : 
                                                            ($p['estado_proceso'] == 'documentos_pendientes' ? 'warning' : 
                                                            ($p['estado_proceso'] == 'pago_pendiente' ? 'danger' : 
                                                            ($p['estado_proceso'] == 'cita_pendiente' ? 'secondary' : 'primary'))); 
                                                    ?> text-white">
                                                        <?php echo $estados_proceso[$p['estado_proceso']] ?? $p['estado_proceso']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $aprobados = $p['docs_aprobados'] ?? 0;
                                                    $total = $p['total_requeridos'] ?? 5;
                                                    ?>
                                                    <span class="badge bg-<?php echo $aprobados == $total ? 'success' : 'warning'; ?>">
                                                        <?php echo $aprobados; ?> de <?php echo $total; ?> aprobados
                                                    </span>
                                                    <br>
                                                    <small class="text-muted"><?php echo $total; ?> obligatorios</small>
                                                </td>
                                                <td>
                                                    <a href="ver_documentos.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i> Revisar
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB 2: LISTOS PARA MATRÍCULA -->
                <div class="tab-pane fade" id="listos_matricula">
                    <h4><i class="bi bi-check-circle"></i> Listos para Matrícula 2027</h4>
                    <p class="text-muted">Estudiantes que completaron con éxito los requisitos</p>

                    <div class="card-dashboard">
                        <?php if (empty($listos_matricula)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-emoji-smile" style="font-size: 60px; color: #2e7d32;"></i>
                                <h5 class="text-primary-dark mt-3">¡Excelente! No hay alumnos pendientes de formalizar matrícula.</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sortable" id="tablaListos">
                                    <thead>
                                        <tr>
                                            <th onclick="ordenarTabla('tablaListos', 0)">DNI / Estudiante <i class="bi bi-arrow-up-down"></i></th>
                                            <th onclick="ordenarTabla('tablaListos', 1)">Grado <i class="bi bi-arrow-up-down"></i></th>
                                            <th onclick="ordenarTabla('tablaListos', 2)">Sede <i class="bi bi-arrow-up-down"></i></th>
                                            <th onclick="ordenarTabla('tablaListos', 3)">Estado <i class="bi bi-arrow-up-down"></i></th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($listos_matricula as $p): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?></strong><br>
                                                    <small class="text-muted">DNI: <?php echo $p['dni']; ?></small>
                                                </td>
                                                <td><?php echo $p['grado']; ?></td>
                                                <td><?php echo $p['sede']; ?></td>
                                                <td><span class="badge bg-success"><?php echo str_replace('_', ' ', $p['estado_proceso']); ?></span></td>
                                                <td>
                                                    <a href="matricula.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="bi bi-check-circle"></i> Formalizar
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function ordenarTabla(tablaId, colIndex) {
    const table = document.getElementById(tablaId);
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    
    if (!window.sortOrder) window.sortOrder = {};
    if (!window.sortOrder[tablaId]) window.sortOrder[tablaId] = {};
    if (!window.sortOrder[tablaId][colIndex]) window.sortOrder[tablaId][colIndex] = 'asc';
    else if (window.sortOrder[tablaId][colIndex] === 'asc') window.sortOrder[tablaId][colIndex] = 'desc';
    else window.sortOrder[tablaId][colIndex] = 'asc';
    
    const order = window.sortOrder[tablaId][colIndex];
    
    rows.sort((a, b) => {
        const valA = a.getElementsByTagName('td')[colIndex]?.textContent.trim() || '';
        const valB = b.getElementsByTagName('td')[colIndex]?.textContent.trim() || '';
        if (!isNaN(valA) && !isNaN(valB)) {
            return order === 'asc' ? parseInt(valA) - parseInt(valB) : parseInt(valB) - parseInt(valA);
        }
        return order === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}
</script>

</body>
</html>