<?php
// Exemple complet — lancez avec:
//   php -d extension=./build/vocalizer.so examples/speak.php models/vits-piper-fr_FR-siwis-medium "Bonjour le monde"

use Vocalizer\Engine;
use Vocalizer\TimeoutException;
use Vocalizer\CrashException;

[$_, $model, $text] = array_pad($argv, 3, null);
if (!$model) {
    exit("usage: php examples/speak.php <repertoire-modele> [texte]\n");
}
$text ??= "Bonjour ! Ceci est une démonstration de synthèse vocale native pour PHP.";

// 1. Chargement — instantané si le modèle est déjà dans le cache du processus
$t = microtime(true);
$engine = Engine::load($model, ['threads' => 0]);
printf("Modèle prêt en %.0f ms (%s, %d voix, %d Hz)\n",
    (microtime(true) - $t) * 1000,
    $engine->info()['type'],
    $engine->info()['num_speakers'],
    $engine->info()['sample_rate']);

// 2. Synthèse synchrone
try {
    $res = $engine->speak($text, [
        'voice'      => 0,
        'speed'      => 1.0,
        'timeout_ms' => 60_000,
    ]);
} catch (TimeoutException $e) {
    exit("Trop long: {$e->getMessage()}\n");
} catch (CrashException $e) {
    exit("Synthèse irrécupérable: {$e->getMessage()}\n");
}

$out = sys_get_temp_dir() . '/vocalizer_demo.wav';
$res->save($out);
printf("Audio: %.2f s générées en %.0f ms → %s (%d Ko)\n",
    $res->seconds, $res->generationMs, $out, intdiv(filesize($out), 1024));

// 3. Synthèses asynchrones en parallèle (pool de threads natif)
$jobs = [
    $engine->speakAsync("Première phrase générée en parallèle."),
    $engine->speakAsync("Deuxième phrase générée en parallèle.", ['speed' => 1.3]),
];
foreach ($jobs as $i => $job) {
    $r = $job->wait();
    printf("Job %d: %.2f s d'audio\n", $i, $r->seconds);
}

// 4. Observabilité
print_r($engine->info());
print_r(Engine::stats());
