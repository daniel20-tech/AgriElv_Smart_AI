<?php
// ============================================================
// API capteurs
//  GET  : (session connectée) dernières mesures pour le dashboard
//  POST : (clé capteur X-Device-Key) envoi d'une mesure par le capteur IoT
// ============================================================
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/sensors.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = $_SERVER['HTTP_X_DEVICE_KEY'] ?? '';
    if (!is_string($key) || strlen($key) < 20 || strlen($key) > 100) json_out(['error' => 'Clé invalide'], 401);
    $q = db()->prepare('SELECT id, user_id, last_seen FROM devices WHERE api_key_hash = ?');
    $q->execute([hash('sha256', $key)]);
    $dev = $q->fetch();
    if (!$dev) json_out(['error' => 'Clé invalide'], 401);
    // Limite : 1 mesure toutes les 5 secondes par capteur
    if ($dev['last_seen'] && time() - strtotime($dev['last_seen']) < 5) json_out(['error' => 'Trop de requêtes (1 mesure / 5 s)'], 429);

    $raw = file_get_contents('php://input', false, null, 0, 4096);
    $in = json_decode($raw ?: '', true);
    if (!is_array($in)) json_out(['error' => 'JSON invalide'], 400);
    $num = fn($k, $min, $max) => (isset($in[$k]) && is_numeric($in[$k]) && $in[$k] >= $min && $in[$k] <= $max) ? (float)$in[$k] : null;
    $t = $num('temperature', -10, 60); $h = $num('soil_humidity', 0, 100); $a = $num('air_quality', 0, 100);
    if ($t === null || $h === null || $a === null) json_out(['error' => 'Champs requis : temperature (-10..60), soil_humidity (0..100), air_quality (0..100)'], 422);
    $r = [
        'temperature' => round($t, 1), 'soil_humidity' => round($h, 1), 'air_quality' => (int)$a,
        'camera_online' => empty($in['camera_online']) ? 0 : 1, 'motion' => empty($in['motion']) ? 0 : 1,
    ];
    $uid = (int)$dev['user_id'];
    store_reading($uid, (int)$dev['id'], $r, user_settings($uid));
    db()->prepare('UPDATE devices SET last_seen = NOW() WHERE id = ?')->execute([$dev['id']]);
    json_out(['ok' => true], 201);
}

// ---- GET : dashboard ----
$user = require_login(true);
$uid = (int)$user['id'];
$s = user_settings($uid);
demo_tick($uid, $s);
$r = latest_reading($uid);
if (!$r) json_out(['latest' => null, 'series' => [], 'alerts_today' => alerts_today($uid)]);
$st = reading_status($r, $s);
$wk = week_stats($uid);
json_out([
    'health' => health_score((float)$r['temperature'], (float)$r['soil_humidity'], $s),
    'zones' => zones_overview($uid, $s, $r),
    'hum_avg' => $wk['hum'],
    'latest' => [
        'temperature' => number_format((float)$r['temperature'], 1, '.', ''),
        'soil_humidity' => number_format((float)$r['soil_humidity'], 1, '.', ''),
        'air_quality' => (int)$r['air_quality'],
        'camera_online' => (int)$r['camera_online'],
        'time' => date('H:i', strtotime($r['created_at'])),
    ] + $st,
    'series' => humidity_series($uid),
    'alerts_today' => alerts_today($uid),
]);
