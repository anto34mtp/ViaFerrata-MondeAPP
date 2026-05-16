#Requires -Version 5.1
Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Write-Step { param($msg) Write-Host "`n--- $msg ---" -ForegroundColor Cyan }
function Write-OK   { param($msg) Write-Host "[OK] $msg" -ForegroundColor Green }
function Write-Info { param($msg) Write-Host "[..] $msg" -ForegroundColor Cyan }
function Write-Warn { param($msg) Write-Host "[!!] $msg" -ForegroundColor Yellow }
function Write-Fail { param($msg) Write-Host "[XX] $msg" -ForegroundColor Red; exit 1 }

function Test-Command { param($name) return [bool](Get-Command $name -ErrorAction SilentlyContinue) }

function Refresh-Path {
    $env:PATH = [System.Environment]::GetEnvironmentVariable("PATH","Machine") + ";" +
                [System.Environment]::GetEnvironmentVariable("PATH","User")
}

function Install-Winget-Package {
    param([string]$Id, [string]$Label)
    Write-Info "Installation de $Label via winget..."
    winget install --id $Id --silent --accept-package-agreements --accept-source-agreements
}

Clear-Host
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  ViaFerrata-Monde -- Build Android (Windows)" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""

# Chemins
$ScriptDir    = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir       = Join-Path $ScriptDir "ViaFerrataApp"
$AndroidDir   = Join-Path $AppDir "android"
$KeystoreDir  = Join-Path $ScriptDir "keystore"
$KeystoreFile = Join-Path $KeystoreDir "viaferrata.keystore"
$KeystoreProps= Join-Path $KeystoreDir "keystore.properties"
$OutputDir    = Join-Path $ScriptDir "build_output"

# 1. Node.js
Write-Step "Verification Node.js"
Refresh-Path
if (-not (Test-Command "node")) {
    Write-Warn "Node.js non detecte."
    if (Test-Command "winget") {
        Install-Winget-Package "OpenJS.NodeJS.LTS" "Node.js LTS"
        Refresh-Path
    }
    if (-not (Test-Command "node")) {
        Write-Fail "Node.js introuvable. Installez depuis https://nodejs.org puis relancez."
    }
}
$nodeVer = node --version
Write-OK "Node.js $nodeVer"

# 2. Java
Write-Step "Verification Java (JDK 17+)"
Refresh-Path
if (-not (Test-Command "java")) {
    Write-Warn "Java non detecte."
    if (Test-Command "winget") {
        Install-Winget-Package "Microsoft.OpenJDK.17" "Microsoft OpenJDK 17"
        Refresh-Path
    }
    if (-not (Test-Command "java")) {
        Write-Fail "Java introuvable. Installez depuis https://adoptium.net puis relancez."
    }
}
$javaVer = & cmd /c "java -version 2>&1" | Select-Object -First 1
Write-OK "Java : $javaVer"

if ([string]::IsNullOrEmpty($env:JAVA_HOME)) {
    $javaExe = (Get-Command java -ErrorAction SilentlyContinue).Source
    if ($javaExe) {
        $env:JAVA_HOME = Split-Path -Parent (Split-Path -Parent $javaExe)
        Write-Info "JAVA_HOME = $env:JAVA_HOME"
    }
}

# 3. Android SDK
Write-Step "Verification Android SDK"
$sdkCandidates = @(
    $env:ANDROID_HOME,
    $env:ANDROID_SDK_ROOT,
    "$env:LOCALAPPDATA\Android\Sdk",
    "$env:USERPROFILE\AppData\Local\Android\Sdk",
    "C:\Android\Sdk"
) | Where-Object { $_ -and (Test-Path $_) }

if ($sdkCandidates.Count -eq 0) {
    Write-Warn "Android SDK non detecte."
    if (Test-Command "winget") {
        Write-Info "Installation d'Android Studio (inclut le SDK)..."
        Install-Winget-Package "Google.AndroidStudio" "Android Studio"
    }
    Write-Host ""
    Write-Host "Apres installation d'Android Studio :" -ForegroundColor Yellow
    Write-Host "  1. Lancez Android Studio et terminez le setup wizard" -ForegroundColor Yellow
    Write-Host "  2. Le SDK sera dans : $env:LOCALAPPDATA\Android\Sdk" -ForegroundColor Yellow
    Write-Host "  3. Relancez ce script" -ForegroundColor Yellow
    Read-Host "Appuyez sur Entree une fois le SDK installe" | Out-Null
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
    Write-Warn "Android SDK introuvable -- Gradle tentera de continuer."
}

# 4. Keystore
Write-Step "Verification du keystore"
if (-not (Test-Path $KeystoreFile)) {
    Write-Fail "Keystore introuvable : $KeystoreFile"
}
Write-OK "Keystore trouve : $KeystoreFile"

$StorePwd = ""
$KeyAlias  = ""
$KeyPwd   = ""

