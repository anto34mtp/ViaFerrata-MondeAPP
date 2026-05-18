#!/usr/bin/env bash
# =============================================================================
#  ViaFerrata-Monde — Script de build Android interactif
#  Usage : ./build.sh
#  Prérequis : Java 11+, Android SDK (ANDROID_HOME ou ANDROID_SDK_ROOT défini)
# =============================================================================

set -euo pipefail

# ── Couleurs ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

log_info()    { echo -e "${CYAN}ℹ  $*${NC}"; }
log_success() { echo -e "${GREEN}✔  $*${NC}"; }
log_warn()    { echo -e "${YELLOW}⚠  $*${NC}"; }
log_error()   { echo -e "${RED}✖  $*${NC}"; }
log_step()    { echo -e "\n${BOLD}${CYAN}━━━  $*${NC}\n"; }

# ── Chemins ───────────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$SCRIPT_DIR/ViaFerrataApp"
ANDROID_DIR="$APP_DIR/android"
KEYSTORE_DIR="$SCRIPT_DIR/keystore"
KEYSTORE_FILE="$KEYSTORE_DIR/viaferrata.keystore"
KEYSTORE_PROPS="$KEYSTORE_DIR/keystore.properties"
OUTPUT_DIR="$SCRIPT_DIR/build_output"

# ── Bannière ──────────────────────────────────────────────────────────────────
echo -e "${BOLD}"
cat << 'BANNER'
  ╔═══════════════════════════════════════════════════╗
  ║     ViaFerrata-Monde — Build Android              ║
  ║     Signature automatique via Play Store keystore ║
  ╚═══════════════════════════════════════════════════╝
BANNER
echo -e "${NC}"

# ── 1. Vérification des prérequis ─────────────────────────────────────────────
log_step "Vérification des prérequis"

# Java
if ! command -v java &>/dev/null; then
    log_error "Java introuvable. Installez Java 11+ (ou Android Studio inclut un JDK)."
    exit 1
fi
JAVA_VER=$(java -version 2>&1 | head -1)
log_success "Java détecté : $JAVA_VER"

# Node.js
if ! command -v node &>/dev/null; then
    log_error "Node.js introuvable. Installez Node.js 18+."
    exit 1
fi
log_success "Node.js $(node --version) détecté"

# Android SDK
if [ -z "${ANDROID_HOME:-}" ] && [ -z "${ANDROID_SDK_ROOT:-}" ]; then
    # Tentative de détection automatique
    if [ -d "$HOME/Library/Android/sdk" ]; then
        export ANDROID_HOME="$HOME/Library/Android/sdk"
    elif [ -d "$HOME/Android/Sdk" ]; then
        export ANDROID_HOME="$HOME/Android/Sdk"
    elif [ -d "/opt/android-sdk" ]; then
        export ANDROID_HOME="/opt/android-sdk"
    else
        log_warn "ANDROID_HOME non défini. Définissez la variable d'environnement."
        log_warn "Exemple : export ANDROID_HOME=\$HOME/Android/Sdk"
        log_warn "Le build tentera quand même de continuer via le wrapper Gradle…"
    fi
fi
if [ -n "${ANDROID_HOME:-}" ]; then
    log_success "Android SDK : $ANDROID_HOME"
fi

# Keystore
if [ ! -f "$KEYSTORE_FILE" ]; then
    log_error "Keystore introuvable : $KEYSTORE_FILE"
    exit 1
fi
log_success "Keystore : $KEYSTORE_FILE"

# Dossier projet
if [ ! -d "$APP_DIR" ]; then
    log_error "Dossier application introuvable : $APP_DIR"
    exit 1
fi

# ── 2. Choix du format de sortie ──────────────────────────────────────────────
log_step "Format de sortie"

echo -e "  ${BOLD}Quel format souhaitez-vous générer ?${NC}"
echo -e "  ${GREEN}1)${NC} APK  — Installation directe sur un appareil / tests"
echo -e "  ${GREEN}2)${NC} AAB  — Android App Bundle (requis pour le Google Play Store)"
echo ""

FORMAT_CHOICE=""
while [[ "$FORMAT_CHOICE" != "1" && "$FORMAT_CHOICE" != "2" ]]; do
    read -rp "  Votre choix [1/2] : " FORMAT_CHOICE
    if [[ "$FORMAT_CHOICE" != "1" && "$FORMAT_CHOICE" != "2" ]]; then
        log_warn "Saisie invalide, entrez 1 (APK) ou 2 (AAB)."
    fi
