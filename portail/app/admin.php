<?php
declare(strict_types=1);

/*
 * Page de gestion : /admin
 * Lecture seule. Les modifications se font dans le dossier Livraisons sur le PC.
 */

$action = $parts[1] ?? '';
$arg    = $parts[2] ?? '';

if ($action === 'sortir') {
    admin_logout();
    redirect('/admin');
}

if ($method === 'POST' && isset($_POST['mdp'])) {
    if (is_locked()) {
        view('admin_login', ['error' => 'locked']);
        exit;
    }
    $given = (string) $_POST['mdp'];
    if (ADMIN_PASSWORD !== 'change-moi' && $given !== '' && hash_equals(ADMIN_PASSWORD, $given)) {
        clear_fails();
        admin_login();
        redirect('/admin');
    }
    register_fail();
    view('admin_login', ['error' => ADMIN_PASSWORD === 'change-moi' ? 'unset' : 'wrong']);
    exit;
}

if (!is_admin()) {
    view('admin_login', ['error' => ADMIN_PASSWORD === 'change-moi' ? 'unset' : null]);
    exit;
}

if ($action === 'voir' && $arg !== '') {
    redirect('/' . rawurlencode($arg));
}

/* ---------- collecte ---------- */

$rows = $alerts = $upcoming = $journal = [];
$totalVideos = 0;
$activeClients = 0;
$now = time();
$dayStart = strtotime('today');
$weekEnd  = $dayStart + 7 * 86400;

foreach (list_client_dirs() as $slug) {
    $c = load_client($slug);
    if ($c === null) continue;
    $videos = $c['ok'] ? load_videos($c) : [];
    $act = client_activity($slug, $videos);
    $n = count($videos);
    $totalVideos += $n;
    $expired = client_expired($c);
    if ($c['ok'] && !$expired) $activeClients++;

    $nAlerts = 0;
    foreach ($c['errors'] as $e) {
        $alerts[] = ['level' => 'bad', 'text' => $e, 'where' => $slug, 'fix' => 'Corriger le dossier'];
        $nAlerts++;
    }
    $lastDelivery = null;
    $old = 0;
    foreach ($videos as $v) {
        $lastDelivery = max($lastDelivery ?? 0, $v['mtime']);
        if ($now - $v['mtime'] > RETENTION_DAYS * 86400) $old++;
        foreach ($v['warnings'] as $w) {
            $alerts[] = ['level' => $v['playable'] ? 'warn' : 'bad', 'text' => $w, 'where' => $slug . ' / ' . $v['base'], 'fix' => ''];
            $nAlerts++;
        }
        if ($v['date'] !== null && $v['date'] >= $dayStart && $v['date'] < $weekEnd) {
            $upcoming[] = ['ts' => $v['date'], 'has_time' => $v['date_has_time'], 'titre' => $v['titre'], 'client' => $c['nom'], 'slug' => $slug];
        }
    }
    if ($old > 0) {
        $alerts[] = ['level' => 'info', 'text' => $old . ' vidéo' . ($old > 1 ? 's' : '') . ' de plus de ' . round(RETENTION_DAYS / 30) . ' mois', 'where' => $slug, 'fix' => 'À supprimer du dossier'];
        $nAlerts++;
    }
    if ($expired && $act['last_visit'] !== null && $act['last_visit'] > $c['expire']) {
        $alerts[] = ['level' => 'info', 'text' => 'Le client a essayé d\'ouvrir sa page après expiration', 'where' => $slug, 'fix' => 'Prolonger « expire: » ?'];
        $nAlerts++;
    }
    if ($act['fails_24h'] >= 3) {
        $alerts[] = ['level' => 'warn', 'text' => $act['fails_24h'] . ' mots de passe refusés en 24 h', 'where' => $slug, 'fix' => 'Renvoyer l\'invitation ?'];
        $nAlerts++;
    }
    // vignettes ou textes sans vidéo
    if ($c['ok']) {
        $bases = array_column($videos, 'base');
        foreach (scandir($c['dir']) ?: [] as $f) {
            if ($f[0] === '.' || $f[0] === '_' || !is_file($c['dir'] . '/' . $f)) continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $b = pathinfo($f, PATHINFO_FILENAME);
            if (strtolower($f) === 'client.txt' || in_array(strtolower($f), LOGO_NAMES, true)) continue;
            if (in_array($ext, VIDEO_EXT, true)) continue;
            if ((in_array($ext, IMAGE_EXT, true) || $ext === 'txt') && !in_array($b, $bases, true)) {
                $alerts[] = ['level' => 'info', 'text' => 'Fichier « ' . $f . ' » sans vidéo du même nom', 'where' => $slug, 'fix' => 'Vidéo manquante ou nom différent'];
                $nAlerts++;
            }
        }
    }

    foreach (read_log($slug, 30) as $e) {
        $e['slug'] = $slug;
        $e['client'] = $c['nom'];
        $journal[] = $e;
    }

    $rows[] = [
        'c' => $c, 'n' => $n, 'ok' => $c['ok'], 'expired' => $expired,
        'last_delivery' => $lastDelivery, 'last_visit' => $act['last_visit'],
        'downloaded' => $act['downloaded'], 'size' => dir_size($c['dir']), 'alerts' => $nAlerts,
        'invite' => str_replace(['{nom}', '{url}', '{mdp}'], [$c['nom'], client_url($slug), $c['mdp']], INVITE_TEMPLATE),
    ];
}

