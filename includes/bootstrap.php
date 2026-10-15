<?php
// ============================================================
// AgriElv Smart AI - Socle commun : sécurité, session, BDD, outils
// ============================================================
declare(strict_types=1);
require_once __DIR__ . '/../config/env.php';

$isProd = env('APP_ENV', 'production') === 'production';
ini_set('display_errors', $isProd ? '0' : '1');
error_reporting(E_ALL);
date_default_timezone_set('Africa/Douala');

// En-têtes de sécurité (anti clickjacking, anti sniffing, CSP)
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: blob: https: http:; media-src 'self' blob: https: http:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

// Session sécurisée (cookie HttpOnly + SameSite)
session_name('agrielv_sid');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
]);
session_start();
// Expiration après 30 minutes d'inactivité
if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 1800) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last'] = time();

// ---------- Base de données (PDO + requêtes préparées) ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), env('DB_NAME', 'agrielv'));
        try {
            $pdo = new PDO($dsn, (string)env('DB_USER', 'root'), (string)env('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Service indisponible. Vérifiez la configuration de la base de données.');
        }
    }
    return $pdo;
}

// ---------- Outils ----------
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function type_label(string $t): string
{
    return ['agriculture' => 'Agriculture', 'elevage' => 'Élevage', 'mixte' => 'Mixte'][$t] ?? 'Agriculture';
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): bool
{
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf'] ?? '', $t);
}
function csrf_or_die(bool $json = false): void
{
    if (csrf_verify()) return;
    if ($json) json_out(['error' => 'Session expirée. Rechargez la page.'], 419);
    http_response_code(419);
    exit('Session expirée. Rechargez la page puis réessayez.');
}

// ---------- Messages flash ----------
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['t' => $type, 'm' => $msg]; }
function pull_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ---------- Authentification ----------
function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $s = db()->prepare('SELECT id, nom, email, type_exploitation, role, is_active FROM users WHERE id = ?');
            $s->execute([(int)$_SESSION['uid']]);
            $u = $s->fetch() ?: null;
            if ($u && !(int)$u['is_active']) { $u = null; unset($_SESSION['uid']); }   // compte suspendu
        }
    }
    return $u;
}
function require_login(bool $json = false): array
{
    $u = current_user();
    if (!$u) {
        if ($json) json_out(['error' => 'Non connecté'], 401);
        redirect('login.php');
    }
    return $u;
}
function user_settings(int $uid): array
{
    $pdo = db();
    $pdo->prepare('INSERT IGNORE INTO settings (user_id) VALUES (?)')->execute([$uid]);
    $s = $pdo->prepare('SELECT alert_temp_max, alert_humidity_min, alert_motion, theme, auto_irrigation, ventilation FROM settings WHERE user_id = ?');
    $s->execute([$uid]);
    $r = $s->fetch();
    return [
        'alert_temp_max' => (float)$r['alert_temp_max'],
        'alert_humidity_min' => (int)$r['alert_humidity_min'],
        'alert_motion' => (int)$r['alert_motion'],
        'theme' => $r['theme'],
        'auto_irrigation' => (int)$r['auto_irrigation'],
        'ventilation' => (int)$r['ventilation'],
    ];
}

// ---------- Anti brute-force ----------
function attempts_count(string $email, string $ip, int $minutes): int
{
    $s = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE (ip = ? OR email = ?) AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)');
    $s->execute([$ip, $email, $minutes]);
    return (int)$s->fetchColumn();
}
function attempt_log(string $email, string $ip): void
{
    db()->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')->execute([$email, $ip]);
    db()->exec('DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
}

// ---------- Librairies front : version locale si présente, sinon CDN de secours ----------
function lib(string $local, string $cdn): string
{
    return is_file(__DIR__ . '/../' . $local) ? $local : $cdn;
}

// ---------- Administration & création de comptes ----------
function require_admin(bool $json = false): array
{
    $u = require_login($json);
    if ($u['role'] !== 'admin') {
        if ($json) json_out(['error' => 'Accès refusé'], 403);
        http_response_code(403);
        exit('Accès refusé.');
    }
    return $u;
}

/** Crée 3 zones par défaut (A, B, C) selon le type d'exploitation */
function ensure_zones(int $uid, string $type): void
{
    $c = db()->prepare('SELECT COUNT(*) FROM zones WHERE user_id = ?');
    $c->execute([$uid]);
    if ((int)$c->fetchColumn() > 0) return;
    $crops = ['agriculture' => ['Cacao', 'Manioc', 'Maïs'], 'elevage' => ['Volailles', 'Porcs', 'Bovins'], 'mixte' => ['Cacao', 'Volailles', 'Maïs']][$type] ?? ['Cultures', 'Cultures', 'Cultures'];
    $ins = db()->prepare('INSERT INTO zones (user_id, name, culture) VALUES (?,?,?)');
    foreach (['Zone A', 'Zone B', 'Zone C'] as $i => $z) $ins->execute([$uid, $z, $crops[$i]]);
}

/** Crée un utilisateur (inscription classique ou Google). Le mail ADMIN_EMAIL devient admin. */
function create_user(string $nom, string $email, string $hash, string $type): int
{
    $isAdmin = ($a = strtolower((string)env('ADMIN_EMAIL', ''))) !== '' && $a === strtolower($email);
    db()->prepare('INSERT INTO users (nom, email, password_hash, type_exploitation, role) VALUES (?,?,?,?,?)')
        ->execute([$nom, $email, $hash, $type, $isAdmin ? 'admin' : 'user']);
    $uid = (int)db()->lastInsertId();
    user_settings($uid);
    ensure_zones($uid, $type);
    return $uid;
}
