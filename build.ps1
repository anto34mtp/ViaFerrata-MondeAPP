#Requires -Version 5.1
<#
.SYNOPSIS
    ViaFerrata-Monde — Script de build Android interactif (Windows/PowerShell)
.DESCRIPTION
    Installe automatiquement les prérequis manquants (Node.js, Java JDK, Android SDK),
    génère un APK ou AAB signé avec le keystore Play Store inclus dans le projet.
.EXAMPLE
    .\build.ps1
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ── Couleurs & helpers ────────────────────────────────────────────────────────
function Write-Step  { param($msg) Write-Host "`n━━━  $msg" -ForegroundColor Cyan -BackgroundColor Black }
function Write-OK    { param($msg) Write-Host "✔  $msg" -ForegroundColor Green }
function Write-Info  { param($msg) Write-Host "ℹ  $msg" -ForegroundColor Cyan }
function Write-Warn  { param($msg) Write-Host "⚠  $msg" -ForegroundColor Yellow }
function Write-Fail  { param($msg) Write-Host "✖  $msg" -ForegroundColor Red; exit 1 }

function Pause-User  { param($msg = "Appuyez sur Entrée pour continuer…") Read-Host $msg | Out-Null }

function Test-Command { param($name) return [bool](Get-Command $name -ErrorAction SilentlyContinue) }

function Install-Winget-Package {
    param([string]$Id, [string]$Label)
    Write-Info "Installation de $Label via winget…"
    winget install --id $Id --silent --accept-package-agreements --accept-source-agreements
    if ($LASTEXITCODE -ne 0) {
        Write-Warn "winget a retourné le code $LASTEXITCODE pour $Label — continuons quand même."
    }
}

function Refresh-Path {
    $env:PATH = [System.Environment]::GetEnvironmentVariable("PATH","Machine") + ";" +
                [System.Environment]::GetEnvironmentVariable("PATH","User")
}

# ── Bannière ─────────────────────────────────────────────────────────────────
Clear-Host
Write-Host @"

  ╔═══════════════════════════════════════════════════╗
  ║     ViaFerrata-Monde — Build Android              ║
  ║     Script PowerShell  (Windows)                  ║
  ║     Signature automatique via keystore Play Store  ║
  ╚═══════════════════════════════════════════════════╝

"@ -ForegroundColor Green

# ── Chemins ───────────────────────────────────────────────────────────────────
$ScriptDir    = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir       = Join-Path $ScriptDir "ViaFerrataApp"
$AndroidDir   = Join-Path $AppDir "android"
$KeystoreDir  = Join-Path $ScriptDir "keystore"
$KeystoreFile = Join-Path $KeystoreDir "viaferrata.keystore"
$KeystoreProps= Join-Path $KeystoreDir "keystore.properties"
$OutputDir    = Join-Path $ScriptDir "build_output"

# ── 1. Prérequis : winget ────────────────────────────────────────────────────
Write-Step "Vérification de winget"
if (-not (Test-Command "winget")) {
    Write-Warn "winget non disponible. Installez manuellement les prérequis."
    Write-Warn "  - Node.js  : https://nodejs.org"
    Write-Warn "  - Java JDK : https://adoptium.net"
    Write-Warn "  - Android Studio : https://developer.android.com/studio"
    Pause-User "Appuyez sur Entrée une fois les installations manuelles effectuées"
} else {
    Write-OK "winget disponible."
}

# ── 2. Prérequis : Node.js ───────────────────────────────────────────────────
Write-Step "Vérification de Node.js"
Refresh-Path
if (-not (Test-Command "node")) {
    Write-Warn "Node.js non détecté. Installation en cours…"
    if (Test-Command "winget") {
        Install-Winget-Package "OpenJS.NodeJS.LTS" "Node.js LTS"
        Refresh-Path
    }
    if (-not (Test-Command "node")) {
        Write-Fail "Node.js toujours introuvable après installation. Installez manuellement depuis https://nodejs.org"
    }
}
$nodeVer = node --version
Write-OK "Node.js $nodeVer détecté."

