<?php

declare(strict_types=1);

namespace App\Services\Equations2018;

use App\Enums\CategorieAnimal;
use App\Models\Ration;
use App\Services\RationHelper;

/**
 * Calculs des impacts nutritionnels INRA 2018 (lait permis, eau, coût…).
 */
class Impact
{
    public static function calculerLaitPermisParUF(Ration $ration): float
    {
        $totalUFL = Apport::calculerApportTotalUF($ration);
        $besoinNP = Besoin::calculerBesoinUF_NP($ration);
        $besoinGain = Besoin::calculerBesoinUF_gain($ration);
        $besoinGest = Besoin::calculerBesoinUF_gest($ration);
        $besoinDRC = Besoin::calculerBesoinUF_DRC($ration);
        $TB = Besoin::calculerTB($ration);
        $TP = Besoin::calculerTP($ration);
        $denom = 0.42 + 0.0053 * ($TB - 40) + 0.0032 * ($TP - 31);

        return $denom > 0
            ? ($totalUFL - $besoinNP - $besoinGain - $besoinGest - $besoinDRC) / $denom
            : 0.0;
    }

    public static function calculerLaitPermisParPDI(Ration $ration): float
    {
        $totalPDI = Apport::calculerApportTotalPDI($ration);
        $besoinNP = Besoin::calculerBesoinPDI_NP($ration);
        $besoinGain = Besoin::calculerBesoinPDI_gain($ration);
        $besoinGest = Besoin::calculerBesoinPDI_gest($ration);
        $besoinDRC = Besoin::calculerBesoinPDI_DRC($ration);
        $EffPDI = Apport::calculerEffPDI($ration);
        $TP = Besoin::calculerTP($ration);

        return $TP > 0
            ? ($totalPDI - $besoinNP - $besoinGain - $besoinGest - $besoinDRC) * $EffPDI / $TP
            : 0.0;
    }

    /**
     * Production laitière réelle tenant compte du bilan UFL et PDI (méthode INRA 2018).
     */
    public static function calculerPL(Ration $ration): float
    {
        $PLpot = RationHelper::calculerProductionLaitPotentielle($ration);
        $TP = Besoin::calculerTP($ration);
        $UFL = Apport::calculerApportTotalUF($ration);
        $PDI = Apport::calculerApportTotalPDI($ration);
        $UFL_VPR = Apport::calculerApportUFL_VPR($ration, false);
        $PDI_ut = RationHelper::calculerPDIUt($ration);
        $PDI_VPR = $PDI_ut + 33 * $UFL_VPR;
        $besoinTotalUF = self::calculerBesoinTotalUFPotentiel($ration, $PLpot);
        $besoinTotalPDI = self::calculerBesoinTotalPDIPotentiel($ration, $PLpot);
        $bilUFL = $UFL + $UFL_VPR - $besoinTotalUF;
        $bilPDI = $PDI + $PDI_VPR - $besoinTotalPDI;
        $CPDI = 0.3 * $bilPDI - 0.0001 * $bilPDI * $bilPDI;
        $rep_MP = ($PLpot * $TP / 850)
            * (49.6 + 50 * $bilUFL - 71.5 * log(1 + exp(($bilUFL - 0.014 * $CPDI) / 1.43)));
        $rep_PL = 0.029 * $rep_MP;

        return $PLpot + $rep_PL;
    }

    public static function calculerEauBue(Ration $ration): float
    {
        $lait = (float) ($ration->lait_objectif ?? 0);
        $apportTotalMS = Apport::calculerApportTotalMS($ration);
        $pourcentageMS = Apport::calculerApportMSParMB($ration) * 100;
        $fourragesMS = Apport::calculerApportFourragesMS($ration);
        $PFO = $apportTotalMS > 0 ? $fourragesMS / $apportTotalMS : 0.0;
        $MATf = $apportTotalMS > 0 ? Apport::calculerApportFourragesMAT($ration) / $apportTotalMS : 0.0;

        $eauBueTH = self::calculerEauBueTH($ration);

        return $pourcentageMS > 0
            ? $eauBueTH - 4.34 + 0.88 * $lait
                + $apportTotalMS * (4.6 - 100 / $pourcentageMS)
                + 0.0012 * pow($MATf * $PFO, 2)
            : 0.0;
    }

