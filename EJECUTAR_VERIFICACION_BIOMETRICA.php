<?php
/**
 * Script para ejecutar verificación inmediata del Health Check
 * Accede a: http://localhost/Rtime/EJECUTAR_VERIFICACION_BIOMETRICA.php
 */

set_time_limit(30);
require_once __DIR__ . '/datos/db.php';
require_once __DIR__ . '/datos/BiometricoHealthCheck.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Ejecutar Verificación Biométrica</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { 
            color: #333; 
            border-bottom: 3px solid #667eea; 
            padding-bottom: 15px;
        }
        .section { 
            margin: 25px 0; 
            padding: 15px; 
            background: #f5f5f5; 
            border-left: 4px solid #667eea;
            border-radius: 5px;
        }
        .check-header {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .check-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .success { 
            color: #16a34a; 
            font-weight: bold;
        }
        .error { 
            color: #dc2626; 
            font-weight: bold;
        }
        .warning { 
            color: #f59e0b; 
            font-weight: bold;
        }
        .info {
            color: #2563eb;
            font-weight: bold;
        }
        .value {
            color: #666;
            font-family: monospace;
            margin-left: 10px;
        }
        .buttons {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        button {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .summary {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            font-size: 16px;
        }
        .summary.success {
            background: #dcfce7;
            border: 2px solid #16a34a;
            color: #15803d;
        }
        .summary.error {
            background: #fee2e2;
            border: 2px solid #dc2626;
            color: #b91c1c;
        }
        .live-log {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 15px;
        }
        .log-line {
            margin: 4px 0;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 Ejecutar Verificación Biométrica Manual</h1>
    <p style='color: #666;'>Haz clic en los botones a continuación para ejecutar verificaciones manuales:</p>";

// Verificaciones disponibles
$acciones = [
    'verificar_puente' => [
        'nombre' => '🌉 Verificar BioBridge',
        'descripcion' => 'Verifica si BioBridge (puente) está activo',
        'color' => '#2563eb'
    ],
    'verificar_todos' => [
        'nombre' => '📱 Verificar Todos los Dispositivos',
        'descripcion' => 'Verifica la conexión de todos los dispositivos biométricos',
        'color' => '#667eea'
    ],
];

$accion = $_GET['accion'] ?? '';

// Si hay acción solicitada, ejecutar
if ($accion && isset($acciones[$accion])) {
    echo "<div class='section'>";
    echo "<div class='check-header'>" . $acciones[$accion]['nombre'] . "</div>";
    echo "<p>" . $acciones[$accion]['descripcion'] . "</p>";
    echo "<div class='live-log'>";
    
    try {
        $health = new BiometricoHealthCheck($con2);
        
        if ($accion === 'verificar_puente') {
            echo "<div class='log-line'>[" . date('Y-m-d H:i:s') . "] Iniciando verificación de BioBridge...</div>";
            
            $es_activo = $health->verificarBridgeActivo();
            $estado_bridge = $health->obtenerEstadoBridge();
            
            if ($es_activo) {
                echo "<div class='log-line' style='color: #90EE90;'>✓ BioBridge ACTIVO</div>";
                echo "<div class='log-line'>  Latencia: <span class='value'>" . ($estado_bridge['latencia'] ?? 'N/A') . "ms</span></div>";
                echo "<div class='log-line'>  URL: <span class='value'>http://localhost:5101/</span></div>";
                echo "<div class='log-line'>  Mensaje: <span class='value'>" . htmlspecialchars($estado_bridge['mensaje'] ?? '') . "</span></div>";
                echo "<div class='summary success'>✅ BioBridge está respondiendo correctamente</div>";
            } else {
                echo "<div class='log-line' style='color: #FF6B6B;'>✗ BioBridge INACTIVO</div>";
                echo "<div class='log-line'>  Error: <span class='value'>" . htmlspecialchars($estado_bridge['mensaje'] ?? 'Error desconocido') . "</span></div>";
                echo "<div class='summary error'>❌ BioBridge no está respondiendo. Verifica que esté iniciado en http://localhost:5101/</div>";
            }
        } 
        elseif ($accion === 'verificar_todos') {
            echo "<div class='log-line'>[" . date('Y-m-d H:i:s') . "] Iniciando verificación de dispositivos...</div>";
            
            $resultado = $health->verificarTodosBiometricos();
            
            if (isset($resultado['error'])) {
                echo "<div class='log-line' style='color: #FF6B6B;'>✗ Error: " . htmlspecialchars($resultado['error']) . "</div>";
                echo "<div class='summary error'>❌ " . htmlspecialchars($resultado['error']) . "</div>";
            } else if (isset($resultado['puente_activo']) && !$resultado['puente_activo']) {
                echo "<div class='log-line' style='color: #FFD700;'>⚠️ BioBridge no está activo</div>";
                echo "<div class='log-line'>No se puede verificar dispositivos si el puente no responde</div>";
                echo "<div class='summary warning'>⚠️ BioBridge inactivo - Verifica http://localhost:5101/</div>";
            } else {
                echo "<div class='log-line' style='color: #90EE90;'>✓ Verificación completada</div>";
                
                if (isset($resultado['dispositivos']) && count($resultado['dispositivos']) > 0) {
                    foreach ($resultado['dispositivos'] as $dispositivo) {
                        $estado_color = match($dispositivo['estado'] ?? 'error') {
                            'conectado' => '#90EE90',
                            'desconectado' => '#FF6B6B',
                            'lento' => '#FFD700',
                            default => '#FF6B6B'
                        };
                        echo "<div class='log-line' style='color: $estado_color;'>  • " . htmlspecialchars($dispositivo['nombre'] ?? 'ID: ' . ($dispositivo['id'] ?? '?')) . " - " . ($dispositivo['estado'] ?? 'error') . " (" . ($dispositivo['latencia_ms'] ?? '?') . "ms)</div>";
                    }
                    echo "<div class='summary success'>✅ Verificación finalizada exitosamente</div>";
                } else {
                    echo "<div class='log-line' style='color: #FFD700;'>⚠️ No hay dispositivos registrados en la BD</div>";
                    echo "<div class='summary warning'>⚠️ Registra dispositivos antes de verificar</div>";
                }
            }
        }
        
        echo "<div class='log-line'>[" . date('Y-m-d H:i:s') . "] Verificación finalizada</div>";
        
    } catch (Exception $e) {
        echo "<div class='log-line' style='color: #FF6B6B;'>✗ Excepción: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='summary error'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "</div>";
    echo "</div>";
}

// Mostrar botones de acción
echo "<div class='buttons'>";
foreach ($acciones as $key => $info) {
    echo "<form method='GET' style='flex: 1;'>";
    echo "<input type='hidden' name='accion' value='$key'>";
    echo "<button type='submit' class='btn-primary' style='width: 100%;'>" . $info['nombre'] . "</button>";
    echo "</form>";
}
echo "</div>";

// Link de ayuda
echo "<div class='section' style='background: #e0e7ff; border-left-color: #667eea; margin-top: 30px;'>";
echo "<p><strong>📊 Ver registros en las tablas:</strong> <a href='DEBUG_BIOMETRICO_REGISTROS.php' style='color: #667eea; font-weight: bold;'>DEBUG_BIOMETRICO_REGISTROS.php</a></p>";
echo "<p><strong>✓ Verificar setup completo:</strong> <a href='VERIFICAR_SETUP_BIOMETRICO.php' style='color: #667eea; font-weight: bold;'>VERIFICAR_SETUP_BIOMETRICO.php</a></p>";
echo "<p><strong>⚙️ Forzar ejecución del cron ahora:</strong> <a href='FORZAR_EJECUCION_CRON_BIOMETRICO.php' style='color: #667eea; font-weight: bold;'>FORZAR_EJECUCION_CRON_BIOMETRICO.php</a> (sin esperar 5 minutos)</p>";
echo "</div>";

echo "</div>
</body>
</html>";
?>
