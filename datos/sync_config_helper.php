<?php

require_once __DIR__ . '/offline_asistencia_helper.php';

if (!function_exists('syncBaseConfigDefaults')) {
    function syncBaseConfigDefaults() {
        return [
            'enabled' => false,
            'cloud_base_url' => '',
            'sede_codigo' => '',
            'api_key' => '',
            'batch_limit' => 200,
            'equipo_nombre' => php_uname('n'),
            'dispositivo_codigo' => 'LOCAL',
        ];
    }
}

if (!function_exists('syncRuntimeConfigFile')) {
    function syncRuntimeConfigFile() {
        return offlineDataDir() . '/sync_runtime_config.json';
    }
}

if (!function_exists('syncDetectCurrentBaseUrl')) {
    function syncDetectCurrentBaseUrl() {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return '';
        }

        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');
        return $scheme . '://' . $host . $basePath;
    }
}

if (!function_exists('syncLoadBaseConfig')) {
    function syncLoadBaseConfig() {
        $cfg = syncBaseConfigDefaults();
        $path = __DIR__ . '/sync_local_config.php';

        if (file_exists($path)) {
            $SYNC_LOCAL_CONFIG = [];
            require $path;
            if (is_array($SYNC_LOCAL_CONFIG)) {
                $cfg = array_merge($cfg, $SYNC_LOCAL_CONFIG);
            }
        }

        return $cfg;
    }
}

if (!function_exists('syncLoadRuntimeConfig')) {
    function syncLoadRuntimeConfig() {
        $data = readJsonFileSafe(syncRuntimeConfigFile(), []);
        if (!is_array($data)) {
            $data = [];
        }

        if (!isset($data['default']) || !is_array($data['default'])) {
            $data['default'] = [];
        }
        if (!isset($data['terminals']) || !is_array($data['terminals'])) {
            $data['terminals'] = [];
        }

        return $data;
    }
}

if (!function_exists('syncSaveRuntimeConfig')) {
    function syncSaveRuntimeConfig($data) {
        return writeJsonFileSafe(syncRuntimeConfigFile(), $data);
    }
}

if (!function_exists('syncGetTerminalConfig')) {
    function syncGetTerminalConfig($ip) {
        $runtime = syncLoadRuntimeConfig();
        if (!isset($runtime['terminals']) || !is_array($runtime['terminals'])) {
            return null;
        }

        $cfg = $runtime['terminals'][$ip] ?? null;
        return is_array($cfg) ? $cfg : null;
    }
}

if (!function_exists('syncTerminalConfigExists')) {
    function syncTerminalConfigExists($ip) {
        return is_array(syncGetTerminalConfig($ip));
    }
}

if (!function_exists('syncClientIp')) {
    function syncClientIp() {
        $envIp = trim((string)getenv('RTIME_TERMINAL_IP'));
        if ($envIp !== '') {
            return $envIp;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '::1') {
            return '127.0.0.1';
        }
        return $ip ?: '0.0.0.0';
    }
}

if (!function_exists('syncResolveConfigForIp')) {
    function syncResolveConfigForIp($ip = null) {
        $cfg = array_merge(syncBaseConfigDefaults(), syncLoadBaseConfig());
        $runtime = syncLoadRuntimeConfig();

        if ($ip !== null && isset($runtime['terminals'][$ip]) && is_array($runtime['terminals'][$ip])) {
            $cfg = array_merge($cfg, $runtime['terminals'][$ip]);
        }

        return $cfg;
    }
}

if (!function_exists('syncResolveConfigForWorker')) {
    function syncResolveConfigForWorker() {
        $runtime = syncLoadRuntimeConfig();
        $clientIp = syncClientIp();

        if ($clientIp !== '0.0.0.0' && isset($runtime['terminals'][$clientIp]) && is_array($runtime['terminals'][$clientIp])) {
            return array_merge(syncLoadBaseConfig(), $runtime['terminals'][$clientIp]);
        }

        if (!empty($runtime['terminals']) && is_array($runtime['terminals'])) {
            $first = reset($runtime['terminals']);
            if (is_array($first)) {
                return array_merge(syncLoadBaseConfig(), $first);
            }
        }

        return syncLoadBaseConfig();
    }
}

