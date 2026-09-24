# Portail clients · client.nathandayer.ch

Un dossier sur le PC = le site. Chaque client reçoit un lien et un mot de passe, et retrouve ses vidéos,
vignettes, descriptions à copier, dates de publication conseillées et conseils. Zéro frais en plus :
hébergement Infomaniak existant, PHP sans base de données, synchro gratuite avec rclone.

Sur le PC de Nathan, ce repo est cloné dans `C:\Users\natha\Portail`. Une session Claude Code ouverte là
lit `CLAUDE.md` et sait tout faire : clients, synchro, code, déploiement.

```
Livraisons/         LES CLIENTS. Un dossier par client. Synchronisé vers le serveur. Jamais dans git.
portail/            ce qui tourne sur le serveur
  public/           racine web (index.php, assets, .htaccess)
  app/              code PHP (config.php, lib, media, zip, ics, admin, vues)
  clients/          cible de la synchro sur le serveur (contient _modele/)
  private/          clé de signature, journaux, blocages (créé tout seul)
sync/               install-windows.ps1 (une fois), sync.ps1 (toutes les 5 min), deploy.ps1 (envoi du code)
maquettes/          maquette HTML validée à l'étape 2
CLAUDE.md           contexte pour Claude Code en local
```

Les fichiers HTML à la racine (index.html, design.html…) sont l'ancien portfolio de 2022. Ils ne servent plus.

## Mise en ligne (une fois, environ 30 minutes)

### 1. Sous-domaine chez Infomaniak

Manager Infomaniak > Hébergement Web > Mes sites > Ajouter un site.

