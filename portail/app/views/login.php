<?php
/** @var array $c  @var string|null $error */
view('_top', ['title' => $c['nom'], 'c' => $c, 'body_class' => 'page-gate']);
?>
<main class="gate">
  <div class="gate-card">
    <?php if ($c['logo'] !== null): ?>
      <img class="logo-client img" src="/<?= h($c['slug']) ?>/media/<?= rawurlencode($c['logo']) ?>" alt="">
    <?php else: ?>
      <div class="logo-client"><?= h(initials($c['nom'])) ?></div>
    <?php endif; ?>
    <div>
      <p class="eyebrow">Vos vidéos</p>
      <h1><?= h($c['nom']) ?></h1>
    </div>

    <?php if ($error === 'expired'): ?>
      <p class="notice">L'accès à cet espace est terminé.<?php if (WHATSAPP !== ''): ?> Écrivez-moi si vous avez besoin de vos fichiers.<?php endif; ?></p>
      <?php if (WHATSAPP !== ''): ?>
        <a class="btn" href="<?= h(whatsapp_url('Bonjour Nathan, je voudrais récupérer les vidéos de ' . $c['nom'] . '.')) ?>" target="_blank" rel="noopener">Écrire sur WhatsApp</a>
      <?php endif; ?>
    <?php elseif ($error === 'locked'): ?>
      <p class="notice">Trop d'essais. Réessayez dans <?= (int) LOGIN_LOCK_MIN ?> minutes.</p>
    <?php else: ?>
      <form method="post" action="/<?= h($c['slug']) ?>" autocomplete="off">
        <input id="mdp" name="mdp" type="password" placeholder="Mot de passe" aria-label="Mot de passe"
               autocomplete="current-password" autofocus required<?= $error === 'wrong' ? ' class="is-wrong"' : '' ?>>
        <?php if ($error === 'wrong'): ?><p class="field-error">Ce n'est pas le bon mot de passe.</p><?php endif; ?>
        <button class="btn primary" type="submit">Entrer</button>
      </form>
    <?php endif; ?>

    <p class="byline"><?= h(SITE_NAME) ?> · <?= h(SITE_TAGLINE) ?></p>
  </div>
</main>
<?php view('_bottom'); ?>
