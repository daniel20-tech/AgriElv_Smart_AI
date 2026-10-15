<?php
// Enregistre le thème clair/sombre de l'utilisateur
require __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Méthode non autorisée'], 405);
$user = require_login(true);
csrf_or_die(true);
$in = json_decode(file_get_contents('php://input', false, null, 0, 512) ?: '', true);
$theme = ($in['theme'] ?? '') === 'dark' ? 'dark' : 'light';
user_settings((int)$user['id']);
db()->prepare('UPDATE settings SET theme = ? WHERE user_id = ?')->execute([$theme, (int)$user['id']]);
json_out(['ok' => true]);