    public static function calculerEauBueTH(Ration $ration): float
    {
        $temperature = (float) ($ration->temperature_ambiante ?? 15);
        $poidsVif = RationHelper::poidsVif($ration);

        if ($temperature <= 15) {
            return 0.0;
        }

        return ((85.2 * exp(($temperature - 24.9) / 8)
            + 2.25 * exp(($temperature - 12) / 6.8)
            - 28.2) * 0.14 * pow($poidsVif, 0.57) * 86.4) / 2500;
    }

    public static function calculerCoutParAnimal(Ration $ration): float
    {
        return Apport::calculerCoutParAnimal($ration);
    }

    public static function calculerCoutPar1000Lait(Ration $ration): float
    {
        $lait = (float) ($ration->lait_objectif ?? 0);

        return $lait > 0 ? self::calculerCoutParAnimal($ration) * 1000 / $lait : 0.0;
    }

    public static function calculerCH4(Ration $ration): float
    {
        $apportMS = Apport::calculerApportTotalMS($ration);
        $MOD = Apport::calculerApportMOD($ration);
        $NI = Apport::calculerNI($ration);
        $PCO = Apport::calculerPCO($ration);
        $CH4MOD = 45.42 - 6.66 * $NI + 0.75 * $NI * $NI + 19.65 * $PCO - 35 * $PCO * $PCO - 2.69 * $NI * $PCO;

        return $apportMS * 0.001 * $MOD * $CH4MOD;
    }

    /**
     * Bilan UFL = UFL apporté + VPR - besoins totaux ajustés.
     */
    public static function calculerBilUFL(Ration $ration): float
    {
        $UFL = Apport::calculerApportTotalUF($ration);
        $UFL_VPR = Apport::calculerApportUFL_VPR($ration, false);
        $besoinTotalUF = Besoin::calculerBesoinTotalUF($ration) + self::calculerDeltaUFprod($ration);

        return $UFL + $UFL_VPR - $besoinTotalUF;
    }

    public static function calculerPH(Ration $ration): float
    {
        $amidonRumen = Apport::calculerApportAmiD_ru($ration);

        return 6.4 - 0.001 * $amidonRumen;
    }

    public static function calculerNND(Ration $ration): float
    {
        $MAT = Apport::calculerApportMAT($ration);
        $DT_N = Apport::calculerDT_N($ration);
        $PIA = $MAT * (1 - 0.01 * $DT_N);
        $MAmicDuo = Apport::calculerMAmic_duo($ration);
        $NDFND = Apport::calculerApportNDFNDParKgMS($ration);
        $NNDParKgMS = (26.9 + 0.193 * $PIA + 0.106 * $MAmicDuo + 0.022 * $NDFND) / 6.25;
        $apportMS = Apport::calculerApportTotalMS($ration);

        return $NNDParKgMS * $apportMS;
    }

    public static function calculerRapportCN(Ration $ration): float
    {
        $MAT = Apport::calculerApportMAT($ration);
        $PCO = Apport::calculerPCO($ration);

        return 14.2 + 52.7 * exp(-0.014 * $MAT) - 3.74 * $PCO;
    }

