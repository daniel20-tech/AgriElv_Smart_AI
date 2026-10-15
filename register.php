<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('dashboard.php');
$errors = [];
$old = ['nom' => '', 'email' => '', 'type' => 'agriculture'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_or_die();
    $old['nom'] = trim((string)($_POST['nom'] ?? ''));
    $old['email'] = strtolower(trim((string)($_POST['email'] ?? '')));
    $old['type'] = (string)($_POST['type'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    if (mb_strlen($old['nom']) < 2 || mb_strlen($old['nom']) > 80) $errors[] = 'Entrez votre nom (2 à 80 caractères).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 190) $errors[] = 'Adresse email invalide.';
    if (strlen($pass) < 8) $errors[] = 'Mot de passe : 8 caractères minimum.';
    if (!in_array($old['type'], ['agriculture', 'elevage', 'mixte'], true)) $errors[] = "Choisissez votre type d'exploitation.";
    if (empty($_POST['consent'])) $errors[] = 'Acceptez le traitement de vos données pour continuer.';
    if (!$errors && attempts_count('#reg', client_ip(), 60) >= 5) $errors[] = 'Trop de créations de compte. Réessayez plus tard.';
    if (!$errors) {
        $x = db()->prepare('SELECT id FROM users WHERE email = ?');
        $x->execute([$old['email']]);
        if ($x->fetch()) {
            $errors[] = 'Un compte existe déjà avec cet email.';
        } else {
            attempt_log('#reg', client_ip());
            $uid = create_user($old['nom'], $old['email'], password_hash($pass, PASSWORD_DEFAULT), $old['type']);
            session_regenerate_id(true);
            $_SESSION['uid'] = $uid;
            redirect('dashboard.php');
        }
    }
}
$pageTitle = 'Créer un compte'; $auth = true;
require __DIR__ . '/includes/header.php';
$types = ['agriculture' => ['bi-flower1', 'Agriculture'], 'elevage' => ['bi-egg-fried', 'Élevage'], 'mixte' => ['bi-tree', 'Mixte']];
?>
<div class="af-auth">
  <section class="af-art">
    <div class="af-word">AgriElv</div>
    <?= robot_svg('a', 'robot-xl') ?>
  </section>
  <section class="af-formside">
    <form method="post" class="af-card" novalidate id="authForm">
      <?= csrf_field() ?>
      <svg class="af-leaf" viewBox="0 0 64 48" aria-hidden="true"><path d="M30 44 C14 42 4 30 6 14 C22 12 34 22 30 44Z" fill="#0d6a2f"/><path d="M34 44 C32 26 42 10 60 4 C62 24 52 40 34 44Z" fill="#14803a"/></svg>
      <h1 class="af-title">Bienvenue sur AgriElv</h1>
      <?php if ($errors): ?><div class="alert alert-danger rounded-3 py-2" role="alert" id="authError"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
      <label class="af-label" for="nom">Nom complet</label>
      <div class="af-field"><i class="bi bi-person"></i><input id="nom" name="nom" value="<?= e($old['nom']) ?>" placeholder="Entrez votre nom et prénom" autocomplete="name" maxlength="80" required></div>
      <label class="af-label" for="email">Adresse e-mail</label>
      <div class="af-field"><i class="bi bi-envelope"></i><input id="email" name="email" type="email" value="<?= e($old['email']) ?>" placeholder="exemple@domaine.com" autocomplete="email" required></div>
      <label class="af-label" for="password">Mot de passe</label>
      <div class="af-field"><i class="bi bi-lock"></i><input id="password" name="password" type="password" minlength="8" placeholder="Créez un mot de passe" autocomplete="new-password" required><button class="af-eye" type="button" aria-label="Afficher le mot de passe"><i class="bi bi-eye"></i></button></div>
      <div class="af-label">Type d'exploitation</div>
      <div class="af-types">
        <?php foreach ($types as $val => [$ic, $lab]): ?>
          <div class="af-type"><input type="radio" name="type" id="t-<?= $val ?>" value="<?= $val ?>" <?= $old['type'] === $val ? 'checked' : '' ?>><label for="t-<?= $val ?>"><i class="bi <?= $ic ?>"></i> <?= $lab ?></label></div>
        <?php endforeach; ?>
      </div>
      <div class="form-check af-consent">
        <input class="form-check-input" type="checkbox" name="consent" id="consent" value="1" required>
        <label class="form-check-label" for="consent">J'accepte que mes données servent au fonctionnement de l'application.</label>
      </div>
      <button class="af-btn">S'inscrire</button>
      <a class="af-google" href="google.php"><svg viewBox="0 0 48 48" width="22" height="22" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.5l6.7-6.7C35.7 2.4 30.2 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.8 6.1C12.3 13.5 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4.1 7.1-10.1 7.1-17.5z"/><path fill="#FBBC05" d="M10.4 28.7A14.5 14.5 0 0 1 9.5 24c0-1.6.3-3.2.9-4.7l-7.8-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.6 10.8l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.5-5.8c-2.1 1.4-4.9 2.3-8.4 2.3-6.3 0-11.7-4-13.6-9.8l-7.8 6.1C6.5 42.6 14.6 48 24 48z"/></svg> Continuer avec Google</a>
      <div class="af-sep"><span>Déjà un compte ? <a href="login.php">Se connecter</a></span></div>
    </form>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
