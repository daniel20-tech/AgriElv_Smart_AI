<?php
// Armel, le robot mascotte (SVG animé : flotte, cligne, bouge les antennes, salue, suit le curseur)
function robot_svg(string $id = 'r', string $class = ''): string
{
    $id = preg_replace('/[^a-z0-9]/i', '', $id);
    ob_start(); ?>
<svg class="robot tilt3d <?= e($class) ?>" viewBox="0 0 240 340" role="img" aria-label="Armel, l'assistant AgriElv" data-state="idle">
  <defs>
    <linearGradient id="hd<?= $id ?>" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#d8e1eb"/></linearGradient>
    <linearGradient id="bd<?= $id ?>" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#dde5ee"/></linearGradient>
    <radialGradient id="vz<?= $id ?>" cx=".5" cy=".35" r=".75"><stop offset="0" stop-color="#232b35"/><stop offset="1" stop-color="#090c10"/></radialGradient>
    <filter id="gl<?= $id ?>" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="3" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
  </defs>
  <ellipse class="r-shadow" cx="120" cy="326" rx="64" ry="9" fill="rgba(25,50,90,.18)"/>
  <g class="r-float">
    <g class="r-ant r-ant-l"><line x1="92" y1="80" x2="72" y2="30" stroke="#8e98a6" stroke-width="6" stroke-linecap="round"/><circle cx="70" cy="25" r="12" fill="#98a2b0"/><circle cx="66" cy="21" r="4.5" fill="#fff" opacity=".65"/></g>
    <g class="r-ant r-ant-r"><line x1="148" y1="80" x2="170" y2="30" stroke="#8e98a6" stroke-width="6" stroke-linecap="round"/><circle cx="172" cy="25" r="12" fill="#98a2b0"/><circle cx="168" cy="21" r="4.5" fill="#fff" opacity=".65"/></g>
    <g class="r-arm r-arm-l"><rect x="52" y="208" width="24" height="60" rx="12" fill="url(#bd<?= $id ?>)" stroke="#cfd8e3" stroke-width="2"/><circle cx="64" cy="274" r="12" fill="#58626f"/></g>
    <g class="r-arm r-arm-r"><rect x="164" y="208" width="24" height="60" rx="12" fill="url(#bd<?= $id ?>)" stroke="#cfd8e3" stroke-width="2"/><circle cx="176" cy="274" r="12" fill="#58626f"/></g>
    <rect x="88" y="284" width="26" height="28" rx="10" fill="#4b5563"/><rect x="126" y="284" width="26" height="28" rx="10" fill="#4b5563"/>
    <rect x="78" y="304" width="44" height="20" rx="10" fill="#6b7482"/><rect x="118" y="304" width="44" height="20" rx="10" fill="#6b7482"/>
    <rect x="76" y="196" width="88" height="92" rx="38" fill="url(#bd<?= $id ?>)" stroke="#cfd8e3" stroke-width="2"/>
    <g transform="translate(120 238)"><path d="M0 0 C-6 -14 -22 -16 -26 -8 C-20 2 -8 6 0 0Z" fill="#1f7a3a"/><path d="M2 0 C8 -18 22 -22 28 -12 C22 0 10 6 2 0Z" fill="#17632e"/></g>
    <text x="120" y="268" text-anchor="middle" font-size="17" font-weight="700" fill="#1f3b73" font-family="system-ui,sans-serif">Armel</text>
    <rect x="100" y="192" width="40" height="10" rx="5" fill="#3f4854"/>
    <rect x="36" y="118" width="20" height="42" rx="10" fill="#7d8896"/><rect x="39" y="124" width="7" height="30" rx="3.5" fill="#67e8ff" filter="url(#gl<?= $id ?>)"/>
    <rect x="184" y="118" width="20" height="42" rx="10" fill="#7d8896"/><rect x="194" y="124" width="7" height="30" rx="3.5" fill="#67e8ff" filter="url(#gl<?= $id ?>)"/>
    <rect class="r-head" x="48" y="76" width="144" height="122" rx="58" fill="url(#hd<?= $id ?>)" stroke="#cfd8e3" stroke-width="2"/>
    <rect x="62" y="98" width="116" height="78" rx="34" fill="url(#vz<?= $id ?>)"/>
    <g class="r-eyes">
      <g transform="translate(98 137)"><circle r="20" fill="#05080c" stroke="#67e8ff" stroke-width="6" filter="url(#gl<?= $id ?>)"/><g class="pupil"><circle r="11" fill="#020304"/><circle cx="4" cy="-5" r="3.6" fill="#fff"/></g></g>
      <g transform="translate(142 137)"><circle r="20" fill="#05080c" stroke="#67e8ff" stroke-width="6" filter="url(#gl<?= $id ?>)"/><g class="pupil"><circle r="11" fill="#020304"/><circle cx="4" cy="-5" r="3.6" fill="#fff"/></g></g>
    </g>
  </g>
</svg>
<?php
    return (string)ob_get_clean();
}