if (!function_exists('syncIsPlaceholderApiKey')) {
    function syncIsPlaceholderApiKey($key) {
        $k = trim((string)$key);
        if ($k === '') {
            return true;
        }

        $placeholderValues = ['change-me', 'placeholder', 'demo-key', 'example-key', 'test-key'];
        return in_array(strtolower($k), $placeholderValues, true);
    }
}

if (!function_exists('syncNeedsSetupForIp')) {
    function syncNeedsSetupForIp($ip) {
        $cfg = syncResolveConfigForIp($ip);

        if (empty($cfg['cloud_base_url'])) {
            return true;
        }
        if (empty($cfg['sede_codigo'])) {
            return true;
        }
        if (syncIsPlaceholderApiKey($cfg['api_key'] ?? '')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('syncSaveConfigForIp')) {
    function syncSaveConfigForIp($ip, $input) {
        $runtime = syncLoadRuntimeConfig();
        $existingTerminalCfg = syncGetTerminalConfig($ip);

        $cloudBaseUrl = rtrim(trim((string)($input['cloud_base_url'] ?? '')), '/');
        if ($cloudBaseUrl === '') {
            $cloudBaseUrl = rtrim(syncDetectCurrentBaseUrl(), '/');
        }
        $sedeCodigo = trim((string)($input['sede_codigo'] ?? ''));
        $apiKey = trim((string)($input['api_key'] ?? ''));
        $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
        $batchLimit = (int)($input['batch_limit'] ?? 200);
        $equipoNombre = trim((string)($input['equipo_nombre'] ?? ''));
        if ($equipoNombre === '') {
            $equipoNombre = php_uname('n');
        }
        if ($batchLimit <= 0) {
            $batchLimit = 200;
        }

        if ($cloudBaseUrl === '' || $sedeCodigo === '' || $apiKey === '') {
            return ['ok' => false, 'mensaje' => 'Faltan datos de configuración'];
        }

        $requireCurrentKey = isset($input['require_current_key']) ? (bool)$input['require_current_key'] : true;
        if ($requireCurrentKey && is_array($existingTerminalCfg) && !empty($existingTerminalCfg['api_key'])) {
            $currentApiKey = trim((string)($input['current_api_key'] ?? ''));
            $currentSedeCodigo = trim((string)($input['current_sede_codigo'] ?? ''));
            if ($currentSedeCodigo === '') {
                $currentSedeCodigo = trim((string)($existingTerminalCfg['sede_codigo'] ?? ''));
            }
            $registeredApiKey = trim((string)$existingTerminalCfg['api_key']);

            if ($currentApiKey === '') {
                return ['ok' => false, 'mensaje' => 'Para cambiar configuración debes ingresar la API Key actual'];
            }

            $currentKeyOk = hash_equals($registeredApiKey, $currentApiKey);
            if (!$currentKeyOk) {
                $cloudBaseForCheck = rtrim((string)($existingTerminalCfg['cloud_base_url'] ?? $cloudBaseUrl), '/');
                $sedeForCheck = trim((string)($existingTerminalCfg['sede_codigo'] ?? ''));
                $sedeTargetForCheck = trim((string)$sedeCodigo);

                $checkCandidates = [];
                if ($cloudBaseForCheck !== '' && $currentSedeCodigo !== '') {
                    $checkCandidates[] = [
                        'cloud_base_url' => $cloudBaseForCheck,
                        'sede_codigo' => $currentSedeCodigo,
                    ];
                }
                if ($cloudBaseForCheck !== '' && $sedeForCheck !== '') {
                    $checkCandidates[] = [
                        'cloud_base_url' => $cloudBaseForCheck,
                        'sede_codigo' => $sedeForCheck,
                    ];
                }

                if ($cloudBaseUrl !== '' && $sedeTargetForCheck !== '') {
                    $checkCandidates[] = [
                        'cloud_base_url' => $cloudBaseUrl,
                        'sede_codigo' => $sedeTargetForCheck,
                    ];
                }

                foreach ($checkCandidates as $candidate) {
                    $checkCfg = [
                        'cloud_base_url' => $candidate['cloud_base_url'],
                        'sede_codigo' => $candidate['sede_codigo'],
                        'api_key' => $currentApiKey,
                        'dispositivo_codigo' => (string)($existingTerminalCfg['dispositivo_codigo'] ?? 'LOCAL'),
                        'equipo_nombre' => (string)($existingTerminalCfg['equipo_nombre'] ?? $equipoNombre),
                    ];

                    $checkCurrent = syncValidateCloudCredentials($checkCfg);
                    if (($checkCurrent['ok'] ?? false)) {
                        $currentKeyOk = true;
                        break;
                    }
                }
            }

            if (!$currentKeyOk) {
                return ['ok' => false, 'mensaje' => 'API Key actual incorrecta para este dispositivo'];
            }

            $existingCode = trim((string)($existingTerminalCfg['sede_codigo'] ?? ''));
            $existingKey = trim((string)($existingTerminalCfg['api_key'] ?? ''));
            $isChangingCredentials = ($existingCode !== $sedeCodigo) || ($existingKey !== $apiKey);

            if ($isChangingCredentials) {
                $cloudBaseForUpdate = rtrim((string)($existingTerminalCfg['cloud_base_url'] ?? $cloudBaseUrl), '/');
                $oldCodeForUpdate = $currentSedeCodigo !== '' ? $currentSedeCodigo : $existingCode;
                if ($oldCodeForUpdate === '') {
                    $oldCodeForUpdate = $sedeCodigo;
                }

                $updateCloud = syncUpdateCloudCredentials([
                    'cloud_base_url' => $cloudBaseForUpdate,
                    'old_sede_codigo' => $oldCodeForUpdate,
                    'old_api_key' => $currentApiKey,
                    'new_sede_codigo' => $sedeCodigo,
                    'new_api_key' => $apiKey,
                    'new_nombre' => $equipoNombre,
                ]);

                if (!($updateCloud['ok'] ?? false)) {
                    return [
                        'ok' => false,
                        'mensaje' => $updateCloud['mensaje'] ?? 'No se pudo actualizar credenciales de sede en cloud',
                        'update_cloud' => $updateCloud,
                    ];
                }
            }
        }

        $validation = syncValidateCloudCredentials([
            'cloud_base_url' => $cloudBaseUrl,
            'sede_codigo' => $sedeCodigo,
            'api_key' => $apiKey,
            'dispositivo_codigo' => (string)($input['dispositivo_codigo'] ?? 'LOCAL'),
            'equipo_nombre' => $equipoNombre,
        ]);
        if (!($validation['ok'] ?? false)) {
            $validationMessage = $validation['mensaje'] ?? 'No se validaron credenciales cloud';
            if (stripos($validationMessage, 'Sede no autorizada') !== false) {
                $validationMessage .= ' (verifica que el par sede_codigo/api_key exista y esté activo en sync_sede)';
            }

            return [
                'ok' => false,
                'mensaje' => $validationMessage,
                'validacion' => $validation,
            ];
        }

        $entry = [
            'enabled' => $enabled,
            'cloud_base_url' => $cloudBaseUrl,
            'sede_codigo' => $sedeCodigo,
            'api_key' => $apiKey,
            'batch_limit' => $batchLimit,
            'equipo_nombre' => $equipoNombre,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!isset($runtime['terminals']) || !is_array($runtime['terminals'])) {
            $runtime['terminals'] = [];
        }

        $runtime['terminals'][$ip] = $entry;

        $setDefault = isset($input['set_as_default']) ? (bool)$input['set_as_default'] : false;
        if ($setDefault) {
            $runtime['default'] = [
                'enabled' => $enabled,
                'cloud_base_url' => $cloudBaseUrl,
                'sede_codigo' => $sedeCodigo,
                'api_key' => $apiKey,
                'batch_limit' => $batchLimit,
                'equipo_nombre' => $equipoNombre,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (!syncSaveRuntimeConfig($runtime)) {
            return ['ok' => false, 'mensaje' => 'No se pudo guardar configuración runtime'];
        }

        return ['ok' => true, 'mensaje' => 'Configuración guardada', 'config' => syncResolveConfigForIp($ip)];
    }
}

if (!function_exists('syncValidateCloudCredentials')) {
    function syncValidateCloudCredentials($cfg) {
        $base = rtrim((string)($cfg['cloud_base_url'] ?? ''), '/');
        $sede = trim((string)($cfg['sede_codigo'] ?? ''));
        $key = trim((string)($cfg['api_key'] ?? ''));

        if ($base === '' || $sede === '' || $key === '') {
            return ['ok' => false, 'mensaje' => 'Faltan datos para validar credenciales'];
        }

        $payload = [
            'sede_codigo' => $sede,
            'dispositivo_codigo' => (string)($cfg['dispositivo_codigo'] ?? 'LOCAL'),
            'nombre_equipo' => (string)($cfg['equipo_nombre'] ?? php_uname('n')),
        ];

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 15,
                'header' => "Content-Type: application/json\r\n" .
                            "X-API-KEY: $key\r\n" .
                            "X-SEDE-CODIGO: $sede\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ignore_errors' => true,
            ],
        ]);

        $url = $base . '/api/sync/ping_sede.php';
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return ['ok' => false, 'mensaje' => 'No se pudo conectar a cloud para validar credenciales'];
        }

        $json = @json_decode($resp, true);
        if (!is_array($json)) {
            return ['ok' => false, 'mensaje' => 'Respuesta inválida desde cloud al validar credenciales'];
        }

        if (!($json['ok'] ?? false)) {
            return ['ok' => false, 'mensaje' => $json['mensaje'] ?? 'Credenciales no autorizadas', 'respuesta' => $json];
        }

        return ['ok' => true, 'mensaje' => 'Credenciales validadas', 'respuesta' => $json];
    }
}

