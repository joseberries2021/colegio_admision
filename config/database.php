<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'colegio_admision');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetchAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

function fetchOne($sql, $params = []) {
    return query($sql, $params)->fetch();
}

function insert($sql, $params = []) {
    global $pdo;
    query($sql, $params);
    return $pdo->lastInsertId();
}

// ============================================
// FUNCIÓN: Generar código FAM-XXX secuencial
// ============================================
function generarCodigoFamilia() {
    $ultimo = fetchOne("SELECT usuario FROM usuarios WHERE usuario LIKE 'FAM-%' ORDER BY id DESC LIMIT 1");
    
    if ($ultimo) {
        $numero = (int)substr($ultimo['usuario'], 4);
        $nuevo = $numero + 1;
    } else {
        $nuevo = 1;
    }
    
    return 'FAM-' . str_pad($nuevo, 3, '0', STR_PAD_LEFT);
}
?>