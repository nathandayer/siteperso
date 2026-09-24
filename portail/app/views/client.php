<?php
/** @var array $c  @var array $videos  @var array $groups */
view('_top', ['title' => $c['nom'] . ' · Vos vidéos', 'c' => $c, 'body_class' => 'page-client']);
$base = '/' . h($c['slug']);
$total = count($videos);
$nNew  = count($groups['new']);
$host = host_name();
$feed_webcal = 'webcal://' . $host . '/' . $c['slug'] . '/calendrier/' . calendar_token($c) . '.ics';
$feed_https  = client_url($c['slug']) . '/calendrier/' . calendar_token($c) . '.ics';
$has_dates = false;
foreach ($videos as $v) { if ($v['date'] !== null) { $has_dates = true; break; } }
?>
<main class="wrap">
  <header class="top">
    <div class="who">
      <?php if ($c['logo'] !== null): ?>
        <img class="logo-client img" src="<?= $base ?>/media/<?= rawurlencode($c['logo']) ?>" alt="">
      <?php else: ?>
        <div class="logo-client"><?= h(initials($c['nom'])) ?></div>
      <?php endif; ?>
      <div>
        <h1><?= h($c['nom']) ?></h1>
        <p class="sub">
          <?php if ($total === 0): ?>Aucune vidéo pour le moment
          <?php elseif ($nNew > 0): ?><?= $nNew ?> nouvelle<?= $nNew > 1 ? 's' : '' ?> vidéo<?= $nNew > 1 ? 's' : '' ?> · <?= $total ?> au total
          <?php else: ?><?= $total ?> vidéo<?= $total > 1 ? 's' : '' ?><?php endif; ?>
        </p>
      </div>
    </div>
    <div class="top-actions">
      <?php if ($has_dates): ?>
        <a class="btn small ghost" id="cal-link" href="<?= h($feed_webcal) ?>" data-https="<?= h($feed_https) ?>" data-log="calendar" title="S'abonner au calendrier des publications">
          <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
          <span class="only-wide">Calendrier</span>
        </a>
      <?php endif; ?>
      <?php if ($total > 0): ?>
        <a class="btn small ghost" href="<?= $base ?>/zip" title="Tout télécharger en un zip">
          <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
          <span class="only-wide">Tout télécharger</span>
        </a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($total === 0): ?>
    <div class="empty">
      <p>Vos vidéos apparaîtront ici dès qu'elles seront prêtes.</p>
    </div>
  <?php endif; ?>

  <?php if ($groups['new'] !== []): ?>
    <div class="section-h"><h2>Nouveautés</h2></div>
    <div class="list">
      <?php foreach ($groups['new'] as $v) view('_card', ['c' => $c, 'v' => $v, 'is_new' => true]); ?>
    </div>
  <?php endif; ?>

  <?php if ($groups['recent'] !== []): ?>
    <div class="section-h"><h2><?= $groups['new'] !== [] ? 'Récentes' : 'Vos vidéos' ?></h2></div>
    <div class="list">
      <?php foreach ($groups['recent'] as $v) view('_card', ['c' => $c, 'v' => $v, 'is_new' => false]); ?>
    </div>
  <?php endif; ?>

  <?php if ($groups['old'] !== []): ?>
    <?php if ($groups['new'] === [] && $groups['recent'] === []): ?>
      <div class="section-h"><h2>Vos vidéos</h2></div>
      <div class="list">
        <?php foreach ($groups['old'] as $v) view('_card', ['c' => $c, 'v' => $v, 'is_new' => false]); ?>
      </div>
    <?php else: ?>
      <details class="archive">
        <summary>
          <span>Précédentes · <?= count($groups['old']) ?> vidéo<?= count($groups['old']) > 1 ? 's' : '' ?></span>
          <svg class="i chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
        </summary>
        <?php foreach ($groups['old'] as $v): ?>
          <div class="arch-row">
            <?php if ($v['thumb'] !== null): ?>
              <img class="thumb" src="<?= $base ?>/media/<?= rawurlencode($v['thumb']) ?>" alt="">
            <?php else: ?>
              <div class="thumb placeholder"></div>
            <?php endif; ?>
            <div>
              <div class="t"><?= h($v['titre']) ?></div>
              <div class="d">Livrée le <?= h(fr_date($v['mtime'], false, false)) ?></div>
            </div>
            <div class="arch-actions">
              <?php if ($v['description'] !== ''): ?>
                <button class="btn small ghost" type="button" data-copy-text="<?= h($v['description']) ?>" data-file="<?= h($v['file']) ?>">Description</button>
              <?php endif; ?>
              <a class="btn small ghost" href="<?= $base ?>/media/<?= rawurlencode($v['file']) ?>?dl=1" download>Télécharger</a>
            </div>
          </div>
        <?php endforeach; ?>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <footer class="foot">
    <span class="sig"><?= h(SITE_NAME) ?> · <?= h(SITE_TAGLINE) ?></span>
    <div class="foot-actions">
      <?php if (WHATSAPP !== ''): ?>
        <a class="btn small" href="<?= h(whatsapp_url('Bonjour Nathan, question au sujet des vidéos de ' . $c['nom'] . ' :')) ?>" target="_blank" rel="noopener">
          <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 01-13.5 7.8L3 21l1.2-4.5A9 9 0 1121 12z"/></svg>
          Une question ? WhatsApp
        </a>
      <?php endif; ?>
      <a class="quiet" href="<?= $base ?>/sortir">Se déconnecter</a>
    </div>
  </footer>
</main>

<div class="lb" id="lb" role="dialog" aria-modal="true" aria-label="Vignette" hidden>
  <button class="btn close" type="button" id="lb-close" aria-label="Fermer">
    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
  </button>
  <div class="box">
    <img id="lb-img" src="" alt="Vignette">
    <div class="t" id="lb-title"></div>
    <div class="acts">
      <button class="btn primary share-btn" type="button" id="lb-share" hidden data-type="image/jpeg"><span>Enregistrer dans Photos</span></button>
      <a class="btn primary dl-btn" id="lb-dl" href="#" download>Télécharger</a>
      <button class="btn ghost" type="button" id="lb-close-2">Fermer</button>
    </div>
  </div>
</div>
<?php view('_bottom'); ?>