if (!function_exists('syncUpdateCloudCredentials')) {
    function syncUpdateCloudCredentials($cfg) {
        $base = rtrim((string)($cfg['cloud_base_url'] ?? ''), '/');
        $oldCode = trim((string)($cfg['old_sede_codigo'] ?? ''));
        $oldKey = trim((string)($cfg['old_api_key'] ?? ''));
        $newCode = trim((string)($cfg['new_sede_codigo'] ?? ''));
        $newKey = trim((string)($cfg['new_api_key'] ?? ''));
        $newName = trim((string)($cfg['new_nombre'] ?? ''));

        if ($base === '' || $oldCode === '' || $oldKey === '' || $newCode === '' || $newKey === '') {
            return ['ok' => false, 'mensaje' => 'Faltan datos para actualizar credenciales en cloud'];
        }

        $payload = [
            'sede_codigo' => $oldCode,
            'nuevo_sede_codigo' => $newCode,
            'nuevo_api_key' => $newKey,
            'nuevo_nombre' => ($newName !== '' ? $newName : null),
        ];

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 20,
                'header' => "Content-Type: application/json\r\n" .
                            "X-API-KEY: $oldKey\r\n" .
                            "X-SEDE-CODIGO: $oldCode\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ignore_errors' => true,
            ],
        ]);

        $url = $base . '/api/sync/update_sede_credentials.php';
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return ['ok' => false, 'mensaje' => 'No se pudo conectar a cloud para actualizar credenciales'];
        }

        $json = @json_decode($resp, true);
        if (!is_array($json)) {
            return ['ok' => false, 'mensaje' => 'Respuesta inválida desde cloud al actualizar credenciales'];
        }

        if (!($json['ok'] ?? false)) {
            return ['ok' => false, 'mensaje' => $json['mensaje'] ?? 'No se pudo actualizar credenciales en cloud', 'respuesta' => $json];
        }

        return ['ok' => true, 'mensaje' => 'Credenciales cloud actualizadas', 'respuesta' => $json];
    }
}

