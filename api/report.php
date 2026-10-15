<?php
// Données du rapport (JSON) : le PDF est ensuite généré côté navigateur par assets/js/pdf.js
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/sensors.php';
$user = require_login(true);
$uid = (int)$user['id'];
$s = user_settings($uid);
$week = weekly_optimal($uid, $s);
$alerts = array_map(fn($a) => [
    'date' => date('d/m/Y H:i', strtotime($a['created_at'])), 'msg' => $a['message'], 'sev' => $a['severity'],
], recent_alerts($uid, 25));
$r = latest_reading($uid);
json_out([
    'nom' => $user['nom'], 'type' => type_label($user['type_exploitation']),
    'generated' => date('d/m/Y H:i'),
    'thresholds' => ['temp' => $s['alert_temp_max'], 'hum' => $s['alert_humidity_min']],
    'stats' => week_stats($uid),
    'latest' => $r ? ['temperature' => (float)$r['temperature'], 'soil_humidity' => (float)$r['soil_humidity'], 'air_quality' => (int)$r['air_quality'], 'health' => health_score((float)$r['temperature'], (float)$r['soil_humidity'], $s)] : null,
    'week' => $week,
    'zones' => zones_overview($uid, $s, $r),
    'alerts' => $alerts,
]);