    public static function calculerNU(Ration $ration): float
    {
        $categorie = RationHelper::categorie($ration->categorie_animal ?? '');
        if (! in_array($categorie, [CategorieAnimal::VacheLaitiere, CategorieAnimal::VacheAllaitante], true)) {
            return 0.0;
        }

        $poidsVif = RationHelper::poidsVif($ration);
        $bpr = Apport::calculerBPR($ration);
        $apportPDI = Apport::calculerApportTotalPDI($ration);
        $apportMS = Apport::calculerApportTotalMS($ration);
        $effPDI = Apport::calculerEffPDI($ration);
        $azoteEndogene = 0.05 * $poidsVif;
        $azoteMicrobien = Apport::calculerMAmic_duo($ration) / 6.25;
        $azoteNonUtiliseMicrobien = $azoteMicrobien * 0.116 * 0.8 * 0.85;
        $PDI = Apport::calculerApportTotalPDI($ration);
        $UFL_VPR = Apport::calculerApportUFL_VPR($ration, false);
        $PDI_ut = RationHelper::calculerPDIUt($ration);
        $PDI_VPR = $PDI_ut + 33 * $UFL_VPR;
        $besoinTotalPDI = Besoin::calculerBesoinTotalPDI($ration);
        $bilPDI = $PDI + $PDI_VPR - $besoinTotalPDI;
        $azoteUrinaireRumen = 0.79 * $bpr * $apportMS / 6.25
            + $apportPDI / 6.25 * (1 - $effPDI)
            + $azoteEndogene
            + $azoteNonUtiliseMicrobien * $apportMS
            + $bilPDI / 6.25;

        return 0.05 + 0.83 * $azoteUrinaireRumen;
    }

    public static function calculerBE(Ration $ration): float
    {
        $K = Apport::calculerApportK($ration);
        $Na = Apport::calculerApportNa($ration);
        $Cl = Apport::calculerApportCl($ration);
        $MS = Apport::calculerApportTotalMS($ration);

        return $MS > 0 ? (($K / 39) + ($Na / 23) - ($Cl / 35.5)) / $MS * 1000 : 0.0;
    }

    public static function calculerBACA(Ration $ration): float
    {
        $K = Apport::calculerApportK($ration);
        $Na = Apport::calculerApportNa($ration);
        $Cl = Apport::calculerApportCl($ration);
        $S = Apport::calculerApportS($ration);
        $MS = Apport::calculerApportTotalMS($ration);

        return $MS > 0 ? (($K / 39) + ($Na / 23) - ($Cl / 35.5) - ($S / 16)) / $MS * 1000 : 0.0;
    }

    public static function calculerIRA(Ration $ration): float
    {
        $BE = self::calculerBE($ration);
        if ($BE < 200) {
            $iraBE = 2;
        } elseif ($BE < 250) {
            $iraBE = 1;
        } else {
            $iraBE = 0;
        }

        $amidonRumen = Apport::calculerApportAmiD_ru($ration);
        if ($amidonRumen < 200) {
            $iraAmidon = 0;
        } elseif ($amidonRumen < 250) {
            $iraAmidon = 1;
        } else {
            $iraAmidon = 2;
        }

        $PCO = Apport::calculerPCO($ration);
        if ($PCO < 0.4) {
            $iraPCO = 0;
        } elseif ($PCO < 0.5) {
            $iraPCO = 1;
        } else {
            $iraPCO = 2;
        }

        $ndfFourrages = Apport::calculerApportNDFf($ration);
        if ($ndfFourrages < 250) {
            $iraNdfFourrages = 2;
        } elseif ($ndfFourrages < 300) {
            $iraNdfFourrages = 1;
        } else {
            $iraNdfFourrages = 0;
        }

        $ndfTotal = Apport::calculerApportNDFParKgMS($ration);
        if ($ndfTotal < 300) {
            $iraNdfTotal = 2;
        } elseif ($ndfTotal < 350) {
            $iraNdfTotal = 1;
        } else {
            $iraNdfTotal = 0;
        }

        return ($iraBE + $iraAmidon + $iraPCO + $iraNdfFourrages + $iraNdfTotal) / 5;
    }

