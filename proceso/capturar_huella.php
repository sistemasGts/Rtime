<?php
/**
 * Proceso de captura y verificación biométrica
 * 
 * Este archivo maneja:
 * - Captura desde el lector biométrico
 * - Comparación de templates
 * - Registro de asistencia
 */

define('DB_SOFT_FAIL', true);
require_once __DIR__ . '/../datos/db.php';
require_once __DIR__ . '/../datos/BiometricSensor.php';
require_once __DIR__ . '/../datos/offline_asistencia_helper.php';
require_once __DIR__ . '/../datos/sync_config_helper.php';

$clientIpSync = syncClientIp();
$SYNC_LOCAL_CONFIG = syncResolveConfigForIp($clientIpSync);

if (!function_exists('syncPushQueueToWebIfConfigured')) {
    function syncPushQueueToWebIfConfigured(array $cfg, $con2 = null) {
        $enabled = (bool)($cfg['enabled'] ?? false);
        $base = rtrim((string)($cfg['cloud_base_url'] ?? ''), '/');
        $sede = trim((string)($cfg['sede_codigo'] ?? ''));
        $apiKey = trim((string)($cfg['api_key'] ?? ''));
        $limit = (int)($cfg['batch_limit'] ?? 200);

        if (!$enabled || $base === '' || $sede === '' || $apiKey === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'mensaje' => 'Sync API no configurada para este terminal',
                'pendientes' => obtenerPendientesOffline(),
                'sincronizadas' => 0,
            ];
        }

        $sync = sincronizarAsistenciasOfflineCloud($base, $sede, $apiKey, $limit);

        if ($con2 instanceof mysqli && is_array($sync) && ($sync['ok'] ?? false)) {
            $accepted = is_array($sync['accepted_uids'] ?? null) ? $sync['accepted_uids'] : [];
            $duplicates = is_array($sync['duplicate_uids'] ?? null) ? $sync['duplicate_uids'] : [];
            $syncedUids = array_values(array_unique(array_merge($accepted, $duplicates)));
            if (!empty($syncedUids) && function_exists('marcarAsistenciasSyncEstadoLote')) {
                marcarAsistenciasSyncEstadoLote($con2, $syncedUids, 'synced');
            }
        }

        return $sync;
    }
}

if (!function_exists('syncEnsureAutoBackgroundProvision')) {
    function syncEnsureAutoBackgroundProvision(array $cfg) {
        $enabled = (bool)($cfg['enabled'] ?? false);
        if (!$enabled) {
            return;
        }

        if (!function_exists('offlineMetaGet') || !function_exists('offlineMetaSet')) {
            return;
        }

        $lastTry = (string)offlineMetaGet('sync_auto_provision_last_try', '');
        $now = time();
        $lastTs = $lastTry !== '' ? strtotime($lastTry) : 0;

        if ($lastTs !== false && $lastTs > 0 && ($now - $lastTs) < 86400) {
            return;
        }

        offlineMetaSet('sync_auto_provision_last_try', date('Y-m-d H:i:s'));

        if (function_exists('syncAutoProvisionAfterConfig')) {
            $auto = syncAutoProvisionAfterConfig(1);
            if (($auto['ok'] ?? false) && function_exists('offlineMetaSet')) {
                offlineMetaSet('sync_auto_provision_ok_at', date('Y-m-d H:i:s'));
            }
        }
    }
}

