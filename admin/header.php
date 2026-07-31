<?php
// ============================================
// INICIAR SESIÓN Y VERIFICAR - SIN SALIDA HTML ANTES
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admisión 2027 - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
        }
        .btn-primary {
            background: #1a3a6b;
            border: none;
        }
        .btn-primary:hover {
            background: #2d6bb8;
        }
        .text-primary-dark {
            color: #1a3a6b;
        }
        .sidebar {
            background: white;
            border-radius: 16px;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
        }
        .sidebar-link {
            color: #333;
            text-decoration: none;
            padding: 8px 15px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .sidebar-link:hover {
            background: #e8f0fe;
            color: #1a3a6b;
        }
        .sidebar-link.active {
            background: #1a3a6b;
            color: white;
        }
        .sidebar-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .nav-link-custom {
            color: #333;
            text-decoration: none;
            padding: 8px 15px 8px 35px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 13px;
        }
        .nav-link-custom:hover {
            background: #e8f0fe;
            color: #1a3a6b;
        }
        .nav-link-custom.active {
            background: #1a3a6b;
            color: white;
        }
        .nav-link-custom i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
        .sidebar-title {
            font-size: 11px;
            text-transform: uppercase;
            color: #6c757d;
            letter-spacing: 1px;
            padding: 8px 15px;
            font-weight: 700;
        }
        .card-dashboard {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .stat-card {
            border-radius: 15px;
            padding: 20px;
            color: white;
            transition: transform 0.3s;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .icon {
            font-size: 35px;
            opacity: 0.8;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: 900;
        }
        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
        }
        .bg-primary-dark {
            background: #1a3a6b;
        }
        .bg-success-dark {
            background: #2e7d32;
        }
        .bg-warning-dark {
            background: #f57c00;
        }
        .bg-danger-dark {
            background: #c62828;
        }
        .bg-info-dark {
            background: #00695c;
        }
        .bg-secondary-dark {
            background: #455a64;
        }
        .bg-purple-dark {
            background: #4a148c;
        }
        .badge-estado {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .sidebar-separator {
            border-top: 1px solid #dee2e6;
            margin: 10px 0;
        }
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
            <span class="badge bg-success me-3">Activo</span>
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
                
                <!-- 1. Estadísticas Generales -->
                <a href="index.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Estadísticas Generales
                </a>

                <!-- 2. Alumnos & Postulantes -->
                <a href="postulantes.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'postulantes.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Alumnos & Postulantes
                </a>

                <!-- 3. Alumnos Antiguos (Fase 3) -->
                <a href="alumnos_antiguos.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'alumnos_antiguos.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history"></i> Alumnos Antiguos (Fase 3)
                </a>

                <!-- 4. Revisión de Documentos -->
                <a href="documentos.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'documentos.php' ? 'active' : ''; ?>">
                    <i class="bi bi-files"></i> Revisión de Documentos
                </a>

                <!-- 5. Pagos (con submenú) -->
                <div class="sidebar-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['pagos.php', 'codigos_pago.php']) ? 'active' : ''; ?>" style="cursor: default;">
                    <i class="bi bi-credit-card"></i> Pagos
                </div>
                <a href="pagos.php" class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'pagos.php' ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle"></i> Validación de Pagos
                </a>
                <a href="codigos_pago.php" class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'codigos_pago.php' ? 'active' : ''; ?>">
                    <i class="bi bi-upc-scan"></i> Códigos de Pago
                </a>

                <!-- 6. Citas Psicológicas -->
                <a href="citas.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'citas.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar"></i> Citas Psicológicas
                </a>

                <!-- 7. Sedes, Distritos y Vacantes -->
                <a href="configuracion.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'configuracion.php' ? 'active' : ''; ?>">
                    <i class="bi bi-geo-alt"></i> Sedes, Distritos y Vacantes
                </a>

                <!-- 8. Configurar Documentos -->
                <a href="config_documentos.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'config_documentos.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i> Configurar Documentos
                </a>

                <!-- 9. Descuentos y Campañas -->
                <a href="descuentos.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'descuentos.php' ? 'active' : ''; ?>">
                    <i class="bi bi-percent"></i> Descuentos y Campañas
                </a>

                <!-- 10. Contratos y Reglamentos -->
                <a href="contratos.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'contratos.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-text"></i> Contratos y Reglamentos
                </a>

                <!-- 11. Bitácora de Auditoría -->
                <a href="seguridad.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'seguridad.php' ? 'active' : ''; ?>">
                    <i class="bi bi-shield-lock"></i> Bitácora de Auditoría
                </a>

                <!-- 12. Reportes & Descargas -->
                <a href="reportes.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart"></i> Reportes & Descargas
                </a>

                <hr>

                <!-- Control de Usuarios -->
                <div class="sidebar-title">Control de Usuarios</div>

                <!-- 13. Control de Usuarios -->
                <a href="control_usuarios.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'control_usuarios.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-gear"></i> Control de Usuarios
                </a>

                <!-- 14. Matriz de Permisos -->
                <a href="matriz_permisos.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'matriz_permisos.php' ? 'active' : ''; ?>">
                    <i class="bi bi-table"></i> Matriz de Permisos
                </a>
            </div>
        </div>
        <!-- INICIO DEL CONTENIDO -->
        <div class="col-md-10">