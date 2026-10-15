<?php
// ============================================================
// Capteurs : lecture, simulation (démo), alertes, statistiques
// ============================================================

/** Interprète une mesure par rapport aux seuils de l'utilisateur */
function reading_status(array $r, array $s): array
{
    $t = (float)$r['temperature']; $h = (float)$r['soil_humidity']; $a = (int)$r['air_quality'];
    $tmax = $s['alert_temp_max']; $hmin = $s['alert_humidity_min'];
    [$ts, $tl] = $t > $tmax ? ['Élevée', 'bad'] : ($t > $tmax - 2 ? ['Proche du seuil', 'warn'] : ['Normale', 'ok']);
    [$hs, $hl] = $h < $hmin ? ['Trop sec', 'bad'] : ($h < $hmin + 10 ? ['À surveiller', 'warn'] : ['Optimale', 'ok']);
    [$as, $al] = $a >= 70 ? ['Bonne', 'ok'] : ($a >= 40 ? ['Moyenne', 'warn'] : ['Mauvaise', 'bad']);
    return [
        'temp_status' => $ts, 'temp_level' => $tl,
        'soil_status' => $hs, 'soil_level' => $hl,
        'air_label' => $as, 'air_level' => $al,
    ];
}

/** Mesure simulée (mode démo) : petite variation autour de la précédente */
function demo_reading(?array $prev): array
{
    $t = $prev ? (float)$prev['temperature'] : 29.0;
    $h = $prev ? (float)$prev['soil_humidity'] : 55.0;
    $a = $prev ? (int)$prev['air_quality'] : 72;
    return [
        'temperature' => round(max(22, min(38, $t + mt_rand(-12, 12) / 10)), 1),
        'soil_humidity' => round(max(28, min(75, $h + mt_rand(-30, 25) / 10)), 1),
        'air_quality' => max(25, min(95, $a + mt_rand(-4, 4))),
        'camera_online' => mt_rand(1, 100) > 4 ? 1 : 0,
        'motion' => mt_rand(1, 100) > 93 ? 1 : 0,
    ];
}

function store_reading(int $uid, ?int $deviceId, array $r, array $s, ?string $ts = null): void
{
    $ts = $ts ?? date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO sensor_readings (user_id, device_id, temperature, soil_humidity, air_quality, camera_online, motion, created_at) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$uid, $deviceId, $r['temperature'], $r['soil_humidity'], $r['air_quality'], (int)$r['camera_online'], (int)$r['motion'], $ts]);
    evaluate_alerts($uid, $r, $s, $ts);
}

/** Crée les alertes selon les seuils (1 alerte max par type toutes les 30 min) */
function evaluate_alerts(int $uid, array $r, array $s, string $ts): void
{
    $rules = [];
    $t = (float)$r['temperature']; $h = (float)$r['soil_humidity'];
    if ($t > $s['alert_temp_max']) {
        $rules[] = ['temperature', "Température élevée : {$t}°C (seuil {$s['alert_temp_max']}°C)", $t > $s['alert_temp_max'] + 4 ? 'critique' : 'moyenne'];
    }
    if ($h < $s['alert_humidity_min']) {
        $rules[] = ['humidite', "Sol trop sec : {$h}% (seuil {$s['alert_humidity_min']}%)", $h < $s['alert_humidity_min'] - 15 ? 'critique' : 'moyenne'];
    }
    if (!empty($r['motion']) && $s['alert_motion']) $rules[] = ['mouvement', 'Mouvement détecté devant la caméra', 'faible'];
    if (empty($r['camera_online'])) $rules[] = ['camera', 'Caméra hors ligne', 'moyenne'];

    $chk = db()->prepare('SELECT COUNT(*) FROM alerts WHERE user_id = ? AND type = ? AND created_at <= ? AND created_at > DATE_SUB(?, INTERVAL 30 MINUTE)');
    $ins = db()->prepare('INSERT INTO alerts (user_id, type, message, severity, created_at) VALUES (?,?,?,?,?)');
    foreach ($rules as [$type, $msg, $sev]) {
        $chk->execute([$uid, $type, $ts, $ts]);
        if ((int)$chk->fetchColumn() === 0) $ins->execute([$uid, $type, $msg, $sev, $ts]);
    }
}

