<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('dashboard.php');
$error = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_or_die();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $ip = client_ip();
    if (attempts_count($email, $ip, 15) >= 5) {
        $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } else {
        $q = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE email = ?');
        $q->execute([$email]);
        $u = $q->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            if (!(int)$u['is_active']) {
                $error = 'Ce compte est suspendu. Contactez l\'administrateur.';
            } else {
                db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                redirect('dashboard.php');
            }
        } else {
            attempt_log($email, $ip);
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
$pageTitle = 'Connexion'; $auth = true;
require __DIR__ . '/includes/header.php';
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
      <h1 class="af-title">Bon retour sur AgriElv</h1>
      <?php foreach (pull_flash() as $f): ?><div class="alert alert-<?= $f['t'] === 'ok' ? 'success' : 'danger' ?> rounded-3 py-2" role="alert"><?= e($f['m']) ?></div><?php endforeach; ?>
      <?php if ($error): ?><div class="alert alert-danger rounded-3 py-2" role="alert" id="authError"><?= e($error) ?></div><?php endif; ?>
      <label class="af-label" for="email">Adresse e-mail</label>
      <div class="af-field"><i class="bi bi-envelope"></i><input id="email" name="email" type="email" value="<?= e($email) ?>" placeholder="exemple@domaine.com" autocomplete="email" required autofocus></div>
      <label class="af-label" for="password">Mot de passe</label>
      <div class="af-field"><i class="bi bi-lock"></i><input id="password" name="password" type="password" placeholder="Votre mot de passe" autocomplete="current-password" required><button class="af-eye" type="button" aria-label="Afficher le mot de passe"><i class="bi bi-eye"></i></button></div>
      <button class="af-btn">Se connecter</button>
      <a class="af-google" href="google.php"><svg viewBox="0 0 48 48" width="22" height="22" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.5l6.7-6.7C35.7 2.4 30.2 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.8 6.1C12.3 13.5 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4.1 7.1-10.1 7.1-17.5z"/><path fill="#FBBC05" d="M10.4 28.7A14.5 14.5 0 0 1 9.5 24c0-1.6.3-3.2.9-4.7l-7.8-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.6 10.8l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.5-5.8c-2.1 1.4-4.9 2.3-8.4 2.3-6.3 0-11.7-4-13.6-9.8l-7.8 6.1C6.5 42.6 14.6 48 24 48z"/></svg> Continuer avec Google</a>
      <div class="af-sep"><span>Pas encore de compte ? <a href="register.php">S'inscrire</a></span></div>
    </form>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
