<?php
require __DIR__ . '/../datos/db.php';
$d = '2026-09-09';
$stmt = $con2->prepare('SELECT idAperiodo, inicio_periodo, fin_periodo, cli_iId, conpla_iId FROM asistencias_periodo WHERE ? BETWEEN inicio_periodo AND fin_periodo LIMIT 10');
$stmt->bind_param('s', $d);
$stmt->execute();
$r = $stmt->get_result();
$out = [];
while ($row = $r->fetch_assoc()) { $out[] = $row; }
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
