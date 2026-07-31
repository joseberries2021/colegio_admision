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
$postulante = fetchOne("SELECT p.*, g.nombre as grado, s.nombre as sede
                        FROM postulantes p
                        JOIN grados g ON p.id_grado = g.id
                        JOIN sedes s ON p.id_sede = s.id
                        WHERE p.id = ? AND p.id_usuario_padre = ?", [$postulante_id, $user_id]);

if (!$postulante) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$tipo_cita = isset($_GET['tipo']) ? $_GET['tipo'] : 'psicopedagogica';

// Verificar si ya tiene cita
$cita_existente = fetchOne("SELECT * FROM citas WHERE id_postulante = ? AND tipo = ?", [$postulante_id, $tipo_cita]);

// Procesar agendamiento
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $tipo = $_POST['tipo'];
    
    // Verificar disponibilidad
    $ocupado = fetchOne("SELECT id FROM citas WHERE fecha = ? AND hora = ? AND tipo = ? AND estado != 'cancelada'", 
                        [$fecha, $hora, $tipo]);
    
    if ($ocupado) {
        $mensaje = "❌ El horario seleccionado no está disponible. Elige otro.";
    } else {
        if ($cita_existente) {
            // Actualizar cita existente
            query("UPDATE citas SET fecha = ?, hora = ?, estado = 'pendiente' WHERE id = ?", 
                  [$fecha, $hora, $cita_existente['id']]);
        } else {
            // Crear nueva cita
            insert("INSERT INTO citas (id_postulante, tipo, fecha, hora, estado) VALUES (?, ?, ?, ?, 'pendiente')", 
                   [$postulante_id, $tipo, $fecha, $hora]);
        }
        
        // Actualizar estado del postulante
        query("UPDATE postulantes SET estado_proceso = 'cita_pendiente' WHERE id = ?", [$postulante_id]);
        
        $mensaje = "✅ Cita agendada correctamente. Espera la confirmación.";
        $cita_existente = fetchOne("SELECT * FROM citas WHERE id_postulante = ? AND tipo = ?", [$postulante_id, $tipo]);
    }
}

// Obtener fechas ocupadas para el calendario
$fechas_ocupadas = fetchAll("SELECT fecha, hora FROM citas WHERE tipo = ? AND estado != 'cancelada'", [$tipo_cita]);
$fechas_ocupadas_array = [];
foreach ($fechas_ocupadas as $f) {
    $fechas_ocupadas_array[] = $f['fecha'] . ' ' . $f['hora'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar Cita - Admisión 2027</title>
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
        .card-cita {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 700px;
            margin: 0 auto;
        }
        .horario-disponible {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 10px 15px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            background: white;
        }
        .horario-disponible:hover {
            border-color: #1a3a6b;
            background: #e8f0fe;
        }
        .horario-disponible.seleccionado {
            border-color: #2e7d32;
            background: #e8f5e9;
        }
        .horario-disponible.ocupado {
            border-color: #dc3545;
            background: #f8d7da;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .badge-estado {
            font-size: 14px;
            padding: 6px 16px;
            border-radius: 20px;
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
    <div class="card-cita">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="text-primary-dark"><i class="bi bi-calendar-check"></i> Agendar Cita</h4>
            <span class="badge-estado bg-<?php 
                echo $cita_existente ? ($cita_existente['estado'] == 'confirmada' ? 'success' : 'warning') : 'secondary';
            ?> text-white">
                <?php echo $cita_existente ? ucfirst($cita_existente['estado']) : 'Sin cita'; ?>
            </span>
        </div>
        
        <p class="text-muted">
            <?php echo $postulante['nombres'] . ' ' . $postulante['apellido_paterno']; ?> - 
            <?php echo $postulante['grado']; ?>
        </p>
        <hr>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo strpos($mensaje, '✅') !== false ? 'success' : 'danger'; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- Selector de tipo de cita -->
        <div class="mb-4">
            <label class="form-label fw-bold">Tipo de cita</label>
            <div class="d-flex gap-3">
                <a href="cita.php?id=<?php echo $postulante_id; ?>&tipo=psicopedagogica" 
                   class="btn <?php echo $tipo_cita == 'psicopedagogica' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="bi bi-brain"></i> Psicopedagógica
                </a>
                <a href="cita.php?id=<?php echo $postulante_id; ?>&tipo=academica" 
                   class="btn <?php echo $tipo_cita == 'academica' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="bi bi-pencil"></i> Académica
                </a>
            </div>
        </div>

        <?php if ($cita_existente && $cita_existente['estado'] != 'cancelada'): ?>
            <!-- Mostrar cita existente -->
            <div class="alert alert-info">
                <h6><i class="bi bi-info-circle"></i> Cita agendada</h6>
                <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cita_existente['fecha'])); ?></p>
                <p class="mb-1"><strong>Hora:</strong> <?php echo $cita_existente['hora']; ?></p>
                <p class="mb-0"><strong>Estado:</strong> 
                    <span class="badge bg-<?php 
                        echo $cita_existente['estado'] == 'confirmada' ? 'success' : 
                            ($cita_existente['estado'] == 'cancelada' ? 'danger' : 'warning'); 
                    ?>">
                        <?php echo ucfirst($cita_existente['estado']); ?>
                    </span>
                </p>
                <?php if ($cita_existente['estado'] == 'pendiente'): ?>
                    <div class="mt-2">
                        <small class="text-muted">Puedes reagendar tu cita seleccionando una nueva fecha/hora.</small>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Formulario de agendamiento -->
        <form method="POST">
            <input type="hidden" name="tipo" value="<?php echo $tipo_cita; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Fecha</label>
                    <input type="date" name="fecha" class="form-control" required 
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                           max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                           value="<?php echo $cita_existente ? $cita_existente['fecha'] : ''; ?>">
                    <small class="text-muted">Selecciona una fecha dentro de los próximos 30 días</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Hora</label>
                    <select name="hora" class="form-select" required>
                        <option value="">Seleccionar hora...</option>
                        <?php 
                        $horas = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', 
                                  '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'];
                        foreach ($horas as $hora): 
                            $ocupada = in_array(date('Y-m-d') . ' ' . $hora, $fechas_ocupadas_array);
                        ?>
                            <option value="<?php echo $hora; ?>" 
                                    <?php echo ($cita_existente && $cita_existente['hora'] == $hora) ? 'selected' : ''; ?>
                                    <?php echo $ocupada ? 'disabled style="color:#dc3545;"' : ''; ?>>
                                <?php echo $hora; ?>
                                <?php echo $ocupada ? ' (Ocupada)' : ' (Disponible)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Horario de atención: 9:00 - 12:00 y 14:00 - 17:00</small>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="detalle.php?id=<?php echo $postulante_id; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> 
                    <?php echo $cita_existente ? 'Reagendar Cita' : 'Agendar Cita'; ?>
                </button>
            </div>
        </form>

        <!-- Información adicional -->
        <div class="mt-4 p-3 bg-light rounded">
            <h6><i class="bi bi-info-circle"></i> Información importante</h6>
            <ul class="text-muted small mb-0">
                <li>La cita psicopedagógica tiene una duración aproximada de 30 minutos.</li>
                <li>La cita académica tiene una duración aproximada de 45 minutos.</li>
                <li>Recibirás un correo de confirmación una vez que la cita sea aprobada.</li>
                <li>Si no puedes asistir, puedes reagendar tu cita con 24 horas de anticipación.</li>
            </ul>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>