# ── 3. Prérequis : Java JDK ──────────────────────────────────────────────────
Write-Step "Vérification de Java (JDK 17+)"
Refresh-Path
$javaOk = $false
if (Test-Command "java") {
    $javaVerStr = & java -version 2>&1 | Select-String "version" | Select-Object -First 1
    Write-OK "Java détecté : $javaVerStr"
    $javaOk = $true
}

if (-not $javaOk) {
    Write-Warn "Java non détecté. Installation de Microsoft OpenJDK 17…"
    if (Test-Command "winget") {
        Install-Winget-Package "Microsoft.OpenJDK.17" "Microsoft OpenJDK 17"
        Refresh-Path
    }
    if (-not (Test-Command "java")) {
        Write-Warn "Java toujours introuvable. Installez depuis https://adoptium.net (JDK 17 recommandé)."
        Pause-User "Appuyez sur Entrée une fois Java installé"
        Refresh-Path
        if (-not (Test-Command "java")) {
            Write-Fail "Java introuvable. Build impossible."
        }
    }
}

# JAVA_HOME pour Gradle
if ([string]::IsNullOrEmpty($env:JAVA_HOME)) {
    $javaExePath = (Get-Command java -ErrorAction SilentlyContinue).Source
    if ($javaExePath) {
        $env:JAVA_HOME = Split-Path -Parent (Split-Path -Parent $javaExePath)
        Write-Info "JAVA_HOME défini automatiquement : $env:JAVA_HOME"
    }
}

# ── 4. Prérequis : Android SDK ───────────────────────────────────────────────
Write-Step "Vérification de l'Android SDK"

$sdkCandidates = @(
    $env:ANDROID_HOME,
    $env:ANDROID_SDK_ROOT,
    "$env:LOCALAPPDATA\Android\Sdk",
    "$env:USERPROFILE\AppData\Local\Android\Sdk",
    "C:\Android\Sdk"
) | Where-Object { $_ -and (Test-Path $_) }

if ($sdkCandidates.Count -eq 0) {
    Write-Warn "Android SDK non détecté."
    if (Test-Command "winget") {
        Write-Info "Téléchargement d'Android Studio (inclut le SDK)…"
        Write-Info "  ► Après installation, ouvrez Android Studio et acceptez la licence SDK."
        Install-Winget-Package "Google.AndroidStudio" "Android Studio"
        Refresh-Path
    }

    Write-Host ""
    Write-Host "  Après l'installation d'Android Studio :" -ForegroundColor Yellow
    Write-Host "  1. Lancez Android Studio et terminez le setup wizard" -ForegroundColor Yellow
    Write-Host "  2. Le SDK sera installé dans : $env:LOCALAPPDATA\Android\Sdk" -ForegroundColor Yellow
    Write-Host "  3. Relancez ce script" -ForegroundColor Yellow
    Pause-User "Appuyez sur Entrée une fois le SDK installé"
    Refresh-Path

    $sdkCandidates = @(
        "$env:LOCALAPPDATA\Android\Sdk",
        "$env:USERPROFILE\AppData\Local\Android\Sdk"
    ) | Where-Object { Test-Path $_ }
}

if ($sdkCandidates.Count -gt 0) {
    $env:ANDROID_HOME = $sdkCandidates[0]
    $env:ANDROID_SDK_ROOT = $env:ANDROID_HOME
    Write-OK "Android SDK : $env:ANDROID_HOME"
} else {
    Write-Warn "Android SDK toujours introuvable — Gradle tentera quand même de continuer."
}

# ── 5. Vérification keystore ─────────────────────────────────────────────────
Write-Step "Vérification du keystore"
if (-not (Test-Path $KeystoreFile)) {
    Write-Fail "Keystore introuvable : $KeystoreFile"
}
Write-OK "Keystore : $KeystoreFile"

