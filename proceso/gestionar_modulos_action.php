<?php
/**
 * Gestionar Módulos - Controlador de permisos
 * Maneja la lógica de guardado/actualización de permisos de módulos por administrador
 */
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../vistas/login.php');
    exit;
}

require_once __DIR__ . '/../datos/db.php';

$adminId = intval($_POST['admin_id'] ?? 0);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminId > 0) {
    $selectedPermissions = $_POST['permisos'] ?? [];
    if (!is_array($selectedPermissions)) {
        $selectedPermissions = [];
    }
    $selectedPermissions = array_map('intval', $selectedPermissions);

    $con2->begin_transaction();
    try {
        $stmt = $con2->prepare("DELETE FROM admin_modulo WHERE admin_id = ?");
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();

        if (count($selectedPermissions) > 0) {
            $stmt = $con2->prepare("INSERT INTO admin_modulo (admin_id, mod_id, ver) VALUES (?, ?, 1)");
            foreach ($selectedPermissions as $modId) {
                $stmt->bind_param('ii', $adminId, $modId);
                $stmt->execute();
            }
            $stmt->close();
        }

        $con2->commit();
        $_SESSION['flash_success'] = 'Permisos guardados correctamente.';
    } catch (Exception $e) {
        $con2->rollback();
        $_SESSION['flash_error'] = 'Error guardando permisos: ' . $e->getMessage();
    }

    header('Location: ../vistas/gestionar_modulos.php?admin_id=' . $adminId);
    exit;
}
