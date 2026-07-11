<?php

/**
 * Stub IDE pour l'extension vocalizer (synthèse vocale via sherpa-onnx embarqué).
 * Ce fichier n'est jamais exécuté — il documente l'API native pour l'autocomplétion.
 */

namespace Vocalizer;

final class Engine
{
    public const VERSION = '0.1.0';

    private function __construct() {}

    /**
     * Charge un modèle TTS (ou le récupère instantanément depuis le cache du processus).
     *
     * Options communes (tous modèles): threads, provider, type, lang, vocoder,
     * noise_scale, noise_scale_w, max_num_sentences.
     *
     * Options spécifiques au modèle via `opts` (tableau ou JSON) — ignorées si
     * le modèle ne les supporte pas. Exemples:
     *   chatterbox: weight_type, t3_version, profile ("premium" → f16 + anti-hallucination)
     *   (autres familles: pas d'effet)
     *
     * Rétrocompatibilité: weight_type et t3_version au niveau racine sont
     * fusionnés dans opts.
     *
     * @param array{
     *     threads?: int,
     *     provider?: string,
     *     type?: string,
     *     lang?: string,
     *     vocoder?: string,
     *     noise_scale?: float,
     *     noise_scale_w?: float,
     *     max_num_sentences?: int,
     *     opts?: array<string, scalar>|string,
     *     weight_type?: string,
     *     t3_version?: string,
     * } $options
     */
    public static function load(string $modelDir, array $options = []): Engine {}

    /**
     * Synthétise un texte en audio.
     *
     * Options communes (tous modèles): lang, voice, speed, silence_scale,
     * num_steps, timeout_ms, reference, reference_text.
     *
     * Options spécifiques au modèle via `opts` (tableau ou JSON). Exemples:
     *   supertonic: (lang est aussi accepté en racine)
     *   chatterbox: temperature, seed, repetition_penalty, guidance_scale
     *   pocket: temperature, seed, max_reference_audio_len
     *
     * Rétrocompatibilité: ref_audio → reference, ref_text → reference_text,
     * extra (JSON string) → opts.
     *
     * @param array{
     *     lang?: string,
     *     voice?: int,
     *     speed?: float,
     *     silence_scale?: float,
     *     num_steps?: int,
     *     reference?: string,
     *     reference_text?: string,
     *     opts?: array<string, scalar>|string,
     *     timeout_ms?: int,
     *     ref_audio?: string,
     *     ref_text?: string,
     *     extra?: string,
     * } $options
     */
    public function speak(string $text, array $options = []): Result {}

    /**
     * @param array<string,mixed> $options Mêmes options que speak()
     */
    public function speakAsync(string $text, array $options = []): Job {}

    /**
     * @return array{model:string,type:string,loaded:bool,provider:string,
     *               sample_rate:int,num_speakers:int,model_bytes:int,generation:int,
     *               ok:int,failed:int,crashes_recovered:int,reloads:int,
     *               total_generation_ms:float}
     */
    public function info(): array {}

    public function reload(): void {}

    /** @return list<array{model:string,type:string,loaded:bool,model_bytes:int}> */
    public static function loaded(): array {}

    public static function unload(string $modelDir): bool {}

    public static function unloadAll(): int {}

    /**
     * @return array{models_cached:int,syntheses_ok:int,syntheses_failed:int,
     *               crashes_recovered:int,model_reloads:int,pool_threads:int,queue_depth:int}
     */
    public static function stats(): array {}
}

final class Result
{
    public int $sampleRate;
    public float $seconds;
    public float $generationMs;
    public int $retries;

    private function __construct() {}

    public function pcm(): string {}
    public function wav(): string {}
    public function save(string $path): void {}
}

final class Job
{
    private function __construct() {}

    public function isDone(): bool {}
    public function wait(?int $timeoutMs = null): ?Result {}
    public function cancel(): bool {}
}

class Exception extends \Exception {}
class ModelException extends Exception {}
class TextException extends Exception {}
class CrashException extends Exception {}
class TimeoutException extends Exception {}
