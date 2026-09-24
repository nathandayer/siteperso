<?php
declare(strict_types=1);

/*
 * Calendrier : un événement par vidéo (fichier .ics) ou un abonnement pour tout le client
 * (webcal://…/calendrier/<jeton>.ics) qui se met à jour tout seul.
 */

function ics_escape(string $s): string
{
    return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $s);
}

/** Replie les lignes à 75 octets comme l'exige le format. */
function ics_fold(string $line): string
{
    $out = '';
    $first = true;
    while ($line !== '') {
        $max = $first ? 75 : 74;
        $chunk = mb_strcut($line, 0, $max, 'UTF-8');
        $out .= ($first ? '' : "\r\n ") . $chunk;
        $line = substr($line, strlen($chunk));
        $first = false;
    }
    return $out;
}

function ics_event(array $c, array $v): array
{
    $host = parse_url(base_url(), PHP_URL_HOST) ?: 'portail';
    $url  = client_url($c['slug']);
    $desc = $v['description'] !== '' ? $v['description'] : 'Vidéo prête sur votre espace.';
    if ($v['conseil'] !== '') $desc .= "\n\nConseil : " . $v['conseil'];
    $desc .= "\n\n" . $url;

    $lines = [
        'BEGIN:VEVENT',
        'UID:' . $c['slug'] . '-' . preg_replace('/[^A-Za-z0-9.-]/', '_', $v['base']) . '@' . $host,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'SUMMARY:' . ics_escape('Publier : ' . $v['titre']),
        'DESCRIPTION:' . ics_escape($desc),
        'URL:' . $url,
        'SEQUENCE:' . (int) floor($v['mtime'] / 60),   // change si la vidéo est réexportée
    ];
    if ($v['date_has_time']) {
        $lines[] = 'DTSTART:' . gmdate('Ymd\THis\Z', $v['date']);
        $lines[] = 'DTEND:' . gmdate('Ymd\THis\Z', $v['date'] + 1800);
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:' . ics_escape('Dans 1 h : publier « ' . $v['titre'] . ' »');
        $lines[] = 'TRIGGER:-PT1H';
        $lines[] = 'END:VALARM';
    } else {
        $lines[] = 'DTSTART;VALUE=DATE:' . date('Ymd', $v['date']);
        $lines[] = 'DTEND;VALUE=DATE:' . date('Ymd', $v['date'] + 86400);
    }
    $lines[] = 'END:VEVENT';
    return $lines;
}

function ics_document(array $c, array $eventsLines, bool $feed): string
{
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//' . ics_escape(SITE_NAME) . '//Portail clients//FR',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:' . ics_escape('Publications · ' . $c['nom']),
        'X-WR-TIMEZONE:' . TIMEZONE,
    ];
    if ($feed) {
        $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT1H';
        $lines[] = 'X-PUBLISHED-TTL:PT1H';
    }
    foreach ($eventsLines as $ev) {
        foreach ($ev as $l) $lines[] = $l;
    }
    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
}

function serve_ics_single(array $c, array $v): never
{
    $body = ics_document($c, [ics_event($c, $v)], false);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . download_name($c['slug'], $v['base'] . '.ics') . '"');
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

function serve_ics_feed(array $c, array $videos): never
{
    $events = [];
    foreach ($videos as $v) {
        if ($v['date'] !== null) $events[] = ics_event($c, $v);
    }
    $body = ics_document($c, $events, true);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="publications-' . $c['slug'] . '.ics"');
    header('Cache-Control: no-cache');
    echo $body;
    exit;
}
