<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit('No autorizado');
}
require_once '../config/database.php';

$usuario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$historial = fetchAll("
    SELECT * FROM auditoria 
    WHERE id_usuario = ? 
    ORDER BY fecha_registro DESC 
    LIMIT 20
", [$usuario_id]);

if (empty($historial)) {
    echo '<p class="text-muted">No hay registros de actividad</p>';
} else {
    echo '<ul class="list-unstyled">';
    foreach ($historial as $h) {
        echo '<li class="mb-2">';
        echo '<small class="text-muted">' . date('Y-m-d H:i', strtotime($h['fecha_registro'])) . '</small><br>';
        echo '<strong>' . $h['accion'] . '</strong> - ' . $h['descripcion'];
        echo '</li>';
    }
    echo '</ul>';
}
?>