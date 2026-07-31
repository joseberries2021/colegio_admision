<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$mensaje = '';
$error = '';
$seccion = isset($_GET['seccion']) ? $_GET['seccion'] : 'distritos';

// Obtener datos para selects en todo el archivo
$distritos = fetchAll("SELECT * FROM distritos ORDER BY nombre");
$niveles_select = fetchAll("
    SELECT n.*, d.nombre as distrito_nombre 
    FROM niveles n 
    LEFT JOIN distritos d ON n.id_distrito = d.id 
    WHERE n.id_distrito IS NOT NULL 
    ORDER BY d.nombre, n.nombre
");

// ============================================
// 1. GESTIÓN DE DISTRITOS (NIVEL 1)
// ============================================
if ($seccion == 'distritos' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['agregar_distrito'])) {
        $nombre = trim($_POST['nombre']);
        if (!empty($nombre)) {
            $existe = fetchOne("SELECT id FROM distritos WHERE nombre = ?", [$nombre]);
            if ($existe) {
                $error = "❌ El distrito '$nombre' ya existe";
            } else {
                insert("INSERT INTO distritos (nombre, estado) VALUES (?, 1)", [$nombre]);
                $mensaje = "✅ Distrito '$nombre' agregado correctamente";
            }
        }
    }
    
    if (isset($_POST['editar_distrito'])) {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);
        if (!empty($nombre)) {
            query("UPDATE distritos SET nombre = ? WHERE id = ?", [$nombre, $id]);
            $mensaje = "✅ Distrito actualizado correctamente";
        }
    }
    
    if (isset($_POST['eliminar_distrito']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $tiene_niveles = fetchOne("SELECT COUNT(*) as total FROM niveles WHERE id_distrito = ?", [$id]);
        $tiene_sedes = fetchOne("SELECT COUNT(*) as total FROM sedes WHERE id_distrito = ?", [$id]);
        $tiene_postulantes = fetchOne("SELECT COUNT(*) as total FROM postulantes WHERE id_distrito = ?", [$id]);
        
        if ($tiene_niveles['total'] > 0 || $tiene_sedes['total'] > 0 || $tiene_postulantes['total'] > 0) {
            $error = "❌ No se puede eliminar el distrito porque tiene datos asociados (niveles, sedes o postulantes)";
        } else {
            query("DELETE FROM distritos WHERE id = ?", [$id]);
            $mensaje = "✅ Distrito eliminado correctamente";
        }
    }
}

// ============================================
// 2. GESTIÓN DE NIVELES (NIVEL 2)
// ============================================
if ($seccion == 'niveles' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['agregar_nivel'])) {
        $nombre = trim($_POST['nombre']);
        $id_distrito = (int)$_POST['id_distrito'];
        if (!empty($nombre) && $id_distrito > 0) {
            $existe = fetchOne("SELECT id FROM niveles WHERE nombre = ? AND id_distrito = ?", [$nombre, $id_distrito]);
            if ($existe) {
                $error = "❌ El nivel '$nombre' ya existe en este distrito";
            } else {
                insert("INSERT INTO niveles (nombre, id_distrito, estado) VALUES (?, ?, 1)", [$nombre, $id_distrito]);
                $mensaje = "✅ Nivel '$nombre' agregado correctamente";
            }
        } else {
            $error = "❌ Complete todos los campos";
        }
    }
    
    if (isset($_POST['editar_nivel'])) {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);
        $id_distrito = (int)$_POST['id_distrito'];
        if (!empty($nombre) && $id_distrito > 0) {
            query("UPDATE niveles SET nombre = ?, id_distrito = ? WHERE id = ?", [$nombre, $id_distrito, $id]);
            $mensaje = "✅ Nivel actualizado correctamente";
        }
    }
    
    if (isset($_POST['eliminar_nivel']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $tiene_grados = fetchOne("SELECT COUNT(*) as total FROM grados WHERE id_nivel = ?", [$id]);
        $tiene_sedes = fetchOne("SELECT COUNT(*) as total FROM sedes WHERE id_nivel = ?", [$id]);
        $tiene_postulantes = fetchOne("SELECT COUNT(*) as total FROM postulantes WHERE id_nivel = ?", [$id]);
        
        if ($tiene_grados['total'] > 0 || $tiene_sedes['total'] > 0 || $tiene_postulantes['total'] > 0) {
            $error = "❌ No se puede eliminar el nivel porque tiene datos asociados (grados, sedes o postulantes)";
        } else {
            query("DELETE FROM niveles WHERE id = ?", [$id]);
            $mensaje = "✅ Nivel eliminado correctamente";
        }
    }
}