function latest_reading(int $uid): ?array
{
    $q = db()->prepare('SELECT * FROM sensor_readings WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1');
    $q->execute([$uid]);
    return $q->fetch() ?: null;
}

/** Démo : remplit 7 jours d'historique au 1er lancement puis ajoute une mesure toutes les 20 s */
function demo_tick(int $uid, array $s): void
{
    if (env('APP_DEMO', 'false') !== 'true') return;
    $last = latest_reading($uid);
    if (!$last) {
        $prev = null;
        for ($i = 7 * 6; $i >= 1; $i--) {
            $ts = date('Y-m-d H:i:s', time() - $i * 4 * 3600);
            $prev = demo_reading($prev);
            store_reading($uid, null, $prev, $s, $ts);
        }
        $last = $prev;
        $last['created_at'] = date('Y-m-d H:i:s', time() - 3600);
    }
    if (time() - strtotime($last['created_at']) >= 20) {
        store_reading($uid, null, demo_reading($last), $s);
    }
}

/** Dernières valeurs d'humidité du sol pour la courbe */
function humidity_series(int $uid, int $n = 14): array
{
    $q = db()->prepare('SELECT soil_humidity FROM (SELECT soil_humidity, created_at, id FROM sensor_readings WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int)$n . ') x ORDER BY created_at ASC, id ASC');
    $q->execute([$uid]);
    return array_map('floatval', array_column($q->fetchAll(), 'soil_humidity'));
}

/** % de conditions optimales sur les 7 derniers jours */
function weekly_optimal(int $uid, array $s): array
{
    $q = db()->prepare('SELECT DATE(created_at) d, COUNT(*) n, SUM(temperature <= ? AND soil_humidity >= ?) ok FROM sensor_readings WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)');
    $q->execute([$s['alert_temp_max'], $s['alert_humidity_min'], $uid]);
    $rows = [];
    foreach ($q->fetchAll() as $r) $rows[$r['d']] = $r;
    $jours = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
    $out = [];
    for ($i = 6; $i >= 0; $i--) {
        $ts = strtotime("-$i day");
        $d = date('Y-m-d', $ts);
        $pct = isset($rows[$d]) && (int)$rows[$d]['n'] > 0 ? (int)round(100 * $rows[$d]['ok'] / $rows[$d]['n']) : null;
        $out[] = ['label' => $jours[(int)date('N', $ts)], 'pct' => $pct, 'today' => $i === 0];
    }
    return $out;
}

