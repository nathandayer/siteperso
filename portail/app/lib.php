<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

date_default_timezone_set(TIMEZONE);
mb_internal_encoding('UTF-8');

const VIDEO_EXT    = ['mp4', 'm4v', 'webm', 'mov'];
const PLAYABLE_EXT = ['mp4', 'm4v', 'webm'];          // .mov ne se lit pas partout
const IMAGE_EXT    = ['jpg', 'jpeg', 'png', 'webp'];
const LOGO_NAMES   = ['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp', 'logo.svg'];
const MIME_BY_EXT  = [
    'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
];

/* ------------------------------------------------------------------ */
/*  Utilitaires                                                        */
/* ------------------------------------------------------------------ */

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slug_valid(string $s): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,48}$/', $s);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function base_url(): string
{
    return (is_https() ? 'https' : 'http') . '://' . host_name();
}

/** Hôte avec son port éventuel (« clients.nathandayer.ch », « 127.0.0.1:8000 ») */
function host_name(): string
{
    return $_SERVER['HTTP_HOST'] ?? 'localhost';
}

function client_url(string $slug): string
{
    return base_url() . '/' . $slug;
}

function redirect(string $to, int $code = 303): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
}

function human_size(int $bytes): string
{
    if ($bytes >= 1e9) return number_format($bytes / 1e9, 1, ',', '') . ' Go';
    if ($bytes >= 1e6) return number_format($bytes / 1e6, 0, ',', '') . ' Mo';
    if ($bytes >= 1e3) return number_format($bytes / 1e3, 0, ',', '') . ' Ko';
    return $bytes . ' o';
}

/** Retire les accents : « Réglage » -> « reglage » */
function unaccent(string $s): string
{
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    return $t === false ? $s : $t;
}

/* ------------------------------------------------------------------ */
/*  Dates en français                                                  */
/* ------------------------------------------------------------------ */

const FR_DAYS   = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const FR_MONTHS = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const FR_MONTHS_SHORT = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

/** « jeudi 2 octobre, 18h00 » (année ajoutée si différente de l'année courante) */
function fr_date(int $ts, bool $with_time = false, bool $with_day = true): string
{
    $d = (int) date('j', $ts);
    $day = $d === 1 ? '1er' : (string) $d;
    $s = ($with_day ? FR_DAYS[(int) date('w', $ts)] . ' ' : '') . $day . ' ' . FR_MONTHS[(int) date('n', $ts)];
    if (date('Y', $ts) !== date('Y')) {
        $s .= ' ' . date('Y', $ts);
    }
    if ($with_time) {
        $s .= ', ' . date('G', $ts) . 'h' . date('i', $ts);
    }
    return $s;
}

/** Majuscule initiale qui respecte les accents : « à l'instant » -> « À l'instant » */
function ucfirst_fr(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

/** « 2 oct. » */
function fr_date_short(int $ts): string
{
    return date('j', $ts) . ' ' . FR_MONTHS_SHORT[(int) date('n', $ts)];
}

/** « hier », « il y a 3 jours »… */
function fr_ago(?int $ts): string
{
    if ($ts === null) return 'jamais';
    $diff = time() - $ts;
    if ($diff < 90)       return "à l'instant";
    if ($diff < 3600)     return 'il y a ' . intdiv($diff, 60) . ' min';
    if (date('Y-m-d', $ts) === date('Y-m-d')) return "aujourd'hui " . date('H:i', $ts);
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) return 'hier ' . date('H:i', $ts);
    $days = intdiv($diff, 86400);
    if ($days < 7)   return 'il y a ' . $days . ' jours';
    if ($days < 30)  return 'il y a ' . intdiv($days, 7) . ' sem.';
    if ($days < 365) return 'il y a ' . intdiv($days, 30) . ' mois';
    return 'il y a ' . intdiv($days, 365) . ' an' . ($days >= 730 ? 's' : '');
}

