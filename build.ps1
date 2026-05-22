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

# ── Redirection des caches vers D: ──────────────────────────────────────────
# Gradle cache peut depasser 10 Go ; npm cache plusieurs Go.
# Si D: existe, on redirige pour liberer C:.
Write-Step "Espace disque et caches"
if (Test-Path "D:\") {
    $env:GRADLE_USER_HOME = "D:\GradleCache"
    $env:NPM_CONFIG_CACHE = "D:\NpmCache"
    New-Item -ItemType Directory -Path $env:GRADLE_USER_HOME -Force | Out-Null
    New-Item -ItemType Directory -Path $env:NPM_CONFIG_CACHE -Force | Out-Null
    Write-OK "Cache Gradle → $($env:GRADLE_USER_HOME)"
    Write-OK "Cache npm    → $($env:NPM_CONFIG_CACHE)"
} else {
    Write-Info "Disque D non disponible -- caches sur C: par defaut."
}
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
$sdkCandidates = @(@(
    $env:ANDROID_HOME,
    $env:ANDROID_SDK_ROOT,
    "$env:LOCALAPPDATA\Android\Sdk",
    "$env:USERPROFILE\AppData\Local\Android\Sdk",
    "C:\Android\Sdk"
) | Where-Object { $_ -and (Test-Path $_) })

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
    $sdkCandidates = @(@(
        "$env:LOCALAPPDATA\Android\Sdk",
        "$env:USERPROFILE\AppData\Local\Android\Sdk"
    ) | Where-Object { Test-Path $_ })
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
Write-Host "  [2] AAB  -- Google Play Store (incremente la version automatiquement)" -ForegroundColor White
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

# 6b. Auto-increment de version pour Play Store (AAB Release uniquement)
$BuildGradlePath = Join-Path $AndroidDir "app\build.gradle"
if ($Format -eq "AAB" -and $Variant -eq "release") {
    Write-Step "Increment automatique de la version (Play Store)"
    $GradleContent = Get-Content $BuildGradlePath -Raw

    # Incrementer versionCode
    if ($GradleContent -match 'versionCode\s+(\d+)') {
        $OldCode = [int]$Matches[1]
        $NewCode = $OldCode + 1
        $GradleContent = $GradleContent -replace "versionCode\s+$OldCode\b", "versionCode $NewCode"
        Write-OK "versionCode : $OldCode  -->  $NewCode"
    }

    # Incrementer le patch de versionName  (ex: "1.0.3" --> "1.0.4")
    if ($GradleContent -match 'versionName\s+"(\d+)\.(\d+)\.(\d+)"') {
        $Maj = $Matches[1]; $Min = $Matches[2]; $Pat = [int]$Matches[3]
        $OldName = "$Maj.$Min.$Pat"
        $NewName = "$Maj.$Min.$($Pat + 1)"
        $GradleContent = $GradleContent -replace "versionName\s+`"$OldName`"", "versionName `"$NewName`""
        Write-OK "versionName : $OldName  -->  $NewName"
    }

    Set-Content $BuildGradlePath -Value $GradleContent -NoNewline
    Write-OK "build.gradle mis a jour."
} else {
    # Lire les versions actuelles pour les afficher dans le resume
    $GradleContent = Get-Content $BuildGradlePath -Raw
    if ($GradleContent -match 'versionCode\s+(\d+)') { $NewCode = [int]$Matches[1] }
    if ($GradleContent -match 'versionName\s+"([^"]+)"') { $NewName = $Matches[1] }
    Write-Info "Version actuelle : $NewName (code $NewCode) -- pas d'increment en mode $Format/$Variant."
}

# 7. npm install
Write-Step "Installation des dependances npm"
Set-Location $AppDir

# Verifier si les versions installees correspondent aux versions epinglees.
# Si non, supprimer node_modules et reinstaller (evite les caches de versions trop neuves).
$ExpectedVersions = @{
    "react-native-maps"    = "1.10.3"
    "react-native-screens" = "3.31.0"
}
$NeedsReinstall = $false
if (Test-Path "node_modules") {
    foreach ($pkg in $ExpectedVersions.Keys) {
        $pkgJson = Join-Path $AppDir "node_modules\$pkg\package.json"
        if (Test-Path $pkgJson) {
            $installedVer = (Get-Content $pkgJson -Raw | ConvertFrom-Json).version
            if ($installedVer -ne $ExpectedVersions[$pkg]) {
                Write-Warn "Version incorrecte pour $pkg : $installedVer (attendu $($ExpectedVersions[$pkg]))"
                $NeedsReinstall = $true
            }
        } else {
            $NeedsReinstall = $true
        }
    }
}

if ($NeedsReinstall) {
    Write-Warn "Suppression de node_modules pour reinstallation propre..."
    Remove-Item -Recurse -Force "node_modules" -ErrorAction SilentlyContinue
    if (Test-Path "package-lock.json") { Remove-Item -Force "package-lock.json" }
}

