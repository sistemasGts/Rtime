<?php
/**
 * Verificar dispositivos registrados y forzar verificación
 * Accede a: http://localhost/Rtime/DIAGNOSTICO_DISPOSITIVOS.php
 */

require_once __DIR__ . '/datos/db.php';
require_once __DIR__ . '/datos/BiometricoHealthCheck.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnóstico de Dispositivos</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #333; 
            border-bottom: 3px solid #4CAF50; 
            padding-bottom: 15px;
        }
        h2 { 
            color: #555;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .section { 
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #2196F3;
            border-radius: 4px;
        }
        .ok { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #2196F3;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .button-group {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        button, a.button {
            padding: 12px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        button:hover, a.button:hover {
            background: #45a049;
        }
        .button-danger {
            background: #f44336;
        }
        .button-danger:hover {
            background: #da190b;
        }
        .button-info {
            background: #2196F3;
        }
        .button-info:hover {
            background: #0b7dda;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 14px;
        }
        .alert-error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
        }
        .alert-warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            color: #e65100;
        }
        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            color: #2e7d32;
        }
        .alert-info {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            color: #1565c0;
        }
        .code {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background: #c8e6c9;
            color: #2e7d32;
        }
        .status-inactive {
            background: #ffcdd2;
            color: #c62828;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 Diagnóstico de Dispositivos Biométricos</h1>";

// 1. Verificar si existe la tabla biometrico
echo "<h2>1️⃣ Verificar tabla de dispositivos</h2>";
$result = @$con2->query("SHOW TABLES LIKE 'biometrico'");
$tabla_existe = ($result && $result->num_rows > 0);

if ($tabla_existe) {
    echo "<div class='alert alert-success'>✓ La tabla <strong>biometrico</strong> existe</div>";
    
    // Contar dispositivos
    $query = "SELECT COUNT(*) as total FROM biometrico";
    $result = @$con2->query($query);
    $total_dispositivos = $result ? $result->fetch_assoc()['total'] : 0;
    
    if ($total_dispositivos > 0) {
        echo "<div class='alert alert-success'>✓ Hay <strong>$total_dispositivos</strong> dispositivo(s) registrado(s)</div>";
        
        // Mostrar tabla de dispositivos
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Puerto</th>
                <th>Ubicación</th>
                <th>Activo</th>
                <th>Fecha Registro</th>
            </tr>";
        
        $result = @$con2->query("SELECT * FROM biometrico ORDER BY id");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $activo_badge = $row['activo'] ? '<span class="status-badge status-active">Activo</span>' : '<span class="status-badge status-inactive">Inactivo</span>';
                echo "<tr>
                    <td>#{$row['id']}</td>
                    <td>" . htmlspecialchars($row['nombre'] ?? 'N/A') . "</td>
                    <td>" . (isset($row['puerto']) ? $row['puerto'] : '5101') . "</td>
                    <td>" . htmlspecialchars($row['ubicacion'] ?? 'N/A') . "</td>
                    <td>$activo_badge</td>
                    <td>" . (isset($row['fecha_registro']) ? $row['fecha_registro'] : 'N/A') . "</td>
                </tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<div class='alert alert-warning'>⚠️ <strong>No hay dispositivos registrados</strong> en la tabla biometrico</div>";
        echo "<p>Sin dispositivos, el cron no puede verificar nada. Necesitas registrar al menos un dispositivo.</p>";
    }
} else {
    echo "<div class='alert alert-error'>✗ La tabla <strong>biometrico</strong> NO EXISTE</div>";
    echo "<p>Esta tabla es necesaria para que el health check conozca qué dispositivos verificar.</p>";
}

// 2. Verificar estado del Health Check
echo "<h2>2️⃣ Estado actual del Health Check</h2>";

$res = @$con2->query("SELECT COUNT(*) as total FROM biometrico_conexion");
$registros_conexion = $res ? $res->fetch_assoc()['total'] : 0;

$res = @$con2->query("SELECT COUNT(*) as total FROM biometrico_conexion_historial");
$registros_historial = $res ? $res->fetch_assoc()['total'] : 0;

$res = @$con2->query("SELECT COUNT(*) as total FROM biometrico_puente_conexion");
$registros_puente = $res ? $res->fetch_assoc()['total'] : 0;

echo "<div class='section'>
    <strong>Registros en tablas:</strong><br>
    • biometrico_conexion: <span class='ok'>$registros_conexion</span><br>
    • biometrico_conexion_historial: <span class='ok'>$registros_historial</span><br>
    • biometrico_puente_conexion: <span class='ok'>$registros_puente</span>
</div>";

// 3. Verificar BioBridge
echo "<h2>3️⃣ Verificar BioBridge</h2>";
try {
    $health = new BiometricoHealthCheck($con2);
    $puente_activo = $health->verificarBridgeActivo();
    $estado_puente = $health->obtenerEstadoBridge();
    
    if ($puente_activo) {
        echo "<div class='alert alert-success'>✓ BioBridge está <strong>ACTIVO</strong> (Latencia: " . ($estado_puente['latencia'] ?? 'N/A') . "ms)</div>";
    } else {
        echo "<div class='alert alert-error'>✗ BioBridge está <strong>INACTIVO</strong><br>
        " . htmlspecialchars($estado_puente['mensaje'] ?? 'Error desconocido') . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='alert alert-error'>✗ Error al verificar BioBridge: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// 4. Forzar ejecución del cron
echo "<h2>4️⃣ Forzar verificación inmediata</h2>";

if (isset($_GET['forzar'])) {
    echo "<div class='alert alert-info'>⏳ Ejecutando verificación forzada...</div>";
    
    try {
        $health = new BiometricoHealthCheck($con2);
        
        if (!$health->verificarBridgeActivo()) {
            echo "<div class='alert alert-error'>✗ BioBridge no está activo, no se puede verificar dispositivos</div>";
        } else {
            $resultado = $health->verificarTodosBiometricos();
            
            if (isset($resultado['error'])) {
                echo "<div class='alert alert-error'>✗ " . htmlspecialchars($resultado['error']) . "</div>";
            } else if ($resultado['success'] ?? false) {
                echo "<div class='alert alert-success'>✓ Verificación completada exitosamente</div>";
                echo "<div class='section'>
                    <strong>Resultados:</strong><br>
                    • Dispositivos verificados: " . ($resultado['total'] ?? 0) . "<br>
                    • Latencia BioBridge: " . ($resultado['puente_latencia'] ?? 'N/A') . "ms
                </div>";
                
                if (!empty($resultado['reportes'])) {
                    echo "<table>
                        <tr>
                            <th>Dispositivo</th>
                            <th>Estado</th>
                            <th>Latencia</th>
                            <th>Observación</th>
                        </tr>";
                    
                    foreach ($resultado['reportes'] as $reporte) {
                        echo "<tr>
                            <td>" . htmlspecialchars($reporte['nombre'] ?? 'ID: ' . ($reporte['id'] ?? '?')) . "</td>
                            <td><strong>" . htmlspecialchars($reporte['estado'] ?? 'error') . "</strong></td>
                            <td>" . ($reporte['latencia_ms'] ?? 'N/A') . "ms</td>
                            <td>" . htmlspecialchars($reporte['observacion'] ?? '') . "</td>
                        </tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<div class='alert alert-warning'>⚠️ Verificación sin resultados</div>";
            }
        }
        
        // Recargar registro de registros
        $res = @$con2->query("SELECT COUNT(*) as total FROM biometrico_conexion_historial");
        $registros_after = $res ? $res->fetch_assoc()['total'] : 0;
        echo "<div class='alert alert-success'>✓ Total registros ahora: <strong>$registros_after</strong></div>";
        
    } catch (Exception $e) {
        echo "<div class='alert alert-error'>✗ Excepción: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

echo "<div class='button-group'>
    <a href='?forzar=1' class='button button-info'>⚙️ Ejecutar Verificación AHORA</a>
    <a href='/Rtime/FORZAR_EJECUCION_CRON_BIOMETRICO.php' class='button'>Ir a Forzar Cron</a>
</div>";

// 5. Información y recomendaciones
echo "<h2>5️⃣ Recomendaciones</h2>";

echo "<div class='section'>";
if ($total_dispositivos === 0) {
    echo "<span class='error'>⚠️ Problema encontrado:</span> No hay dispositivos registrados en la tabla <strong>biometrico</strong><br><br>";
    echo "<strong>Solución:</strong><br>";
    echo "Necesitas insertar al menos un dispositivo en la tabla biometrico. Ejecuta en tu base de datos:<br>";
    echo "<div class='code'>
INSERT INTO biometrico (nombre, puerto, ubicacion, activo) 
VALUES ('Huellero Principal', 5101, 'Oficina', 1);
    </div>";
    echo "Luego recarga esta página y vuelve a intentar la verificación.";
} elseif ($registros_historial === 0) {
    echo "<span class='warning'>⚠️ Nota:</span> Los dispositivos están registrados pero sin verificación aún<br><br>";
    echo "<strong>Acción:</strong> Haz clic en <strong>'Ejecutar Verificación AHORA'</strong> para registrar el primer evento.";
} else {
    echo "<span class='ok'>✓ Todo parece estar funcionando</span><br><br>";
    echo "El sistema está registrando eventos correctamente. Verifica:<br>";
    echo "• <a href='DEBUG_BIOMETRICO_REGISTROS.php'>Ver todos los registros</a><br>";
    echo "• <a href='STATUS_BIOMETRICO.php'>Panel de control</a>";
}
echo "</div>";

echo "</div>
</body>
</html>";
?>
