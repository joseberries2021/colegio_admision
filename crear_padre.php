<?php
require_once 'config/database.php';

// Datos del padre
$usuario = 'fam-001';
$password = '12345678';
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Verificar si ya existe
$existe = fetchOne("SELECT id FROM usuarios WHERE usuario = ?", [$usuario]);

if ($existe) {
    // Actualizar contraseña
    query("UPDATE usuarios SET password = ? WHERE usuario = ?", [$hashed, $usuario]);
    echo "✅ Contraseña actualizada para $usuario<br>";
} else {
    // Crear nuevo usuario
    query("INSERT INTO usuarios (usuario, password, tipo, dni, nombres, apellidos, email, telefono, estado) 
           VALUES (?, ?, 'familia', '12345678', 'Juan Carlos', 'Pérez Gómez', 'juan.perez@email.com', '987654321', 1)",
           [$usuario, $hashed]);
    echo "✅ Usuario $usuario creado correctamente<br>";
}

// Verificar que funcione
$test = fetchOne("SELECT * FROM usuarios WHERE usuario = ?", [$usuario]);
if ($test && password_verify($password, $test['password'])) {
    echo "✅ La contraseña '$password' es válida<br>";
    echo "ID: " . $test['id'] . "<br>";
    echo "Usuario: " . $test['usuario'] . "<br>";
    echo "Tipo: " . $test['tipo'] . "<br>";
    echo "<hr>";
    echo "<strong>🔑 Credenciales:</strong><br>";
    echo "Usuario: <code>$usuario</code><br>";
    echo "Contraseña: <code>$password</code><br>";
    echo "<br><a href='login.php'>Ir al login</a>";
} else {
    echo "❌ Error: La contraseña no es válida";
}
?>