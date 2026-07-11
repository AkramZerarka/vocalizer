#!/usr/bin/env bash
# Install the vocalizer PHP extension from this repository's binaries.
#
#   curl -fsSL https://raw.githubusercontent.com/akramzerarka/vocalizer/main/install.sh | bash
#
# Optional variables:
#   VOCALIZER_REPO      GitHub repository               (default: akramzerarka/vocalizer)
#   VOCALIZER_BASE_URL  binary download base URL        (default: raw.githubusercontent.com for the repo)
#   VOCALIZER_EXT_DIR   installation directory          (default: PHP extension_dir)
#   PHP_BIN             php binary to target            (default: php)
set -euo pipefail

REPO="${VOCALIZER_REPO:-akramzerarka/vocalizer}"
BASE_URL="${VOCALIZER_BASE_URL:-https://raw.githubusercontent.com/$REPO/main/bin}"
PHP_BIN="${PHP_BIN:-php}"

err() { echo "✘ $*" >&2; exit 1; }

command -v "$PHP_BIN" >/dev/null || err "php not found (set PHP_BIN)"
command -v curl >/dev/null || err "curl is required"

# ---- platform compatibility -------------------------------------------------
[[ "$(uname -s)" == "Linux" ]] || err "Linux binaries only"
[[ "$(uname -m)" == "x86_64" ]] || err "architecture $(uname -m) not covered by the binaries"
if ldd --version 2>&1 | grep -qi musl; then
    err "musl distro (Alpine) not covered by the binaries"
fi

MINOR="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
ZTS="$("$PHP_BIN" -r 'echo ZEND_THREAD_SAFE ? "zts" : "nts";')"
[[ "$ZTS" == "nts" ]] || err "PHP ZTS detected: only NTS binaries are provided"

ASSET="vocalizer-php${MINOR}-nts-linux-x86_64.so"

# ---- download + integrity verification --------------------------------------
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
echo "→ Downloading $ASSET"
curl -fSL --progress-bar -o "$TMP/$ASSET" "$BASE_URL/$ASSET" \
    || err "binary unavailable for PHP $MINOR — supported versions: 8.4, 8.5"
if curl -fsSL -o "$TMP/SHA256SUMS" "$BASE_URL/SHA256SUMS" 2>/dev/null; then
    (cd "$TMP" && grep " $ASSET\$" SHA256SUMS | sha256sum -c --quiet) \
        || err "integrity verification failed (SHA256)"
    echo "→ Integrity verified (SHA256)"
fi

# ---- installation -----------------------------------------------------------
EXT_DIR="${VOCALIZER_EXT_DIR:-$("$PHP_BIN" -r 'echo ini_get("extension_dir");')}"
SUDO=""
[[ -w "$EXT_DIR" ]] || SUDO="sudo"
echo "→ Installing into $EXT_DIR"
$SUDO cp "$TMP/$ASSET" "$EXT_DIR/vocalizer.so"

# ---- activation -------------------------------------------------------------
if [[ -n "${VOCALIZER_EXT_DIR:-}" ]]; then
    echo "✔ Copied. Enable with: php -d extension=$EXT_DIR/vocalizer.so"
    exit 0
fi

enable_done=""
if command -v phpenmod >/dev/null && [[ -d "/etc/php/$MINOR/mods-available" ]]; then
    echo "extension=vocalizer.so" | $SUDO tee "/etc/php/$MINOR/mods-available/vocalizer.ini" >/dev/null
    $SUDO phpenmod -v "$MINOR" vocalizer
    enable_done=1
elif [[ -d /etc/php.d ]]; then
    echo "extension=vocalizer.so" | $SUDO tee /etc/php.d/40-vocalizer.ini >/dev/null
    enable_done=1
fi

if [[ -z "$enable_done" ]]; then
    echo "⚠ Manually add 'extension=vocalizer.so' to your php.ini"
elif "$PHP_BIN" -m | grep -q vocalizer; then
    echo "✔ vocalizer installed and enabled (PHP $MINOR)"
else
    echo "⚠ Installed, but reload PHP-FPM / check your php.ini"
fi
echo "Voices: ./scripts/download-model.sh --help  (chatterbox, supertonic, pocket, kokoro, piper…)"
