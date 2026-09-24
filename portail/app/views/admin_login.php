<?php
/** @var string|null $error */
view('_top', ['title' => 'Gestion', 'body_class' => 'page-gate']);
?>
<main class="gate">
  <div class="gate-card">
    <div class="logo-client"><?= h(initials(SITE_NAME)) ?></div>
    <div>
      <p class="eyebrow">Espace clients</p>
      <h1>Gestion</h1>
    </div>
    <?php if ($error === 'unset'): ?>
      <p class="notice">Le mot de passe admin n'est pas encore défini. Modifie ADMIN_PASSWORD dans app/config.php.</p>
    <?php elseif ($error === 'locked'): ?>
      <p class="notice">Trop d'essais. Réessaie dans <?= (int) LOGIN_LOCK_MIN ?> minutes.</p>
    <?php else: ?>
      <form method="post" action="/admin" autocomplete="off">
        <input id="mdp" name="mdp" type="password" placeholder="Mot de passe" aria-label="Mot de passe" autofocus required<?= $error === 'wrong' ? ' class="is-wrong"' : '' ?>>
        <?php if ($error === 'wrong'): ?><p class="field-error">Mauvais mot de passe.</p><?php endif; ?>
        <button class="btn primary" type="submit">Entrer</button>
      </form>
    <?php endif; ?>
    <p class="byline"><?= h(SITE_NAME) ?></p>
  </div>
</main>
<?php view('_bottom'); ?>
