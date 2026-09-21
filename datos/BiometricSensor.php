<?php
/**
 * Módulo de integración con SDK de huellero biométrico
 * 
 * Este archivo contiene la lógica para:
 * - Capturar huellas desde el lector
 * - Extraer template biométrico
 * - Comparar templates (MATCH) para verificación
 * - Guardar templates en base de datos
 * 
 * El SDK debe estar instalado en el servidor Windows con XAMPP
 */

class BiometricSensorManager {
    
    /**
     * Métodos para capturar huella del lector
     * 
     * Requiere:
     * - ZKTECO SDK instalado
     * - Lector conectado por USB
     * - Extensión COM de PHP habilitada
     */
    
    public static function capturarHuella() {
        // Primero intentar el puente HTTP local (servicio 32-bit) en http://localhost:5101/capture
        try {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'timeout' => 15,
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query(['bridge' => '1']),
                ]
            ]);

            $bridgeUrl = 'http://localhost:5101/capture';
            $resp = @file_get_contents($bridgeUrl, false, $ctx);
            if ($resp !== false) {
                $data = json_decode($resp, true);
                if (is_array($data) && isset($data['success']) && $data['success'] === true) {
                    return [
                        'success' => true,
                        'template' => $data['template'] ?? '',
                        'imagenBuffer' => $data['imagen'] ?? ''
                    ];
                } elseif (is_array($data) && isset($data['success']) && $data['success'] === false) {
                    // El puente respondió pero con error; devolver ese mensaje para debugging
                    return [
                        'success' => false,
                        'mensaje' => 'Bridge: ' . ($data['mensaje'] ?? 'error')
                    ];
                }
            }
        } catch (Exception $e) {
            // continuar al fallback COM
        }

        // Fallback: intentar COM directo (si PHP está en 32-bit y OCX/SDK registrado)
        try {
            if (!class_exists('COM')) {
                return [
                    'success' => false,
                    'mensaje' => 'COM no está disponible en PHP. Habilita la extensión COM en php.ini.'
                ];
            }

            // Control ActiveX/COM sugerido por ZK: ZKFP2.ZKFP2Ctrl.1
            $zkfp = @new COM("ZKFP2.ZKFP2Ctrl.1");
            if (!$zkfp) {
                // Intentar nombre alternativo usado en algunos kits
                $zkfp = @new COM("ZKFPCtl.ZKFPCtlCtrl.1");
            }

            if (!$zkfp) {
                return [
                    'success' => false,
                    'mensaje' => 'No se pudo inicializar el control del lector (COM). Verifique SDK/registrado OCX.'
                ];
            }

            // Si el SDK provee Init
            if (method_exists($zkfp, 'Init')) {
                $init = @$zkfp->Init();
            }

            // Abrir primer dispositivo (índice 0)
            $deviceHandle = null;
            if (method_exists($zkfp, 'OpenDevice')) {
                $deviceHandle = @$zkfp->OpenDevice(0);
            }

            // Preparar buffers
            $imageBuffer = null;
            $template = null;

            // Muchas implementaciones usan AcquireFingerprint / AcquireFingerprintImage
            if ($deviceHandle && method_exists($zkfp, 'AcquireFingerprint')) {
                $ret = @$zkfp->AcquireFingerprint($deviceHandle, $imageBuffer, $template);
            } elseif (method_exists($zkfp, 'AcquireFingerprintImage')) {
                $ret = @$zkfp->AcquireFingerprintImage($deviceHandle, $imageBuffer);
                // extraer template si existe función
                if (method_exists($zkfp, 'ExtractFromImage')) {
                    $template = null;
                    @$zkfp->ExtractFromImage($imageBuffer, $template);
                }
            } else {
                return [
                    'success' => false,
                    'mensaje' => 'Métodos de captura no disponibles en el control COM.'
                ];
            }

            // Validar resultado
            if (isset($ret) && ($ret === 0 || $ret === true)) {
                // transformar buffers a base64 si vienen como binarios
                $imgB64 = '';
                $tplB64 = '';

                if (!empty($imageBuffer)) {
                    if (is_string($imageBuffer)) {
                        $imgB64 = base64_encode($imageBuffer);
                    } else {
                        $imgB64 = base64_encode((string)$imageBuffer);
                    }
                }

                if (!empty($template)) {
                    if (is_string($template)) {
                        $tplB64 = base64_encode($template);
                    } else {
                        $tplB64 = base64_encode((string)$template);
                    }
                }

                return [
                    'success' => true,
                    'template' => $tplB64,
                    'imagenBuffer' => $imgB64
                ];
            }

            return [
                'success' => false,
                'mensaje' => 'El lector no devolvió una huella válida.'
            ];
        } catch (Exception $ex) {
            return [
                'success' => false,
                'mensaje' => 'Excepción al acceder al lector: ' . $ex->getMessage()
            ];
        }
    }
    
    /**
     * Comparar dos templates biométricos
     * 
     * @param string $template1 - Template almacenado en BD (base64)
     * @param string $template2 - Template capturado en vivo (base64)
     * @return array - Score de coincidencia y resultado
     */
    public static function compararTemplates($template1, $template2) {
        if (empty($template1) || empty($template2)) {
            return [
                'score' => 0,
                'coincidencia' => false,
                'mensaje' => 'Plantillas incompletas para comparación'
            ];
        }

        try {
            $bridgeUrl = 'http://localhost:5101/compare';
            $payload = json_encode([
                'template1' => $template1,
                'template2' => $template2,
            ]);

            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'timeout' => 15,
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                ]
            ]);

            $resp = @file_get_contents($bridgeUrl, false, $ctx);
            if ($resp === false) {
                $error = error_get_last();
                $hdrs = isset($http_response_header) ? implode(" | ", $http_response_header) : '';
                return [
                    'score' => 0,
                    'coincidencia' => false,
                    'mensaje' => 'No se pudo conectar al puente local: ' . ($error['message'] ?? 'sin detalles') . ' | Headers: ' . $hdrs
                ];
            }

            $data = json_decode($resp, true);
            if (!is_array($data)) {
                $hdrs = isset($http_response_header) ? implode(" | ", $http_response_header) : '';
                return [
                    'score' => 0,
                    'coincidencia' => false,
                    'mensaje' => 'Respuesta inválida del puente local. Headers: ' . $hdrs . ' Body: ' . substr($resp, 0, 400)
                ];
            }

            if (array_key_exists('success', $data)) {
                $score = isset($data['score']) ? floatval($data['score']) : 0;
                $matched = ($data['success'] === true && $score > 0);
                return [
                    'score' => $score,
                    'coincidencia' => $matched,
                    'mensaje' => $data['mensaje'] ?? '',
                ];
            }

            return [
                'score' => 0,
                'coincidencia' => false,
                'mensaje' => 'Respuesta no esperada del puente local'
            ];
        } catch (Exception $e) {
            return [
                'score' => 0,
                'coincidencia' => false,
                'mensaje' => 'Excepción al comparar con el SDK local: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar asistencia por huella
     * 
     * @param int $per_iId - ID del trabajador
     * @param string $templateCapturado - Template del dedo en vivo (base64)
     * @param string $dedo - Código del dedo (ID1-DD5)
     * @return array - Resultado de autenticación
     */
    public static function verificarAsistencia($per_iId, $templateCapturado, $dedo = null) {
        global $conn, $con2;

        if (empty($conn) || !($conn instanceof mysqli)) {
            if (!empty($con2) && $con2 instanceof mysqli) {
                $conn = $con2;
            } else {
                $conexionPath = __DIR__ . '/conexion.php';
                if (file_exists($conexionPath)) {
                    require_once $conexionPath;
                }
            }
        }

        if (empty($conn) || !($conn instanceof mysqli)) {
            return [
                'success' => false,
                'mensaje' => 'No hay conexión a la base de datos disponible'
            ];
        }

        if ($dedo) {
            $sql = "SELECT per_iId, template_binario, dedo FROM huella_biometrica 
                   WHERE per_iId = ? AND dedo = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $per_iId, $dedo);
        } elseif ($per_iId !== null && $per_iId !== '') {
            $sql = "SELECT per_iId, template_binario, dedo FROM huella_biometrica 
                   WHERE per_iId = ? ORDER BY fecha_captura DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $per_iId);
        } else {
            $sql = "SELECT per_iId, template_binario, dedo FROM huella_biometrica 
                   ORDER BY fecha_captura DESC";
            $stmt = $conn->prepare($sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'mensaje' => 'No hay huellas registradas en el sistema'
            ];
        }
        
        // Comparar con cada template
        $mejorScore = 0;
        $mejorDedo = null;
        $mejorPersona = null;
        $mejorMensaje = '';
        $comparaciones = 0;
        
        while ($row = $result->fetch_assoc()) {
            $comparaciones++;
            $templateAlmacenado = $row['template_binario'];
            $comparacion = self::compararTemplates($templateAlmacenado, $templateCapturado);
            
            if (isset($comparacion['score']) && $comparacion['score'] >= $mejorScore) {
                $mejorScore = $comparacion['score'];
                $mejorDedo = $row['dedo'] ?? $dedo;
                $mejorPersona = $row['per_iId'];
                $mejorMensaje = $comparacion['mensaje'] ?? '';
            }
        }
        
        $stmt->close();
        
        if ($mejorScore > 0 && $mejorPersona !== null) {
            $nombre = '';
            $stmt2 = $conn->prepare("SELECT per_vcNombres FROM trabajador WHERE per_iId = ? LIMIT 1");
            if ($stmt2) {
                $stmt2->bind_param('i', $mejorPersona);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($res2 && $row2 = $res2->fetch_assoc()) {
                    $nombre = $row2['per_vcNombres'];
                }
                $stmt2->close();
            }

            return [
                'success' => true,
                'per_iId' => $mejorPersona,
                'nombre' => $nombre,
                'score' => $mejorScore,
                'dedo' => $mejorDedo,
                'mensaje' => 'Huella verificada exitosamente',
                'comparaciones' => $comparaciones
            ];
        }

        $detalle = 'No coincide con los registros. Mejor score: ' . $mejorScore;
        if (!empty($mejorMensaje)) {
            $detalle .= '. Detalle: ' . $mejorMensaje;
        }
        $detalle .= '. Plantillas comparadas: ' . $comparaciones;

        return [
            'success' => false,
            'score' => $mejorScore,
            'comparaciones' => $comparaciones,
            'mensaje' => $detalle
        ];
    }
}

/**
 * ARQUITECTURA DE COMPARACIÓN BIOMÉTRICA
 * 
 * FLUJO DE CAPTURA:
 * 1. Usuario coloca dedo en lector
 * 2. SDK extrae imagen + datos biométricos
 * 3. SDK calcula TEMPLATE (huella característica)
 * 4. Template se almacena en BD (datos binarios)
 * 
 * FLUJO DE VERIFICACIÓN:
 * 1. Usuario coloca dedo en lector (asistencia)
 * 2. SDK extrae template nuevamente
 * 3. SDK compara con templates guardados en BD
 * 4. SDK retorna SCORE de similitud
 * 5. Si score > umbral = VERIFICADO
 * 
 * DIFERENCIA CON DATOS TEXTUALES:
 * - Datos textuales: comparación exacta (Admin123 = Admin123)
 * - Huellas biométricas: comparación probabilística (98% similar = ACEPTADO)
 * - Mayor seguridad: imposible falsificar sin la huella física
 * - Mayor exactitud: variaciones naturales en captura se toleran
 */
?>