done

if [[ "$FORMAT_CHOICE" == "1" ]]; then
    FORMAT="APK"
    GRADLE_TASK="assembleRelease"
else
    FORMAT="AAB"
    GRADLE_TASK="bundleRelease"
fi

log_success "Format sélectionné : ${BOLD}$FORMAT${NC}"

# ── 3. Variante de build ───────────────────────────────────────────────────────
log_step "Variante de build"

echo -e "  ${BOLD}Variante ?${NC}"
echo -e "  ${GREEN}1)${NC} Release  — Production (optimisée, signée, minifiée)"
echo -e "  ${GREEN}2)${NC} Debug    — Développement (non signée avec le keystore Play)"
echo ""

VARIANT_CHOICE="1"
read -rp "  Votre choix [1/2, défaut=1] : " VARIANT_INPUT
VARIANT_INPUT="${VARIANT_INPUT:-1}"

if [[ "$VARIANT_INPUT" == "2" ]]; then
    VARIANT="debug"
    GRADLE_TASK="${GRADLE_TASK/release/debug}"
    log_warn "Mode debug sélectionné — la signature Play Store ne s'applique pas."
else
    VARIANT="release"
fi
log_success "Variante : ${BOLD}$VARIANT${NC}"

# ── 4. Installation des dépendances npm ───────────────────────────────────────
log_step "Dépendances Node.js"

cd "$APP_DIR"

if [ ! -d "node_modules" ]; then
    log_info "Installation des dépendances npm (première fois — peut prendre quelques minutes)…"
    npm install --legacy-peer-deps 2>&1 | tail -5
    log_success "Dépendances installées."
else
    log_info "node_modules présents. Vérification des mises à jour…"
    npm install --legacy-peer-deps --prefer-offline 2>&1 | tail -3
    log_success "Dépendances à jour."
fi

# ── 5. Configuration du keystore ──────────────────────────────────────────────
log_step "Configuration de la signature"

# Lecture des propriétés keystore
STORE_FILE="$KEYSTORE_FILE"
STORE_PASSWORD=""
KEY_ALIAS=""
KEY_PASSWORD=""

if [ -f "$KEYSTORE_PROPS" ]; then
    while IFS='=' read -r key value; do
        key=$(echo "$key" | tr -d '[:space:]')
        value=$(echo "$value" | tr -d '[:space:]')
        case "$key" in
            storePassword) STORE_PASSWORD="$value" ;;
            keyAlias)      KEY_ALIAS="$value" ;;
            keyPassword)   KEY_PASSWORD="$value" ;;
        esac
    done < "$KEYSTORE_PROPS"
fi

if [ -z "$STORE_PASSWORD" ] || [ -z "$KEY_ALIAS" ]; then
    log_warn "Impossible de lire keystore.properties — saisie manuelle requise."
    read -rsp "  Mot de passe du keystore (storePassword) : " STORE_PASSWORD; echo
    read -rp  "  Alias de la clé (keyAlias) : " KEY_ALIAS
    read -rsp "  Mot de passe de la clé (keyPassword) : " KEY_PASSWORD; echo
fi

log_success "Alias keystore : $KEY_ALIAS"

# Injecter les variables dans l'environnement Gradle
export KEYSTORE_PATH="$STORE_FILE"
export KEYSTORE_STORE_PASSWORD="$STORE_PASSWORD"
export KEYSTORE_KEY_ALIAS="$KEY_ALIAS"
export KEYSTORE_KEY_PASSWORD="$KEY_PASSWORD"

# ── 6. Nettoyage optionnel ─────────────────────────────────────────────────────
log_step "Nettoyage"

read -rp "  Effectuer un clean Gradle avant le build ? [O/n] : " CLEAN_INPUT
CLEAN_INPUT="${CLEAN_INPUT:-O}"
if [[ "${CLEAN_INPUT,,}" == "o" || "${CLEAN_INPUT,,}" == "y" ]]; then
    log_info "Nettoyage du projet Android…"
    cd "$ANDROID_DIR"
    ./gradlew clean 2>&1 | tail -5
    log_success "Clean terminé."
fi

# ── 7. Build ───────────────────────────────────────────────────────────────────
log_step "Compilation — $FORMAT $VARIANT"

cd "$ANDROID_DIR"

