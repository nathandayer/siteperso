<?php
declare(strict_types=1);

/*
 * « Tout télécharger » : un zip envoyé en flux, sans compression (les vidéos sont déjà
 * compressées) et sans rien charger en mémoire. Taille totale connue à l'avance, donc
 * le navigateur affiche une vraie barre de progression.
 */

function zip_dos_time(int $ts): array
{
    $t = ((int) date('H', $ts) << 11) | ((int) date('i', $ts) << 5) | ((int) date('s', $ts) >> 1);
    $d = (max(1980, (int) date('Y', $ts)) - 1980) << 9 | ((int) date('n', $ts) << 5) | (int) date('j', $ts);
    return [$t, $d];
}

function zip_crc_file(string $path): int
{
    return (int) hexdec(hash_file('crc32b', $path));
}

/** Prépare la liste des entrées : ['name','path'|'data','size','mtime'] */
function zip_entries(array $c, array $videos): array
{
    $folder = $c['slug'] . '/';
    $entries = [];
    $notes = [];
    foreach ($videos as $v) {
        $entries[] = ['name' => $folder . $v['file'], 'path' => $c['dir'] . '/' . $v['file'], 'size' => $v['size'], 'mtime' => $v['mtime']];
        if ($v['thumb'] !== null) {
            $p = $c['dir'] . '/' . $v['thumb'];
            $entries[] = ['name' => $folder . $v['thumb'], 'path' => $p, 'size' => filesize($p) ?: 0, 'mtime' => filemtime($p) ?: time()];
        }
        $block = ($v['num'] !== '' ? $v['num'] . ' · ' : '') . $v['titre'] . "\n";
        if ($v['date'] !== null) $block .= 'À publier : ' . fr_date($v['date'], $v['date_has_time']) . "\n";
        $block .= "\n" . ($v['description'] !== '' ? $v['description'] : '(pas de description)') . "\n";
        if ($v['conseil'] !== '') $block .= "\nConseil : " . $v['conseil'] . "\n";
        $notes[] = $block;
    }
    $txt = implode("\n" . str_repeat('-', 40) . "\n\n", $notes);
    $txt = str_replace("\n", "\r\n", $txt);
    $entries[] = ['name' => $folder . 'descriptions.txt', 'data' => $txt, 'size' => strlen($txt), 'mtime' => time()];
    return $entries;
}

function serve_zip(array $c, array $videos): never
{
    if ($videos === []) {
        http_response_code(404);
        exit('Aucune vidéo.');
    }
    $entries = zip_entries($c, $videos);

    // Longueur totale (format zip classique, limite 4 Go)
    $total = 22;
    foreach ($entries as $e) {
        $n = strlen($e['name']);
        $total += 30 + $n + $e['size'] + 16 + 46 + $n;
    }
    if ($total >= 0xFFFFFFFF) {
        http_response_code(413);
        exit('Trop volumineux pour un seul zip. Téléchargez les vidéos une par une.');
    }

    while (ob_get_level() > 0) ob_end_clean();
    set_time_limit(0);
    if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
    @ini_set('zlib.output_compression', '0');

    $zipName = download_name($c['slug'], 'videos-' . date('Y-m-d') . '.zip');
    header('Content-Type: application/zip');
    header('Content-Length: ' . $total);
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    $offset  = 0;
    $central = '';
    $count   = 0;

    foreach ($entries as $e) {
        if (connection_aborted()) exit;
        [$dt, $dd] = zip_dos_time($e['mtime']);
        $name = $e['name'];
        $size = $e['size'];
        $localOffset = $offset;

        // En-tête local : CRC et tailles à 0, complétés par le descripteur (bit 3)
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0808, 0, $dt, $dd, 0, 0, 0, strlen($name), 0) . $name;
        echo $local;
        $offset += strlen($local);

        if (isset($e['data'])) {
            echo $e['data'];
            $crc = crc32($e['data']);
        } else {
            $crc = zip_crc_file($e['path']);
            $fp = fopen($e['path'], 'rb');
            if ($fp === false) exit;
            while (!feof($fp) && !connection_aborted()) {
                $buf = fread($fp, 1024 * 1024);
                if ($buf === false) break;
                echo $buf;
                flush();
            }
            fclose($fp);
        }
        $offset += $size;

        $desc = pack('VVVV', 0x08074b50, $crc, $size, $size);
        echo $desc;
        $offset += strlen($desc);
        flush();

        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0808, 0, $dt, $dd, $crc, $size, $size,
            strlen($name), 0, 0, 0, 0, 0, $localOffset) . $name;
        $count++;
    }

    echo $central;
    echo pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), $offset, 0);
    flush();
    exit;
}