if (!function_exists('asegurarTablaEstadoSyncApiLocal')) {
    function asegurarTablaEstadoSyncApiLocal($con2) {
        if (!($con2 instanceof mysqli)) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS asistencias_api_sync_estado (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            offline_uid VARCHAR(64) NOT NULL UNIQUE,
            per_iId INT NOT NULL,
            fecha_hora_origen DATETIME NOT NULL,
            score DOUBLE DEFAULT NULL,
            origen VARCHAR(40) DEFAULT NULL,
            estado ENUM('queued','synced','failed') NOT NULL DEFAULT 'queued',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_estado (estado),
            KEY idx_per_fecha (per_iId, fecha_hora_origen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        return @$con2->query($sql) !== false;
    }
}

if (!function_exists('marcarAsistenciaSyncEstadoLocal')) {
    function marcarAsistenciaSyncEstadoLocal($con2, $offlineUid, $estado, $perId, $fechaHora, $score = 0, $origen = null) {
        if (!($con2 instanceof mysqli)) {
            return;
        }
        if (!asegurarTablaEstadoSyncApiLocal($con2)) {
            return;
        }

        $estado = in_array($estado, ['queued', 'synced', 'failed'], true) ? $estado : 'queued';
        $stmt = @$con2->prepare(
            "INSERT INTO asistencias_api_sync_estado (offline_uid, per_iId, fecha_hora_origen, score, origen, estado)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE estado = VALUES(estado), score = VALUES(score), origen = VALUES(origen), updated_at = CURRENT_TIMESTAMP"
        );
        if (!$stmt) {
            return;
        }

        @$stmt->bind_param('sisdss', $offlineUid, $perId, $fechaHora, $score, $origen, $estado);
        @$stmt->execute();
        @$stmt->close();
    }
}

if (!function_exists('marcarAsistenciasSyncEstadoLote')) {
    function marcarAsistenciasSyncEstadoLote($con2, array $uids, $estado = 'synced') {
        if (!($con2 instanceof mysqli) || empty($uids)) {
            return;
        }
        if (!asegurarTablaEstadoSyncApiLocal($con2)) {
            return;
        }

        $estado = in_array($estado, ['queued', 'synced', 'failed'], true) ? $estado : 'synced';

        $stmt = @$con2->prepare("UPDATE asistencias_api_sync_estado SET estado = ?, updated_at = CURRENT_TIMESTAMP WHERE offline_uid = ?");
        if (!$stmt) {
            return;
        }

        foreach ($uids as $uid) {
            $uidVal = (string)$uid;
            @$stmt->bind_param('ss', $estado, $uidVal);
            @$stmt->execute();
        }
        @$stmt->close();
    }
}

if (!function_exists('encolarAsistenciasLocalesPendientesApiSync')) {
    function encolarAsistenciasLocalesPendientesApiSync($con2, $limit = 300) {
        if (!($con2 instanceof mysqli)) {
            return 0;
        }
        if (!asegurarTablaEstadoSyncApiLocal($con2)) {
            return 0;
        }

        $limit = max(1, (int)$limit);
        $sql = "SELECT a.per_iId, a.fecha, a.hora, a.huella_similitud
                FROM asistencias a
                LEFT JOIN asistencias_api_sync_estado s
                  ON s.per_iId = a.per_iId
                 AND s.fecha_hora_origen = TIMESTAMP(a.fecha, a.hora)
                WHERE s.id IS NULL OR s.estado <> 'synced'
                ORDER BY a.fecha DESC, a.hora DESC
                LIMIT ?";

        $stmt = @$con2->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        @$stmt->bind_param('i', $limit);
        @$stmt->execute();
        $res = @$stmt->get_result();

        $encoladas = 0;
        while ($res && ($row = $res->fetch_assoc())) {
            $perId = (int)($row['per_iId'] ?? 0);
            if ($perId <= 0) {
                continue;
            }

            $fechaHora = trim((string)($row['fecha'] ?? '')) . ' ' . trim((string)($row['hora'] ?? ''));
            $score = (float)($row['huella_similitud'] ?? 0);
            $uid = offlineUidForAsistencia($perId, $fechaHora, $score);

            $queued = guardarAsistenciaOffline($perId, '', $score, $fechaHora, 'backfill_local');
            if (($queued['ok'] ?? false) === true) {
                $uidEstado = !empty($queued['offline_uid']) ? (string)$queued['offline_uid'] : $uid;
                marcarAsistenciaSyncEstadoLocal($con2, $uidEstado, 'queued', $perId, $fechaHora, $score, 'backfill_local');
                $encoladas++;
            }
        }

        @$stmt->close();
        return $encoladas;
    }
}

syncEnsureAutoBackgroundProvision($SYNC_LOCAL_CONFIG);

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
if (empty($_POST) && !empty($rawInput)) {
    $jsonInput = json_decode($rawInput, true);
    if (is_array($jsonInput)) {
        $_POST = array_merge($_POST, $jsonInput);
    }
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$per_iId = $_POST['per_iId'] ?? $_GET['per_iId'] ?? 0;

if ($accion === 'estado_offline') {
    echo json_encode([
        'success' => true,
        'pendientes' => obtenerPendientesOffline(),
        'storage_mode' => offlineStorageMode()
    ]);
    exit;
}

if ($accion === 'sync_offline') {
    if ($con2 instanceof mysqli && function_exists('encolarAsistenciasLocalesPendientesApiSync')) {
        encolarAsistenciasLocalesPendientesApiSync($con2, 300);
    }

    $sync = syncPushQueueToWebIfConfigured($SYNC_LOCAL_CONFIG, $con2);

    echo json_encode([
        'success' => (bool)($sync['ok'] ?? false),
        'sincronizadas' => (int)($sync['sincronizadas'] ?? 0),
        'pendientes' => (int)($sync['pendientes'] ?? obtenerPendientesOffline()),
        'mensaje' => $sync['mensaje'] ?? ''
    ]);
    exit;
}

if ($accion === 'capturar') {
    $isLive = isset($_POST['live']) || isset($_GET['live']);

    $cap = BiometricSensorManager::capturarHuella();
    if (!$cap['success']) {
        echo json_encode([
            'success' => false,
            'mensaje' => $cap['mensaje'] ?? 'Error en captura'
        ]);
        exit;
    }

    if ($isLive) {
        echo json_encode([
            'success' => true,
            'template' => $cap['template'] ?? '',
            'imagen' => $cap['imagenBuffer'] ?? ''
        ]);
        exit;
    }

    $templateCapturado = $cap['template'] ?? '';

    $cacheTry = actualizarCacheHuellasDesdeDB($con2, false);

    $resultado = BiometricSensorManager::verificarAsistencia(
        !empty($per_iId) ? intval($per_iId) : null,
        $templateCapturado,
        null
    );

    if (!$resultado['success']) {
        $fallback = verificarAsistenciaDesdeCacheLocal($templateCapturado);
        if (!$fallback['success']) {
            echo json_encode($resultado);
            exit;
        }

        $matchedId = intval($fallback['per_iId'] ?? 0);
        $score = floatval($fallback['score'] ?? 0);
        $nombre = $fallback['nombre'] ?? '';
        $dedo = $fallback['dedo'] ?? null;

        $eventoFechaHora = date('Y-m-d H:i:s');
        $queued = guardarAsistenciaOffline($matchedId, $nombre, $score, $eventoFechaHora, 'cache_local');
        if ($con2 instanceof mysqli && !empty($queued['offline_uid']) && function_exists('marcarAsistenciaSyncEstadoLocal')) {
            marcarAsistenciaSyncEstadoLocal($con2, (string)$queued['offline_uid'], 'queued', $matchedId, $eventoFechaHora, $score, 'cache_local');
        }
        $push = syncPushQueueToWebIfConfigured($SYNC_LOCAL_CONFIG, $con2);
        $pendientes = (int)(
            ($push['pendientes'] ?? null) !== null
                ? $push['pendientes']
                : ($queued['pendientes'] ?? obtenerPendientesOffline())
        );
        $sincronizadas = (int)($push['sincronizadas'] ?? 0);

        echo json_encode([
            'success' => true,
            'offline' => true,
            'mensaje' => 'Asistencia guardada offline (se sincronizará al volver internet)',
            'per_iId' => $matchedId,
            'nombre' => $nombre,
            'score' => $score,
            'dedo' => $dedo,
            'sincronizadas' => $sincronizadas,
            'pendientes_sync' => $pendientes
        ]);
        exit;
    }

    $matchedId = intval($resultado['per_iId'] ?? 0);
    $score = floatval($resultado['score'] ?? 0);
    $nombre = $resultado['nombre'] ?? '';
    $dedo = $resultado['dedo'] ?? null;

    $eventoFechaHora = date('Y-m-d H:i:s');
    $eventoFecha = date('Y-m-d', strtotime($eventoFechaHora));
    $eventoHora = date('H:i:s', strtotime($eventoFechaHora));

    $stmt = $con2->prepare(
        'INSERT INTO asistencias (per_iId, fecha, hora, huella_similitud) VALUES (?, ?, ?, ?)'
    );

    if ($stmt) {
        $stmt->bind_param('issd', $matchedId, $eventoFecha, $eventoHora, $score);
        if (!$stmt->execute()) {
            $stmt->close();

            $queued = guardarAsistenciaOffline($matchedId, $nombre, $score, $eventoFechaHora, 'insert_fail');
            if ($con2 instanceof mysqli && !empty($queued['offline_uid']) && function_exists('marcarAsistenciaSyncEstadoLocal')) {
                marcarAsistenciaSyncEstadoLocal($con2, (string)$queued['offline_uid'], 'queued', $matchedId, $eventoFechaHora, $score, 'insert_fail');
            }
            $push = syncPushQueueToWebIfConfigured($SYNC_LOCAL_CONFIG, $con2);
            $pendientes = (int)(
                ($push['pendientes'] ?? null) !== null
                    ? $push['pendientes']
                    : ($queued['pendientes'] ?? obtenerPendientesOffline())
            );
            $sincronizadas = (int)($push['sincronizadas'] ?? 0);
            echo json_encode([
                'success' => true,
                'offline' => true,
                'mensaje' => 'Asistencia guardada offline (error temporal de BD). Se sincronizará automáticamente.',
                'per_iId' => $matchedId,
                'nombre' => $nombre,
                'score' => $score,
                'dedo' => $dedo,
                'sincronizadas' => $sincronizadas,
                'pendientes_sync' => $pendientes
            ]);
            exit;
        }
        $stmt->close();
    }

    $queuedOnline = guardarAsistenciaOffline($matchedId, $nombre, $score, $eventoFechaHora, 'online_local');
    if ($con2 instanceof mysqli && !empty($queuedOnline['offline_uid']) && function_exists('marcarAsistenciaSyncEstadoLocal')) {
        marcarAsistenciaSyncEstadoLocal($con2, (string)$queuedOnline['offline_uid'], 'queued', $matchedId, $eventoFechaHora, $score, 'online_local');
    }
    $syncApi = syncPushQueueToWebIfConfigured($SYNC_LOCAL_CONFIG, $con2);
    $syncAuto = is_array($syncApi) ? $syncApi : [
        'ok' => false,
        'sincronizadas' => 0,
        'pendientes' => obtenerPendientesOffline(),
        'mensaje' => 'No se pudo sincronizar con Rt_web en este intento',
    ];

    echo json_encode([
        'success' => true,
        'mensaje' => $resultado['mensaje'] ?? 'Asistencia registrada',
        'per_iId' => $matchedId,
        'nombre' => $nombre,
        'score' => $score,
        'dedo' => $dedo,
        'cache_huellas' => $cacheTry['total'] ?? null,
        'sincronizadas' => (int)($syncAuto['sincronizadas'] ?? 0),
        'pendientes_sync' => (int)($syncAuto['pendientes'] ?? obtenerPendientesOffline())
    ]);
    exit;
} elseif ($accion === 'verificar_biometrica') {
    if (empty($per_iId)) {
        echo json_encode([
            'success' => false,
            'mensaje' => 'ID requerido'
        ]);
        exit;
    }

    $stmt = $con2->prepare(
        'SELECT COUNT(*) as total_huellas, GROUP_CONCAT(DISTINCT dedo) as dedos FROM huella_biometrica WHERE per_iId = ?'
    );
    $stmt->bind_param('i', $per_iId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'huellas_registradas' => $data['total_huellas'],
        'dedos' => explode(',', $data['dedos'] ?? '')
    ]);
    exit;
} else {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Acción no reconocida'
    ]);
    exit;
}
