<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Biométrica</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .biometric-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            margin: 30px 0;
        }
        .fingerprint-reader {
            width: 200px;
            height: 200px;
            border: 3px solid #2563eb;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef6ff;
            font-size: 80px;
            cursor: pointer;
            transition: .3s;
        }
        .fingerprint-reader:hover {
            background: #dbeafe;
            transform: scale(1.05);
        }
        .fingerprint-reader.capturing {
            background: #fef3c7;
            border-color: #f59e0b;
            animation: pulse 1s infinite;
        }
        .fingerprint-reader.success {
            background: #f0fdf4;
            border-color: #16a34a;
        }
        .fingerprint-reader.error {
            background: #fde9e9;
            border-color: #dc2626;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, .7); }
            50% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
        }
        .status-text {
            text-align: center;
            font-weight: 600;
            color: #374151;
            min-height: 24px;
        }
        .status-text.capturing { color: #f59e0b; }
        .status-text.success { color: #16a34a; }
        .status-text.error { color: #dc2626; }
    </style>
</head>
<body>
<div class="container">
    <div class="card-box">
        <h1>Verificación Biométrica de Asistencia</h1>
        <p>Coloca tu dedo en el lector para registrar tu asistencia.</p>

        <div class="biometric-container">
            <div class="fingerprint-reader" id="reader" onclick="capturarHuella()">
                👆
            </div>
            <div class="status-text" id="status">
                Presiona o coloca tu dedo en el lector
            </div>
        </div>

        <div id="resultado" style="display: none; margin-top: 20px;">
            <div id="resultadoMensaje" class="info"></div>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <h3>¿Cómo funciona la verificación biométrica?</h3>
            <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                <li><strong>Captura:</strong> El lector extrae características únicas de tu huella</li>
                <li><strong>Template:</strong> Se genera una plantilla matemática (no la imagen)</li>
                <li><strong>Comparación:</strong> SDK compara el template con los almacenados</li>
                <li><strong>Resultado:</strong> Score de similitud indica si es una coincidencia</li>
                <li><strong>Ventaja:</strong> Mucho más seguro que contraseñas (no se puede falsificar sin tu dedo)</li>
            </ul>
        </div>
    </div>
</div>

<script>
async function capturarHuella() {
    const reader = document.getElementById('reader');
    const status = document.getElementById('status');
    const resultado = document.getElementById('resultado');
    const resultadoMsg = document.getElementById('resultadoMensaje');
    
    reader.classList.add('capturing');
    status.classList.add('capturing');
    status.textContent = 'Capturando huella... Espera';
    resultado.style.display = 'none';
    
    try {
        // Llamar al servidor para capturar huella
        const response = await fetch('../proceso/capturar_huella.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                accion: 'capturar'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            reader.classList.remove('capturing');
            reader.classList.add('success');
            status.classList.remove('capturing');
            status.classList.add('success');
            status.textContent = '✓ Huella capturada correctamente';
            
            resultadoMsg.className = 'success';
            resultadoMsg.textContent = '✓ Asistencia registrada: ' + data.mensaje;
            resultado.style.display = 'block';
            
            // Reiniciar después de 2 segundos
            setTimeout(() => {
                reader.classList.remove('success');
                status.classList.remove('success');
                status.textContent = 'Presiona o coloca tu dedo en el lector';
                resultado.style.display = 'none';
            }, 2000);
        } else {
            reader.classList.remove('capturing');
            reader.classList.add('error');
            status.classList.remove('capturing');
            status.classList.add('error');
            status.textContent = '✗ Error: ' + data.mensaje;
            
            resultadoMsg.className = 'error';
            resultadoMsg.textContent = '✗ ' + data.mensaje;
            resultado.style.display = 'block';
            
            setTimeout(() => {
                reader.classList.remove('error');
                status.classList.remove('error');
                status.textContent = 'Intenta de nuevo';
            }, 2000);
        }
    } catch (error) {
        console.error('Error:', error);
        reader.classList.remove('capturing');
        reader.classList.add('error');
        status.classList.remove('capturing');
        status.classList.add('error');
        status.textContent = 'Error de conexión';
    }
}
</script>
</body>
</html>