/**
 * Comprend « 2026-10-02 18:00 », « 02.10.2026 18h30 », « 2.10.26 », « 02/10/2026 »,
 * avec l'heure dans la même ligne ou dans $heure.
 * Retourne [timestamp, heure_donnée] ou null.
 */
function parse_date(?string $date, ?string $heure = null): ?array
{
    $date = trim((string) $date);
    if ($date === '') return null;

    $time = null;
    $src  = $date . ' ' . trim((string) $heure);
    if (preg_match('/(\d{1,2})\s*[:hH]\s*(\d{2})?/', $src, $m)) {
        $time = [(int) $m[1], (int) ($m[2] ?? 0)];
    }

    $y = $mo = $d = null;
    if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $date, $m)) {
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } elseif (preg_match('/(\d{1,2})[.\/](\d{1,2})[.\/](\d{2,4})/', $date, $m)) {
        [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($y < 100) $y += 2000;
    } else {
        return null;
    }
    if (!checkdate($mo, $d, $y)) return null;

    $ts = mktime($time[0] ?? 0, $time[1] ?? 0, 0, $mo, $d, $y);
    return [$ts, $time !== null];
}

/* ------------------------------------------------------------------ */
/*  Lecture des fichiers texte « clé: valeur »                         */
/* ------------------------------------------------------------------ */

const KEY_ALIASES = [
    // client.txt
    'nom' => 'nom', 'name' => 'nom', 'client' => 'nom', 'entreprise' => 'nom', 'societe' => 'nom',
    'mdp' => 'mdp', 'motdepasse' => 'mdp', 'password' => 'mdp', 'pass' => 'mdp', 'mot' => 'mdp', 'code' => 'mdp',
    'expire' => 'expire', 'expiration' => 'expire', 'fin' => 'expire', 'jusqua' => 'expire', 'jusquau' => 'expire',
    'instagram' => 'instagram', 'insta' => 'instagram',
    // vidéo.txt
    'titre' => 'titre', 'title' => 'titre',
    'date' => 'date', 'publication' => 'date', 'publier' => 'date', 'quand' => 'date', 'jour' => 'date', 'apublierle' => 'date',
    'heure' => 'heure', 'time' => 'heure', 'hour' => 'heure', 'a' => 'heure',
    'description' => 'description', 'desc' => 'description', 'descr' => 'description', 'descriptif' => 'description',
    'texte' => 'description', 'text' => 'description', 'legende' => 'description', 'caption' => 'description', 'post' => 'description',
    'conseil' => 'conseil', 'conseils' => 'conseil', 'tip' => 'conseil', 'tips' => 'conseil', 'astuce' => 'conseil',
    'note' => 'conseil', 'notes' => 'conseil', 'remarque' => 'conseil',
    'plateforme' => 'plateforme', 'platform' => 'plateforme', 'reseau' => 'plateforme', 'ou' => 'plateforme',
];

function normalize_key(string $k): string
{
    $k = strtolower(unaccent(trim($k)));
    return preg_replace('/[^a-z]/', '', $k) ?? '';
}

/**
 * Parseur tolérant. Une ligne « clé: valeur » ouvre une clé connue ;
 * les lignes suivantes s'y rattachent (descriptions sur plusieurs lignes).
 */
