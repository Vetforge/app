<?php

declare(strict_types=1);

namespace App\Services\Equations2018;

use App\Enums\CategorieAnimal;
use App\Models\Ration;
use App\Services\RationHelper;

/**
 * Besoins nutritionnels des caprins (INRA 2018, chapitre 21 — Sauvant & Giger-Reverdin).
 *
 * Deux catégories : chèvre laitière ({@see CategorieAnimal::ChevreLaitiere}) et chevrette en
 * croissance ({@see CategorieAnimal::ChevretteCroissance}). Unités : encombrement UEL, énergie UFL.
 *
 * Les numéros d'équation renvoient au chapitre 21. Les branches non-gestantes/non-lactantes ont été
 * validées numériquement contre les valeurs publiées (Tables 21.2 et 21.7) et les exemples chiffrés
 * du livre (chèvre 70 kg / 3,5 kg lait, 2,6 kg MS → Caabs 6,7 g, Pabs 6,0 g ; DMI 2,58 kg).
 */
class CaprinBesoin
{
    private const UFL_KCAL = 1760.0; // 1 UFL = 1 760 kcal (ch. 21)

    // ─── Capacité d'ingestion (UEL) ──────────────────────────────────────────────

    public static function calculerCapaciteIngestion(Ration $ration): float
    {
        $bw = self::bw($ration);

        if (self::estChevrette($ration)) {
            return 0.080 * pow($bw, 0.75); // Éq. 21.50
        }

        // Chèvre laitière (Éq. 21.46).
        $ci = 1.3 + 0.016 * ($bw - 60) + 0.24 * self::laitStandard35($ration);

        return $ci * self::indiceCILactation($ration)
            * self::indiceCIGestation($ration)
            * self::indiceCIProteines($ration);
    }

    // ─── Énergie (UFL) ───────────────────────────────────────────────────────────

    public static function besoinUFEntretien(Ration $ration): float
    {
        return 0.0406 * pow(self::bw($ration), 0.75); // Éq. 21.6
    }

    /** Éq. 21.7a, ou 21.7b si le TP du lait est renseigné. */
    public static function besoinUFLait(Ration $ration): float
    {
        return self::my($ration) * self::coutUFLParLitreLait($ration);
    }

    /**
     * Coût énergétique (UFL) d'un litre de lait produit — Éq. 21.7a, ou 21.7b si le TP est renseigné.
     * Sert à la fois au besoin lait ({@see besoinUFLait}) et au lait permis par les UFL.
     */
    public static function coutUFLParLitreLait(Ration $ration): float
    {
        if (self::estChevrette($ration)) {
            return 0.0;
        }
        $mfc = (float) ($ration->mfc ?? 35);
        $mpc = $ration->mpc !== null ? (float) $ration->mpc : null;

        return $mpc !== null
            ? 0.389 + 0.0052 * ($mfc - 35) + 0.0029 * ($mpc - 31)
            : 0.389 + 0.0056 * ($mfc - 35);
    }

    /**
     * NE du conceptus (Éq. 21.26) convertie en UFL d'aliment
     * (ME = NE/0,14 ; expression en UFL via km = 0,65 et 1 760 kcal/UFL).
     */
    public static function besoinUFGestation(Ration $ration): float
    {
        if (self::estChevrette($ration)) {
            return 0.0;
        }
        $dg = self::dg($ration);
        if ($dg <= 0) {
            return 0.0;
        }
        $a = match (self::nombreKids($ration)) {
            1 => 1.8,
            2 => 3.0,
            default => 4.1,
        };

        return ($a * exp(0.034 * $dg) / 0.14) * 0.65 / self::UFL_KCAL;
    }

    /** Gain de la chevrette : ≈ 1,6 UFL/kg de gain (Éq. 21.39 exprimée par kg de gain, Part 21.3.2). */
    public static function besoinUFGain(Ration $ration): float
    {
        if (! self::estChevrette($ration)) {
            return 0.0;
        }

        return 1.60 * self::gainKgJour($ration);
    }

    public static function calculerBesoinTotalUF(Ration $ration): float
    {
        return self::besoinUFEntretien($ration)
            + self::besoinUFLait($ration)
            + self::besoinUFGestation($ration)
            + self::besoinUFGain($ration);
    }

    // ─── Protéines (PDI) ─────────────────────────────────────────────────────────

    /** PDI non productif (Éq. 21.11-21.14) : faecal + urinaire + desquamation. */
    public static function besoinPDINonProductif(Ration $ration): float
    {
        $effPdi = self::effPdi($ration);
        if ($effPdi <= 0) {
            return 0.0;
        }
        $bw = self::bw($ration);
        $dmi = Apport::calculerApportTotalMS($ration);
        $ndom = Apport::calculerApportMOND($ration);

        $efp = $dmi * (0.5 * (5.7 + 0.074 * $ndom)) / $effPdi; // Éq. 21.12
        $eup = 0.312 * $bw;                                    // Éq. 21.13
        $scurf = 0.2 * pow($bw, 0.6) / $effPdi;                // Éq. 21.14

        return $efp + $eup + $scurf;
    }

    /** PDI lait (Éq. 21.15) : MY × MPC / PDIeff. */
    public static function besoinPDILait(Ration $ration): float
    {
        return self::my($ration) * self::coutPDIParLitreLait($ration);
    }

    /**
     * Coût protéique (PDI) d'un litre de lait produit — Éq. 21.15 : MPC / PDIeff.
     * Sert à la fois au besoin lait ({@see besoinPDILait}) et au lait permis par les PDI.
     */
    public static function coutPDIParLitreLait(Ration $ration): float
    {
        if (self::estChevrette($ration)) {
            return 0.0;
        }
        $effPdi = self::effPdi($ration);

        return $effPdi > 0 ? (float) ($ration->mpc ?? 31) / $effPdi : 0.0;
    }

