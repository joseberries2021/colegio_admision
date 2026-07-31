<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'familia') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$postulante_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$postulante = fetchOne("SELECT p.*, g.nombre as grado, s.nombre as sede, n.nombre as nivel, u.usuario as codigo_familia
                        FROM postulantes p
                        JOIN grados g ON p.id_grado = g.id
                        JOIN sedes s ON p.id_sede = s.id
                        JOIN niveles n ON p.id_nivel = n.id
                        JOIN usuarios u ON p.id_usuario_padre = u.id
                        WHERE p.id = ? AND p.id_usuario_padre = ?", [$postulante_id, $user_id]);

if (!$postulante) {
    header('Location: index.php');
    exit;
}

// Definir los 7 pasos del proceso
$pasos = [
    1 => ['nombre' => 'Registro', 'icono' => '📄', 'estados' => ['registrado']],
    2 => ['nombre' => 'Documentos', 'icono' => '📎', 'estados' => ['documentos_pendientes', 'documentos_revisados']],
    3 => ['nombre' => 'Pago', 'icono' => '💳', 'estados' => ['pago_pendiente', 'pago_verificado']],
    4 => ['nombre' => 'Cita Psic.', 'icono' => '🧠', 'estados' => ['cita_pendiente', 'cita_aprobada']],
    5 => ['nombre' => 'Evaluación', 'icono' => '📝', 'estados' => ['evaluacion_pendiente', 'evaluacion_aprobada']],
    6 => ['nombre' => 'Matrícula', 'icono' => '✅', 'estados' => ['matriculado']],
    7 => ['nombre' => 'Finalizado', 'icono' => '🎓', 'estados' => ['finalizado']]
];

// Determinar paso actual
$estado_actual = $postulante['estado_proceso'];
$paso_actual = 1;
foreach ($pasos as $num => $paso) {
    if (in_array($estado_actual, $paso['estados'])) {
        $paso_actual = $num;
        break;
    }
}

// Mapeo de estados a acciones (botones)
$acciones = [
    'registrado' => ['mensaje' => 'Comienza subiendo tus documentos', 'btn' => 'Subir Documentos', 'link' => 'documentos.php?id='],
    'documentos_pendientes' => ['mensaje' => 'Tus documentos están en revisión', 'btn' => 'Ver Documentos', 'link' => 'documentos.php?id='],
    'documentos_revisados' => ['mensaje' => 'Documentos aprobados. Realiza el pago', 'btn' => 'Realizar Pago', 'link' => 'pago.php?id='],
    'pago_pendiente' => ['mensaje' => 'Pago en revisión', 'btn' => 'Ver Pago', 'link' => 'pago.php?id='],
    'pago_verificado' => ['mensaje' => 'Pago verificado. Agenda tu cita', 'btn' => 'Agendar Cita', 'link' => 'cita.php?id='],
    'cita_pendiente' => ['mensaje' => 'Cita agendada, espera confirmación', 'btn' => 'Ver Cita', 'link' => 'cita.php?id='],
    'cita_aprobada' => ['mensaje' => 'Cita aprobada. Espera evaluación', 'btn' => 'Ver Cita', 'link' => 'cita.php?id='],
    'evaluacion_pendiente' => ['mensaje' => 'Evaluación en proceso', 'btn' => 'Ver Evaluación', 'link' => 'evaluacion.php?id='],
    'evaluacion_aprobada' => ['mensaje' => 'Evaluación aprobada. Matrícula lista', 'btn' => 'Ver Matrícula', 'link' => 'matricula.php?id='],
    'matriculado' => ['mensaje' => '¡Felicidades! Tu hijo está matriculado', 'btn' => 'Ver Ficha', 'link' => 'ficha.php?id='],
    'finalizado' => ['mensaje' => 'Proceso completado', 'btn' => 'Ver Ficha', 'link' => 'ficha.php?id=']
];

$accion = $acciones[$estado_actual] ?? ['mensaje' => 'Proceso en curso', 'btn' => 'Ver Detalles', 'link' => 'detalle.php?id='];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Postulante</title>
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
        .btn-success {
            background: #2e7d32;
            border: none;
        }
        .btn-success:hover {
            background: #388e3c;
        }
        .text-primary-dark {
            color: #1a3a6b;
        }
        .text-secondary-dark {
            color: #f57c00;
        }
        .step-flow {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 20px 0;
            padding: 0;
            list-style: none;
            justify-content: center;
        }
        .step-flow .step {
            flex: 1;
            min-width: 90px;
            padding: 12px 8px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 11px;
            position: relative;
            border: 2px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .step-flow .step .icon {
            font-size: 22px;
            display: block;
            margin-bottom: 4px;
        }
        .step-flow .step .step-num {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #6c757d;
            color: white;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* Estados de los pasos */
        .step-flow .step.completed {
            border-color: #2e7d32;
            background: #e8f5e9;
        }
        .step-flow .step.completed .step-num {
            background: #2e7d32;
        }
        .step-flow .step.active {
            border-color: #1a3a6b;
            background: #e8f0fe;
            box-shadow: 0 0 0 3px rgba(26, 58, 107, 0.2);
        }
        .step-flow .step.active .step-num {
            background: #1a3a6b;
        }
        .step-flow .step.pending {
            border-color: #dee2e6;
            background: #f8f9fa;
            opacity: 0.6;
        }
        .step-flow .step.pending .step-num {
            background: #6c757d;
        }
        .step-flow .step.blocked {
            border-color: #dee2e6;
            background: #f8f9fa;
            opacity: 0.4;
            cursor: not-allowed;
        }
        .step-flow .step.blocked .step-num {
            background: #adb5bd;
        }
        /* Badge de estado */
        .status-badge {
            font-size: 14px;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        /* Tarjeta de información */
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 900px;
            margin: 0 auto;
        }
        .accion-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #1a3a6b;
        }
        .btn-pdf {
            background: #dc3545;
            border: none;
            font-weight: 600;
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
        }
        .btn-pdf:hover {
            background: #c82333;
            color: white;
        }
        /* Responsive */
        @media (max-width: 768px) {
            .step-flow .step {
                min-width: 60px;
                padding: 8px 4px;
                font-size: 9px;
            }
            .step-flow .step .icon {
                font-size: 16px;
            }
            .step-flow .step .step-num {
                width: 18px;
                height: 18px;
                font-size: 9px;
                top: -8px;
                right: -8px;
            }
            .info-card {
                padding: 16px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background: #1a3a6b;">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="../assets/img/LOGO%201000X1000%20EN%20BLANCO.png" alt="Logo" height="40" class="d-inline-block align-text-top">
            <span class="ms-2">Portal del Padre</span>
        </a>
        <div class="ms-auto">
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="info-card">
        <!-- Cabecera -->
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <h4 class="text-primary-dark mb-1">
                    <?php echo $postulante['nombres'] . ' ' . $postulante['apellido_paterno']; ?>
                </h4>
                <p class="text-muted mb-0">
                    <i class="bi bi-person-badge"></i> DNI: <?php echo $postulante['dni']; ?>
                </p>
                <p class="text-muted">
                    <i class="bi bi-book"></i> <?php echo $postulante['grado']; ?> 
                    - <i class="bi bi-building"></i> <?php echo $postulante['sede']; ?>
                </p>
            </div>
            <div class="text-end">
                <span class="status-badge bg-<?php 
                    echo $estado_actual == 'matriculado' ? 'success' : 
                        ($estado_actual == 'documentos_pendientes' ? 'warning' : 
                        ($estado_actual == 'pago_pendiente' ? 'danger' : 
                        ($estado_actual == 'cita_pendiente' ? 'info' : 'secondary'))); 
                ?> text-white">
                    <?php echo str_replace('_', ' ', $estado_actual); ?>
                </span>
                <p class="text-muted small mt-1">
                    <i class="bi bi-tag"></i> Código: <?php echo $postulante['codigo_familia']; ?>
                </p>
            </div>
        </div>

        <hr>

        <!-- 7 Pasos del proceso -->
        <div class="step-flow">
            <?php foreach ($pasos as $num => $paso): 
                $estado_paso = 'pending';
                if ($num < $paso_actual) {
                    $estado_paso = 'completed';
                } elseif ($num == $paso_actual) {
                    $estado_paso = 'active';
                } elseif ($num > $paso_actual) {
                    // Verificar si el paso está bloqueado o pendiente
                    $estado_paso = 'blocked';
                }
            ?>
                <div class="step <?php echo $estado_paso; ?>">
                    <span class="step-num"><?php echo $num; ?></span>
                    <span class="icon"><?php echo $paso['icono']; ?></span>
                    <?php echo $paso['nombre']; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <hr>

        <!-- Mensaje y acción según estado -->
        <div class="accion-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="text-primary-dark mb-1">
                        <i class="bi bi-info-circle"></i> Estado actual
                    </h6>
                    <p class="mb-0 text-muted"><?php echo $accion['mensaje']; ?></p>
                </div>
                <div class="mt-2 mt-sm-0">
                    <a href="<?php echo $accion['link'] . $postulante_id; ?>" class="btn btn-primary">
                        <i class="bi bi-arrow-right"></i> <?php echo $accion['btn']; ?>
                    </a>
                    <button onclick="generarPDF()" class="btn btn-pdf ms-2">
                        <i class="bi bi-file-pdf"></i> PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- Detalle del avance -->
        <div class="mt-4">
            <h6 class="text-primary-dark"><i class="bi bi-list-check"></i> Progreso</h6>
            <div class="progress" style="height: 12px;">
                <?php 
                $progreso = round(($paso_actual - 1) / 6 * 100);
                ?>
                <div class="progress-bar bg-<?php echo $progreso >= 100 ? 'success' : 'primary'; ?>" 
                     style="width: <?php echo $progreso; ?>%;">
                    <?php echo $progreso; ?>%
                </div>
            </div>
            <small class="text-muted">Paso <?php echo $paso_actual; ?> de 7</small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function generarPDF() {
    window.print();
}
</script>
</body>
</html>