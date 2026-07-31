<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';

// Pagos pendientes
$pagos = fetchAll("
    SELECT p.*, po.nombres, po.apellido_paterno, po.dni, po.estado_proceso,
           u.nombres as padre_nombre, u.apellidos as padre_apellidos,
           g.nombre as grado, s.nombre as sede
    FROM pagos p
    JOIN postulantes po ON p.id_postulante = po.id
    JOIN usuarios u ON po.id_usuario_padre = u.id
    JOIN grados g ON po.id_grado = g.id
    JOIN sedes s ON po.id_sede = s.id
    WHERE p.estado = 'pendiente'
    ORDER BY p.fecha_pago DESC
");

// Verificar pago
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $pago_id = $_POST['pago_id'];
    $estado = $_POST['action'] == 'verificar' ? 'verificado' : 'rechazado';
    
    query("UPDATE pagos SET estado = ? WHERE id = ?", [$estado, $pago_id]);
    
    // Actualizar estado del postulante
    $pago = fetchOne("SELECT id_postulante FROM pagos WHERE id = ?", [$pago_id]);
    if ($pago && $estado == 'verificado') {
        query("UPDATE postulantes SET estado_proceso = 'pago_verificado' WHERE id = ?", [$pago['id_postulante']]);
    }
    
    header('Location: ver_pagos.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos Pendientes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
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
        <div class="ms-auto">
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-2">
            <div class="list-group">
                <a href="index.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="postulantes.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-people"></i> Postulantes
                </a>
                <a href="documentos.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-files"></i> Documentos
                </a>
                <a href="citas.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-calendar"></i> Citas
                </a>
                <a href="pagos.php" class="list-group-item list-group-item-action active">
                    <i class="bi bi-credit-card"></i> Pagos
                </a>
                <a href="codigos_pago.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-upc-scan"></i> Códigos de Pago
                </a>
                <a href="reportes.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-bar-chart"></i> Reportes
                </a>
                <a href="configuracion.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-gear"></i> Configuración
                </a>
            </div>
        </div>
        
        <div class="col-md-10">
            <h4><i class="bi bi-credit-card"></i> Pagos Pendientes de Verificar</h4>

            <?php if (empty($pagos)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> No hay pagos pendientes de verificar
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Postulante</th>
                                        <th>DNI</th>
                                        <th>Padre</th>
                                        <th>Grado</th>
                                        <th>Sede</th>
                                        <th>Voucher</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagos as $p): ?>
                                        <tr>
                                            <td><?php echo $p['nombres'] . ' ' . $p['apellido_paterno']; ?></td>
                                            <td><?php echo $p['dni']; ?></td>
                                            <td><?php echo $p['padre_nombre'] . ' ' . $p['padre_apellidos']; ?></td>
                                            <td><?php echo $p['grado']; ?></td>
                                            <td><?php echo $p['sede']; ?></td>
                                            <td>
                                                <?php if ($p['voucher']): ?>
                                                    <a href="../uploads/vouchers/<?php echo $p['voucher']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">No subido</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($p['fecha_pago'])); ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="pago_id" value="<?php echo $p['id']; ?>">
                                                    <button type="submit" name="action" value="verificar" class="btn btn-sm btn-success">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                    <button type="submit" name="action" value="rechazar" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>