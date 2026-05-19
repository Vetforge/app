<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\Ration;
use App\Services\Equations2018\CalculValeur;
use Illuminate\Support\Str;

/**
 * Fonctions utilitaires partagées pour les calculs de ration.
 */
class RationHelper
{
    /**
     * Champs nécessitant un calcul dynamique 2018 via CalculValeur.
     *
     * @var string[]
     */
    private const COMPUTED_FIELDS = ['UFL', 'PDI', 'PDIA', 'BPR', 'DT_N', 'AmiD', 'MAmic_duo', 'dMOc', 'NDFND', 'MOD', 'AmiD_ru', 'AmiD_int'];

    /**
     * Calculer la moyenne pondérée d'une valeur nutritionnelle pour un mélange.
     *
     * @param  string  $champ  Nom du champ (ex: 'UFL', 'ms', 'Ca')
     * @param  string|null  $type  Filtre par type: 'Fourrage', 'Concentre', ou null (tous)
     */
    public static function calculerMoyennePondereeMelange(
        Ration $ration,
        Melange $melange,
        string $champ,
        ?string $type = null
    ): float {
        $typeFilter = match ($type) {
            'Fourrage' => 'Fourrage',
            'Concentre' => 'Conc',
            default => null,
        };

        $totalValeurPonderee = 0.0;
        $totalMS = 0.0;

        foreach ($melange->melangeAliments as $ma) {
            $aliment = $ma->aliment;

            if ($typeFilter !== null && $aliment->type !== $typeFilter) {
                continue;
            }

            if ($ma->quantite <= 0) {
                continue;
            }

            $qtyMS = $ma->is_mb
                ? ($ma->quantite * (float) ($aliment->ms ?? 0) / 100)
                : $ma->quantite;

            // Champs pondérés par le PDI
            if ($champ === 'LysDI' || $champ === 'MetDI') {
                $pdi = self::getAlimentValue($ration, $aliment, 'PDI');
                $val = self::getAlimentValue($ration, $aliment, $champ);
                $totalValeurPonderee += $qtyMS * $pdi * $val;
                $totalMS += $qtyMS * $pdi;
            } elseif ($champ === 'dMOc' || $champ === 'MOD') {
                // Pondéré par la MO
                $mo = (float) ($aliment->mo ?? 0);
                $val = self::getAlimentValue($ration, $aliment, $champ);
                $totalValeurPonderee += $qtyMS * $mo * $val;
                $totalMS += $qtyMS * $mo;
            } elseif ($champ === 'Caabs' && $aliment->caabs === null) {
                // Valeur de repli pour le type minéral
                $caabs = ($aliment->ca !== null && $aliment->type === 'Mineral')
                    ? (float) $aliment->ca * 0.4
                    : 0.0;
                $totalValeurPonderee += $qtyMS * $caabs;
                $totalMS += $qtyMS;
            } elseif ($champ === 'Pabs' && $aliment->pabs === null) {
                // Valeur de repli pour le type minéral
                $pabs = ($aliment->p !== null && $aliment->type === 'Mineral')
                    ? (float) $aliment->p * 0.4
                    : 0.0;
                $totalValeurPonderee += $qtyMS * $pabs;
                $totalMS += $qtyMS;
            } else {
                $val = self::getAlimentValue($ration, $aliment, $champ);
                $totalValeurPonderee += $qtyMS * $val;
                $totalMS += $qtyMS;
            }
        }

        return $totalMS > 0 ? $totalValeurPonderee / $totalMS : 0.0;
    }

    /**
     * Calculer la proportion en fourrages (MS fourrage / MS totale) d'un mélange.
     */
    public static function calculerProportionFourrage(Melange $melange): float
    {
        $totalMS = 0.0;
        $fourrageMS = 0.0;

        foreach ($melange->melangeAliments as $ma) {
            if ($ma->quantite <= 0) {
                continue;
            }
            $ms = (float) ($ma->aliment->ms ?? 0);
            $qtyMS = $ma->is_mb ? ($ma->quantite * $ms / 100) : $ma->quantite;
            $totalMS += $qtyMS;
            if ($ma->aliment->type === 'Fourrage') {
                $fourrageMS += $qtyMS;
            }
        }

        return $totalMS > 0 ? $fourrageMS / $totalMS : 0.0;
    }

