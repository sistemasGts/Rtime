<?php
/**
 * Script de verificación del setup automático del Health Check Biométrico
 * Accede a: http://localhost/Rtime/VERIFICAR_SETUP_BIOMETRICO.php
 */

require_once __DIR__ . '/datos/db.php';

$status = [
    'bases_de_datos' => [],
    'archivos' => [],
    'directorios' => [],
    'conexion_puente' => null,
    'estado_general' => 'OK'
];

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verificación Setup Biométrico</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        .section { margin: 20px 0; }
        .section h2 { color: #555; margin-bottom: 10px; font-size: 16px; }
        .item { margin: 8px 0; padding: 8px 12px; background: #f9f9f9; border-left: 4px solid #ddd; }
        .ok { border-left-color: #4CAF50; color: #2e7d32; }
        .error { border-left-color: #f44336; color: #c62828; }
        .warning { border-left-color: #ff9800; color: #e65100; }
        .log-preview { background: #1e1e1e; color: #00ff00; padding: 12px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; margin-top: 10px; }
        .summary { font-weight: bold; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .summary.ok { background: #c8e6c9; color: #2e7d32; }
        .summary.error { background: #ffcdd2; color: #c62828; }
        button { margin-top: 20px; padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Verificación del Setup Biométrico</h1>
    <p>Este script verifica que el Health Check Biométrico esté correctamente instalado y funcionando.</p>";

// 1. Verificar tablas de BD
echo "<div class='section'><h2>🗄️ Tablas de Base de Datos</h2>";
$tablas_esperadas = ['biometrico_conexion', 'biometrico_conexion_historial', 'biometrico_puente_conexion'];
foreach ($tablas_esperadas as $tabla) {
    $query = "SHOW TABLES LIKE '$tabla'";
    $result = @mysqli_query($con2, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<div class='item ok'>✓ Tabla '$tabla' existe</div>";
        $status['bases_de_datos'][$tabla] = 'OK';
    } else {
        echo "<div class='item error'>✗ Tabla '$tabla' NO EXISTE</div>";
        $status['bases_de_datos'][$tabla] = 'MISSING';
        $status['estado_general'] = 'ERROR';
    }
}
echo "</div>";

// 2. Verificar archivos clave
echo "<div class='section'><h2>📄 Archivos Clave</h2>";
$archivos_esperados = [
    'datos/BiometricoHealthCheck.php' => 'Clase central',
    'datos/biometrico_auto_init.php' => 'Auto-inicialización',
    'datos/biometrico_health_check.sql' => 'Schema SQL',
    'proceso/biometrico_health_check_cron.php' => 'Cron script',
    'proceso/biometrico_health_check_action.php' => 'API endpoint',
    'vistas/biometrico_salud.php' => 'Dashboard'
];

foreach ($archivos_esperados as $archivo => $descripcion) {
    $ruta = __DIR__ . '/' . $archivo;
    if (file_exists($ruta)) {
        $size = filesize($ruta);
        echo "<div class='item ok'>✓ $archivo ($size bytes) - $descripcion</div>";
        $status['archivos'][$archivo] = 'OK';
    } else {
        echo "<div class='item error'>✗ $archivo - $descripcion (NO ENCONTRADO)</div>";
        $status['archivos'][$archivo] = 'MISSING';
        $status['estado_general'] = 'ERROR';
    }
}
echo "</div>";

// 3. Verificar directorios
echo "<div class='section'><h2>📁 Directorios</h2>";
$directorios_esperados = ['logs'];
foreach ($directorios_esperados as $dir) {
    $ruta = __DIR__ . '/' . $dir;
    if (is_dir($ruta)) {
        echo "<div class='item ok'>✓ Directorio '/$dir' existe</div>";
        $status['directorios'][$dir] = 'OK';
        
        // Verificar archivo de logs
        if ($dir === 'logs' && file_exists($ruta . '/biometrico_health_check.log')) {
            $lines = count(file($ruta . '/biometrico_health_check.log'));
            echo "<div class='item ok'>  → Archivo de logs: " . ($lines > 0 ? "$lines líneas registradas" : "vacío") . "</div>";
            
            echo "<div class='log-preview'>";
            $log_lines = array_slice(file($ruta . '/biometrico_health_check.log'), -10);
            foreach ($log_lines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</div>";
        }
    } else {
        echo "<div class='item warning'>⚠ Directorio '/$dir' NO EXISTE (será creado automáticamente)</div>";
        $status['directorios'][$dir] = 'WILL_CREATE';
    }
}
echo "</div>";

// 4. Verificar conexión al BioBridge
echo "<div class='section'><h2>🌉 Conexión BioBridge</h2>";
$bridge_url = 'http://localhost:5101/health';
$inicio = microtime(true);
$contexto = stream_context_create([
    'http' => [
        'timeout' => 5,
        'method' => 'GET'
    ]
]);

$respuesta = @file_get_contents($bridge_url, false, $contexto);
$tiempo = (microtime(true) - $inicio) * 1000;

if ($respuesta !== false) {
    echo "<div class='item ok'>✓ BioBridge ACTIVO en $bridge_url (" . number_format($tiempo, 2) . "ms)</div>";
    $status['conexion_puente'] = 'ACTIVE';
} else {
    echo "<div class='item warning'>⚠ BioBridge NO RESPONDE en $bridge_url</div>";
    echo "<div class='item' style='margin-left: 20px; border-left-color: #ff9800;'>Si el puente aún no está activo, inicia BioBridge.exe y vuelve a verificar.</div>";
    $status['conexion_puente'] = 'INACTIVE';
}
echo "</div>";

// 5. Estado General
echo "<div class='section'>";
if ($status['estado_general'] === 'OK') {
    echo "<div class='summary ok'>✅ SETUP COMPLETADO CON ÉXITO</div>";
    echo "<p style='color: #2e7d32;'>";
    echo "El Health Check Biométrico está completamente instalado. Ahora:<br>";
    echo "1. El sistema verifica automáticamente cada 5 minutos<br>";
    echo "2. Accede al dashboard en: <strong><a href='/Rtime/vistas/biometrico_salud.php' style='color: #2e7d32;'>http://localhost/Rtime/vistas/biometrico_salud.php</a></strong><br>";
    echo "3. Los logs se guardan automáticamente en: <strong>/logs/biometrico_health_check.log</strong>";
    echo "</p>";
} else {
    echo "<div class='summary error'>❌ SETUP INCOMPLETO</div>";
    echo "<p style='color: #c62828;'>Revisa los errores arriba y ejecuta <strong>proceso/biometrico_health_check_setup.php</strong> manualmente si es necesario.</p>";
}
echo "</div>";

// Botones de acción
echo "<div style='margin-top: 30px;'>";
echo "<button onclick=\"location.href='/Rtime/vistas/biometrico_salud.php'\">📊 Ir al Dashboard</button>";
echo "<button onclick=\"location.reload()\" style='margin-left: 10px;'>🔄 Actualizar Verificación</button>";
echo "</div>";

echo "</div>
</body>
</html>";
?>