if (Test-Path $KeystoreProps) {
    Get-Content $KeystoreProps | ForEach-Object {
        if ($_ -match '^\s*storePassword\s*=\s*(.+)') { $StorePwd = $Matches[1].Trim() }
        if ($_ -match '^\s*keyAlias\s*=\s*(.+)')      { $KeyAlias  = $Matches[1].Trim() }
        if ($_ -match '^\s*keyPassword\s*=\s*(.+)')   { $KeyPwd   = $Matches[1].Trim() }
    }
}

if (-not $StorePwd -or -not $KeyAlias) {
    Write-Warn "keystore.properties incomplet -- saisie manuelle."
    $StorePwd = Read-Host "  storePassword"
    $KeyAlias  = Read-Host "  keyAlias"
    $KeyPwd   = Read-Host "  keyPassword"
}
Write-OK "Alias : $KeyAlias"

# 5. Format APK ou AAB
Write-Step "Format de sortie"
Write-Host "  [1] APK  -- installation directe / test appareil" -ForegroundColor White
Write-Host "  [2] AAB  -- Google Play Store" -ForegroundColor White
Write-Host ""
$FormatChoice = ""
do { $FormatChoice = Read-Host "  Choix [1 ou 2]" } while ($FormatChoice -ne "1" -and $FormatChoice -ne "2")

if ($FormatChoice -eq "1") {
    $Format     = "APK"
    $GradleTask = "assembleRelease"
} else {
    $Format     = "AAB"
    $GradleTask = "bundleRelease"
}

# 6. Release ou Debug
Write-Step "Variante"
Write-Host "  [1] Release -- production, signee (defaut)" -ForegroundColor White
Write-Host "  [2] Debug   -- developpement" -ForegroundColor White
Write-Host ""
$VariantInput = Read-Host "  Choix [1 ou 2, defaut=1]"
if ($VariantInput -eq "2") {
    $GradleTask = $GradleTask -replace "Release","Debug" -replace "release","debug"
    $Variant = "debug"
    Write-Warn "Mode debug."
} else {
    $Variant = "release"
    Write-OK "Variante : release"
}

# 7. npm install
Write-Step "Installation des dependances npm"
Set-Location $AppDir
if (-not (Test-Path "node_modules")) {
    Write-Info "Premiere installation..."
    & npm install --legacy-peer-deps
    if ($LASTEXITCODE -ne 0) { Write-Fail "npm install a echoue." }
} else {
    Write-Info "node_modules present. Mise a jour..."
    & npm install --legacy-peer-deps --prefer-offline
}
Write-OK "Dependances npm OK."

# 8. Gradle clean (optionnel)
Write-Step "Nettoyage"
$CleanInput = Read-Host "  Effectuer un gradle clean ? [O/n]"
if ($CleanInput -eq "" -or $CleanInput -match "^[OoYy]") {
    Set-Location $AndroidDir
    & .\gradlew.bat clean
    if ($LASTEXITCODE -ne 0) { Write-Warn "Clean a echoue -- on continue." }
    Write-OK "Clean termine."
}

# 9. Build
Write-Step "Compilation -- $Format ($Variant)"
Set-Location $AndroidDir
Write-Info "Tache Gradle : $GradleTask"
Write-Info "Cela peut prendre 5-15 min..."
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
    Write-Fail "Gradle a echoue (code $LASTEXITCODE). Voir les logs ci-dessus."
}

# 10. Copie du fichier final
Write-Step "Recuperation du fichier genere"
New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"

if ($Format -eq "APK") {
    $Generated = Get-ChildItem -Path "$AndroidDir\app\build\outputs\apk" -Recurse -Filter "*.apk" |
                 Sort-Object LastWriteTime -Descending | Select-Object -First 1
    $Dest = Join-Path $OutputDir "ViaFerrata_${Timestamp}.apk"
} else {
    $Generated = Get-ChildItem -Path "$AndroidDir\app\build\outputs\bundle" -Recurse -Filter "*.aab" |
                 Sort-Object LastWriteTime -Descending | Select-Object -First 1
    $Dest = Join-Path $OutputDir "ViaFerrata_${Timestamp}.aab"
}

if (-not $Generated) { Write-Fail "Fichier genere introuvable." }

Copy-Item $Generated.FullName -Destination $Dest
$FileSize = [math]::Round((Get-Item $Dest).Length / 1MB, 1)

# 11. Resume final
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  BUILD TERMINE AVEC SUCCES" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Format  : $Format ($Variant)" -ForegroundColor White
Write-Host "  Fichier : $Dest" -ForegroundColor White
Write-Host "  Taille  : $FileSize MB" -ForegroundColor White
$ksLeaf = Split-Path -Leaf $KeystoreFile
Write-Host "  Keystore: $KeyAlias @ $ksLeaf" -ForegroundColor White
Write-Host ""

if ($Format -eq "AAB") {
    Write-Host "  Pret pour le Google Play Store." -ForegroundColor Cyan
} else {
    Write-Host "  Installer sur un appareil : adb install `"$Dest`"" -ForegroundColor Cyan
}

Write-Host ""
$OpenExplorer = Read-Host "  Ouvrir le dossier build_output dans l'Explorateur ? [O/n]"
if ($OpenExplorer -eq "" -or $OpenExplorer -match "^[OoYy]") {
    Start-Process explorer.exe $OutputDir
}
