<?php
/**
 * Verificador de salud de dispositivos biométricos Características:
 * - Verifica conexión del BioBridge cada 5 minutos
 * - Solo verifica dispositivos si el puente está activo
 * - Registra latencia y cambios de estado
 * - Mantiene historial completo de conexiones
 */

require_once __DIR__ . '/db.php';

class BiometricoHealthCheck {
    private $con2;
    private $bridgeUrl = '';
    private $bridgeHost = 'localhost';
    private $bridgePort = 5101;
    private $bridgeProtocol = 'http';
    private $timeout = 5; // segundos
    private $alertThreshold = 1000; // ms
    private $bridgeStatus = null;
    
    public function __construct($dbConnection) {
        $this->con2 = $dbConnection;
        
        // Cargar configuración del BioBridge
        $config_file = dirname(__FILE__) . '/biobridge_config.php';
        $bridge_host = 'localhost';
        $bridge_port = 5101;
        $bridge_protocol = 'http';
        
        if (file_exists($config_file)) {
            include $config_file;
            if (isset($BIOBRIDGE_CONFIG)) {
                $bridge_host = $BIOBRIDGE_CONFIG['host'] ?? 'localhost';
                $bridge_port = $BIOBRIDGE_CONFIG['port'] ?? 5101;
                $bridge_protocol = $BIOBRIDGE_CONFIG['protocol'] ?? 'http';
            }
        }
        
        // Construir URL desde configuración
        $this->bridgeHost = $bridge_host;
        $this->bridgePort = (int)$bridge_port;
        $this->bridgeProtocol = $bridge_protocol;
        $this->bridgeUrl = $bridge_protocol . '://' . $bridge_host . ':' . $bridge_port . '/';
    }

