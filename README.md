# vocalizer — text-to-speech for PHP

Native PHP extension for speech synthesis (TTS) embedding
[sherpa-onnx](https://github.com/k2-fsa/sherpa-onnx). **Ready-to-use binaries**
— no compilation, no dependencies to install. Sister project of
[transcriptor](https://github.com/akramzerarka/transcriptor) (speech-to-text).

- Several model families through one engine: **VITS/Piper** (50+ languages,
  faster than real time on CPU), **Kokoro** (near-commercial quality),
  **Kitten**, **Matcha** — auto-detected from the model directory
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

Then download a voice
([full catalog](https://github.com/k2-fsa/sherpa-onnx/releases/tag/tts-models)):

```bash
./scripts/download-model.sh vits-piper-en_US-amy-low         # English, fast (~65 MB)
./scripts/download-model.sh vits-piper-fr_FR-siwis-medium    # French
./scripts/download-model.sh kokoro-multi-lang-v1_0           # premium quality (~330 MB)
```

## Usage

```php
use Vocalizer\Engine;

$engine = Engine::load('/opt/voices/vits-piper-en_US-amy-low');  // loaded once per process

$res = $engine->speak("Your order is ready.", [
    'speed'      => 1.0,
    'timeout_ms' => 30_000,
]);

$res->save('/var/www/audio/notice.wav');  // 16-bit mono WAV
// or: $res->wav() (WAV in memory), $res->pcm() (raw float32 for streaming)
echo $res->seconds;        // audio duration
echo $res->generationMs;   // compute time
echo $res->retries;        // crashes absorbed by self-healing (normally 0)
```

Async:

```php
$job = $engine->speakAsync($paragraph);
// … other work …
$res = $job->wait(30_000) ?? throw new RuntimeException('still running');
```

Full API (IDE autocompletion): [stubs/vocalizer.stub.php](stubs/vocalizer.stub.php).
Complete example: [examples/speak.php](examples/speak.php).

## Configuration (php.ini)

| Directive | Default | Purpose |
|---|---|---|
| `vocalizer.isolation` | `fork` | `fork` = crash-proof (recommended); `direct` = minimal latency, cooperative timeout |
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
Size ≈ 40 MB (embeds the ONNX Runtime inference engine).

## License

[MIT](LICENSE). Embeds sherpa-onnx (Apache-2.0), ONNX Runtime (MIT),
espeak-ng (GPL-3.0, phonemization data) and other statically linked
dependencies — see their respective licenses.