// ============================================
// 3. GESTIÓN DE GRADOS (NIVEL 3) - CORREGIDO
// ============================================
if ($seccion == 'grados' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['agregar_grado'])) {
        $nombre = trim($_POST['nombre']);
        $id_nivel = (int)$_POST['id_nivel'];
        if (!empty($nombre) && $id_nivel > 0) {
            $existe = fetchOne("SELECT id FROM grados WHERE nombre = ? AND id_nivel = ?", [$nombre, $id_nivel]);
            if ($existe) {
                $error = "❌ El grado '$nombre' ya existe en este nivel";
            } else {
                insert("INSERT INTO grados (nombre, id_nivel, estado) VALUES (?, ?, 1)", [$nombre, $id_nivel]);
                $mensaje = "✅ Grado '$nombre' agregado correctamente";
            }
        } else {
            $error = "❌ Complete todos los campos";
        }
    }
    
    if (isset($_POST['editar_grado'])) {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);
        $id_nivel = (int)$_POST['id_nivel'];
        if (!empty($nombre) && $id_nivel > 0) {
            query("UPDATE grados SET nombre = ?, id_nivel = ? WHERE id = ?", [$nombre, $id_nivel, $id]);
            $mensaje = "✅ Grado actualizado correctamente";
        }
    }
    
    if (isset($_POST['eliminar_grado']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $tiene_postulantes = fetchOne("SELECT COUNT(*) as total FROM postulantes WHERE id_grado = ?", [$id]);
        
        if ($tiene_postulantes['total'] > 0) {
            $error = "❌ No se puede eliminar el grado porque tiene postulantes asociados";
        } else {
            query("DELETE FROM grados WHERE id = ?", [$id]);
            $mensaje = "✅ Grado eliminado correctamente";
        }
    }
}

// ============================================
// 4. GESTIÓN DE SEDES (NIVEL 4)
// ============================================
if ($seccion == 'sedes' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['agregar_sede'])) {
        $nombre = trim($_POST['nombre']);
        $direccion = trim($_POST['direccion']);
        $id_distrito = (int)$_POST['id_distrito'];
        $id_nivel = (int)$_POST['id_nivel'];
        
        if (!empty($nombre) && $id_distrito > 0 && $id_nivel > 0) {
            $existe = fetchOne("SELECT id FROM sedes WHERE nombre = ? AND id_distrito = ? AND id_nivel = ?", 
                              [$nombre, $id_distrito, $id_nivel]);
            if ($existe) {
                $error = "❌ La sede '$nombre' ya existe para este distrito y nivel";
            } else {
                $distrito_nombre = fetchOne("SELECT nombre FROM distritos WHERE id = ?", [$id_distrito]);
                $distrito = $distrito_nombre ? $distrito_nombre['nombre'] : '';
                
                insert("INSERT INTO sedes (nombre, direccion, distrito, id_distrito, id_nivel, estado) 
                        VALUES (?, ?, ?, ?, ?, 1)", [$nombre, $direccion, $distrito, $id_distrito, $id_nivel]);
                $mensaje = "✅ Sede '$nombre' agregada correctamente";
            }
        } else {
            $error = "❌ Complete todos los campos";
        }
    }
    
    if (isset($_POST['editar_sede'])) {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);
        $direccion = trim($_POST['direccion']);
        $id_distrito = (int)$_POST['id_distrito'];
        $id_nivel = (int)$_POST['id_nivel'];
        
        if (!empty($nombre) && $id_distrito > 0 && $id_nivel > 0) {
            $distrito_nombre = fetchOne("SELECT nombre FROM distritos WHERE id = ?", [$id_distrito]);
            $distrito = $distrito_nombre ? $distrito_nombre['nombre'] : '';
            
            query("UPDATE sedes SET nombre = ?, direccion = ?, distrito = ?, id_distrito = ?, id_nivel = ? WHERE id = ?", 
                  [$nombre, $direccion, $distrito, $id_distrito, $id_nivel, $id]);
            $mensaje = "✅ Sede actualizada correctamente";
        }
    }
    
    if (isset($_POST['eliminar_sede']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $tiene_postulantes = fetchOne("SELECT COUNT(*) as total FROM postulantes WHERE id_sede = ?", [$id]);
        
        if ($tiene_postulantes['total'] > 0) {
            $error = "❌ No se puede eliminar la sede porque tiene postulantes asociados";
        } else {
            query("DELETE FROM sedes WHERE id = ?", [$id]);
            $mensaje = "✅ Sede eliminada correctamente";
        }
    }
}

