#!/usr/bin/env bash
# Download TTS voice models for vocalizer.
#
# Sherpa-onnx models (catalogue):
#   https://github.com/k2-fsa/sherpa-onnx/releases/tag/tts-models
#
# Examples:
#   ./scripts/download-model.sh chatterbox                                  # realism flagship, 23 langs + cloning (~7.5 GB)
#   ./scripts/download-model.sh sherpa-onnx-supertonic-3-tts-int8-2026-05-11  # 31 languages, near-human (~120 MB)
#   ./scripts/download-model.sh sherpa-onnx-pocket-tts-int8-2026-01-26        # voice cloning en/fr (~95 MB)
#   ./scripts/download-model.sh kokoro-en-v0_19                               # English premium (~310 MB)
#   ./scripts/download-model.sh vits-piper-en_US-amy-low                      # English, fast (~65 MB)
set -euo pipefail

MODEL="${1:-}"
DEST="${MODELS_DIR:-$(dirname "$0")/../models}"

usage() {
    cat <<'EOF'
usage: ./scripts/download-model.sh <model>

Special model:
  chatterbox    Chatterbox multilingual weights (ResembleAI, MIT, ~7.5 GB)
                23 languages (ar, fr, en, …) + voice cloning from a 3-10 s WAV

Sherpa-onnx models (from GitHub releases):
  sherpa-onnx-supertonic-3-tts-int8-2026-05-11   31 languages, near-human (~120 MB)
  sherpa-onnx-pocket-tts-int8-2026-01-26         voice cloning en/fr (~95 MB)
  kokoro-en-v0_19                                English premium (~310 MB)
  vits-piper-en_US-amy-low                       English, fast (~65 MB)

Full catalog:
  https://github.com/k2-fsa/sherpa-onnx/releases/tag/tts-models

Environment:
  MODELS_DIR    destination directory (default: ./models)
EOF
}

download_chatterbox() {
    local dir="$DEST/chatterbox"
    mkdir -p "$dir"

    local files=(
        ve.safetensors
        t3_cfg.safetensors
        t3_mtl23ls_v2.safetensors
        t3_mtl23ls_v3.safetensors
        s3gen.safetensors
        tokenizer.json
        grapheme_mtl_merged_expanded_v1.json
        Cangjie5_TC.json
        conds.pt
    )

    echo "→ Downloading Chatterbox into $dir"
    for f in "${files[@]}"; do
        if [[ -f "$dir/$f" ]]; then
            echo "  ✓ $f (already present)"
            continue
        fi
        echo "  → $f"
        curl -fL --progress-bar -o "$dir/$f.part" \
            "https://huggingface.co/ResembleAI/chatterbox/resolve/main/$f"
        mv "$dir/$f.part" "$dir/$f"
    done
    echo "✔ $dir"
}

download_sherpa() {
    local name="$1"
    mkdir -p "$DEST"
    if [[ -d "$DEST/$name" ]]; then
        echo "Already present: $DEST/$name"
        exit 0
    fi

    local url="https://github.com/k2-fsa/sherpa-onnx/releases/download/tts-models/$name.tar.bz2"
    echo "→ Downloading $name…"
    curl -fL --progress-bar "$url" | tar xj -C "$DEST"
    echo "✔ $DEST/$name"
}

case "${MODEL:-}" in
    ""|-h|--help|help)
        usage
        [[ -n "${MODEL:-}" ]] || exit 1
        ;;
    chatterbox|chatterbox-multilingual)
        download_chatterbox
        ;;
    *)
        download_sherpa "$MODEL"
        ;;
esac