    /**
     * Obtenir la valeur d'un champ pour un aliment, avec calcul dynamique si nécessaire.
     */
    private static function getAlimentValue(Ration $ration, Aliment $aliment, string $champ): float
    {
        if (in_array($champ, self::COMPUTED_FIELDS, true)) {
            $cv = new CalculValeur($ration, $aliment);

            return match ($champ) {
                'UFL' => $cv->calculerUFLAliment(),
                'PDI' => $cv->calculerPDIAliment(),
                'PDIA' => $cv->calculerPDIAAliment(),
                'BPR' => $cv->calculerBPRAliment(),
                'DT_N' => $cv->calculerDT_NAliment(),
                'AmiD' => $cv->calculerAmiD_intAliment(),
                'MAmic_duo' => $cv->calculerMAmic_duoAliment(),
                'dMOc', 'MOD' => $cv->calculerMODAliment(),
                'NDFND' => $cv->calculerNDFNDAliment(),
                'AmiD_ru' => $cv->calculerAmiD_ruAliment(),
                'AmiD_int' => $cv->calculerAmiD_intAliment(),
            };
        }

        // NIref : les fourrages utilisent la valeur stockée, les autres utilisent 2 par défaut
        if ($champ === 'NIref') {
            return ($aliment->type === 'Fourrage')
                ? (float) ($aliment->niref ?? 2.0)
                : 2.0;
        }

        // Mapper le champ vers la propriété Eloquent (snake_case)
        $prop = self::mapChampToProperty($champ);

        return (float) ($aliment->{$prop} ?? 0);
    }

    /**
     * Mapper le nom de champ nutritionnel vers la propriété Eloquent snake_case.
     */
    private static function mapChampToProperty(string $champ): string
    {
        return match ($champ) {
            'MS' => 'ms',
            'MO' => 'mo',
            'MAT' => 'mat',
            'CB' => 'cb',
            'NDF' => 'ndf',
            'ADF' => 'adf',
            'NI', 'NIref' => 'niref',
            'EB' => 'eb',
            'EM' => 'em',
            'Amidon' => 'amidon',
            'PF' => 'pf',
            'AG' => 'ag',
            'UFV' => 'ufv',
            'UEM' => 'uem',
            'UEL' => 'uel',
            'UEB' => 'ueb',
            'Ca' => 'ca',
            'Caabs' => 'caabs',
            'P' => 'p',
            'Pabs' => 'pabs',
            'Mg' => 'mg',
            'K' => 'k',
            'Na' => 'na',
            'Cl' => 'cl',
            'S' => 's',
            'Co' => 'co',
            'Cu' => 'cu',
            'Mn' => 'mn',
            'Zn' => 'zn',
            'I' => 'i',
            'Se' => 'se',
            'Fe' => 'fe',
            'Prix' => 'prix',
            'bVEc', 'BVEc', 'b_vec' => 'b_vec',
            'BPR' => 'bpr',
            'drN', 'dr_N', 'drn' => 'dr_n',
            'DT6N', 'DT6_N', 'dt6n' => 'dt6_n',
            'DT6Ami', 'DT6_Ami', 'dt6ami' => 'dt6_ami',
            'dMO' => 'd_mo',
            'PDIN' => 'pdin',
            'PDIE' => 'pdie',
            'PDI2007' => 'pdie2007',
            default => strtolower($champ),
        };
    }

    /**
     * Normaliser categorie_animal vers un identifiant canonique pour le routage conditionnel.
     */
    public static function normalizeCategorieAnimal(string $categorie): string
    {
        $lower = Str::of($categorie)->lower()->ascii()->value();
        if (str_contains($lower, 'allait')) {
            return 'vacheAllaitante';
        }

        return 'vacheLaitiere';
    }

