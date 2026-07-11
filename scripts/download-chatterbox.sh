#!/usr/bin/env bash
# Downloads the official Chatterbox multilingual weights (ResembleAI, MIT)
# from Hugging Face into models/chatterbox (~7.5 GB).
#
# Chatterbox is the realism flagship: 23 languages (ar, fr, en, de, es, hi,
# it, ko, pt, tr, …) with voice cloning from a 3-10 s reference WAV.
set -euo pipefail

DEST="${MODELS_DIR:-$(dirname "$0")/../models}/chatterbox"
mkdir -p "$DEST"

FILES=(ve.safetensors t3_cfg.safetensors t3_mtl23ls_v2.safetensors
       t3_mtl23ls_v3.safetensors s3gen.safetensors tokenizer.json
       grapheme_mtl_merged_expanded_v1.json Cangjie5_TC.json conds.pt)

for f in "${FILES[@]}"; do
    if [[ -f "$DEST/$f" ]]; then
        echo "✓ $f (already present)"
        continue
    fi
    echo "→ $f"
    curl -fL --progress-bar -o "$DEST/$f.part" \
        "https://huggingface.co/ResembleAI/chatterbox/resolve/main/$f"
    mv "$DEST/$f.part" "$DEST/$f"
done
echo "✔ $DEST"
