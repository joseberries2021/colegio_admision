<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// LIMPIAR SESIÓN SI VIENE DE UN REGISTRO EXITOSO
// ============================================
if (isset($_GET['nuevo']) && $_GET['nuevo'] == '1') {
    unset($_SESSION['registro']);
    unset($_SESSION['registro_completo']);
    unset($_SESSION['credenciales']);
    unset($_SESSION['postulante_guardado']);
    header('Location: registro.php');
    exit;
}

require_once '../config/database.php';

// Si ya está logueado como familia, redirigir
if (isset($_SESSION['user_id']) && $_SESSION['user_tipo'] == 'familia') {
    header('Location: index.php');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = false;
$postulante_guardado = null;
$apoderado_guardado = null;

// Obtener datos para selects
$distritos = fetchAll("SELECT * FROM distritos WHERE estado = 1 ORDER BY nombre");

// Inicializar sesión de registro si no existe
if (!isset($_SESSION['registro'])) {
    $_SESSION['registro'] = [
        'tipo_alumno' => 'nuevo',
        'id_distrito' => '',
        'id_nivel' => '',
        'id_grado' => '',
        'id_sede' => '',
        'sede_nombre' => '',
        'sede_direccion' => '',
        'sede_distrito' => ''
    ];
}

// ============================================
// PROCESAR PASO 1 - TIPO DE ALUMNO & POSTULACIÓN
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $step == 1) {
    $_SESSION['registro']['tipo_alumno'] = $_POST['tipo_alumno'] ?? 'nuevo';
    $_SESSION['registro']['id_distrito'] = $_POST['id_distrito'] ?? '';
    $_SESSION['registro']['id_nivel'] = $_POST['id_nivel'] ?? '';
    $_SESSION['registro']['id_grado'] = $_POST['id_grado'] ?? '';
    $_SESSION['registro']['id_sede'] = $_POST['id_sede'] ?? '';
    
    if (!empty($_POST['id_sede'])) {
        $sede = fetchOne("SELECT * FROM sedes WHERE id = ?", [$_POST['id_sede']]);
        $_SESSION['registro']['sede_nombre'] = $sede['nombre'] ?? '';
        $_SESSION['registro']['sede_direccion'] = $sede['direccion'] ?? '';
        $_SESSION['registro']['sede_distrito'] = $sede['distrito'] ?? '';
    } else {
        $_SESSION['registro']['sede_nombre'] = '';
        $_SESSION['registro']['sede_direccion'] = '';
        $_SESSION['registro']['sede_distrito'] = '';
    }
    
    header('Location: registro.php?step=2');
    exit;
}

// ============================================
// PROCESAR PASO 2 - DATOS DEL APODERADO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $step == 2) {
    $_SESSION['registro']['apoderado'] = [
        'tipo_documento' => $_POST['tipo_documento'] ?? 'DNI',
        'numero_documento' => $_POST['numero_documento'] ?? '',
        'nombres' => $_POST['nombres'] ?? '',
        'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
        'apellido_materno' => $_POST['apellido_materno'] ?? '',
        'relacion' => $_POST['relacion'] ?? '',
        'whatsapp' => $_POST['whatsapp'] ?? '',
        'email' => $_POST['email'] ?? '',
        'pais' => $_POST['pais'] ?? 'Perú',
        'departamento' => $_POST['departamento'] ?? '',
        'provincia' => $_POST['provincia'] ?? '',
        'distrito' => $_POST['distrito'] ?? ''
    ];
    header('Location: registro.php?step=3');
    exit;
}

// ============================================
// PROCESAR PASO 3 - DATOS DEL POSTULANTE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $step == 3) {
    // Obtener el tipo de colegio del formulario
    $tipo_colegio = $_POST['tipo_colegio'] ?? 'particular';
    
    $_SESSION['registro']['postulante'] = [
        'tipo_documento' => $_POST['tipo_documento'] ?? 'DNI',
        'numero_documento' => $_POST['numero_documento'] ?? '',
        'genero' => $_POST['genero'] ?? '',
        'nombres' => $_POST['nombres'] ?? '',
        'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
        'apellido_materno' => $_POST['apellido_materno'] ?? '',
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
        'tipo_colegio' => $tipo_colegio,
        'colegio_procedencia' => $_POST['colegio_procedencia'] ?? '',
        'distrito_colegio' => $_POST['distrito_colegio'] ?? '',
        'seguro' => isset($_POST['seguro']) ? 1 : 0,
        'seguro_compania' => $_POST['seguro_compania'] ?? '',
        'diagnostico' => isset($_POST['diagnostico']) ? 1 : 0,
        'diagnostico_descripcion' => $_POST['diagnostico_descripcion'] ?? '',
        'religion' => $_POST['religion'] ?? '',
        'asiste_iglesia' => isset($_POST['asiste_iglesia']) ? 1 : 0,
        'iglesia_nombre' => $_POST['iglesia_nombre'] ?? '',
        'bautizado' => isset($_POST['bautizado']) ? 1 : 0,
        'primera_comunion' => isset($_POST['primera_comunion']) ? 1 : 0
    ];
    header('Location: registro.php?step=4');
    exit;
}

