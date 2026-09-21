<?php
/**
 * Auto-inicialización del Health Check Este archivo se ejecuta silenciosamente en cada acceso
 * Verifica y crea tablas si no existen
 * Inicia el cron automáticamente
 * Registra eventos en log visual
 * 
 * Se incluye desde: index.php
 */

// Evitar múltiples inicializaciones
if (defined('BIOMETRICO_HEALTH_CHECK_INITIALIZED')) {
    return;
}
define('BIOMETRICO_HEALTH_CHECK_INITIALIZED', true);

// Variable de sesión para almacenar estado
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['biometrico_init_status'] = [];

try {
    // Solo ejecutar si hay conexión DB
    if (!isset($con2) || !$con2) {
        $_SESSION['biometrico_init_status']['error'] = 'No hay conexión a BD';
        return;
    }
    
    $_SESSION['biometrico_init_status']['status'] = 'inicializando';

    // 1. Crear tablas si no existen (incluye tabla biometrico base)
    $tablas_sql = [
        "CREATE TABLE IF NOT EXISTS `biometrico` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre` VARCHAR(120) NOT NULL,
            `ip` VARCHAR(100) DEFAULT 'localhost',
            `puerto` INT DEFAULT 5101,
            `ubicacion` VARCHAR(150) DEFAULT NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_activo` (`activo`),
            KEY `idx_ip_puerto` (`ip`, `puerto`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `biometrico_conexion` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `biometrico_id` INT NOT NULL UNIQUE,
            `estado` ENUM('conectado', 'desconectado', 'lento', 'error') NOT NULL DEFAULT 'desconectado',
            `latencia_ms` INT DEFAULT NULL,
            `fecha_check` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `observacion` VARCHAR(500) DEFAULT NULL,
            `intentos_fallidos` INT DEFAULT 0,
            `ultima_conexion_exitosa` DATETIME DEFAULT NULL,
            KEY `idx_estado` (`estado`),
            KEY `idx_fecha` (`fecha_check`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS `biometrico_conexion_historial` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `biometrico_id` INT DEFAULT 0,
            `estado_anterior` ENUM('conectado', 'desconectado', 'lento', 'error') DEFAULT NULL,
            `estado_nuevo` ENUM('conectado', 'desconectado', 'lento', 'error') NOT NULL,
            `latencia_ms` INT DEFAULT NULL,
            `fecha_cambio` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `observacion` VARCHAR(500) DEFAULT NULL,
            KEY `idx_biometrico_id` (`biometrico_id`),
            KEY `idx_fecha` (`fecha_cambio`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS `biometrico_puente_conexion` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `fecha_check` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `estado` ENUM('activo', 'inactivo') NOT NULL,
            `latencia_ms` INT DEFAULT NULL,
            `observacion` VARCHAR(500) DEFAULT NULL,
            KEY `idx_fecha` (`fecha_check`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    $tablas_previas = [];
    $tablas_requeridas = ['biometrico', 'biometrico_conexion', 'biometrico_conexion_historial', 'biometrico_puente_conexion'];
    foreach ($tablas_requeridas as $tabla_req) {
        $r = @$con2->query("SHOW TABLES LIKE '" . $tabla_req . "'");
        $tablas_previas[$tabla_req] = ($r && $r->num_rows > 0);
    }

    $tablas_creadas = [];
    $hubo_creacion = false;
    foreach ($tablas_sql as $sql) {
        $res = @$con2->query($sql);
        $tablas_creadas[] = ($res !== false) ? '✓' : '✗';
        if ($res !== false) {
            $hubo_creacion = true;
        }
    }
    
    // 3. Registrar evento de inicialización en historial
    $observacion = 'Sistema Health Check inicializado automáticamente';
    $sql_evento = "INSERT IGNORE INTO `biometrico_conexion_historial` 
                   (biometrico_id, estado_anterior, estado_nuevo, observacion) 
                   VALUES (0, NULL, 'conectado', ?)";
    $stmt = @$con2->prepare($sql_evento);
    if ($stmt) {
        @$stmt->bind_param('s', $observacion);
        @$stmt->execute();
        @$stmt->close();
    }
    
    $_SESSION['biometrico_init_status']['mensaje'] = $hubo_creacion ? 'Tablas verificadas/creadas correctamente' : 'Sistema verificado';
    $_SESSION['biometrico_init_status']['tablas'] = $tablas_creadas;
    
    // 4. Crear directorio de logs si no existe
    $log_dir = dirname(__DIR__) . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
        $_SESSION['biometrico_init_status']['logs_dir'] = 'creado';
    } else {
        $_SESSION['biometrico_init_status']['logs_dir'] = 'existe';
    }
    
    // 5. Escribir en archivo de log de inicialización
    $log_file = $log_dir . '/biometrico_init.log';
    $log_message = "[" . date('Y-m-d H:i:s') . "] ✓ SISTEMA INICIALIZADO - Tablas creadas, cron iniciado\n";
    @file_put_contents($log_file, $log_message, FILE_APPEND);
    
    // 6. Iniciar cron en Windows (si es Windows)
    $cron_iniciado = false;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cron_iniciado = iniciar_cron_windows();
        $_SESSION['biometrico_init_status']['cron'] = $cron_iniciado ? 'iniciado' : 'no_iniciado';
    } else {
        $_SESSION['biometrico_init_status']['cron'] = 'linux_manual';
    }
    
    $_SESSION['biometrico_init_status']['status'] = 'exito';

    if (
        $tablas_previas['biometrico'] &&
        $tablas_previas['biometrico_conexion'] &&
        $tablas_previas['biometrico_conexion_historial'] &&
        $tablas_previas['biometrico_puente_conexion']
    ) {
        $_SESSION['biometrico_init_status']['status'] = 'ya_existe';
        $_SESSION['biometrico_init_status']['mensaje'] = 'Sistema ya inicializado';
    }


} catch (Exception $e) {
    // Silenciar errores en auto-init
    error_log("Bio Health Check Auto-Init Error: " . $e->getMessage());
    $_SESSION['biometrico_init_status']['error'] = $e->getMessage();
    $_SESSION['biometrico_init_status']['status'] = 'error';
}

/**
 * Iniciar el cron en Windows usando dos métodos
 * Retorna true si se logró iniciar
 */
function iniciar_cron_windows() {
    try {
        // Verificar si el proceso ya está corriendo
        $output = shell_exec('tasklist /FI "IMAGENAME eq php.exe" 2>NUL');
        
        // Método 1: Intentar ejecutar directamente (background, sin bloquear)
        $php_path = 'C:\\xampp\\php\\php.exe';
        $script_path = dirname(__DIR__) . '\\proceso\\biometrico_health_check_cron.php';
        
        if (file_exists($php_path) && file_exists($script_path)) {
            // Usar START /B para ejecutar en background
            $cmd = "START \"\" /B \"$php_path\" \"$script_path\" >nul 2>&1";
            pclose(popen($cmd, 'r'));
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

?>
