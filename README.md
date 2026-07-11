# vocalizer — text-to-speech for PHP

Native PHP extension for speech synthesis (TTS). Embeds
[sherpa-onnx](https://github.com/k2-fsa/sherpa-onnx) (Supertonic, Piper, Pocket,
Kokoro, …) and [audio.cpp](https://github.com/ggml-org/audio.cpp) (Chatterbox
voice cloning). **Ready-to-use binaries** — no compilation, no dependencies to install.

- Eight model families through one engine: **Chatterbox** (LLM-TTS realism
  flagship — beats ElevenLabs in blind tests, 23 languages incl. Arabic &
  French, voice cloning from any 3–10 s WAV), **Supertonic 3** (31 languages,
  near-human, real-time CPU), **Pocket TTS** (zero-shot voice cloning),
  **ZipVoice** (zh/en cloning), **Kokoro** (near-commercial English),
  **VITS/Piper** (50+ languages, fastest), **Kitten**, **Matcha** —
  auto-detected from the model directory
- Model loaded **once** per process (instant between FPM requests)
- **Self-healing**: a synthesis engine crash never kills PHP
  (process isolation, retry, automatic model reload)
- Async (`speakAsync`), timeouts, built-in statistics
- Output: 16-bit WAV file/string or raw float32 PCM for streaming

## Installation

```bash
curl -fsSL https://raw.githubusercontent.com/akramzerarka/vocalizer/main/install.sh | bash
```

Requirements: Linux x86-64 (glibc ≥ 2.28 — Ubuntu 20.04+, Debian 10+,
RHEL/Alma 8+, Fedora 29+…), PHP **8.4 or 8.5** NTS. Binary integrity is
verified via SHA256. (Alpine/musl, ARM, and PHP ZTS are not supported.)

Download a voice model (`./scripts/download-model.sh --help` for the full list):

```bash
./scripts/download-model.sh chatterbox                                    # realism + cloning (~7.5 GB)
./scripts/download-model.sh sherpa-onnx-supertonic-3-tts-int8-2026-05-11  # 31 languages (~120 MB)
./scripts/download-model.sh sherpa-onnx-pocket-tts-int8-2026-01-26        # voice cloning en/fr (~95 MB)
./scripts/download-model.sh vits-piper-en_US-amy-low                      # English, fast (~65 MB)
```

