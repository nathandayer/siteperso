# Portail clients de Nathan Dayer

Tu travailles pour Nathan Dayer, vidéaste en Valais (nathandayer.ch). Il vend des vidéos réseaux sociaux
(reels) à des PME locales et les livre via ce portail : `https://client.nathandayer.ch/<client>`.
Nathan écrit vite avec des fautes, comprends sans corriger. Réponds en français, court, direct.

## Ce dossier

```
Livraisons/     LES CLIENTS. Source de vérité. Synchronisé toutes les 5 min vers le serveur. Jamais dans git.
sync/           Synchro (rclone, tâche planifiée Windows) et déploiement du code.
portail/        Code PHP du site. Modifications -> commit -> `sync\deploy.ps1` pour envoyer sur le serveur.
maquettes/      Maquette HTML de conception. Historique, ne sert plus.
README.md       Doc complète : installation Infomaniak, format des fichiers, dépannage.
```

Les fichiers HTML à la racine (index.html, design.html, …) sont un vieux portfolio de 2022. Ignore-les.

## Comment ça marche

- Un dossier dans `Livraisons/` = un client. Son nom = l'adresse : `Livraisons/ogrignou` -> `client.nathandayer.ch/ogrignou`.
- `sync/sync.ps1` pousse `Livraisons/` vers le dossier `clients/` du serveur toutes les 5 minutes (tâche planifiée
  « Portail clients - synchro »). Suppression locale = suppression en ligne. Les fichiers modifiés depuis moins
  de 2 minutes attendent le tour suivant (export DaVinci en cours).
- Le site PHP lit le dossier à chaque visite : rien à publier, rien à vider.
- Page de gestion : `https://client.nathandayer.ch/admin` (mot de passe dans `app/config.php` **sur le serveur**,
  pas dans le repo).

## Format des fichiers client (règles strictes)

Dossier client : minuscules, chiffres, tirets. Sans espace, sans accent, sans apostrophe. `ogrignou`, `cave-mercier`.

`client.txt` :
```
nom: O'Grignou
mdp: terrasse26
expire: 31.03.2027        (facultatif)
```

Par vidéo, trois fichiers du même nom, numérotés :
```
01-terrasse.mp4           H.264, audio AAC. Jamais .mov ni HEVC.
01-terrasse.jpg           vignette (png/webp acceptés)
01-terrasse.txt :
  titre: La terrasse au coucher du soleil
  date: 02.10.2026 18:00
  description:
  Texte à publier, plusieurs lignes possibles.

  #sierre #valais #ogrignou
  conseil: Une ou deux lignes, un geste concret pour le client.
```

- `logo.png` (ou jpg/svg/webp) à la racine du dossier client : facultatif.
- Un fichier ou dossier dont le nom commence par `_` est ignoré (brouillon).
- Fichiers texte en UTF-8. Écris-les toi-même plutôt que de laisser Nathan ouvrir le Bloc-notes.
- Dates au format `jj.mm.aaaa hh:mm` ou `aaaa-mm-jj hh:mm`. Sans heure = journée entière.

## Ce que tu fais typiquement

**Nouveau client** : créer `Livraisons/<slug>/client.txt` avec un mot de passe simple et propre au client
(deux mots ou un mot et un nombre, pas de caractères spéciaux, ex. `terrasse26`). Demander le logo.

**Nouvelle vidéo** : Nathan dépose le mp4 (et la vignette). Tu écris le `.txt` : titre court, date et heure
de publication conseillées (les meilleurs créneaux : 11h30-12h ou 18h-19h en semaine, jeudi et vendredi
forts pour la restauration), description prête à coller (accroche, 2 à 4 lignes, appel à l'action,
5 à 8 hashtags locaux et métier), conseil concret.

**Vérifier que c'est en ligne** : `powershell -ExecutionPolicy Bypass -File sync\sync.ps1` force une synchro
tout de suite ; `sync\sync.log` dit ce qui s'est passé. Puis l'admin sur le site.

**Modifier le code du portail** : édite dans `portail/`, teste en local si PHP est installé
(`php -S 127.0.0.1:8000 -t portail/public portail/public/index.php`), commit sur la branche courante,
puis `powershell -ExecutionPolicy Bypass -File sync\deploy.ps1` envoie `public/`, `app/` et `.htaccess`
sur le serveur. Le déploiement n'écrase jamais `app/config.php` du serveur, ni `clients/`, ni `private/`.

**Retirer un client** : supprimer son dossier dans `Livraisons/`. Il disparaît du site à la synchro suivante.
Confirmer avec Nathan avant, c'est irréversible côté serveur (les fichiers restent sur son PC seulement
s'il les a gardés ailleurs).

## Interdits

- Ne jamais commiter `Livraisons/`, `sync/sync.config.json`, `sync/sync.log`, un mot de passe FTP ou admin.
- Ne jamais mettre le mot de passe FTP dans un fichier ou dans le chat : il vit dans la config rclone
  (`%APPDATA%\rclone\rclone.conf`), c'est tout.
- Ne pas renommer un dossier client en ligne depuis longtemps sans prévenir : l'adresse et le cookie du client changent.
- Ne pas toucher aux fichiers du vieux portfolio.

## Comptes et chemins (pas de secrets ici)

- Hébergement Infomaniak, site `client.nathandayer.ch`, racine `/sites/client.nathandayer.ch` (le `.htaccess`
  racine renvoie vers `public/`).
- FTP `lw48k.ftp.infomaniak.com`. Utilisateur `lw48k_livraisons` : limité à `clients/`, sert à la synchro
  (remote rclone `infomaniak`). Utilisateur `lw48k_claude` : tout le site, sert au déploiement du code
  (remote rclone `portail`, créé par `deploy.ps1` à la première utilisation).
- WhatsApp de Nathan : 41774257761 (déjà dans la config du site).