if (!function_exists('syncWorkerScriptPath')) {
    function syncWorkerScriptPath() {
        return realpath(__DIR__ . '/../proceso/sync_worker.php');
    }
}

if (!function_exists('syncPhpBinaryPath')) {
    function syncPhpBinaryPath() {
        $envPhp = trim((string)getenv('RTIME_PHP_BIN'));
        if ($envPhp !== '' && is_file($envPhp)) {
            return $envPhp;
        }

        $xamppPhp = 'C:\\xampp\\php\\php.exe';
        if (is_file($xamppPhp)) {
            return $xamppPhp;
        }

        if (!empty(PHP_BINARY) && is_file(PHP_BINARY)) {
            $binName = strtolower((string)basename(PHP_BINARY));
            if ($binName === 'php.exe' || $binName === 'php') {
                return PHP_BINARY;
            }
        }

        return 'php';
    }
}

if (!function_exists('syncCanUseExec')) {
    function syncCanUseExec() {
        if (!function_exists('exec')) {
            return false;
        }

        $disabled = (string)ini_get('disable_functions');
        if ($disabled === '') {
            return true;
        }

        $disabledList = array_map('trim', explode(',', strtolower($disabled)));
        return !in_array('exec', $disabledList, true);
    }
}

if (!function_exists('syncWorkerHttpPostInternal')) {
    function syncWorkerHttpPostInternal($url, $payload, $sede, $key) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 20,
                'header' => "Content-Type: application/json\r\n" .
                            "X-API-KEY: $key\r\n" .
                            "X-SEDE-CODIGO: $sede\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ignore_errors' => true,
            ]
        ]);

        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return null;
        }

        $data = @json_decode($resp, true);
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('syncCollectLocalCatalogForPush')) {
    function syncCollectLocalCatalogForPush($con2, $limit = 200) {
        if (!is_object($con2) || !($con2 instanceof mysqli)) {
            return ['trabajadores' => [], 'huellas' => []];
        }

        $trabajadores = [];
        $stmt = @$con2->prepare('SELECT per_iId, per_vcDocumento, per_vcPaterno, per_vcMaterno, per_vcNombre, per_vcNombres FROM trabajador ORDER BY per_iId LIMIT ?');
        if ($stmt) {
            $limitInt = max(0, (int)$limit);
            @$stmt->bind_param('i', $limitInt);
            @$stmt->execute();
            $res = @$stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $trabajadores[] = [
                    'per_iId' => (int)($row['per_iId'] ?? 0),
                    'per_vcDocumento' => (string)($row['per_vcDocumento'] ?? ''),
                    'per_vcPaterno' => (string)($row['per_vcPaterno'] ?? ''),
                    'per_vcMaterno' => (string)($row['per_vcMaterno'] ?? ''),
                    'per_vcNombre' => (string)($row['per_vcNombre'] ?? ''),
                    'per_vcNombres' => (string)($row['per_vcNombres'] ?? ''),
                ];
            }
            @$stmt->close();
        }

        $huellas = [];
        $stmt2 = @$con2->prepare('SELECT per_iId, dedo, template_binario, imagen_b64, fecha_captura FROM huella_biometrica ORDER BY fecha_captura DESC LIMIT ?');
        if ($stmt2) {
            $limitInt = max(0, (int)$limit);
            @$stmt2->bind_param('i', $limitInt);
            @$stmt2->execute();
            $res2 = @$stmt2->get_result();
            while ($res2 && ($row = $res2->fetch_assoc())) {
                if (empty($row['template_binario'])) {
                    continue;
                }
                $huellas[] = [
                    'per_iId' => (int)($row['per_iId'] ?? 0),
                    'dedo' => (string)($row['dedo'] ?? ''),
                    'template_binario' => (string)$row['template_binario'],
                    'imagen_b64' => isset($row['imagen_b64']) ? (string)$row['imagen_b64'] : '',
                    'fecha_captura' => (string)($row['fecha_captura'] ?? date('Y-m-d H:i:s')),
                ];
            }
            @$stmt2->close();
        }

        return ['trabajadores' => $trabajadores, 'huellas' => $huellas];
    }
}