- Nom : `client.nathandayer.ch` (le DNS se règle tout seul puisque le domaine est chez eux).
- Dossier du site : `/sites/client.nathandayer.ch/public` (important : `public` à la fin, c'est la racine web).
  Si l'assistant ne propose pas de chemin, créer le site puis modifier son « dossier racine » dans ses réglages.
- Version PHP : 8.2 ou plus.
- Certificat SSL : Let's Encrypt, activé par défaut. Vérifier que https fonctionne.

### 2. Envoyer les fichiers du portail

Avec FileZilla et le compte FTP principal, envoyer **le contenu** du dossier `portail/` dans
`/sites/client.nathandayer.ch/` sur le serveur. Résultat attendu :

```
/sites/client.nathandayer.ch/
  public/
  app/
  clients/
  private/
```

Vérifier que `.htaccess` et `.user.ini` (fichiers cachés) sont bien partis dans `public/`.

### 3. Régler la configuration

Modifier `app/config.php` sur le serveur (ou en local avant l'envoi) :

- `ADMIN_PASSWORD` : le mot de passe de la page de gestion. Tant qu'il vaut `change-moi`, l'admin refuse d'ouvrir.
- `WHATSAPP` : ton numéro au format international sans + ni espaces, ex. `41791234567`. Vide = bouton masqué.
- `INVITE_TEMPLATE` : le message copié par le bouton « Invitation ».

Puis ouvrir `https://client.nathandayer.ch/admin`. Si la page s'affiche, le serveur est prêt.
Le dossier `private/` doit être accessible en écriture par PHP (c'est le cas par défaut chez Infomaniak).

### 4. Compte FTP dédié à la synchro

Manager > Hébergement > FTP/SSH > Ajouter un utilisateur FTP.

- Dossier de départ : `/sites/client.nathandayer.ch/clients` (l'utilisateur ne voit que ce dossier, il ne peut rien casser d'autre).
- Noter serveur, utilisateur et mot de passe. Ils ne servent qu'au script d'installation sur le PC.

### 5. Le PC Windows

1. Installer Git (`winget install Git.Git`) puis cloner le repo :
   `git clone -b claude/sweet-noether-2v4gff https://github.com/nathandayer/siteperso.git C:\Users\natha\Portail`
2. PowerShell : `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned` (une fois), puis
   `& "C:\Users\natha\Portail\sync\install-windows.ps1"`.
3. Répondre aux questions : dossier local (Entrée pour `C:\Users\natha\Portail\Livraisons`), serveur FTP, utilisateur
   `lw48k_livraisons` (limité au dossier `clients` du site), son mot de passe, chemin distant `/`.
4. Le script installe rclone, teste la connexion, crée la tâche planifiée « Portail clients - synchro » (toutes les 5 minutes
   et à l'ouverture de session) et fait une première synchro.
5. Pour envoyer une nouvelle version du code sur le serveur : `powershell -ExecutionPolicy Bypass -File sync\deploy.ps1`
   (demande une fois le mot de passe FTP de `lw48k_claude`). Ne touche ni aux clients, ni à `app/config.php` du serveur.

Journal de la synchro : `sync.log` à côté du script. La page admin affiche « Synchro il y a X min ».

## Utilisation au quotidien

### Nouveau client

1. Dans `Livraisons`, copier `_modele` et renommer : **minuscules, chiffres, tirets, sans espace ni accent**.
   `ogrignou`, `cave-mercier`, `hotel-bella-tola`. Le nom du dossier devient l'adresse : `client.nathandayer.ch/ogrignou`.
2. Remplir `client.txt` :
   ```
   nom: O'Grignou
   mdp: terrasse26
   expire: 31.03.2027
   ```
   `expire` est facultatif. Après cette date le client ne peut plus ouvrir sa page.
3. Déposer `logo.png` (ou .jpg, .svg). Facultatif, sinon les initiales s'affichent.
4. Dans l'admin, bouton « Invitation » : le message WhatsApp avec lien et mot de passe est copié.

### Nouvelle vidéo

Trois fichiers du même nom, numérotés pour l'ordre :

```
01-terrasse.mp4     export DaVinci, H.264, directement dans le dossier du client
01-terrasse.jpg     la vignette (image fixe exportée depuis DaVinci ou Photoshop)
01-terrasse.txt     copie de 01-exemple.txt :
                      titre: La terrasse au coucher du soleil
                      date: 02.10.2026 18:00
                      description:
                      Le texte à publier, sur plusieurs lignes si besoin.

                      #sierre #valais
                      conseil: Postez en Reel, pas en story.
```

Dans les 5 minutes c'est en ligne. Les clés acceptent les fautes et les variantes : `desc`, `Déscription`, `conseils`,
`Mot de passe`… Sans fichier .txt la vidéo s'affiche quand même, avec un titre tiré du nom de fichier.

Règles :

- **mp4 H.264, audio AAC**. Un `.mov` ou du HEVC ne se lit pas dans le navigateur, l'admin le signale.
- Un fichier ou dossier dont le nom commence par `_` n'est jamais publié. Pratique pour préparer : `_03-cuisine.mp4`,
  puis retirer le `_` quand tout est prêt.
- Supprimer un fichier ou un dossier localement le supprime en ligne à la synchro suivante.
- Les vidéos de plus de 6 mois sont signalées dans l'admin : les supprimer du dossier pour garder de la place.
- Le dossier `Livraisons` est la seule source. La page admin lit, elle n'écrit jamais.

### Ce que voit le client

- Mot de passe une fois, puis 90 jours sans le retaper sur le même appareil.
- Première visite : un seul réglage, l'abonnement au calendrier des publications (webcal, se met à jour tout seul).
  Sur Android, le bouton renvoie vers Google Agenda ; le bouton « Calendrier » sous chaque vidéo marche partout.
- Par vidéo : aperçu, date de publication conseillée, description avec bouton Copier, conseil, et sur téléphone
  « Enregistrer dans Photos » en deux temps (préparation avec progression, puis enregistrement via le menu de partage,
  qui propose aussi Instagram directement). Sur ordinateur : Télécharger.
- Vignette en plein écran avec les mêmes boutons. « Tout télécharger » en zip. Bouton WhatsApp.
- Nouveautés (moins de 7 jours), Récentes (moins de 45 jours), Précédentes repliées.

### Page de gestion : /admin

Clients, vidéos, dernière livraison, dernière visite, ce qui a été récupéré, accès, mot de passe, poids, alertes à corriger
(format, vignette ou date manquante, dossier mal nommé, vieilles vidéos, synchro en panne, essais de mot de passe),
publications des 7 prochains jours tous clients confondus, journal. Bouton « Voir » pour ouvrir la page comme le client
(tes propres visites ne sont pas comptées dans le journal).

## Sécurité, en deux mots

- `clients/` et `private/` sont hors de la racine web et refusés par `.htaccess` par sécurité supplémentaire.
- Les vidéos sont servies par PHP après vérification d'un cookie signé (HMAC, clé générée dans `private/secret.key`).
- 10 mots de passe faux en 15 minutes bloquent l'adresse IP 15 minutes.
- Les mots de passe clients sont en clair dans `client.txt`, par choix : c'est toi qui les choisis et le fichier
  n'est jamais accessible depuis le web. Utilise des mots de passe propres à chaque client, pas le tien.

## Tester en local

```
cd portail
php -S 127.0.0.1:8000 -t public public/index.php
```

Créer `app/config.local.php` avec `define('ADMIN_PASSWORD', 'test');` et un dossier `clients/demo/` avec un `client.txt`.

## Dépannage

- **La vidéo ne se lit pas sur iPhone** : vérifier que c'est du H.264 en mp4. Vérifier `curl -I -r 0-100 https://…/media/x.mp4`
  renvoie `206 Partial Content`.
- **Rien n'arrive en ligne** : ouvrir `sync.log`. « Aucun dossier client » = le dossier local est vide ou mal indiqué.
  Vérifier la tâche dans le Planificateur de tâches Windows.
- **Zip qui s'arrête** : augmenter `max_execution_time` dans les réglages PHP du site chez Infomaniak (déjà demandé
  à 600 s par `public/.user.ini`).
- **Page admin blanche** : `private/` n'est pas accessible en écriture, ou PHP < 8.2.
