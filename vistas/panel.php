<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$mensaje = '';
$error = '';
if (!empty($_SESSION['flash_success'])) {
    $mensaje = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <?php include __DIR__ . '/../includes/head.php'; ?>
</head>
<body>
<div class="container">
    <div class="card-box">
        <?php
        $pageTitle = 'Panel Administrativo';
        $pageSubtitle = 'Bienvenido, ' . htmlspecialchars($_SESSION['usuario']) . '. Aquí administras solo módulos y huellas del equipo local.';
        $headerLinks = [

        ];
        include __DIR__ . '/header_menu.php';
        ?>

        <?php if ($mensaje): ?>
            <div class="success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="welcome-banner" style="margin-top: 24px; padding: 14px 16px; border-radius: 12px; background: #eff6ff; border: 1px solid #c7d2fe; color: #1e3a8a; font-size: 0.95rem;">
            <strong style="display:block; font-size:1.05rem; margin-bottom:6px;">Panel local de RTIME</strong>
            <span>Este entorno está limitado a registrar huellas y asignar módulos para usuarios locales.</span>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-top: 18px;">

        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>