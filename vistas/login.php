<?php
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: panel.php');
    exit;
}

$message = '';
if (!empty($_SESSION['flash_error'])) { $message = $_SESSION['flash_error']; unset($_SESSION['flash_error']); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . '/../includes/head.php'; ?>
</head>
<body>
    <div class="card-box" style="width:420px; margin: 0 auto; display: flex; align-items: center; min-height: 100vh; position: relative; z-index: 1;">
        <div style="width: 100%;">
            <div class="logo">
                TS
            </div>

            <h2>Bienvenido</h2>

            <div class="subtitle">
                Sistema de Control de Asistencia
            </div>

            <?php if($message != ''): ?>
                <div class="error">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="../proceso/login_action.php">

                <label>Usuario</label>

                <input
                    type="text"
                    name="usuario"
                    placeholder="Ingrese su usuario"
                    required
                    autofocus>

                <label>Contraseña</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Ingrese su contraseña"
                    required>

                <button type="submit">
                    Ingresar al Sistema
                </button>

            </form>

            <div style="margin-top: 14px; text-align: center;">
                <a href="../index.php" class="btn btn-secondary" style="display: inline-block; width: auto;">
                    Volver a Asistencia
                </a>
            </div>

            <div class="footer">
                © <?= date('Y') ?> T-Soluciona · Sistema de Asistencia
            </div>
        </div>
    </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>