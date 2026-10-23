<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CategorieAnimal;
use App\Enums\TypeAliment;
use App\Models\Aliment;
use App\Models\Ration;
use App\Services\Equations2018\CalculValeur;

class AlimentCalculationWarnings
{
    /** Ajoute des informations à la réponse uniquement, sans modifier les aliments enregistrés. */
    public static function annotate(Ration $ration): void
    {
        foreach ($ration->rationAliments as $row) {
            $row->setAttribute('calculation_warnings', (float) $row->quantite > 0 ? self::forAliment($row->aliment, $ration) : []);
        }

        foreach ($ration->melanges as $melange) {
            $warnings = [];
            foreach ($melange->melangeAliments as $row) {
                $ingredientWarnings = (float) $melange->quantite > 0 && (float) $row->quantite > 0
                    ? self::forAliment($row->aliment, $ration) : [];
                $row->setAttribute('calculation_warnings', $ingredientWarnings);
                foreach ($ingredientWarnings as $warning) {
                    $warnings[] = $row->aliment->libelle0.' : '.$warning;
                }
            }
            $melange->setAttribute('calculation_warnings', $warnings);
        }
    }

    /** @return list<string> */
    public static function forAliment(Aliment $aliment, Ration $ration): array
    {
        $warnings = [];
        if ($aliment->ms === null || (float) $aliment->ms <= 0 || (float) $aliment->ms > 100) {
            $warnings[] = 'MS absente ou invalide : conversion MS/MB impossible ; une quantité en matière brute peut être ignorée ou fausser les proportions du mélange.';
        }

        $mineral = $aliment->type === TypeAliment::Mineral->value;
        $inra2018 = $ration->planRationnement->inra === '2018';
        if (! $mineral && $inra2018) {
            $missing = self::missing($aliment, CalculValeur::PRECURSEURS_DYNAMIQUES);
            $hasDynamicPrecursors = $missing === [];
            if (! $hasDynamicPrecursors) {
                $warnings[] = 'Calcul dynamique INRA 2018 impossible ('.implode(', ', $missing).' manquant). UFL/UFV, PDI et BPR tabulés utilisés lorsqu’ils sont renseignés ; leur adaptation à cette ration n’est pas vérifiée.';
                $missingValues = self::missing($aliment, [$ration->categorie()->uniteFourragere(), 'pdi', 'bpr']);
                if ($missingValues !== []) {
                    $warnings[] = 'Valeurs tabulées absentes ('.implode(', ', $missingValues).') : leur contribution est comptée à zéro.';
                }
            } else {
                $fields = ['ag', 'bpr'];
                if ($aliment->type === TypeAliment::Fourrage->value) {
                    $fields[] = 'niref';
                    if ($aliment->procede_technologique === 'ensile') {
                        $fields[] = 'pf';
                    }
                }
                $missing = self::missing($aliment, $fields);
                if ($missing !== []) {
                    $warnings[] = 'Calcul dynamique approximatif ('.implode(', ', $missing).' manquant) : le moteur utilise des valeurs par défaut.';
                }
            }

            if ((float) $aliment->amidon > 0 && $aliment->dt6_ami === null) {
                $warnings[] = 'DT6 amidon manquant pour un aliment contenant de l’amidon : indicateurs de digestion approximatifs.'
                    .($hasDynamicPrecursors ? ' Le BPR et les PDI recalculés peuvent aussi être affectés.' : ' Les valeurs tabulées ne corrigent pas ces indicateurs.');
            }
        } elseif (! $mineral) {
            $missing = self::missing($aliment, ['ufl2007', 'pdie2007', 'pdin2007']);
            if ($missing !== []) {
                $warnings[] = 'Valeurs INRA 2007 absentes ('.implode(', ', $missing).') : apports incomplets.';
            }
        }

        if (! $mineral) {
            $missingFibres = self::missing($aliment, ['ndf', 'adf', 'cb']);
            if ($missingFibres !== []) {
                $warnings[] = 'Fibres non renseignées ('.implode(', ', $missingFibres).') : apports de fibres et indicateurs associés incomplets.';
            }
            if ($inra2018 && $ration->categorie() === CategorieAnimal::VacheLaitiere) {
                $missingAminoAcids = self::missing($aliment, ['lys_di', 'met_di']);
                if ($missingAminoAcids !== []) {
                    $warnings[] = 'Acides aminés non renseignés ('.implode(', ', $missingAminoAcids).') : apports LysDI/MetDI incomplets, avec un effet possible sur l’efficacité des PDI.';
                }
            }
        }

        if ($aliment->type === TypeAliment::Fourrage->value) {
            $ue = $ration->categorie()->uniteEncombrement();
            $field = $inra2018 ? $ue : ($ration->categorie() === CategorieAnimal::VacheAllaitante ? 'ueb2007' : 'uel2007');
            if ((! $inra2018 || $ue !== 'kg_ms') && $aliment->{$field} === null) {
                $warnings[] = strtoupper($field).' absent : encombrement et calcul à volonté incomplets.';
            }
        }

        $mineralFields = $inra2018
            ? ['ca', 'p', 'mg', 'na', 'k', 'cl', 's', 'cu', 'zn', 'mn', 'co', 'se', 'i', 'molybdene']
            : ['ca', 'p', 'mg', 'na', 'k'];
        if (! $inra2018 && ! $mineral && (float) $aliment->ca > 0) {
            $mineralFields[] = 'caabs';
        }
        if ((! $inra2018 && ! $mineral || $inra2018 && $aliment->type === TypeAliment::Concentre->value) && (float) $aliment->p > 0) {
            $mineralFields[] = 'pabs';
        }
        $missingMinerals = self::missing($aliment, $mineralFields);
        if ($missingMinerals !== []) {
            $warnings[] = 'Minéraux non renseignés ('.implode(', ', $missingMinerals).') : bilan minéral incomplet ou calculé avec des coefficients par défaut.';
        }

        return $warnings;
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    private static function missing(Aliment $aliment, array $fields): array
    {
        return array_values(array_filter($fields, fn (string $field): bool => $aliment->{$field} === null));
    }
}
