<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';

$mensaje = '';
$error = '';
$confirmado = isset($_POST['confirmar']) && $_POST['confirmar'] == '1';

// ============================================
// PROCESAR LIMPIEZA TOTAL
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['limpiar'])) {
    if (!$confirmado) {
        $error = "❌ Debes marcar la casilla de confirmación para proceder";
    } else {
        try {
            // Desactivar verificación de claves foráneas
            query("SET FOREIGN_KEY_CHECKS = 0");
            
            // ============================================
            // 1. ELIMINAR TODOS LOS DATOS DE TODAS LAS TABLAS
            // ============================================
            
            $tablas = [
                // Tablas transaccionales
                'documentos_subidos',
                'pagos',
                'citas',
                'evaluaciones',
                'postulantes',
                'codigos_pago',
                'alumnos_antiguos',
                'solicitudes_ratificacion',
                'revisiones_manuales',
                'lotes_carga',
                'auditoria',
                'usuario_distritos',
                'usuario_sedes',
                // Tablas de configuración
                'config_documentos',
                'configuracion_vacantes',
                'configuracion_sedes',
                'grados',
                'niveles',
                'sedes',
                'distritos',
                'descuentos',
                'contratos',
                'permisos',
                'roles'
            ];
            
            foreach ($tablas as $tabla) {
                query("DELETE FROM $tabla");
            }
            
            // ============================================
            // 2. LIMPIAR USUARIOS (SOLO DEJAR admin Y fam-001)
            // ============================================
            
            // Eliminar todos los usuarios excepto admin
            query("DELETE FROM usuarios WHERE usuario != 'admin'");
            
            // Asegurar que admin existe
            $admin = fetchOne("SELECT id FROM usuarios WHERE usuario = 'admin'");
            if (!$admin) {
                $hashed_admin = password_hash('admin123', PASSWORD_DEFAULT);
                insert("INSERT INTO usuarios (usuario, password, tipo, nombres, apellidos, email, estado) 
                        VALUES ('admin', ?, 'admin', 'Administrador', 'Sistema', 'admin@colegio.com', 1)", 
                        [$hashed_admin]);
            } else {
                // Actualizar admin con datos correctos
                $hashed_admin = password_hash('admin123', PASSWORD_DEFAULT);
                query("UPDATE usuarios SET 
                        password = ?,
                        nombres = 'Administrador',
                        apellidos = 'Sistema',
                        email = 'admin@colegio.com',
                        tipo = 'admin',
                        estado = 1
                        WHERE usuario = 'admin'", [$hashed_admin]);
            }
            
            // Crear usuario padre de prueba (fam-001)
            $hashed = password_hash('12345678', PASSWORD_DEFAULT);
            $existe_fam = fetchOne("SELECT id FROM usuarios WHERE usuario = 'fam-001'");
            if (!$existe_fam) {
                insert("INSERT INTO usuarios (usuario, password, tipo, dni, nombres, apellidos, email, telefono, estado) 
                        VALUES ('fam-001', ?, 'familia', '12345678', 'Juan Carlos', 'Pérez Gómez', 'juan.perez@email.com', '987654321', 1)", 
                        [$hashed]);
            } else {
                query("UPDATE usuarios SET 
                        password = ?,
                        dni = '12345678',
                        nombres = 'Juan Carlos',
                        apellidos = 'Pérez Gómez',
                        email = 'juan.perez@email.com',
                        telefono = '987654321',
                        tipo = 'familia',
                        estado = 1
                        WHERE usuario = 'fam-001'", [$hashed]);
            }
            
            // ============================================
            // 3. RESETEAR AUTO_INCREMENT DE TODAS LAS TABLAS
            // ============================================
            
            $tablas_reset = [
                'config_documentos',
                'configuracion_vacantes',
                'configuracion_sedes',
                'grados',
                'niveles',
                'sedes',
                'distritos',
                'descuentos',
                'contratos',
                'permisos',
                'roles',
                'postulantes',
                'usuarios',
                'documentos_subidos',
                'pagos',
                'citas',
                'evaluaciones',
                'codigos_pago',
                'alumnos_antiguos',
                'solicitudes_ratificacion',
                'revisiones_manuales',
                'lotes_carga',
                'auditoria'
            ];
            
            foreach ($tablas_reset as $tabla) {
                query("ALTER TABLE $tabla AUTO_INCREMENT = 1");
            }
            
            // Reactivar verificación de claves foráneas
            query("SET FOREIGN_KEY_CHECKS = 1");
            
            $mensaje = "✅ Limpieza total completada. Todos los datos han sido eliminados. Solo se conservan admin y fam-001.";
            
        } catch (Exception $e) {
            query("SET FOREIGN_KEY_CHECKS = 1");
            $error = "❌ Error durante la limpieza: " . $e->getMessage();
        }
    }
}

// Obtener estadísticas actuales
$stats = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM usuarios) as total_usuarios,
        (SELECT COUNT(*) FROM postulantes) as total_postulantes,
        (SELECT COUNT(*) FROM documentos_subidos) as total_documentos,
        (SELECT COUNT(*) FROM pagos) as total_pagos,
        (SELECT COUNT(*) FROM citas) as total_citas,
        (SELECT COUNT(*) FROM evaluaciones) as total_evaluaciones,
        (SELECT COUNT(*) FROM auditoria) as total_auditoria,
        (SELECT COUNT(*) FROM distritos) as total_distritos,
        (SELECT COUNT(*) FROM sedes) as total_sedes,
        (SELECT COUNT(*) FROM niveles) as total_niveles,
        (SELECT COUNT(*) FROM grados) as total_grados,
        (SELECT COUNT(*) FROM config_documentos) as total_config_documentos
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpieza de Fábrica - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; }
        .btn-danger { background: #c62828; border: none; }
        .btn-danger:hover { background: #b71c1c; }
        .btn-primary { background: #1a3a6b; border: none; }
        .btn-primary:hover { background: #2d6bb8; }
        .text-primary-dark { color: #1a3a6b; }
        .card-limpieza { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto; }
        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; border-left: 4px solid #1a3a6b; }
        .stat-box .number { font-size: 28px; font-weight: 900; color: #1a3a6b; }
        .stat-box .label { font-size: 12px; color: #6c757d; }
        .danger-zone { border: 2px solid #c62828; border-radius: 16px; padding: 20px; background: #ffebee; }
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
                <a href="documentos.php" class="sidebar-link"><i class="bi bi-files"></i> Revisión de Documentos</a>
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
                <div class="sidebar-title">Herramientas</div>
                <a href="limpieza_fabrica.php" class="sidebar-link active"><i class="bi bi-eraser"></i> Limpieza de Fábrica</a>
                <hr>
                <div class="sidebar-title">Control de Usuarios</div>
                <a href="control_usuarios.php" class="sidebar-link"><i class="bi bi-person-gear"></i> Control de Usuarios</a>
                <a href="matriz_permisos.php" class="sidebar-link"><i class="bi bi-table"></i> Matriz de Permisos</a>
            </div>
        </div>
        
        <div class="col-md-10">
            <div class="card-limpieza">
                <h4 class="text-primary-dark text-center"><i class="bi bi-eraser"></i> Limpieza de Fábrica</h4>
                <p class="text-muted text-center">Esta herramienta eliminará <strong>TODOS</strong> los datos del sistema, excepto los usuarios esenciales.</p>
                <hr>

                <?php if ($mensaje): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Estadísticas actuales -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number"><?php echo $stats['total_usuarios'] ?? 0; ?></div>
                            <div class="label">Usuarios</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number"><?php echo $stats['total_postulantes'] ?? 0; ?></div>
                            <div class="label">Postulantes</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number"><?php echo $stats['total_documentos'] ?? 0; ?></div>
                            <div class="label">Documentos Subidos</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number"><?php echo $stats['total_auditoria'] ?? 0; ?></div>
                            <div class="label">Registros de Auditoría</div>
                        </div>
                    </div>
                </div>

                <!-- Segunda fila de estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number"><?php echo $stats['total_distritos'] ?? 0; ?></div>
                            <div class="label">Distritos</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number"><?php echo $stats['total_sedes'] ?? 0; ?></div>
                            <div class="label">Sedes</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number"><?php echo $stats['total_niveles'] ?? 0; ?></div>
                            <div class="label">Niveles</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number"><?php echo $stats['total_grados'] ?? 0; ?></div>
                            <div class="label">Grados</div>
                        </div>
                    </div>
                </div>

                <!-- Zona de peligro -->
                <div class="danger-zone">
                    <h6 class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> ¡Zona de Peligro!</h6>
                    <p class="small text-danger">Esta acción es <strong>IRREVERSIBLE</strong>. Eliminarás <strong>TODOS</strong> los datos del sistema.</p>
                    
                    <ul class="small text-muted">
                        <li><strong>Se eliminarán TODOS los datos de:</strong></li>
                        <li>📌 Postulantes, documentos, pagos, citas, evaluaciones</li>
                        <li>📌 Códigos de pago, alumnos antiguos, auditoría</li>
                        <li>📌 <strong>Distritos, Sedes, Niveles, Grados</strong></li>
                        <li>📌 <strong>Configuración de documentos, Descuentos, Contratos</strong></li>
                        <li>📌 Roles, permisos y relaciones de usuarios</li>
                        <br>
                        <li><strong>Se conservarán únicamente:</strong></li>
                        <li>✅ Usuario <code>admin</code> (admin123)</li>
                        <li>✅ Usuario <code>fam-001</code> (12345678)</li>
                    </ul>

                    <form method="POST" onsubmit="return confirm('¿Estás SEGURO de que quieres eliminar TODOS los datos? Esta acción es irreversible.')">
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="confirmar" name="confirmar" value="1" required>
                            <label class="form-check-label text-danger fw-bold" for="confirmar">
                                Sí, confirmo que deseo eliminar TODOS los datos del sistema
                            </label>
                        </div>
                        <button type="submit" name="limpiar" class="btn btn-danger w-100">
                            <i class="bi bi-eraser"></i> Ejecutar Limpieza Total
                        </button>
                    </form>
                </div>

                <div class="mt-4">
                    <h6 class="text-primary-dark"><i class="bi bi-info-circle"></i> Después de la limpieza</h6>
                    <p class="small text-muted">
                        El sistema quedará completamente vacío. Deberás configurar nuevamente:
                    </p>
                    <ul class="small text-muted">
                        <li>1. Distritos, Sedes, Niveles y Grados</li>
                        <li>2. Configuración de documentos requeridos</li>
                        <li>3. Roles y permisos (se crearán automáticamente)</li>
                        <li>4. Descuentos, contratos y demás configuraciones</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>