function parse_kv(string $path): array
{
    $raw = @file_get_contents($path);
    if ($raw === false) return [];
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);          // BOM
    if (!mb_check_encoding($raw, 'UTF-8')) {                   // vieux Bloc-notes en ANSI
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);

    $out = [];
    $current = null;
    foreach (explode("\n", $raw) as $line) {
        if (preg_match('/^\s*([\p{L} ]{1,30})\s*:\s?(.*)$/u', $line, $m)) {
            $key = KEY_ALIASES[normalize_key($m[1])] ?? null;
            if ($key !== null) {
                // « nom » dans un fichier vidéo veut dire « titre »
                $current = $key;
                $out[$current] = rtrim($m[2]);
                continue;
            }
        }
        if ($current !== null) {
            $out[$current] .= "\n" . rtrim($line);
        }
    }
    foreach ($out as $k => $v) {
        $out[$k] = trim($v);
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/*  Clients et vidéos                                                  */
/* ------------------------------------------------------------------ */

/** Tous les dossiers de clients/, valides ou non (pour l'admin). */
function list_client_dirs(): array
{
    if (!is_dir(CLIENTS_DIR)) return [];
    $dirs = [];
    foreach (scandir(CLIENTS_DIR) ?: [] as $name) {
        if ($name[0] === '.' || $name[0] === '_') continue;
        if (is_dir(CLIENTS_DIR . '/' . $name)) $dirs[] = $name;
    }
    sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
    return $dirs;
}

/**
 * Charge un client. Retourne null si le dossier n'existe pas.
 * $c['ok'] est faux si le dossier ne peut pas être servi (voir $c['errors']).
 */
function load_client(string $slug): ?array
{
    $dir = CLIENTS_DIR . '/' . $slug;
    if ($slug === '' || $slug[0] === '.' || $slug[0] === '_' || str_contains($slug, '/') || !is_dir($dir)) {
        return null;
    }
    $c = [
        'slug' => $slug, 'dir' => $dir, 'nom' => $slug, 'mdp' => '', 'expire' => null,
        'instagram' => '', 'logo' => null, 'ok' => true, 'errors' => [],
    ];
    if (!slug_valid($slug)) {
        $c['ok'] = false;
        $c['errors'][] = 'Nom de dossier invalide : minuscules, chiffres et tirets seulement, sans espace ni accent.';
    }
    $meta = is_file("$dir/client.txt") ? parse_kv("$dir/client.txt") : null;
    if ($meta === null) {
        $c['ok'] = false;
        $c['errors'][] = 'Pas de fichier client.txt dans le dossier.';
    } else {
        $c['nom']       = $meta['nom'] ?? $c['nom'];
        $c['mdp']       = trim($meta['mdp'] ?? '');
        $c['instagram'] = ltrim(trim($meta['instagram'] ?? ''), '@');
        if ($c['mdp'] === '') {
            $c['ok'] = false;
            $c['errors'][] = 'Pas de mot de passe (ligne « mdp: ») dans client.txt.';
        }
        if (!empty($meta['expire'])) {
            $p = parse_date($meta['expire']);
            if ($p === null) {
                $c['errors'][] = 'Date d\'expiration illisible : « ' . $meta['expire'] . ' ».';
            } else {
                $c['expire'] = $p[0] + ($p[1] ? 0 : 86399);   // jusqu'à la fin du jour
            }
        }
    }
    foreach (LOGO_NAMES as $l) {
        if (is_file("$dir/$l")) { $c['logo'] = $l; break; }
    }
    return $c;
}

function client_expired(array $c): bool
{
    return $c['expire'] !== null && $c['expire'] < time();
}

/** Initiales pour le logo de secours : « O'Grignou » -> « OG » */
function initials(string $name): string
{
    $words = preg_split('/[\s\'’-]+/u', trim($name)) ?: [];
    $s = '';
    foreach ($words as $w) {
        if ($w === '') continue;
        $s .= mb_strtoupper(mb_substr($w, 0, 1));
        if (mb_strlen($s) >= 2) break;
    }
    return $s !== '' ? $s : mb_strtoupper(mb_substr($name, 0, 2));
}

/** Titre de secours depuis le nom de fichier : « 01-terrasse-soir » -> « Terrasse soir » */
function title_from_filename(string $base): string
{
    $t = preg_replace('/^\d+[\s._-]*/', '', $base) ?? $base;
    $t = str_replace(['-', '_', '.'], ' ', $t);
    $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);
    return $t === '' ? $base : mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
}

