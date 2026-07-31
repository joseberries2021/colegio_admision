<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'familia') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$padre = fetchOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

$hijos = fetchAll("SELECT p.*, g.nombre as grado, s.nombre as sede, n.nombre as nivel
                   FROM postulantes p
                   JOIN grados g ON p.id_grado = g.id
                   JOIN sedes s ON p.id_sede = s.id
                   JOIN niveles n ON p.id_nivel = n.id
                   WHERE p.id_usuario_padre = ?", [$user_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Portal - Admisión 2027</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
        }
        .card-hijo {
            border-left: 4px solid #1a3a6b;
            transition: all 0.3s;
            cursor: pointer;
        }
        .card-hijo:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 12px;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        .step-flow {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin: 12px 0;
        }
        .step-flow .step {
            flex: 1;
            min-width: 55px;
            padding: 6px 4px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 9px;
            border: 1px solid #dee2e6;
        }
        .step-flow .step.active {
            background: #1a3a6b;
            color: white;
            border-color: #1a3a6b;
        }
        .step-flow .step.completed {
            background: #2e7d32;
            color: white;
            border-color: #2e7d32;
        }
        .step-flow .step .icon {
            font-size: 14px;
            display: block;
            margin-bottom: 2px;
        }
        .btn-primary {
            background: #1a3a6b;
            border: none;
            font-weight: 600;
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #2d6bb8;
            color: white;
        }
        .text-primary-dark {
            color: #1a3a6b;
        }
        .bg-primary-dark {
            background: #1a3a6b;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background: #1a3a6b;">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="../assets/img/LOGO%201000X1000%20EN%20BLANCO.png" alt="Logo" height="40" class="d-inline-block align-text-top">
            <span class="ms-2">Portal del Padre</span>
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3" style="font-size: 14px;">
                <i class="bi bi-person-circle"></i> <?php echo $_SESSION['user_nombre']; ?>
            </span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-primary-dark"><i class="bi bi-house"></i> Mis Hijos</h4>
        <a href="registro_hijo.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Registrar Nuevo Hijo</a>
    </div>
    
    <?php if (empty($hijos)): ?>
        <div class="alert alert-info text-center p-5" style="border-radius: 20px;">
            <i class="bi bi-emoji-frown" style="font-size: 50px; color: #1a3a6b;"></i>
            <h5 class="mt-3 text-primary-dark">No tienes hijos registrados</h5>
            <p class="text-muted">Haz clic en "Registrar Nuevo Hijo" para comenzar el proceso de admisión.</p>
            <a href="registro_hijo.php" class="btn btn-primary mt-2"><i class="bi bi-plus-circle"></i> Comenzar Registro</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($hijos as $hijo): ?>
                <div class="col-md-6 mb-4">
                    <div class="card card-hijo" onclick="window.location.href='detalle.php?id=<?php echo $hijo['id']; ?>'">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title"><?php echo $hijo['nombres'] . ' ' . $hijo['apellido_paterno']; ?></h5>
                                    <p class="text-muted small mb-0"><i class="bi bi-person-badge"></i> DNI: <?php echo $hijo['dni']; ?></p>
                                    <p class="text-muted small"><i class="bi bi-book"></i> <?php echo $hijo['grado']; ?> - <i class="bi bi-building"></i> <?php echo $hijo['sede']; ?></p>
                                </div>
                                <span class="status-badge bg-<?php 
                                    echo $hijo['estado_proceso'] == 'matriculado' ? 'success' : 
                                        ($hijo['estado_proceso'] == 'registrado' ? 'info' : 
                                        ($hijo['estado_proceso'] == 'documentos_pendientes' ? 'warning' : 
                                        ($hijo['estado_proceso'] == 'pago_pendiente' ? 'danger' : 'secondary'))); 
                                ?> text-white">
                                    <?php echo str_replace('_', ' ', $hijo['estado_proceso']); ?>
                                </span>
                            </div>
                            
                            <!-- 6 pasos resumidos -->
                            <div class="step-flow">
                                <div class="step <?php 
                                    echo in_array($hijo['estado_proceso'], ['registrado', 'documentos_pendientes', 'documentos_revisados', 'pago_pendiente', 'pago_verificado', 'cita_pendiente', 'cita_aprobada', 'evaluacion_pendiente', 'evaluacion_aprobada', 'matriculado']) ? 'completed' : '';
                                ?>">
                                    <span class="icon">📄</span> Reg.
                                </div>
                                <div class="step <?php 
                                    echo in_array($hijo['estado_proceso'], ['documentos_revisados', 'pago_pendiente', 'pago_verificado', 'cita_pendiente', 'cita_aprobada', 'evaluacion_pendiente', 'evaluacion_aprobada', 'matriculado']) ? 'completed' : (
                                        in_array($hijo['estado_proceso'], ['documentos_pendientes']) ? 'active' : ''
                                    );
                                ?>">
                                    <span class="icon">📎</span> Docs.
                                </div>
                                <div class="step <?php 
                                    echo in_array($hijo['estado_proceso'], ['pago_verificado', 'cita_pendiente', 'cita_aprobada', 'evaluacion_pendiente', 'evaluacion_aprobada', 'matriculado']) ? 'completed' : (
                                        in_array($hijo['estado_proceso'], ['pago_pendiente']) ? 'active' : ''
                                    );
                                ?>">
                                    <span class="icon">💳</span> Pago
                                </div>
                                <div class="step <?php 
                                    echo in_array($hijo['estado_proceso'], ['cita_aprobada', 'evaluacion_pendiente', 'evaluacion_aprobada', 'matriculado']) ? 'completed' : (
                                        in_array($hijo['estado_proceso'], ['cita_pendiente']) ? 'active' : ''
                                    );
                                ?>">
                                    <span class="icon">🧠</span> Cita
                                </div>
                                <div class="step <?php 
                                    echo in_array($hijo['estado_proceso'], ['evaluacion_aprobada', 'matriculado']) ? 'completed' : (
                                        in_array($hijo['estado_proceso'], ['evaluacion_pendiente']) ? 'active' : ''
                                    );
                                ?>">
                                    <span class="icon">📝</span> Eval.
                                </div>
                                <div class="step <?php 
                                    echo $hijo['estado_proceso'] == 'matriculado' ? 'completed' : '';
                                ?>">
                                    <span class="icon">✅</span> Mat.
                                </div>
                            </div>
                            
                            <div class="mt-2 text-end">
                                <small class="text-muted">
                                    <i class="bi bi-tag"></i> Código: <?php echo $padre['usuario']; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>