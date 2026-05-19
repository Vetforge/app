<?php

declare(strict_types=1);

namespace App\Services\Equations2018;

use App\Models\Ration;
use App\Services\RationHelper;

/**
 * Calculs des besoins nutritionnels INRA 2018.
 */
class Besoin
{
    // ─── TB / TP ───────────────────────────────────────────────────────────────

    public static function calculerTB(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $TBannuel = (float) ($ration->tb_annuel ?? 40);
        $semLactation = RationHelper::calculerSemainesLactation($ration);

        return match ($cat) {
            'vacheLaitiere' => $TBannuel * (0.87 + 0.52 * exp(-0.62 * $semLactation) + 0.005 * $semLactation),
            default => 43.0,
        };
    }

    public static function calculerTP(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $TPannuel = (float) ($ration->tp_annuel ?? 32);
        $semLactation = RationHelper::calculerSemainesLactation($ration);

        return match ($cat) {
            'vacheLaitiere' => $TPannuel * (0.88 + 1.18 * exp(-1.24 * $semLactation) + 0.005 * $semLactation),
            default => 33.0,
        };
    }

    // ─── Besoins UF ────────────────────────────────────────────────────────────

    public static function calculerBesoinUF_NP(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $activite = RationHelper::normalizeActivite2018($ration->activite);
        $NEC = (float) ($ration->nec ?? 2.5);
        $deltaPV = (float) ($ration->ecart_variation_reserve ?? 0);
        $moisLact = (float) ($ration->mois_lactation ?? 0);

        if ($cat === 'vacheLaitiere') {
            $iAct = match ($activite) {
                'entravee' => 0.95,
                'stabulation' => 1.0,
                'plaine' => 1.1,
                'vallon' => 1.2,
                'montagne' => 1.3,
                default => 1.0,
            };

            return $iAct * 0.0536 * pow($poidsVif, 0.75);
        }

        if ($cat === 'vacheAllaitante') {
            $iAct = match ($activite) {
                'entravee' => 1.0,
                'stabulation' => 1.08,
                'plaine' => 1.2,
                'vallon', 'montagne' => 1.3,
                default => 1.08,
            };
            $besUFL_ent = $moisLact > 0 ? 0.049 : 0.043;

            return ($iAct * $besUFL_ent + 0.0073 * $deltaPV * $NEC) * pow($poidsVif, 0.75);
        }

        return 0.0;
    }

    public static function calculerBesoinUF_PL(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $lait = (float) ($ration->lait_objectif ?? 0);
        $TB = self::calculerTB($ration);
        $TP = self::calculerTP($ration);

        return match ($cat) {
            'vacheLaitiere', 'vacheAllaitante' => (0.42 + 0.0053 * ($TB - 40) + 0.0032 * ($TP - 31)) * $lait,
            default => 0.0,
        };
    }

    public static function calculerBesoinUF_gest(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $moisGest = (float) ($ration->mois_gestation ?? 0);
        $semGest = RationHelper::calculerSemainesGestation($ration);
        $poidsVeau = (float) ($ration->poids_veau_naissance ?? 50);

        return match ($cat) {
            'vacheLaitiere', 'vacheAllaitante' => 0.000695 * $poidsVeau * exp(0.116 * $semGest),
            default => 0.0,
        };
    }

    public static function calculerBesoinUF_gain(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $primipare = (float) ($ration->pourcentage_primipare ?? 0);

        return match ($cat) {
            'vacheLaitiere', 'vacheAllaitante' => 0.6 * ($primipare / 100),
            default => 0.0,
        };
    }

    public static function calculerBesoinUF_DRC(Ration $ration): float
    {
        $deltaPV = (float) ($ration->ecart_variation_reserve ?? 0);

        return 2.4 * $deltaPV;
    }

    public static function calculerBesoinTotalUF(Ration $ration): float
    {
        return self::calculerBesoinUF_NP($ration)
            + self::calculerBesoinUF_PL($ration)
            + self::calculerBesoinUF_gest($ration)
            + self::calculerBesoinUF_gain($ration)
            + self::calculerBesoinUF_DRC($ration);
    }

    // ─── Capacité d'ingestion ──────────────────────────────────────────────────

