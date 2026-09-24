<?php
declare(strict_types=1);

/*
 * Routeur unique du portail.
 *
 *   /                            -> renvoie vers le site principal
 *   /admin…                      -> page de gestion (app/admin.php)
 *   /<slug>                      -> page du client (mot de passe si besoin)
 *   /<slug>/bienvenue            -> écran de première visite
 *   /<slug>/sortir               -> déconnexion
 *   /<slug>/media/<fichier>      -> vidéo, vignette ou logo (?dl=1 pour télécharger)
 *   /<slug>/zip                  -> tout en un zip
 *   /<slug>/ics/<base>           -> une publication au format calendrier
 *   /<slug>/calendrier/<jeton>.ics -> abonnement calendrier (sans cookie)
 *   /<slug>/log                  -> POST, événements côté navigateur (copie, partage…)
 */

// Serveur de développement : `php -S localhost:8000 -t public public/index.php`
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($f) && !str_ends_with($f, '.php')) {
        return false;
    }
}

require __DIR__ . '/../app/lib.php';

$path  = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$parts = $path === '' ? [] : array_map('rawurldecode', explode('/', $path));
$slug  = $parts[0] ?? '';
$action = $parts[1] ?? '';
$arg    = $parts[2] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($slug === '') {
    redirect(OWNER_URL, 302);
}

if ($slug === 'admin') {
    require __DIR__ . '/../app/admin.php';
    exit;
}

/* ---------- client ---------- */

$c = load_client($slug);
if ($c === null || (!$c['ok'] && !is_admin())) {
    http_response_code(404);
    view('notfound', ['c' => $c]);
    exit;
}
if (!$c['ok'] && is_admin()) {
    http_response_code(200);
    view('notfound', ['c' => $c]);      // l'admin voit pourquoi le dossier ne passe pas
    exit;
}

$videos = load_videos($c);

// Logo et flux calendrier : accessibles sans mot de passe
if ($action === 'media' && $c['logo'] !== null && $arg === $c['logo']) {
    require __DIR__ . '/../app/media.php';
    serve_media($c, $arg, false);
}
if ($action === 'calendrier') {
    if ($arg !== calendar_token($c) . '.ics') {
        http_response_code(404);
        exit('Calendrier introuvable.');
    }
    require __DIR__ . '/../app/ics.php';
    serve_ics_feed($c, $videos);
}

if ($action === 'sortir') {
    client_logout($c);
    redirect(client_url($c['slug']));
}

// Connexion
if ($method === 'POST' && $action === '' && isset($_POST['mdp'])) {
    if (client_expired($c)) {
        view('login', ['c' => $c, 'error' => 'expired']);
        exit;
    }
    if (is_locked()) {
        view('login', ['c' => $c, 'error' => 'locked']);
        exit;
    }
    $given = trim((string) $_POST['mdp']);
    if ($given !== '' && hash_equals($c['mdp'], $given)) {
        clear_fails();
        client_login($c);
        log_event($c['slug'], 'login');
        $seen = isset($_COOKIE['vu_' . str_replace('-', '_', $c['slug'])]);
        redirect(client_url($c['slug']) . ($seen ? '' : '/bienvenue'));
    }
    register_fail();
    log_event($c['slug'], 'login_ko');
    view('login', ['c' => $c, 'error' => 'wrong']);
    exit;
}

if (!is_client_authed($c)) {
    if ($action !== '' && $action !== 'bienvenue') {
        http_response_code(401);            // média, zip, ics : pas de page HTML à la place d'un fichier
        exit('Connexion requise.');
    }
    view('login', ['c' => $c, 'error' => client_expired($c) ? 'expired' : null]);
    exit;
}

/* ---------- client connecté ---------- */

switch ($action) {
    case '':
        log_event($c['slug'], 'view');
        view('client', ['c' => $c, 'videos' => $videos, 'groups' => group_videos($videos)]);
        break;

    case 'bienvenue':
        set_cookie('vu_' . str_replace('-', '_', $c['slug']), '1', 365);
        log_event($c['slug'], 'welcome');
        view('welcome', ['c' => $c]);
        break;

    case 'media':
        $dl = isset($_GET['dl']);
        if ($dl) {
            $ext = strtolower(pathinfo($arg, PATHINFO_EXTENSION));
            log_event($c['slug'], in_array($ext, IMAGE_EXT, true) ? 'thumb' : 'download', $arg);
        }
        require __DIR__ . '/../app/media.php';
        serve_media($c, $arg, $dl);
        break;

    case 'zip':
        log_event($c['slug'], 'zip');
        require __DIR__ . '/../app/zip.php';
        serve_zip($c, $videos);
        break;

    case 'ics':
        foreach ($videos as $v) {
            if ($v['base'] === $arg && $v['date'] !== null) {
                log_event($c['slug'], 'ics', $v['file']);
                require __DIR__ . '/../app/ics.php';
                serve_ics_single($c, $v);
            }
        }
        http_response_code(404);
        exit('Pas de date pour cette vidéo.');

    case 'log':
        if ($method === 'POST') {
            $ev = (string) ($_POST['event'] ?? '');
            if (in_array($ev, ['copy', 'share', 'thumb', 'calendar'], true)) {
                log_event($c['slug'], $ev, substr((string) ($_POST['detail'] ?? ''), 0, 200));
            }
        }
        http_response_code(204);
        break;

    default:
        http_response_code(404);
        view('notfound', ['c' => null]);
}
