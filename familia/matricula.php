<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'familia') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$postulante_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$postulante = fetchOne("
    SELECT p.*, g.nombre as grado, s.nombre as sede, u.usuario as codigo_familia
    FROM postulantes p
    JOIN grados g ON p.id_grado = g.id
    JOIN sedes s ON p.id_sede = s.id
    JOIN usuarios u ON p.id_usuario_padre = u.id
    WHERE p.id = ? AND p.id_usuario_padre = ?
", [$postulante_id, $user_id]);

if (!$postulante) {
    header('Location: index.php');
    exit;
}

// Obtener evaluación
$evaluacion = fetchOne("SELECT * FROM evaluaciones WHERE id_postulante = ?", [$postulante_id]);

if ($postulante['estado_proceso'] != 'matriculado') {
    header('Location: detalle.php?id=' . $postulante_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrícula Confirmada</title>
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
        .card-matricula {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 700px;
            margin: 0 auto;
            text-align: center;
        }
        .icono-exito {
            font-size: 80px;
            color: #2e7d32;
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
    <div class="card-matricula">
        <div class="icono-exito">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        
        <h2 class="text-primary-dark mt-3">¡Matrícula Confirmada!</h2>
        <p class="text-muted">
            <?php echo $postulante['nombres'] . ' ' . $postulante['apellido_paterno']; ?> 
            ha sido matriculado exitosamente para el año escolar 2027.
        </p>

        <hr>

        <div class="row text-start">
            <div class="col-md-6">
                <h6 class="text-primary-dark">Datos del Estudiante</h6>
                <p class="mb-1"><strong>Nombre:</strong> <?php echo $postulante['nombres'] . ' ' . $postulante['apellido_paterno'] . ' ' . $postulante['apellido_materno']; ?></p>
                <p class="mb-1"><strong>DNI:</strong> <?php echo $postulante['dni']; ?></p>
                <p class="mb-1"><strong>Grado:</strong> <?php echo $postulante['grado']; ?></p>
                <p class="mb-1"><strong>Sede:</strong> <?php echo $postulante['sede']; ?></p>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary-dark">Detalles de la Matrícula</h6>
                <p class="mb-1"><strong>Código Familiar:</strong> <?php echo $postulante['codigo_familia']; ?></p>
                <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></p>
                <?php if ($evaluacion): ?>
                    <p class="mb-1"><strong>Nota Evaluación:</strong> <?php echo $evaluacion['nota'] ?? '--'; ?></p>
                    <p class="mb-1"><strong>Estado:</strong> <span class="badge bg-success">Aprobado</span></p>
                <?php endif; ?>
            </div>
        </div>

        <hr>

        <div class="d-flex justify-content-center gap-3">
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-house"></i> Ir al Portal
            </a>
            <button onclick="window.print()" class="btn btn-pdf">
                <i class="bi bi-file-pdf"></i> Descargar PDF
            </button>
        </div>

        <div class="mt-4 p-3 bg-light rounded">
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle"></i> 
                En los próximos días recibirás un correo con la información completa del inicio de clases.
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>