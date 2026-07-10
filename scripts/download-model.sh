#!/usr/bin/env bash
# Télécharge un modèle TTS depuis les releases sherpa-onnx.
#
#   ./scripts/download-model.sh vits-piper-en_US-amy-low     # anglais, rapide (~65 Mo)
#   ./scripts/download-model.sh vits-piper-fr_FR-siwis-medium # français
#   ./scripts/download-model.sh kokoro-multi-lang-v1_0        # qualité premium (~330 Mo)
#
# Catalogue complet:
#   https://github.com/k2-fsa/sherpa-onnx/releases/tag/tts-models
set -euo pipefail

MODEL="${1:?usage: $0 <nom-du-modele> — ex: $0 vits-piper-fr_FR-siwis-medium}"
DEST="${MODELS_DIR:-$(dirname "$0")/../models}"

mkdir -p "$DEST"
if [[ -d "$DEST/$MODEL" ]]; then
    echo "Déjà présent: $DEST/$MODEL"
    exit 0
fi

URL="https://github.com/k2-fsa/sherpa-onnx/releases/download/tts-models/$MODEL.tar.bz2"
echo "Téléchargement de $MODEL…"
curl -fL --progress-bar "$URL" | tar xj -C "$DEST"
echo "✔ $DEST/$MODEL"