// ============================================
// 5. GESTIÓN DE VACANTES
// ============================================
if ($seccion == 'vacantes' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_vacantes'])) {
    $id_sede = (int)$_POST['id_sede'];
    $id_nivel = (int)$_POST['id_nivel'];
    $id_grado = (int)$_POST['id_grado'];
    $total_vacantes = (int)$_POST['total_vacantes'];
    
    if ($id_sede > 0 && $id_nivel > 0 && $id_grado > 0 && $total_vacantes >= 0) {
        $existe = fetchOne("SELECT id FROM configuracion_vacantes WHERE id_sede = ? AND id_nivel = ? AND id_grado = ?", 
                          [$id_sede, $id_nivel, $id_grado]);
        if ($existe) {
            query("UPDATE configuracion_vacantes SET total_vacantes = ? WHERE id_sede = ? AND id_nivel = ? AND id_grado = ?", 
                  [$total_vacantes, $id_sede, $id_nivel, $id_grado]);
            $mensaje = "✅ Vacantes actualizadas correctamente";
        } else {
            insert("INSERT INTO configuracion_vacantes (id_sede, id_nivel, id_grado, total_vacantes, ocupados) 
                    VALUES (?, ?, ?, ?, 0)", [$id_sede, $id_nivel, $id_grado, $total_vacantes]);
            $mensaje = "✅ Vacantes configuradas correctamente";
        }
    } else {
        $error = "❌ Complete todos los campos";
    }
}

if ($seccion == 'vacantes' && isset($_GET['eliminar_vacante']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    query("DELETE FROM configuracion_vacantes WHERE id = ?", [$id]);
    $mensaje = "✅ Configuración de vacantes eliminada";
}

// ============================================
// OBTENER DATOS PARA MOSTRAR
// ============================================
$distritos = fetchAll("SELECT * FROM distritos ORDER BY nombre");
$niveles = fetchAll("
    SELECT n.*, d.nombre as distrito_nombre 
    FROM niveles n 
    LEFT JOIN distritos d ON n.id_distrito = d.id 
    WHERE n.id_distrito IS NOT NULL 
    ORDER BY d.nombre, n.nombre
");
$grados = fetchAll("
    SELECT g.*, 
           n.nombre as nivel_nombre, 
           d.nombre as distrito_nombre,
           d.id as distrito_id
    FROM grados g 
    JOIN niveles n ON g.id_nivel = n.id 
    LEFT JOIN distritos d ON n.id_distrito = d.id 
    WHERE n.id_distrito IS NOT NULL
    ORDER BY d.nombre, n.nombre, g.id
");
$sedes = fetchAll("
    SELECT s.*, 
           d.nombre as distrito_nombre, 
           n.nombre as nivel_nombre 
    FROM sedes s 
    LEFT JOIN distritos d ON s.id_distrito = d.id 
    LEFT JOIN niveles n ON s.id_nivel = n.id 
    WHERE s.id_distrito IS NOT NULL AND s.id_nivel IS NOT NULL
    ORDER BY d.nombre, n.nombre, s.nombre
");

$vacantes = fetchAll("
    SELECT v.*, 
           s.nombre as sede_nombre, 
           n.nombre as nivel_nombre, 
           g.nombre as grado_nombre,
           (SELECT COUNT(*) FROM postulantes WHERE id_sede = v.id_sede AND id_nivel = v.id_nivel AND id_grado = v.id_grado) as ocupados
    FROM configuracion_vacantes v
    JOIN sedes s ON v.id_sede = s.id
    JOIN niveles n ON v.id_nivel = n.id
    JOIN grados g ON v.id_grado = g.id
    ORDER BY s.nombre, n.nombre, g.nombre
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Admin</title>
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
        .btn-warning { background: #f57c00; border: none; color: white; }
        .btn-warning:hover { background: #e65100; color: white; }
        .text-primary-dark { color: #1a3a6b; }
        .card-dashboard { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .nav-link.active { background: #1a3a6b !important; color: white !important; }
        .nav-link { color: #1a3a6b; }
        .table-sortable th { cursor: pointer; user-select: none; }
        .table-sortable th:hover { background: #e8f0fe; }
        .filtro-busqueda { margin-bottom: 15px; }
        .filtro-busqueda input { max-width: 300px; }
        .badge-count { font-size: 11px; padding: 3px 8px; }
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
            <span class="text-white me-3"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['user_nombre'] ?? 'Admin'; ?></span>
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
                <a href="documentos.php" class="sidebar-link"><i class="bi bi-files"></i> Revisión de Documentos</a>
                <div class="sidebar-link" style="cursor: default;"><i class="bi bi-credit-card"></i> Pagos</div>
                <a href="pagos.php" class="nav-link-custom"><i class="bi bi-check-circle"></i> Validación de Pagos</a>
                <a href="codigos_pago.php" class="nav-link-custom"><i class="bi bi-upc-scan"></i> Códigos de Pago</a>
                <a href="citas.php" class="sidebar-link"><i class="bi bi-calendar"></i> Citas Psicológicas</a>
                <a href="configuracion.php" class="sidebar-link active"><i class="bi bi-geo-alt"></i> Sedes, Distritos y Vacantes</a>
                <a href="config_documentos.php" class="sidebar-link"><i class="bi bi-file-earmark-text"></i> Configurar Documentos</a>
                <a href="descuentos.php" class="sidebar-link"><i class="bi bi-percent"></i> Descuentos y Campañas</a>
                <a href="contratos.php" class="sidebar-link"><i class="bi bi-file-text"></i> Contratos y Reglamentos</a>
                <a href="seguridad.php" class="sidebar-link"><i class="bi bi-shield-lock"></i> Bitácora de Auditoría</a>
                <a href="reportes.php" class="sidebar-link"><i class="bi bi-bar-chart"></i> Reportes & Descargas</a>
                <hr>
                <div class="sidebar-title">Herramientas</div>
                <a href="limpieza_fabrica.php" class="sidebar-link"><i class="bi bi-eraser"></i> Limpieza de Fábrica</a>
                <hr>
                <div class="sidebar-title">Control de Usuarios</div>
                <a href="control_usuarios.php" class="sidebar-link"><i class="bi bi-person-gear"></i> Control de Usuarios</a>
                <a href="matriz_permisos.php" class="sidebar-link"><i class="bi bi-table"></i> Matriz de Permisos</a>
            </div>
        </div>
        
        <div class="col-md-10">
            <h4><i class="bi bi-gear"></i> Configuración del Sistema</h4>
            <p class="text-muted">Jerarquía: <strong>Distrito</strong> → <strong>Nivel Educativo</strong> → <strong>Grado</strong> → <strong>Sede</strong> → <strong>Vacantes</strong></p>

            <?php if ($mensaje): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Tabs de navegación -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $seccion == 'distritos' ? 'active' : ''; ?>" href="configuracion.php?seccion=distritos">
                        <i class="bi bi-geo-alt"></i> Distritos <span class="badge bg-primary badge-count"><?php echo count($distritos); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $seccion == 'niveles' ? 'active' : ''; ?>" href="configuracion.php?seccion=niveles">
                        <i class="bi bi-book"></i> Niveles <span class="badge bg-success badge-count"><?php echo count($niveles); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $seccion == 'grados' ? 'active' : ''; ?>" href="configuracion.php?seccion=grados">
                        <i class="bi bi-layers"></i> Grados <span class="badge bg-warning badge-count"><?php echo count($grados); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $seccion == 'sedes' ? 'active' : ''; ?>" href="configuracion.php?seccion=sedes">
                        <i class="bi bi-building"></i> Sedes <span class="badge bg-info badge-count"><?php echo count($sedes); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $seccion == 'vacantes' ? 'active' : ''; ?>" href="configuracion.php?seccion=vacantes">
                        <i class="bi bi-people-fill"></i> Vacantes <span class="badge bg-danger badge-count"><?php echo count($vacantes); ?></span>
                    </a>
                </li>
            </ul>

            <!-- ========================================== -->
            <!-- SECCIÓN: DISTRITOS (NIVEL 1) -->
            <!-- ========================================== -->
            <?php if ($seccion == 'distritos'): ?>
            <div class="card-dashboard">
                <h6 class="text-primary-dark"><i class="bi bi-plus-circle"></i> Agregar Distrito</h6>
                <hr>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="agregar_distrito" value="1">
                    <div class="col-md-8">
                        <input type="text" name="nombre" class="form-control" placeholder="Nombre del distrito" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus"></i> Agregar Distrito</button>
                    </div>
                </form>
                <hr>

                <div class="filtro-busqueda">
                    <input type="text" id="buscarDistrito" class="form-control form-control-sm" placeholder="🔍 Buscar distrito..." onkeyup="filtrarTabla('tablaDistritos', 'buscarDistrito')">
                </div>

                <h6 class="text-primary-dark"><i class="bi bi-list"></i> Lista de Distritos</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sortable" id="tablaDistritos">
                        <thead>
                            <tr>
                                <th onclick="ordenarTabla('tablaDistritos', 0)">Nombre <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaDistritos', 1)">Estado <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaDistritos', 2)">Niveles <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaDistritos', 3)">Sedes <i class="bi bi-arrow-up-down"></i></th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($distritos as $d): 
                                $niveles_count = fetchOne("SELECT COUNT(*) as total FROM niveles WHERE id_distrito = ?", [$d['id']]);
                                $sedes_count = fetchOne("SELECT COUNT(*) as total FROM sedes WHERE id_distrito = ?", [$d['id']]);
                            ?>
                            <tr>
                                <td><strong><?php echo $d['nombre']; ?></strong></td>
                                <td><span class="badge bg-<?php echo $d['estado'] ? 'success' : 'danger'; ?>"><?php echo $d['estado'] ? 'Activo' : 'Inactivo'; ?></span></td>
                                <td><?php echo $niveles_count['total']; ?></td>
                                <td><?php echo $sedes_count['total']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editarDistritoModal" 
                                            data-id="<?php echo $d['id']; ?>" data-nombre="<?php echo $d['nombre']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($niveles_count['total'] == 0 && $sedes_count['total'] == 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este distrito?')">
                                            <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                            <button type="submit" name="eliminar_distrito" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted" title="No se puede eliminar, tiene datos asociados"><i class="bi bi-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ========================================== -->
            <!-- SECCIÓN: NIVELES (NIVEL 2) -->
            <!-- ========================================== -->
            <?php if ($seccion == 'niveles'): ?>
            <div class="card-dashboard">
                <h6 class="text-primary-dark"><i class="bi bi-plus-circle"></i> Agregar Nivel Educativo</h6>
                <hr>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="agregar_nivel" value="1">
                    <div class="col-md-6">
                        <select name="id_distrito" class="form-select" required>
                            <option value="">Seleccionar Distrito</option>
                            <?php foreach ($distritos as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo $d['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Inicial, Primaria, Secundaria" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus"></i></button>
                    </div>
                </form>
                <hr>

                <div class="filtro-busqueda">
                    <input type="text" id="buscarNivel" class="form-control form-control-sm" placeholder="🔍 Buscar nivel..." onkeyup="filtrarTabla('tablaNiveles', 'buscarNivel')">
                </div>

                <h6 class="text-primary-dark"><i class="bi bi-list"></i> Lista de Niveles</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sortable" id="tablaNiveles">
                        <thead>
                            <tr>
                                <th onclick="ordenarTabla('tablaNiveles', 0)">Distrito <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaNiveles', 1)">Nombre <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaNiveles', 2)">Grados <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaNiveles', 3)">Sedes <i class="bi bi-arrow-up-down"></i></th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($niveles as $n): 
                                $grados_count = fetchOne("SELECT COUNT(*) as total FROM grados WHERE id_nivel = ?", [$n['id']]);
                                $sedes_count = fetchOne("SELECT COUNT(*) as total FROM sedes WHERE id_nivel = ?", [$n['id']]);
                            ?>
                            <tr>
                                <td><?php echo $n['distrito_nombre'] ?? 'Sin distrito'; ?></td>
                                <td><strong><?php echo $n['nombre']; ?></strong></td>
                                <td><?php echo $grados_count['total']; ?></td>
                                <td><?php echo $sedes_count['total']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editarNivelModal" 
                                            data-id="<?php echo $n['id']; ?>" data-nombre="<?php echo $n['nombre']; ?>" data-distrito="<?php echo $n['id_distrito']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($grados_count['total'] == 0 && $sedes_count['total'] == 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este nivel?')">
                                            <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                            <button type="submit" name="eliminar_nivel" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted" title="No se puede eliminar, tiene datos asociados"><i class="bi bi-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ========================================== -->
            <!-- SECCIÓN: GRADOS (NIVEL 3) -->
            <!-- ========================================== -->
            <?php if ($seccion == 'grados'): ?>
            <div class="card-dashboard">
                <h6 class="text-primary-dark"><i class="bi bi-plus-circle"></i> Agregar Grado</h6>
                <hr>
                <form method="POST" class="row g-3" id="formGrado">
                    <input type="hidden" name="agregar_grado" value="1">
                    
                    <div class="col-md-3">
                        <label class="form-label required">Distrito</label>
                        <select name="id_distrito_filtro" class="form-select" id="selectDistritoGrado" required>
                            <option value="">Seleccionar Distrito</option>
                            <?php foreach ($distritos as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo $d['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label required">Nivel</label>
                        <select name="id_nivel" class="form-select" id="selectNivelGrado" required>
                            <option value="">Primero selecciona un distrito</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label required">Nombre del Grado</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: 1er Grado, Inicial 3 años" required>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus"></i> Agregar Grado</button>
                    </div>
                </form>
                <hr>

                <div class="filtro-busqueda">
                    <input type="text" id="buscarGrado" class="form-control form-control-sm" placeholder="🔍 Buscar grado..." onkeyup="filtrarTabla('tablaGrados', 'buscarGrado')">
                </div>

                <h6 class="text-primary-dark"><i class="bi bi-list"></i> Lista de Grados</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sortable" id="tablaGrados">
                        <thead>
                            <tr>
                                <th onclick="ordenarTabla('tablaGrados', 0)">Distrito <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaGrados', 1)">Nivel <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaGrados', 2)">Nombre <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaGrados', 3)">Postulantes <i class="bi bi-arrow-up-down"></i></th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grados as $g): 
                                $postulantes_count = fetchOne("SELECT COUNT(*) as total FROM postulantes WHERE id_grado = ?", [$g['id']]);
                            ?>
                            <tr>
                                <td><?php echo $g['distrito_nombre'] ?? 'Sin distrito'; ?></td>
                                <td><?php echo $g['nivel_nombre']; ?></td>
                                <td><strong><?php echo $g['nombre']; ?></strong></td>
                                <td><?php echo $postulantes_count['total']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editarGradoModal" 
                                            data-id="<?php echo $g['id']; ?>" 
                                            data-nombre="<?php echo $g['nombre']; ?>" 
                                            data-nivel="<?php echo $g['id_nivel']; ?>"
                                            data-distrito="<?php echo $g['distrito_id'] ?? ''; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($postulantes_count['total'] == 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este grado?')">
                                            <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                                            <button type="submit" name="eliminar_grado" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted" title="No se puede eliminar, tiene postulantes asociados"><i class="bi bi-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ========================================== -->
            <!-- SECCIÓN: SEDES (NIVEL 4) -->
            <!-- ========================================== -->
            <?php if ($seccion == 'sedes'): ?>
            <div class="card-dashboard">
                <h6 class="text-primary-dark"><i class="bi bi-plus-circle"></i> Agregar Sede</h6>
                <hr>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="agregar_sede" value="1">
                    <div class="col-md-3">
                        <select name="id_distrito" class="form-select" required id="selectDistritoSede">
                            <option value="">Distrito</option>
                            <?php foreach ($distritos as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo $d['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="id_nivel" class="form-select" required id="selectNivelSede">
                            <option value="">Nivel</option>
                            <?php foreach ($niveles_select as $n): ?>
                                <option value="<?php echo $n['id']; ?>"><?php echo $n['nombre']; ?> (<?php echo $n['distrito_nombre'] ?? 'Sin distrito'; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="nombre" class="form-control" placeholder="Nombre de la sede" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus"></i></button>
                    </div>
                    <div class="col-md-12">
                        <input type="text" name="direccion" class="form-control" placeholder="Dirección de la sede">
                    </div>
                </form>
                <hr>

                <div class="filtro-busqueda">
                    <input type="text" id="buscarSede" class="form-control form-control-sm" placeholder="🔍 Buscar sede..." onkeyup="filtrarTabla('tablaSedes', 'buscarSede')">
                </div>

                <h6 class="text-primary-dark"><i class="bi bi-list"></i> Lista de Sedes</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sortable" id="tablaSedes">
                        <thead>
                            <tr>
                                <th onclick="ordenarTabla('tablaSedes', 0)">Distrito <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaSedes', 1)">Nivel <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaSedes', 2)">Nombre <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaSedes', 3)">Dirección <i class="bi bi-arrow-up-down"></i></th>
                                <th onclick="ordenarTabla('tablaSedes', 4)">Postulantes <i class="bi bi-arrow-up-down"></i></th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sedes as $s): 
                                $postulantes_count = fetchOne("SELECT COUNT(*) as total FROM postulantes WHERE id_sede = ?", [$s['id']]);
                            ?>
                            <tr>
                                <td><?php echo $s['distrito_nombre'] ?? 'Sin distrito'; ?></td>
                                <td><?php echo $s['nivel_nombre'] ?? 'Sin nivel'; ?></td>
                                <td><strong><?php echo $s['nombre']; ?></strong></td>
                                <td><?php echo $s['direccion']; ?></td>
                                <td><?php echo $postulantes_count['total']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editarSedeModal" 
                                            data-id="<?php echo $s['id']; ?>" data-nombre="<?php echo $s['nombre']; ?>" 
                                            data-direccion="<?php echo $s['direccion']; ?>" data-distrito="<?php echo $s['id_distrito']; ?>" 
                                            data-nivel="<?php echo $s['id_nivel']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($postulantes_count['total'] == 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta sede?')">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" name="eliminar_sede" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted" title="No se puede eliminar, tiene postulantes asociados"><i class="bi bi-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ========================================== -->
            <!-- SECCIÓN: VACANTES (NIVEL 5) -->
            <!-- ========================================== -->
            <?php if ($seccion == 'vacantes'): ?>
            <div class="card-dashboard">
                <h6 class="text-primary-dark"><i class="bi bi-people-fill"></i> Configurar Vacantes por Sede, Nivel y Grado</h6>
                <hr>
                
                <form method="POST" class="row g-3">
                    <input type="hidden" name="guardar_vacantes" value="1">
                    
                    <div class="col-md-3">
                        <label class="form-label">Sede</label>
                        <select name="id_sede" class="form-select" required>
                            <option value="">Seleccionar Sede</option>
                            <?php foreach ($sedes as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo $s['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Nivel</label>
                        <select name="id_nivel" class="form-select" required>
                            <option value="">Seleccionar Nivel</option>
                            <?php foreach ($niveles as $n): ?>
                                <option value="<?php echo $n['id']; ?>"><?php echo $n['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Grado</label>
                        <select name="id_grado" class="form-select" required>
                            <option value="">Seleccionar Grado</option>
                            <?php foreach ($grados as $g): ?>
                                <option value="<?php echo $g['id']; ?>"><?php echo $g['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Total Vacantes</label>
                        <input type="number" name="total_vacantes" class="form-control" placeholder="30" required min="0">
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i></button>
                    </div>
                </form>
                <hr>

                <h6 class="text-primary-dark"><i class="bi bi-list"></i> Vacantes Configuradas</h6>
                
                <?php if (empty($vacantes)): ?>
                    <p class="text-muted text-center py-3">No hay vacantes configuradas</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sortable" id="tablaVacantes">
                            <thead>
                                <tr>
                                    <th onclick="ordenarTabla('tablaVacantes', 0)">Sede <i class="bi bi-arrow-up-down"></i></th>
                                    <th onclick="ordenarTabla('tablaVacantes', 1)">Nivel <i class="bi bi-arrow-up-down"></i></th>
                                    <th onclick="ordenarTabla('tablaVacantes', 2)">Grado <i class="bi bi-arrow-up-down"></i></th>
                                    <th onclick="ordenarTabla('tablaVacantes', 3)">Total <i class="bi bi-arrow-up-down"></i></th>
                                    <th onclick="ordenarTabla('tablaVacantes', 4)">Ocupados <i class="bi bi-arrow-up-down"></i></th>
                                    <th onclick="ordenarTabla('tablaVacantes', 5)">Disponibles <i class="bi bi-arrow-up-down"></i></th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vacantes as $v): 
                                    $disponibles = $v['total_vacantes'] - $v['ocupados'];
                                ?>
                                <tr>
                                    <td><?php echo $v['sede_nombre']; ?></td>
                                    <td><?php echo $v['nivel_nombre']; ?></td>
                                    <td><?php echo $v['grado_nombre']; ?></td>
                                    <td><?php echo $v['total_vacantes']; ?></td>
                                    <td><?php echo $v['ocupados']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $disponibles > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $disponibles; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editarVacanteModal" 
                                                data-id="<?php echo $v['id']; ?>"
                                                data-sede="<?php echo $v['id_sede']; ?>"
                                                data-nivel="<?php echo $v['id_nivel']; ?>"
                                                data-grado="<?php echo $v['id_grado']; ?>"
                                                data-total="<?php echo $v['total_vacantes']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="configuracion.php?seccion=vacantes&eliminar_vacante=1&id=<?php echo $v['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('¿Eliminar esta configuración de vacantes?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODALES PARA EDITAR -->
<!-- ========================================== -->

<!-- Modal Editar Distrito -->
<div class="modal fade" id="editarDistritoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Distrito</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="editDistritoId">
                    <input type="hidden" name="editar_distrito" value="1">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" id="editDistritoNombre" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Nivel -->
<div class="modal fade" id="editarNivelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Nivel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="editNivelId">
                    <input type="hidden" name="editar_nivel" value="1">
                    <div class="mb-3">
                        <label class="form-label">Distrito</label>
                        <select name="id_distrito" id="editNivelDistrito" class="form-select" required>
                            <option value="">Seleccionar Distrito</option>
                            <?php foreach ($distritos as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo $d['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" id="editNivelNombre" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Grado -->
<div class="modal fade" id="editarGradoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Grado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="editGradoId">
                    <input type="hidden" name="editar_grado" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Distrito</label>
                        <select name="id_distrito" id="editGradoDistrito" class="form-select" required>
                            <option value="">Seleccionar Distrito</option>
                            <?php foreach ($distritos as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo $d['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nivel</label>
                        <select name="id_nivel" id="editGradoNivel" class="form-select" required>
                            <option value="">Primero selecciona un distrito</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre del Grado</label>
                        <input type="text" name="nombre" id="editGradoNombre" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Sede -->
<div class="modal fade" id="editarSedeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Sede</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="editSedeId">
                    <input type="hidden" name="editar_sede" value="1">
                    <div class="mb-3">
                        <label class="form-label">Distrito</label>
                        <select name="id_distrito" id="editSedeDistrito" class="form-select" required>
                            <option value="">Seleccionar Distrito</option>
                            <?php foreach ($distritos as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo $d['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nivel</label>
                        <select name="id_nivel" id="editSedeNivel" class="form-select" required>
                            <option value="">Seleccionar Nivel</option>
                            <?php foreach ($niveles_select as $n): ?>
                                <option value="<?php echo $n['id']; ?>"><?php echo $n['nombre']; ?> (<?php echo $n['distrito_nombre'] ?? 'Sin distrito'; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" id="editSedeNombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" id="editSedeDireccion" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Vacante -->
<div class="modal fade" id="editarVacanteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Vacante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="editVacanteId">
                    <input type="hidden" name="editar_vacante" value="1">
                    <div class="mb-3">
                        <label class="form-label">Sede</label>
                        <select name="id_sede" id="editVacanteSede" class="form-select" required>
                            <option value="">Seleccionar Sede</option>
                            <?php foreach ($sedes as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo $s['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nivel</label>
                        <select name="id_nivel" id="editVacanteNivel" class="form-select" required>
                            <option value="">Seleccionar Nivel</option>
                            <?php foreach ($niveles as $n): ?>
                                <option value="<?php echo $n['id']; ?>"><?php echo $n['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Grado</label>
                        <select name="id_grado" id="editVacanteGrado" class="form-select" required>
                            <option value="">Seleccionar Grado</option>
                            <?php foreach ($grados as $g): ?>
                                <option value="<?php echo $g['id']; ?>"><?php echo $g['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Vacantes</label>
                        <input type="number" name="total_vacantes" id="editVacanteTotal" class="form-control" required min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================
// FILTRAR TABLA
// ============================================
function filtrarTabla(tablaId, inputId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toUpperCase();
    const table = document.getElementById(tablaId);
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let found = false;
        for (let j = 0; j < cells.length - 1; j++) {
            const text = cells[j].textContent || cells[j].innerText;
            if (text.toUpperCase().indexOf(filter) > -1) {
                found = true;
                break;
            }
        }
        rows[i].style.display = found ? '' : 'none';
    }
}

// ============================================
// ORDENAR TABLA
// ============================================
let sortOrder = {};

function ordenarTabla(tablaId, colIndex) {
    const table = document.getElementById(tablaId);
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));

    if (!sortOrder[tablaId]) sortOrder[tablaId] = {};
    if (!sortOrder[tablaId][colIndex]) sortOrder[tablaId][colIndex] = 'asc';
    else if (sortOrder[tablaId][colIndex] === 'asc') sortOrder[tablaId][colIndex] = 'desc';
    else sortOrder[tablaId][colIndex] = 'asc';

    const order = sortOrder[tablaId][colIndex];

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

// ============================================
// MODALES - CARGAR DATOS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Datos de niveles para filtros
    const nivelesData = <?php echo json_encode($niveles_select); ?>;
    
    // Función para cargar niveles por distrito
    function cargarNivelesPorDistrito(distritoId, selectElement, selectedId) {
        selectElement.innerHTML = '<option value="">Primero selecciona un distrito</option>';
        if (distritoId) {
            const filtrados = nivelesData.filter(n => n.id_distrito == distritoId);
            filtrados.forEach(n => {
                const selected = (n.id == selectedId) ? 'selected' : '';
                selectElement.innerHTML += `<option value="${n.id}" ${selected}>${n.nombre}</option>`;
            });
            if (filtrados.length === 0) {
                selectElement.innerHTML = '<option value="">No hay niveles para este distrito</option>';
            }
        }
    }

    // ============================================
    // 1. FILTRO DE NIVELES POR DISTRITO PARA GRADOS (FORMULARIO)
    // ============================================
    const selectDistritoGrado = document.getElementById('selectDistritoGrado');
    const selectNivelGrado = document.getElementById('selectNivelGrado');
    
    if (selectDistritoGrado && selectNivelGrado) {
        selectDistritoGrado.addEventListener('change', function() {
            cargarNivelesPorDistrito(this.value, selectNivelGrado, null);
        });
    }

    // ============================================
    // 2. MODAL EDITAR GRADO
    // ============================================
    const modalGrado = document.getElementById('editarGradoModal');
    if (modalGrado) {
        modalGrado.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const gradoId = button.getAttribute('data-id');
            const gradoNombre = button.getAttribute('data-nombre');
            const nivelId = button.getAttribute('data-nivel');
            const distritoId = button.getAttribute('data-distrito') || '';
            
            document.getElementById('editGradoId').value = gradoId;
            document.getElementById('editGradoNombre').value = gradoNombre;
            
            // Seleccionar distrito
            const selectDistrito = document.getElementById('editGradoDistrito');
            selectDistrito.value = distritoId;
            
            // Cargar niveles según distrito
            const selectNivel = document.getElementById('editGradoNivel');
            cargarNivelesPorDistrito(distritoId, selectNivel, nivelId);
        });
    }

    // ============================================
    // 3. FILTRO DE NIVELES POR DISTRITO EN MODAL GRADO
    // ============================================
    const editGradoDistrito = document.getElementById('editGradoDistrito');
    const editGradoNivel = document.getElementById('editGradoNivel');
    
    if (editGradoDistrito && editGradoNivel) {
        editGradoDistrito.addEventListener('change', function() {
            cargarNivelesPorDistrito(this.value, editGradoNivel, null);
        });
    }

    // ============================================
    // 4. MODAL EDITAR SEDE - FILTRO DE NIVELES POR DISTRITO
    // ============================================
    const selectDistritoSede = document.getElementById('selectDistritoSede');
    const selectNivelSede = document.getElementById('selectNivelSede');
    
    if (selectDistritoSede && selectNivelSede) {
        const opcionesOriginales = selectNivelSede.innerHTML;
        
        selectDistritoSede.addEventListener('change', function() {
            const distritoId = this.value;
            selectNivelSede.innerHTML = '<option value="">Cargando...</option>';
            
            if (distritoId) {
                const filtrados = nivelesData.filter(n => n.id_distrito == distritoId);
                selectNivelSede.innerHTML = '<option value="">Seleccionar Nivel</option>';
                filtrados.forEach(n => {
                    selectNivelSede.innerHTML += `<option value="${n.id}">${n.nombre}</option>`;
                });
                if (filtrados.length === 0) {
                    selectNivelSede.innerHTML = '<option value="">No hay niveles para este distrito</option>';
                }
            } else {
                selectNivelSede.innerHTML = opcionesOriginales;
            }
        });
    }

    // ============================================
    // 5. MODALES - EDITAR DISTRITO, NIVEL, SEDE, VACANTE
    // ============================================
    // Editar Distrito
    const modalDistrito = document.getElementById('editarDistritoModal');
    if (modalDistrito) {
        modalDistrito.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('editDistritoId').value = button.getAttribute('data-id');
            document.getElementById('editDistritoNombre').value = button.getAttribute('data-nombre');
        });
    }

    // Editar Nivel
    const modalNivel = document.getElementById('editarNivelModal');
    if (modalNivel) {
        modalNivel.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('editNivelId').value = button.getAttribute('data-id');
            document.getElementById('editNivelNombre').value = button.getAttribute('data-nombre');
            document.getElementById('editNivelDistrito').value = button.getAttribute('data-distrito');
        });
    }

    // Editar Sede
    const modalSede = document.getElementById('editarSedeModal');
    if (modalSede) {
        modalSede.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('editSedeId').value = button.getAttribute('data-id');
            document.getElementById('editSedeNombre').value = button.getAttribute('data-nombre');
            document.getElementById('editSedeDireccion').value = button.getAttribute('data-direccion');
            document.getElementById('editSedeDistrito').value = button.getAttribute('data-distrito');
            document.getElementById('editSedeNivel').value = button.getAttribute('data-nivel');
        });
    }

    // Editar Vacante
    const modalVacante = document.getElementById('editarVacanteModal');
    if (modalVacante) {
        modalVacante.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('editVacanteId').value = button.getAttribute('data-id');
            document.getElementById('editVacanteSede').value = button.getAttribute('data-sede');
            document.getElementById('editVacanteNivel').value = button.getAttribute('data-nivel');
            document.getElementById('editVacanteGrado').value = button.getAttribute('data-grado');
            document.getElementById('editVacanteTotal').value = button.getAttribute('data-total');
        });
    }
});
</script>

</body>
</html>