    public static function normalizeActivite2018(?string $activite): string
    {
        $normalized = Str::of((string) $activite)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();

        return match ($normalized) {
            'entravee' => 'entravee',
            'plaine', 'paturage', 'paturageplaine', 'elevee' => 'plaine',
            'vallon' => 'vallon',
            'montagne' => 'montagne',
            'stabulation', 'normale', 'nulle', '' => 'stabulation',
            default => 'stabulation',
        };
    }

    public static function normalizeRace(?string $race): string
    {
        return Str::of((string) $race)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();
    }

    public static function calculerSemainesLactation(Ration $ration): float
    {
        if ($ration->mois_lactation !== null) {
            return max(0.0, (float) $ration->mois_lactation * 4.348);
        }

        if ($ration->stade_moyen !== null) {
            return max(0.0, (float) $ration->stade_moyen / 7);
        }

        return 0.0;
    }

    public static function calculerSemainesGestation(Ration $ration): float
    {
        return max(0.0, (float) ($ration->mois_gestation ?? 0) * 4.348);
    }

    public static function calculerPDIUt(Ration $ration): float
    {
        $semLactation = round(self::calculerSemainesLactation($ration), 2);

        return match (true) {
            $semLactation > 0 && $semLactation <= 1 => 100.0,
            $semLactation > 1 && $semLactation <= 2 => 50.0,
            default => 0.0,
        };
    }

    public static function calculerProductionLaitPotentielle(Ration $ration): float
    {
        $categorie = self::normalizeCategorieAnimal((string) ($ration->categorie_animal ?? ''));
        $fallback = max(0.0, (float) ($ration->lait_objectif ?? 0));

        if ($categorie !== 'vacheLaitiere') {
            return $fallback;
        }

        $plPot305 = max(0.0, (float) ($ration->lait_potentiel305j ?? 0));
        if ($plPot305 <= 0) {
            $plPot305 = max(0.0, (float) ($ration->lait_objectif305j ?? 0));
        }

        if ($plPot305 <= 0) {
            $observedPeak = max(0.0, (float) ($ration->lait_objectif_auge ?? 0));

            return $observedPeak > 0 ? $observedPeak : $fallback;
        }

        $partPrimipare = self::clamp((float) ($ration->pourcentage_primipare ?? 0) / 100, 0.0, 1.0);
        $partMultipare = 1.0 - $partPrimipare;
        $semainesLactation = self::calculerSemainesLactation($ration);
        $semainesGestation = self::calculerSemainesGestation($ration);

        $productionPrimipare = self::calculerProductionLaitPotentielleDepuis305(
            $plPot305,
            $semainesLactation,
            $semainesGestation,
            true,
        );
        $productionMultipare = self::calculerProductionLaitPotentielleDepuis305(
            $plPot305,
            $semainesLactation,
            $semainesGestation,
            false,
        );

        return ($partPrimipare * $productionPrimipare) + ($partMultipare * $productionMultipare);
    }

    private static function calculerProductionLaitPotentielleDepuis305(
        float $plPot305,
        float $semainesLactation,
        float $semainesGestation,
        bool $primipare,
    ): float {
        $plMaxPot = $plPot305 / ($primipare ? 260 : 230);
        $courbe = $primipare
            ? -0.55
                + (1.66 * exp(-0.0065 * $semainesLactation))
                - (0.72 * exp(-0.44 * $semainesLactation))
                - (0.69 * exp(-0.16 * (45 - $semainesGestation)))
            : -0.83
                + (1.92 * exp(-0.0083 * $semainesLactation))
                - (0.74 * exp(-0.88 * $semainesLactation))
                - (0.50 * exp(-0.12 * (45 - $semainesGestation)));

        return max(0.0, $plMaxPot * $courbe);
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return min($max, max($min, $value));
    }
}
