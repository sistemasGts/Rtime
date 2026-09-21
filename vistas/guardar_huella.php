<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../datos/db.php';

$per_iId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$showModal = isset($_GET['inserted']) && $_GET['inserted'] == '1';

if ($per_iId === 0) {
    header('Location: gestionar_huellas.php');
    exit;
}

$persona = null;
$error = '';
$success = '';

$stmt = $con2->prepare('SELECT per_iId, per_vcPaterno, per_vcMaterno, per_vcNombre, per_vcNombres FROM trabajador WHERE per_iId = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $per_iId);
    $stmt->execute();
    $result = $stmt->get_result();
    $persona = $result->fetch_assoc();
    $stmt->close();
}

if (!$persona) {
    $error = 'Trabajador no encontrado.';
}

// Cargar huellas ya registradas para este trabajador
$capturas = [];
$stmt2 = $con2->prepare('SELECT dedo, template_binario, imagen_b64 FROM huella_biometrica WHERE per_iId = ?');
if ($stmt2) {
    $stmt2->bind_param('i', $per_iId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($r = $res2->fetch_assoc()) {
        $capturas[$r['dedo']] = [
            'template' => $r['template_binario'],
            'imagen' => $r['imagen_b64']
        ];
    }
    $stmt2->close();
}

$dedos = [
    'ID1' => 'Pulgar Izquierdo',
    'ID2' => 'Índice Izquierdo',
    'ID3' => 'Medio Izquierdo',
    'ID4' => 'Anular Izquierdo',
    'ID5' => 'Meñique Izquierdo',
    'DD1' => 'Pulgar Derecho',
    'DD2' => 'Índice Derecho',
    'DD3' => 'Medio Derecho',
    'DD4' => 'Anular Derecho',
    'DD5' => 'Meñique Derecho',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dedo'])) {
    $dedo = $_POST['dedo'];
    $huella_template = $_POST['huella_template'] ?? '';
    $huella_image = $_POST['huella_image'] ?? '';
    
    if (isset($dedos[$dedo]) && !empty($huella_template)) {
        $stmt = $con2->prepare('
            INSERT INTO huella_biometrica (per_iId, dedo, template_binario, imagen_b64, fecha_captura)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            template_binario = VALUES(template_binario),
            imagen_b64 = VALUES(imagen_b64),
            fecha_captura = NOW()
        ');
        
        if ($stmt) {
            $stmt->bind_param('isss', $per_iId, $dedo, $huella_template, $huella_image);
            if ($stmt->execute()) {
                $success = "Huella registrada para " . htmlspecialchars($dedos[$dedo]) . " correctamente.";
            } else {
                $error = "Error al guardar la huella: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$pageTitle = 'Gestionar Huellas';
$pageSubtitle = 'Captura huellas por dedo para cada trabajador.';
$headerLinks = [
    ['href' => 'gestionar_huellas.php', 'label' => 'Volver', 'perm' => 'view_huellas'],
    ['href' => 'panel.php', 'label' => 'Panel', 'perm' => 'view_panel'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guardar Huella</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .huella-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        .huella-card {
            background: #f9fafb;
            padding: 16px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            text-align: center;
            cursor: pointer;
            transition: .3s;
        }
        .huella-card:hover {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .huella-card.capturada {
            border-color: #16a34a;
            background: #f0fdf4;
        }
        .huella-card h4 {
            margin: 0 0 8px;
            font-size: 14px;
            color: #374151;
        }
        .huella-icon {
            font-size: 32px;
            margin: 8px 0;
        }
        .huella-status {
            font-size: 12px;
            color: #6b7280;
            margin-top: 8px;
        }
        .huella-status.capturada {
            color: #16a34a;
            font-weight: 600;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            z-index: 9999;
        }
        .modal-box {
            width: auto;
            max-width: 520px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 32px 80px rgba(15, 23, 42, 0.18);
            padding: 28px;
            text-align: left;
            box-sizing: border-box;
        }
        .modal-box h3 {
            margin: 0 0 16px;
            color: #0f172a;
        }
        .modal-box p {
            margin: 0 0 20px;
            color: #475569;
        }
        .modal-content {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
        }
        .modal-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            color: #334155;
            align-items: center;
        }
        .modal-row strong {
            color: #0f172a;
        }
        .huella-preview {
            width: 100%;
            max-width: 100%;
            min-height: 120px;
            max-height: 260px;
            overflow: hidden;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
            line-height: 1.45;
        }
        .huella-preview.captured {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #0f172a;
        }
        .huella-preview svg {
            width: 56px;
            height: 56px;
            margin-bottom: 8px;
        }
        .huella-preview img {
            display: block;
            max-width: 100%;
            max-height: 100%;
            height: auto;
            border-radius: 12px;
            object-fit: contain;
        }

        /* Evitar que el contenido del modal provoque overflow horizontal */
        .modal-overlay { overflow-x: hidden; }
        .modal-box .modal-content { overflow-y: auto; max-height: 60vh; }

        /* Ajustes específicos para la fila de plantilla: evitar que la cadena larga rompa el layout */
        #modalTemplateRow {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        #modalTemplateValue {
            display: block;
            width: 100%;
            max-width: 100%;
            white-space: pre-wrap;
            word-break: break-all;
            overflow-x: auto;
            background: #f8fafc;
            border: 1px solid #e6eef8;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 12px;
            color: #0f172a;
            line-height: 1.2;
        }
        .modal-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
        }
        .modal-actions .btn {
            flex: 1 1 130px;
            min-width: 110px;
            padding: 12px 14px;
            font-size: 0.95rem;
            white-space: normal;
            line-height: 1.3;
        }
        .modal-actions .btn-primary {
            background: #2563eb;
            color: #ffffff;
            border: 1px solid #2563eb;
        }
        .modal-actions .btn-primary:hover {
            background: #1d4ed8;
        }
        .modal-close {
            position: absolute;
            right: 18px;
            top: 18px;
            background: transparent;
            border: none;
            font-size: 20px;
            color: #64748b;
            cursor: pointer;
        }
        .modal-actions .btn-secondary {
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid #cbd5e1;
        }
        .modal-actions .btn-secondary:hover {
            background: #e2e8f0;
        }
        .modal-actions .btn[disabled] {
            opacity: 0.55;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card-box">
        <?php include __DIR__ . '/header_menu.php'; ?>
        <?php if ($persona): ?>
            <div class="info" style="margin-top: 12px;">
               <p><strong><?= htmlspecialchars($persona['per_vcNombres']) ?></strong> (ID: <?= htmlspecialchars($persona['per_iId']) ?>)</p>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($showModal && $persona): ?>
            <div class="modal-overlay" id="modalInfo">
                <div class="modal-box">
                    <h3>Trabajador cargado</h3>
                    <p>Se insertó el trabajador correctamente en la tabla <strong>trabajador</strong>.</p>
                    <p><strong><?= htmlspecialchars($persona['per_vcNombres']) ?></strong> está listo para gestionar huellas.</p>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cerrar</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($persona): ?>
            <div>
                <h3>Capturar Huellas por Dedo</h3>
                <p>Selecciona un dedo para capturar o actualizar su huella biométrica.</p>

                <div class="huella-container">
                    <?php foreach ($dedos as $codigo => $nombre): ?>
                        <?php $captured = isset($capturas[$codigo]); ?>
                                <?php $capturedImage = $captured ? $capturas[$codigo]['imagen'] : ''; ?>
                        <?php $capturedTemplate = $captured ? $capturas[$codigo]['template'] : ''; ?>
                        <div class="huella-card <?= $captured ? 'capturada' : '' ?>" data-dedo="<?= htmlspecialchars($codigo) ?>" data-name="<?= htmlspecialchars($nombre) ?>" onclick="seleccionarDedo('<?= htmlspecialchars($codigo) ?>', '<?= htmlspecialchars($nombre) ?>')">
                            <h4><?= htmlspecialchars($nombre) ?></h4>
                            <div class="huella-icon">🫆</div>
                            <div class="huella-status <?= $captured ? 'capturada' : '' ?>">
                                <?= $captured ? 'Registrada' : 'Haz clic para capturar' ?>
                            </div>
                            <?php if ($captured): ?>

                                <div style="margin-top:8px; display:flex; justify-content:center; gap:8px; flex-wrap:wrap;">
                                    <button type="button" class="btn btn-secondary" onclick="verPlantilla(event, '<?= htmlspecialchars($codigo) ?>', '<?= htmlspecialchars($nombre) ?>')">Ver plantilla</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form id="form-huella" method="POST" action="guardar_huella.php?id=<?= htmlspecialchars($per_iId) ?>" style="display: none;">
                    <input type="hidden" name="dedo" id="dedo-input">
                    <input type="hidden" name="huella_template" id="huella-template-input">
                    <input type="hidden" name="huella_image" id="huella-image-input">
                </form>

                <div class="modal-overlay" id="modalHuella" style="display: none;">
                    <div class="modal-box">
                        <h3 id="modalDedoNombre">Capturar Huella</h3>
                        <button class="modal-close" aria-label="Cerrar" onclick="cerrarModal()">×</button>
                            <p id="modalDedoTexto">Coloca tu dedo en el lector biométrico cuando estés listo.</p>

                        <div class="modal-content">
                            <div class="modal-row">
                                <strong>Dedo seleccionado:</strong>
                                <span id="modalDedoSeleccionado">-</span>
                            </div>
                            <div class="modal-row">
                                <strong>Estado:</strong>
                                <span id="modalEstado">Esperando huella...</span>
                            </div>
                            <div class="huella-preview" id="modalHuellaPreview">
                                <div>
                                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M32 58C47.464 58 60 45.464 60 30C60 14.536 47.464 2 32 2C16.536 2 4 14.536 4 30C4 45.464 16.536 58 32 58Z" stroke="#94a3b8" stroke-width="3"/>
                                        <path d="M40 24C40 32.8366 33.8366 39 25 39" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>
                                        <path d="M32 46C39.1797 46 45 40.1797 45 33" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>
                                        <path d="M24 47C30.6274 47 36 41.6274 36 35" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>
                                        <path d="M28 19C28 24.5228 23.5228 29 18 29" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>
                                    </svg>
                                    <div id="modalPreviewText">No se ha capturado la huella todavía.</div>
                                </div>
                            </div>
                            <div class="modal-row" id="modalTemplateRow" style="display: none;">
                                <strong>Plantilla capturada:</strong>
                                <code id="modalTemplateValue"></code>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn btn-success" id="btnCapturarHuella">Capturar huella</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarHuella" disabled>Guardar huella</button>
                            <button type="button" class="btn btn-secondary" id="btnReintentarHuella" style="display: none;">Reintentar</button>
                            <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                        </div>
                    </div>
                </div>

                <script>
                    const huellasRegistradas = <?= json_encode($capturas, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
                </script>

                <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e5e7eb;">
                    <h3>Notas:</h3>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Haz clic en el dedo que deseas capturar.</li>
                        <li>Se abrirá un modal para colocar el dedo en el lector.</li>
                        <li>Captura la huella en el modal; luego presiona <strong>Guardar huella</strong>.</li>
                        <li>Si quieres, puedes volver a capturar antes de guardar.</li>
                        <li>Puedes capturar todos los dedos de una persona (ambas manos).</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let dedoActual = '';
let nombreActual = '';
let autoCapture = false; // when true, modal will start capture automatically

function seleccionarDedo(codigo, nombre) {
    dedoActual = codigo;
    nombreActual = nombre;
    autoCapture = true;

    document.getElementById('modalDedoNombre').textContent = 'Capturar huella: ' + nombre;
    document.getElementById('modalDedoSeleccionado').textContent = nombre;
    document.getElementById('modalEstado').textContent = 'Coloca tu dedo en el lector... captura automática en curso.';
    document.getElementById('modalTemplateRow').style.display = 'none';
    document.getElementById('modalTemplateValue').textContent = '';
    document.getElementById('modalHuella').style.display = 'flex';

    // start automatic capture shortly after modal opens
    setTimeout(function() {
        if (autoCapture) capturarHuella();
    }, 300);
}

function cerrarModal() {
    const modalIds = ['modalHuella', 'modalInfo'];
    modalIds.forEach(id => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'none';
        }
    });
    autoCapture = false;
}

// Cierra el modal al hacer clic fuera de la caja del modal
window.addEventListener('click', function(event) {
    const overlayIds = ['modalHuella', 'modalInfo'];
    overlayIds.forEach(id => {
        const overlay = document.getElementById(id);
        if (overlay && event.target === overlay) {
            cerrarModal();
        }
    });
});

async function capturarHuella() {
    const dedoInput = document.getElementById('dedo-input');
    const templateInput = document.getElementById('huella-template-input');
    const imageInput = document.getElementById('huella-image-input');
    const estado = document.getElementById('modalEstado');
    const templateRow = document.getElementById('modalTemplateRow');
    const templateValue = document.getElementById('modalTemplateValue');
    const btnGuardar = document.getElementById('btnGuardarHuella');
    const btnReintentar = document.getElementById('btnReintentarHuella');
    const preview = document.getElementById('modalHuellaPreview');
    const previewText = document.getElementById('modalPreviewText');

    if (!dedoActual) {
        estado.textContent = 'Debe seleccionar primero un dedo.';
        return;
    }

    estado.textContent = 'Activando lector... Coloca tu dedo y presiona Capturar.';

    // Llamar al endpoint de captura en el servidor que invoca al SDK
    const form = new FormData();
    form.append('accion', 'capturar');
    form.append('live', '1');

    try {
        const resp = await fetch('../proceso/capturar_huella.php', {
            method: 'POST',
            body: form
        });
        const data = await resp.json();

        if (!data.success) {
            estado.textContent = 'Error: ' + (data.mensaje || 'No se pudo capturar');
            return;
        }

        const template = data.template || '';
        const imagenB64 = data.imagen || '';

        dedoInput.value = dedoActual;
        templateInput.value = template;
        imageInput.value = imagenB64;
        templateValue.textContent = template;
        templateRow.style.display = 'block';

        if (imagenB64) {
            preview.classList.add('capturada');
            preview.innerHTML = '<img src="data:image/png;base64,' + imagenB64 + '" alt="Huella" style="max-width:100%; max-height:220px; border-radius:12px;">';
        } else {
            previewText.textContent = 'Huella capturada correctamente.';
        }

        estado.textContent = 'Huella capturada. Ahora puedes guardar o volver a capturar.';
        btnGuardar.disabled = false;
        btnReintentar.style.display = 'inline-flex';
    } catch (err) {
        estado.textContent = 'Error de comunicación: ' + err.message;
    }
}

function guardarHuella() {
    const templateInput = document.getElementById('huella-template-input');
    const imageInput = document.getElementById('huella-image-input');
    const estado = document.getElementById('modalEstado');

    if (!templateInput.value) {
        estado.textContent = 'No hay huella capturada para guardar.';
        return;
    }

    estado.textContent = 'Guardando huella...';
    document.getElementById('form-huella').submit();
}

function reintentarCaptura() {
    const estado = document.getElementById('modalEstado');
    const templateRow = document.getElementById('modalTemplateRow');
    const templateValue = document.getElementById('modalTemplateValue');
    const btnGuardar = document.getElementById('btnGuardarHuella');
    const btnReintentar = document.getElementById('btnReintentarHuella');
    const templateInput = document.getElementById('huella-template-input');
    const preview = document.getElementById('modalHuellaPreview');
    const previewText = document.getElementById('modalPreviewText');

    estado.textContent = 'Listo para capturar de nuevo. Coloca tu dedo en el lector.';
    templateInput.value = '';
    templateValue.textContent = '';
    templateRow.style.display = 'none';
    preview.classList.remove('capturada');
    previewText.textContent = 'No se ha capturado la huella todavía.';
    btnGuardar.disabled = true;
    btnReintentar.style.display = 'none';
}

const btnCapturarHuella = document.getElementById('btnCapturarHuella');
if (btnCapturarHuella) {
    btnCapturarHuella.addEventListener('click', capturarHuella);
}

const btnGuardarHuella = document.getElementById('btnGuardarHuella');
if (btnGuardarHuella) {
    btnGuardarHuella.addEventListener('click', guardarHuella);
}

const btnReintentarHuella = document.getElementById('btnReintentarHuella');
if (btnReintentarHuella) {
    btnReintentarHuella.addEventListener('click', reintentarCaptura);
}

function verPlantilla(e, codigo, nombre) {
    if (e && e.stopPropagation) e.stopPropagation();
    const huella = huellasRegistradas[codigo] || { template: '', imagen: '' };
    const template = huella.template || '';
    const imagenB64 = huella.imagen || '';
    // viewing existing template: disable auto-capture
    autoCapture = false;

    document.getElementById('modalHuella').style.display = 'flex';
    document.getElementById('modalDedoSeleccionado').textContent = nombre || nombreActual || '-';
    document.getElementById('modalEstado').textContent = 'Huella ya registrada.';
    document.getElementById('modalTemplateRow').style.display = 'block';
    document.getElementById('modalTemplateValue').textContent = template;
    document.getElementById('huella-template-input').value = template;
    document.getElementById('huella-image-input').value = imagenB64;
    const preview = document.getElementById('modalHuellaPreview');

    if (imagenB64) {
        preview.classList.add('capturada');
        preview.innerHTML = '<img src="data:image/png;base64,' + imagenB64 + '" alt="Huella" style="max-width:100%; max-height:220px; border-radius:12px;">';
    } else {
        preview.classList.remove('capturada');
        preview.innerHTML = '<div><svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M32 58C47.464 58 60 45.464 60 30C60 14.536 47.464 2 32 2C16.536 2 4 14.536 4 30C4 45.464 16.536 58 32 58Z" stroke="#94a3b8" stroke-width="3"/><path d="M40 24C40 32.8366 33.8366 39 25 39" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/><path d="M32 46C39.1797 46 45 40.1797 45 33" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/><path d="M24 47C30.6274 47 36 41.6274 36 35" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/><path d="M28 19C28 24.5228 23.5228 29 18 29" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/></svg><div id="modalPreviewText">No se ha capturado la huella todavía.</div></div>';
    }
    document.getElementById('btnGuardarHuella').disabled = false;
    document.getElementById('btnReintentarHuella').style.display = 'inline-flex';
}
</script>
</body>
</html>
