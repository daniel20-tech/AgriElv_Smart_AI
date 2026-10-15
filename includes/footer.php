<?php $auth = $auth ?? false; ?>
<?php if (!$auth): ?>
  </main>
</div>
<nav class="tabbar" aria-label="Navigation mobile">
  <?php foreach ($nav as [$href, $icon, $label, $key]): ?>
    <a href="<?= $href ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>" aria-label="<?= e($label) ?>"><i class="bi <?= $icon ?>"></i></a>
  <?php endforeach; ?>
</nav>
<?php if (!empty($guide)): ?>
<aside class="guide" id="guide" hidden data-page="<?= e($active ?? '') ?>" data-steps="<?= e(json_encode($guide, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="guide-bubble" id="guideBubble" role="status">
    <button class="guide-x" id="guideX" type="button" aria-label="Fermer le guide">&times;</button>
    <p id="guideText"></p>
    <div class="guide-nav"><button type="button" id="gPrev" aria-label="Précédent"><i class="bi bi-chevron-left"></i></button><span id="gDots"></span><button type="button" id="gNext" class="gnext">Suivant <i class="bi bi-chevron-right"></i></button></div>
  </div>
  <button class="guide-bot" id="guideBot" type="button" aria-label="Armel, votre guide"><?= robot_svg('guide', 'robot-xs') ?></button>
</aside>
<?php endif; ?>
<?php endif; ?>
<script src="assets/js/app.js" defer></script>
<?php foreach (($extraJs ?? []) as $js): ?><script src="assets/js/<?= e($js) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
