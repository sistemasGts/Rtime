<?php
/**
 * Ejecutar el cron biométrico AHORA (sin esperar 5 minutos)
 * Accede a: http://localhost/Rtime/FORZAR_EJECUCION_CRON_BIOMETRICO.php
 */

set_time_limit(60);
require_once __DIR__ . '/datos/db.php';
require_once __DIR__ . '/datos/BiometricoHealthCheck.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Forzar Ejecución Cron Biométrico</title>
    <style>
        body { 
            font-family: 'Arial', sans-serif; 
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { 
            color: #1e293b; 
            border-bottom: 3px solid #3b82f6; 
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .log-container {
            background: #0f172a;
            color: #00ff00;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #1e293b;
            line-height: 1.6;
        }
        .log-line {
            margin: 4px 0;
            word-wrap: break-word;
        }
        .log-success { color: #10b981; }
        .log-warning { color: #f59e0b; }
        .log-error { color: #ef4444; }
        .log-info { color: #60a5fa; }
        .timestamp { color: #64748b; }
        
        .summary-box {
            margin-top: 30px;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }
        .summary-success {
            background: #dcfce7;
            border-color: #16a34a;
            color: #15803d;
        }
        .summary-error {
            background: #fee2e2;
            border-color: #dc2626;
            color: #b91c1c;
        }
        .summary-warning {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
        }
        
        .stats {
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .stat-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #3b82f6;
        }
        .stat-label {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        button {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>⚙️ Forzar Ejecución del Cron Biométrico</h1>";

// Obtener estado actual antes de ejecutar
$query_before = "SELECT COUNT(*) as total FROM biometrico_conexion_historial";
$result_before = @$con2->query($query_before);
$registros_before = $result_before ? $result_before->fetch_assoc()['total'] : 0;

echo "<div class='log-container'>";
echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-info'>═════════════════════════════════════════</span></div>";
echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-info'>INICIANDO VERIFICACIÓN FORZADA</span></div>";
echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-info'>═════════════════════════════════════════</span></div>";

try {
    $health = new BiometricoHealthCheck($con2);
    
    // Paso 1: Verificar ponte
    echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-info'>→ Paso 1:</span> Verificando BioBridge...</div>";
    
    $puente_activo = $health->verificarBridgeActivo();
    $estado_puente = $health->obtenerEstadoBridge();
    
    if ($puente_activo) {
        echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-success'>✓ BioBridge ACTIVO</span> (Latencia: " . ($estado_puente['latencia'] ?? 'N/A') . "ms)</div>";
        
        // Paso 2: Si está activo, verificar dispositivos
        echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-info'>→ Paso 2:</span> Verificando dispositivos...</div>";
        
        $resultado = $health->verificarTodosBiometricos();
        
        if ($resultado['success'] ?? false) {
            echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-success'>✓ Verificación de dispositivos completada</span></div>";
            echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-success'>  Total dispositivos verificados:</span> " . ($resultado['total'] ?? 0) . "</div>";
            
            if (!empty($resultado['reportes'])) {
                foreach ($resultado['reportes'] as $reporte) {
                    $color = match($reporte['estado'] ?? 'error') {
                        'conectado' => 'log-success',
                        'desconectado' => 'log-error',
                        'lento' => 'log-warning',
                        default => 'log-error'
                    };
                    echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='$color'>  • " . htmlspecialchars($reporte['nombre'] ?? 'Dispositivo #' . ($reporte['id'] ?? '?')) . ":</span> " . ($reporte['estado'] ?? 'error') . " (" . ($reporte['latencia_ms'] ?? '?') . "ms)</div>";
                }
            }
        } else {
            echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-error'>✗ Error en verificación de dispositivos:</span> " . htmlspecialchars($resultado['mensaje'] ?? 'Error desconocido') . "</div>";
        }
    } else {
        echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-warning'>⚠ BioBridge INACTIVO</span></div>";
        echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-warning'>  Salteando verificación de dispositivos (BioBridge no responde)</span></div>";
        echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-warning'>  Razón:</span> " . htmlspecialchars($estado_puente['mensaje'] ?? 'Desconocida') . "</div>";
    }
    
    echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-info'>═════════════════════════════════════════</span></div>";
    echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-success'>VERIFICACIÓN COMPLETADA</span></div>";
    echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-info'>═════════════════════════════════════════</span></div>";
    
} catch (Exception $e) {
    echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-error'>✗ EXCEPCIÓN:</span> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='log-line'><span class='timestamp'>[" . date('Y-m-d H:i:s') . "]</span> <span class='log-error'>Archivo:</span> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</div>";
}

echo "</div>";

// Comparar registros antes y después
$query_after = "SELECT COUNT(*) as total FROM biometrico_conexion_historial";
$result_after = @$con2->query($query_after);
$registros_after = $result_after ? $result_after->fetch_assoc()['total'] : 0;
$registros_nuevos = $registros_after - $registros_before;

// Determinar resumen
$resumen_clase = ($puente_activo) ? 'summary-success' : 'summary-warning';
$resumen_titulo = ($puente_activo) ? '✅ Ejecución Completada' : '⚠️ Ejecución Parcial';
$resumen_msg = ($puente_activo) 
    ? 'El cron se ejecutó correctamente. Los dispositivos fueron verificados.' 
    : 'BioBridge no está activo. No se verificaron dispositivos.';

echo "<div class='summary-box $resumen_clase'>
    <strong>$resumen_titulo</strong><br>
    $resumen_msg
</div>";

echo "<div class='stats'>
    <div class='stat-card'>
        <div class='stat-number'>$registros_nuevos</div>
        <div class='stat-label'>Registros nuevos agregados</div>
    </div>
    <div class='stat-card'>
        <div class='stat-number'>$registros_after</div>
        <div class='stat-label'>Total registros en historial</div>
    </div>
    <div class='stat-card'>
        <div class='stat-number'>" . ($puente_activo ? '🟢 ACTIVO' : '🔴 INACTIVO') . "</div>
        <div class='stat-label'>Estado BioBridge</div>
    </div>
</div>";

echo "<div class='actions'>
    <button class='btn-primary' onclick=\"location.href='DEBUG_BIOMETRICO_REGISTROS.php'\">📊 Ver Registros</button>
    <button class='btn-secondary' onclick=\"location.reload()\">🔄 Ejecutar de Nuevo</button>
    <button class='btn-secondary' onclick=\"location.href='index.php'\">🏠 Volver al Inicio</button>
</div>";

echo "</div>
</body>
</html>";
?>