    public static function calculerCapaciteIngestion(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $lait = $cat === 'vacheLaitiere'
            ? RationHelper::calculerProductionLaitPotentielle($ration)
            : (float) ($ration->lait_objectif ?? 0);
        $NEC = (float) ($ration->nec ?? 2.5);
        $primipare = (float) ($ration->pourcentage_primipare ?? 0);
        $race = RationHelper::normalizeRace($ration->race);
        $moisLact = (int) floor((float) ($ration->mois_lactation ?? 0));
        $moisGest = (float) ($ration->mois_gestation ?? 0);
        $semGest = RationHelper::calculerSemainesGestation($ration);
        $semLact = RationHelper::calculerSemainesLactation($ration);
        $PDI = Apport::calculerApportTotalPDI($ration);
        $UFL = Apport::calculerApportTotalUF($ration);

        if ($cat === 'vacheLaitiere') {
            $a = 0.7 - 0.1 * $primipare / 100;
            $Ind_CILact = $a + (1 - $a) * (1 - exp(-0.25 * $semLact));
            $Ind_CIGest = 0.8 + 0.2 * (1 - exp(-0.25 * (40 - $semGest)));
            $ageMoyenMois = 60 - 24 * $primipare / 100;
            $Ind_CIMat = -0.1 + 1.1 * (1 - exp(-0.08 * $ageMoyenMois));
            $Ind_CIPDI = $UFL > 0
                ? 0.91 + (0.115 / (1 + exp(0.13 * (90 - $PDI / $UFL))))
                : 0.0;

            return (14.25 + 0.11 * $lait + 0.015 * ($poidsVif - 600) + (2.5 - $NEC))
                * $Ind_CIGest * $Ind_CILact * $Ind_CIMat * $Ind_CIPDI;
        }

        if ($cat === 'vacheAllaitante') {
            $iRace = match ($race) {
                'limousine' => 0.95,
                'croiselaitiere' => 1.15,
                default => 1.0,
            };
            $iStade = match (true) {
                $moisLact === 0 => 0.95,
                $moisLact === 1 => 0.98,
                $moisLact === 2 => 1.0,
                $moisLact === 3 => 1.02,
                $moisGest >= 8 => 0.95,
                default => 1.0,
            };
            $iNote = $moisGest > 0 ? 0.002 : ($moisLact > 0 ? 0.0015 : 0.0);
            $iPare = match (true) {
                $moisGest > 0 => 0.88,
                $moisLact === 1 => 0.9,
                $moisLact === 2 => 0.93,
                $moisLact === 3 => 0.96,
                default => 1.0,
            };

            return $iRace * $iStade * $iPare
                * (3.2 + 0.015 * $poidsVif + 0.25 * $lait - $iNote * $poidsVif * ($NEC - 2.5));
        }

        return 0.0;
    }

    // ─── Besoins PDI ───────────────────────────────────────────────────────────

    public static function calculerBesoinPDI_NP(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $EffPDI = Apport::calculerEffPDI($ration);
        $MSI = Apport::calculerApportTotalMS($ration);
        $MOND = Apport::calculerApportMOND($ration);

        return match ($cat) {
            'vacheLaitiere', 'vacheAllaitante' => $EffPDI > 0
                ? 0.312 * $poidsVif
                    + (0.2 * pow($poidsVif, 0.6)) / $EffPDI
                    + $MSI * (0.5 * (5.7 + 0.074 * $MOND)) / $EffPDI
                : 0.0,
            default => 0.0,
        };
    }

    public static function calculerBesoinPDI_gain(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $primipare = (float) ($ration->pourcentage_primipare ?? 0);
        $EffPDI = Apport::calculerEffPDI($ration);

        return match ($cat) {
            'vacheLaitiere', 'vacheAllaitante' => $EffPDI > 0
                ? (56 * ($primipare / 100)) / $EffPDI
                : 0.0,
            default => 0.0,
        };
    }

    public static function calculerBesoinPDI_gest(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $moisGest = (float) ($ration->mois_gestation ?? 0);
        $semGest = RationHelper::calculerSemainesGestation($ration);
        $poidsVeau = (float) ($ration->poids_veau_naissance ?? 50);
        $EffPDI = Apport::calculerEffPDI($ration);

        return match ($cat) {
            'vacheLaitiere', 'vacheAllaitante' => $EffPDI > 0 && $semGest > 0
                ? (0.0448 * $poidsVeau * exp(0.111 * $semGest)) / $EffPDI
                : 0.0,
            default => 0.0,
        };
    }

    public static function calculerBesoinPDI_PL(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $lait = (float) ($ration->lait_objectif ?? 0);
        $EffPDI = Apport::calculerEffPDI($ration);
        $TP = self::calculerTP($ration);

        return match ($cat) {
            'vacheLaitiere', 'vacheAllaitante' => $EffPDI > 0
                ? ($TP * $lait) / $EffPDI
                : 0.0,
            default => 0.0,
        };
    }

    public static function calculerBesoinPDI_DRC(Ration $ration): float
    {
        $primipare = (float) ($ration->pourcentage_primipare ?? 0);
        $deltaPV = (float) ($ration->ecart_variation_reserve ?? 0);
        $EffPDI = Apport::calculerEffPDI($ration);

        return $EffPDI > 0
            ? ((0.16 - 0.03 * $primipare / 100) * $deltaPV) / $EffPDI
            : 0.0;
    }

    public static function calculerBesoinTotalPDI(Ration $ration): float
    {
        return self::calculerBesoinPDI_NP($ration)
            + self::calculerBesoinPDI_gain($ration)
            + self::calculerBesoinPDI_gest($ration)
            + self::calculerBesoinPDI_PL($ration)
            + self::calculerBesoinPDI_DRC($ration);
    }

    // ─── Besoins minéraux ──────────────────────────────────────────────────────