if (!function_exists('syncPushCatalogToWebIfConfigured')) {
    function syncPushCatalogToWebIfConfigured(array $cfg, $con2 = null) {
        $base = rtrim((string)($cfg['cloud_base_url'] ?? ''), '/');
        $sede = trim((string)($cfg['sede_codigo'] ?? ''));
        $key = trim((string)($cfg['api_key'] ?? ''));

        if ($base === '' || $sede === '' || $key === '') {
            return ['ok' => false, 'mensaje' => 'Falta cloud_base_url/sede_codigo/api_key para push catalogo'];
        }

        if (!is_object($con2) || !($con2 instanceof mysqli)) {
            if (isset($GLOBALS['con2']) && $GLOBALS['con2'] instanceof mysqli) {
                $con2 = $GLOBALS['con2'];
            }
        }

        if (!is_object($con2) || !($con2 instanceof mysqli)) {
            return ['ok' => false, 'mensaje' => 'No hay conexión local para leer catálogo'];
        }

        $catalogo = syncCollectLocalCatalogForPush($con2, (int)($cfg['batch_limit'] ?? 200));
        $payload = [
            'sede_codigo' => $sede,
            'trabajadores' => $catalogo['trabajadores'],
            'huellas' => $catalogo['huellas'],
        ];

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 30,
                'header' => "Content-Type: application/json\r\n" .
                    "X-API-KEY: $key\r\n" .
                    "X-SEDE-CODIGO: $sede\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ignore_errors' => true,
            ],
        ]);

        $resp = @file_get_contents($base . '/api/sync/push_catalogo.php', false, $ctx);
        if ($resp === false) {
            return ['ok' => false, 'mensaje' => 'No se pudo conectar a cloud para enviar catálogo'];
        }

        $data = @json_decode($resp, true);
        if (!is_array($data)) {
            return ['ok' => false, 'mensaje' => 'Respuesta inválida de cloud al enviar catálogo'];
        }

        if (!($data['ok'] ?? false)) {
            return ['ok' => false, 'mensaje' => $data['mensaje'] ?? 'Cloud rechazó catálogo', 'respuesta' => $data];
        }

        return [
            'ok' => true,
            'mensaje' => $data['mensaje'] ?? 'Catálogo enviado',
            'total_trabajadores' => (int)($data['total_trabajadores'] ?? 0),
            'total_huellas' => (int)($data['total_huellas'] ?? 0),
            'respuesta' => $data,
        ];
    }
}

