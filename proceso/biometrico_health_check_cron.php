<?php
/**
 * Health Check de Biométricos - Cron cada 5 minutos
 * INTELIGENTE: 
 * - Verifica si el BioBridge está activo PRIMERO
 * - Si está DOWN: registra evento pero NO verifica dispositivos
 * - Si está UP: verifica todos los dispositivos cada 5 minutos
 * - Mantiene registro de cuándo se conectó/desconectó el puente
 * 
 * Uso: ejecutar cada 5 minutos con php C:\xampp\htdocs\Rtime\proceso\biometrico_health_check_cron.php
 */

if (php_sapi_name() !== 'cli') {
    die("❌ Solo CLI\n");
}

define('RTIME_ROOT', dirname(__DIR__));
define('LOG_PATH', RTIME_ROOT . '/logs');

if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

$logFile = LOG_PATH . '/biometrico_health_check.log';
$logHandle = fopen($logFile, 'a');

function log_msg($msg, $prefix = '') {
    global $logHandle;
    $timestamp = date('[Y-m-d H:i:s]');
    $prefix_str = $prefix ? " [$prefix]" : '';
    $line = $timestamp . $prefix_str . ' ' . $msg . "\n";
    fwrite($logHandle, $line);
}

log_msg('═════════════════════════════════════════════════════', 'INICIO');

try {
    require_once RTIME_ROOT . '/datos/db.php';
    require_once RTIME_ROOT . '/datos/BiometricoHealthCheck.php';
    
    $checker = new BiometricoHealthCheck($con2);
    
    // PASO 1: Verificar el BioBridge
    log_msg('Verificando disponibilidad del BioBridge...', 'CHECK-PUENTE');
    
    $puenteActivo = $checker->verificarBridgeActivo();
    $estadoPuente = $checker->obtenerEstadoBridge();
    
    if ($estadoPuente['activo']) {
        log_msg('✓ BioBridge ACTIVO (latencia: ' . $estadoPuente['latencia'] . 'ms)', 'PUENTE-OK');
        
        // PASO 2: Solo si puente está activo, verificar dispositivos
        log_msg('BioBridge activo. Verificando dispositivos...', 'CHECK-DISPOSITIVOS');
        
        $resultado = $checker->verificarTodosBiometricos();
        
        if ($resultado['success']) {
            log_msg('✓ Verificación completada', 'DISPOSITIVOS-OK');
            
            foreach ($resultado['reportes'] as $reporte) {
                $latencia = $reporte['latencia_ms'] ?? '?';
                $obs = $reporte['observacion'] ? ' (' . $reporte['observacion'] . ')' : '';
                log_msg(
                    "[{$reporte['estado']}] {$reporte['nombre']}:{$reporte['puerto']} → {$latencia}ms{$obs}",
                    'DISPOSITIVO'
                );
            }
            log_msg("Total verificados: {$resultado['total']}", 'RESUMEN');
        } else {
            log_msg('✗ Error: ' . $resultado['mensaje'], 'ERROR-DISPOSITIVOS');
        }
        
    } else {
        // BioBridge NO está activo
        log_msg('✗ BioBridge INACTIVO/DESCONECTADO', 'PUENTE-DOWN');
        log_msg('Razón: ' . $estadoPuente['mensaje'], 'PUENTE-DETALLE');
        log_msg('Verificación de dispositivos CANCELADA (esperar a que puente se active)', 'SKIP');
        
        // Registrar que el puente está caído
        $sql = "INSERT INTO biometrico_conexion_historial 
                (biometrico_id, estado_anterior, estado_nuevo, observacion, fecha_cambio)
                VALUES (0, NULL, 'desconectado', 'BioBridge no disponible', NOW())";
        @$con2->query($sql);
    }
    
    log_msg('═════════════════════════════════════════════════════', 'FIN');
    log_msg('', '');
    
} catch (Exception $e) {
    log_msg('✗ EXCEPCIÓN: ' . $e->getMessage(), 'EXCEPTION');
    log_msg('Stack: ' . $e->getTraceAsString(), 'STACK');
    log_msg('═════════════════════════════════════════════════════', 'FIN-ERROR');
}

fclose($logHandle);
?>
