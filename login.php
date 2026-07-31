<?php
session_start();
require_once 'config/database.php';

// Si ya está logueado, redirigir según rol
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_tipo'] == 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: familia/index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM usuarios WHERE usuario = ? AND estado = 1";
    $user = fetchOne($sql, [$usuario]);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_tipo'] = $user['tipo'];
        $_SESSION['user_nombre'] = $user['nombres'] . ' ' . $user['apellidos'];
        
        if ($user['tipo'] == 'admin') {
            header('Location: admin/index.php');
        } else {
            header('Location: familia/index.php');
        }
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admisión 2027 - Colegio Católico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #1a3a6b 0%, #2d6bb8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 420px;
            margin: 0 auto;
        }
        .login-card img {
            max-height: 120px;  /* Antes era 80px, ahora 120px */
            margin-bottom: 20px;
            width: auto;
        }
        .btn-primary {
            background: #1a3a6b;
            border: none;
        }
        .btn-primary:hover {
            background: #2d6bb8;
        }
        .btn-outline-primary {
            color: #1a3a6b;
            border-color: #1a3a6b;
        }
        .btn-outline-primary:hover {
            background: #1a3a6b;
            color: white;
        }
        .text-primary-dark {
            color: #1a3a6b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="text-center">
                <img src="assets/img/LOGO%201000X1000%20EN%20COLOR.png" alt="Logo Colegio" class="img-fluid">
                <h4 class="mt-3 text-primary-dark">Sistema de Admisión 2027</h4>
                <p class="text-muted">Ingresa tus credenciales</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="usuario" class="form-control" placeholder="Ingresa tu usuario" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" placeholder="Ingresa tu contraseña" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">Ingresar</button>
            </form>
            
            <div class="text-center mt-3">
                <small class="text-muted">¿Eres nuevo? Regístrate como apoderado</small><br>
                <a href="familia/registro.php" class="text-primary">Crear cuenta</a>
            </div>
            
            <hr>
            <div class="text-center">
                <small class="text-muted">Credenciales de prueba:</small><br>
                <small><strong>admin</strong> / admin123</small><br>
                <small><strong>fam-001</strong> / 12345678</small>
            </div>
        </div>
    </div>
</body>
</html>