if (!function_exists('syncRunWorkerInline')) {
    function syncRunWorkerInline($cfg = null) {
        $SYNC_LOCAL_CONFIG = is_array($cfg) ? $cfg : syncResolveConfigForWorker();

        if (($SYNC_LOCAL_CONFIG['enabled'] ?? false) !== true) {
            return [
                'ok' => false,
                'mensaje' => 'Sync worker deshabilitado. Activa datos/sync_local_config.php',
                'storage' => offlineStoragePathInfo(),
            ];
        }

        $base = rtrim((string)($SYNC_LOCAL_CONFIG['cloud_base_url'] ?? ''), '/');
        $sede = (string)($SYNC_LOCAL_CONFIG['sede_codigo'] ?? '');
        $key = (string)($SYNC_LOCAL_CONFIG['api_key'] ?? '');
        $limit = (int)($SYNC_LOCAL_CONFIG['batch_limit'] ?? 200);

        if ($base === '' || $sede === '' || $key === '') {
            return [
                'ok' => false,
                'mensaje' => 'Falta cloud_base_url/sede_codigo/api_key en config local',
                'storage' => offlineStoragePathInfo(),
            ];
        }

        $result = [
            'ok' => true,
            'storage' => offlineStoragePathInfo(),
            'steps' => [],
            'time' => date('Y-m-d H:i:s')
        ];

        $ping = syncWorkerHttpPostInternal(
            $base . '/api/sync/ping_sede.php',
            [
                'sede_codigo' => $sede,
                'dispositivo_codigo' => (string)($SYNC_LOCAL_CONFIG['dispositivo_codigo'] ?? 'LOCAL'),
                'nombre_equipo' => (string)($SYNC_LOCAL_CONFIG['equipo_nombre'] ?? php_uname('n')),
            ],
            $sede,
            $key
        );
        $result['steps']['ping'] = $ping;

        $catalogo = syncWorkerHttpPostInternal(
            $base . '/api/sync/pull_catalogo.php',
            ['sede_codigo' => $sede],
            $sede,
            $key
        );

        if (is_array($catalogo) && ($catalogo['ok'] ?? false)) {
            $huellas = is_array($catalogo['huellas'] ?? null) ? $catalogo['huellas'] : [];
            offlineReplaceCacheRows($huellas);
            offlineMetaSet('cache_huellas_actualizada', date('Y-m-d H:i:s'));
            offlineMirrorMetaToJson();

            $result['steps']['pull_catalogo'] = [
                'ok' => true,
                'total_huellas' => count($huellas),
            ];
        } else {
            $result['steps']['pull_catalogo'] = [
                'ok' => false,
                'mensaje' => $catalogo['mensaje'] ?? 'No se pudo descargar catálogo'
            ];
        }

        $catalogoPush = syncPushCatalogToWebIfConfigured($SYNC_LOCAL_CONFIG, $GLOBALS['con2'] ?? null);
        $result['steps']['push_catalogo'] = $catalogoPush;

        $push = sincronizarAsistenciasOfflineCloud($base, $sede, $key, $limit);
        $result['steps']['push_asistencias'] = $push;

        if (!(($result['steps']['pull_catalogo']['ok'] ?? false) || ($push['ok'] ?? false))) {
            $result['ok'] = false;
            $result['mensaje'] = 'No se logró sincronizar con cloud';
        } else {
            $result['mensaje'] = 'Sync worker ejecutado';
        }

        $result['pendientes_final'] = obtenerPendientesOffline();
        return $result;
    }
}

