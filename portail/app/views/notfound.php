<?php
/** @var array|null $c  (client chargé mais invalide, montré à l'admin seulement) */
view('_top', ['title' => 'Introuvable', 'body_class' => 'page-gate']);
?>
<main class="gate">
  <div class="gate-card">
    <div class="logo-client">?</div>
    <?php if ($c !== null && is_admin()): ?>
      <div>
        <p class="eyebrow">Dossier <?= h($c['slug']) ?></p>
        <h1>Ce dossier ne peut pas être publié</h1>
      </div>
      <ul class="errors">
        <?php foreach ($c['errors'] as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
      <a class="btn" href="/admin">Retour à la gestion</a>
    <?php else: ?>
      <div>
        <p class="eyebrow">Introuvable</p>
        <h1>Cette page n'existe pas</h1>
      </div>
      <p class="muted">Vérifiez le lien que vous avez reçu.</p>
      <a class="btn" href="<?= h(OWNER_URL) ?>"><?= h(preg_replace('#^https?://#', '', OWNER_URL)) ?></a>
    <?php endif; ?>
    <p class="byline"><?= h(SITE_NAME) ?> · <?= h(SITE_TAGLINE) ?></p>
  </div>
</main>
<?php view('_bottom'); ?>