usort($upcoming, fn($a, $b) => $a['ts'] <=> $b['ts']);
usort($journal, fn($a, $b) => $b['ts'] <=> $a['ts']);
// regroupe les répétitions (10 mots de passe refusés = une ligne « ×10 »), ignore les simples consultations
$compact = [];
foreach ($journal as $e) {
    if ($e['event'] === 'view' || $e['event'] === 'welcome') continue;
    $last = $compact !== [] ? $compact[count($compact) - 1] : null;
    if ($last && $last['slug'] === $e['slug'] && $last['event'] === $e['event'] && $last['detail'] === $e['detail'] && $last['ts'] - $e['ts'] < 3600) {
        $compact[count($compact) - 1]['count']++;
        continue;
    }
    $e['count'] = 1;
    $compact[] = $e;
}
$journal = array_slice($compact, 0, 40);
usort($rows, fn($a, $b) => ($b['last_delivery'] ?? 0) <=> ($a['last_delivery'] ?? 0));

$storage = dir_size(CLIENTS_DIR);
$quota   = DISK_QUOTA_GB * 1e9;
if ($storage > 0.8 * $quota) {
    array_unshift($alerts, ['level' => 'warn', 'text' => 'Stockage à ' . round(100 * $storage / $quota) . ' %', 'where' => 'hébergement', 'fix' => 'Supprimer les vieilles vidéos']);
}
$sync = last_sync();
if ($sync === null) {
    array_unshift($alerts, ['level' => 'info', 'text' => 'Aucune synchronisation reçue pour le moment', 'where' => 'PC', 'fix' => 'Lancer install-windows.ps1']);
} elseif ($now - $sync > 36 * 3600) {
    array_unshift($alerts, ['level' => 'warn', 'text' => 'Dernière synchro ' . fr_ago($sync), 'where' => 'PC', 'fix' => 'PC éteint ou tâche planifiée arrêtée ?']);
}
$levelOrder = ['bad' => 0, 'warn' => 1, 'info' => 2];
usort($alerts, fn($a, $b) => $levelOrder[$a['level']] <=> $levelOrder[$b['level']]);

// publications de la semaine, regroupées par jour
$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = $dayStart + $i * 86400;
    $days[date('Y-m-d', $d)] = ['ts' => $d, 'items' => []];
}
foreach ($upcoming as $u) {
    $k = date('Y-m-d', $u['ts']);
    if (isset($days[$k])) $days[$k]['items'][] = $u;
}

view('admin', [
    'rows' => $rows, 'alerts' => $alerts, 'days' => $days, 'journal' => $journal,
    'kpi' => [
        'clients' => $activeClients, 'videos' => $totalVideos, 'week' => count($upcoming),
        'storage' => $storage, 'quota' => $quota, 'sync' => $sync,
    ],
]);