// ============================================
// PROCESAR PASO 4 - ENVIAR PRE-FICHA
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $step == 4) {
    if (!isset($_POST['acepto']) || $_POST['acepto'] != '1') {
        $errors[] = "Debe aceptar la declaración jurada para continuar";
    } else {
        try {
            $apoderado = $_SESSION['registro']['apoderado'];
            $postulante = $_SESSION['registro']['postulante'];
            
            $existe = fetchOne("SELECT id FROM postulantes WHERE dni = ?", [$postulante['numero_documento']]);
            if ($existe) {
                $errors[] = "El DNI del postulante ya está registrado";
            } else {
                $usuario = generarCodigoFamilia();
                $password = $apoderado['numero_documento'];
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                $id_usuario = insert(
                    "INSERT INTO usuarios (usuario, password, tipo, dni, nombres, apellidos, email, telefono, estado) 
                     VALUES (?, ?, 'familia', ?, ?, ?, ?, ?, 1)",
                    [
                        $usuario,
                        $hashed,
                        $apoderado['numero_documento'],
                        $apoderado['nombres'] . ' ' . $apoderado['apellido_paterno'],
                        $apoderado['apellido_paterno'] . ' ' . $apoderado['apellido_materno'],
                        $apoderado['email'],
                        $apoderado['whatsapp']
                    ]
                );
                
                $id_postulante = insert(
                    "INSERT INTO postulantes (
                        id_usuario_padre, nombres, apellido_paterno, apellido_materno, 
                        dni, fecha_nacimiento, id_sede, id_nivel, id_grado, 
                        colegio_procedencia, tipo_colegio,
                        seguro, diagnostico, religion, iglesia, bautizado, primera_comunion,
                        estado_proceso
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'registrado')",
                    [
                        $id_usuario,
                        $postulante['nombres'],
                        $postulante['apellido_paterno'],
                        $postulante['apellido_materno'],
                        $postulante['numero_documento'],
                        $postulante['fecha_nacimiento'],
                        $_SESSION['registro']['id_sede'],
                        $_SESSION['registro']['id_nivel'],
                        $_SESSION['registro']['id_grado'],
                        $postulante['colegio_procedencia'],
                        $postulante['tipo_colegio'],
                        $postulante['seguro'] ? 'Sí' : 'No',
                        $postulante['diagnostico'] ? $postulante['diagnostico_descripcion'] : 'No',
                        $postulante['religion'],
                        $postulante['iglesia_nombre'],
                        $postulante['bautizado'],
                        $postulante['primera_comunion']
                    ]
                );
                
                $_SESSION['registro_completo'] = true;
                $_SESSION['credenciales'] = [
                    'usuario' => $usuario,
                    'password' => $password,
                    'id_postulante' => $id_postulante
                ];
                $_SESSION['postulante_guardado'] = $_SESSION['registro'];
                $_SESSION['postulante_guardado']['id'] = $id_postulante;
                
                $sede_info = fetchOne("SELECT * FROM sedes WHERE id = ?", [$_SESSION['registro']['id_sede']]);
                $nivel_info = fetchOne("SELECT * FROM niveles WHERE id = ?", [$_SESSION['registro']['id_nivel']]);
                $grado_info = fetchOne("SELECT * FROM grados WHERE id = ?", [$_SESSION['registro']['id_grado']]);
                
                $_SESSION['postulante_guardado']['sede_nombre'] = $sede_info['nombre'] ?? '';
                $_SESSION['postulante_guardado']['sede_distrito'] = $sede_info['distrito'] ?? '';
                $_SESSION['postulante_guardado']['nivel_nombre'] = $nivel_info['nombre'] ?? '';
                $_SESSION['postulante_guardado']['grado_nombre'] = $grado_info['nombre'] ?? '';
                
                $success = true;
            }
        } catch (Exception $e) {
            $errors[] = "Error al guardar: " . $e->getMessage();
        }
    }
}

// ============================================
// AJAX - OBTENER NIVELES POR DISTRITO
// ============================================
if (isset($_GET['get_niveles']) && isset($_GET['id_distrito'])) {
    $niveles_filter = fetchAll("SELECT id, nombre FROM niveles WHERE id_distrito = ? AND estado = 1 ORDER BY nombre", [$_GET['id_distrito']]);
    echo json_encode($niveles_filter);
    exit;
}

// ============================================
// AJAX - OBTENER GRADOS POR NIVEL
// ============================================
if (isset($_GET['get_grados']) && isset($_GET['id_nivel'])) {
    $grados_filter = fetchAll("SELECT id, nombre FROM grados WHERE id_nivel = ? AND estado = 1 ORDER BY orden", [$_GET['id_nivel']]);
    echo json_encode($grados_filter);
    exit;
}

