<?php
/**
 * Gestionar Módulos - Lectura de datos
 * Carga admins, módulos y permisos del administrador seleccionado
 */

require_once __DIR__ . '/../datos/db.php';

$selectedAdmin = intval($_GET['admin_id'] ?? 0);

// Leer lista de administradores
$admins = [];
$result = $con2->query('SELECT id, usuario FROM admins ORDER BY usuario ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    $result->free();
}

// Leer lista de módulos
$modules = [];
$result = $con2->query('SELECT mod_id, mod_padre, mod_nombre, mod_url, mod_orden FROM ad_modulo ORDER BY mod_padre ASC, mod_orden ASC, mod_id ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $modules[] = $row;
    }
    $result->free();
}

// Leer permisos del administrador seleccionado
$permissions = [];
if ($selectedAdmin > 0) {
    $stmt = $con2->prepare('SELECT mod_id FROM admin_modulo WHERE admin_id = ? AND ver = 1');
    if ($stmt) {
        $stmt->bind_param('i', $selectedAdmin);
        $stmt->execute();
        $stmt->bind_result($modId);
        while ($stmt->fetch()) {
            $permissions[] = $modId;
        }
        $stmt->close();
    }
}