log_info "Lancement de Gradle : $GRADLE_TASK"
log_info "Cela peut prendre 5–15 minutes selon votre machine…"
echo ""

./gradlew "$GRADLE_TASK" \
    -PKEYSTORE_PATH="$STORE_FILE" \
    -PKEYSTORE_STORE_PASSWORD="$STORE_PASSWORD" \
    -PKEYSTORE_KEY_ALIAS="$KEY_ALIAS" \
    -PKEYSTORE_KEY_PASSWORD="$KEY_PASSWORD" \
    --stacktrace 2>&1 | grep -E "(BUILD|FAILURE|error|warning|Task|Deprecated)" | head -50 || true

# Vérification du succès Gradle
./gradlew "$GRADLE_TASK" \
    -PKEYSTORE_PATH="$STORE_FILE" \
    -PKEYSTORE_STORE_PASSWORD="$STORE_PASSWORD" \
    -PKEYSTORE_KEY_ALIAS="$KEY_ALIAS" \
    -PKEYSTORE_KEY_PASSWORD="$KEY_PASSWORD"

# ── 8. Localisation et copie du fichier généré ────────────────────────────────
log_step "Récupération du fichier généré"

mkdir -p "$OUTPUT_DIR"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

if [[ "$FORMAT" == "APK" ]]; then
    GENERATED=$(find "$ANDROID_DIR/app/build/outputs/apk/release" -name "*.apk" 2>/dev/null | head -1)
    if [ -z "$GENERATED" ]; then
        GENERATED=$(find "$ANDROID_DIR/app/build/outputs/apk" -name "*.apk" 2>/dev/null | head -1)
    fi
    DEST="$OUTPUT_DIR/ViaFerrata_${TIMESTAMP}.apk"
else
    GENERATED=$(find "$ANDROID_DIR/app/build/outputs/bundle/release" -name "*.aab" 2>/dev/null | head -1)
    if [ -z "$GENERATED" ]; then
        GENERATED=$(find "$ANDROID_DIR/app/build/outputs/bundle" -name "*.aab" 2>/dev/null | head -1)
    fi
    DEST="$OUTPUT_DIR/ViaFerrata_${TIMESTAMP}.aab"
fi

if [ -z "$GENERATED" ]; then
    log_error "Fichier généré introuvable. Vérifiez les logs Gradle ci-dessus."
    exit 1
fi

cp "$GENERATED" "$DEST"
FILE_SIZE=$(du -sh "$DEST" | cut -f1)

# ── 9. Vérification de la signature ───────────────────────────────────────────
log_step "Vérification de la signature"

if command -v apksigner &>/dev/null && [[ "$FORMAT" == "APK" ]]; then
    apksigner verify --print-certs "$DEST" | head -5 && log_success "Signature APK vérifiée."
elif [ -n "${ANDROID_HOME:-}" ] && [[ "$FORMAT" == "APK" ]]; then
    APKSIGNER="$ANDROID_HOME/build-tools"
    APKSIGNER_BIN=$(find "$APKSIGNER" -name "apksigner" -type f 2>/dev/null | sort -r | head -1)
    if [ -n "$APKSIGNER_BIN" ]; then
        "$APKSIGNER_BIN" verify --print-certs "$DEST" | head -5 && log_success "Signature APK vérifiée."
    fi
else
    log_warn "apksigner non disponible — signature non vérifiée automatiquement."
fi

# ── 10. Résumé final ──────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${GREEN}╔════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║  BUILD TERMINÉ AVEC SUCCÈS                         ║${NC}"
echo -e "${BOLD}${GREEN}╚════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${BOLD}Format     :${NC} $FORMAT ($VARIANT)"
echo -e "  ${BOLD}Fichier    :${NC} $DEST"
echo -e "  ${BOLD}Taille     :${NC} $FILE_SIZE"
echo -e "  ${BOLD}Keystore   :${NC} $KEY_ALIAS @ $(basename "$STORE_FILE")"
echo ""

if [[ "$FORMAT" == "AAB" ]]; then
    echo -e "  ${CYAN}Le fichier .aab est prêt à être uploadé sur le Google Play Store.${NC}"
    echo -e "  ${CYAN}Rendez-vous sur : https://play.google.com/console${NC}"
elif [[ "$FORMAT" == "APK" ]]; then
    echo -e "  ${CYAN}Installez l'APK sur un appareil Android :${NC}"
    echo -e "  ${CYAN}  adb install \"$DEST\"${NC}"
fi
echo ""
