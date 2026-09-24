<?php
declare(strict_types=1);

/*
 * Sert une vidéo, une vignette ou un logo depuis le dossier du client,
 * avec les requêtes partielles (Range) sans lesquelles Safari ne lit pas les vidéos.
 */

function serve_media(array $c, string $file, bool $download): never
{
    if ($file === '' || $file[0] === '.' || $file[0] === '_'
        || str_contains($file, '/') || str_contains($file, '\\') || str_contains($file, "\0")) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $isLogo = $c['logo'] !== null && $file === $c['logo'];
    if (!isset(MIME_BY_EXT[$ext]) || ($ext === 'svg' && !$isLogo)) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    $path = $c['dir'] . '/' . $file;
    if (!is_file($path)) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }

    $size  = filesize($path) ?: 0;
    $mtime = filemtime($path) ?: time();
    $etag  = '"' . substr(md5($file . '|' . $size . '|' . $mtime), 0, 20) . '"';

    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }

    while (ob_get_level() > 0) ob_end_clean();
    set_time_limit(0);
    if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
    @ini_set('zlib.output_compression', '0');

    header('Content-Type: ' . MIME_BY_EXT[$ext]);
    header('Accept-Ranges: bytes');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    if ($download) {
        $name = download_name($c['slug'], $file);
        header('Content-Disposition: attachment; filename="' . $name . "\"; filename*=UTF-8''" . rawurlencode($name));
    }

    // Plage demandée ?
    $start = 0;
    $end   = $size - 1;
    $range = $_SERVER['HTTP_RANGE'] ?? '';
    if ($range !== '' && $size > 0) {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) || ($m[1] === '' && $m[2] === '')) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        if ($m[1] === '') {                      // bytes=-500 : les 500 derniers octets
            $start = max(0, $size - (int) $m[2]);
        } else {
            $start = (int) $m[1];
            if ($m[2] !== '') $end = min($end, (int) $m[2]);
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    $length = $end - $start + 1;
    header('Content-Length: ' . $length);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }

    $fp = fopen($path, 'rb');
    if ($fp === false) {
        http_response_code(500);
        exit;
    }
    fseek($fp, $start);
    $remaining = $length;
    $chunk = 1024 * 1024;
    while ($remaining > 0 && !connection_aborted()) {
        $buf = fread($fp, min($chunk, $remaining));
        if ($buf === false || $buf === '') break;
        echo $buf;
        flush();
        $remaining -= strlen($buf);
    }
    fclose($fp);
    exit;
}
