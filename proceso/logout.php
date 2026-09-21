<?php
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();
$redirect = '../vistas/login.php';
if (!empty($_GET['redirect'])) {
    $candidate = trim($_GET['redirect']);
    $candidateLower = strtolower($candidate);

    // Permitir rutas internas relativas seguras y absolute paths locales.
    if (strpos($candidateLower, 'http://') === false && strpos($candidateLower, 'https://') === false && strpos($candidateLower, 'javascript:') === false && strpos($candidateLower, '//') !== 0) {
        $redirect = $candidate;
    }
}
header('Location: ' . $redirect);
exit;
