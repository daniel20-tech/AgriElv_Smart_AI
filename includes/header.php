<?php
// Layout commun. Variables : $pageTitle, $active, $auth, $guide (étapes de la mascotte)
require_once __DIR__ . '/robot.php';
$auth = $auth ?? false;
$user = current_user();
$theme = $user ? user_settings((int)$user['id'])['theme'] : 'light';
$nav = [
    ['dashboard.php', 'bi-grid-1x2-fill', 'Tableau de bord', 'dashboard'],
    ['armel.php', 'bi-robot', 'Armel IA', 'armel'],
    ['forum.php', 'bi-people-fill', 'Forum', 'forum'],
    ['rapports.php', 'bi-bar-chart-line-fill', 'Rapports', 'rapports'],
    ['avis.php', 'bi-chat-heart-fill', 'Avis', 'avis'],
    ['parametres.php', 'bi-gear-fill', 'Paramètres', 'parametres'],
];
if ($user && $user['role'] === 'admin') $nav[] = ['admin.php', 'bi-shield-lock-fill', 'Admin', 'admin'];
?><!doctype html>
<html lang="fr" data-bs-theme="<?= e($theme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'AgriElv') ?> | AgriElv Smart AI</title>
  <meta name="description" content="AgriElv Smart AI : surveillez vos cultures et votre élevage au Cameroun avec des capteurs connectés et l'assistant IA Armel.">
  <meta property="og:title" content="AgriElv Smart AI">
  <meta property="og:description" content="Gestion intelligente des exploitations agricoles et d'élevage au Cameroun.">
  <meta property="og:type" content="website">
  <meta name="theme-color" content="#0f7b47">
  <?php if (!$auth): ?><meta name="robots" content="noindex"><?php endif; ?>
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext y=%22.9em%22 font-size=%2290%22%3E%F0%9F%8C%B1%3C/text%3E%3C/svg%3E">
  <link rel="stylesheet" href="<?= e(lib('assets/vendor/bootstrap/css/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(lib('assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= $auth ? 'auth-body' : 'app-body' ?>">
<div id="splash" class="splash" aria-hidden="true">
  <div class="splash-in">
    <?= robot_svg('splash', 'robot-sm') ?>
    <div class="splash-title">Bienvenue sur <b>AgriElv</b></div>
    <div class="splash-bar"><i></i></div>
  </div>
</div>
<div id="navProgress" class="nav-progress" aria-hidden="true"></div>
<?php if (!$auth): ?>
<div class="app-frame">
  <aside class="rail" aria-label="Navigation">
    <a class="rail-logo" href="dashboard.php" aria-label="AgriElv"><i class="bi bi-flower2"></i></a>
    <?php foreach ($nav as [$href, $icon, $label, $key]): ?>
      <a href="<?= $href ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"><i class="bi <?= $icon ?>"></i></a>
    <?php endforeach; ?>
    <form method="post" action="logout.php" class="mt-auto">
      <?= csrf_field() ?>
      <button class="rail-btn" title="Se déconnecter" aria-label="Se déconnecter"><i class="bi bi-box-arrow-right"></i></button>
    </form>
  </aside>
  <main class="main">
    <header class="topbar" id="topbar">
      <a class="brand" href="dashboard.php"><i class="bi bi-flower2"></i> AgriElv <span>Smart AI</span></a>
      <nav class="nav-top" aria-label="Menu principal">
        <?php foreach ($nav as [$href, , $label, $key]): ?>
          <a href="<?= $href ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="d-flex align-items-center gap-2">
        <button class="icon-btn" id="themeBtn" type="button" aria-label="Changer de thème"><i class="bi bi-moon-stars-fill"></i></button>
        <a class="avatar" href="parametres.php" aria-label="Mon profil"><?= e(mb_strtoupper(mb_substr($user['nom'] ?? '?', 0, 1))) ?></a>
      </div>
    </header>
    <?php foreach (pull_flash() as $f): ?>
      <div class="alert alert-<?= $f['t'] === 'ok' ? 'success' : 'danger' ?> rounded-4 py-2" role="alert"><?= e($f['m']) ?></div>
    <?php endforeach; ?>
<?php endif; ?>
