<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$postulante_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$postulante_id) {
    header('Location: documentos.php');
    exit;
}

$postulante = fetchOne("
    SELECT p.*, 
           u.nombres as padre_nombre, 
           u.apellidos as padre_apellidos,
           g.nombre as grado, 
           s.nombre as sede
    FROM postulantes p
    JOIN usuarios u ON p.id_usuario_padre = u.id
    JOIN grados g ON p.id_grado = g.id
    JOIN sedes s ON p.id_sede = s.id
    WHERE p.id = ?
", [$postulante_id]);

if (!$postulante) {
    header('Location: documentos.php');
    exit;
}

// ============================================
// OBTENER DOCUMENTOS - CON COLLATION CORREGIDA
// ============================================
$documentos = fetchAll("
    SELECT cd.*, ds.id as documento_subido_id, ds.nombre_archivo, ds.ruta, ds.estado, ds.fecha_subida
    FROM config_documentos cd
    LEFT JOIN documentos_subidos ds ON cd.id = ds.id_documento_requerido AND ds.id_postulante = ?
    WHERE cd.estado = 1
    AND (cd.id_nivel IS NULL OR cd.id_nivel = ?)
    AND (cd.id_grado IS NULL OR cd.id_grado = ?)
    AND (cd.tipo_colegio = 'ambos' OR cd.tipo_colegio COLLATE utf8mb4_general_ci = ?)
    AND (cd.tipo_alumno = 'ambos' OR cd.tipo_alumno COLLATE utf8mb4_general_ci = 'nuevo')
    ORDER BY cd.orden
", [$postulante_id, $postulante['id_nivel'], $postulante['id_grado'], $postulante['tipo_colegio'] ?? 'particular']);

// Procesar aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $doc_subido_id = $_POST['doc_subido_id'];
    $estado = $_POST['action'] == 'aprobar' ? 'aprobado' : 'rechazado';
    
    query("UPDATE documentos_subidos SET estado = ? WHERE id = ? AND id_postulante = ?", 
          [$estado, $doc_subido_id, $postulante_id]);
    
    // Verificar si todos los documentos están aprobados
    $pendientes = fetchOne("SELECT COUNT(*) as total FROM documentos_subidos 
                           WHERE id_postulante = ? AND estado != 'aprobado'", [$postulante_id]);
    
    if ($pendientes['total'] == 0) {
        query("UPDATE postulantes SET estado_proceso = 'documentos_revisados' WHERE id = ?", [$postulante_id]);
        $mensaje = "✅ Todos los documentos han sido aprobados";
    }
    
    registrarAuditoria('documentos', $estado, 'documento', $doc_subido_id, "Documento $estado para postulante ID $postulante_id");
    
    header('Location: ver_documentos.php?id=' . $postulante_id . '&mensaje=' . urlencode($mensaje ?? ''));
    exit;
}

$mensaje = isset($_GET['mensaje']) ? $_GET['mensaje'] : '';

// Contar documentos para estadísticas
$total_docs = count($documentos);
$docs_aprobados = 0;
foreach ($documentos as $doc) {
    if ($doc['estado'] == 'aprobado') {
        $docs_aprobados++;
    }
}
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
        .btn-success { background: #2e7d32; border: none; }
        .btn-success:hover { background: #388e3c; }
        .btn-danger { background: #c62828; border: none; }
        .btn-danger:hover { background: #b71c1c; }
        .text-primary-dark { color: #1a3a6b; }
        .card-documento {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #dee2e6;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        .card-documento.aprobado { border-left-color: #2e7d32; }
        .card-documento.pendiente { border-left-color: #f57c00; }
        .card-documento.rechazado { border-left-color: #c62828; }
        .badge-dni {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            background: #e8f0fe;
            color: #1a3a6b;
            margin-left: 5px;
        }
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
        .card-dashboard { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    </style>
</head>
<body>

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
        
        <div class="col-md-10">
            
            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4><i class="bi bi-files"></i> Documentos de <?php echo $postulante['nombres'] . ' ' . $postulante['apellido_paterno']; ?></h4>
                    <p class="text-muted">
                        Padre: <?php echo $postulante['padre_nombre'] . ' ' . $postulante['padre_apellidos']; ?> | 
                        Grado: <?php echo $postulante['grado']; ?> | 
                        Sede: <?php echo $postulante['sede']; ?>
                    </p>
                    <div class="mt-2">
                        <span class="badge bg-primary">Total: <?php echo $total_docs; ?></span>
                        <span class="badge bg-success">Aprobados: <?php echo $docs_aprobados; ?></span>
                        <span class="badge bg-warning">Pendientes: <?php echo $total_docs - $docs_aprobados; ?></span>
                    </div>
                </div>
                <a href="documentos.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>

            <div class="row">
                <?php if (empty($documentos)): ?>
                    <div class="col-12">
                        <div class="card-dashboard">
                            <p class="text-muted text-center py-4">No hay documentos requeridos para este postulante</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($documentos as $doc): 
                        $esta_subido = $doc['documento_subido_id'] ? true : false;
                        $esta_aprobado = $doc['estado'] == 'aprobado';
                        $esta_rechazado = $doc['estado'] == 'rechazado';
                        $clase_borde = 'pendiente';
                        if ($esta_aprobado) $clase_borde = 'aprobado';
                        elseif ($esta_rechazado) $clase_borde = 'rechazado';
                    ?>
                        <div class="col-md-6">
                            <div class="card-documento <?php echo $clase_borde; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php 
                                            if (strpos($doc['nombre_documento'], 'DNI') !== false) {
                                                if (strpos($doc['nombre_documento'], 'Anverso') !== false) {
                                                    echo 'DNI <span class="badge-dni">Anverso</span>';
                                                } elseif (strpos($doc['nombre_documento'], 'Reverso') !== false) {
                                                    echo 'DNI <span class="badge-dni">Reverso</span>';
                                                } else {
                                                    echo $doc['nombre_documento'];
                                                }
                                            } else {
                                                echo $doc['nombre_documento'];
                                            }
                                            ?>
                                            <?php if ($doc['obligatorio']): ?>
                                                <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo $esta_subido ? '📄 Subido' : '⏳ No subido'; ?>
                                        </small>
                                    </div>
                                    <?php if ($esta_subido): ?>
                                        <span class="badge bg-<?php 
                                            echo $doc['estado'] == 'aprobado' ? 'success' : 
                                                ($doc['estado'] == 'rechazado' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo strtoupper($doc['estado'] ?? 'pendiente'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($esta_subido && $doc['ruta']): ?>
                                    <div class="mt-2">
                                        <a href="<?php echo $doc['ruta']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                        <a href="<?php echo $doc['ruta']; ?>" download class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-download"></i> Descargar
                                        </a>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Subido: <?php echo date('d/m/Y H:i', strtotime($doc['fecha_subida'])); ?>
                                        </small>
                                    </div>
                                    
                                    <?php if ($doc['estado'] != 'aprobado'): ?>
                                        <form method="POST" class="mt-2">
                                            <input type="hidden" name="doc_subido_id" value="<?php echo $doc['documento_subido_id']; ?>">
                                            <button type="submit" name="action" value="aprobar" class="btn btn-sm btn-success">
                                                <i class="bi bi-check"></i> Aprobar
                                            </button>
                                            <button type="submit" name="action" value="rechazar" class="btn btn-sm btn-danger">
                                                <i class="bi bi-x"></i> Rechazar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="mt-2 text-success">
                                            <i class="bi bi-check-circle"></i> Documento aprobado
                                        </div>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <p class="text-muted mt-2">📄 No subido</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-3">
                <a href="documentos.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a Documentos
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>