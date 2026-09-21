<?php
/**
 * Endpoint para Health Check de biométricos
 * Valida que el BioBridge esté activo antes de proceder
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../datos/db.php';
require_once __DIR__ . '/../datos/BiometricoHealthCheck.php';
require_once __DIR__ . '/../datos/permisos.php';

session_start();
if (!isset($_SESSION['idUsuario'])) {
    http_response_code(401);
    die(json_encode(['ok' => false, 'mensaje' => 'No autorizado']));
}

try {
    $checker = new BiometricoHealthCheck($con2);
    $action = $_POST['action'] ?? $_GET['action'] ?? 'check';
    $biometrico_id = $_POST['biometrico_id'] ?? $_GET['biometrico_id'] ?? null;
    
    switch ($action) {
        
        case 'check':
            $resultado = $checker->verificarTodosBiometricos();
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'check_uno':
            if (!$biometrico_id) {
                http_response_code(400);
                die(json_encode(['ok' => false, 'mensaje' => 'biometrico_id requerido']));
            }
            
            $stmt = $con2->prepare("SELECT id, nombre, puerto FROM biometrico WHERE id = ?");
            $stmt->bind_param('i', $biometrico_id);
            $stmt->execute();
            $bio = $stmt->get_result()->fetch_assoc();
            
            if (!$bio) {
                http_response_code(404);
                die(json_encode(['ok' => false, 'mensaje' => 'Biométrico no encontrado']));
            }
            
            $resultado = $checker->verificarDispositivo(
                $bio['id'],
                $bio['nombre'],
                $bio['puerto'] ?? 5101
            );
            
            echo json_encode(['ok' => true, 'datos' => $resultado]);
            break;
            
        case 'obtener_estado':
            $estados = $checker->obtenerEstadoTodos();
            echo json_encode([
                'ok' => true,
                'cantidad' => count($estados),
                'datos' => $estados
            ]);
            break;
            
        case 'obtener_estado_uno':
            if (!$biometrico_id) {
                http_response_code(400);
                die(json_encode(['ok' => false, 'mensaje' => 'biometrico_id requerido']));
            }
            
            $estado = $checker->obtenerEstado($biometrico_id);
            if (!$estado) {
                http_response_code(404);
                die(json_encode(['ok' => false, 'mensaje' => 'Biométrico no encontrado']));
            }
            
            echo json_encode(['ok' => true, 'datos' => $estado]);
            break;
            
        case 'obtener_historial':
            if (!$biometrico_id) {
                http_response_code(400);
                die(json_encode(['ok' => false, 'mensaje' => 'biometrico_id requerido']));
            }
            
            $limite = $_GET['limite'] ?? $_POST['limite'] ?? 50;
            $historial = $checker->obtenerHistorial($biometrico_id, (int)$limite);
            
            echo json_encode([
                'ok' => true,
                'biometrico_id' => $biometrico_id,
                'cantidad' => count($historial),
                'datos' => $historial
            ]);
            break;
            
        case 'verificar_puente':
            // Verificación rápida SOLO del puente
            $puenteActivo = $checker->verificarBridgeActivo();
            $estadoPuente = $checker->obtenerEstadoBridge();
            
            echo json_encode([
                'ok' => $puenteActivo,
                'puente_activo' => $puenteActivo,
                'latencia_ms' => $estadoPuente['latencia'] ?? null,
                'mensaje' => $estadoPuente['mensaje'] ?? ''
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Acción no válida',
                'disponibles' => [
                    'check', 'check_uno', 'obtener_estado', 
                    'obtener_estado_uno', 'obtener_historial', 'verificar_puente'
                ]
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>
