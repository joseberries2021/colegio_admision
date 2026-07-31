<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'admin') {
    exit;
}
require_once '../config/database.php';

$id_distrito = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_distrito > 0) {
    $niveles = fetchAll("SELECT id, nombre FROM niveles WHERE id_distrito = ? AND estado = 1 ORDER BY nombre", [$id_distrito]);
    echo json_encode($niveles);
} else {
    echo json_encode([]);
}
?>