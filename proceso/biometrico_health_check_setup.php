<?php
/**
 * Setup del Sistema de Health Check (Biométricos)
 * Este script:
 * 1. Crea las tablas necesarias
 * 2. Valida que BioBridge esté corriendo
 * 3. Genera instrucciones de configuración
 */

if (php_sapi_name() !== 'cli') {
    die("❌ Solo CLI\n");
}

echo "\n╔═══════════════════════════════════════════════════════╗\n";
echo "║  SETUP: Health Check de Biométricos                   ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

$root = dirname(__DIR__);
$logDir = $root . '/logs';

// Crear directorio de logs
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    echo "✓ Directorio logs creado\n";
}

// Conectar BD
require_once $root . '/datos/db.php';

if (!$con2) {
    die("❌ No se pudo conectar a BD\n");
}

echo "✓ Conectado a BD\n";

// Crear tablas
echo "\n📋 Creando tablas de health check...\n";

$sqls = [
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

foreach ($sqls as $i => $sql) {
    if ($con2->query($sql)) {
        $tables = ['biometrico_conexion', 'biometrico_conexion_historial', 'biometrico_puente_conexion'];
        echo "  ✓ {$tables[$i]} creada\n";
    } else {
        echo "  ❌ Error tabla " . ($i+1) . ": " . $con2->error . "\n";
    }
}

// Verificar tabla biometrico
echo "\n🔍 Verificando dispositivos...\n";

$result = $con2->query("SELECT COUNT(*) as total FROM biometrico");
if ($result) {
    $row = $result->fetch_assoc();
    echo "  ✓ Encontrados: " . ($row['total'] ?? 0) . " dispositivos\n";
} else {
    echo "  ⚠️  Tabla 'biometrico' no existe o no hay permisos\n";
}

// Probar BioBridge
echo "\n🔌 Verificando BioBridge...\n";

$inicio = microtime(true);
$ctx = stream_context_create([
    'http' => ['method' => 'GET', 'timeout' => 2],
    'ssl' => ['verify_peer' => false]
]);

$response = @file_get_contents('http://localhost:5101/health', false, $ctx);
$latencia = round((microtime(true) - $inicio) * 1000);

if ($response !== false) {
    echo "  ✓ BioBridge ACTIVO en localhost:5101\n";
    echo "  📊 Latencia: ${latencia}ms\n";
} else {
    echo "  ⚠️  BioBridge NO está disponible\n";
    echo "  Asegúrate de ejecutar: C:\\xampp\\htdocs\\Rtime\\tools\\biobridge\\BioBridge.exe\n";
}

// Verificar archivos
echo "\n📁 Verificando archivos...\n";

$files = [
    'datos/BiometricoHealthCheck.php',
    'proceso/biometrico_health_check_action.php',
    'proceso/biometrico_health_check_cron.php',
    'vistas/biometrico_salud.php'
];

foreach ($files as $f) {
    $path = $root . '/' . $f;
    if (file_exists($path)) {
        echo "  ✓ $f\n";
    } else {
        echo "  ❌ $f (NO ENCONTRADO)\n";
    }
}

// Archivo de log
echo "\n📄 Configurando logs...\n";

$logFile = $logDir . '/biometrico_health_check.log';
if (!file_exists($logFile)) {
    touch($logFile);
}

if (is_writable($logFile)) {
    echo "  ✓ LOG OK: $logFile\n";
} else {
    echo "  ⚠️  LOG sin permisos de escritura\n";
}

// Instrucciones finales
echo "\n╔═══════════════════════════════════════════════════════╗\n";
echo "║           ✅ SETUP COMPLETADO                         ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

echo "📌 PRÓXIMOS PASOS:\n\n";

echo "1️⃣  Configurar CRON (cada 5 minutos):\n";

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    echo "   Windows - Abre Programador de tareas:\n";
    echo "   • Nueva tarea básica\n";
    echo "   • Nombre: RTime-BiometricoHealthCheck\n";
    echo "   • Trigger: Repetir cada 5 minutos\n";
    echo "   • Acción: Ejecutar program\n";
    echo "   • Program: C:\\xampp\\php\\php.exe\n";
    echo "   • Argumento: C:\\xampp\\htdocs\\Rtime\\proceso\\biometrico_health_check_cron.php\n";
} else {
    echo "   Linux/Mac - crontab -e\n";
    echo "   */5 * * * * /usr/bin/php {$root}/proceso/biometrico_health_check_cron.php\n";
}

echo "\n2️⃣  Acceder al dashboard:\n";
echo "   http://localhost/Rtime/vistas/biometrico_salud.php\n";

echo "\n3️⃣  Ver logs:\n";
echo "   tail -f {$logFile}\n";

echo "\n4️⃣  Prueba manual:\n";
echo "   php {$root}/proceso/biometrico_health_check_cron.php\n";

echo "\n💡 NOTA IMPORTANTE:\n";
echo "   El sistema verifica que BioBridge esté activo CADA 5 MINUTOS.\n";
echo "   Si BioBridge se cae, suspende verificación de dispositivos.\n";
echo "   Cuando BioBridge se recupera, reanuda automáticamente.\n";

echo "\n✨ ¡Listo para usar!\n\n";
?>
