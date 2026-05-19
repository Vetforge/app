<?php

namespace App\Console\Commands;

use App\Models\Aliment;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetWhrrdnbreportsImportCommand extends Command
{
    protected $signature = 'legacy:reset-whrrdnbreports
        {path : Chemin du fichier PHPMyAdmin exporte en PHP array, garde la meme interface que l import}
        {--user= : ID ou email de l utilisateur cible}
        {--cabinet=rieupeyroux : identification_cabinet de l import a reset}
        {--memory=1G : memory_limit applique pendant le reset}
        {--dry-run : Compte les lignes supprimees sans ecrire en base}';

    protected $description = 'Supprime les analyses, eleveurs et aliments importes depuis l ancien export whrrdnbreports pour un utilisateur.';

    private const ANALYSIS_MODULES = [
        'coproscopie-parasitaire',
        'diarrhee-neonatale',
        'gaz-du-sang',
        'comptage-cellulaire',
        'diagnostic-bacteriologique',
        'analyse-diverse',
        'tests-rapides',
        'tests-biochimie',
        'hemogramme',
        'autopsie',
        'compte-rendu',
        'bse-laitier',
        'bse-allaitant',
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error("Fichier introuvable: {$path}");

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if (! $user) {
            $this->error('Option --user obligatoire. Utilise un ID ou un email existant.');

            return self::FAILURE;
        }

        ini_set('memory_limit', (string) $this->option('memory'));

        $stats = $this->currentStats($user);

        if (! $dryRun) {
            DB::transaction(function () use ($user): void {
                Analysis::query()
                    ->where('user_id', $user->id)
                    ->delete();

                Breeder::query()
                    ->where('user_id', $user->id)
                    ->delete();

                Aliment::query()
                    ->where('user_id', $user->id)
                    ->delete();
            });
        }

        $this->printStats($stats, $dryRun);

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $value = (string) $this->option('user');

        if ($value === '') {
            return null;
        }

        return ctype_digit($value)
            ? User::query()->find((int) $value)
            : User::query()->where('email', $value)->first();
    }

    /**
     * @return array{breeders: int, aliments: int, analyses: array<string, int>, otherAnalyses: int}
     */
    private function currentStats(User $user): array
    {
        $stats = [
            'breeders' => Breeder::query()->where('user_id', $user->id)->count(),
            'aliments' => Aliment::query()->where('user_id', $user->id)->count(),
            'analyses' => [],
            'otherAnalyses' => Analysis::query()
                ->where('user_id', $user->id)
                ->whereNotIn('module', self::ANALYSIS_MODULES)
                ->count(),
        ];

        foreach (self::ANALYSIS_MODULES as $module) {
            $stats['analyses'][$module] = Analysis::query()
                ->where('user_id', $user->id)
                ->where('module', $module)
                ->count();
        }

        return $stats;
    }

    /**
     * @param  array{breeders: int, aliments: int, analyses: array<string, int>, otherAnalyses: int}  $stats
     */
    private function printStats(array $stats, bool $dryRun): void
    {
        $cabinet = (string) $this->option('cabinet');
        $prefix = $dryRun ? 'Simulation reset' : 'Reset termine';

        $this->info("{$prefix} whrrdnbreports pour le cabinet {$cabinet}.");
        $this->line("Eleveurs: {$stats['breeders']}");
        $this->line("Aliments: {$stats['aliments']}");

        foreach ($stats['analyses'] as $module => $count) {
            $this->line("Analyses {$module}: {$count}");
        }

        if ($stats['otherAnalyses'] > 0) {
            $this->line("Analyses autres: {$stats['otherAnalyses']}");
        }
    }
}