/** Numéro d'ordre depuis le nom de fichier : « 01-terrasse » -> « 01 » */
function number_from_filename(string $base): string
{
    return preg_match('/^(\d+)/', $base, $m) ? $m[1] : '';
}

/**
 * Liste les vidéos d'un client, triées par nom de fichier.
 * Chaque vidéo : base, file, ext, size, mtime, thumb, titre, num, date, date_has_time,
 *                description, conseil, plateforme, playable, meta (bool), warnings[]
 */
function load_videos(array $c): array
{
    $dir = $c['dir'];
    $files = scandir($dir) ?: [];
    $byBase = [];
    foreach ($files as $f) {
        if ($f[0] === '.' || $f[0] === '_') continue;
        if (!is_file("$dir/$f")) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $base = pathinfo($f, PATHINFO_FILENAME);
        if (in_array($ext, VIDEO_EXT, true)) {
            $byBase[$base]['video'] = $f;
        } elseif (in_array($ext, IMAGE_EXT, true) && !in_array(strtolower($f), LOGO_NAMES, true)) {
            $byBase[$base]['thumb'] = $f;
        } elseif ($ext === 'txt' && strtolower($f) !== 'client.txt') {
            $byBase[$base]['meta'] = $f;
        }
    }

    $videos = [];
    foreach ($byBase as $base => $parts) {
        if (empty($parts['video'])) continue;          // vignette ou txt orphelin
        $file = $parts['video'];
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $meta = !empty($parts['meta']) ? parse_kv("$dir/{$parts['meta']}") : [];
        $date = parse_date($meta['date'] ?? null, $meta['heure'] ?? null);

        $v = [
            'base' => (string) $base,
            'file' => $file,
            'ext'  => $ext,
            'size' => filesize("$dir/$file") ?: 0,
            'mtime' => filemtime("$dir/$file") ?: time(),
            'thumb' => $parts['thumb'] ?? null,
            'num'  => number_from_filename((string) $base),
            'titre' => trim($meta['titre'] ?? '') !== '' ? trim($meta['titre']) : title_from_filename((string) $base),
            'date' => $date[0] ?? null,
            'date_has_time' => $date[1] ?? false,
            'description' => $meta['description'] ?? '',
            'conseil' => $meta['conseil'] ?? '',
            'plateforme' => $meta['plateforme'] ?? '',
            'playable' => in_array($ext, PLAYABLE_EXT, true),
            'meta' => !empty($parts['meta']),
            'warnings' => [],
        ];
        if (!$v['playable'])        $v['warnings'][] = 'Format .' . $ext . ' illisible dans le navigateur, réexporter en mp4 (H.264).';
        if (!$v['meta'])            $v['warnings'][] = 'Pas de fichier ' . $base . '.txt (titre, date, description).';
        if ($v['thumb'] === null)   $v['warnings'][] = 'Pas de vignette ' . $base . '.jpg.';
        if ($v['meta'] && $v['date'] === null) {
            $v['warnings'][] = !empty($meta['date'])
                ? 'Date illisible : « ' . $meta['date'] . ' ». Utiliser 02.10.2026 18:00.'
                : 'Pas de date de publication (ligne « date: »).';
        }
        if ($v['meta'] && $v['description'] === '') $v['warnings'][] = 'Description vide.';
        $videos[] = $v;
    }
    usort($videos, fn($a, $b) => strnatcasecmp($a['base'], $b['base']));
    return $videos;
}

/** Répartit en « new », « recent », « old » selon la date de livraison (mtime). */
function group_videos(array $videos): array
{
    $g = ['new' => [], 'recent' => [], 'old' => []];
    $now = time();
    foreach ($videos as $v) {
        $age = ($now - $v['mtime']) / 86400;
        if ($age <= NEW_DAYS)        $g['new'][] = $v;
        elseif ($age <= RECENT_DAYS) $g['recent'][] = $v;
        else                         $g['old'][] = $v;
    }
    return $g;
}

