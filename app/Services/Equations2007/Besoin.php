<?php

declare(strict_types=1);

namespace App\Services\Equations2007;

use App\Models\Ration;
use App\Services\RationHelper;

/**
 * Calculs des besoins nutritionnels INRA 2007.
 */
class Besoin
{
    public static function calculerTB(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $tbAnnuel = (float) ($ration->tb_annuel ?? 40);
        $semainesLactation = (float) ($ration->mois_lactation ?? 0) * 4.348;

        return match ($categorie) {
            'vacheLaitiere' => $tbAnnuel * (0.87 + 0.52 * exp(-0.62 * $semainesLactation) + 0.005 * $semainesLactation),
            default => 43.0,
        };
    }

    public static function calculerTP(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $tpAnnuel = (float) ($ration->tp_annuel ?? 32);
        $semainesLactation = (float) ($ration->mois_lactation ?? 0) * 4.348;

        return match ($categorie) {
            'vacheLaitiere' => $tpAnnuel * (0.88 + 1.18 * exp(-1.24 * $semainesLactation) + 0.005 * $semainesLactation),
            default => 33.0,
        };
    }

    public static function calculerBesoinUF_NP(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $activite = self::normaliserActivite($ration->activite);

        if ($categorie === 'vacheLaitiere') {
            $iAct = match ($activite) {
                'entravee' => 1.0,
                'stabulation' => 1.1,
                default => 1.2,
            };

            return $iAct * 0.041 * pow($poidsVif, 0.75);
        }

        if ($categorie === 'vacheAllaitante') {
            $iAct = match ($activite) {
                'entravee' => 1.0,
                'stabulation' => 1.1,
                default => 1.2,
            };
            $iStade = (int) ($ration->mois_lactation ?? 0) > 0 ? 0.041 : 0.037;
            $nec = (float) ($ration->nec ?? 2.5);

            return (($iAct * $iStade) + (0.0068 * ($nec - 2.5))) * pow($poidsVif, 0.75);
        }

        return 0.0;
    }

    public static function calculerBesoinEntretienUF(Ration $ration): float
    {
        return self::calculerBesoinUF_NP($ration);
    }

    public static function calculerBesoinUF_PL(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $laitObjectif = (float) ($ration->lait_objectif ?? 0);

        return match ($categorie) {
            'vacheLaitiere' => (0.44 + (0.0055 * (self::calculerTB($ration) - 40)) + (0.0033 * (self::calculerTP($ration) - 31))) * $laitObjectif,
            'vacheAllaitante' => 0.45 * $laitObjectif,
            default => 0.0,
        };
    }

    public static function calculerBesoinProductionUF(Ration $ration): float
    {
        return self::calculerBesoinUF_PL($ration);
    }

    public static function calculerBesoinUF_gest(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $semainesGestation = (float) ($ration->mois_gestation ?? 0) * 4.348;
        $poidsVeau = (float) ($ration->poids_veau_naissance ?? 50);

        return match ($categorie) {
            'vacheLaitiere', 'vacheAllaitante' => 0.00072 * $poidsVeau * exp(0.116 * $semainesGestation),
            default => 0.0,
        };
    }

    public static function calculerBesoinGestationUF(Ration $ration): float
    {
        return self::calculerBesoinUF_gest($ration);
    }

    public static function calculerBesoinUF_gain(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $pourcentagePrimipare = (float) ($ration->pourcentage_primipare ?? 0);

        return match ($categorie) {
            'vacheLaitiere', 'vacheAllaitante' => 0.6 * ($pourcentagePrimipare / 100),
            default => 0.0,
        };
    }

    public static function calculerBesoinCroissanceUF(Ration $ration): float
    {
        return self::calculerBesoinUF_gain($ration);
    }

    public static function calculerBesoinTotalUF(Ration $ration): float
    {
        return self::calculerBesoinUF_NP($ration)
            + self::calculerBesoinUF_PL($ration)
            + self::calculerBesoinUF_gest($ration)
            + self::calculerBesoinUF_gain($ration);
    }

