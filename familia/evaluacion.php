<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'familia') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$postulante_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verificar que el postulante pertenece al padre
$postulante = fetchOne("
    SELECT p.*, g.nombre as grado, s.nombre as sede
    FROM postulantes p
    JOIN grados g ON p.id_grado = g.id
    JOIN sedes s ON p.id_sede = s.id
    WHERE p.id = ? AND p.id_usuario_padre = ?
", [$postulante_id, $user_id]);

if (!$postulante) {
    header('Location: index.php');
    exit;
}

// Obtener evaluación
$evaluacion = fetchOne("SELECT * FROM evaluaciones WHERE id_postulante = ?", [$postulante_id]);

// Obtener estado actual
$estado = $postulante['estado_proceso'];

// Definir colores y mensajes según estado
$config_estado = [
    'evaluacion_pendiente' => ['color' => 'warning', 'icono' => '⏳', 'mensaje' => 'Tu evaluación está en proceso. Pronto recibirás los resultados.'],
    'evaluacion_aprobada' => ['color' => 'success', 'icono' => '🎉', 'mensaje' => '¡Felicidades! Has aprobado la evaluación académica.'],
    'evaluacion_reprobada' => ['color' => 'danger', 'icono' => '😔', 'mensaje' => 'No has alcanzado la nota mínima requerida. Comunícate con administración.']
];

$info = $config_estado[$estado] ?? ['color' => 'secondary', 'icono' => '📋', 'mensaje' => 'Esperando evaluación.'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluación - Admisión 2027</title>
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
        .card-evaluacion {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 700px;
            margin: 0 auto;
        }
        .nota-display {
            font-size: 72px;
            font-weight: 900;
        }
        .nota-display.aprobado {
            color: #2e7d32;
        }
        .nota-display.reprobado {
            color: #c62828;
        }
        .nota-display.pendiente {
            color: #f57c00;
        }
        .icono-grande {
            font-size: 80px;
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
            <a href="detalle.php?id=<?php echo $postulante_id; ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="card-evaluacion">
        <!-- Cabecera -->
        <div class="text-center">
            <h4 class="text-primary-dark">
                <i class="bi bi-pencil-square"></i> Evaluación Académica
            </h4>
            <p class="text-muted">
                <?php echo $postulante['nombres'] . ' ' . $postulante['apellido_paterno']; ?> - 
                <?php echo $postulante['grado']; ?>
            </p>
            <hr>
        </div>

        <!-- Estado -->
        <div class="text-center mb-4">
            <div class="icono-grande"><?php echo $info['icono']; ?></div>
            <h5 class="text-<?php echo $info['color']; ?>">
                <?php echo str_replace('_', ' ', ucfirst($estado)); ?>
            </h5>
            <p class="text-muted"><?php echo $info['mensaje']; ?></p>
        </div>

        <!-- Nota -->
        <?php if ($evaluacion): ?>
            <div class="text-center">
                <h6 class="text-muted">Nota obtenida</h6>
                <div class="nota-display <?php 
                    echo ($evaluacion['estado'] ?? '') == 'aprobado' ? 'aprobado' : 
                        (($evaluacion['estado'] ?? '') == 'reprobado' ? 'reprobado' : 'pendiente'); 
                ?>">
                    <?php echo $evaluacion['nota'] ?? '--'; ?>
                </div>
                <span class="badge bg-<?php 
                    echo ($evaluacion['estado'] ?? '') == 'aprobado' ? 'success' : 
                        (($evaluacion['estado'] ?? '') == 'reprobado' ? 'danger' : 'warning'); 
                ?> p-2">
                    <?php echo strtoupper($evaluacion['estado'] ?? 'pendiente'); ?>
                </span>
            </div>

            <?php if ($evaluacion['observaciones']): ?>
                <div class="mt-3 p-3 bg-light rounded">
                    <h6><i class="bi bi-chat"></i> Observaciones</h6>
                    <p class="mb-0"><?php echo $evaluacion['observaciones']; ?></p>
                </div>
            <?php endif; ?>

            <div class="mt-3 text-center">
                <small class="text-muted">
                    Fecha de evaluación: <?php echo date('d/m/Y', strtotime($evaluacion['fecha_registro'])); ?>
                </small>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-hourglass-split" style="font-size: 50px; color: #dee2e6;"></i>
                <p class="text-muted mt-3">Aún no hay resultados disponibles.</p>
                <p class="text-muted small">La evaluación se publicará una vez sea registrada por el administrador.</p>
            </div>
        <?php endif; ?>

        <hr>

        <!-- Acciones -->
        <div class="d-flex justify-content-between">
            <a href="detalle.php?id=<?php echo $postulante_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php if ($evaluacion && $evaluacion['estado'] == 'aprobado'): ?>
                <a href="matricula.php?id=<?php echo $postulante_id; ?>" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Ver Matrícula
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>