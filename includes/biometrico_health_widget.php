<?php
/**
 * Widget de Health Check para biométricos
 * Incluir en cualquier vista: <?php include('../includes/biometrico_health_widget.php'); ?>
 */

require_once __DIR__ . '/../datos/db.php';
require_once __DIR__ . '/../datos/BiometricoHealthCheck.php';

try {
    $checker = new BiometricoHealthCheck($con2);
    $puenteActivo = $checker->verificarBridgeActivo();
    $estadoPuente = $checker->obtenerEstadoBridge();
    $estados = $checker->obtenerEstadoTodos();
    
    $conectados = count(array_filter($estados, fn($e) => $e['estado'] === 'conectado'));
    $desconectados = count(array_filter($estados, fn($e) => $e['estado'] === 'desconectado'));
    $total = count($estados);
    
} catch (Exception $e) {
    $puenteActivo = false;
    $estadoPuente = ['latencia' => null, 'mensaje' => 'Error'];
    $conectados = $desconectados = $total = 0;
}
?>

<style>
    .biometrico-health-widget {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 20px;
        color: white;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        font-family: 'Segoe UI', sans-serif;
        min-width: 280px;
    }
    
    .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .widget-title {
        font-size: 14px;
        font-weight: bold;
        margin: 0;
    }
    
    .widget-link {
        color: white;
        text-decoration: none;
        font-size: 12px;
        opacity: 0.8;
    }
    
    .widget-link:hover {
        opacity: 1;
    }
    
    .bridge-indicator {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 6px;
        margin-bottom: 12px;
        font-size: 13px;
    }
    
    .bridge-indicator.active {
        background: rgba(76, 175, 80, 0.3);
    }
    
    .bridge-indicator.inactive {
        background: rgba(244, 67, 54, 0.3);
    }
    
    .indicator-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    
    .indicator-dot.active {
        background: #4caf50;
    }
    
    .indicator-dot.inactive {
        background: #f44336;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    
    .widget-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        font-size: 12px;
    }
    
    .stat-item {
        background: rgba(255, 255, 255, 0.1);
        padding: 10px;
        border-radius: 6px;
        text-align: center;
    }
    
    .stat-value {
        font-size: 20px;
        font-weight: bold;
        display: block;
        margin-bottom: 4px;
    }
    
    .stat-label {
        opacity: 0.8;
        font-size: 11px;
    }
    
    .widget-footer {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        font-size: 11px;
        opacity: 0.8;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .widget-action {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
        transition: all 0.3s;
    }
    
    .widget-action:hover {
        background: rgba(255, 255, 255, 0.3);
    }
</style>

<div class="biometrico-health-widget">
    <div class="widget-header">
        <h3 class="widget-title">🔌 Biométricos</h3>
        <a href="/Rtime/vistas/biometrico_salud.php" class="widget-link">Ver dashboard →</a>
    </div>
    
    <!-- Indicador del BioBridge -->
    <div class="bridge-indicator <?= $puenteActivo ? 'active' : 'inactive' ?>">
        <span class="indicator-dot <?= $puenteActivo ? 'active' : 'inactive' ?>"></span>
        <span>
            BioBridge: <?= $puenteActivo ? '✓ Activo' : '✗ Inactivo' ?>
            <?= $estadoPuente['latencia'] ? ' (' . $estadoPuente['latencia'] . 'ms)' : '' ?>
        </span>
    </div>
    
    <!-- Estadísticas de dispositivos -->
    <div class="widget-stats">
        <div class="stat-item">
            <span class="stat-value" style="color: #4caf50;">
                <?= $conectados ?>
            </span>
            <span class="stat-label">Conectados</span>
        </div>
        <div class="stat-item">
            <span class="stat-value" style="color: #f44336;">
                <?= $desconectados ?>
            </span>
            <span class="stat-label">Desconectados</span>
        </div>
    </div>
    
    <div class="widget-footer">
        <span>
            <strong><?= $total ?></strong> dispositivos
        </span>
        <button class="widget-action" onclick="verificarPuenteWidget()">
            Verificar ahora
        </button>
    </div>
</div>

<script>
    function verificarPuenteWidget() {
        fetch('/Rtime/proceso/biometrico_health_check_action.php?action=verificar_puente')
            .then(r => r.json())
            .then(data => {
                // Recargar widget
                location.reload();
            })
            .catch(err => alert('Error: ' + err));
    }
    
    // Auto-actualizar cada 2 minutos
    setInterval(() => {
        location.reload();
    }, 120000);
</script>