# Lecture keystore.properties
$StorePwd = ""
$KeyAlias = ""
$KeyPwd   = ""

if (Test-Path $KeystoreProps) {
    Get-Content $KeystoreProps | ForEach-Object {
        if ($_ -match '^\s*storePassword\s*=\s*(.+)') { $StorePwd = $Matches[1].Trim() }
        if ($_ -match '^\s*keyAlias\s*=\s*(.+)')      { $KeyAlias  = $Matches[1].Trim() }
        if ($_ -match '^\s*keyPassword\s*=\s*(.+)')   { $KeyPwd   = $Matches[1].Trim() }
    }
}

if (-not $StorePwd -or -not $KeyAlias) {
    Write-Warn "keystore.properties incomplet — saisie manuelle."
    $StorePwd = Read-Host "  Mot de passe keystore (storePassword)"
    $KeyAlias  = Read-Host "  Alias de la clé (keyAlias)"
    $KeyPwd   = Read-Host "  Mot de passe de la clé (keyPassword)"
}
Write-OK "Alias keystore : $KeyAlias"

# ── 6. Choix du format ───────────────────────────────────────────────────────
Write-Step "Choix du format de sortie"
Write-Host ""
Write-Host "  Quel format souhaitez-vous générer ?" -ForegroundColor White
Write-Host "  [1] APK  — Installation directe / tests sur appareil" -ForegroundColor Green
Write-Host "  [2] AAB  — Android App Bundle (Google Play Store)"   -ForegroundColor Green
Write-Host ""

$FormatChoice = ""
do {
    $FormatChoice = Read-Host "  Votre choix [1 ou 2]"
} while ($FormatChoice -ne "1" -and $FormatChoice -ne "2")

if ($FormatChoice -eq "1") {
    $Format       = "APK"
    $GradleTask   = "assembleRelease"
} else {
    $Format       = "AAB"
    $GradleTask   = "bundleRelease"
}
Write-OK "Format sélectionné : $Format"

# ── 7. Variante ──────────────────────────────────────────────────────────────
Write-Step "Variante de build"
Write-Host ""
Write-Host "  [1] Release  — Production, signée, optimisée (défaut)" -ForegroundColor Green
Write-Host "  [2] Debug    — Développement (sans signature Play)"    -ForegroundColor Green
Write-Host ""
$VariantInput = Read-Host "  Votre choix [1 ou 2, défaut=1]"
if ($VariantInput -eq "2") {
    $GradleTask = $GradleTask -replace "release","debug"
    $Variant = "debug"
    Write-Warn "Mode debug sélectionné."
} else {
    $Variant = "release"
    Write-OK "Variante : release"
}

# ── 8. Dépendances npm ───────────────────────────────────────────────────────
Write-Step "Installation des dépendances npm"

Set-Location $AppDir
if (-not (Test-Path "node_modules")) {
    Write-Info "Première installation (peut prendre quelques minutes)…"
    & npm install --legacy-peer-deps
    if ($LASTEXITCODE -ne 0) { Write-Fail "npm install a échoué." }
} else {
    Write-Info "node_modules présents. Mise à jour…"
    & npm install --legacy-peer-deps --prefer-offline
}
Write-OK "Dépendances npm OK."

# ── 9. Nettoyage Gradle ──────────────────────────────────────────────────────
Write-Step "Nettoyage"
$CleanInput = Read-Host "  Effectuer un 'gradle clean' avant le build ? [O/n]"
if ($CleanInput -eq "" -or $CleanInput -match "^[OoYy]") {
    Set-Location $AndroidDir
    Write-Info "Nettoyage du projet Gradle…"
    & .\gradlew.bat clean
    if ($LASTEXITCODE -ne 0) { Write-Warn "Clean a retourné une erreur — continuons." }
    Write-OK "Clean terminé."
}

