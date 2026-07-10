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
     * Le modèle reste en mémoire entre les requêtes FPM: le chargement n'a lieu qu'une fois.
     *
     * @param string $modelDir Répertoire du modèle (VITS/Piper, Kokoro, Kitten, Matcha)
     *                         — détection automatique du type par le contenu.
     * @param array{
     *     threads?: int,          // threads d'inférence (0 = auto)
     *     provider?: string,      // "cpu" (défaut) ou "cuda" (build VX_CUDA=ON)
     *     type?: string,          // "auto" | "vits" | "kokoro" | "kitten" | "matcha"
     *     vocoder?: string,       // chemin du vocoder .onnx (requis pour matcha)
     *     lang?: string,          // indice de langue (kokoro multilingue)
     *     noise_scale?: float,    // variabilité de la voix (vits/matcha)
     *     noise_scale_w?: float,  // variabilité des durées (vits)
     *     max_num_sentences?: int,// phrases par lot interne
     * } $options
     * @throws ModelException si le répertoire est introuvable ou le modèle invalide
     */
    public static function load(string $modelDir, array $options = []): Engine {}

    /**
     * Synthétise un texte en audio.
     *
     * @param array{
     *     voice?: int,           // speaker id (modèles multi-locuteurs / kokoro)
     *     speed?: float,         // vitesse (>1 = plus rapide, défaut 1.0)
     *     silence_scale?: float, // silence entre phrases
     *     num_steps?: int,       // étapes flow-matching (modèles concernés)
     *     extra?: string,        // JSON d'options spécifiques au modèle
     *     timeout_ms?: int,      // délai max (-1 = ini vocalizer.timeout_ms)
     * } $options
     * @throws TextException    texte vide
     * @throws CrashException   crash de synthèse non récupérable (après retries + rechargement)
     * @throws TimeoutException délai dépassé (le worker est arrêté proprement)
     */
    public function speak(string $text, array $options = []): Result {}

    /**
     * Version asynchrone: la synthèse part dans le pool de threads natif
     * (taille contrôlée par l'ini vocalizer.max_concurrency).
     *
     * @param array<string,mixed> $options Mêmes options que speak()
     */
    public function speakAsync(string $text, array $options = []): Job {}

    /**
     * Métadonnées et compteurs du moteur.
     *
     * @return array{model:string,type:string,loaded:bool,provider:string,
     *               sample_rate:int,num_speakers:int,model_bytes:int,generation:int,
     *               ok:int,failed:int,crashes_recovered:int,reloads:int,
     *               total_generation_ms:float}
     */
    public function info(): array {}

    /** Force le rechargement du modèle depuis le disque. */
    public function reload(): void {}

    /** @return list<array{model:string,type:string,loaded:bool,model_bytes:int}> */
    public static function loaded(): array {}

    /** Retire un modèle du cache (libéré quand la dernière référence PHP disparaît). */
    public static function unload(string $modelDir): bool {}

    /** Vide le cache de modèles; renvoie le nombre de modèles retirés. */
    public static function unloadAll(): int {}

    /**
     * Statistiques globales du module.
     *
     * @return array{models_cached:int,syntheses_ok:int,syntheses_failed:int,
     *               crashes_recovered:int,model_reloads:int,pool_threads:int,queue_depth:int}
     */
    public static function stats(): array {}
}

final class Result
{
    /** Fréquence d'échantillonnage de l'audio généré (Hz). */
    public int $sampleRate;
    /** Durée de l'audio généré (secondes). */
    public float $seconds;
    /** Temps de génération (millisecondes). */
    public float $generationMs;
    /** Crashs absorbés par l'auto-réparation pour produire ce résultat. */
    public int $retries;

    private function __construct() {}

    /** PCM brut: float32 little-endian, mono. */
    public function pcm(): string {}

    /** Fichier WAV complet (PCM 16 bits mono) en chaîne binaire. */
    public function wav(): string {}

    /** Écrit l'audio dans un fichier WAV (PCM 16 bits mono). */
    public function save(string $path): void {}
}

final class Job
{
    private function __construct() {}

    public function isDone(): bool {}

    /**
     * Attend le résultat. $timeoutMs null = bloque jusqu'à la fin;
     * sinon renvoie null si le job n'est pas terminé à temps (il continue).
     * @throws Exception l'erreur du job est relancée ici
     */
    public function wait(?int $timeoutMs = null): ?Result {}

    /** Annule un job pas encore démarré. */
    public function cancel(): bool {}
}

class Exception extends \Exception {}
class ModelException extends Exception {}
class TextException extends Exception {}
class CrashException extends Exception {}
class TimeoutException extends Exception {}
