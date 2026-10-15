<?php
// Ajout / suppression de caméras et capteurs (POST + CSRF uniquement)
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('dashboard.php');
csrf_or_die();
$back = in_array($_POST['back'] ?? '', ['equipements.php', 'dashboard.php', 'parametres.php'], true) ? $_POST['back'] : 'equipements.php';
$action = (string)($_POST['action'] ?? '');
$name = trim((string)($_POST['name'] ?? ''));

// La zone choisie doit appartenir à l'utilisateur
$zoneId = null;
if (!empty($_POST['zone_id'])) {
    $z = db()->prepare('SELECT id FROM zones WHERE id = ? AND user_id = ?');
    $z->execute([(int)$_POST['zone_id'], $uid]);
    $zoneId = $z->fetchColumn() ?: null;
}
$count = function (string $table) use ($uid): int {
    $c = db()->prepare("SELECT COUNT(*) FROM $table WHERE user_id = ?");   // $table vient du code, jamais de l'utilisateur
    $c->execute([$uid]);
    return (int)$c->fetchColumn();
};

if ($action === 'add_camera') {
    $type = (string)($_POST['type'] ?? '');
    $url = trim((string)($_POST['url'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) flash('err', 'Nom de caméra : 2 à 60 caractères.');
    elseif (!in_array($type, ['mjpeg', 'snapshot', 'video', 'device'], true)) flash('err', 'Type de flux invalide.');
    elseif ($count('cameras') >= 6) flash('err', 'Maximum 6 caméras par compte.');
    else {
        $ok = true;
        if ($type === 'device') $url = null;
        else {
            $p = parse_url($url);
            $ok = filter_var($url, FILTER_VALIDATE_URL) && strlen($url) <= 500 && in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) && empty($p['user']) && empty($p['pass']);
        }
        if (!$ok) flash('err', "Adresse invalide : utilisez une URL http(s) sans identifiant (user:pass@).");
        else {
            db()->prepare('INSERT INTO cameras (user_id, zone_id, name, type, url) VALUES (?,?,?,?,?)')->execute([$uid, $zoneId, $name, $type, $url]);
            flash('ok', 'Caméra ajoutée. Sélectionnez-la au centre du tableau de bord.');
        }
    }
} elseif ($action === 'add_sensor') {
    $kind = (string)($_POST['kind'] ?? 'mixte');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) flash('err', 'Nom du capteur : 2 à 80 caractères.');
    elseif (!in_array($kind, ['mixte', 'temperature', 'humidite', 'air'], true)) flash('err', 'Type de capteur invalide.');
    elseif ($count('devices') >= 10) flash('err', 'Maximum 10 capteurs par compte.');
    else {
        $key = 'agk_' . bin2hex(random_bytes(24));
        db()->prepare('INSERT INTO devices (user_id, zone_id, kind, name, api_key_hash) VALUES (?,?,?,?,?)')->execute([$uid, $zoneId, $kind, $name, hash('sha256', $key)]);
        $_SESSION['newkey'] = ['key' => $key, 'name' => $name];   // affichée une seule fois
    }
} elseif ($action === 'del_camera' || $action === 'del_sensor') {
    $table = $action === 'del_camera' ? 'cameras' : 'devices';
    db()->prepare("DELETE FROM $table WHERE id = ? AND user_id = ?")->execute([(int)($_POST['id'] ?? 0), $uid]);
    flash('ok', $action === 'del_camera' ? 'Caméra supprimée.' : 'Capteur supprimé.');
}
redirect($back . (($action === 'add_sensor' && isset($_SESSION['newkey'])) ? '#newKey' : ''));
