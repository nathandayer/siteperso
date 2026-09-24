# Envoie le CODE du portail (portail/public, portail/app, .htaccess) sur le serveur.
# À lancer après une modification du code. N'envoie jamais les clients, les journaux ni la config du serveur.
#   powershell -ExecutionPolicy Bypass -File sync\deploy.ps1
# Première utilisation : demande le mot de passe FTP de lw48k_claude et crée le remote rclone « portail ».

$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$root = Resolve-Path (Join-Path $here '..')
$src  = Join-Path $root 'portail'
$configFile = Join-Path $here 'sync.config.json'

if (-not (Test-Path $configFile)) { Write-Host "sync.config.json introuvable : lance d'abord install-windows.ps1." -ForegroundColor Red; exit 1 }
$cfg = Get-Content $configFile -Raw -Encoding UTF8 | ConvertFrom-Json
$rclone = $cfg.rclone
if (-not (Test-Path $rclone)) { Write-Host "rclone introuvable ($rclone)." -ForegroundColor Red; exit 1 }

$ErrorActionPreference = 'Continue'
$remotes = & $rclone listremotes 2>&1
if (-not ($remotes -match '^portail:')) {
    Write-Host 'Première utilisation : accès FTP du compte qui voit tout le site (lw48k_claude).' -ForegroundColor Yellow
    $ftpHost = Read-Host 'Serveur FTP [lw48k.ftp.infomaniak.com]'
    if ([string]::IsNullOrWhiteSpace($ftpHost)) { $ftpHost = 'lw48k.ftp.infomaniak.com' }
    $ftpUser = Read-Host 'Utilisateur FTP [lw48k_claude]'
    if ([string]::IsNullOrWhiteSpace($ftpUser)) { $ftpUser = 'lw48k_claude' }
    $sec = Read-Host 'Mot de passe FTP' -AsSecureString
    $pass = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
    & $rclone config create portail ftp host $ftpHost user $ftpUser pass $pass explicit_tls true --obscure --non-interactive 2>&1 | Out-Null
    $pass = $null
}

& $rclone lsd 'portail:/' 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host 'Connexion impossible avec le remote « portail ». Pour recommencer : rclone config delete portail' -ForegroundColor Red
    exit 1
}

Write-Host "Déploiement de $src -> portail:/"
$args = @(
    'sync', $src, 'portail:/',
    '--exclude', '/clients/**',
    '--exclude', '/private/**',
    '--exclude', '/app/config.php',
    '--exclude', '/app/config.local.php',
    '--exclude', '/.gitignore',
    '--exclude', '.DS_Store', '--exclude', 'Thumbs.db',
    '--transfers', '4', '--retries', '3',
    '--stats-one-line', '--stats', '0', '--log-level', 'NOTICE'
)
& $rclone @args 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host 'Déployé. Vérifie https://client.nathandayer.ch/admin' -ForegroundColor Green
} else {
    Write-Host "rclone a terminé avec le code $LASTEXITCODE" -ForegroundColor Red
}
exit $LASTEXITCODE
