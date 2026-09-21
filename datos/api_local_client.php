<?php

$RTIME_LOCAL_API = [
    'enabled' => true,
    'base_url' => 'http://localhost/Rt_web/api/local/trabajadores.php',
    'api_key' => 'RTIME_LOCAL_KEY_2026',
    'timeout' => 15,
];

$configPath = __DIR__ . '/api_local_config.php';
if (file_exists($configPath)) {
    require $configPath;
}

if (!function_exists('rtimeLocalApiRequest')) {
    function rtimeLocalApiRequest(string $action, array $payload = []): array {
        global $RTIME_LOCAL_API;

        if (!(bool)($RTIME_LOCAL_API['enabled'] ?? false)) {
            return ['ok' => false, 'mensaje' => 'API local deshabilitada en Rtime'];
        }

        $baseUrl = trim((string)($RTIME_LOCAL_API['base_url'] ?? ''));
        $apiKey = trim((string)($RTIME_LOCAL_API['api_key'] ?? ''));
        $timeout = (int)($RTIME_LOCAL_API['timeout'] ?? 15);

        if ($baseUrl === '' || $apiKey === '') {
            return ['ok' => false, 'mensaje' => 'Falta base_url o api_key para API local'];
        }

        $body = array_merge($payload, ['action' => $action]);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $timeout > 0 ? $timeout : 15,
                'header' => "Content-Type: application/json\r\n" .
                            "X-LOCAL-API-KEY: " . $apiKey . "\r\n",
                'content' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'ignore_errors' => true,
            ],
        ]);

        $resp = @file_get_contents($baseUrl, false, $ctx);
        if ($resp === false) {
            return ['ok' => false, 'mensaje' => 'No se pudo conectar con API de Rt_web'];
        }

        $json = @json_decode($resp, true);
        if (!is_array($json)) {
            return ['ok' => false, 'mensaje' => 'Respuesta inválida de API Rt_web'];
        }

        return $json;
    }
}

if (!function_exists('rtimeApiBuscarTrabajadores')) {
    function rtimeApiBuscarTrabajadores(string $query): array {
        $res = rtimeLocalApiRequest('search', ['q' => $query]);
        if (!($res['ok'] ?? false)) {
            return ['ok' => false, 'mensaje' => $res['mensaje'] ?? 'Error al buscar trabajadores', 'items' => []];
        }

        $items = is_array($res['items'] ?? null) ? $res['items'] : [];
        return ['ok' => true, 'items' => $items];
    }
}

if (!function_exists('rtimeApiObtenerTrabajador')) {
    function rtimeApiObtenerTrabajador(int $perId): array {
        $res = rtimeLocalApiRequest('get', ['per_iId' => $perId]);
        if (!($res['ok'] ?? false)) {
            return ['ok' => false, 'mensaje' => $res['mensaje'] ?? 'Error al obtener trabajador'];
        }

        $item = is_array($res['item'] ?? null) ? $res['item'] : null;
        if (!$item) {
            return ['ok' => false, 'mensaje' => 'Trabajador no encontrado'];
        }

        return ['ok' => true, 'item' => $item];
    }
}
