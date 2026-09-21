<?php
session_start();
require_once __DIR__ . '/../datos/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../vistas/login.php');
    exit;
}

$usuario  = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

if ($usuario === '' || $password === '') {
    $_SESSION['flash_error'] = 'Por favor ingresa usuario y contraseña.';
    header('Location: ../vistas/login.php');
    exit;
}

$stmt = $con2->prepare("SELECT id, usuario, password_hash
                                FROM admins
                                WHERE usuario = ? AND iniciado = 1
                                LIMIT 1");

if (!$stmt) {
    $_SESSION['flash_error'] = 'Error al validar las credenciales.';
    header('Location: ../vistas/login.php');
    exit;
}

$stmt->bind_param('s', $usuario);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    $_SESSION['flash_error'] = 'Usuario o contraseña incorrectos o acceso no autorizado.';
    $stmt->close();
    header('Location: ../vistas/login.php');
    exit;
}

$stmt->bind_result($id, $dbUsuario, $dbPasswordHash);
$stmt->fetch();

$validPassword = false;
if (!empty($dbPasswordHash)) {
    if (password_verify($password, $dbPasswordHash)) {
        $validPassword = true;
    } elseif (
        $dbPasswordHash === sha1($password) ||
        $dbPasswordHash === md5($password) ||
        $dbPasswordHash === $password
    ) {
        $validPassword = true;
    }
}

if ($validPassword) {
    // login OK
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id']   = $id;
    $_SESSION['usuario']   = $dbUsuario;
    // clear any flash
    unset($_SESSION['flash_error']);
    header('Location: ../vistas/panel.php');
    exit;
}

// default fail
$_SESSION['flash_error'] = 'Usuario o contraseña incorrectos o acceso no autorizado.';
$stmt->close();
header('Location: ../vistas/login.php');
exit;