# --ignore-scripts evite le prepare de react-native-screens 3.31+ (bob build)
# qui n'est necessaire que pour le dev TypeScript, pas pour le build Android.
# Le postinstall (patch-rngp.js) est relance manuellement apres.
if (-not (Test-Path "node_modules")) {
    Write-Info "Installation des dependances..."
    & npm.cmd install --legacy-peer-deps --ignore-scripts
    if ($LASTEXITCODE -ne 0) { Write-Fail "npm install a echoue." }
} else {
    Write-Info "node_modules present. Mise a jour..."
    & npm.cmd install --legacy-peer-deps --ignore-scripts --prefer-offline
}
Write-OK "Dependances npm OK."

# 8. Patch RNGP (serviceOf supprime dans Gradle 8.8+)
Write-Step "Patch compatibilite RNGP / Gradle 8.13"
$PatchScript = Join-Path $AppDir "scripts\patch-rngp.js"
if (Test-Path $PatchScript) {
    & node $PatchScript
    if ($LASTEXITCODE -ne 0) { Write-Warn "Patch RNGP a echoue -- on continue." }
} else {
    Write-Warn "Script de patch introuvable : $PatchScript"
}

# 9. Pre-generation du bundle JS (evite "Unable to load script" sur Debug et Release)
Write-Step "Generation du bundle JS"
$AssetsDir = Join-Path $AndroidDir "app\src\main\assets"
New-Item -ItemType Directory -Path $AssetsDir -Force | Out-Null
$DevMode = if ($Variant -eq "debug") { "true" } else { "false" }
Set-Location $AppDir
Write-Info "npx react-native bundle --dev $DevMode ..."
& npx.cmd react-native bundle `
    --platform android `
    --dev $DevMode `
    --entry-file index.js `
    --bundle-output "$AssetsDir\index.android.bundle" `
    --assets-dest "$AndroidDir\app\src\main\res"
if ($LASTEXITCODE -ne 0) { Write-Fail "Echec de la generation du bundle JS." }
Write-OK "Bundle JS genere : $AssetsDir\index.android.bundle"

# 11. Gradle clean (optionnel)
Write-Step "Nettoyage"
$CleanInput = Read-Host "  Effectuer un gradle clean ? [O/n]"
if ($CleanInput -eq "" -or $CleanInput -match "^[OoYy]") {
    Set-Location $AndroidDir
    & .\gradlew.bat clean
    if ($LASTEXITCODE -ne 0) { Write-Warn "Clean a echoue -- on continue." }
    Write-OK "Clean termine."
}

# 12. Build
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

$BuildStart = Get-Date
& .\gradlew.bat @GradleArgs
$GradleExit = $LASTEXITCODE

# gradlew.bat sur Windows peut retourner 1 meme en cas de succes (bug connu).
# On verifie donc l'existence du fichier genere plutot que le code de retour.

# 13. Copie du fichier final
Write-Step "Recuperation du fichier genere"
New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"

if ($Format -eq "APK") {
    $Generated = Get-ChildItem -Path "$AndroidDir\app\build\outputs\apk" -Recurse -Filter "*.apk" -ErrorAction SilentlyContinue |
                 Sort-Object LastWriteTime -Descending | Select-Object -First 1
    $Dest = Join-Path $OutputDir "ViaFerrata_${Timestamp}.apk"
} else {
    $Generated = Get-ChildItem -Path "$AndroidDir\app\build\outputs\bundle" -Recurse -Filter "*.aab" -ErrorAction SilentlyContinue |
                 Sort-Object LastWriteTime -Descending | Select-Object -First 1
    $Dest = Join-Path $OutputDir "ViaFerrata_${Timestamp}.aab"
}

if (-not $Generated -or $Generated.LastWriteTime -lt $BuildStart) {
    # Aucun fichier recent => le build a echoue (le fichier trouve est un ancien build)
    Write-Fail "Build echoue (code Gradle: $GradleExit) -- aucun fichier recent genere. Voir les logs ci-dessus."
}
# Le fichier est plus recent que le debut du build => succes reel

Copy-Item $Generated.FullName -Destination $Dest
$FileSize = [math]::Round((Get-Item $Dest).Length / 1MB, 1)

# 14. Resume final
Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  BUILD TERMINE AVEC SUCCES" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Format  : $Format ($Variant)" -ForegroundColor White
Write-Host "  Version : $NewName (code $NewCode)" -ForegroundColor White
Write-Host "  Fichier : $Dest" -ForegroundColor White
Write-Host "  Taille  : $FileSize MB" -ForegroundColor White
$ksLeaf = Split-Path -Leaf $KeystoreFile
Write-Host "  Keystore: $KeyAlias @ $ksLeaf" -ForegroundColor White
Write-Host ""

if ($Format -eq "AAB") {
    Write-Host "  Pret pour le Google Play Store." -ForegroundColor Cyan
} else {
    Write-Host "  IMPORTANT : Desinstalle d'abord l'ancienne version du tel" -ForegroundColor Yellow
    Write-Host "              (Parametres > Apps > ViaFerrata > Desinstaller)" -ForegroundColor Yellow
    Write-Host "  Installer sur un appareil : adb install `"$Dest`"" -ForegroundColor Cyan
}

Write-Host ""
$OpenExplorer = Read-Host "  Ouvrir le dossier build_output dans l'Explorateur ? [O/n]"
if ($OpenExplorer -eq "" -or $OpenExplorer -match "^[OoYy]") {
    Start-Process explorer.exe $OutputDir
}