/** Statistiques des 7 derniers jours */
function week_stats(int $uid): array
{
    $q = db()->prepare('SELECT AVG(temperature) t, AVG(soil_humidity) h, AVG(camera_online) c FROM sensor_readings WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
    $q->execute([$uid]);
    $r = $q->fetch();
    $a = db()->prepare('SELECT COUNT(*) FROM alerts WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
    $a->execute([$uid]);
    return [
        'temp' => $r['t'] !== null ? round((float)$r['t'], 1) : null,
        'hum' => $r['h'] !== null ? round((float)$r['h'], 1) : null,
        'camera' => $r['c'] !== null ? (int)round(100 * (float)$r['c']) : null,
        'alerts' => (int)$a->fetchColumn(),
    ];
}

function alerts_today(int $uid): int
{
    $q = db()->prepare('SELECT COUNT(*) FROM alerts WHERE user_id = ? AND created_at >= CURDATE()');
    $q->execute([$uid]);
    return (int)$q->fetchColumn();
}

/** Alertes filtrées par criticité ('' = toutes) */
function recent_alerts(int $uid, int $limit = 5, string $sev = ''): array
{
    $sql = 'SELECT type, message, severity, created_at FROM alerts WHERE user_id = ?';
    $args = [$uid];
    if (in_array($sev, ['faible', 'moyenne', 'critique'], true)) { $sql .= ' AND severity = ?'; $args[] = $sev; }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . (int)$limit;
    $q = db()->prepare($sql);
    $q->execute($args);
    return $q->fetchAll();
}

function alert_icon(string $type): string
{
    return ['temperature' => 'bi-thermometer-high', 'humidite' => 'bi-droplet', 'mouvement' => 'bi-person-walking', 'camera' => 'bi-camera-video-off'][$type] ?? 'bi-bell';
}
function sev_class(string $sev): string
{
    return ['critique' => 'bad', 'moyenne' => 'warn', 'faible' => 'ok'][$sev] ?? 'ok';
}

// ============================================================
// V2 : zones, indice de santé, recommandations
// ============================================================

/** Indice de santé 0-100 selon température et humidité du sol */
function health_score(float $t, float $h, array $s): int
{
    $p = max(0, $t - ($s['alert_temp_max'] - 3)) * 7 + max(0, ($s['alert_humidity_min'] + 12) - $h) * 1.6;
    return (int)max(0, min(100, round(100 - $p)));
}

function level_of(?int $score): string
{
    return $score === null ? 'none' : ($score >= 80 ? 'ok' : ($score >= 50 ? 'warn' : 'bad'));
}
function level_label(string $lvl): string
{
    return ['ok' => 'Saine', 'warn' => 'À surveiller', 'bad' => 'Action requise', 'none' => 'Non équipée'][$lvl] ?? '';
}

/** État de chaque zone : mesure du capteur assigné ; sinon (démo) valeurs simulées autour de la mesure globale */
function zones_overview(int $uid, array $s, ?array $r): array
{
    $z = db()->prepare('SELECT id, name, culture FROM zones WHERE user_id = ? ORDER BY id');
    $z->execute([$uid]);
    $demo = env('APP_DEMO', 'false') === 'true';
    $offs = [[0, 0], [2.5, -9], [4.5, -18]];
    $q = db()->prepare('SELECT sr.temperature, sr.soil_humidity FROM sensor_readings sr JOIN devices d ON d.id = sr.device_id WHERE d.zone_id = ? AND d.user_id = ? ORDER BY sr.created_at DESC, sr.id DESC LIMIT 1');
    $out = [];
    foreach ($z->fetchAll() as $i => $zone) {
        $q->execute([$zone['id'], $uid]);
        $row = $q->fetch();
        $sim = false;
        if (!$row && $r && $demo) {
            $o = $offs[$i % 3];
            $row = ['temperature' => (float)$r['temperature'] + $o[0], 'soil_humidity' => max(5, (float)$r['soil_humidity'] + $o[1])];
            $sim = true;
        }
        $score = $row ? health_score((float)$row['temperature'], (float)$row['soil_humidity'], $s) : null;
        $lvl = level_of($score);
        $out[] = [
            'id' => (int)$zone['id'], 'name' => $zone['name'], 'culture' => $zone['culture'],
            'score' => $score, 'level' => $lvl, 'label' => level_label($lvl),
            'temp' => $row ? round((float)$row['temperature'], 1) : null,
            'hum' => $row ? round((float)$row['soil_humidity'], 1) : null,
            'sim' => $sim,
        ];
    }
    return $out;
}

/** Recommandation courte affichée dans "Conseil IA" + question à envoyer à Armel */
function farm_insight(?array $r, array $s, array $zones): array
{
    if (!$r) return ['Connectez un capteur pour recevoir des recommandations personnalisées.', 'Comment bien démarrer avec mes capteurs ?'];
    $worst = null;
    foreach ($zones as $z) if ($z['score'] !== null && ($worst === null || $z['score'] < $worst['score'])) $worst = $z;
    $t = (float)$r['temperature']; $h = (float)$r['soil_humidity'];
    if ($h < $s['alert_humidity_min'] + 10) $txt = "Irriguer aujourd'hui peut rétablir l'humidité du sol (actuellement {$h}%).";
    elseif ($t > $s['alert_temp_max'] - 2) $txt = "Chaleur élevée ({$t}°C) : ajoutez de l'ombre et arrosez tôt le matin.";
    else $txt = 'Conditions favorables. Continuez le suivi, tout est dans la zone idéale.';
    $zn = $worst ? $worst['name'] . ' (' . $worst['culture'] . ')' : 'mon exploitation';
    return [$txt, "Que faire pour $zn avec {$t}°C et {$h}% d'humidité du sol ?"];
}

function readings_week_count(int $uid): int
{
    $q = db()->prepare('SELECT COUNT(*) FROM sensor_readings WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
    $q->execute([$uid]);
    return (int)$q->fetchColumn();
}
