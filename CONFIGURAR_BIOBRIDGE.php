<?php
/**
 * CONFIGURAR BIOBRIDGE
 * Permite especificar dónde está el BioBridge (IP, puerto, etc.)
 * Accede a: http://localhost/Rtime/CONFIGURAR_BIOBRIDGE.php
 */

require_once __DIR__ . '/datos/db.php';

// Archivo de configuración
$config_file = __DIR__ . '/datos/biobridge_config.php';

// Leer configuración existente
$bridge_config = [
    'host' => 'localhost',
    'port' => 5101,
    'protocol' => 'http',
    'timeout' => 5
];

if (file_exists($config_file)) {
    include $config_file;
    if (isset($BIOBRIDGE_CONFIG)) {
        $bridge_config = $BIOBRIDGE_CONFIG;
    }
}

// Procesar formulario
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_host = trim($_POST['host'] ?? '');
    $nuevo_port = intval($_POST['port'] ?? 5101);
    $nuevo_protocol = $_POST['protocol'] ?? 'http';
    
    if (empty($nuevo_host)) {
        $mensaje = '❌ El host no puede estar vacío';
        $tipo_mensaje = 'error';
    } else {
        // Guardar configuración
        $config_content = '<?php
// Configuración de BioBridge - Generado automáticamente
// NO EDITAR MANUALMENTE

$BIOBRIDGE_CONFIG = [
    "host" => "' . addslashes($nuevo_host) . '",
    "port" => ' . $nuevo_port . ',
    "protocol" => "' . addslashes($nuevo_protocol) . '",
    "timeout" => 5
];
?>';
        
        if (@file_put_contents($config_file, $config_content)) {
            $bridge_config = [
                'host' => $nuevo_host,
                'port' => $nuevo_port,
                'protocol' => $nuevo_protocol,
                'timeout' => 5
            ];
            $mensaje = '✓ Configuración guardada correctamente';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = '❌ Error al guardar la configuración';
            $tipo_mensaje = 'error';
        }
    }
}

// Construir URL
$bridge_url = $bridge_config['protocol'] . '://' . $bridge_config['host'] . ':' . $bridge_config['port'];

// Probar conexión
$puente_funciona = false;
$diagnostico = '';

$ctx = stream_context_create([
    'http' => ['timeout' => 2, 'method' => 'GET'],
    'ssl' => ['verify_peer' => false]
]);

