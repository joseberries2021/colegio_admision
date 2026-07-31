<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] != 'familia') {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';

$postulante_id = $_POST['postulante_id'] ?? 0;
$pago_id = $_POST['pago_id'] ?? 0;

if (!$postulante_id || !$pago_id || !isset($_FILES['voucher'])) {
    header('Location: pago.php?id=' . $postulante_id);
    exit;
}

$archivo = $_FILES['voucher'];
$extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
$nombre_archivo = 'voucher_' . $postulante_id . '_' . time() . '.' . $extension;
$ruta = '../uploads/vouchers/' . $nombre_archivo;

// Crear carpeta si no existe
if (!file_exists('../uploads/vouchers/')) {
    mkdir('../uploads/vouchers/', 0777, true);
}

if (move_uploaded_file($archivo['tmp_name'], $ruta)) {
    query("UPDATE pagos SET voucher = ?, estado = 'pendiente' WHERE id = ?", [$nombre_archivo, $pago_id]);
    query("UPDATE postulantes SET estado_proceso = 'pago_pendiente' WHERE id = ?", [$postulante_id]);
    $_SESSION['mensaje'] = '✅ Voucher subido correctamente. Espera la verificación.';
} else {
    $_SESSION['mensaje'] = '❌ Error al subir el voucher';
}

header('Location: pago.php?id=' . $postulante_id);
exit;
?>