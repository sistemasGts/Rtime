<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../datos/db.php';
require_once __DIR__ . '/../datos/api_local_client.php';

$search = trim($_GET['q'] ?? '');
$results = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert_trabajador' && isset($_POST['per_iId'])) {
    $insertId = intval($_POST['per_iId']);
    $personaResponse = rtimeApiObtenerTrabajador($insertId);

    if ($personaResponse['ok'] ?? false) {
        $personaInsert = $personaResponse['item'];

        $stmtExist = $con2->prepare('SELECT per_iId FROM trabajador WHERE per_iId = ? LIMIT 1');
        if ($stmtExist) {
            $stmtExist->bind_param('i', $insertId);
            $stmtExist->execute();
            $resultExist = $stmtExist->get_result();
            $alreadyExists = $resultExist->fetch_assoc();
            $stmtExist->close();

            if ($alreadyExists) {
                header('Location: guardar_huella.php?id=' . $insertId);
                exit;
            }
        }

        $stmt2 = $con2->prepare('INSERT INTO trabajador (per_iId, per_vcDocumento, per_vcPaterno, per_vcMaterno, per_vcNombre, per_vcNombres) VALUES (?, ?, ?, ?, ?, ?)');
        if ($stmt2) {
            $stmt2->bind_param('isssss', $personaInsert['per_iId'], $personaInsert['per_vcDocumento'], $personaInsert['per_vcPaterno'], $personaInsert['per_vcMaterno'], $personaInsert['per_vcNombre'], $personaInsert['per_vcNombres']);
            if ($stmt2->execute()) {
                $stmt2->close();
                header('Location: guardar_huella.php?id=' . $insertId . '&inserted=1');
                exit;
            }
            $error = 'Error al insertar trabajador: ' . $stmt2->error;
            $stmt2->close();
        } else {
            $error = 'Error preparando inserción en trabajador.';
        }
    } else {
        $error = $personaResponse['mensaje'] ?? 'No se pudo consultar API de trabajadores.';
    }
}

if ($search !== '') {
    $searchResponse = rtimeApiBuscarTrabajadores($search);
    if ($searchResponse['ok'] ?? false) {
        $results = $searchResponse['items'];
    } else {
        $error = $searchResponse['mensaje'] ?? 'No se pudo consultar API de trabajadores.';
    }
}

$pageTitle = 'Gestión de huellas';
$pageSubtitle = 'Buscar trabajadores por nombre o documento.';
$headerLinks = [
    ['href' => '../proceso/logout.php?redirect=../index.php', 'label' => 'Volver a Asistencia', 'perm' => 'view_assistance'],
    ['href' => 'panel.php', 'label' => 'Panel', 'perm' => 'view_panel'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de huellas</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<div class="container">
    <div class="card-box">
        <?php include __DIR__ . '/header_menu.php'; ?>

        <form class="search-box" method="get" action="gestionar_huellas.php">
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nombre o documento" required>
            <button type="submit">Buscar</button>
        </form>

        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($search !== '' && empty($results)): ?>
            <div class="warning">No se encontraron trabajadores para "<?= htmlspecialchars($search) ?>".</div>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Apellido paterno</th>
                        <th>Apellido materno</th>
                        <th>Nombre</th>
                        <th>Nombres</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['per_iId']) ?></td>
                            <td><?= htmlspecialchars($row['per_vcPaterno']) ?></td>
                            <td><?= htmlspecialchars($row['per_vcMaterno']) ?></td>
                            <td><?= htmlspecialchars($row['per_vcNombre']) ?></td>
                            <td><?= htmlspecialchars($row['per_vcNombres']) ?></td>
                            <td>
                                <form method="POST" action="gestionar_huellas.php" style="display:inline; margin:0;">
                                    <input type="hidden" name="action" value="insert_trabajador">
                                    <input type="hidden" name="per_iId" value="<?= htmlspecialchars($row['per_iId']) ?>">
                                    <button type="submit" class="btn btn-success" style="display: inline-block; width: auto;">
                                        Gestionar Huellas
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