    public static function calculerBesoinCaabs(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $apportTotalMS = Apport::calculerApportTotalMS($ration);
        $besoinTotalUF = self::calculerBesoinTotalUF($ration);
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $lait = (float) ($ration->lait_objectif ?? 0);
        $moisGest = (float) ($ration->mois_gestation ?? 0);
        $semGest = RationHelper::calculerSemainesGestation($ration);
        $moisLact = (float) ($ration->mois_lactation ?? 0);

        return match ($cat) {
            'vacheLaitiere' => (0.663 * $apportTotalMS) + (0.008 * $poidsVif)
                + ($semGest >= 27 ? 23.5 / (1 + exp(18.8 - 5.03 * log($semGest))) : 0.0)
                + 1.25 * $lait,
            'vacheAllaitante' => $moisLact > 0
                ? 2.9 * $besoinTotalUF - 3.35
                : 2.3 * $besoinTotalUF - 1.5,
            default => 0.0,
        };
    }

    public static function calculerBesoinPabs(Ration $ration): float
    {
        $cat = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $apportTotalMS = Apport::calculerApportTotalMS($ration);
        $besoinTotalUF = self::calculerBesoinTotalUF($ration);
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $lait = (float) ($ration->lait_objectif ?? 0);
        $moisGest = (float) ($ration->mois_gestation ?? 0);
        $semGest = RationHelper::calculerSemainesGestation($ration);
        $moisLact = (float) ($ration->mois_lactation ?? 0);

        return match ($cat) {
            'vacheLaitiere' => (0.83 * $apportTotalMS) + (0.002 * $poidsVif)
                + ($semGest >= 27 ? 7.38 / (1 + exp(19.1 - 5.46 * log($semGest))) : 0.0)
                + 0.9 * $lait,
            'vacheAllaitante' => $moisLact > 0
                ? 2.22 * $besoinTotalUF - 1.71
                : 0.82 * $besoinTotalUF + 7.03,
            default => 0.0,
        };
    }

    public static function calculerBesoinMgabs(Ration $ration): float
    {
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $lait = (float) ($ration->lait_objectif ?? 0);
        $semGest = RationHelper::calculerSemainesGestation($ration);

        return 0.011 * $poidsVif
            + ($semGest >= 27 ? 0.3 : 0.0)
            + 0.15 * $lait;
    }

    public static function calculerBesoinNa(Ration $ration): float
    {
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $lait = (float) ($ration->lait_objectif ?? 0);
        $semGest = RationHelper::calculerSemainesGestation($ration);
        $entretien = $lait > 0 ? 0.023 * $poidsVif : 0.015 * $poidsVif;

        return $entretien
            + ($semGest >= 27 ? 1.3 : 0.0)
            + 0.45 * $lait;
    }

    public static function calculerBesoinK(Ration $ration): float
    {
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $lait = (float) ($ration->lait_objectif ?? 0);
        $semGest = RationHelper::calculerSemainesGestation($ration);
        $entretien = $lait > 0 ? 0.115 * $poidsVif : 0.07 * $poidsVif;

        return $entretien
            + ($semGest >= 27 ? 1.0 : 0.0)
            + 1.5 * $lait;
    }

    public static function calculerBesoinCl(Ration $ration): float
    {
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $lait = (float) ($ration->lait_objectif ?? 0);
        $semGest = RationHelper::calculerSemainesGestation($ration);
        $entretien = $lait > 0 ? 0.035 * $poidsVif : 0.023 * $poidsVif;

        return $entretien
            + ($semGest >= 27 ? 1.0 : 0.0)
            + 0.15 * $lait;
    }

    public static function calculerBesoinS(Ration $ration): float
    {
        return 2 * Apport::calculerApportTotalMS($ration);
    }

    public static function calculerBesoinCo(Ration $ration): float
    {
        return 0.3 * Apport::calculerApportTotalMS($ration);
    }

    public static function calculerBesoinCu(Ration $ration): float
    {
        return 10 * Apport::calculerApportTotalMS($ration);
    }

    public static function calculerBesoinI(Ration $ration): float
    {
        return 0.5 * Apport::calculerApportTotalMS($ration);
    }

    public static function calculerBesoinMn(Ration $ration): float
    {
        return 50 * Apport::calculerApportTotalMS($ration);
    }

    public static function calculerBesoinSe(Ration $ration): float
    {
        return 0.2 * Apport::calculerApportTotalMS($ration);
    }

    public static function calculerBesoinZn(Ration $ration): float
    {
        return 50 * Apport::calculerApportTotalMS($ration);
    }

    public static function calculerBesoinMo(Ration $ration): float
    {
        return 0.5 * Apport::calculerApportTotalMS($ration);
    }

    public static function calculerBesoinVitA(Ration $ration): float
    {
        return 100 * (float) ($ration->poids_vif ?? 650);
    }

    public static function calculerBesoinVitD(Ration $ration): float
    {
        return 30 * (float) ($ration->poids_vif ?? 650);
    }

    public static function calculerBesoinVitE(Ration $ration): float
    {
        return (float) ($ration->poids_vif ?? 650);
    }
}
