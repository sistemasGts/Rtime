<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function getUserIdFromSession(): int {
    return isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
}

function getUserAllowedModules(): array {
    global $con2;
    $allowed = [];
    $userId = getUserIdFromSession();
    if ($userId <= 0) {
        return $allowed;
    }

    $stmt = $con2->prepare('SELECT mod_id FROM admin_modulo WHERE admin_id = ? AND ver = 1');
    if (!$stmt) {
        return $allowed;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $modId = null;
    $stmt->bind_result($modId);
    while ($stmt->fetch()) {
        $allowed[(int)$modId] = true;
    }
    $stmt->close();
    return $allowed;
}

function getUserAllowedClients(): array {
    global $con2;
    $allowed = [];
    $userId = getUserIdFromSession();
    if ($userId <= 0) {
        return $allowed;
    }

    $stmt = $con2->prepare('SELECT cli_id FROM admin_cliente WHERE admin_id = ?');
    if (!$stmt) {
        return $allowed;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $cliId = null;
    $stmt->bind_result($cliId);
    while ($stmt->fetch()) {
        $allowed[] = (int)$cliId;
    }
    $stmt->close();
    return $allowed;
}

function getUserAllowedPlanillas(): array {
    global $con2;
    $allowed = [];
    $userId = getUserIdFromSession();
    if ($userId <= 0) {
        return $allowed;
    }

    $stmt = $con2->prepare('SELECT conpla_id FROM admin_planilla WHERE admin_id = ?');
    if (!$stmt) {
        return $allowed;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $conplaId = null;
    $stmt->bind_result($conplaId);
    while ($stmt->fetch()) {
        $allowed[] = (int)$conplaId;
    }
    $stmt->close();
    return $allowed;
}