# ── 10. Build ────────────────────────────────────────────────────────────────
Write-Step "Compilation — $Format ($Variant)"
Set-Location $AndroidDir
Write-Info "Tâche Gradle : $GradleTask"
Write-Info "Cela peut prendre 5–15 min selon votre machine et connexion…"
Write-Host ""

$GradleArgs = @(
    $GradleTask,
    "-PKEYSTORE_PATH=$KeystoreFile",
    "-PKEYSTORE_STORE_PASSWORD=$StorePwd",
    "-PKEYSTORE_KEY_ALIAS=$KeyAlias",
    "-PKEYSTORE_KEY_PASSWORD=$KeyPwd"
)

& .\gradlew.bat @GradleArgs
if ($LASTEXITCODE -ne 0) {
    Write-Fail "Gradle a échoué (code $LASTEXITCODE). Consultez les logs ci-dessus."
}

# ── 11. Copie du fichier de sortie ───────────────────────────────────────────
Write-Step "Récupération du fichier généré"

New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"

if ($Format -eq "APK") {
    $Generated = Get-ChildItem -Path "$AndroidDir\app\build\outputs\apk" -Recurse -Filter "*.apk" |
                 Where-Object { $_.DirectoryName -match "release" } |
                 Select-Object -First 1
    if (-not $Generated) {
        $Generated = Get-ChildItem -Path "$AndroidDir\app\build\outputs\apk" -Recurse -Filter "*.apk" |
                     Select-Object -First 1
    }
    $Dest = Join-Path $OutputDir "ViaFerrata_$Timestamp.apk"
} else {
    $Generated = Get-ChildItem -Path "$AndroidDir\app\build\outputs\bundle" -Recurse -Filter "*.aab" |
                 Select-Object -First 1
    $Dest = Join-Path $OutputDir "ViaFerrata_$Timestamp.aab"
}

if (-not $Generated) {
    Write-Fail "Fichier généré introuvable dans le dossier de build."
}

Copy-Item $Generated.FullName -Destination $Dest
$FileSize = [math]::Round((Get-Item $Dest).Length / 1MB, 1)

# ── 12. Vérification signature APK ───────────────────────────────────────────
if ($Format -eq "APK" -and $env:ANDROID_HOME) {
    $ApkSigner = Get-ChildItem -Path "$env:ANDROID_HOME\build-tools" -Recurse -Filter "apksigner.bat" -ErrorAction SilentlyContinue |
                 Sort-Object DirectoryName -Descending | Select-Object -First 1
    if ($ApkSigner) {
        Write-Info "Vérification de la signature APK…"
        & $ApkSigner.FullName verify --print-certs $Dest | Select-Object -First 5
        Write-OK "Signature vérifiée."
    }
}

# ── 13. Résumé ───────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "╔════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║  BUILD TERMINÉ AVEC SUCCÈS                         ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Write-Host "  Format    : $Format ($Variant)"   -ForegroundColor White
Write-Host "  Fichier   : $Dest"                -ForegroundColor White
Write-Host "  Taille    : $FileSize MB"         -ForegroundColor White
Write-Host "  Keystore  : $KeyAlias @ $(Split-Path -Leaf $KeystoreFile)" -ForegroundColor White
Write-Host ""

if ($Format -eq "AAB") {
    Write-Host "  Le fichier .aab est prêt pour le Google Play Store." -ForegroundColor Cyan
    Write-Host "  https://play.google.com/console" -ForegroundColor Cyan
} else {
    Write-Host "  Installez l'APK sur un appareil Android :" -ForegroundColor Cyan
    Write-Host "  adb install `"$Dest`"" -ForegroundColor Cyan
}

Write-Host ""
# Ouvrir le dossier de sortie dans l'explorateur
$OpenExplorer = Read-Host "  Ouvrir le dossier de sortie dans l'Explorateur ? [O/n]"
if ($OpenExplorer -eq "" -or $OpenExplorer -match "^[OoYy]") {
    Start-Process explorer.exe $OutputDir
}
