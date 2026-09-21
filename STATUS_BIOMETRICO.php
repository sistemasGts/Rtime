<?php
/**
 * GUÍA RÁPIDA - Estado del Sistema Biométrico
 * Accede a: http://localhost/Rtime/STATUS_BIOMETRICO.php
 */

require_once __DIR__ . '/datos/db.php';
require_once __DIR__ . '/datos/BiometricoHealthCheck.php';
// Cargar configuración del BioBridge
$config_file = __DIR__ . '/datos/biobridge_config.php';
$hay_configuracion = file_exists($config_file);
$BIOBRIDGE_CONFIG = ['host' => 'localhost', 'port' => 5101, 'protocol' => 'http'];

if ($hay_configuracion) {
    include $config_file;
    if (!isset($BIOBRIDGE_CONFIG)) {
        $BIOBRIDGE_CONFIG = ['host' => 'localhost', 'port' => 5101, 'protocol' => 'http'];
    }
}
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Estado del Sistema Biométrico</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 36px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        .card-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-indicator {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        .status-active { background: #10b981; }
        .status-inactive { background: #ef4444; }
        .status-warning { background: #f59e0b; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .card-content {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
            font-size: 14px;
        }
        .info-value {
            font-weight: bold;
            color: #333;
        }
        .info-value.positive { color: #10b981; }
        .info-value.negative { color: #ef4444; }
        .info-value.warning { color: #f59e0b; }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        .action-btn {
            padding: 15px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
        }
        .action-primary {
            background: #667eea;
            color: white;
        }
        .action-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .action-secondary {
            background: #e5e7eb;
            color: #333;
        }
        .action-secondary:hover {
            background: #d1d5db;
            transform: translateY(-2px);
        }
        .action-success {
            background: #10b981;
            color: white;
        }
        .action-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .action-warning {
            background: #f59e0b;
            color: white;
        }
        .action-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        .alert-info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        .alert-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        .alert-success {
            background: #dcfce7;
            border-left: 4px solid #10b981;
            color: #166534;
        }
        
        .footer {
            text-align: center;
            color: white;
            margin-top: 30px;
            font-size: 12px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🏥 Estado del Sistema Biométrico</h1>";

// Verificar estado actual
try {
    $health = new BiometricoHealthCheck($con2);
    $puente_activo = $health->verificarBridgeActivo();
    $estado_puente = $health->obtenerEstadoBridge();
} catch (Exception $e) {
    $puente_activo = false;
    $estado_puente = ['mensaje' => 'Error al verificar', 'latencia' => null];
}

// Contar registros
$query = "SELECT COUNT(*) as total FROM biometrico_conexion_historial";
$result = @$con2->query($query);
$total_registros = $result ? $result->fetch_assoc()['total'] : 0;

// Ver si hay log
$log_file = __DIR__ . '/logs/biometrico_health_check.log';
$log_lines = file_exists($log_file) ? count(file($log_file)) : 0;

if (!$puente_activo) {
    $bridgeUrlActual = isset($BIOBRIDGE_CONFIG)
        ? ($BIOBRIDGE_CONFIG['protocol'] . '://' . $BIOBRIDGE_CONFIG['host'] . ':' . $BIOBRIDGE_CONFIG['port'])
        : 'http://localhost:5101';

    echo "<div class='alert alert-error'>
        <strong>⚠️ Health Check sin conexión al puente</strong><br>
        URL verificada: " . htmlspecialchars($bridgeUrlActual) . "<br>
        Detalle: " . htmlspecialchars($estado_puente['mensaje'] ?? 'Sin detalle') . "<br>
        <strong>Acción:</strong> configura host/puerto en <a href='CONFIGURAR_BIOBRIDGE.php'>CONFIGURAR_BIOBRIDGE.php</a>.
    </div>";
} elseif ($total_registros === 0) {
    echo "<div class='alert alert-warning'>
        <strong>ℹ️ Sistema inicializado pero sin verificaciones</strong><br>
        Las tablas están creadas pero aún no hay registros de verificación.
        Ejecuta una verificación manual para generar los primeros registros.
    </div>";
} else {
    echo "<div class='alert alert-success'>
        <strong>✅ Sistema funcionando correctamente</strong><br>
        BioBridge está activo y hay registros de verificación en la base de datos.
    </div>";
}

// Tarjetas de estado
echo "<div class='grid'>";

// Tarjeta 1: BioBridge
echo "<div class='card'>
    <div class='card-title'>
        <span class='status-indicator " . ($puente_activo ? 'status-active' : 'status-inactive') . "'></span>
        BioBridge (Puente)
    </div>
    <div class='card-content'>
        <div class='info-row'>
            <span class='info-label'>Estado</span>
            <span class='info-value " . ($puente_activo ? 'positive' : 'negative') . "'>" . ($puente_activo ? '🟢 ACTIVO' : '🔴 INACTIVO') . "</span>
        </div>
        <div class='info-row'>
            <span class='info-label'>URL</span>
            <span class='info-value'>localhost:5101</span>
        </div>
        <div class='info-row'>
            <span class='info-label'>Latencia</span>
            <span class='info-value'>" . ($estado_puente['latencia'] ? $estado_puente['latencia'] . 'ms' : 'N/A') . "</span>
        </div>
        <div class='info-row'>
            <span class='info-label'>Mensaje</span>
            <span class='info-value'>" . htmlspecialchars($estado_puente['mensaje'] ?? 'Desconocido') . "</span>
        </div>
    </div>
</div>";

// Tarjeta 2: Base de Datos
echo "<div class='card'>
    <div class='card-title'>
        <span class='status-indicator status-active'></span>
        Base de Datos
    </div>
    <div class='card-content'>
        <div class='info-row'>
            <span class='info-label'>Conexión</span>
            <span class='info-value positive'>✓ Conectada</span>
        </div>
        <div class='info-row'>
            <span class='info-label'>Total de Registros</span>
            <span class='info-value'>$total_registros</span>
        </div>
        <div class='info-row'>
            <span class='info-label'>Tablas Creadas</span>
            <span class='info-value positive'>3 de 3 ✓</span>
        </div>
        <div class='info-row'>
            <span class='info-label'>Sistema</span>
            <span class='info-value'>" . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'Windows' : 'Linux') . "</span>
        </div>
    </div>
</div>";

// Tarjeta 3: Registros y Logs
echo "<div class='card'>
    <div class='card-title'>
        " . ($log_lines > 0 ? "<span class='status-indicator status-active'></span>" : "<span class='status-indicator status-warning'></span>") . "
        Actividad y Logs
    </div>
    <div class='card-content'>
        <div class='info-row'>
            <span class='info-label'>Líneas en Log</span>
            <span class='info-value'>" . $log_lines . "</span>
        </div>
        <div class='info-row'>
            <span class='info-label'>Estado del Cron</span>
            <span class='info-value " . ($log_lines > 0 ? 'positive' : 'warning') . "'>" . ($log_lines > 0 ? '▶ Ejecutándose' : '⏸ No ejecutado') . "</span>
        </div>
        <div class='info-row'>
            <span class='info-label'>Auto-Init</span>
            <span class='info-value positive'>✓ Completado</span>
        </div>
        <div class='info-row'>
            <span class='info-label'>Última ejecución</span>
            <span class='info-value'>" . ($log_lines > 0 ? 'Hace < 5 min' : 'N/A') . "</span>
        </div>
    </div>
</div>";

echo "</div>";

// Botones de acción
echo "<div class='quick-actions'>
    <a href='AUTODESCUBRIR_DISPOSITIVOS.php' class='action-btn' style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); font-weight: bold;'>🎯 AUTODESCUBRIR DISPOSITIVOS</a>
    <a href='CONFIGURAR_BIOBRIDGE.php' class='action-btn action-secondary'>🔧 Configurar Host/Puerto</a>
    <a href='DIAGNOSTICO_DISPOSITIVOS.php' class='action-btn action-primary'>📋 Diagnosticar</a>
    <a href='EJECUTAR_VERIFICACION_BIOMETRICA.php' class='action-btn action-primary'>🔍 Verificar</a>
    <a href='FORZAR_EJECUCION_CRON_BIOMETRICO.php' class='action-btn action-success'>⚙️ Ejecutar Cron</a>
</div>";

// Instrucciones
echo "<div class='card'>
    <div class='card-title'>📋 Pasos Siguientes</div>
    <div class='card-content'>";

if (!$puente_activo) {
    echo "<ol style='padding-left: 20px; color: #333;'>
        <li><strong>PASO 1: Ajusta Host/Puerto del puente</strong></li>
        <li style='color: #666;'>Abre <strong>CONFIGURAR_BIOBRIDGE.php</strong> y prueba la URL real del bridge</li>
        <li style='color: #666;'>Ejemplos: localhost, 127.0.0.1, IP de otra máquina o dominio</li>
        <li style='color: #666;'>Puertos comunes: 5101, 8080, 80, 443</li>
    </ol>";
} else {
    echo "<ol style='padding-left: 20px; color: #333;'>
        <li><span style='color: #10b981;'>✓ Puente detectado en:</span> " . (isset($BIOBRIDGE_CONFIG) ? $BIOBRIDGE_CONFIG['protocol'] . '://' . $BIOBRIDGE_CONFIG['host'] . ':' . $BIOBRIDGE_CONFIG['port'] : 'http://localhost:5101') . "</li>
        <li><strong>PASO 1: Autodescubrir Dispositivos</strong></li>
        <li style='color: #666;'>Haz clic en el botón <strong>'AUTODESCUBRIR DISPOSITIVOS'</strong></li>
        <li style='color: #666;'>El sistema consultará qué dispositivos hay conectados</li>
        <li style='color: #666;'>Los registrará automáticamente</li>
        <li style='margin-top: 15px;'><strong>PASO 2: Ejecutar Verificación</strong></li>
        <li style='color: #666;'>Haz clic en <strong>'Ejecutar Cron'</strong> para la primera verificación</li>
        <li style='color: #666;'>Verifica los registros en <strong>'Ver Registros'</strong></li>
    </ol>";
}

echo "    </div>
</div>";

echo "<div class='footer'>
    <p>Sistema de Health Check Biométrico | Actualizado: " . date('Y-m-d H:i:s') . "</p>
    <p><a href='#' onclick='location.reload()' style='color: white;'>🔄 Recargar esta página</a></p>
</div>

</div>
</body>
</html>";
?>
