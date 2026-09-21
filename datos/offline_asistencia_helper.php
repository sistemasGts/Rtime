<?php

if (!function_exists('offlineUseSqlite')) {
    function offlineUseSqlite() {
        return class_exists('SQLite3');
    }
}

if (!function_exists('offlineStorageMode')) {
    function offlineStorageMode() {
        return offlineUseSqlite() ? 'sqlite' : 'json';
    }
}

if (!function_exists('isDbAvailable')) {
    function isDbAvailable($con2) {
        return ($con2 instanceof mysqli);
    }
}

if (!function_exists('offlineDataDir')) {
    function offlineDataDir() {
        static $resolved = null;

        if ($resolved !== null) {
            return $resolved;
        }

        $candidates = [];

        $envDir = getenv('RTIME_OFFLINE_DIR');
        if (!empty($envDir)) {
            $candidates[] = $envDir;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $programData = getenv('ProgramData');
            if (!empty($programData)) {
                $candidates[] = rtrim($programData, '\\/') . DIRECTORY_SEPARATOR . 'Rtime' . DIRECTORY_SEPARATOR . 'offline';
            }
        }

        $candidates[] = __DIR__ . '/offline_store';

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                $resolved = $dir;
                return $resolved;
            }
        }

        $resolved = __DIR__ . '/offline_store';
        if (!is_dir($resolved)) {
            @mkdir($resolved, 0755, true);
        }
        return $resolved;
    }
}

if (!function_exists('offlineStoragePathInfo')) {
    function offlineStoragePathInfo() {
        return [
            'mode' => offlineStorageMode(),
            'data_dir' => offlineDataDir(),
            'sqlite_file' => offlineSqliteFile(),
            'queue_file' => offlineQueueFile(),
            'cache_file' => offlineCacheFile(),
            'meta_file' => offlineMetaFile(),
        ];
    }
}

if (!function_exists('offlineQueueFile')) {
    function offlineQueueFile() {
        return offlineDataDir() . '/asistencias_queue.json';
    }
}

if (!function_exists('offlineCacheFile')) {
    function offlineCacheFile() {
        return offlineDataDir() . '/huellas_cache.json';
    }
}

if (!function_exists('offlineMetaFile')) {
    function offlineMetaFile() {
        return offlineDataDir() . '/meta.json';
    }
}

if (!function_exists('offlineSqliteFile')) {
    function offlineSqliteFile() {
        return offlineDataDir() . '/offline_cache.sqlite';
    }
}

if (!function_exists('offlineSqlite')) {
    function offlineSqlite() {
        static $db = null;

        if (!offlineUseSqlite()) {
            return null;
        }

        if ($db instanceof SQLite3) {
            return $db;
        }

        $db = new SQLite3(offlineSqliteFile());
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode=WAL;');
        $db->exec('PRAGMA synchronous=NORMAL;');

        $db->exec('CREATE TABLE IF NOT EXISTS queue_asistencias (
            offline_uid TEXT PRIMARY KEY,
            per_iId INTEGER NOT NULL,
            nombre TEXT,
            score REAL,
            fecha_hora TEXT NOT NULL,
            origen TEXT,
            creado_en TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS cache_huellas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            per_iId INTEGER NOT NULL,
            nombre TEXT,
            dedo TEXT,
            template_binario TEXT NOT NULL
        )');

        $db->exec('CREATE INDEX IF NOT EXISTS idx_cache_per ON cache_huellas(per_iId)');

        $db->exec('CREATE TABLE IF NOT EXISTS meta (
            k TEXT PRIMARY KEY,
            v TEXT
        )');

        offlineMigrateJsonToSqlite($db);
        return $db;
    }
}