/** Vue simulée de la parcelle (3 zones colorées selon leur état) pour le centre du dashboard */
function field_svg(array $levels): string
{
    $l = fn($i) => preg_replace('/[^a-z]/', '', $levels[$i] ?? 'none');
    return '<svg class="field-svg" viewBox="0 0 640 400" role="img" aria-label="Vue simulée de la parcelle avec les zones A, B et C">'
    . '<defs><linearGradient id="fbg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2f6320"/><stop offset="1" stop-color="#123a11"/></linearGradient>'
    . '<pattern id="rows" width="14" height="14" patternUnits="userSpaceOnUse" patternTransform="rotate(-18)"><rect width="5" height="14" fill="rgba(0,0,0,.2)"/></pattern>'
    . '<linearGradient id="scanG" x1="0" x2="1"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#fff" stop-opacity=".25"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>'
    . '<rect width="640" height="400" fill="url(#fbg)"/><rect width="640" height="400" fill="url(#rows)"/>'
    . '<g class="trees" fill="#0c2c0c" opacity=".55"><circle cx="40" cy="60" r="26"/><circle cx="96" cy="30" r="20"/><circle cx="590" cy="330" r="30"/><circle cx="530" cy="368" r="22"/><circle cx="60" cy="350" r="24"/><circle cx="600" cy="200" r="18"/></g>'
    . '<g class="plots" stroke="rgba(255,255,255,.45)" stroke-width="2">'
    . '<polygon class="plot lvl-' . $l(0) . '" points="60,215 232,150 322,228 142,305"/><polygon points="60,215 232,150 322,228 142,305" fill="url(#rows)"/>'
    . '<polygon class="plot lvl-' . $l(1) . '" points="232,150 382,100 468,168 322,228"/><polygon points="232,150 382,100 468,168 322,228" fill="url(#rows)"/>'
    . '<polygon class="plot lvl-' . $l(2) . '" points="382,100 540,64 612,126 468,168"/><polygon points="382,100 540,64 612,126 468,168" fill="url(#rows)"/></g>'
    . '<g class="pings"><circle class="ping" cx="190" cy="232" r="5"/><circle class="ping p2" cx="350" cy="168" r="5"/><circle class="ping p3" cx="498" cy="118" r="5"/></g>'
    . '<rect class="scan" x="-120" y="0" width="120" height="400" fill="url(#scanG)"/></svg>';
}

/** Jauge semi-circulaire (indice de santé). L'animation est en CSS pur (variable --off). */
function gauge_svg(?int $score, string $id): string
{
    $len = 251.33;
    $off = $score === null ? $len : $len * (1 - $score / 100);
    $id = preg_replace('/[^a-z0-9]/i', '', $id);
    return '<svg class="gauge" viewBox="0 0 200 112" role="img" aria-label="Indice de santé ' . ($score ?? 0) . ' pour cent">'
        . '<defs><linearGradient id="gg' . $id . '" gradientUnits="userSpaceOnUse" x1="20" y1="0" x2="180" y2="0"><stop offset="0" stop-color="#ef8a2d"/><stop offset=".5" stop-color="#f3c623"/><stop offset="1" stop-color="#2fa84f"/></linearGradient></defs>'
        . '<path class="g-track" d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke-width="16" stroke-linecap="round"/>'
        . '<path class="g-val" style="--off:' . round($off, 2) . '" d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke="url(#gg' . $id . ')" stroke-width="16" stroke-linecap="round" stroke-dasharray="' . $len . '" stroke-dashoffset="' . $len . '"/></svg>';
}
