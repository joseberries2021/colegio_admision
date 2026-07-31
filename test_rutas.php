<?php
echo "<h3>Diagnóstico de Rutas</h3>";
echo "<ul>";
echo "<li>Ruta actual: " . __DIR__ . "</li>";
echo "<li>¿Existe familia/registro.php? " . (file_exists('familia/registro.php') ? '✅ Sí' : '❌ No') . "</li>";
echo "<li>¿Existe familia/index.php? " . (file_exists('familia/index.php') ? '✅ Sí' : '❌ No') . "</li>";
echo "</ul>";

echo "<h4>Enlaces de prueba:</h4>";
echo "<ul>";
echo "<li><a href='familia/index.php'>Portal del Padre</a></li>";
echo "<li><a href='familia/registro.php'>Registro de Postulante</a></li>";
echo "<li><a href='login.php'>Login</a></li>";
echo "</ul>";
?>