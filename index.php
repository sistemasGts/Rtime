<?php
// Auto-inicialización del Health Check (silencioso)
require_once __DIR__ . '/datos/db.php';
require_once __DIR__ . '/datos/biometrico_auto_init.php';
require_once __DIR__ . '/datos/BiometricoHealthCheck.php';

$biobridge_activo = false;
$biobridge_mensaje = 'Estado no verificado';

try {
    if (isset($con2) && $con2) {
        $healthCheck = new BiometricoHealthCheck($con2);
        $biobridge_activo = $healthCheck->verificarBridgeActivo();
        $estadoBridge = $healthCheck->obtenerEstadoBridge();
        if (isset($estadoBridge['mensaje'])) {
            $biobridge_mensaje = $estadoBridge['mensaje'];
        }
    }
} catch (Exception $e) {
    $biobridge_mensaje = 'No se pudo verificar el puente';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Asistencia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .biometric-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            margin: 30px 0;
        }
        .fingerprint-reader {
            width: 220px;
            height: 220px;
            border: 3px solid #2563eb;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef6ff;
            font-size: 96px;
            cursor: pointer;
            transition: .25s ease;
        }
        .fingerprint-reader:hover {
            background: #dbeafe;
            transform: translateY(-2px);
        }
        .fingerprint-reader.capturing {
            background: #fef3c7;
            border-color: #f59e0b;
            animation: pulse 1s infinite;
        }
        .fingerprint-reader.success {
            background: #ecfdf5;
            border-color: #16a34a;
        }
        .fingerprint-reader.error {
            background: #fef2f2;
            border-color: #dc2626;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, .5); }
            50% { box-shadow: 0 0 0 12px rgba(245, 158, 11, 0); }
        }
        .status-text {
            font-size: 1rem;
            font-weight: 600;
            color: #334155;
            min-height: 28px;
            text-align: center;
        }
        .status-text.capturing { color: #d97706; }
        .status-text.success { color: #15803d; }
        .status-text.error { color: #b91c1c; }
        .result-box {
            margin-top: 20px;
            padding: 18px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            display: none;
        }
        .result-box.success { border-color: #86efac; background: #ecfdf5; }
        .result-box.error { border-color: #fecaca; background: #fef2f2; }
    </style>
</head>
<body>
<div class="container">

<!-- Sonidos personalizados: coloque archivos en assets/sounds/success.mp3 y assets/sounds/error.mp3 -->
<audio id="sound-success" src="tools/sounds/success.mp3" preload="auto"></audio>
<!-- <audio id="sound-error" src="tools/sounds/error.mp3" preload="auto"></audio> -->

<!-- ========== STATUS HEALTH CHECK BIOMÉTRICO ========== -->
<?php if (!$biobridge_activo): ?>
<div style="background: #fff7ed; border-bottom: 2px solid #f59e0b; padding: 12px 20px; font-size: 13px; color: #9a3412;">
    <strong>⚠️ Health Check sin conexión al puente</strong> | <?php echo htmlspecialchars($biobridge_mensaje); ?> |
    <a href="CONFIGURAR_BIOBRIDGE.php" style="color: #9a3412; font-weight: bold; text-decoration: underline;">Configurar host/puerto</a>
</div>
<?php elseif (isset($_SESSION['biometrico_init_status'])): ?>
<div style="background: #f8f9fa; border-bottom: 2px solid #dee2e6; padding: 8px 20px; font-size: 13px; color: #495057;">
    <?php 
        $status = $_SESSION['biometrico_init_status'];
        if ($status['status'] === 'exito'):
    ?>
    <span style="color: #16a34a;">✓ Sistema de Health Check</span>: Inicializado correctamente 
    <?php if (!empty($status['cron'])): ?>
    | <span style="color: #0284c7;">Cron:</span> <?php echo $status['cron']; ?>
    <?php endif; ?>
    
    <?php elseif ($status['status'] === 'ya_existe'): ?>
    <span style="color: #2563eb;">✓ Sistema de Health Check</span>: Activo y funcionando
    
    <?php elseif ($status['status'] === 'inicializando'): ?>
    <span style="color: #f59e0b;">⏳ Sistema de Health Check</span>: Inicializándose...
    
    <?php elseif ($status['status'] === 'error'): ?>
    <span style="color: #dc2626;">✗ Error Health Check</span>: <?php echo htmlspecialchars($status['error'] ?? 'Error desconocido'); ?>
    
    <?php endif; ?>
</div>
<?php else: ?>
<div style="background: #dcfce7; border-bottom: 2px solid #16a34a; padding: 8px 20px; font-size: 13px; color: #166534;">
    ✓ BioBridge detectado correctamente. Ver status en <a href="STATUS_BIOMETRICO.php" style="color: #166534; font-weight: bold; text-decoration: underline;">Panel de Control</a>
</div>
<?php endif; ?>
<!-- ========== FIN STATUS HEALTH CHECK ========== -->

    <div class="card-box">
        <h1>Registro de Asistencia</h1>
        <p>Coloca tu dedo en el lector para que el SDK compare la plantilla y registre la asistencia en la tabla <strong>asistencias</strong>.</p>

        <div class="biometric-container">
            <div class="fingerprint-reader" id="reader">🫆</div>
            <div class="status-text" id="status">Esperando huella... (captura automática activada)</div>
            <div id="offlineSyncInfo" style="font-size: 12px; color: #475569; background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 10px; border-radius: 8px;">
                Estado de red: verificando...
            </div>
        </div>

        <div id="resultado" class="result-box" aria-live="polite">
            <div id="resultadoMensaje"></div>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <h3>Cómo funciona</h3>
            <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                <li>El lector captura la huella física.</li>
                <li>El SDK genera una plantilla biométrica de la huella.</li>
                <li>La plantilla se compara con los registros guardados en la BD.</li>
                <li>Si coincide, se registra la asistencia en <code>asistencias</code>.</li>
                <li>Si no coincide, la marcación se rechaza.</li>
            </ul>
        </div>

        <div style="margin-top: 24px; text-align: center;">
            <a class="btn btn-secondary" href="vistas/login.php">Ir al panel administrativo</a>
        </div>

    </div>
</div>

<script>
let autoCaptureEnabled = true;
let isCapturing = false;

function setOfflineInfo(text, color = '#475569', bg = '#f8fafc', border = '#cbd5e1') {
    const el = document.getElementById('offlineSyncInfo');
    if (!el) return;
    el.textContent = text;
    el.style.color = color;
    el.style.background = bg;
    el.style.borderColor = border;
}

async function obtenerPendientesOffline() {
    try {
        const response = await fetch('proceso/capturar_huella.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'estado_offline' })
        });
        const data = await response.json();
        return {
            pendientes: data.pendientes || 0,
            storageMode: data.storage_mode || 'json'
        };
    } catch (_) {
        return { pendientes: 0, storageMode: 'json' };
    }
}

async function sincronizarOffline() {
    if (!navigator.onLine) return;
    try {
        const response = await fetch('proceso/capturar_huella.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'sync_offline' })
        });
        const data = await response.json();
        if (data.success) {
            const msg = 'Internet activo | Pendientes: ' + (data.pendientes || 0) +
                ' | Sincronizadas: ' + (data.sincronizadas || 0);
            setOfflineInfo(msg, '#166534', '#dcfce7', '#86efac');
        }
    } catch (_) {
    }
}

async function refrescarEstadoRedYCola() {
    const estado = await obtenerPendientesOffline();
    const pendientes = estado.pendientes;
    const modo = estado.storageMode;
    if (navigator.onLine) {
        setOfflineInfo('Internet activo | Pendientes: ' + pendientes + ' | Storage: ' + modo, '#1e3a8a', '#dbeafe', '#93c5fd');
    } else {
        setOfflineInfo('Sin internet | Modo offline activo | Pendientes: ' + pendientes + ' | Storage: ' + modo, '#9a3412', '#ffedd5', '#fdba74');
    }
}

async function startAutoCapture() {
    autoCaptureEnabled = true;
    statusUpdate('Esperando huella... (captura automática activada)', '');
    while (autoCaptureEnabled) {
        if (!isCapturing) {
            await capturarHuellaLoop();
        } else {
            await new Promise(r => setTimeout(r, 300));
        }
    }
}

function stopAutoCapture() {
    autoCaptureEnabled = false;
    statusUpdate('Captura automática detenida', 'error');
}

function playSound(success) {
    // Primero intentamos reproducir archivos de audio en assets/sounds/
    try {
        const audioEl = document.getElementById(success ? 'sound-success' : 'sound-error');
        if (audioEl && audioEl.src) {
            const p = audioEl.play();
            if (p && typeof p.then === 'function') {
                p.catch(err => {
                    // si falla la reproducción por autoplay/permiso, caer al fallback WebAudio
                    console.warn('Reproducción de archivo falló, usando fallback:', err);
                    playToneFallback(success);
                });
                return;
            }
            return;
        }
    } catch (e) {
        console.warn('Error al reproducir audio desde elemento:', e);
    }
    // Fallback a tonos generados por Web Audio
    playToneFallback(success);
}

function playToneFallback(success) {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioCtx();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.connect(g); g.connect(ctx.destination);
        g.gain.value = 0.05;

        if (success) {
            o.type = 'sine';
            o.frequency.value = 880;
            o.start();
            setTimeout(() => { o.frequency.setValueAtTime(1320, ctx.currentTime); }, 120);
            setTimeout(() => { try { o.stop(); ctx.close(); } catch(_){} }, 260);
        } else {
            o.type = 'square';
            o.frequency.value = 220;
            g.gain.value = 0.08;
            o.start();
            setTimeout(() => { try { o.stop(); ctx.close(); } catch(_){} }, 300);
        }
    } catch (e) {
        console.warn('Audio no disponible:', e);
    }
}

async function capturarHuellaLoop() {
    isCapturing = true;
    const reader = document.getElementById('reader');
    const status = document.getElementById('status');
    const resultado = document.getElementById('resultado');
    const resultadoMsg = document.getElementById('resultadoMensaje');

    reader.classList.remove('success', 'error');
    reader.classList.add('capturing');
    status.classList.remove('success', 'error');
    status.classList.add('capturing');
    status.textContent = 'Capturando huella... espera por favor';
    resultado.style.display = 'none';

    try {
        const response = await fetch('proceso/capturar_huella.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'capturar' })
        });

        const data = await response.json();

        reader.classList.remove('capturing');

        if (data.success) {
            playSound(true);
            reader.classList.add('success');
            status.classList.remove('capturing');
            status.classList.add('success');
            status.textContent = data.offline ? '✓ Huella reconocida (modo offline)' : '✓ Huella reconocida';

            resultado.className = 'result-box success';
            const nombre = data.nombre ? ' – ' + data.nombre : '';
            const score = data.score ? ' (' + data.score + ')' : '';
            if (data.offline) {
                const pendientes = (data.pendientes_sync ?? 0);
                resultadoMsg.textContent = 'Asistencia guardada offline' + nombre + score + ' | Pendientes: ' + pendientes;
            } else {
                resultadoMsg.textContent = 'Asistencia registrada' + nombre + score;
            }
            resultado.style.display = 'block';

            await refrescarEstadoRedYCola();
            if (navigator.onLine) {
                await sincronizarOffline();
            }

            await new Promise(r => setTimeout(r, 4000));
        } else {
            playSound(false);
            reader.classList.add('error');
            status.classList.remove('capturing');
            status.classList.add('error');
            status.textContent = '✗ ' + (data.mensaje || 'No se reconoció la huella');

            resultado.className = 'result-box error';
            resultadoMsg.textContent = data.mensaje || 'No se pudo registrar la asistencia.';
            resultado.style.display = 'block';

            await new Promise(r => setTimeout(r, 800));
        }
    } catch (error) {
        console.error(error);
        playSound(false);
        reader.classList.remove('capturing');
        reader.classList.add('error');
        status.classList.remove('capturing');
        status.classList.add('error');
        status.textContent = 'Error de conexión con el servidor';

        resultado.className = 'result-box error';
        resultadoMsg.textContent = 'Error de conexión con el servidor. Revisa el servicio o el lector.';
        resultado.style.display = 'block';

        await new Promise(r => setTimeout(r, 2000));
    }
    isCapturing = false;
}

function statusUpdate(text, styleClass) {
    const status = document.getElementById('status');
    status.textContent = text;
    status.classList.remove('capturing','success','error');
    if (styleClass) status.classList.add(styleClass);
}

window.addEventListener('DOMContentLoaded', function() {
    refrescarEstadoRedYCola();
    if (navigator.onLine) {
        sincronizarOffline();
    }

    setInterval(async () => {
        await refrescarEstadoRedYCola();
        if (navigator.onLine) {
            await sincronizarOffline();
        }
    }, 15000);

    window.addEventListener('online', async () => {
        await refrescarEstadoRedYCola();
        await sincronizarOffline();
    });

    window.addEventListener('offline', async () => {
        await refrescarEstadoRedYCola();
    });

    startAutoCapture();
});
</script>
</body>
</html>
