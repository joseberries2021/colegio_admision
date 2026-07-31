<?php
// ============================================
// FUNCIONES DE AUDITORÍA
// ============================================

function registrarAuditoria($modulo, $accion, $entidad_afectada, $entidad_id, $descripcion, $severidad = 'informativo', $datos_anteriores = null, $datos_nuevos = null) {
    global $pdo;
    
    $id_usuario = $_SESSION['user_id'] ?? null;
    $usuario_correo = $_SESSION['user_email'] ?? null;
    $usuario_nombre = $_SESSION['user_nombre'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $firma = hash('sha256', $id_usuario . $modulo . $accion . $entidad_id . time() . $ip);
    
    $sql = "INSERT INTO auditoria (id_usuario, usuario_correo, usuario_nombre, ip, modulo, accion, 
            entidad_afectada, entidad_id, descripcion, severidad, datos_anteriores, datos_nuevos, firma_digital) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $id_usuario,
        $usuario_correo,
        $usuario_nombre,
        $ip,
        $modulo,
        $accion,
        $entidad_afectada,
        $entidad_id,
        $descripcion,
        $severidad,
        $datos_anteriores ? json_encode($datos_anteriores) : null,
        $datos_nuevos ? json_encode($datos_nuevos) : null,
        $firma
    ]);
}

// ============================================
// FUNCIONES DE PERMISOS
// ============================================

function usuarioTienePermiso($modulo, $permiso) {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $sql = "SELECT id_rol FROM usuarios WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario || !$usuario['id_rol']) {
        return false;
    }
    
    $sql = "SELECT valor FROM permisos WHERE id_rol = ? AND modulo = ? AND permiso = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario['id_rol'], $modulo, $permiso]);
    $permiso_val = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $permiso_val && $permiso_val['valor'] == 1;
}

function verificarPermiso($modulo, $permiso) {
    if (!usuarioTienePermiso($modulo, $permiso)) {
        header('Location: ../login.php');
        exit;
    }
}

function usuarioPuedeVer($modulo) {
    return usuarioTienePermiso($modulo, 'ver');
}

function usuarioPuedeCrear($modulo) {
    return usuarioTienePermiso($modulo, 'crear');
}

function usuarioPuedeEditar($modulo) {
    return usuarioTienePermiso($modulo, 'editar');
}

function usuarioPuedeAprobar($modulo) {
    return usuarioTienePermiso($modulo, 'aprobar');
}

function usuarioPuedeEliminar($modulo) {
    return usuarioTienePermiso($modulo, 'eliminar');
}
?>