    private function asegurarTablaBiometrico() {
        $sql = "CREATE TABLE IF NOT EXISTS `biometrico` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre` VARCHAR(120) NOT NULL,
            `ip` VARCHAR(100) DEFAULT 'localhost',
            `puerto` INT DEFAULT 5101,
            `ubicacion` VARCHAR(150) DEFAULT NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_activo` (`activo`),
            KEY `idx_ip_puerto` (`ip`, `puerto`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        @$this->con2->query($sql);
    }

    private function asegurarDispositivoBase() {
        try {
            $countResult = @$this->con2->query("SELECT COUNT(*) AS total FROM biometrico WHERE activo = 1");
            $total = 0;
            if ($countResult) {
                $row = $countResult->fetch_assoc();
                $total = (int)($row['total'] ?? 0);
            }

            if ($total === 0) {
                $nombre = 'BioBridge Principal';
                $ip = $this->bridgeHost;
                $puerto = $this->bridgePort;
                $ubicacion = 'Auto-registrado';

                $stmt = @$this->con2->prepare(
                    "INSERT INTO biometrico (nombre, ip, puerto, ubicacion, activo, fecha_registro)
                     VALUES (?, ?, ?, ?, 1, NOW())"
                );
                if ($stmt) {
                    @$stmt->bind_param('ssis', $nombre, $ip, $puerto, $ubicacion);
                    @$stmt->execute();
                    @$stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log('Error asegurando dispositivo base: ' . $e->getMessage());
        }
    }

    public function obtenerBridgeUrl() {
        return $this->bridgeUrl;
    }
    
    /**
     * Verificar si el puente BioBridge está activo
     */
    public function verificarBridgeActivo() {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'header' => "Connection: close\r\n",
                'ignore_errors' => true
            ],
            'ssl' => ['verify_peer' => false]
        ]);

        $rutas = ['health', 'status', 'ping', ''];

        foreach ($rutas as $ruta) {
            $inicio = microtime(true);
            try {
                $url = $this->bridgeUrl . $ruta;
                $response = @file_get_contents($url, false, $ctx);
                $latencia = round((microtime(true) - $inicio) * 1000);

                if ($response !== false) {
                    $this->bridgeStatus = [
                        'activo' => true,
                        'latencia' => $latencia,
                        'mensaje' => 'Puente alcanzable en ' . $url
                    ];
                    return true;
                }

                if (!empty($http_response_header) && is_array($http_response_header)) {
                    $this->bridgeStatus = [
                        'activo' => true,
                        'latencia' => $latencia,
                        'mensaje' => 'Puente responde (HTTP) en ' . $url
                    ];
                    return true;
                }
            } catch (Exception $e) {
            }
        }

        $this->bridgeStatus = [
            'activo' => false,
            'latencia' => null,
            'mensaje' => 'Puente no disponible en ' . $this->bridgeUrl . ' (timeout o desconectado)'
        ];
        return false;
    }
    
    /**
     * Obtener estado del puente
     */
    public function obtenerEstadoBridge() {
        return $this->bridgeStatus;
    }
    
    /**
     * Verificar todos los dispositivos (solo si puente está activo)
     */
    public function verificarTodosBiometricos() {
        $this->asegurarTablaBiometrico();
        $this->asegurarDispositivoBase();

        // Primero chequear el puente
        $puenteActivo = $this->verificarBridgeActivo();
        
        if (!$puenteActivo) {
            return [
                'success' => false,
                'puente_activo' => false,
                'mensaje' => 'BioBridge no está disponible. Verificación cancelada.',
                'detalle' => $this->bridgeStatus['mensaje']
            ];
        }
        
        try {
            $query = "
                SELECT 
                    b.id,
                    b.nombre,
                    b.ip,
                    b.puerto,
                    b.ubicacion
                FROM biometrico b
                WHERE b.activo = 1
                ORDER BY b.id
            ";
            
            $result = $this->con2->query($query);
            
            if (!$result) {
                return [
                    'success' => false,
                    'puente_activo' => true,
                    'mensaje' => 'Error al obtener biométricos: ' . $this->con2->error
                ];
            }

            if ($result->num_rows === 0) {
                return [
                    'success' => false,
                    'puente_activo' => true,
                    'mensaje' => 'No hay dispositivos biométricos activos en la tabla biometrico'
                ];
            }
            
            $reportes = [];
            while ($row = $result->fetch_assoc()) {
                $check = $this->verificarDispositivo(
                    $row['id'],
                    $row['nombre'],
                    $row['puerto'] ?? $this->bridgePort,
                    $row['ip'] ?? $this->bridgeHost
                );
                $reportes[] = $check;
            }
            
            return [
                'success' => true,
                'puente_activo' => true,
                'puente_latencia' => $this->bridgeStatus['latencia'] ?? null,
                'total' => count($reportes),
                'reportes' => $reportes,
                'fecha_ejecucion' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'puente_activo' => true,
                'mensaje' => 'Excepción: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar un dispositivo específico
     */
    public function verificarDispositivo($biometrico_id, $nombre, $puerto = null, $ip = null) {
        if (!$puerto) {
            $puerto = $this->bridgePort;
        }

        $host = $ip ?: $this->bridgeHost;
        $host = preg_replace('#^https?://#', '', trim((string)$host));
        $url = $this->bridgeProtocol . '://' . $host . ':' . (int)$puerto . '/health';
        
        $inicio = microtime(true);
        $estado = 'desconectado';
        $latencia = null;
        $observacion = null;
        
        try {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $this->timeout,
                    'header' => "Connection: close\r\n",
                ],
                'ssl' => ['verify_peer' => false]
            ]);
            
            $response = @file_get_contents($url, false, $ctx);
            $latencia = round((microtime(true) - $inicio) * 1000);
            
            if ($response !== false) {
                $data = @json_decode($response, true);
                
                if (is_array($data) && isset($data['status'])) {
                    if ($data['status'] === 'ok' || $data['status'] === 'healthy') {
                        $estado = 'conectado';
                    } else {
                        $estado = 'error';
                        $observacion = $data['mensaje'] ?? 'Estado: ' . $data['status'];
                    }
                } else {
                    $estado = 'conectado';
                    $observacion = 'Respondió correctamente';
                }
                
                if ($latencia > $this->alertThreshold) {
                    $estado = 'lento';
                    $observacion = "Latencia alta: ${latencia}ms";
                }
            } else {
                $estado = 'desconectado';
                $observacion = "Timeout en {$url}";
            }
            
        } catch (Exception $e) {
            $estado = 'error';
            $latencia = round((microtime(true) - $inicio) * 1000);
            $observacion = $e->getMessage();
        }
        
        $this->registrarCheck($biometrico_id, $estado, $latencia, $observacion);
        
        return [
            'biometrico_id' => $biometrico_id,
            'nombre' => $nombre,
            'ip' => $host,
            'puerto' => $puerto,
            'estado' => $estado,
            'latencia_ms' => $latencia,
            'observacion' => $observacion
        ];
    }
    
    /**
     * Registrar resultado en BD
     */
    private function registrarCheck($biometrico_id, $estado, $latencia, $observacion) {
        try {
            $checkAnterior = $this->con2->query(
                "SELECT estado FROM biometrico_conexion WHERE biometrico_id = " . (int)$biometrico_id
            )->fetch_assoc();
            
            $estado_anterior = $checkAnterior['estado'] ?? null;
            
            $sql = "INSERT INTO biometrico_conexion 
                    (biometrico_id, estado, latencia_ms, observacion, fecha_check)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        estado = VALUES(estado),
                        latencia_ms = VALUES(latencia_ms),
                        observacion = VALUES(observacion),
                        fecha_check = NOW(),
                        intentos_fallidos = IF(VALUES(estado) = 'conectado', 0, intentos_fallidos + 1),
                        ultima_conexion_exitosa = IF(VALUES(estado) = 'conectado', NOW(), ultima_conexion_exitosa)";
            
            $stmt = $this->con2->prepare($sql);
            $stmt->bind_param('isis', $biometrico_id, $estado, $latencia, $observacion);
            $stmt->execute();
            
            if ($estado !== $estado_anterior) {
                $this->registrarCambio($biometrico_id, $estado_anterior, $estado, $latencia, $observacion);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error registrando check: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar cambio en historial
     */
    private function registrarCambio($biometrico_id, $estado_anterior, $estado_nuevo, $latencia, $observacion) {
        try {
            $stmt = $this->con2->prepare(
                "INSERT INTO biometrico_conexion_historial
                 (biometrico_id, estado_anterior, estado_nuevo, latencia_ms, observacion, fecha_cambio)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            
            $stmt->bind_param('issis', $biometrico_id, $estado_anterior, $estado_nuevo, $latencia, $observacion);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error registrando cambio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener estado actual
     */
    public function obtenerEstado($biometrico_id) {
        $stmt = $this->con2->prepare(
            "SELECT bc.*, b.nombre, b.puerto, b.ubicacion
             FROM biometrico_conexion bc
             LEFT JOIN biometrico b ON b.id = bc.biometrico_id
             WHERE bc.biometrico_id = ?"
        );
        
        $stmt->bind_param('i', $biometrico_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Obtener estado de todos
     */
    public function obtenerEstadoTodos() {
        $this->asegurarTablaBiometrico();

        $result = $this->con2->query(
            "SELECT b.id, b.nombre, b.ubicacion, b.puerto, bc.estado, bc.latencia_ms,
                    bc.observacion, bc.fecha_check, bc.intentos_fallidos,
                    bc.ultima_conexion_exitosa,
                    TIMESTAMPDIFF(MINUTE, bc.fecha_check, NOW()) as minutos_desde_check
             FROM biometrico b
             LEFT JOIN biometrico_conexion bc ON b.id = bc.biometrico_id
             WHERE b.activo = 1
             ORDER BY b.id"
        );
        
        $estados = [];
        while ($row = $result->fetch_assoc()) {
            $estados[] = $row;
        }
        return $estados;
    }
    
    /**
     * Obtener historial
     */
    public function obtenerHistorial($biometrico_id, $limite = 50) {
        $stmt = $this->con2->prepare(
            "SELECT * FROM biometrico_conexion_historial
             WHERE biometrico_id = ?
             ORDER BY fecha_cambio DESC
             LIMIT ?"
        );
        
        $stmt->bind_param('ii', $biometrico_id, $limite);
        $stmt->execute();
        
        $historial = [];
        $resultado = $stmt->get_result();
        while ($row = $resultado->fetch_assoc()) {
            $historial[] = $row;
        }
        return $historial;
    }
}

// Si se ejecuta desde CLI
if (php_sapi_name() === 'cli') {
    $checker = new BiometricoHealthCheck($con2);
    $resultado = $checker->verificarTodosBiometricos();
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
?>