function dir_size(string $dir): int
{
    $total = 0;
    if (!is_dir($dir)) return 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $total += $f->getSize();
    }
    return $total;
}

/* ------------------------------------------------------------------ */
/*  Authentification (cookies signés, pas de session)                  */
/* ------------------------------------------------------------------ */

function secret(): string
{
    static $s = null;
    if ($s !== null) return $s;
    ensure_dir(PRIVATE_DIR);
    $f = PRIVATE_DIR . '/secret.key';
    if (!is_file($f)) {
        file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($f, 0600);
    }
    $s = trim((string) file_get_contents($f));
    if ($s === '') {
        throw new RuntimeException('Impossible de créer private/secret.key. Vérifier les droits d\'écriture.');
    }
    return $s;
}

function sign(string $data): string
{
    return hash_hmac('sha256', $data, secret());
}

function set_cookie(string $name, string $value, int $days): void
{
    setcookie($name, $value, [
        'expires' => $days > 0 ? time() + $days * 86400 : 1,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function client_token(array $c): string
{
    return sign('client|' . $c['slug'] . '|' . $c['mdp']);
}

function client_cookie_name(string $slug): string
{
    return 'acces_' . str_replace('-', '_', $slug);
}

function is_client_authed(array $c): bool
{
    if (is_admin()) return true;
    if (!$c['ok'] || client_expired($c)) return false;
    $v = $_COOKIE[client_cookie_name($c['slug'])] ?? '';
    return $v !== '' && hash_equals(client_token($c), $v);
}

function client_login(array $c): void
{
    set_cookie(client_cookie_name($c['slug']), client_token($c), COOKIE_DAYS);
}

function client_logout(array $c): void
{
    set_cookie(client_cookie_name($c['slug']), '', 0);
}

/** Jeton public du flux calendrier (les agendas n'envoient pas de cookies). */
function calendar_token(array $c): string
{
    return substr(sign('cal|' . $c['slug'] . '|' . $c['mdp']), 0, 32);
}

function admin_token(): string
{
    return sign('admin|' . ADMIN_PASSWORD);
}

function is_admin(): bool
{
    $v = $_COOKIE['admin'] ?? '';
    return $v !== '' && hash_equals(admin_token(), $v);
}

function admin_login(): void
{
    set_cookie('admin', admin_token(), COOKIE_DAYS);
}

function admin_logout(): void
{
    set_cookie('admin', '', 0);
}

/* ------------------------------------------------------------------ */
/*  Blocage des essais répétés                                         */
/* ------------------------------------------------------------------ */

function lock_file(): string
{
    ensure_dir(PRIVATE_DIR . '/locks');
    return PRIVATE_DIR . '/locks/' . hash('sha256', client_ip()) . '.json';
}

function is_locked(): bool
{
    $f = lock_file();
    if (!is_file($f)) return false;
    $d = json_decode((string) file_get_contents($f), true) ?: [];
    if (($d['until'] ?? 0) > time()) return true;
    if (($d['first'] ?? 0) < time() - LOGIN_LOCK_MIN * 60) { @unlink($f); }
    return false;
}

function register_fail(): void
{
    $f = lock_file();
    $d = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    if (($d['first'] ?? 0) < time() - LOGIN_LOCK_MIN * 60) {
        $d = ['first' => time(), 'count' => 0, 'until' => 0];
    }
    $d['count'] = ($d['count'] ?? 0) + 1;
    if ($d['count'] >= LOGIN_MAX_FAILS) {
        $d['until'] = time() + LOGIN_LOCK_MIN * 60;
    }
    file_put_contents($f, json_encode($d), LOCK_EX);
    usleep(800000);   // 0,8 s : ralentit les robots sans gêner un humain
}

function clear_fails(): void
{
    @unlink(lock_file());
}

/* ------------------------------------------------------------------ */
/*  Journal                                                            */
/* ------------------------------------------------------------------ */

const LOG_LABELS = [
    'login'     => 'a ouvert sa page',
    'login_ko'  => 'mot de passe refusé',
    'view'      => 'a consulté sa page',
    'download'  => 'a téléchargé',
    'share'     => 'a enregistré dans Photos',
    'copy'      => 'a copié la description de',
    'thumb'     => 'a récupéré la vignette de',
    'zip'       => 'a téléchargé le zip complet',
    'ics'       => 'a ajouté au calendrier',
    'calendar'  => "s'est abonné au calendrier",
    'welcome'   => 'a vu l\'écran de bienvenue',
];

function log_event(string $slug, string $event, string $detail = ''): void
{
    if (is_admin()) return;                       // les visites de Nathan ne comptent pas
    ensure_dir(PRIVATE_DIR . '/logs');
    $line = date('Y-m-d H:i:s') . "\t" . $event . "\t" . str_replace(["\t", "\n"], ' ', $detail) . "\n";
    @file_put_contents(PRIVATE_DIR . '/logs/' . $slug . '.log', $line, FILE_APPEND | LOCK_EX);
}

/** Dernières lignes du journal d'un client : [['ts','event','detail'], …], plus récent d'abord. */
function read_log(string $slug, int $limit = 200): array
{
    $f = PRIVATE_DIR . '/logs/' . $slug . '.log';
    if (!is_file($f)) return [];
    $lines = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice($lines, -$limit);
    $out = [];
    foreach (array_reverse($lines) as $l) {
        $p = explode("\t", $l, 3);
        if (count($p) < 2) continue;
        $out[] = ['ts' => strtotime($p[0]) ?: 0, 'event' => $p[1], 'detail' => $p[2] ?? ''];
    }
    return $out;
}

/** Résumé d'activité d'un client : dernière visite, fichiers récupérés, échecs récents. */
function client_activity(string $slug, array $videos): array
{
    $log = read_log($slug, 2000);
    $last_visit = null;
    $got = [];
    $fails = 0;
    foreach ($log as $e) {
        if (in_array($e['event'], ['login', 'view'], true) && $last_visit === null) $last_visit = $e['ts'];
        if (in_array($e['event'], ['download', 'share'], true)) $got[$e['detail']] = true;
        if ($e['event'] === 'zip') { foreach ($videos as $v) $got[$v['file']] = true; }
        if ($e['event'] === 'login_ko' && $e['ts'] > time() - 86400) $fails++;
    }
    $n = 0;
    foreach ($videos as $v) { if (!empty($got[$v['file']])) $n++; }
    return ['last_visit' => $last_visit, 'downloaded' => $n, 'fails_24h' => $fails, 'got' => $got];
}

/* ------------------------------------------------------------------ */
/*  Synchro                                                            */
/* ------------------------------------------------------------------ */

/** Horodatage écrit par le script de synchro dans clients/_sync.txt, ou null. */
function last_sync(): ?int
{
    $f = CLIENTS_DIR . '/_sync.txt';
    if (!is_file($f)) return null;
    $t = strtotime(trim((string) file_get_contents($f)));
    return $t ?: (filemtime($f) ?: null);
}

/* ------------------------------------------------------------------ */
/*  Rendu                                                              */
/* ------------------------------------------------------------------ */

function view(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require __DIR__ . '/views/' . $name . '.php';
}

function whatsapp_url(string $text = ''): string
{
    return 'https://wa.me/' . WHATSAPP . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** Nom de fichier propre pour le téléchargement : « ogrignou-01-terrasse.mp4 » */
function download_name(string $slug, string $file): string
{
    $n = $slug . '-' . $file;
    $n = preg_replace('/[^\w.\-]+/u', '_', $n) ?? $n;
    return $n;
}