Sherpa-onnx catalog: [tts-models](https://github.com/k2-fsa/sherpa-onnx/releases).

## Quick start

```php
use Vocalizer\Engine;

$engine = Engine::load('/opt/voices/sherpa-onnx-supertonic-3-tts-int8-2026-05-11');

$res = $engine->speak('Votre commande est prête.', [
    'lang'       => 'fr',
    'voice'      => 0,
    'speed'      => 1.0,
    'num_steps'  => 8,
    'timeout_ms' => 30_000,
]);

$res->save('/var/www/audio/notice.wav');
echo $res->seconds;        // audio duration
echo $res->generationMs;   // compute time
```

## API options

**Common fields** (all models): `lang`, `voice`, `speed`, `threads`, `timeout_ms`,
`reference`, `reference_text`, `num_steps`.

**Model-specific fields** go in `opts` (PHP array or JSON string). Unknown keys are
ignored by models that do not use them.

```php
// Chatterbox — voice cloning
$engine = Engine::load('/opt/voices/chatterbox', [
    'threads' => 4,
    'opts' => ['profile' => 'premium', 'weight_type' => 'f16'],
]);
$res = $engine->speak('مرحبا بكم', [
    'lang'      => 'ar',
    'reference' => '/opt/voices/refs/ar.wav',
    'opts'      => ['temperature' => 0.6, 'repetition_penalty' => 1.2, 'seed' => 42],
    'timeout_ms' => 600_000,
]);

// Supertonic — preset voices, no reference needed
$engine = Engine::load('/opt/voices/sherpa-onnx-supertonic-3-tts-int8-2026-05-11');
$res = $engine->speak('Votre commande est prête.', ['lang' => 'fr', 'voice' => 0]);
```

Legacy aliases: `ref_audio` → `reference`, `ref_text` → `reference_text`,
`extra` (JSON) → `opts`.

Full API (IDE autocompletion): [stubs/vocalizer.stub.php](stubs/vocalizer.stub.php).
Example: [examples/speak.php](examples/speak.php).

## Premium 7 languages

Supported: **fr, en, es, it, de, pt, ar** — one Chatterbox model, voice cloning
per language from a 3–10 s reference WAV in that language.

```bash
./scripts/download-model.sh chatterbox
# refs/{fr,en,es,it,de,pt,ar}.wav — one WAV per language (3–10 s, mono)
```

```php
require 'examples/lib/PremiumTts.php';

$tts = PremiumTts::load('/opt/voices/chatterbox', '/opt/voices/refs', [
    'threads' => 4,
]);

$res = $tts->speak('Bonjour, votre commande est prête.', PremiumTts::LANG_FR);
$res->save('/tmp/out.wav');
```

Or natively with `opts.profile`:

```php
// php.ini: vocalizer.isolation = direct
$engine = Engine::load('/opt/voices/chatterbox', [
    'opts' => ['profile' => 'premium'],
]);
$res = $engine->speak($text, [
    'lang'      => 'es',
    'reference' => '/opt/voices/refs/es.wav',
    'timeout_ms' => 600_000,
]);
```

**Quality tips:**
- Reference WAV **in the target language** (do not reuse a French clip for Arabic).
- Arabic text with **tashkeel** (diacritics) for correct pronunciation.
- `temperature` 0.5–0.7, `repetition_penalty` ≥ 1.2 to limit hallucinations.
- Chatterbox needs `vocalizer.isolation = direct` (persistent thread pool).
- CUDA recommended for production throughput; CPU is ~20× slower than real time.

## Supertonic

```php
$engine = Engine::load('/opt/voices/sherpa-onnx-supertonic-3-tts-int8-2026-05-11');

$res = $engine->speak('Votre commande est prête.', [
    'lang'  => 'fr',   // required
    'voice' => 0,      // 0–9
    'speed' => 1.0,
]);

// Arabic — use tashkeel
$res = $engine->speak('مَرْحَبًا بِكُمْ', ['lang' => 'ar', 'voice' => 0]);
```

`lang` is required. Default `num_steps` is 8 when not specified.

## Voice cloning (Pocket TTS)

```php
$engine = Engine::load('/opt/voices/sherpa-onnx-pocket-tts-int8-2026-01-26');
$res = $engine->speak('Hello, I speak with the cloned voice.', [
    'reference' => '/opt/voices/my-voice.wav',
    'opts'      => ['temperature' => 0.7, 'seed' => 42],
]);
```

## Async

```php
$job = $engine->speakAsync($paragraph);
$res = $job->wait(30_000) ?? throw new RuntimeException('still running');
```

## Configuration (php.ini)

| Directive | Default | Purpose |
|---|---|---|
| `vocalizer.isolation` | `fork` | `fork` = crash-proof (recommended); `direct` = minimal latency, required for Chatterbox |
| `vocalizer.max_retries` | `2` | Retries after a crash before `CrashException` |
| `vocalizer.timeout_ms` | `0` | Max time per synthesis (0 = unlimited) |
| `vocalizer.max_models` | `2` | Simultaneous models in cache (LRU eviction) |
| `vocalizer.max_concurrency` | `2` | Async pool threads |
| `vocalizer.default_threads` | `0` | Inference threads (0 = auto) |

Production tips: account for model RAM **per FPM worker**; with concurrency N,
give `cores/N` threads per call.

## Binaries

| File | PHP | Platform |
|---|---|---|
| `bin/vocalizer-php8.4-nts-linux-x86_64.so` | 8.4 NTS | Linux x86-64, glibc ≥ 2.28 |
| `bin/vocalizer-php8.5-nts-linux-x86_64.so` | 8.5 NTS | Linux x86-64, glibc ≥ 2.28 |

Checksums: [bin/SHA256SUMS](bin/SHA256SUMS). Each `.so` only depends on
libc/libm (libstdc++ statically linked) and targets x86-64-v2 (any CPU since ~2009).
Size ≈ 44 MB (embeds the ONNX Runtime and ggml inference engines).

## License

[MIT](LICENSE). Embeds sherpa-onnx (Apache-2.0), audio.cpp / ggml (MIT),
ONNX Runtime (MIT), espeak-ng (GPL-3.0, phonemization data) and other
statically linked dependencies — see their respective licenses.
