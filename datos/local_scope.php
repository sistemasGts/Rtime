<?php
if (!defined('RT_WEB_BASE_URL')) {
    define('RT_WEB_BASE_URL', '/Rt_web');
}

if (!defined('RT_LOCAL_ALLOWED_PAGES')) {
    define('RT_LOCAL_ALLOWED_PAGES', [
        'panel.php',
        'gestionar_huellas.php',
        'guardar_huella.php',
        'gestionar_modulos.php',
    ]);
}

if (!function_exists('rtCurrentScriptBasename')) {
    function rtCurrentScriptBasename(): string {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName === '') {
            return '';
        }

        return basename($scriptName);
    }
}

if (!function_exists('rtIsAllowedLocalPage')) {
    function rtIsAllowedLocalPage(string $basename): bool {
        $allowed = RT_LOCAL_ALLOWED_PAGES;
        return in_array($basename, $allowed, true);
    }
}

if (!function_exists('rtBuildWebRedirectUrl')) {
    function rtBuildWebRedirectUrl(string $basename): string {
        $baseUrl = rtrim(RT_WEB_BASE_URL, '/');
        $path = '/vistas/' . ltrim($basename, '/');
        $query = $_SERVER['QUERY_STRING'] ?? '';

        if ($query !== '') {
            return $baseUrl . $path . '?' . $query;
        }

        return $baseUrl . $path;
    }
}
