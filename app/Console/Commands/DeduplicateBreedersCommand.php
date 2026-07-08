<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\PlanRationnement;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fusionne les éleveurs en double d'un même cabinet (même {@see Breeder::$user_id}) partageant
 * le même numéro de cheptel normalisé. La fiche conservée est la plus active (analyses, puis plans,
 * puis la plus ancienne) ; ses doublons voient leurs analyses et plans repointés dessus avant d'être
 * supprimés. Deux éleveurs de cabinets différents ne sont jamais fusionnés.
 */
class DeduplicateBreedersCommand extends Command
{
    protected $signature = 'breeders:deduplicate {--force : Applique réellement la fusion (sinon simulation sans écriture)}';

    protected $description = 'Fusionne les éleveurs en double (même cabinet + même numéro de cheptel) sur la fiche la plus active.';

    public function handle(): int
    {
        $apply = (bool) $this->option('force');

        $groups = $this->groupesDeDoublons();

        if ($groups->isEmpty()) {
            $this->info('Aucun doublon d\'éleveur à fusionner.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'Fusion' : 'Simulation (dry-run)').' — '.$groups->count().' groupe(s) de doublons trouvé(s).');

        $rows = [];
        $analysesDeplacees = 0;
        $plansDeplaces = 0;

        $fusionner = function () use ($groups, $apply, &$rows, &$analysesDeplacees, &$plansDeplaces): void {
            foreach ($groups as $group) {
                /** @var Collection<int, Breeder> $sorted */
                $sorted = $group->sortBy([
                    ['analyses_count', 'desc'],
                    ['plan_rationnements_count', 'desc'],
                    ['id', 'asc'],
                ])->values();

                $cible = $sorted->first();

                foreach ($sorted->slice(1) as $doublon) {
                    if ($apply) {
                        Analysis::where('breeder_id', $doublon->id)->update(['breeder_id' => $cible->id]);
                        PlanRationnement::where('breeder_id', $doublon->id)->update(['breeder_id' => $cible->id]);
                        // Repointé : le doublon n'a plus d'analyses ni de plans, la suppression ne
                        // déclenche donc aucune cascade destructrice.
                        $doublon->delete();
                    }

                    $rows[] = [
                        (string) $cible->user_id,
                        (string) $doublon->herd_number,
                        $cible->name.' (#'.$cible->id.')',
                        $doublon->name.' (#'.$doublon->id.')',
                        (string) $doublon->analyses_count,
                        (string) $doublon->plan_rationnements_count,
                    ];
                    $analysesDeplacees += $doublon->analyses_count;
                    $plansDeplaces += $doublon->plan_rationnements_count;
                }
            }
        };

        if ($apply) {
            DB::transaction($fusionner);
        } else {
            $fusionner();
        }

        $this->table(
            ['Cabinet', 'Cheptel', 'Cible conservée', 'Doublon fusionné', 'Analyses', 'Plans'],
            $rows,
        );

        $resume = count($rows).' doublon(s), '.$analysesDeplacees.' analyse(s) et '.$plansDeplaces.' plan(s) repointé(s).';

        if ($apply) {
            $this->info('Fusion appliquée : '.$resume);
        } else {
            $this->warn('Simulation : aucune donnée modifiée ('.$resume.'). Relance avec --force pour appliquer.');
        }

        return self::SUCCESS;
    }

    /**
     * Groupes d'éleveurs en double : même cabinet et même numéro de cheptel normalisé (non vide),
     * ne conservant que les groupes comptant au moins deux fiches.
     *
     * @return Collection<string, Collection<int, Breeder>>
     */
    private function groupesDeDoublons(): Collection
    {
        return Breeder::query()
            ->withCount(['analyses', 'planRationnements'])
            ->get()
            ->filter(fn (Breeder $breeder): bool => self::normaliserCheptel($breeder->herd_number) !== '')
            ->groupBy(fn (Breeder $breeder): string => $breeder->user_id.'|'.self::normaliserCheptel($breeder->herd_number))
            ->filter(fn (Collection $group): bool => $group->count() > 1);
    }

    /**
     * Normalise un numéro de cheptel pour la comparaison : suppression des espaces et passage
     * en majuscules. Un cheptel vide ou nul renvoie une chaîne vide (jamais fusionné).
     */
    private static function normaliserCheptel(?string $herd): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $herd) ?? '');
    }
}
