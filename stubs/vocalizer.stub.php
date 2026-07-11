<?php

/**
 * IDE stub for the vocalizer extension (text-to-speech via embedded sherpa-onnx).
 * This file is never executed — it documents the native API for autocompletion.
 */

namespace Vocalizer;

final class Engine
{
    public const VERSION = '0.1.0';

    private function __construct() {}

    /**
     * Loads a TTS model (or fetches it instantly from the per-process cache).
     *
     * Common options (all models): threads, provider, type, lang, vocoder,
     * noise_scale, noise_scale_w, max_num_sentences.
     *
     * Model-specific options via `opts` (array or JSON) — ignored when the
     * model does not support them. Examples:
     *   chatterbox: weight_type, t3_version
     *   (other families: no effect)
     *
     * Backward compatibility: root-level weight_type and t3_version are
     * merged into opts.
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
     * Synthesizes text into audio.
     *
     * Common options (all models): lang, voice, speed, silence_scale,
     * num_steps, timeout_ms, reference, reference_text.
     *
     * Model-specific options via `opts` (array or JSON). Examples:
     *   supertonic: (lang is also accepted at the root)
     *   chatterbox: temperature, seed, repetition_penalty, guidance_scale,
     *               verify ('off' disables the anti-hallucination guard,
     *               on by default), verify_retries (re-syntheses with a
     *               fresh seed when the output is implausible, default 2)
     *   pocket: temperature, seed, max_reference_audio_len
     *
     * Backward compatibility: ref_audio → reference, ref_text → reference_text,
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
     * @param array<string,mixed> $options Same options as speak()
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
    /** Re-syntheses triggered by the anti-hallucination guard (chatterbox). */
    public int $qualityRetries;

    private function __construct() {}

    public function pcm(): string {}
    public function wav(): string {}
    public function save(string $path): void {}
}

final class Job
{
    private function __construct() {}

    public function isDone(): bool {}

    /** Waits for completion; returns null when the timeout expires first. */
    public function wait(?int $timeoutMs = null): ?Result {}

    /** Cancels the job if it has not started yet. */
    public function cancel(): bool {}
}

class Exception extends \Exception {}
class ModelException extends Exception {}
class TextException extends Exception {}
class CrashException extends Exception {}
class TimeoutException extends Exception {}