if (!function_exists('syncExecuteWorkerOnce')) {
    function syncExecuteWorkerOnce() {
        $cfg = syncResolveConfigForWorker();

        if (!syncCanUseExec()) {
            $inline = syncRunWorkerInline($cfg);
            return [
                'ok' => (bool)($inline['ok'] ?? false),
                'mensaje' => ($inline['ok'] ?? false)
                    ? 'Worker ejecutado en modo inline (sin exec)'
                    : ($inline['mensaje'] ?? 'No se pudo ejecutar worker inline'),
                'detalle' => $inline,
                'raw' => json_encode($inline, JSON_UNESCAPED_UNICODE),
            ];
        }

        $phpBin = syncPhpBinaryPath();
        $worker = syncWorkerScriptPath();

        if (empty($worker) || !is_file($worker)) {
            return ['ok' => false, 'mensaje' => 'No se encontró sync_worker.php'];
        }

        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($worker);
        $output = [];
        $code = 0;
        @exec($cmd . ' 2>&1', $output, $code);

        $raw = implode("\n", $output);
        return [
            'ok' => $code === 0,
            'mensaje' => $code === 0 ? 'Worker ejecutado' : 'Worker falló',
            'code' => $code,
            'raw' => $raw,
        ];
    }
}

if (!function_exists('syncInstallScheduledTask')) {
    function syncInstallScheduledTask($taskName = 'Rtime Sync Worker', $minutes = 1) {
        if (!syncCanUseExec()) {
            return ['ok' => false, 'mensaje' => 'No se puede programar automático: exec deshabilitado'];
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            return syncInstallCronJob($minutes);
        }

        $phpBin = syncPhpBinaryPath();
        $worker = syncWorkerScriptPath();
        if (empty($worker) || !is_file($worker)) {
            return ['ok' => false, 'mensaje' => 'No se encontró sync_worker.php para programar'];
        }

        $minutes = max(1, (int)$minutes);
        $taskRun = '"' . $phpBin . ' ' . $worker . '"';
        $baseCmd = 'schtasks /Create /SC MINUTE /MO ' . $minutes . ' /TN "' . $taskName . '" /TR ' . $taskRun . ' /F';

        $attempts = [];
        $commands = [
            $baseCmd,
            $baseCmd . ' /RL HIGHEST /RU SYSTEM',
        ];

        $createCode = 1;
        $createOut = [];
        foreach ($commands as $command) {
            $out = [];
            $code = 1;
            @exec($command . ' 2>&1', $out, $code);
            $attempts[] = [
                'cmd' => $command,
                'exit_code' => $code,
                'output' => implode("\n", $out),
            ];

            if ($code === 0) {
                $createCode = 0;
                $createOut = $out;
                break;
            }
        }

        if ($createCode !== 0) {
            $last = end($attempts);
            $lastOutput = is_array($last) ? ($last['output'] ?? '') : '';
            return [
                'ok' => false,
                'mensaje' => 'No se pudo crear/actualizar la tarea programada',
                'output' => $lastOutput,
                'attempts' => $attempts,
                'exit_code' => 1,
            ];
        }

        $runCmd = 'schtasks /Run /TN "' . $taskName . '"';
        $runOut = [];
        $runCode = 1;
        @exec($runCmd . ' 2>&1', $runOut, $runCode);

        return [
            'ok' => true,
            'mensaje' => 'Tarea programada instalada/actualizada',
            'run_triggered' => ($runCode === 0),
            'create_output' => implode("\n", $createOut),
            'run_output' => implode("\n", $runOut),
            'task_to_run' => $taskRun,
        ];
    }
}

