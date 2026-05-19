<?php

declare(strict_types=1);

namespace App\Services\Equations2007;

use App\Models\Ration;

/**
 * Calculs des impacts (lait permis, eau, coût) INRA 2007.
 */
class Impact
{
    public static function calculerLaitPermisParUF(Ration $ration): float
    {
        $totalUFL = Apport::calculerApportTotalUF($ration);
        $besoinEntretien = Besoin::calculerBesoinUF_NP($ration);
        $besoinCroissance = Besoin::calculerBesoinUF_gain($ration);
        $besoinGestation = Besoin::calculerBesoinUF_gest($ration);
        $denominateur = 0.44 + (0.0055 * (Besoin::calculerTB($ration) - 40)) + (0.0033 * (Besoin::calculerTP($ration) - 31));

        return $denominateur !== 0.0 && (float) ($ration->lait_objectif ?? 0) !== 0.0
            ? ($totalUFL - ($besoinEntretien + $besoinCroissance + $besoinGestation)) / $denominateur
            : 0.0;
    }

    public static function calculerLaitPermisParUFL(Ration $ration): float
    {
        return self::calculerLaitPermisParUF($ration);
    }

    public static function calculerLaitPermisParPDIE(Ration $ration): float
    {
        $totalPDIE = Apport::calculerApportTotalPDIE($ration);
        $besoinEntretien = Besoin::calculerBesoinPDI_NP($ration);
        $besoinCroissance = Besoin::calculerBesoinPDI_gain($ration);
        $besoinGestation = Besoin::calculerBesoinPDI_gest($ration);
        $tp = Besoin::calculerTP($ration);

        return $tp !== 0.0 && (float) ($ration->lait_objectif ?? 0) !== 0.0
            ? ($totalPDIE - ($besoinEntretien + $besoinCroissance + $besoinGestation)) / ($tp / 0.64)
            : 0.0;
    }

    public static function calculerLaitPermisParPDIN(Ration $ration): float
    {
        $totalPDIN = Apport::calculerApportTotalPDIN($ration);
        $besoinEntretien = Besoin::calculerBesoinPDI_NP($ration);
        $besoinCroissance = Besoin::calculerBesoinPDI_gain($ration);
        $besoinGestation = Besoin::calculerBesoinPDI_gest($ration);
        $tp = Besoin::calculerTP($ration);

        return $tp !== 0.0 && (float) ($ration->lait_objectif ?? 0) !== 0.0
            ? ($totalPDIN - ($besoinEntretien + $besoinCroissance + $besoinGestation)) / ($tp / 0.64)
            : 0.0;
    }

    public static function calculerMinimumPDIN(Ration $ration): float
    {
        $apportPDIE = Apport::calculerApportTotalPDIE($ration);
        $apportUFL = Apport::calculerApportTotalUF($ration);
        $laitObjectif = (float) ($ration->lait_objectif ?? 0);

        return match (true) {
            $laitObjectif <= 25 => $apportPDIE - (8 * $apportUFL),
            $laitObjectif <= 35 => $apportPDIE - (4 * $apportUFL),
            default => $apportPDIE,
        };
    }

    public static function calculerMaximumPDIN(Ration $ration): float
    {
        $apportPDIE = Apport::calculerApportTotalPDIE($ration);
        $besoinTotalPDI = Besoin::calculerBesoinTotalPDI($ration);

        return (600 - ($apportPDIE - $besoinTotalPDI) + (2.4 * $apportPDIE)) / 2.4;
    }

    public static function calculerRapportRmic(Ration $ration): float
    {
        $apportUFL = Apport::calculerApportTotalUF($ration);

        return $apportUFL !== 0.0
            ? (Apport::calculerApportTotalPDIN($ration) - Apport::calculerApportTotalPDIE($ration)) / $apportUFL
            : 0.0;
    }

    public static function calculerEauBue(Ration $ration): float
    {
        $objectifLait = (float) ($ration->lait_objectif ?? 0);
        $apportTotalMS = Apport::calculerApportTotalMS($ration);
        $pourcentageMSParMB = Apport::calculerApportMSParMB($ration) * 100;
        $PFO = $apportTotalMS !== 0.0 ? Apport::calculerApportFourragesMS($ration) / $apportTotalMS : 0.0;
        $MATf = $apportTotalMS !== 0.0 ? Apport::calculerApportFourragesMAT($ration) / $apportTotalMS : 0.0;
        $eauBueTH = self::calculerEauBueTH($ration);

        return $pourcentageMSParMB !== 0.0
            ? $eauBueTH - 4.34 + (0.88 * $objectifLait) + ($apportTotalMS * (4.6 - 100 / $pourcentageMSParMB)) + (0.0012 * pow($MATf * $PFO, 2))
            : 0.0;
    }

    public static function calculerEauBueTH(Ration $ration): float
    {
        $temperature = (float) ($ration->temperature_ambiante ?? 15);
        $poidsVif = (float) ($ration->poids_vif ?? 650);

        if ($temperature <= 15) {
            return 0.0;
        }

        return ((85.2 * exp(($temperature - 24.9) / 8) + 2.25 * exp(($temperature - 12) / 6.8) - 28.2) * 0.14 * pow($poidsVif, 0.57) * 86.4) / 2500;
    }

    public static function calculerCoutParAnimal(Ration $ration): float
    {
        return Apport::calculerCoutParAnimal($ration);
    }

    public static function calculerCoutPar1000Lait(Ration $ration): float
    {
        $laitObjectif = (float) ($ration->lait_objectif ?? 0);

        return $laitObjectif !== 0.0 ? self::calculerCoutParAnimal($ration) * 1000 / $laitObjectif : 0.0;
    }
}
