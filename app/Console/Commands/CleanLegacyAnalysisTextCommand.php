<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Analysis;
use App\Models\UserModuleSetting;
use App\Support\LegacyHtmlCleaner;
use Illuminate\Console\Command;

class CleanLegacyAnalysisTextCommand extends Command
{
    protected $signature = 'legacy:clean-analysis-text
        {--dry-run : Affiche les analyses qui seraient modifiees sans ecrire en base}';

    protected $description = 'Nettoie le texte HTML importe depuis l\'ancien VetReport (espaces parasites, balises cassees, HTML BSE).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $modified = 0;
        $total = 0;

        Analysis::query()
            ->whereNotNull('payload')
            ->chunkById(200, function ($analyses) use ($dryRun, &$modified, &$total) {
                foreach ($analyses as $analysis) {
                    $total++;

                    $originalPayload = $analysis->payload;
                    $plainPayload = in_array($analysis->module, ['bse-laitier', 'bse-allaitant'], true);
                    $cleanedPayload = $analysis->module === 'compte-rendu'
                        ? $this->cleanReportPayload($originalPayload)
                        : ($plainPayload
                        ? LegacyHtmlCleaner::plainPayload($originalPayload)
                        : LegacyHtmlCleaner::cleanPayload($originalPayload));
                    $originalSettingsSnapshot = $analysis->settings_snapshot;
                    $cleanedSettingsSnapshot = $plainPayload
                        ? LegacyHtmlCleaner::plainPayload($originalSettingsSnapshot)
                        : $originalSettingsSnapshot;
                    $originalResults = $analysis->results;
                    $cleanedResults = $plainPayload
                        ? LegacyHtmlCleaner::plainPayload($originalResults)
                        : $originalResults;

                    $originalAnimalNom = $analysis->animal_nom;
                    $cleanedAnimalNom = $originalAnimalNom !== null
                        ? LegacyHtmlCleaner::clean($originalAnimalNom)
                        : null;

                    $originalIntervenant = $analysis->intervenant;
                    $cleanedIntervenant = $originalIntervenant !== null
                        ? LegacyHtmlCleaner::clean($originalIntervenant)
                        : null;

                    $hasChanged = $cleanedPayload !== $originalPayload
                        || $cleanedSettingsSnapshot !== $originalSettingsSnapshot
                        || $cleanedResults !== $originalResults
                        || $cleanedAnimalNom !== $originalAnimalNom
                        || $cleanedIntervenant !== $originalIntervenant;

                    if (! $hasChanged) {
                        continue;
                    }

                    $modified++;

                    if ($dryRun) {
                        $this->line("  [dry-run] Analyse #{$analysis->id} ({$analysis->module}) serait modifiee.");

                        continue;
                    }

                    $analysis->updateQuietly([
                        'payload' => $cleanedPayload,
                        'settings_snapshot' => $cleanedSettingsSnapshot,
                        'results' => $cleanedResults,
                        'animal_nom' => $cleanedAnimalNom,
                        'intervenant' => $cleanedIntervenant,
                    ]);
                }
            });

        UserModuleSetting::query()
            ->whereIn('module', ['bse-laitier', 'bse-allaitant'])
            ->chunkById(200, function ($settings) use ($dryRun, &$modified, &$total) {
                foreach ($settings as $setting) {
                    $total++;

                    $originalSettings = $setting->settings;
                    $cleanedSettings = LegacyHtmlCleaner::plainPayload($originalSettings);

                    if ($cleanedSettings === $originalSettings) {
                        continue;
                    }

                    $modified++;

                    if ($dryRun) {
                        $this->line("  [dry-run] Reglage #{$setting->id} ({$setting->module}) serait modifie.");

                        continue;
                    }

                    $setting->updateQuietly([
                        'settings' => $cleanedSettings,
                    ]);
                }
            });

        $action = $dryRun ? 'a modifier' : 'modifiees';
        $this->info("Termine : {$modified}/{$total} elements {$action}.");

        return self::SUCCESS;
    }

    private function cleanReportPayload(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        if (! isset($payload['pages']) || ! is_array($payload['pages'])) {
            return $payload;
        }

        $payload['pages'] = array_map(
            fn (mixed $page): mixed => is_string($page) ? LegacyHtmlCleaner::plainTextWithBreaks($page) : $page,
            $payload['pages'],
        );

        return $payload;
    }
}