if (!function_exists('syncInstallCronJob')) {
    function syncInstallCronJob($minutes = 1) {
        $phpBin = syncPhpBinaryPath();
        $worker = syncWorkerScriptPath();
        if (empty($worker) || !is_file($worker)) {
            return ['ok' => false, 'mensaje' => 'No se encontró sync_worker.php para cron'];
        }

        $minutes = max(1, (int)$minutes);
        $cronExpr = ($minutes <= 1) ? '* * * * *' : '*/' . $minutes . ' * * * *';
        $marker = '# RTIME_SYNC_WORKER';
        $cronLine = $cronExpr . ' ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($worker) . ' >/dev/null 2>&1 ' . $marker;

        $checkOut = [];
        $checkCode = 1;
        @exec('crontab -l 2>/dev/null', $checkOut, $checkCode);

        if ($checkCode !== 0 && !empty($checkOut)) {
            return [
                'ok' => false,
                'mensaje' => 'No se pudo leer crontab actual',
                'output' => implode("\n", $checkOut),
            ];
        }

        $existing = [];
        foreach ($checkOut as $line) {
            if (stripos($line, 'RTIME_SYNC_WORKER') !== false) {
                continue;
            }
            $existing[] = $line;
        }
        $existing[] = $cronLine;

        $tmpFile = offlineDataDir() . '/crontab_rtime.tmp';
        $content = implode("\n", $existing) . "\n";
        if (@file_put_contents($tmpFile, $content, LOCK_EX) === false) {
            return ['ok' => false, 'mensaje' => 'No se pudo preparar archivo temporal de crontab'];
        }

        $installOut = [];
        $installCode = 1;
        @exec('crontab ' . escapeshellarg($tmpFile) . ' 2>&1', $installOut, $installCode);
        @unlink($tmpFile);

        if ($installCode !== 0) {
            return [
                'ok' => false,
                'mensaje' => 'No se pudo instalar cron automáticamente',
                'output' => implode("\n", $installOut),
                'exit_code' => $installCode,
            ];
        }

        return [
            'ok' => true,
            'mensaje' => 'Cron instalado/actualizado correctamente',
            'line' => $cronLine,
            'output' => implode("\n", $installOut),
        ];
    }
}

if (!function_exists('syncAutoProvisionAfterConfig')) {
    function syncAutoProvisionAfterConfig($taskEveryMinutes = 1) {
        $task = syncInstallScheduledTask('Rtime Sync Worker', $taskEveryMinutes);
        $worker = syncExecuteWorkerOnce();

        $taskOk = (bool)($task['ok'] ?? false);
        $workerOk = (bool)($worker['ok'] ?? false);
        $overallOk = $workerOk || $taskOk;

        if ($workerOk && $taskOk) {
            $message = 'Auto-instalación completada';
        } elseif ($workerOk && !$taskOk) {
            $message = 'Configuración activa; ejecución inmediata OK. No se pudo programar tarea automática.';
        } elseif (!$workerOk && $taskOk) {
            $message = 'Tarea automática creada, pero la primera ejecución inmediata falló.';
        } else {
            $message = 'No se pudo ejecutar ni programar la sincronización automática.';
        }

        return [
            'ok' => $overallOk,
            'task' => $task,
            'worker' => $worker,
            'mensaje' => $message,
        ];
    }
}

if (!function_exists('syncClearConfigForIp')) {
    function syncClearConfigForIp($ip) {
        $runtime = syncLoadRuntimeConfig();
        if (!isset($runtime['terminals']) || !is_array($runtime['terminals'])) {
            $runtime['terminals'] = [];
        }

        if (isset($runtime['terminals'][$ip])) {
            unset($runtime['terminals'][$ip]);
        }

        if (isset($runtime['default']) && is_array($runtime['default'])) {
            $runtime['default'] = [];
        }

        return syncSaveRuntimeConfig($runtime);
    }
}