    public static function calculerCapaciteIngestion(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $pourcentagePrimipare = (float) ($ration->pourcentage_primipare ?? 0);
        $nec = (float) ($ration->nec ?? 2.5);
        $race = strtolower((string) ($ration->race ?? ''));
        $laitObjectif = (float) ($ration->lait_objectif ?? 0);
        $moisLactation = (int) ($ration->mois_lactation ?? 0);
        $semainesLactation = $moisLactation * 4.348;
        $semainesGestation = (float) ($ration->mois_gestation ?? 0) * 4.348;

        if ($categorie === 'vacheLaitiere') {
            $a = 0.7 - (0.1 * $pourcentagePrimipare / 100);
            $ingestionLactation = $a + (1 - $a) * (1 - exp(-0.16 * $semainesLactation));
            $ingestionGestation = 0.8 + 0.2 * (1 - exp(-0.25 * (40 - $semainesGestation)));
            $ageMoyenMois = 60 - (24 * $pourcentagePrimipare / 100);
            $ingestionMaturite = -0.1 + 1.1 * (1 - exp(-0.08 * $ageMoyenMois));

            return (13.9 + (0.15 * $laitObjectif) + (0.015 * ($poidsVif - 600)) + (1.5 * (3 - $nec)))
                * $ingestionGestation
                * $ingestionLactation
                * $ingestionMaturite;
        }

        if ($categorie === 'vacheAllaitante') {
            $iRace = match ($race) {
                'charolaise' => 1.0,
                'limousine' => 0.95,
                default => 0.95,
            };
            $iStade = ($moisLactation === 0 || (float) ($ration->mois_gestation ?? 0) >= 8.0) ? 0.95 : 1.0;
            $iNoteCI = $moisLactation === 0 ? 0.002 : 0.0015;

            if ($moisLactation === 0) {
                $iPareCI = 1 - (0.12 * ($pourcentagePrimipare / 100));
            } elseif ($moisLactation <= 3) {
                $iPareCI = 1 - (0.1 * ($pourcentagePrimipare / 100));
            } elseif ($moisLactation <= 6) {
                $iPareCI = 1 - (0.07 * ($pourcentagePrimipare / 100));
            } elseif ($moisLactation <= 9) {
                $iPareCI = 1 - (0.04 * ($pourcentagePrimipare / 100));
            } else {
                $iPareCI = 1.0;
            }

            return $iRace
                * $iStade
                * (3.2 + (0.015 * $poidsVif) + (0.25 * $laitObjectif) - ($iNoteCI * $poidsVif * ($nec - 2.5)))
                * $iPareCI;
        }

        return 0.0;
    }

    public static function calculerBesoinPDI_NP(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $poidsVif = (float) ($ration->poids_vif ?? 650);

        return match ($categorie) {
            'vacheLaitiere', 'vacheAllaitante' => 3.25 * pow($poidsVif, 0.75),
            default => 0.0,
        };
    }

    public static function calculerBesoinEntretienPDI(Ration $ration): float
    {
        return self::calculerBesoinPDI_NP($ration);
    }

    public static function calculerBesoinPDI_gain(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $pourcentagePrimipare = (float) ($ration->pourcentage_primipare ?? 0);

        return match ($categorie) {
            'vacheLaitiere', 'vacheAllaitante' => 80 * ($pourcentagePrimipare / 100),
            default => 0.0,
        };
    }

    public static function calculerBesoinCroissancePDI(Ration $ration): float
    {
        return self::calculerBesoinPDI_gain($ration);
    }

    public static function calculerBesoinPDI_gest(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $semainesGestation = (float) ($ration->mois_gestation ?? 0) * 4.348;
        $poidsVeau = (float) ($ration->poids_veau_naissance ?? 50);

        return match ($categorie) {
            'vacheLaitiere', 'vacheAllaitante' => 0.07 * $poidsVeau * exp(0.111 * $semainesGestation),
            default => 0.0,
        };
    }

    public static function calculerBesoinGestationPDI(Ration $ration): float
    {
        return self::calculerBesoinPDI_gest($ration);
    }

    public static function calculerBesoinPDI_PL(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $laitObjectif = (float) ($ration->lait_objectif ?? 0);

        return match ($categorie) {
            'vacheLaitiere' => (self::calculerTP($ration) / 0.64) * $laitObjectif,
            'vacheAllaitante' => 53 * $laitObjectif,
            default => 0.0,
        };
    }

    public static function calculerBesoinProductionPDI(Ration $ration): float
    {
        return self::calculerBesoinPDI_PL($ration);
    }

    public static function calculerBesoinTotalPDI(Ration $ration): float
    {
        return self::calculerBesoinPDI_NP($ration)
            + self::calculerBesoinPDI_gain($ration)
            + self::calculerBesoinPDI_gest($ration)
            + self::calculerBesoinPDI_PL($ration)
            + self::calculerAdaptationStrategie($ration->strategie);
    }

