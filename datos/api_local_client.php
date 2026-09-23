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
                            "Accept: application/json\r\n" .
                            "X-LOCAL-API-KEY: " . $apiKey . "\r\n",
                'content' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'ignore_errors' => true,
            ],
        ]);

        $urls = rtimeLocalApiCandidateUrls($baseUrl);
        $lastError = 'No se pudo conectar con API de Rt_web';
        foreach ($urls as $url) {
            $resp = @file_get_contents($url, false, $ctx);
            if ($resp === false) {
                continue;
            }

            $decoded = rtimeDecodeApiResponseJson($resp);
            $json = $decoded['json'] ?? null;
            if (is_array($json)) {
                return $json;
            }

            $statusLine = '';
            if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header[0])) {
                $statusLine = trim((string)$http_response_header[0]);
            }
            $preview = trim((string)($decoded['preview'] ?? ''));
            $msg = 'Respuesta inválida de API Rt_web';
            if ($statusLine !== '') {
                $msg .= ' (' . $statusLine . ')';
            }
            if ($preview !== '') {
                $msg .= ': ' . $preview;
            }

            $is404 = (stripos($statusLine, '404') !== false);
            if ($is404) {
                $lastError = $msg;
                continue;
            }

            return ['ok' => false, 'mensaje' => $msg];
        }

        return ['ok' => false, 'mensaje' => $lastError];
    }
}

if (!function_exists('rtimeLocalApiCandidateUrls')) {
    function rtimeLocalApiCandidateUrls(string $baseUrl): array {
        $candidates = [];
        $baseUrl = trim($baseUrl);
        if ($baseUrl !== '') {
            $candidates[] = $baseUrl;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $isHttps ? 'https' : 'http';

        $candidates[] = $scheme . '://' . $host . '/Rt_web/api/local/trabajadores.php';
        $candidates[] = $scheme . '://' . $host . '/rt_web/api/local/trabajadores.php';
        $candidates[] = $scheme . '://' . $host . '/api/local/trabajadores.php';

        $unique = [];
        foreach ($candidates as $url) {
            $u = rtrim((string)$url, '/');
            if ($u === '') {
                continue;
            }
            $unique[$u] = true;
        }

        return array_keys($unique);
    }
}

if (!function_exists('rtimeDecodeApiResponseJson')) {
    function rtimeDecodeApiResponseJson(string $resp): array {
        $raw = ltrim($resp, "\xEF\xBB\xBF \t\r\n");
        $json = @json_decode($raw, true);
        if (is_array($json)) {
            return ['json' => $json, 'preview' => ''];
        }

        $startObj = strpos($raw, '{');
        $endObj = strrpos($raw, '}');
        if ($startObj !== false && $endObj !== false && $endObj > $startObj) {
            $fragment = substr($raw, $startObj, ($endObj - $startObj + 1));
            $json = @json_decode($fragment, true);
            if (is_array($json)) {
                return ['json' => $json, 'preview' => ''];
            }
        }

        $startArr = strpos($raw, '[');
        $endArr = strrpos($raw, ']');
        if ($startArr !== false && $endArr !== false && $endArr > $startArr) {
            $fragment = substr($raw, $startArr, ($endArr - $startArr + 1));
            $json = @json_decode($fragment, true);
            if (is_array($json)) {
                return ['json' => $json, 'preview' => ''];
            }
        }

        $preview = trim(preg_replace('/\s+/', ' ', strip_tags($raw)));
        if ($preview === '') {
            $preview = 'sin cuerpo de respuesta';
        }
        if (strlen($preview) > 180) {
            $preview = substr($preview, 0, 180) . '...';
        }

        return ['json' => null, 'preview' => $preview];
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
