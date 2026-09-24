<?php
/** @var array $c  @var array $v  @var bool $is_new */
$base   = '/' . h($c['slug']);
$media  = $base . '/media/' . rawurlencode($v['file']);
$thumb  = $v['thumb'] !== null ? $base . '/media/' . rawurlencode($v['thumb']) : null;
$mime   = MIME_BY_EXT[$v['ext']] ?? 'video/mp4';
$uid    = 'v' . substr(md5($v['file']), 0, 8);
?>
<article class="card" id="<?= $uid ?>">
  <div class="preview">
    <?php if ($v['playable']): ?>
      <video controls playsinline preload="<?= $thumb ? 'none' : 'metadata' ?>"<?= $thumb ? ' poster="' . $thumb . '"' : '' ?>
             src="<?= $media ?>" title="<?= h($v['titre']) ?>"></video>
    <?php else: ?>
      <?php if ($thumb): ?><img src="<?= $thumb ?>" alt="" class="frame-img"><?php endif; ?>
      <div class="unplayable">Aperçu indisponible pour ce format.<br>Téléchargez la vidéo pour la lire.</div>
    <?php endif; ?>
    <?php if ($is_new): ?><span class="new">Nouveau</span><?php endif; ?>
  </div>
  <div class="body">
    <div class="title">
      <?php if ($v['num'] !== ''): ?><span class="num"><?= h($v['num']) ?></span><?php endif; ?>
      <h3><?= h($v['titre']) ?></h3>
    </div>

    <?php if ($v['date'] !== null): ?>
      <?php $past = $v['date'] < time(); ?>
      <span class="when<?= $past ? ' past' : '' ?>">
        <?php if ($past): ?>
          <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
          Publication conseillée le <?= h(fr_date($v['date'], $v['date_has_time'], false)) ?>
        <?php else: ?>
          <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
          À publier <?= h(fr_date($v['date'], $v['date_has_time'])) ?>
        <?php endif; ?>
      </span>
    <?php endif; ?>

    <?php if ($v['description'] !== ''): ?>
      <div class="desc">
        <div class="row">
          <span class="eyebrow">Description</span>
          <button class="btn small" type="button" data-copy="<?= $uid ?>-d" data-file="<?= h($v['file']) ?>">Copier</button>
        </div>
        <pre id="<?= $uid ?>-d"><?= h($v['description']) ?></pre>
      </div>
    <?php endif; ?>

    <?php if ($v['conseil'] !== ''): ?>
      <div class="tip">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 21h4M12 3a6 6 0 00-4 10.5c.6.6 1 1.4 1 2.2V17h6v-1.3c0-.8.4-1.6 1-2.2A6 6 0 0012 3z"/></svg>
        <p><b>Conseil.</b> <?= nl2br(h($v['conseil'])) ?></p>
      </div>
    <?php endif; ?>

    <div class="actions">
      <button class="btn primary share-btn" type="button" hidden
              data-url="<?= $media ?>" data-dl="<?= $media ?>?dl=1" data-name="<?= h(download_name($c['slug'], $v['file'])) ?>"
              data-type="<?= h($mime) ?>" data-title="<?= h($v['titre']) ?>" data-file="<?= h($v['file']) ?>">
        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V3m0 0l-4 4m4-4l4 4M4 14v5a2 2 0 002 2h12a2 2 0 002-2v-5"/></svg>
        <span>Enregistrer dans Photos</span>
      </button>
      <a class="btn primary dl-btn" href="<?= $media ?>?dl=1" download="<?= h(download_name($c['slug'], $v['file'])) ?>">
        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
        <span>Télécharger la vidéo</span>
      </a>
      <?php if ($thumb): ?>
        <button class="btn" type="button" data-thumb="<?= $thumb ?>" data-dl="<?= $thumb ?>?dl=1"
                data-name="<?= h(download_name($c['slug'], $v['thumb'])) ?>" data-title="<?= h($v['titre']) ?>"
                data-num="<?= h($v['num']) ?>" data-file="<?= h($v['thumb']) ?>">Vignette</button>
      <?php endif; ?>
      <?php if ($v['date'] !== null && $v['date'] >= time()): ?>
        <a class="btn" href="<?= $base ?>/ics/<?= rawurlencode($v['base']) ?>" data-log="ics">Calendrier</a>
      <?php endif; ?>
    </div>
  </div>
</article>