    public static function calculerBesoinCaabs(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $apportTotalMS = Apport::calculerApportTotalMS($ration);
        $besoinTotalUF = self::calculerBesoinTotalUF($ration);
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $objectifLait = (float) ($ration->lait_objectif ?? 0);
        $moisLactation = (int) ($ration->mois_lactation ?? 0);
        $semainesGestation = (float) ($ration->mois_gestation ?? 0) * 4.348;

        return match ($categorie) {
            'vacheLaitiere' => (0.663 * $apportTotalMS)
                + (0.008 * $poidsVif)
                + ($semainesGestation >= 27 ? 23.5 / (1 + exp(18.8 - (5.03 * log($semainesGestation)))) : 0.0)
                + (1.25 * $objectifLait),
            'vacheAllaitante' => $moisLactation > 0
                ? (3 * $besoinTotalUF) - 3.47
                : (2.38 * $besoinTotalUF) - 1.55,
            default => 0.0,
        };
    }

    public static function calculerBesoinPabs(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $apportTotalMS = Apport::calculerApportTotalMS($ration);
        $besoinTotalUF = self::calculerBesoinTotalUF($ration);
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $objectifLait = (float) ($ration->lait_objectif ?? 0);
        $moisLactation = (int) ($ration->mois_lactation ?? 0);
        $semainesGestation = (float) ($ration->mois_gestation ?? 0) * 4.348;

        return match ($categorie) {
            'vacheLaitiere' => (0.83 * $apportTotalMS)
                + (0.002 * $poidsVif)
                + ($semainesGestation >= 27 ? 7.38 / (1 + exp(19.1 - (5.46 * log($semainesGestation)))) : 0.0)
                + (0.9 * $objectifLait),
            'vacheAllaitante' => $moisLactation > 0
                ? (2.3 * $besoinTotalUF) - 1.77
                : (0.85 * $besoinTotalUF) + 7.38,
            default => 0.0,
        };
    }

    public static function calculerBesoinMgabs(Ration $ration): float
    {
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $objectifLait = (float) ($ration->lait_objectif ?? 0);
        $semainesGestation = (float) ($ration->mois_gestation ?? 0) * 4.348;

        return (0.011 * $poidsVif)
            + ($semainesGestation >= 27 ? 0.3 : 0.0)
            + (0.15 * $objectifLait);
    }

    public static function calculerBesoinNa(Ration $ration): float
    {
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $objectifLait = (float) ($ration->lait_objectif ?? 0);
        $semainesGestation = (float) ($ration->mois_gestation ?? 0) * 4.348;
        $entretien = $objectifLait !== 0.0 ? 0.023 * $poidsVif : 0.015 * $poidsVif;

        return $entretien + ($semainesGestation >= 27 ? 1.3 : 0.0) + (0.45 * $objectifLait);
    }

    public static function calculerBesoinK(Ration $ration): float
    {
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $objectifLait = (float) ($ration->lait_objectif ?? 0);
        $semainesGestation = (float) ($ration->mois_gestation ?? 0) * 4.348;
        $entretien = $objectifLait !== 0.0 ? 0.115 * $poidsVif : 0.07 * $poidsVif;

        return $entretien + ($semainesGestation >= 27 ? 1.0 : 0.0) + (1.5 * $objectifLait);
    }

    public static function calculerBesoinCl(Ration $ration): float
    {
        $poidsVif = (float) ($ration->poids_vif ?? 650);
        $objectifLait = (float) ($ration->lait_objectif ?? 0);
        $semainesGestation = (float) ($ration->mois_gestation ?? 0) * 4.348;
        $entretien = $objectifLait !== 0.0 ? 0.035 * $poidsVif : 0.023 * $poidsVif;

        return $entretien + ($semainesGestation >= 27 ? 1.0 : 0.0) + (0.15 * $objectifLait);
    }

    public static function calculerBesoinS(Ration $ration): float
    {
        return 0.2 * Apport::calculerApportTotalMS($ration);
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

    private static function normaliserActivite(?string $activite): string
    {
        return match (strtolower((string) $activite)) {
            'entravee' => 'entravee',
            'stabulation', 'nulle' => 'stabulation',
            default => 'paturage',
        };
    }

    private static function calculerAdaptationStrategie(?string $strategie): float
    {
        $strategie = strtolower(trim((string) $strategie));

        if ($strategie === '') {
            return 0.0;
        }

        if ($strategie === 'incomplete' || str_contains($strategie, 'incompl')) {
            return 200.0;
        }

        if ($strategie === 'bonne' || str_contains($strategie, 'bonne')) {
            return 300.0;
        }

        if ($strategie === 'complete' || str_contains($strategie, 'compl')) {
            return 400.0;
        }

        return 0.0;
    }
}
