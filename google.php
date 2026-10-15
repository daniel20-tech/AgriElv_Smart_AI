<?php
// Connexion Google (OAuth 2.0, flux "code"). Actif seulement si GOOGLE_CLIENT_ID / SECRET sont dans .env
require __DIR__ . '/includes/bootstrap.php';
$cid = env('GOOGLE_CLIENT_ID'); $sec = env('GOOGLE_CLIENT_SECRET');
if (!$cid || !$sec || !function_exists('curl_init')) {
    flash('err', "La connexion Google n'est pas encore configurée. Utilisez votre email.");
    redirect('login.php');
}
$redirectUri = rtrim((string)env('APP_URL', ''), '/') . '/google.php';

function g_http(string $url, ?array $post = null, array $headers = []): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $out = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($out !== false && $code === 200) ? (json_decode($out, true) ?: null) : null;
}

if (!isset($_GET['code'])) {                       // 1) départ : redirection vers Google
    $_SESSION['g_state'] = bin2hex(random_bytes(16));
    redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $cid, 'redirect_uri' => $redirectUri, 'response_type' => 'code',
        'scope' => 'openid email profile', 'state' => $_SESSION['g_state'], 'prompt' => 'select_account',
    ]));
}
// 2) retour de Google : vérifier l'état anti-CSRF puis échanger le code
$state = (string)($_GET['state'] ?? '');
if ($state === '' || !hash_equals($_SESSION['g_state'] ?? '', $state)) { flash('err', 'Connexion Google refusée (état invalide).'); redirect('login.php'); }
unset($_SESSION['g_state']);
$tok = g_http('https://oauth2.googleapis.com/token', [
    'code' => (string)$_GET['code'], 'client_id' => $cid, 'client_secret' => $sec,
    'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code',
]);
$info = !empty($tok['access_token']) ? g_http('https://openidconnect.googleapis.com/v1/userinfo', null, ['Authorization: Bearer ' . $tok['access_token']]) : null;
if (!$info || empty($info['email']) || empty($info['email_verified'])) { flash('err', 'Impossible de valider votre compte Google. Réessayez.'); redirect('login.php'); }

$email = strtolower((string)$info['email']);
$q = db()->prepare('SELECT id, is_active FROM users WHERE email = ?');
$q->execute([$email]);
$u = $q->fetch();
if ($u) {
    if (!(int)$u['is_active']) { flash('err', "Ce compte est suspendu. Contactez l'administrateur."); redirect('login.php'); }
    $uid = (int)$u['id'];
} else {
    $nom = mb_substr(trim((string)($info['name'] ?? 'Utilisateur')) ?: 'Utilisateur', 0, 80);
    $uid = create_user($nom, $email, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), 'agriculture');
}
session_regenerate_id(true);
$_SESSION['uid'] = $uid;
redirect('dashboard.php');