    public static function calculerPourcentageAcetate(Ration $ration): float
    {
        $categorie = RationHelper::categorie($ration->categorie_animal ?? '');
        if (! in_array($categorie, [CategorieAnimal::VacheLaitiere, CategorieAnimal::VacheAllaitante], true)) {
            return 0.0;
        }

        $NDFD = Apport::calculerApportNDFD($ration);
        $MOD = Apport::calculerApportMOD($ration);
        $DTAmi = Apport::calculerDTAmi($ration);
        $NI = Apport::calculerNI($ration);
        $ratio = $MOD > 0 ? 100 * $NDFD / $MOD : 0.0;

        return $ratio > 0 ? 54.2 + 12 * log10($ratio) - 0.052 * $DTAmi - 1.99 * $NI : 0.0;
    }

    public static function calculerPourcentagePropionate(Ration $ration): float
    {
        $categorie = RationHelper::categorie($ration->categorie_animal ?? '');
        if (! in_array($categorie, [CategorieAnimal::VacheLaitiere, CategorieAnimal::VacheAllaitante], true)) {
            return 0.0;
        }

        $NDFD = Apport::calculerApportNDFD($ration);
        $MOD = Apport::calculerApportMOD($ration);
        $DTAmi = Apport::calculerDTAmi($ration);
        $NI = Apport::calculerNI($ration);
        $ratio = $MOD > 0 ? 100 * $NDFD / $MOD : 0.0;

        return $ratio > 0 ? 19.7 - 6.63 * log10($ratio) + 0.07 * $DTAmi + 2.62 * $NI : 0.0;
    }

    public static function calculerPourcentageButyrate(Ration $ration): float
    {
        $categorie = RationHelper::categorie($ration->categorie_animal ?? '');
        if (! in_array($categorie, [CategorieAnimal::VacheLaitiere, CategorieAnimal::VacheAllaitante], true)) {
            return 0.0;
        }

        $NDFD = Apport::calculerApportNDFD($ration);
        $MOD = Apport::calculerApportMOD($ration);
        $DTAmi = Apport::calculerDTAmi($ration);
        $ratio = $MOD > 0 ? 100 * $NDFD / $MOD : 0.0;

        return $ratio > 0 ? 19 - 3.99 * log10($ratio) - 0.026 * $DTAmi : 0.0;
    }

    public static function calculerDeltaUFprod(Ration $ration): float
    {
        $categorie = RationHelper::categorie($ration->categorie_animal ?? '');
        if (! in_array($categorie, [CategorieAnimal::VacheLaitiere, CategorieAnimal::VacheAllaitante], true)) {
            return 0.0;
        }

        $laitObjectif = (float) ($ration->lait_objectif ?? 0);
        $laitPermisParUF = self::calculerLaitPermisParUF($ration);
        $laitPermisParPDI = self::calculerLaitPermisParPDI($ration);
        $productionLimitante = min($laitPermisParUF, $laitPermisParPDI);
        $deltaPL = $productionLimitante - $laitObjectif;

        return (0.42 + (0.0053 * (Besoin::calculerTB($ration) - 40)) + (0.0032 * (Besoin::calculerTP($ration) - 31))) * $deltaPL;
    }

    private static function calculerBesoinTotalUFPotentiel(Ration $ration, float $PLpot): float
    {
        $coefficientLait = 0.42
            + (0.0053 * (Besoin::calculerTB($ration) - 40))
            + (0.0032 * (Besoin::calculerTP($ration) - 31));

        return Besoin::calculerBesoinUF_NP($ration)
            + Besoin::calculerBesoinUF_gain($ration)
            + Besoin::calculerBesoinUF_gest($ration)
            + Besoin::calculerBesoinUF_DRC($ration)
            + ($coefficientLait * $PLpot);
    }

    private static function calculerBesoinTotalPDIPotentiel(Ration $ration, float $PLpot): float
    {
        $effPDI = Apport::calculerEffPDI($ration);
        $besoinProduction = $effPDI > 0
            ? (Besoin::calculerTP($ration) * $PLpot) / $effPDI
            : 0.0;

        return Besoin::calculerBesoinPDI_NP($ration)
            + Besoin::calculerBesoinPDI_gain($ration)
            + Besoin::calculerBesoinPDI_gest($ration)
            + Besoin::calculerBesoinPDI_DRC($ration)
            + $besoinProduction;
    }
}