    /** PDI gestation (Éq. 21.30) : a × exp(0,03383 × DG) / PDIeff. */
    public static function besoinPDIGestation(Ration $ration): float
    {
        if (self::estChevrette($ration)) {
            return 0.0;
        }
        $dg = self::dg($ration);
        $effPdi = self::effPdi($ration);
        if ($dg <= 0 || $effPdi <= 0) {
            return 0.0;
        }
        $a = match (self::nombreKids($ration)) {
            1 => 0.21,
            2 => 0.35,
            default => 0.48,
        };

        return $a * exp(0.03383 * $dg) / $effPdi;
    }

    /** PDI de croissance de la chevrette (≈ 155 g de protéines nettes/kg de gain, Table 21.7). */
    public static function besoinPDIGain(Ration $ration): float
    {
        if (! self::estChevrette($ration)) {
            return 0.0;
        }
        $effPdi = self::effPdi($ration);

        return $effPdi > 0 ? 155.0 * self::gainKgJour($ration) / $effPdi : 0.0;
    }

    public static function calculerBesoinTotalPDI(Ration $ration): float
    {
        return self::besoinPDINonProductif($ration)
            + self::besoinPDILait($ration)
            + self::besoinPDIGestation($ration)
            + self::besoinPDIGain($ration);
    }

    // ─── Minéraux ────────────────────────────────────────────────────────────────

    public static function calculerBesoinCaabs(Ration $ration): float
    {
        $my = self::my($ration);

        // Chèvre tarie gestante : simplification par l'énergie (Éq. 21.31).
        if ($my <= 0 && self::dg($ration) > 0 && ! self::estChevrette($ration)) {
            return 2.52 * self::calculerBesoinTotalUF($ration)
                + (self::nombreKids($ration) >= 2 ? 1.0 : 0.0);
        }

        // Éq. 21.20.
        return 0.67 * Apport::calculerApportTotalMS($ration) + 0.01 * self::bw($ration) + 1.21 * $my;
    }

    public static function calculerBesoinPabs(Ration $ration): float
    {
        $my = self::my($ration);

        if ($my <= 0 && self::dg($ration) > 0 && ! self::estChevrette($ration)) {
            return 2.22 * self::calculerBesoinTotalUF($ration)
                + (self::nombreKids($ration) >= 2 ? 0.4 : 0.0);
        }

        // Éq. 21.21.
        return 0.905 * Apport::calculerApportTotalMS($ration) + 0.3 + 0.002 * self::bw($ration) + 0.92 * $my;
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private static function estChevrette(Ration $ration): bool
    {
        return RationHelper::categorie($ration->categorie_animal) === CategorieAnimal::ChevretteCroissance;
    }

    private static function bw(Ration $ration): float
    {
        return RationHelper::poidsVif($ration);
    }

    private static function my(Ration $ration): float
    {
        return max(0.0, (float) ($ration->lait_objectif ?? 0));
    }

    private static function dg(Ration $ration): float
    {
        return max(0.0, (float) ($ration->jours_gestation ?? 0));
    }

    /** Lait standard corrigé à 35 g/kg de MFC (= UFLreq_MY / 0,389, Part 21.1.2). */
    private static function laitStandard35(Ration $ration): float
    {
        return self::my($ration) > 0 ? self::besoinUFLait($ration) / 0.389 : 0.0;
    }

    /** Indice CI lié au stade de lactation (Éq. 21.47), 5 premières semaines. */
    private static function indiceCILactation(Ration $ration): float
    {
        $dim = max(0.0, (float) ($ration->jours_lactation ?? 0));
        if ($dim <= 0) {
            return 1.0;
        }
        $wl = $dim / 7.0;

        return $wl >= 5.0 ? 1.0 : 0.5 + 0.5 * (1 - exp(-0.6 * $wl));
    }

    /** Indice CI lié au stade de gestation (Éq. 21.48). */
    private static function indiceCIGestation(Ration $ration): float
    {
        $dg = self::dg($ration);
        if ($dg <= 0) {
            return 1.0;
        }
        [$a, $b] = match (self::nombreKids($ration)) {
            1 => [0.99, 0.01],
            2 => [0.92, 0.08],
            default => [0.85, 0.15],
        };

        return $a + $b * (1 - exp(-0.033 * (150 - $dg)));
    }

    /** Indice CI lié à la teneur en protéines de la ration (Éq. 21.49). */
    private static function indiceCIProteines(Ration $ration): float
    {
        $cp = Apport::calculerApportMAT($ration); // MAT par kg MS (g/kg)

        return 1.06 - 0.046 * exp(-0.025 * ($cp - 150));
    }

    private static function nombreKids(Ration $ration): int
    {
        return max(1, (int) ($ration->nombre_jeunes ?? 1));
    }

    private static function gainKgJour(Ration $ration): float
    {
        return max(0.0, (float) ($ration->gmq ?? 0) / 1000.0);
    }

    /**
     * Efficience d'utilisation des PDI : 0,50 en croissance (Table 21.7), sinon efficience de la
     * ration (potentiel 0,67 pour la chèvre laitière).
     */
    private static function effPdi(Ration $ration): float
    {
        if (self::estChevrette($ration)) {
            return 0.50;
        }
        $eff = Apport::calculerEffPDI($ration);

        return $eff > 0 ? $eff : 0.67;
    }
}
