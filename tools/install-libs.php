<?php
// Télécharge Bootstrap 5.3.3 et Bootstrap Icons 1.11.3 en local (assets/vendor).
// Usage (une seule fois, avec internet) :  php tools/install-libs.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__) . '/assets/vendor';
$cdn = 'https://cdn.jsdelivr.net/npm/';
$files = [
    'bootstrap@5.3.3/dist/css/bootstrap.min.css' => 'bootstrap/css/bootstrap.min.css',
    'bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' => 'bootstrap-icons/font/bootstrap-icons.min.css',
    'bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2' => 'bootstrap-icons/font/fonts/bootstrap-icons.woff2',
    'bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff' => 'bootstrap-icons/font/fonts/bootstrap-icons.woff',
];
$ok = true;
foreach ($files as $remote => $local) {
    $dest = "$root/$local";
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    $data = @file_get_contents($cdn . $remote);
    if ($data === false || strlen($data) < 1000) { echo "ECHEC  $remote\n"; $ok = false; continue; }
    file_put_contents($dest, $data);
    echo "OK     $local (" . round(strlen($data) / 1024) . " Ko)\n";
}
echo $ok ? "\nTerminé : les librairies sont maintenant locales.\n" : "\nErreur : vérifiez votre connexion et relancez.\n";
exit($ok ? 0 : 1);
