<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Cargar datos
require_once __DIR__ . '/../proceso/gestionar_modulos_data.php';
require_once __DIR__ . '/../includes/gestionar_modulos_helpers.php';

// Leer flash messages
$success = '';
$error = '';
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$pageTitle = 'Gestión de módulos';
$pageSubtitle = 'Asigna permisos de visibilidad por módulo con una vista de árbol.';
$headerLinks = [
    ['href' => 'panel.php', 'label' => 'Panel'],
    ['href' => 'gestionar_modulos.php', 'label' => 'Gestión de módulos'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de módulos</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../assets/css/gestionar_modulos.css">
    <?php include __DIR__ . '/../includes/head.php'; ?>
</head>
<body>
<div class="container">
    <div class="card-box" style="max-width: 980px; margin: 0 auto; padding: 20px; box-sizing: border-box;">
        <?php include __DIR__ . '/header_menu.php'; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="GET" action="gestionar_modulos.php" class="admin-selector-form">
        <label>Seleccionar administrador:</label>
        <select name="admin_id" onchange="this.form.submit()">
            <option value="">-- Seleccionar administrador --</option>
            <?php foreach ($admins as $admin): ?>
                <option value="<?= (int)$admin['id'] ?>" <?= $selectedAdmin === (int)$admin['id'] ? 'selected' : '' ?>><?= htmlspecialchars($admin['usuario']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selectedAdmin <= 0): ?>
        <div class="info">Elige un administrador para ver y asignar permisos por módulo.</div>
    <?php else: ?>
        <form method="POST" action="../proceso/gestionar_modulos_action.php">
            <input type="hidden" name="admin_id" value="<?= (int)$selectedAdmin ?>">
            <div class="module-tree-container">
                <?php
                    $tree = buildModuleTree($modules);
                    if (empty($tree)) {
                        echo '<div class="warning">No hay módulos cargados en <code>ad_modulo</code>.</div>';
                    } else {
                        renderModuleTree($tree, $permissions);
                    }
                ?>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-success">Guardar permisos</button>
                <a href="gestionar_modulos.php?admin_id=<?= (int)$selectedAdmin ?>" class="btn btn-secondary">Recargar</a>
            </div>
        </form>
    <?php endif; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/gestionar_modulos.js"></script>
</body>
</html>
