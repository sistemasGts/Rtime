<?php

require_once __DIR__ . '/../datos/db.php';
require_once __DIR__ . '/../datos/offline_asistencia_helper.php';
require_once __DIR__ . '/../datos/sync_config_helper.php';

header('Content-Type: application/json; charset=utf-8');
$result = syncRunWorkerInline(syncResolveConfigForWorker());
echo json_encode($result, JSON_UNESCAPED_UNICODE);
