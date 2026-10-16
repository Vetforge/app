<?php

declare(strict_types=1);

use Tests\Support\RationComparisonReport;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if ($argc !== 3 || ! is_dir($argv[1])) {
    fwrite(STDERR, "Usage : php tests/Support/generate-ration-report.php <dossier> <code-sortie-tests>\n");
    exit(2);
}

try {
    $complete = RationComparisonReport::generate($argv[1], (int) $argv[2]);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erreur de génération du rapport : '.$exception->getMessage()."\n");
    exit(2);
}

if (! $complete) {
    fwrite(STDERR, "Rapport incomplet : certains calculs sont absents ou en erreur. Consulte rapport.html et tests.log.\n");
    exit(2);
}