if (!function_exists('offlineMetaGet')) {
    function offlineMetaGet($key, $default = null) {
        $db = offlineSqlite();
        if (!($db instanceof SQLite3)) {
            $meta = readJsonFileSafe(offlineMetaFile(), []);
            return $meta[$key] ?? $default;
        }

        $stmt = $db->prepare('SELECT v FROM meta WHERE k = :k LIMIT 1');
        $stmt->bindValue(':k', $key, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
        return $row['v'] ?? $default;
    }
}

if (!function_exists('offlineMetaSet')) {
    function offlineMetaSet($key, $value) {
        $db = offlineSqlite();
        if (!($db instanceof SQLite3)) {
            $meta = readJsonFileSafe(offlineMetaFile(), []);
            $meta[$key] = $value;
            return writeJsonFileSafe(offlineMetaFile(), $meta);
        }

        $stmt = $db->prepare('INSERT INTO meta(k, v) VALUES(:k, :v) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
        $stmt->bindValue(':k', $key, SQLITE3_TEXT);
        $stmt->bindValue(':v', (string)$value, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }
}

if (!function_exists('offlineMigrateJsonToSqlite')) {
    function offlineMigrateJsonToSqlite($db = null) {
        if (!offlineUseSqlite()) {
            return;
        }

        $db = $db instanceof SQLite3 ? $db : offlineSqlite();
        if (!($db instanceof SQLite3)) {
            return;
        }

        $already = offlineMetaGet('json_migrated', '0');
        if ($already === '1') {
            return;
        }

        $queue = readJsonFileSafe(offlineQueueFile(), []);
        foreach ($queue as $item) {
            $stmt = $db->prepare('INSERT OR IGNORE INTO queue_asistencias(offline_uid, per_iId, nombre, score, fecha_hora, origen, creado_en)
                                  VALUES(:uid, :per, :nombre, :score, :fecha_hora, :origen, :creado_en)');
            $stmt->bindValue(':uid', (string)($item['offline_uid'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':per', (int)($item['per_iId'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':nombre', (string)($item['nombre'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':score', (float)($item['score'] ?? 0), SQLITE3_FLOAT);
            $stmt->bindValue(':fecha_hora', (string)($item['fecha_hora'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':origen', (string)($item['origen'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':creado_en', (string)($item['creado_en'] ?? date('Y-m-d H:i:s')), SQLITE3_TEXT);
            $stmt->execute();
        }

        $cache = readJsonFileSafe(offlineCacheFile(), []);
        if (!empty($cache)) {
            $db->exec('DELETE FROM cache_huellas');
            foreach ($cache as $item) {
                $stmt = $db->prepare('INSERT INTO cache_huellas(per_iId, nombre, dedo, template_binario)
                                      VALUES(:per, :nombre, :dedo, :tpl)');
                $stmt->bindValue(':per', (int)($item['per_iId'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':nombre', (string)($item['nombre'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':dedo', (string)($item['dedo'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':tpl', (string)($item['template_binario'] ?? ''), SQLITE3_TEXT);
                $stmt->execute();
            }
        }

        $meta = readJsonFileSafe(offlineMetaFile(), []);
        foreach ($meta as $k => $v) {
            offlineMetaSet($k, is_scalar($v) ? (string)$v : json_encode($v));
        }

        offlineMetaSet('json_migrated', '1');
    }
}

if (!function_exists('offlineMirrorQueueToJson')) {
    function offlineMirrorQueueToJson() {
        $db = offlineSqlite();
        if (!($db instanceof SQLite3)) {
            return;
        }

        $res = $db->query('SELECT offline_uid, per_iId, nombre, score, fecha_hora, origen, creado_en FROM queue_asistencias ORDER BY creado_en ASC');
        $rows = [];
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) {
            $rows[] = $r;
        }
        writeJsonFileSafe(offlineQueueFile(), $rows);
    }
}

if (!function_exists('offlineMirrorCacheToJson')) {
    function offlineMirrorCacheToJson() {
        $db = offlineSqlite();
        if (!($db instanceof SQLite3)) {
            return;
        }

        $res = $db->query('SELECT per_iId, nombre, dedo, template_binario FROM cache_huellas');
        $rows = [];
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) {
            $rows[] = $r;
        }
        writeJsonFileSafe(offlineCacheFile(), $rows);
    }
}

if (!function_exists('offlineMirrorMetaToJson')) {
    function offlineMirrorMetaToJson() {
        $db = offlineSqlite();
        if (!($db instanceof SQLite3)) {
            return;
        }

        $res = $db->query('SELECT k, v FROM meta');
        $meta = [];
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) {
            $meta[$r['k']] = $r['v'];
        }
        writeJsonFileSafe(offlineMetaFile(), $meta);
    }
}

if (!function_exists('readJsonFileSafe')) {
    function readJsonFileSafe($path, $default = []) {
        if (!file_exists($path)) {
            return $default;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $default;
        }

        $data = @json_decode($raw, true);
        return is_array($data) ? $data : $default;
    }
}

if (!function_exists('writeJsonFileSafe')) {
    function writeJsonFileSafe($path, $data) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }
        return @file_put_contents($path, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('offlineUidForAsistencia')) {
    function offlineUidForAsistencia($per_iId, $fechaHora, $score) {
        return sha1($per_iId . '|' . $fechaHora . '|' . round((float)$score, 2));
    }
}

if (!function_exists('obtenerPendientesOffline')) {
    function obtenerPendientesOffline() {
        $db = offlineSqlite();
        if ($db instanceof SQLite3) {
            $res = $db->querySingle('SELECT COUNT(*) FROM queue_asistencias');
            return (int)$res;
        }

        $queue = readJsonFileSafe(offlineQueueFile(), []);
        return count($queue);
    }
}

if (!function_exists('guardarAsistenciaOffline')) {
    function guardarAsistenciaOffline($per_iId, $nombre, $score, $fechaHora = null, $origen = 'offline') {
        $fechaHora = $fechaHora ?: date('Y-m-d H:i:s');
        $uid = offlineUidForAsistencia($per_iId, $fechaHora, $score);

        $db = offlineSqlite();
        if ($db instanceof SQLite3) {
            $stmt = $db->prepare('INSERT OR IGNORE INTO queue_asistencias(offline_uid, per_iId, nombre, score, fecha_hora, origen, creado_en)
                                  VALUES(:uid, :per, :nombre, :score, :fecha_hora, :origen, :creado_en)');
            $stmt->bindValue(':uid', $uid, SQLITE3_TEXT);
            $stmt->bindValue(':per', (int)$per_iId, SQLITE3_INTEGER);
            $stmt->bindValue(':nombre', (string)$nombre, SQLITE3_TEXT);
            $stmt->bindValue(':score', (float)$score, SQLITE3_FLOAT);
            $stmt->bindValue(':fecha_hora', $fechaHora, SQLITE3_TEXT);
            $stmt->bindValue(':origen', (string)$origen, SQLITE3_TEXT);
            $stmt->bindValue(':creado_en', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $ok = $stmt->execute();

            if ($ok === false) {
                return ['ok' => false, 'error' => 'No se pudo guardar en cola local SQLite'];
            }

            offlineMirrorQueueToJson();
            return ['ok' => true, 'offline_uid' => $uid, 'pendientes' => obtenerPendientesOffline()];
        }

        $queue = readJsonFileSafe(offlineQueueFile(), []);

        foreach ($queue as $item) {
            if (($item['offline_uid'] ?? '') === $uid) {
                return ['ok' => true, 'duplicado' => true, 'offline_uid' => $uid, 'pendientes' => count($queue)];
            }
        }

        $queue[] = [
            'offline_uid' => $uid,
            'per_iId' => (int)$per_iId,
            'nombre' => (string)$nombre,
            'score' => (float)$score,
            'fecha_hora' => $fechaHora,
            'origen' => $origen,
            'creado_en' => date('Y-m-d H:i:s')
        ];

        if (!writeJsonFileSafe(offlineQueueFile(), $queue)) {
            return ['ok' => false, 'error' => 'No se pudo guardar en cola local'];
        }

        return ['ok' => true, 'offline_uid' => $uid, 'pendientes' => count($queue)];
    }
}

if (!function_exists('asegurarTablaSyncOffline')) {
    function asegurarTablaSyncOffline($con2) {
        if (!isDbAvailable($con2)) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS asistencias_offline_sync (
            id INT AUTO_INCREMENT PRIMARY KEY,
            offline_uid VARCHAR(64) NOT NULL UNIQUE,
            per_iId INT NOT NULL,
            fecha_hora_origen DATETIME NOT NULL,
            score DOUBLE DEFAULT NULL,
            fecha_sync DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_per_iId (per_iId),
            KEY idx_fecha_sync (fecha_sync)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        return @$con2->query($sql) !== false;
    }
}

if (!function_exists('sincronizarAsistenciasOffline')) {
    function sincronizarAsistenciasOffline($con2) {
        if (!isDbAvailable($con2)) {
            return ['ok' => false, 'sincronizadas' => 0, 'pendientes' => obtenerPendientesOffline(), 'mensaje' => 'Sin conexión a BD'];
        }

        $dbLocal = offlineSqlite();
        $queue = [];
        if ($dbLocal instanceof SQLite3) {
            $resQueue = $dbLocal->query('SELECT offline_uid, per_iId, nombre, score, fecha_hora, origen, creado_en FROM queue_asistencias ORDER BY creado_en ASC');
            while ($resQueue && ($r = $resQueue->fetchArray(SQLITE3_ASSOC))) {
                $queue[] = $r;
            }
        } else {
            $queue = readJsonFileSafe(offlineQueueFile(), []);
        }

        if (empty($queue)) {
            return ['ok' => true, 'sincronizadas' => 0, 'pendientes' => 0, 'mensaje' => 'Sin pendientes'];
        }

        if (!asegurarTablaSyncOffline($con2)) {
            return ['ok' => false, 'sincronizadas' => 0, 'pendientes' => count($queue), 'mensaje' => 'No se pudo preparar tabla de sincronización'];
        }

        $restantes = [];
        $sincronizadas = 0;

        foreach ($queue as $item) {
            $uid = $item['offline_uid'] ?? '';
            $per_iId = (int)($item['per_iId'] ?? 0);
            $score = (float)($item['score'] ?? 0);
            $fechaHora = $item['fecha_hora'] ?? '';

            if ($uid === '' || $per_iId <= 0 || $fechaHora === '') {
                continue;
            }

            $checkSync = @$con2->prepare("SELECT id FROM asistencias_offline_sync WHERE offline_uid = ? LIMIT 1");
            if ($checkSync) {
                @$checkSync->bind_param('s', $uid);
                @$checkSync->execute();
                $resSync = @$checkSync->get_result();
                $yaSync = ($resSync && $resSync->num_rows > 0);
                @$checkSync->close();
                if ($yaSync) {
                    $sincronizadas++;
                    continue;
                }
            }

            $fecha = date('Y-m-d', strtotime($fechaHora));
            $hora = date('H:i:s', strtotime($fechaHora));

            $existsAsistencia = false;
            $checkAsis = @$con2->prepare(
                "SELECT id FROM asistencias
                 WHERE per_iId = ?
                   AND fecha = ?
                   AND ABS(TIMESTAMPDIFF(SECOND, hora, ?)) <= 5
                 LIMIT 1"
            );
            if ($checkAsis) {
                @$checkAsis->bind_param('iss', $per_iId, $fecha, $hora);
                @$checkAsis->execute();
                $resAsis = @$checkAsis->get_result();
                $existsAsistencia = ($resAsis && $resAsis->num_rows > 0);
                @$checkAsis->close();
            }

            if (!$existsAsistencia) {
                $ins = @$con2->prepare(
                    "INSERT INTO asistencias (per_iId, fecha, hora, huella_similitud)
                     VALUES (?, ?, ?, ?)"
                );

                if (!$ins) {
                    $restantes[] = $item;
                    continue;
                }

                @$ins->bind_param('issd', $per_iId, $fecha, $hora, $score);
                $okIns = @$ins->execute();
                @$ins->close();

                if (!$okIns) {
                    $restantes[] = $item;
                    continue;
                }
            }

            $mark = @$con2->prepare(
                "INSERT INTO asistencias_offline_sync (offline_uid, per_iId, fecha_hora_origen, score)
                 VALUES (?, ?, ?, ?)"
            );

            if ($mark) {
                @$mark->bind_param('sisd', $uid, $per_iId, $fechaHora, $score);
                @$mark->execute();
                @$mark->close();
            }

            $sincronizadas++;
        }

        if ($dbLocal instanceof SQLite3) {
            $dbLocal->exec('DELETE FROM queue_asistencias');
            foreach ($restantes as $item) {
                $stmt = $dbLocal->prepare('INSERT OR IGNORE INTO queue_asistencias(offline_uid, per_iId, nombre, score, fecha_hora, origen, creado_en)
                                          VALUES(:uid, :per, :nombre, :score, :fecha_hora, :origen, :creado_en)');
                $stmt->bindValue(':uid', (string)($item['offline_uid'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':per', (int)($item['per_iId'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':nombre', (string)($item['nombre'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':score', (float)($item['score'] ?? 0), SQLITE3_FLOAT);
                $stmt->bindValue(':fecha_hora', (string)($item['fecha_hora'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':origen', (string)($item['origen'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':creado_en', (string)($item['creado_en'] ?? date('Y-m-d H:i:s')), SQLITE3_TEXT);
                $stmt->execute();
            }
            offlineMirrorQueueToJson();
        } else {
            writeJsonFileSafe(offlineQueueFile(), $restantes);
        }

        return [
            'ok' => true,
            'sincronizadas' => $sincronizadas,
            'pendientes' => count($restantes),
            'mensaje' => 'Sincronización completada'
        ];
    }
}

if (!function_exists('actualizarCacheHuellasDesdeDB')) {
    function actualizarCacheHuellasDesdeDB($con2, $forzar = false) {
        if (!isDbAvailable($con2)) {
            return ['ok' => false, 'mensaje' => 'Sin conexión a BD para actualizar cache'];
        }

        $lastMeta = offlineMetaGet('cache_huellas_actualizada', '');
        $last = $lastMeta ? strtotime($lastMeta) : 0;
        $stale = (time() - $last) > 300;

        if (!$forzar && !$stale) {
            return ['ok' => true, 'mensaje' => 'Cache vigente'];
        }

        $sql = "SELECT hb.per_iId, hb.template_binario, hb.dedo, t.per_vcNombres AS nombre
                FROM huella_biometrica hb
                LEFT JOIN trabajador t ON t.per_iId = hb.per_iId
                ORDER BY hb.fecha_captura DESC";

        $res = @$con2->query($sql);
        if (!$res) {
            return ['ok' => false, 'mensaje' => 'No se pudo leer huellas desde BD'];
        }

        $rows = [];
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['template_binario'])) {
                $rows[] = [
                    'per_iId' => (int)$r['per_iId'],
                    'nombre' => (string)($r['nombre'] ?? ''),
                    'dedo' => (string)($r['dedo'] ?? ''),
                    'template_binario' => (string)$r['template_binario']
                ];
            }
        }

        $dbLocal = offlineSqlite();
        if ($dbLocal instanceof SQLite3) {
            $dbLocal->exec('DELETE FROM cache_huellas');
            foreach ($rows as $item) {
                $stmt = $dbLocal->prepare('INSERT INTO cache_huellas(per_iId, nombre, dedo, template_binario)
                                          VALUES(:per, :nombre, :dedo, :tpl)');
                $stmt->bindValue(':per', (int)$item['per_iId'], SQLITE3_INTEGER);
                $stmt->bindValue(':nombre', (string)$item['nombre'], SQLITE3_TEXT);
                $stmt->bindValue(':dedo', (string)$item['dedo'], SQLITE3_TEXT);
                $stmt->bindValue(':tpl', (string)$item['template_binario'], SQLITE3_TEXT);
                $stmt->execute();
            }
            offlineMirrorCacheToJson();
        } else {
            if (!writeJsonFileSafe(offlineCacheFile(), $rows)) {
                return ['ok' => false, 'mensaje' => 'No se pudo escribir cache local'];
            }
        }

        offlineMetaSet('cache_huellas_actualizada', date('Y-m-d H:i:s'));
        offlineMirrorMetaToJson();

        return ['ok' => true, 'total' => count($rows), 'mensaje' => 'Cache actualizada'];
    }
}

if (!function_exists('verificarAsistenciaDesdeCacheLocal')) {
    function verificarAsistenciaDesdeCacheLocal($templateCapturado) {
        $cache = [];
        $db = offlineSqlite();
        if ($db instanceof SQLite3) {
            $res = $db->query('SELECT per_iId, nombre, dedo, template_binario FROM cache_huellas');
            while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) {
                $cache[] = $r;
            }
        } else {
            $cache = readJsonFileSafe(offlineCacheFile(), []);
        }

        if (empty($cache)) {
            return ['success' => false, 'mensaje' => 'Sin cache local de huellas'];
        }

        $bestScore = 0;
        $best = null;
        $comparaciones = 0;

        foreach ($cache as $item) {
            $comparaciones++;
            $comp = BiometricSensorManager::compararTemplates($item['template_binario'] ?? '', $templateCapturado);
            $score = (float)($comp['score'] ?? 0);

            if ($score >= $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        if ($best !== null && $bestScore > 0) {
            return [
                'success' => true,
                'per_iId' => (int)$best['per_iId'],
                'nombre' => (string)($best['nombre'] ?? ''),
                'score' => $bestScore,
                'dedo' => (string)($best['dedo'] ?? ''),
                'comparaciones' => $comparaciones,
                'mensaje' => 'Huella verificada con cache local'
            ];
        }

        return [
            'success' => false,
            'mensaje' => 'No coincide con cache local de huellas',
            'comparaciones' => $comparaciones
        ];
    }
}

if (!function_exists('offlineGetQueueRows')) {
    function offlineGetQueueRows($limit = 0) {
        $rows = [];
        $db = offlineSqlite();

        if ($db instanceof SQLite3) {
            $query = 'SELECT offline_uid, per_iId, nombre, score, fecha_hora, origen, creado_en FROM queue_asistencias ORDER BY creado_en ASC';
            if ($limit > 0) {
                $query .= ' LIMIT ' . (int)$limit;
            }

            $res = $db->query($query);
            while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) {
                $rows[] = $r;
            }
            return $rows;
        }

        $rows = readJsonFileSafe(offlineQueueFile(), []);
        if ($limit > 0) {
            return array_slice($rows, 0, $limit);
        }
        return $rows;
    }
}

if (!function_exists('offlineReplaceQueueRows')) {
    function offlineReplaceQueueRows(array $rows) {
        $db = offlineSqlite();
        if ($db instanceof SQLite3) {
            $db->exec('DELETE FROM queue_asistencias');
            foreach ($rows as $item) {
                $stmt = $db->prepare('INSERT OR IGNORE INTO queue_asistencias(offline_uid, per_iId, nombre, score, fecha_hora, origen, creado_en)
                                      VALUES(:uid, :per, :nombre, :score, :fecha_hora, :origen, :creado_en)');
                $stmt->bindValue(':uid', (string)($item['offline_uid'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':per', (int)($item['per_iId'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':nombre', (string)($item['nombre'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':score', (float)($item['score'] ?? 0), SQLITE3_FLOAT);
                $stmt->bindValue(':fecha_hora', (string)($item['fecha_hora'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':origen', (string)($item['origen'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':creado_en', (string)($item['creado_en'] ?? date('Y-m-d H:i:s')), SQLITE3_TEXT);
                $stmt->execute();
            }
            offlineMirrorQueueToJson();
            return true;
        }

        return writeJsonFileSafe(offlineQueueFile(), array_values($rows));
    }
}

if (!function_exists('offlineRemoveQueueByUids')) {
    function offlineRemoveQueueByUids(array $uids) {
        if (empty($uids)) {
            return true;
        }

        $uidsMap = array_fill_keys($uids, true);
        $rows = offlineGetQueueRows(0);
        $restantes = [];
        foreach ($rows as $item) {
            $uid = (string)($item['offline_uid'] ?? '');
            if (!isset($uidsMap[$uid])) {
                $restantes[] = $item;
            }
        }

        return offlineReplaceQueueRows($restantes);
    }
}

if (!function_exists('offlineReplaceCacheRows')) {
    function offlineReplaceCacheRows(array $rows) {
        $db = offlineSqlite();
        if ($db instanceof SQLite3) {
            $db->exec('DELETE FROM cache_huellas');
            foreach ($rows as $item) {
                if (empty($item['template_binario'])) {
                    continue;
                }
                $stmt = $db->prepare('INSERT INTO cache_huellas(per_iId, nombre, dedo, template_binario)
                                      VALUES(:per, :nombre, :dedo, :tpl)');
                $stmt->bindValue(':per', (int)($item['per_iId'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':nombre', (string)($item['nombre'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':dedo', (string)($item['dedo'] ?? ''), SQLITE3_TEXT);
                $stmt->bindValue(':tpl', (string)($item['template_binario'] ?? ''), SQLITE3_TEXT);
                $stmt->execute();
            }
            offlineMirrorCacheToJson();
            return true;
        }

        return writeJsonFileSafe(offlineCacheFile(), $rows);
    }
}

if (!function_exists('sincronizarAsistenciasOfflineCloud')) {
    function sincronizarAsistenciasOfflineCloud($cloudBaseUrl, $sedeCodigo, $apiKey, $limit = 200) {
        $queue = offlineGetQueueRows($limit);
        if (empty($queue)) {
            return ['ok' => true, 'sincronizadas' => 0, 'pendientes' => 0, 'mensaje' => 'Sin pendientes'];
        }

        $base = rtrim((string)$cloudBaseUrl, '/');
        if ($base === '') {
            return ['ok' => false, 'sincronizadas' => 0, 'pendientes' => count(offlineGetQueueRows(0)), 'mensaje' => 'URL cloud no configurada'];
        }

        $url = $base . '/api/sync/push_asistencias.php';
        $payload = json_encode([
            'sede_codigo' => $sedeCodigo,
            'eventos' => array_values($queue)
        ], JSON_UNESCAPED_UNICODE);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 20,
                'header' => "Content-Type: application/json\r\n" .
                            "X-API-KEY: " . $apiKey . "\r\n" .
                            "X-SEDE-CODIGO: " . $sedeCodigo . "\r\n",
                'content' => $payload,
                'ignore_errors' => true,
            ]
        ]);

        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return [
                'ok' => false,
                'sincronizadas' => 0,
                'pendientes' => count(offlineGetQueueRows(0)),
                'mensaje' => 'No se pudo conectar a API cloud'
            ];
        }

        $data = @json_decode($resp, true);
        if (!is_array($data) || !isset($data['ok'])) {
            return [
                'ok' => false,
                'sincronizadas' => 0,
                'pendientes' => count(offlineGetQueueRows(0)),
                'mensaje' => 'Respuesta inválida de cloud'
            ];
        }

        if (!$data['ok']) {
            return [
                'ok' => false,
                'sincronizadas' => 0,
                'pendientes' => count(offlineGetQueueRows(0)),
                'mensaje' => $data['mensaje'] ?? 'Cloud rechazó sincronización'
            ];
        }

        $accepted = is_array($data['accepted_uids'] ?? null) ? $data['accepted_uids'] : [];
        $duplicates = is_array($data['duplicate_uids'] ?? null) ? $data['duplicate_uids'] : [];
        $remove = array_values(array_unique(array_merge($accepted, $duplicates)));

        offlineRemoveQueueByUids($remove);

        return [
            'ok' => true,
            'sincronizadas' => count($remove),
            'pendientes' => count(offlineGetQueueRows(0)),
            'mensaje' => $data['mensaje'] ?? 'Sync cloud completada',
            'accepted_uids' => $accepted,
            'duplicate_uids' => $duplicates,
        ];
    }
}
