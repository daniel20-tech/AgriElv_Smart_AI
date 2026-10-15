<?php
// Contrôles manuels : irrigation automatique (on/off) et ventilation (0-100). POST JSON + CSRF.
require __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Méthode non autorisée'], 405);
$user = require_login(true);
csrf_or_die(true);
$uid = (int)$user['id'];
user_settings($uid);
$in = json_decode(file_get_contents('php://input', false, null, 0, 512) ?: '', true);
if (!is_array($in)) json_out(['error' => 'JSON invalide'], 400);
if (array_key_exists('auto_irrigation', $in)) {
    db()->prepare('UPDATE settings SET auto_irrigation = ? WHERE user_id = ?')->execute([empty($in['auto_irrigation']) ? 0 : 1, $uid]);
}
if (array_key_exists('ventilation', $in)) {
    $v = max(0, min(100, (int)$in['ventilation']));
    db()->prepare('UPDATE settings SET ventilation = ? WHERE user_id = ?')->execute([$v, $uid]);
}
json_out(['ok' => true]);