$test = @file_get_contents($bridge_url . '/health', false, $ctx);
if ($test !== false) {
    $puente_funciona = true;
    $diagnostico = '✓ Conexión exitosa';
} else {
    $diagnostico = '✗ No hay respuesta en ' . $bridge_url . '/health';
}

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Configurar BioBridge</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-size: 14px;
        }
        .alert-success {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
            color: #15803d;
        }
        .alert-error {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }
        .alert-info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        
        .form-group {
            margin: 25px 0;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: monospace;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        button {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
            flex: 1;
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
        
        .status-box {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .status-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .status-value {
            font-family: monospace;
            font-size: 16px;
            color: #333;
            font-weight: bold;
        }
        .status-test {
            padding: 15px;
            background: #f5f5f5;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 13px;
        }
        .status-test.success {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
            color: #15803d;
        }
        .status-test.error {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }
        
        .examples {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            font-size: 13px;
        }
        .examples h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .example-item {
            margin: 10px 0;
            padding: 8px;
            background: white;
            border-left: 3px solid #ddd;
            font-family: monospace;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Configurar BioBridge</h1>
    <div class='subtitle'>Especifica dónde está ubicado tu BioBridge (IP, puerto, protocolo)</div>";

if ($mensaje) {
    $clase = ($tipo_mensaje === 'success') ? 'alert-success' : 'alert-error';
    echo "<div class='alert $clase'>$mensaje</div>";
}

echo "
    <div class='status-box'>
        <div class='status-label'>URL Actual Configurada</div>
        <div class='status-value'>$bridge_url</div>
        <div class='status-test " . ($puente_funciona ? 'success' : 'error') . "'>
            " . ($puente_funciona ? '✓ Conexión exitosa' : '✗ No responde') . " - $diagnostico
        </div>
    </div>";

if (!$puente_funciona) {
    echo "<div class='alert alert-info'>
        <strong>💡 BioBridge no responde en la URL configurada</strong><br>
        Esto puede ocurrir si:<br>
        • BioBridge está en otra máquina (no es localhost)<br>
        • BioBridge usa un puerto diferente a 5101<br>
        • Firewall bloqueando la conexión<br>
        • BioBridge no está iniciado<br><br>
        Consulta la dirección IP y puerto de tu BioBridge y actualiza abajo.
    </div>";
}

echo "
    <form method='POST'>
        <div class='form-group'>
            <label for='protocol'>Protocolo</label>
            <select name='protocol' id='protocol' required>
                <option value='http' " . ($bridge_config['protocol'] === 'http' ? 'selected' : '') . ">HTTP (puerto 80 o custom)</option>
                <option value='https' " . ($bridge_config['protocol'] === 'https' ? 'selected' : '') . ">HTTPS (puerto 443 o custom)</option>
            </select>
        </div>
        
        <div class='form-row'>
            <div class='form-group' style='margin: 0;'>
                <label for='host'>Host / IP</label>
                <input type='text' name='host' id='host' value='" . htmlspecialchars($bridge_config['host']) . "' placeholder='localhost o 192.168.1.100' required>
            </div>
            <div class='form-group' style='margin: 0;'>
                <label for='port'>Puerto</label>
                <input type='number' name='port' id='port' value='" . $bridge_config['port'] . "' min='1' max='65535' required>
            </div>
        </div>
        
        <div class='button-group'>
            <button type='submit' class='btn-primary'>💾 Guardar Configuración</button>
            <button type='button' class='btn-secondary' onclick='testConnection()'>🔌 Probar Conexión</button>
        </div>
    </form>
    
    <div class='examples'>
        <h3>📝 Ejemplos Comunes</h3>
        <div class='example-item'>
            <strong>LocalHost (en tu computadora):</strong><br>
            Host: <code>localhost</code> | Puerto: <code>5101</code>
        </div>
        <div class='example-item'>
            <strong>Red Local (otro servidor):</strong><br>
            Host: <code>192.168.1.50</code> | Puerto: <code>5101</code>
        </div>
        <div class='example-item'>
            <strong>En la Nube (servidor remoto):</strong><br>
            Host: <code>mi-servidor.com</code> o <code>1.2.3.4</code> | Puerto: <code>443</code> (HTTPS) o <code>5101</code>
        </div>
        <div class='example-item'>
            <strong>Docker Container:</strong><br>
            Host: <code>biobridge-container</code> | Puerto: <code>5101</code>
        </div>
    </div>
    
    <div class='alert alert-info'>
        <strong>🌐 Para cuando esté en la Nube:</strong><br>
        1. Usa la IP pública de tu servidor<br>
        2. O usa un dominio DNS (ej: mi-biobridge.com)<br>
        3. Asegúrate que el puerto esté abierto en el firewall<br>
        4. Considera usar HTTPS para seguridad
    </div>
    
    <div class='button-group' style='margin-top: 30px;'>
        <a href='STATUS_BIOMETRICO.php' style='padding: 12px 30px; background: #2196F3; color: white; text-decoration: none; border-radius: 6px; text-align: center;'>📊 Volver al Panel</a>
    </div>
</div>

<script>
function testConnection() {
    const host = document.getElementById('host').value;
    const port = document.getElementById('port').value;
    const protocol = document.getElementById('protocol').value;
    
    console.log('Probando: ' + protocol + '://' + host + ':' + port);
    alert('Prueba de conexión:\\n\\n' + protocol + '://' + host + ':' + port + '\\n\\n(El navegador intenta la conexión)');
}
</script>
</body>
</html>";
?>
