<?php
/** @var array $c */
view('_top', ['title' => 'Bienvenue · ' . $c['nom'], 'c' => $c, 'body_class' => 'page-welcome']);
$host = host_name();
$feed_https = client_url($c['slug']) . '/calendrier/' . calendar_token($c) . '.ics';
$feed_webcal = 'webcal://' . $host . '/' . $c['slug'] . '/calendrier/' . calendar_token($c) . '.ics';
?>
<main class="wrap narrow">
  <div class="welcome-head">
    <?php if ($c['logo'] !== null): ?>
      <img class="logo-client img" src="/<?= h($c['slug']) ?>/media/<?= rawurlencode($c['logo']) ?>" alt="">
    <?php else: ?>
      <div class="logo-client"><?= h(initials($c['nom'])) ?></div>
    <?php endif; ?>
    <p class="eyebrow">Bienvenue</p>
    <h1>Un réglage, une seule fois.</h1>
    <p class="muted">Ensuite vous n'aurez plus jamais à y penser.</p>
  </div>

  <div class="step" id="step-cal">
    <div class="n">
      <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
    </div>
    <div>
      <h3>Ajoutez votre calendrier</h3>
      <p>Les dates de publication conseillées apparaissent dans votre agenda et se mettent à jour toutes seules quand de nouvelles vidéos arrivent.</p>
      <a class="btn primary" id="cal-link" href="<?= h($feed_webcal) ?>" data-https="<?= h($feed_https) ?>" data-log="calendar">S'abonner au calendrier</a>
      <p class="hint" id="cal-hint">Sur iPhone et Mac, un tap suffit. Sur Android, vous avez aussi le bouton Calendrier sous chaque vidéo.</p>
    </div>
  </div>

  <div class="welcome-actions">
    <a class="btn primary" href="/<?= h($c['slug']) ?>">Voir mes vidéos</a>
    <a class="btn ghost" href="/<?= h($c['slug']) ?>">Plus tard</a>
  </div>
</main>
<?php view('_bottom'); ?>
