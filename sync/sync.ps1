# Synchronise le dossier Livraisons vers le serveur Infomaniak.
# Lancé toutes les 5 minutes par la tâche planifiée créée par install-windows.ps1.
# Peut aussi être lancé à la main : clic droit > Exécuter avec PowerShell.

$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$configFile = Join-Path $here 'sync.config.json'
$logFile = Join-Path $here 'sync.log'

function Log($msg) {
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $msg"
    Add-Content -Path $logFile -Value $line -Encoding UTF8
    Write-Host $line
}

# Journal : on repart de zéro au-delà de 2 Mo
if ((Test-Path $logFile) -and (Get-Item $logFile).Length -gt 2MB) { Remove-Item $logFile }

if (-not (Test-Path $configFile)) {
    Log "ERREUR : sync.config.json introuvable. Lance d'abord install-windows.ps1."
    exit 1
}
$cfg = Get-Content $configFile -Raw -Encoding UTF8 | ConvertFrom-Json
$local = $cfg.local
$remote = "$($cfg.remote):$($cfg.remotePath)"
$rclone = $cfg.rclone

# --- Garde-fous : on ne vide jamais le serveur par accident ---
if (-not (Test-Path $local -PathType Container)) {
    Log "ERREUR : dossier local introuvable ($local). Synchro annulée, rien n'a été effacé en ligne."
    exit 1
}
$clientDirs = Get-ChildItem -Path $local -Directory | Where-Object { $_.Name -notlike '_*' -and $_.Name -notlike '.*' }
if ($clientDirs.Count -eq 0) {
    Log "Aucun dossier client dans $local. Synchro annulée pour ne pas effacer le serveur."
    exit 1
}
if (-not (Test-Path $rclone)) {
    Log "ERREUR : rclone introuvable ($rclone). Relance install-windows.ps1."
    exit 1
}

# --- Fichiers en cours d'écriture (modifiés il y a moins de 2 min) : exclus des deux côtés ---
# Exclure des deux côtés évite que rclone efface sur le serveur un fichier qu'il ne voit plus localement.
$cutoff = (Get-Date).AddMinutes(-2)
$young = Get-ChildItem -Path $local -Recurse -File |
    Where-Object { $_.LastWriteTime -gt $cutoff -and $_.Name -ne '_sync.txt' } |
    ForEach-Object {
        $rel = $_.FullName.Substring($local.TrimEnd('\').Length + 1) -replace '\\', '/'
        # caractères spéciaux des filtres rclone
        '/' + ($rel -replace '([\[\]\{\}\*\?\\])', '\$1')
    }
$excludeFile = Join-Path $env:TEMP 'portail-exclude.txt'
$rules = @('.*', '**/.*', 'Thumbs.db', 'desktop.ini', '*.tmp', '*.part', '*.crdownload', '~*') + @($young)
[System.IO.File]::WriteAllLines($excludeFile, $rules, (New-Object System.Text.UTF8Encoding($false)))

# --- Horodatage lu par la page admin ---
[System.IO.File]::WriteAllText((Join-Path $local '_sync.txt'), (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), (New-Object System.Text.UTF8Encoding($false)))

# --- Synchro ---
$args = @(
    'sync', $local, $remote,
    '--exclude-from', $excludeFile,
    '--max-delete', '200',
    '--transfers', '2', '--checkers', '4',
    '--retries', '3', '--low-level-retries', '10',
    '--contimeout', '30s', '--timeout', '120s',
    '--stats-one-line', '--stats', '0',
    '--log-level', 'INFO', '--log-file', $logFile
)
if ($young.Count -gt 0) { Log "$($young.Count) fichier(s) encore en cours d'export, repoussés à la prochaine fois." }
Log "Synchro $local -> $remote"
$ErrorActionPreference = 'Continue'   # rclone parle sur la sortie d'erreur, ce n'est pas une erreur PowerShell
& $rclone @args 2>&1 | Out-Null
$code = $LASTEXITCODE
if ($code -eq 0) { Log "OK" } else { Log "rclone a terminé avec le code $code (voir les lignes ci-dessus)" }
exit $code
