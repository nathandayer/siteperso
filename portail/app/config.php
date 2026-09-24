<?php
declare(strict_types=1);

/*
 * Configuration du portail clients.
 *
 * Deux façons de régler :
 *  - modifier les valeurs ci-dessous ;
 *  - ou créer app/config.local.php (non versionné) qui fait ses propres define()
 *    avant ceux-ci. Les valeurs ci-dessous ne s'appliquent que si rien n'est déjà défini.
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

function cfg(string $name, mixed $value): void
{
    if (!defined($name)) {
        define($name, $value);
    }
}

// Identité
cfg('SITE_NAME',    'Nathan Dayer');
cfg('SITE_TAGLINE', 'Vidéaste');
cfg('OWNER_URL',    'https://nathandayer.ch');   // où renvoyer quelqu'un qui arrive à la racine
cfg('WHATSAPP',     '');                          // ex. '41791234567'. Vide = bouton masqué.

// Page de gestion : clients.nathandayer.ch/admin
cfg('ADMIN_PASSWORD', 'change-moi');

// Message d'invitation copié depuis la page admin. Variables : {nom} {url} {mdp}
cfg('INVITE_TEMPLATE', "Bonjour, vos vidéos sont prêtes.\n\n{url}\nMot de passe : {mdp}\n\nSur le lien vous trouvez chaque vidéo, sa vignette, la description à copier et la date de publication conseillée.\n\nNathan");

// Réglages d'affichage
cfg('TIMEZONE',       'Europe/Zurich');
cfg('NEW_DAYS',       7);     // « Nouveautés » : livrées depuis moins de N jours
cfg('RECENT_DAYS',    45);    // « Ce mois » : moins de N jours ; au-delà, « Précédentes » repliées
cfg('RETENTION_DAYS', 180);   // l'admin signale les vidéos plus vieilles que ça
cfg('COOKIE_DAYS',    90);    // durée de connexion d'un client
cfg('DISK_QUOTA_GB',  250);   // espace de l'hébergement, pour la jauge admin

// Sécurité
cfg('LOGIN_MAX_FAILS', 10);   // essais ratés avant blocage temporaire d'une IP
cfg('LOGIN_LOCK_MIN',  15);   // minutes de blocage

// Dossiers. clients/ est la cible de la synchro, private/ contient clé, journaux, blocages.
cfg('CLIENTS_DIR', dirname(__DIR__) . '/clients');
cfg('PRIVATE_DIR', dirname(__DIR__) . '/private');
