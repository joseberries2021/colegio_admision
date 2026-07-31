<?php
require_once 'config/database.php';

$usuario = 'admin';
$password = 'admin123';
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Verificar si existe
$existe = fetchOne("SELECT id FROM usuarios WHERE usuario = ?", [$usuario]);

if ($existe) {
    // Actualizar
    query("UPDATE usuarios SET password = ? WHERE usuario = ?", [$hashed, $usuario]);
    echo "✅ Usuario admin actualizado correctamente<br>";
} else {
    // Insertar
    query("INSERT INTO usuarios (usuario, password, tipo, nombres, apellidos, email) VALUES (?, ?, 'admin', 'Administrador', 'Sistema', 'admin@colegio.com')", [$usuario, $hashed]);
    echo "✅ Usuario admin creado correctamente<br>";
}

// Verificar que funcione
$test = fetchOne("SELECT * FROM usuarios WHERE usuario = ?", ['admin']);
if ($test && password_verify('admin123', $test['password'])) {
    echo "✅ La contraseña admin123 es válida<br>";
    echo "ID: " . $test['id'] . "<br>";
    echo "Usuario: " . $test['usuario'] . "<br>";
    echo "Tipo: " . $test['tipo'] . "<br>";
} else {
    echo "❌ Error: La contraseña no es válida";
}
?>