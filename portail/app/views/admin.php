<?php
/** @var array $rows  @var array $alerts  @var array $days  @var array $journal  @var array $kpi */
view('_top', ['title' => 'Gestion · ' . SITE_NAME, 'body_class' => 'page-admin']);
$pct = $kpi['quota'] > 0 ? min(100, round(100 * $kpi['storage'] / $kpi['quota'])) : 0;
?>
<main class="wrap wide">
  <header class="adm-top">
    <div>
      <p class="eyebrow">Espace clients</p>
      <h1>Gestion</h1>
    </div>
    <div class="adm-meta">
      <span class="muted">Synchro <?= h(fr_ago($kpi['sync'])) ?></span>
      <a class="quiet" href="/admin/sortir">Se déconnecter</a>
    </div>
  </header>

  <div class="kpis">
    <div class="kpi"><div class="v"><?= $kpi['clients'] ?></div><div class="l">Clients actifs</div></div>
    <div class="kpi"><div class="v"><?= $kpi['videos'] ?></div><div class="l">Vidéos en ligne</div></div>
    <div class="kpi"><div class="v"><?= $kpi['week'] ?></div><div class="l">Publications cette semaine</div></div>
    <div class="kpi"><div class="v"><?= h(human_size($kpi['storage'])) ?></div><div class="l">sur <?= (int) DISK_QUOTA_GB ?> Go</div><div class="bar"><i style="width:<?= $pct ?>%"></i></div></div>
  </div>

  <div class="section-h"><h2>À corriger</h2><span class="muted"><?= count($alerts) ?> point<?= count($alerts) > 1 ? 's' : '' ?></span></div>
  <?php if ($alerts === []): ?>
    <p class="muted">Rien à signaler.</p>
  <?php else: ?>
    <div class="alerts">
      <?php foreach ($alerts as $a): ?>
        <div class="alert <?= h($a['level']) ?>">
          <span class="dot"></span>
          <div><?= h($a['text']) ?> <div class="where"><?= h($a['where']) ?></div></div>
          <span class="fix"><?= h($a['fix']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="section-h"><h2>Clients</h2><span class="muted"><?= count($rows) ?> dossier<?= count($rows) > 1 ? 's' : '' ?></span></div>
  <?php if ($rows === []): ?>
    <p class="muted">Aucun dossier client reçu. Crée un dossier dans Livraisons sur ton PC.</p>
  <?php else: ?>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Client</th><th>Vidéos</th><th>Dernière livraison</th><th>Dernière visite</th><th>Récupéré</th><th>Accès</th><th>Mot de passe</th><th>Poids</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): $c = $r['c']; ?>
        <tr<?= $r['ok'] ? '' : ' class="row-bad"' ?>>
          <td>
            <div class="name"><?= h($c['nom']) ?><?php if ($r['alerts'] > 0): ?> <span class="badge"><?= $r['alerts'] ?></span><?php endif; ?></div>
            <div class="slug">/<?= h($c['slug']) ?></div>
          </td>
          <td class="n"><?= $r['n'] ?></td>
          <td><?= h(ucfirst_fr(fr_ago($r['last_delivery']))) ?></td>
          <td>
            <?php if ($r['last_visit'] === null): ?><span class="pill warn">Jamais</span>
            <?php elseif (time() - $r['last_visit'] < 7 * 86400): ?><span class="pill ok"><?= h(ucfirst_fr(fr_ago($r['last_visit']))) ?></span>
            <?php else: ?><span class="pill"><?= h(ucfirst_fr(fr_ago($r['last_visit']))) ?></span><?php endif; ?>
          </td>
          <td class="n"><?= $r['downloaded'] ?> / <?= $r['n'] ?></td>
          <td>
            <?php if (!$r['ok']): ?><span class="pill bad">Dossier invalide</span>
            <?php elseif ($r['expired']): ?><span class="pill bad">Expiré le <?= date('d.m.Y', $c['expire']) ?></span>
            <?php elseif ($c['expire'] !== null): ?><span class="pill">Jusqu'au <?= date('d.m.Y', $c['expire']) ?></span>
            <?php else: ?><span class="pill">Sans limite</span><?php endif; ?>
          </td>
          <td class="mdp"><?= h($c['mdp']) ?></td>
          <td class="n muted"><?= h(human_size($r['size'])) ?></td>
          <td>
            <div class="acts">
              <?php if ($r['ok']): ?>
                <button class="btn small" type="button" data-copy-text="<?= h($r['invite']) ?>" data-toast="Invitation copiée">Invitation</button>
                <a class="btn small ghost" href="/admin/voir/<?= h($c['slug']) ?>">Voir</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <p class="note">Les modifications se font dans le dossier Livraisons sur ton PC. Cette page lit seulement.</p>

  <div class="section-h"><h2>Publications à venir</h2><span class="muted">7 jours</span></div>
  <div class="week">
    <?php foreach ($days as $d): ?>
      <div class="day<?= $d['items'] === [] ? ' empty' : '' ?>">
        <div class="dn"><?= h(ucfirst_fr(FR_DAYS[(int) date('w', $d['ts'])])) ?><small><?= h(fr_date_short($d['ts'])) ?></small></div>
        <div class="items">
          <?php if ($d['items'] === []): ?>Rien de prévu<?php endif; ?>
          <?php foreach ($d['items'] as $u): ?>
            <div class="pub">
              <span class="h"><?= $u['has_time'] ? date('H:i', $u['ts']) : '—' ?></span>
              <span><?= h($u['titre']) ?></span>
              <span class="c">· <?= h($u['client']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="section-h"><h2>Journal</h2></div>
  <?php if ($journal === []): ?>
    <p class="muted">Aucune activité pour le moment.</p>
  <?php else: ?>
  <div class="log">
    <?php foreach ($journal as $e): ?>
      <div class="log-row">
        <span class="t"><?= h(ucfirst_fr(fr_ago($e['ts']))) ?></span>
        <span><b><?= h($e['client']) ?></b> <?= h(LOG_LABELS[$e['event']] ?? $e['event']) ?><?= $e['detail'] !== '' ? ' ' . h($e['detail']) : '' ?><?= $e['count'] > 1 ? ' <span class="muted">×' . $e['count'] . '</span>' : '' ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <details class="help">
    <summary>Rappel du format des fichiers</summary>
    <div class="help-grid">
      <div>
        <p class="eyebrow">client.txt</p>
<pre>nom: O'Grignou
mdp: terrasse26
expire: 31.03.2027</pre>
      </div>
      <div>
        <p class="eyebrow">01-terrasse.txt (à côté de 01-terrasse.mp4 et 01-terrasse.jpg)</p>
<pre>titre: La terrasse au coucher du soleil
date: 02.10.2026 18:00
description:
Le soleil se couche sur Sierre, la terrasse se remplit.

#sierre #valais #ogrignou
conseil: Postez en Reel, pas en story.</pre>
      </div>
    </div>
    <p class="muted">Dossier client en minuscules sans espace ni accent. Un fichier qui commence par _ est ignoré. Vidéos en mp4 H.264.</p>
  </details>
</main>
<?php view('_bottom'); ?>
