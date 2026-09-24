# Installation de la synchro sur Windows. À lancer UNE fois :
#   clic droit sur ce fichier > Exécuter avec PowerShell
# (si Windows refuse : ouvrir PowerShell, puis
#   Set-ExecutionPolicy -Scope CurrentUser RemoteSigned   et relancer)
#
# Ce script : installe rclone, demande les accès FTP Infomaniak, crée le dossier Livraisons,
# teste la connexion, crée la tâche planifiée (toutes les 5 min) et lance une première synchro.

$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$taskName = 'Portail clients - synchro'

Write-Host ''
Write-Host '=== Portail clients : installation de la synchro ===' -ForegroundColor Cyan
Write-Host ''

# ---------- 1. rclone ----------
function Find-Rclone {
    $cmd = Get-Command rclone.exe -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    $candidates = @(
        (Join-Path $env:LOCALAPPDATA 'rclone\rclone.exe'),
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'),
        'C:\Program Files\rclone\rclone.exe'
    )
    foreach ($c in $candidates) {
        if (Test-Path $c -PathType Leaf) { return $c }
        if (Test-Path $c -PathType Container) {
            $f = Get-ChildItem -Path $c -Recurse -Filter rclone.exe -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($f) { return $f.FullName }
        }
    }
    return $null
}

$rclone = Find-Rclone
if (-not $rclone) {
    Write-Host 'Installation de rclone...'
    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if ($winget) {
        & winget install --id Rclone.Rclone -e --silent --accept-source-agreements --accept-package-agreements | Out-Null
        $rclone = Find-Rclone
    }
    if (-not $rclone) {
        $dir = Join-Path $env:LOCALAPPDATA 'rclone'
        $zip = Join-Path $env:TEMP 'rclone.zip'
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
        Invoke-WebRequest -Uri 'https://downloads.rclone.org/rclone-current-windows-amd64.zip' -OutFile $zip
        Expand-Archive -Path $zip -DestinationPath $env:TEMP -Force
        $exe = Get-ChildItem -Path $env:TEMP -Recurse -Filter rclone.exe | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        Copy-Item $exe.FullName (Join-Path $dir 'rclone.exe') -Force
        $rclone = Join-Path $dir 'rclone.exe'
    }
}
Write-Host "rclone : $rclone" -ForegroundColor Green

# ---------- 2. questions ----------
$defaultLocal = Join-Path $HOME 'Livraisons'
$local = Read-Host "Dossier local des livraisons [$defaultLocal]"
if ([string]::IsNullOrWhiteSpace($local)) { $local = $defaultLocal }
if (-not (Test-Path $local)) {
    New-Item -ItemType Directory -Force -Path $local | Out-Null
    $modele = Join-Path $here '..\portail\clients\_modele'
    if (Test-Path $modele) { Copy-Item $modele (Join-Path $local '_modele') -Recurse -Force }
    Write-Host "Dossier créé : $local (avec le dossier _modele à copier pour chaque client)"
}

Write-Host ''
Write-Host 'Accès FTP Infomaniak (Manager > Hébergement > FTP/SSH). Rien n''est envoyé ailleurs que chez Infomaniak.' -ForegroundColor Yellow
$ftpHost = Read-Host 'Serveur FTP (ex. xxxxx.ftp.infomaniak.com)'
$ftpUser = Read-Host 'Utilisateur FTP'
$ftpPassSecure = Read-Host 'Mot de passe FTP' -AsSecureString
$ftpPass = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($ftpPassSecure))
$defaultRemote = '/sites/clients.nathandayer.ch/clients'
$remotePath = Read-Host "Dossier clients/ sur le serveur [$defaultRemote] (mettre / si l'utilisateur FTP est limité à ce dossier)"
if ([string]::IsNullOrWhiteSpace($remotePath)) { $remotePath = $defaultRemote }

# ---------- 3. connexion rclone ----------
& $rclone config delete infomaniak 2>$null
& $rclone config create infomaniak ftp host $ftpHost user $ftpUser pass $ftpPass explicit_tls true --obscure --non-interactive | Out-Null
$ftpPass = $null
Write-Host 'Test de connexion...'
& $rclone lsd "infomaniak:$remotePath" 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    & $rclone mkdir "infomaniak:$remotePath" 2>&1 | Out-Null
    & $rclone lsd "infomaniak:$remotePath" 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Connexion impossible à infomaniak:$remotePath. Vérifie serveur, utilisateur, mot de passe et chemin, puis relance." -ForegroundColor Red
        exit 1
    }
}
Write-Host 'Connexion FTP OK' -ForegroundColor Green

# ---------- 4. config de la synchro ----------
$cfg = @{ local = $local; remote = 'infomaniak'; remotePath = $remotePath; rclone = $rclone }
[System.IO.File]::WriteAllText((Join-Path $here 'sync.config.json'), ($cfg | ConvertTo-Json), (New-Object System.Text.UTF8Encoding($false)))

# ---------- 5. tâche planifiée ----------
$syncScript = Join-Path $here 'sync.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$syncScript`""
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration (New-TimeSpan -Days 3650)
$logon = New-ScheduledTaskTrigger -AtLogOn
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 3) -DontStopIfGoingOnBatteries -AllowStartIfOnBatteries
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger @($trigger, $logon) -Settings $settings -Description 'Envoie le dossier Livraisons vers clients.nathandayer.ch toutes les 5 minutes.' -Force | Out-Null
Write-Host "Tâche planifiée « $taskName » créée (toutes les 5 min, et à l'ouverture de session)." -ForegroundColor Green

# ---------- 6. première synchro ----------
Write-Host ''
Write-Host 'Première synchro...'
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $syncScript
Write-Host ''
Write-Host 'Terminé. Dépose un dossier client dans' $local 'et il sera en ligne dans les 5 minutes.' -ForegroundColor Cyan
Write-Host "Journal : $(Join-Path $here 'sync.log')"
Read-Host 'Entrée pour fermer'
