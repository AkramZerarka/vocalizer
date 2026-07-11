# vocalizer — text-to-speech for PHP

Native PHP extension for local speech synthesis. Embeds
[sherpa-onnx](https://github.com/k2-fsa/sherpa-onnx) (Supertonic, Piper, Pocket,
Kokoro, …) and [audio.cpp](https://github.com/ggml-org/audio.cpp) (Chatterbox
voice cloning). **Prebuilt binaries** — no compilation, no runtime dependencies.

```php
use Vocalizer\Engine;

$res = Engine::load('/opt/voices/chatterbox')->speak('Bonjour.', [
    'lang' => 'fr', 'reference' => '/opt/voices/refs/fr.wav',
]);
$res->save('/tmp/out.wav');
```

## Features

- **Eight model families**, one API — auto-detected from the model directory
- **Chatterbox anti-hallucination guard** — implausible LLM-TTS output is
  re-synthesized automatically; corrupt audio is never returned silently
- **Model cache** — load once per PHP worker, instant on subsequent requests
- **Self-healing** — engine crashes are isolated (fork + retry + reload)
- **Async** (`speakAsync`), per-call timeouts, built-in statistics
- **Output** — 16-bit WAV (file or string) or raw float32 PCM

## How it works

Two inference backends, selected automatically:

| Backend | Models | Engine |
|---|---|---|
| **audio.cpp** | Chatterbox (voice cloning, 23 languages) | ggml |
| **sherpa-onnx** | Supertonic, Piper/VITS, Pocket, Kokoro, Kitten, Matcha, ZipVoice | ONNX Runtime |

If the model folder contains `t3_cfg.safetensors`, Chatterbox is used; otherwise
sherpa-onnx handles the directory. Chatterbox always runs in **direct** mode
(its ggml thread pool is not fork-safe). Other models default to **fork**
isolation for crash protection.

## Choosing a model

| Goal | Model | Latency (CPU) | Notes |
|---|---|---|---|
| **Best realism** (fr, en, es, it, de, pt, ar, …) | Chatterbox | Slow (~20× real time) | Clone any voice from a 3–10 s WAV |
| **Fast multi-language** (31 langs) | Supertonic 3 | Real-time | 10 preset voices, `lang` required |
| **Simple FR/EN clone** | Pocket TTS | Fast | Reference WAV required |
| **Fastest, stable** | Piper/VITS | Very fast | One model per locale |

