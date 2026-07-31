<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'familia') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';

$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$postulante_id = isset($_GET['postulante']) ? (int)$_GET['postulante'] : 0;

if ($doc_id && $postulante_id) {
    // Eliminar el archivo físico
    $doc = fetchOne("SELECT ruta FROM documentos_subidos WHERE id = ? AND id_postulante = ?", [$doc_id, $postulante_id]);
    if ($doc && $doc['ruta'] && file_exists($doc['ruta'])) {
        unlink($doc['ruta']);
    }
    
    // Eliminar el registro de la base de datos
    query("DELETE FROM documentos_subidos WHERE id = ? AND id_postulante = ?", [$doc_id, $postulante_id]);
    
    // Actualizar estado del postulante
    query("UPDATE postulantes SET estado_proceso = 'registrado' WHERE id = ?", [$postulante_id]);
}

header('Location: documentos.php?id=' . $postulante_id);
exit;
?>