// ============================================
// AJAX - OBTENER SEDES POR DISTRITO Y NIVEL
// ============================================
if (isset($_GET['get_sedes']) && isset($_GET['id_distrito']) && isset($_GET['id_nivel'])) {
    $sedes_filter = fetchAll("
        SELECT s.* FROM sedes s 
        WHERE s.id_distrito = ? AND s.id_nivel = ? AND s.estado = 1 
        ORDER BY s.nombre
    ", [$_GET['id_distrito'], $_GET['id_nivel']]);
    echo json_encode($sedes_filter);
    exit;
}

// ============================================
// AJAX - OBTENER DIRECCIÓN DE SEDE
// ============================================
if (isset($_GET['get_sede_direccion']) && isset($_GET['id'])) {
    $sede = fetchOne("SELECT direccion FROM sedes WHERE id = ?", [$_GET['id']]);
    echo json_encode(['direccion' => $sede['direccion'] ?? 'No registrada']);
    exit;
}

// ============================================
// AJAX - BUSCAR APODERADO POR DNI
// ============================================
if (isset($_GET['buscar_apoderado']) && isset($_GET['dni'])) {
    $apoderado_data = fetchOne("
        SELECT nombres, apellidos, email, telefono 
        FROM usuarios 
        WHERE dni = ? AND tipo = 'familia'
    ", [$_GET['dni']]);
    echo json_encode($apoderado_data);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Admisión 2027</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; }
        .step-indicator { display: flex; justify-content: center; margin: 30px 0; gap: 0; position: relative; }
        .step-item { display: flex; flex-direction: column; align-items: center; padding: 0 30px; position: relative; flex: 1; max-width: 200px; }
        .step-number { width: 45px; height: 45px; border-radius: 50%; background: #e0e0e0; color: #999; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; transition: all 0.3s; border: 3px solid #e0e0e0; position: relative; z-index: 2; }
        .step-number.active { background: #1a3a6b; color: white; border-color: #1a3a6b; }
        .step-number.completed { background: #2e7d32; color: white; border-color: #2e7d32; }
        .step-label { font-size: 12px; font-weight: 600; color: #666; margin-top: 8px; text-align: center; }
        .step-label.active { color: #1a3a6b; }
        .step-line { position: absolute; top: 22px; left: 50%; right: -50%; height: 3px; background: #e0e0e0; z-index: 1; }
        .step-line.completed { background: #2e7d32; }
        .form-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 5px 30px rgba(0,0,0,0.1); max-width: 900px; margin: 0 auto 40px; }
        .btn-primary { background: #1a3a6b; border: none; font-weight: 600; padding: 12px 40px; }
        .btn-primary:hover { background: #2d6bb8; }
        .btn-success { background: #2e7d32; border: none; font-weight: 600; padding: 12px 40px; }
        .btn-success:hover { background: #388e3c; }
        .btn-danger { background: #dc3545; border: none; font-weight: 600; padding: 12px 40px; }
        .btn-danger:hover { background: #c82333; }
        .btn-outline-secondary:hover { background: #6c757d; color: white; }
        .required::after { content: '*'; color: #f57c00; margin-left: 4px; }
        .text-primary-dark { color: #1a3a6b; }
        .bg-soft-primary { background-color: #e8f0fe; }
        .form-control:disabled, .form-control[readonly] { background-color: #f8f9fa; }
        .radio-group { display: flex; gap: 20px; margin-top: 5px; }
        .radio-group .form-check { margin-right: 15px; }
        .badge-step { font-size: 11px; padding: 4px 12px; border-radius: 20px; background: #1a3a6b; color: white; }
        .card-vacante { background: #e8f5e9; border-radius: 10px; padding: 15px; border-left: 4px solid #2e7d32; }
        .sede-direccion { margin-top: 8px; font-size: 13px; color: #2e7d32; display: none; }
        .sede-direccion i { margin-right: 5px; }
        .sede-direccion.visible { display: block; }
        @media (max-width: 768px) {
            .step-item { padding: 0 10px; max-width: 80px; }
            .step-label { font-size: 9px; }
            .step-number { width: 35px; height: 35px; font-size: 14px; }
            .step-line { top: 17px; }
            .form-card { padding: 20px; }
            .radio-group { flex-direction: column; gap: 5px; }
        }
    </style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: #1a3a6b;">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="../assets/img/LOGO%201000X1000%20EN%20BLANCO.png" alt="Logo" height="50" class="d-inline-block align-text-top">
            <span class="ms-2 d-none d-sm-inline">Admisión 2027</span>
        </a>
        <div class="ms-auto">
            <a href="../login.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <!-- Step Indicator -->
    <div class="step-indicator">
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="step-item">
                <div class="step-number <?php echo $i == $step ? 'active' : ($i < $step ? 'completed' : ''); ?>">
                    <?php echo $i < $step ? '<i class="bi bi-check"></i>' : $i; ?>
                </div>
                <span class="step-label <?php echo $i == $step ? 'active' : ''; ?>">
                    <?php switch($i) { case 1: echo 'Postulación'; break; case 2: echo 'Apoderado / Contacto'; break; case 3: echo 'Postulante'; break; case 4: echo 'Enviar Pre-Ficha'; break; } ?>
                </span>
                <?php if ($i < 4): ?>
                    <div class="step-line <?php echo $i < $step ? 'completed' : ''; ?>"></div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <?php if ($success): ?>
        <!-- ========================================== -->
        <!-- PÁGINA DE ÉXITO -->
        <!-- ========================================== -->
        <div class="form-card">
            <?php $p = $_SESSION['postulante_guardado'] ?? []; $ap = $p['apoderado'] ?? []; $post = $p['postulante'] ?? []; ?>
            <div class="text-center py-3">
                <i class="bi bi-check-circle-fill" style="font-size: 80px; color: #2e7d32;"></i>
                <h2 class="mt-3 text-primary-dark">¡Solicitud Recibida con Éxito!</h2>
                <p class="text-muted">Su postulación ha sido registrada en el sistema de admisión del Colegio Juventud Científica. El pre-registro de vacante queda temporalmente asignado.</p>
            </div>
            <hr>
            <h5 class="text-primary-dark"><i class="bi bi-key"></i> ¡CUENTA DE ACCESO GENERADA!</h5>
            <p class="text-muted">Utilice las siguientes credenciales para realizar el seguimiento en tiempo real de la admisión:</p>
            <div class="row">
                <div class="col-md-6">
                    <div class="bg-light p-3 rounded">
                        <p class="mb-1"><strong>USUARIO DE ACCESO</strong></p>
                        <code class="bg-white p-2 d-block border rounded" style="font-size: 18px;"><?php echo $_SESSION['credenciales']['usuario']; ?></code>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="bg-light p-3 rounded">
                        <p class="mb-1"><strong>CONTRASEÑA (DNI APODERADO)</strong></p>
                        <code class="bg-white p-2 d-block border rounded" style="font-size: 18px;"><?php echo $_SESSION['credenciales']['password']; ?></code>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Ingresar al Portal Automáticamente (Auto-login)
                </a>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4">
                    <p><strong>Código de Expediente Familiar:</strong></p>
                    <h5 class="text-primary-dark"><?php echo $_SESSION['credenciales']['usuario']; ?></h5>
                </div>
                <div class="col-md-4">
                    <p><strong>Fecha de Registro:</strong></p>
                    <h5 class="text-primary-dark"><?php echo date('d \d\e F, Y (H:i)'); ?></h5>
                </div>
                <div class="col-md-4">
                    <p><strong>Sede del Postulante:</strong></p>
                    <h5 class="text-primary-dark"><?php echo $p['sede_nombre'] ?? 'N/A'; ?> (<?php echo $p['sede_distrito'] ?? ''; ?>)</h5>
                </div>
            </div>
            <hr>
            <h5 class="text-primary-dark"><i class="bi bi-list-check"></i> PRÓXIMOS PASOS DEL PROCESO DE SELECCIÓN</h5>
            <div class="row mt-3">
                <div class="col-md-4 text-center"><div class="bg-soft-primary p-3 rounded"><h1>1️⃣</h1><h6><strong>Carga de Documentos</strong></h6><p class="small text-muted">Debe ingresar al portal y cargar los archivos de identidad y estudios solicitados para su validación oficial.</p></div></div>
                <div class="col-md-4 text-center"><div class="bg-soft-primary p-3 rounded"><h1>2️⃣</h1><h6><strong>Entrevista Psicopedagógica</strong></h6><p class="small text-muted">Reserve su cita con nuestro psicólogo eligiendo día y hora dentro del calendario interactivo del portal.</p></div></div>
                <div class="col-md-4 text-center"><div class="bg-soft-primary p-3 rounded"><h1>3️⃣</h1><h6><strong>Matrícula & Aula</strong></h6><p class="small text-muted">Una vez verificado todo, confirme su matrícula en un clic para recibir la asignación automática de pabellón y aula.</p></div></div>
            </div>
            <hr>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="generar_pdf.php" target="_blank" class="btn btn-danger"><i class="bi bi-file-pdf"></i> Descargar PDF</a>
                <a href="registro_hijo.php" class="btn btn-outline-primary"><i class="bi bi-plus-circle"></i> Registrar Siguiente Hijo (Mismo Apoderado)</a>
                <a href="registro.php?nuevo=1" class="btn btn-outline-secondary"><i class="bi bi-person-plus"></i> Registrar Desde Cero (Nueva Familia)</a>
            </div>
        </div>

    <?php else: ?>
        <!-- ========================================== -->
        <!-- FORMULARIO -->
        <!-- ========================================== -->
        <div class="form-card">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="POST" id="registroForm">
                <!-- ========================================== -->
                <!-- PASO 1: TIPO DE ALUMNO & POSTULACIÓN -->
                <!-- ========================================== -->
                <?php if ($step == 1): ?>
                    <div class="step-content">
                        <h4 class="text-primary-dark"><i class="bi bi-person-check"></i> 1. Tipo de Alumno & Postulación 2027</h4>
                        <hr>
                        <h6 class="text-primary-dark">1. TIPO DE ALUMNO POSTULANTE</h6>
                        <div class="radio-group">
                            <div class="form-check">
                                <input type="radio" name="tipo_alumno" id="tipoNuevo" value="nuevo" class="form-check-input" <?php echo (($_SESSION['registro']['tipo_alumno'] ?? '') == 'nuevo' || empty($_SESSION['registro']['tipo_alumno'])) ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="tipoNuevo"><strong>Alumno Nuevo / Externo</strong><br><small class="text-muted">Postulante por primera vez al colegio</small></label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="tipo_alumno" id="tipoAntiguo" value="antiguo" class="form-check-input" <?php echo ($_SESSION['registro']['tipo_alumno'] ?? '') == 'antiguo' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tipoAntiguo"><strong>Alumno Antiguo / Ratificación</strong><br><small class="text-muted">Estudiante que ya cursa estudios con nosotros</small></label>
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-primary-dark">2. DATOS DE POSTULACIÓN 2027</h6>
                        <div class="row">
                            <div class="col-md-12 mb-3"><label class="form-label">Año del Proceso de Admisión</label><input type="text" class="form-control" value="2027" disabled></div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Distrito de Postulación</label>
                                <select name="id_distrito" class="form-select" id="selectDistrito" required>
                                    <option value="">-- Seleccione un Distrito --</option>
                                    <?php foreach ($distritos as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo ($_SESSION['registro']['id_distrito'] ?? '') == $d['id'] ? 'selected' : ''; ?>><?php echo $d['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Nivel Educativo</label>
                                <select name="id_nivel" class="form-select" id="selectNivel" required>
                                    <option value="">Seleccione un distrito primero</option>
                                    <?php if (!empty($_SESSION['registro']['id_distrito'])): ?>
                                        <?php $niveles_filter = fetchAll("SELECT id, nombre FROM niveles WHERE id_distrito = ? AND estado = 1 ORDER BY nombre", [$_SESSION['registro']['id_distrito']]);
                                        foreach ($niveles_filter as $n): ?>
                                            <option value="<?php echo $n['id']; ?>" <?php echo ($_SESSION['registro']['id_nivel'] ?? '') == $n['id'] ? 'selected' : ''; ?>><?php echo $n['nombre']; ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Grado a Postular</label>
                                <select name="id_grado" class="form-select" id="selectGrado" required>
                                    <option value="">Seleccione un nivel primero</option>
                                    <?php if (!empty($_SESSION['registro']['id_nivel'])): ?>
                                        <?php $grados_filter = fetchAll("SELECT id, nombre FROM grados WHERE id_nivel = ? AND estado = 1 ORDER BY orden", [$_SESSION['registro']['id_nivel']]);
                                        foreach ($grados_filter as $g): ?>
                                            <option value="<?php echo $g['id']; ?>" <?php echo ($_SESSION['registro']['id_grado'] ?? '') == $g['id'] ? 'selected' : ''; ?>><?php echo $g['nombre']; ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Sede / Local Escolar</label>
                                <select name="id_sede" class="form-select" id="selectSede" required>
                                    <option value="">Seleccione el grado a postular primero</option>
                                    <?php if (!empty($_SESSION['registro']['id_grado']) && !empty($_SESSION['registro']['id_nivel']) && !empty($_SESSION['registro']['id_distrito'])): ?>
                                        <?php $sedes_filter = fetchAll("SELECT s.* FROM sedes s WHERE s.id_distrito = ? AND s.id_nivel = ? AND s.estado = 1 ORDER BY s.nombre", [$_SESSION['registro']['id_distrito'], $_SESSION['registro']['id_nivel']]);
                                        foreach ($sedes_filter as $s): ?>
                                            <option value="<?php echo $s['id']; ?>" <?php echo ($_SESSION['registro']['id_sede'] ?? '') == $s['id'] ? 'selected' : ''; ?>><?php echo $s['nombre']; ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <?php if (!empty($_SESSION['registro']['id_sede'])): $sede_sel = fetchOne("SELECT * FROM sedes WHERE id = ?", [$_SESSION['registro']['id_sede']]); ?>
                                    <div class="sede-direccion visible" id="sedeDireccionContainer">
                                        <i class="bi bi-geo-alt"></i> <strong>DIRECCIÓN DE LA SEDE:</strong> 
                                        <span id="sedeDireccionTexto"><?php echo $sede_sel['direccion'] ?? 'No registrada'; ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="sede-direccion" id="sedeDireccionContainer" style="display: none;">
                                        <i class="bi bi-geo-alt"></i> <strong>DIRECCIÓN DE LA SEDE:</strong> 
                                        <span id="sedeDireccionTexto">Seleccione una sede</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-vacante mt-3">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <strong>Disponibilidad de Vacante Confirmada:</strong>
                            Hay vacantes disponibles para este grado y sede. Su lugar quedará temporalmente pre-reservado al enviar esta solicitud.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- PASO 2: DATOS DEL APODERADO DE CONTACTO -->
                <!-- ========================================== -->
                <?php if ($step == 2): ?>
                    <div class="step-content">
                        <h4 class="text-primary-dark"><i class="bi bi-person-badge"></i> 2. Datos del Apoderado de Contacto</h4>
                        <hr>
                        <p class="text-muted">Ingrese los datos del apoderado. Al escribir un DNI registrado, el sistema completará sus datos automáticamente. De lo contrario, rellene los campos manualmente.</p>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tipo de Documento</label>
                                <select name="tipo_documento" class="form-select">
                                    <option value="DNI" <?php echo ($_SESSION['registro']['apoderado']['tipo_documento'] ?? '') == 'DNI' ? 'selected' : ''; ?>>DNI</option>
                                    <option value="Pasaporte" <?php echo ($_SESSION['registro']['apoderado']['tipo_documento'] ?? '') == 'Pasaporte' ? 'selected' : ''; ?>>Pasaporte</option>
                                    <option value="Carnet Extranjeria" <?php echo ($_SESSION['registro']['apoderado']['tipo_documento'] ?? '') == 'Carnet Extranjeria' ? 'selected' : ''; ?>>Carnet de Extranjería</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Número de Documento</label>
                                <input type="text" name="numero_documento" id="apoderadoDni" class="form-control" placeholder="Ej. 45678912" required value="<?php echo $_SESSION['registro']['apoderado']['numero_documento'] ?? ''; ?>" onblur="buscarApoderado()">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Relación / Parentesco</label>
                                <select name="relacion" class="form-select" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="Padre" <?php echo ($_SESSION['registro']['apoderado']['relacion'] ?? '') == 'Padre' ? 'selected' : ''; ?>>Padre</option>
                                    <option value="Madre" <?php echo ($_SESSION['registro']['apoderado']['relacion'] ?? '') == 'Madre' ? 'selected' : ''; ?>>Madre</option>
                                    <option value="Tutor" <?php echo ($_SESSION['registro']['apoderado']['relacion'] ?? '') == 'Tutor' ? 'selected' : ''; ?>>Tutor</option>
                                    <option value="Otro Apoderado / Tutor" <?php echo ($_SESSION['registro']['apoderado']['relacion'] ?? '') == 'Otro Apoderado / Tutor' ? 'selected' : ''; ?>>Otro Apoderado / Tutor</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Nombres Completos</label>
                                <input type="text" name="nombres" id="apoderadoNombres" class="form-control" placeholder="Ej. Juan Carlos" required value="<?php echo $_SESSION['registro']['apoderado']['nombres'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Apellido Paterno</label>
                                <input type="text" name="apellido_paterno" id="apoderadoApellidoPaterno" class="form-control" placeholder="Ej. Quispe" required value="<?php echo $_SESSION['registro']['apoderado']['apellido_paterno'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Apellido Materno</label>
                                <input type="text" name="apellido_materno" id="apoderadoApellidoMaterno" class="form-control" placeholder="Ej. Mendoza" required value="<?php echo $_SESSION['registro']['apoderado']['apellido_materno'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">WhatsApp</label>
                                <input type="text" name="whatsapp" id="apoderadoWhatsapp" class="form-control" placeholder="Ej. 987654321" required value="<?php echo $_SESSION['registro']['apoderado']['whatsapp'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Correo Electrónico</label>
                                <input type="email" name="email" id="apoderadoEmail" class="form-control" placeholder="Ej. apoderado@gmail.com" required value="<?php echo $_SESSION['registro']['apoderado']['email'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">País</label>
                                <input type="text" name="pais" class="form-control" value="<?php echo $_SESSION['registro']['apoderado']['pais'] ?? 'Perú'; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Departamento</label>
                                <input type="text" name="departamento" class="form-control" placeholder="Ej. Lima" required value="<?php echo $_SESSION['registro']['apoderado']['departamento'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Provincia</label>
                                <input type="text" name="provincia" class="form-control" placeholder="Ej. Lima" required value="<?php echo $_SESSION['registro']['apoderado']['provincia'] ?? ''; ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label required">Distrito</label>
                                <input type="text" name="distrito" class="form-control" placeholder="Ej. El Agustino" required value="<?php echo $_SESSION['registro']['apoderado']['distrito'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- PASO 3: DATOS PERSONALES DEL POSTULANTE -->
                <!-- ========================================== -->
                <?php if ($step == 3): ?>
                    <div class="step-content">
                        <h4 class="text-primary-dark"><i class="bi bi-person-child"></i> 3. Datos Personales del Postulante</h4>
                        <hr>
                        <h6 class="text-primary-dark">1. INFORMACIÓN PERSONAL</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tipo de Documento</label>
                                <select name="tipo_documento" class="form-select">
                                    <option value="DNI" <?php echo ($_SESSION['registro']['postulante']['tipo_documento'] ?? '') == 'DNI' ? 'selected' : ''; ?>>DNI</option>
                                    <option value="Pasaporte" <?php echo ($_SESSION['registro']['postulante']['tipo_documento'] ?? '') == 'Pasaporte' ? 'selected' : ''; ?>>Pasaporte</option>
                                    <option value="Carnet Extranjeria" <?php echo ($_SESSION['registro']['postulante']['tipo_documento'] ?? '') == 'Carnet Extranjeria' ? 'selected' : ''; ?>>Carnet de Extranjería</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Número de Documento</label>
                                <input type="text" name="numero_documento" class="form-control" placeholder="Ej. 45678912" required value="<?php echo $_SESSION['registro']['postulante']['numero_documento'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Sexo</label>
                                <select name="genero" class="form-select" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="Masculino" <?php echo ($_SESSION['registro']['postulante']['genero'] ?? '') == 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="Femenino" <?php echo ($_SESSION['registro']['postulante']['genero'] ?? '') == 'Femenino' ? 'selected' : ''; ?>>Femenino</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Nombres Completos</label>
                                <input type="text" name="nombres" class="form-control" placeholder="Nombre completo" required value="<?php echo $_SESSION['registro']['postulante']['nombres'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Apellido Paterno</label>
                                <input type="text" name="apellido_paterno" class="form-control" placeholder="Apellido Paterno" required value="<?php echo $_SESSION['registro']['postulante']['apellido_paterno'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Apellido Materno</label>
                                <input type="text" name="apellido_materno" class="form-control" placeholder="Apellido materno" required value="<?php echo $_SESSION['registro']['postulante']['apellido_materno'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Fecha de Nacimiento</label>
                                <input type="date" name="fecha_nacimiento" class="form-control" required value="<?php echo $_SESSION['registro']['postulante']['fecha_nacimiento'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Tipo de Colegio</label>
                                <select name="tipo_colegio" class="form-select" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="particular" <?php echo ($_SESSION['registro']['postulante']['tipo_colegio'] ?? '') == 'particular' ? 'selected' : ''; ?>>Colegio Particular</option>
                                    <option value="estatal" <?php echo ($_SESSION['registro']['postulante']['tipo_colegio'] ?? '') == 'estatal' ? 'selected' : ''; ?>>Colegio Estatal</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Colegio de Procedencia (Opcional)</label>
                                <input type="text" name="colegio_procedencia" class="form-control" placeholder="Nombre del colegio anterior" value="<?php echo $_SESSION['registro']['postulante']['colegio_procedencia'] ?? ''; ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Distrito Colegio Procedencia (Opcional)</label>
                                <input type="text" name="distrito_colegio" class="form-control" placeholder="El Agustino" value="<?php echo $_SESSION['registro']['postulante']['distrito_colegio'] ?? ''; ?>">
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-primary-dark">2. SEGURO DE ACCIDENTES</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="radio-group">
                                    <div class="form-check"><input type="radio" name="seguro" id="seguroSi" value="1" class="form-check-input" <?php echo ($_SESSION['registro']['postulante']['seguro'] ?? 0) ? 'checked' : ''; ?>><label class="form-check-label" for="seguroSi">Sí</label></div>
                                    <div class="form-check"><input type="radio" name="seguro" id="seguroNo" value="0" class="form-check-input" <?php echo !($_SESSION['registro']['postulante']['seguro'] ?? 0) ? 'checked' : ''; ?>><label class="form-check-label" for="seguroNo">No</label></div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3" id="divSeguroCompania">
                                <label class="form-label">Nombre de la Compañía Aseguradora</label>
                                <input type="text" name="seguro_compania" class="form-control" placeholder="Rimac, Pacífico, Mapfre, La Positiva, etc." value="<?php echo $_SESSION['registro']['postulante']['seguro_compania'] ?? ''; ?>">
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-primary-dark">3. DIAGNÓSTICO MÉDICO O PSICOLÓGICO</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="radio-group">
                                    <div class="form-check"><input type="radio" name="diagnostico" id="diagnosticoSi" value="1" class="form-check-input" <?php echo ($_SESSION['registro']['postulante']['diagnostico'] ?? 0) ? 'checked' : ''; ?>><label class="form-check-label" for="diagnosticoSi">Sí</label></div>
                                    <div class="form-check"><input type="radio" name="diagnostico" id="diagnosticoNo" value="0" class="form-check-input" <?php echo !($_SESSION['registro']['postulante']['diagnostico'] ?? 0) ? 'checked' : ''; ?>><label class="form-check-label" for="diagnosticoNo">No</label></div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3" id="divDiagnostico">
                                <label class="form-label">Escríba cuál</label>
                                <input type="text" name="diagnostico_descripcion" class="form-control" placeholder="Describa el diagnóstico" value="<?php echo $_SESSION['registro']['postulante']['diagnostico_descripcion'] ?? ''; ?>">
                            </div>
                        </div>
                        <hr>
                        <h6 class="text-primary-dark">4. INFORMACIÓN RELIGIOSA DEL MENOR</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Religión del Menor</label>
                                <select name="religion" class="form-select">
                                    <option value="">Seleccione una Religión</option>
                                    <option value="Católica" <?php echo ($_SESSION['registro']['postulante']['religion'] ?? '') == 'Católica' ? 'selected' : ''; ?>>Católica</option>
                                    <option value="Cristiana" <?php echo ($_SESSION['registro']['postulante']['religion'] ?? '') == 'Cristiana' ? 'selected' : ''; ?>>Cristiana</option>
                                    <option value="Evangélica" <?php echo ($_SESSION['registro']['postulante']['religion'] ?? '') == 'Evangélica' ? 'selected' : ''; ?>>Evangélica</option>
                                    <option value="Otra" <?php echo ($_SESSION['registro']['postulante']['religion'] ?? '') == 'Otra' ? 'selected' : ''; ?>>Otra</option>
                                    <option value="Ninguna" <?php echo ($_SESSION['registro']['postulante']['religion'] ?? '') == 'Ninguna' ? 'selected' : ''; ?>>Ninguna</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">¿Asiste a alguna iglesia?</label>
                                <div class="radio-group">
                                    <div class="form-check"><input type="radio" name="asiste_iglesia" id="iglesiaSi" value="1" class="form-check-input" <?php echo ($_SESSION['registro']['postulante']['asiste_iglesia'] ?? 0) ? 'checked' : ''; ?>><label class="form-check-label" for="iglesiaSi">Sí</label></div>
                                    <div class="form-check"><input type="radio" name="asiste_iglesia" id="iglesiaNo" value="0" class="form-check-input" <?php echo !($_SESSION['registro']['postulante']['asiste_iglesia'] ?? 0) ? 'checked' : ''; ?>><label class="form-check-label" for="iglesiaNo">No</label></div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3" id="divIglesia">
                                <label class="form-label">Nombre de la Iglesia</label>
                                <input type="text" name="iglesia_nombre" class="form-control" placeholder="Nombre de la iglesia" value="<?php echo $_SESSION['registro']['postulante']['iglesia_nombre'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check"><input type="checkbox" name="bautizado" class="form-check-input" id="checkBautizado" <?php echo ($_SESSION['registro']['postulante']['bautizado'] ?? 0) ? 'checked' : ''; ?>><label class="form-check-label" for="checkBautizado">Está Bautizado(a)?</label></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check"><input type="checkbox" name="primera_comunion" class="form-check-input" id="checkComunion" <?php echo ($_SESSION['registro']['postulante']['primera_comunion'] ?? 0) ? 'checked' : ''; ?>><label class="form-check-label" for="checkComunion">Hizo la Primera Comunión?</label></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- PASO 4: RESUMEN Y ENVÍO DE PRE-FICHA -->
                <!-- ========================================== -->
                <?php if ($step == 4): ?>
                    <div class="step-content">
                        <h4 class="text-primary-dark"><i class="bi bi-check-circle"></i> 4. Resumen y Envío de Pre-ficha</h4>
                        <hr>
                        <p class="text-muted">Por favor, verifique que los datos básicos ingresados sean correctos. Luego de la aprobación del Administrador, podrá completar el resto de la información.</p>
                        <?php $ap = $_SESSION['registro']['apoderado'] ?? []; $post = $_SESSION['registro']['postulante'] ?? []; $sede_nombre = $_SESSION['registro']['sede_nombre'] ?? ''; $sede_distrito = $_SESSION['registro']['sede_distrito'] ?? ''; $nivel_nombre = ''; $grado_nombre = ''; if (!empty($_SESSION['registro']['id_nivel'])) { $n = fetchOne("SELECT nombre FROM niveles WHERE id = ?", [$_SESSION['registro']['id_nivel']]); $nivel_nombre = $n['nombre'] ?? ''; } if (!empty($_SESSION['registro']['id_grado'])) { $g = fetchOne("SELECT nombre FROM grados WHERE id = ?", [$_SESSION['registro']['id_grado']]); $grado_nombre = $g['nombre'] ?? ''; } ?>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary-dark">DATOS DEL POSTULANTE</h6>
                                <div class="bg-soft-primary p-3 rounded">
                                    <p><strong>Nombres y Apellidos:</strong> <?php echo ($post['apellido_paterno'] ?? '') . ' ' . ($post['apellido_materno'] ?? '') . ', ' . ($post['nombres'] ?? ''); ?></p>
                                    <p><strong>Documento de Identidad:</strong> <?php echo ($post['tipo_documento'] ?? 'DNI') . ' - ' . ($post['numero_documento'] ?? ''); ?></p>
                                    <p><strong>Sede de Postulación:</strong> <?php echo $sede_nombre . ' (' . $sede_distrito . ')'; ?></p>
                                    <p><strong>Nivel y Grado:</strong> <?php echo $nivel_nombre . ' - ' . $grado_nombre; ?></p>
                                    <p><strong>Tipo de Colegio:</strong> <?php echo ucfirst($post['tipo_colegio'] ?? 'particular'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary-dark">APODERADO DE CONTACTO</h6>
                                <div class="bg-soft-primary p-3 rounded">
                                    <p><strong>Nombre Completo:</strong> <?php echo ($ap['apellido_paterno'] ?? '') . ' ' . ($ap['apellido_materno'] ?? '') . ', ' . ($ap['nombres'] ?? ''); ?></p>
                                    <p><strong>WhatsApp:</strong> <?php echo $ap['whatsapp'] ?? ''; ?></p>
                                    <p><strong>Correo Electrónico:</strong> <?php echo $ap['email'] ?? ''; ?></p>
                                    <p><strong>Relación:</strong> <?php echo $ap['relacion'] ?? ''; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="checkAceptar" name="acepto" value="1" required>
                                <label class="form-check-label" for="checkAceptar">
                                    <strong>DECLARACIÓN JURADA DE VERACIDAD</strong><br>
                                    <small>Declaro bajo juramento que todos los datos consignados en esta pre-ficha de postulación rápida son verdaderos y de mi entera responsabilidad. Autorizo al Colegio Juventud Científica a verificar la autenticidad de los mismos. Entiendo que una vez aprobada mi pre-postulación, deberé completar los datos restantes obligatorios para continuar con el proceso de admisión.</small>
                                </label>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- BOTONES DE NAVEGACIÓN -->
                <!-- ========================================== -->
                <div class="d-flex justify-content-between mt-4">
                    <?php if ($step > 1 && $step <= 4): ?>
                        <a href="registro.php?step=<?php echo $step-1; ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Atrás</a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                    <?php if ($step < 4): ?>
                        <button type="submit" class="btn btn-primary">Continuar <i class="bi bi-arrow-right"></i></button>
                    <?php elseif ($step == 4): ?>
                        <button type="submit" class="btn btn-success"><i class="bi bi-send"></i> Enviar Pre-Inscripción Rápida</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============================================
// RESETEAR SELECTS AL INICIAR NUEVO REGISTRO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const selectDistrito = document.getElementById('selectDistrito');
    const selectNivel = document.getElementById('selectNivel');
    const selectGrado = document.getElementById('selectGrado');
    const selectSede = document.getElementById('selectSede');
    const sedeDireccionContainer = document.getElementById('sedeDireccionContainer');

    const hayDatosPrevios = <?php echo !empty($_SESSION['registro']['id_distrito']) && $_SESSION['registro']['id_distrito'] != '' ? 'true' : 'false'; ?>;

    if (!hayDatosPrevios) {
        if (selectNivel) selectNivel.innerHTML = '<option value="">Seleccione un distrito primero</option>';
        if (selectGrado) selectGrado.innerHTML = '<option value="">Seleccione un nivel primero</option>';
        if (selectSede) selectSede.innerHTML = '<option value="">Seleccione el grado a postular primero</option>';
        if (sedeDireccionContainer) sedeDireccionContainer.style.display = 'none';
    } else {
        if (selectDistrito && selectDistrito.value) { cargarNiveles(); }
    }
});

// ============================================
// SELECTORES ENCADEADOS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const selectDistrito = document.getElementById('selectDistrito');
    const selectNivel = document.getElementById('selectNivel');
    const selectGrado = document.getElementById('selectGrado');
    const selectSede = document.getElementById('selectSede');

    function cargarNiveles() {
        const distritoId = selectDistrito.value;
        if (distritoId) {
            fetch('registro.php?get_niveles=1&id_distrito=' + distritoId)
                .then(response => response.json())
                .then(data => {
                    selectNivel.innerHTML = '<option value="">Seleccione un nivel</option>';
                    data.forEach(n => { selectNivel.innerHTML += `<option value="${n.id}">${n.nombre}</option>`; });
                    if (selectNivel.value && selectNivel.value != '') { cargarGrados(); }
                });
        } else {
            selectNivel.innerHTML = '<option value="">Seleccione un distrito primero</option>';
            selectGrado.innerHTML = '<option value="">Seleccione un nivel primero</option>';
            selectSede.innerHTML = '<option value="">Seleccione el grado a postular primero</option>';
            const sedeDireccionContainer = document.getElementById('sedeDireccionContainer');
            if (sedeDireccionContainer) { sedeDireccionContainer.style.display = 'none'; }
        }
    }

    function cargarGrados() {
        const nivelId = selectNivel.value;
        if (nivelId) {
            fetch('registro.php?get_grados=1&id_nivel=' + nivelId)
                .then(response => response.json())
                .then(data => {
                    selectGrado.innerHTML = '<option value="">Seleccione un nivel primero</option>';
                    data.forEach(g => { selectGrado.innerHTML += `<option value="${g.id}">${g.nombre}</option>`; });
                    if (selectGrado.value && selectGrado.value != '') { cargarSedes(); }
                });
        } else {
            selectGrado.innerHTML = '<option value="">Seleccione un nivel primero</option>';
            selectSede.innerHTML = '<option value="">Seleccione el grado a postular primero</option>';
        }
    }

    function cargarSedes() {
        const distritoId = selectDistrito.value;
        const nivelId = selectNivel.value;
        if (distritoId && nivelId) {
            fetch('registro.php?get_sedes=1&id_distrito=' + distritoId + '&id_nivel=' + nivelId)
                .then(response => response.json())
                .then(data => {
                    selectSede.innerHTML = '<option value="">Seleccione una sede</option>';
                    data.forEach(s => { selectSede.innerHTML += `<option value="${s.id}">${s.nombre}</option>`; });
                    if (selectSede.value && selectSede.value != '') { actualizarDireccionSede(selectSede.value); }
                });
        } else {
            selectSede.innerHTML = '<option value="">Seleccione el grado a postular primero</option>';
        }
    }

    function actualizarDireccionSede(sedeId) {
        const container = document.getElementById('sedeDireccionContainer');
        const texto = document.getElementById('sedeDireccionTexto');
        if (sedeId && container && texto) {
            fetch('registro.php?get_sede_direccion=1&id=' + sedeId)
                .then(response => response.json())
                .then(data => {
                    container.style.display = 'block';
                    texto.textContent = data.direccion || 'No registrada';
                })
                .catch(() => {
                    container.style.display = 'block';
                    texto.textContent = 'No se pudo cargar la dirección';
                });
        } else if (container && texto) {
            container.style.display = 'none';
            texto.textContent = 'Seleccione una sede';
        }
    }

    if (selectDistrito) { selectDistrito.addEventListener('change', function() { cargarNiveles(); }); }
    if (selectNivel) { selectNivel.addEventListener('change', function() { cargarGrados(); }); }
    if (selectGrado) { selectGrado.addEventListener('change', function() { cargarSedes(); }); }
    if (selectSede) { selectSede.addEventListener('change', function() { actualizarDireccionSede(this.value); }); }
    if (selectDistrito && selectDistrito.value) { cargarNiveles(); }
});

// ============================================
// BUSCAR APODERADO POR DNI
// ============================================
function buscarApoderado() {
    const dni = document.getElementById('apoderadoDni').value;
    if (dni.length >= 8) {
        fetch('registro.php?buscar_apoderado=1&dni=' + dni)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    document.getElementById('apoderadoNombres').value = data.nombres || '';
                    const apellidos = data.apellidos || '';
                    const partes = apellidos.split(' ');
                    document.getElementById('apoderadoApellidoPaterno').value = partes[0] || '';
                    document.getElementById('apoderadoApellidoMaterno').value = partes.slice(1).join(' ') || '';
                    document.getElementById('apoderadoEmail').value = data.email || '';
                    document.getElementById('apoderadoWhatsapp').value = data.telefono || '';
                }
            });
    }
}

// ============================================
// MOSTRAR/OCULTAR CAMPOS CONDICIONALES
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const seguroSi = document.getElementById('seguroSi');
    const seguroNo = document.getElementById('seguroNo');
    const divSeguro = document.getElementById('divSeguroCompania');
    if (seguroSi && seguroNo && divSeguro) {
        function toggleSeguro() { divSeguro.style.display = seguroSi.checked ? 'block' : 'none'; }
        seguroSi.addEventListener('change', toggleSeguro);
        seguroNo.addEventListener('change', toggleSeguro);
        toggleSeguro();
    }

    const diagnosticoSi = document.getElementById('diagnosticoSi');
    const diagnosticoNo = document.getElementById('diagnosticoNo');
    const divDiagnostico = document.getElementById('divDiagnostico');
    if (diagnosticoSi && diagnosticoNo && divDiagnostico) {
        function toggleDiagnostico() { divDiagnostico.style.display = diagnosticoSi.checked ? 'block' : 'none'; }
        diagnosticoSi.addEventListener('change', toggleDiagnostico);
        diagnosticoNo.addEventListener('change', toggleDiagnostico);
        toggleDiagnostico();
    }

    const iglesiaSi = document.getElementById('iglesiaSi');
    const iglesiaNo = document.getElementById('iglesiaNo');
    const divIglesia = document.getElementById('divIglesia');
    if (iglesiaSi && iglesiaNo && divIglesia) {
        function toggleIglesia() { divIglesia.style.display = iglesiaSi.checked ? 'block' : 'none'; }
        iglesiaSi.addEventListener('change', toggleIglesia);
        iglesiaNo.addEventListener('change', toggleIglesia);
        toggleIglesia();
    }
});
</script>
</body>
</html>