Sherpa-onnx catalog: [tts-models](https://github.com/k2-fsa/sherpa-onnx/releases).

## Installation

```bash
curl -fsSL https://raw.githubusercontent.com/akramzerarka/vocalizer/main/install.sh | bash
```

**Requirements:** Linux x86-64 (glibc ≥ 2.28), PHP **8.4 or 8.5** NTS.
Integrity verified via SHA256. Alpine/musl, ARM, and PHP ZTS are not supported.

**Manual install:**

```bash
php -n -d extension=/path/to/bin/vocalizer-php8.5-nts-linux-x86_64.so -m | grep vocalizer
```

Download models:

```bash
./scripts/download-model.sh chatterbox                                    # ~7.5 GB
./scripts/download-model.sh sherpa-onnx-supertonic-3-tts-int8-2026-05-11  # ~120 MB
./scripts/download-model.sh sherpa-onnx-pocket-tts-int8-2026-01-26        # ~95 MB
./scripts/download-model.sh vits-piper-en_US-amy-low                      # ~65 MB
```

## Quick start — Supertonic

```php
use Vocalizer\Engine;

$engine = Engine::load('/opt/voices/sherpa-onnx-supertonic-3-tts-int8-2026-05-11');

$res = $engine->speak('Votre commande est prête.', [
    'lang'       => 'fr',   // required
    'voice'      => 0,      // 0–9
    'speed'      => 1.0,
    'timeout_ms' => 30_000,
]);

$res->save('/var/www/audio/notice.wav');
echo $res->seconds, " s in ", $res->generationMs, " ms\n";
```

Arabic requires **tashkeel** (diacritics): `مَرْحَبًا بِكُمْ` not `مرحبا بكم`.

## Chatterbox — realistic voice cloning

One model covers **fr, en, es, it, de, pt, ar** (among 23 languages). Provide a
**reference WAV in the target language** (3–10 s, mono, clear speech).

```php
// php.ini: vocalizer.isolation = direct  (recommended for Chatterbox)
$engine = Engine::load('/opt/voices/chatterbox', [
    'threads' => 4,
    'opts'    => ['weight_type' => 'f16'],   // default: q8_0
]);

$res = $engine->speak('Bonjour, votre commande est prête.', [
    'lang'       => 'fr',
    'reference'  => '/opt/voices/refs/fr.wav',
    'opts'       => [
        'temperature'        => 0.6,
        'repetition_penalty' => 1.2,
        'seed'               => 42,
    ],
    'timeout_ms' => 600_000,
]);

echo $res->qualityRetries;   // guard re-syntheses (0 = first output accepted)
$res->save('/tmp/out.wav');
```

### Anti-hallucination guard

Autoregressive TTS can skip text, loop, or produce silence. vocalizer checks
every Chatterbox output (duration vs. text length, signal energy). Suspicious
audio is **re-synthesized with a fresh seed** (2 attempts by default). If all
attempts fail, a `Vocalizer\Exception` is thrown — never silent corruption.

| `opts` key | Default | Purpose |
|---|---|---|
| `verify` | on | Set `'off'` to disable |
| `verify_retries` | `2` | Extra attempts after the first (max 5) |

**Quality tips:** reference WAV in the **same language** as the text; Arabic with
**tashkeel**; `temperature` 0.5–0.7; `repetition_penalty` ≥ 1.2; CUDA for
production throughput.

**Fallback pattern** when Chatterbox throws:

```php
try {
    $res = $engine->speak($text, ['lang' => 'fr', 'reference' => $ref, ...]);
} catch (\Vocalizer\Exception $e) {
    $res = Engine::load('/opt/voices/sherpa-onnx-supertonic-3-tts-int8-2026-05-11')
        ->speak($text, ['lang' => 'fr']);
}
```

## Pocket TTS — lightweight cloning

```php
$engine = Engine::load('/opt/voices/sherpa-onnx-pocket-tts-int8-2026-01-26');
$res = $engine->speak('Hello, cloned voice.', [
    'reference' => '/opt/voices/my-voice.wav',
    'opts'      => ['temperature' => 0.7, 'seed' => 42],
]);
```

## Async

```php
$job = $engine->speakAsync($paragraph);
$res = $job->wait(30_000) ?? throw new RuntimeException('still running');
```

## API reference

### `Engine::load($modelDir, $options)`

| Option | Description |
|---|---|
| `threads` | Inference threads (`0` = auto) |
| `provider` | `"cpu"` or `"cuda"` |
| `type` | Force backend: `"auto"`, `"chatterbox"`, `"supertonic"`, `"vits"`, … |
| `opts` | Model-specific options (array or JSON string) |

Chatterbox `opts`: `weight_type` (`q8_0`, `f16`), `temperature`, `seed`,
`repetition_penalty`, `verify`, `verify_retries`.

### `Engine::speak($text, $options)`

| Option | Description |
|---|---|
| `lang` | Language code — **required** for Supertonic |
| `voice` | Speaker id (Supertonic: 0–9) |
| `speed` | Speech rate multiplier |
| `num_steps` | Denoising steps (Supertonic, default 8) |
| `reference` | WAV path for voice cloning |
| `reference_text` | Transcript of reference (ZipVoice) |
| `opts` | Per-call model options |
| `timeout_ms` | Max synthesis time |

Legacy aliases: `ref_audio` → `reference`, `ref_text` → `reference_text`,
`extra` → `opts`.

### `Result`

| Property | Description |
|---|---|
| `sampleRate` | Hz |
| `seconds` | Audio duration |
| `generationMs` | Compute time |
| `retries` | Crash-recovery retries |
| `qualityRetries` | Anti-hallucination re-syntheses (Chatterbox) |

Methods: `save($path)`, `wav()`, `pcm()`.

### Exceptions

| Class | When |
|---|---|
| `Vocalizer\ModelException` | Model load failure |
| `Vocalizer\TextException` | Invalid text or options |
| `Vocalizer\TimeoutException` | Deadline exceeded |
| `Vocalizer\CrashException` | Unrecoverable engine crash |
| `Vocalizer\Exception` | Other errors (incl. persistent hallucination) |

IDE stub: [stubs/vocalizer.stub.php](stubs/vocalizer.stub.php).
Example: [examples/speak.php](examples/speak.php).

## Configuration (php.ini)

| Directive | Default | Purpose |
|---|---|---|
| `vocalizer.isolation` | `fork` | `fork` = crash-proof; `direct` = lower latency |
| `vocalizer.max_retries` | `2` | Retries after crash before `CrashException` |
| `vocalizer.timeout_ms` | `0` | Global synthesis timeout (`0` = unlimited) |
| `vocalizer.max_models` | `2` | Cached models per worker (LRU eviction) |
| `vocalizer.max_concurrency` | `2` | Async pool threads |
| `vocalizer.default_threads` | `0` | Inference threads (`0` = auto) |

**Production:** model RAM is **per FPM worker**. With N concurrent syntheses,
use `cores/N` threads per call. Chatterbox alone needs several GB RAM.

## Binaries

| File | PHP | Platform |
|---|---|---|
| `bin/vocalizer-php8.4-nts-linux-x86_64.so` | 8.4 NTS | Linux x86-64, glibc ≥ 2.28 |
| `bin/vocalizer-php8.5-nts-linux-x86_64.so` | 8.5 NTS | Linux x86-64, glibc ≥ 2.28 |

Checksums: [bin/SHA256SUMS](bin/SHA256SUMS). Depends only on libc/libm
(libstdc++ statically linked). Targets x86-64-v2. Size ≈ 44 MB.

## License

[MIT](LICENSE). Embeds sherpa-onnx (Apache-2.0), audio.cpp / ggml (MIT),
ONNX Runtime (MIT), espeak-ng (GPL-3.0, phonemization data) and other
statically linked dependencies — see their respective licenses.
