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

// Buscar códigos disponibles
$codigos_disponibles = fetchAll("SELECT * FROM codigos_pago WHERE usado = 0 ORDER BY id");

// Procesar selección de código
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $codigo_id = $_POST['codigo_id'];
    $codigo = fetchOne("SELECT * FROM codigos_pago WHERE id = ? AND usado = 0", [$codigo_id]);
    
    if ($codigo) {
        // Marcar código como usado
        query("UPDATE codigos_pago SET usado = 1, id_postulante = ?, fecha_asignacion = NOW() WHERE id = ?", 
              [$postulante_id, $codigo_id]);
        
        // Crear registro de pago
        insert("INSERT INTO pagos (id_postulante, id_codigo_pago, estado) VALUES (?, ?, 'pendiente')", 
               [$postulante_id, $codigo_id]);
        
        // Actualizar estado del postulante
        query("UPDATE postulantes SET estado_proceso = 'pago_pendiente' WHERE id = ?", [$postulante_id]);
        
        $mensaje = "✅ Código asignado correctamente. Ahora carga el voucher de pago.";
        $postulante = fetchOne("SELECT p.*, g.nombre as grado, s.nombre as sede
                                FROM postulantes p
                                JOIN grados g ON p.id_grado = g.id
                                JOIN sedes s ON p.id_sede = s.id
                                WHERE p.id = ? AND p.id_usuario_padre = ?", [$postulante_id, $user_id]);
    } else {
        $mensaje = "❌ El código seleccionado no está disponible";
    }
}

// Verificar si ya tiene pago
$pago = fetchOne("SELECT * FROM pagos WHERE id_postulante = ?", [$postulante_id]);
$estado = $postulante['estado_proceso'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago - Admisión 2027</title>
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
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <div class="card-body">
            <h4 class="text-primary-dark"><i class="bi bi-credit-card"></i> Pago de Admisión</h4>
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

            <?php if ($pago): ?>
                <!-- Ya tiene pago -->
                <div class="alert alert-info">
                    <p><strong>Estado del pago:</strong></p>
                    <span class="badge bg-<?php 
                        echo $pago['estado'] == 'verificado' ? 'success' : 
                            ($pago['estado'] == 'rechazado' ? 'danger' : 'warning'); 
                    ?>">
                        <?php echo strtoupper($pago['estado']); ?>
                    </span>
                    <?php if ($pago['voucher']): ?>
                        <div class="mt-2">
                            <a href="../uploads/vouchers/<?php echo $pago['voucher']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Ver Voucher
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($pago['estado'] == 'pendiente'): ?>
                    <form method="POST" enctype="multipart/form-data" action="subir_voucher.php">
                        <input type="hidden" name="postulante_id" value="<?php echo $postulante_id; ?>">
                        <input type="hidden" name="pago_id" value="<?php echo $pago['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Subir voucher de pago</label>
                            <input type="file" name="voucher" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                            <small class="text-muted">Formatos: JPG, PNG, PDF (máx. 5MB)</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Subir Voucher
                        </button>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <!-- Seleccionar código -->
                <?php if (empty($codigos_disponibles)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> No hay códigos de pago disponibles. Contacta con administración.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Selecciona un código de pago</label>
                            <select name="codigo_id" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($codigos_disponibles as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo $c['codigo']; ?> - S/. <?php echo number_format($c['monto'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Asignar Código
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <div class="mt-3">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>