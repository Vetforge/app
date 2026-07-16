<?php

declare(strict_types=1);

namespace App\Services\Equations2018;

use App\Enums\CategorieAnimal;
use App\Models\Ration;
use App\Services\RationHelper;

/**
 * Besoins nutritionnels des ovins (INRA 2018, chapitre 20 — Bocquier, Hassoun et al.).
 *
 * Trois catégories :
 *  - brebis laitière ({@see CategorieAnimal::BrebisLaitiere}) — UFL, encombrement UEM ;
 *  - brebis allaitante / suitée ({@see CategorieAnimal::BrebisAllaitante}) — UFL, UEM ;
 *  - agneau / agnelle en croissance ({@see CategorieAnimal::AgneauCroissance}) — UFV, UEM.
 *
 * Les numéros d'équation renvoient au chapitre 20. L'énergie de l'agneau (Éq. 20.53) a été validée
 * contre la Table 20.3 (ex. 20 kg + 100 g/j → 0,57 UFV ; 20 kg + 300 g/j → 0,97 UFV).
 */
class OvinBesoin
{
    private const GESTATION_DAYS = 147.0; // durée de gestation ovine (~147 j)

    private const PDIEFF_LACTATION = 0.58; // efficience PDI des brebis en lactation (ch. 20)

    // ─── Capacité d'ingestion (UEM) ──────────────────────────────────────────────

    public static function calculerCapaciteIngestion(Ration $ration): float
    {
        $cat = self::cat($ration);
        $bw = self::bw($ration);

        if ($cat === CategorieAnimal::AgneauCroissance) {
            $dmi = Apport::calculerApportTotalMS($ration);
            $ufv = $dmi > 0 ? Apport::calculerApportTotalUF($ration) / $dmi : 0.90;
            $dmiParBw = 37.65 + 1.98 * (self::adg($ration) / $bw) - 18.11 * $ufv;

            return max(0.0, $dmiParBw * $bw / 1000.0); // Éq. 20.55, kg MS/j.
        }

        $base = (0.100 - 0.010 * self::bcs($ration)) * pow($bw, 0.75); // Éq. 20.37
        $ci = $base;

        if ($cat === CategorieAnimal::BrebisLaitiere && self::enLactation($ration)) {
            $smy = self::smy($ration);

            $ci = self::estLacaune($ration)
                ? 0.900 * $smy + 0.0240 * $bw   // Éq. 20.48
                : 0.754 * $smy + 0.0255 * $bw;  // Éq. 20.49
        } elseif (self::enLactation($ration)) {
            $dim = self::dim($ration);
            $adglit = self::adgLit($ration);
            $semaines = $dim / 7.0;
            $ic46 = 3.0 * $adglit + $base; // Éq. 20.42

            if ($semaines <= 3) {
                $ci = 0.8 * $ic46; // Éq. 20.41
            } elseif ($semaines <= 6) {
                $ci = $ic46;
            } else {
                $ci = -0.027 * $dim * $adglit + 3.244 * $adglit - 0.001 * $dim + $base; // Éq. 20.46.
            }
        } elseif (self::enFinGestation($ration)) {
            // Éq. 20.39 (6 dernières semaines).
            $ci = $base + 0.0503 * self::bwLit($ration) - 0.152 * self::nlit($ration) - 0.272;
        }

        $temperature = (float) ($ration->temperature_ambiante ?? 15.0);

        return max(0.0, $ci * (1.345 - 0.0183 * $temperature)); // Éq. 20.52.
    }

    // ─── Énergie ─────────────────────────────────────────────────────────────────

    public static function besoinUFEntretien(Ration $ration): float
    {
        if (self::cat($ration) === CategorieAnimal::AgneauCroissance) {
            return 0.01802 * self::bw($ration); // maintenance UFV (Éq. 20.53)
        }

        $facteurActivite = match (RationHelper::normalizeActivite2018($ration->activite)) {
            'plaine' => 1.10,
            'vallon', 'montagne' => 1.20,
            default => 1.0,
        };

        return $facteurActivite * 0.0345 * pow(self::bw($ration), 0.75); // Éq. 20.10-20.11.
    }

    public static function besoinUFLait(Ration $ration): float
    {
        $cat = self::cat($ration);

        if ($cat === CategorieAnimal::BrebisLaitiere) {
            return self::my($ration) * self::coutUFLParLitreLait($ration); // Éq. 20.14
        }

        if ($cat === CategorieAnimal::BrebisAllaitante && self::enLactation($ration)) {
            $dim = self::dim($ration);
            $adglit = self::adgLit($ration);

            // Éq. 20.13 (allaitement jusqu'au sevrage).
            return max(0.0, -0.0274 * $adglit * $dim - 0.0007 * $dim + 3.66 * $adglit + 0.0602);
        }

        return 0.0;
    }

    public static function besoinUFGestation(Ration $ration): float
    {
        if (self::cat($ration) === CategorieAnimal::AgneauCroissance || ! self::enFinGestation($ration)) {
            return 0.0;
        }
        $wbl = self::wbl($ration);
        $bwlit = self::bwLit($ration);

        // Éq. 20.12 (6 dernières semaines de gestation).
        return max(0.0, -0.0145 * $wbl * $bwlit + 0.0896 * $bwlit - 0.0096 * $wbl + 0.0751);
    }

    public static function besoinUFGain(Ration $ration): float
    {
        if (self::cat($ration) === CategorieAnimal::AgneauCroissance) {
            return 0.00205 * self::adg($ration); // gain UFV (Éq. 20.53)
        }

        return 0.0;
    }

    /**
     * Coût énergétique (UFL) d'un litre de lait de brebis laitière — 0,686 × facteur standard (Éq. 20.14).
     * Nul pour les autres catégories ovines (le lait des brebis allaitantes se lit via le gain de la portée).
     */
    public static function coutUFLParLitreLait(Ration $ration): float
    {
        return self::cat($ration) === CategorieAnimal::BrebisLaitiere
            ? 0.686 * self::facteurLaitStandard($ration)
            : 0.0;
    }

    // ─── Protéines (PDI) ─────────────────────────────────────────────────────────

    public static function besoinPDINonProductif(Ration $ration): float
    {
        $effPdi = self::effPdi($ration);
        if ($effPdi <= 0) {
            return 0.0;
        }
        $bw = self::bw($ration);
        $dmi = Apport::calculerApportTotalMS($ration);
        $ndom = Apport::calculerApportMOND($ration);

        $efp = $dmi * (0.5 * (5.7 + 0.074 * $ndom)) / $effPdi; // Éq. 20.18
        $eup = 0.312 * $bw;                                    // Éq. 20.19
        $scurf = 0.2 * pow($bw, 0.6) / $effPdi;                // Éq. 20.20
        $wool = 0.22 * pow($bw, 0.75) / $effPdi;               // Éq. 20.21

        return $efp + $eup + $scurf + $wool;
    }

    /**
     * Coût protéique (PDI) d'un litre de lait de brebis laitière — MPC / PDIeff (Éq. 20.24).
     * Nul pour les autres catégories ovines.
     */
    public static function coutPDIParLitreLait(Ration $ration): float
    {
        if (self::cat($ration) !== CategorieAnimal::BrebisLaitiere) {
            return 0.0;
        }
        $effPdi = self::effPdi($ration);

        return $effPdi > 0 ? (float) ($ration->mpc ?? 55) / $effPdi : 0.0;
    }

    public static function besoinPDILait(Ration $ration): float
    {
        $cat = self::cat($ration);
        $effPdi = self::effPdi($ration);
        if ($effPdi <= 0) {
            return 0.0;
        }

        if ($cat === CategorieAnimal::BrebisLaitiere) {
            return self::my($ration) * self::coutPDIParLitreLait($ration); // Éq. 20.24
        }

        if ($cat === CategorieAnimal::BrebisAllaitante && self::enLactation($ration)) {
            $dim = self::dim($ration);
            $adglit = self::adgLit($ration);

            // Éq. 20.23 (allaitement).
            return max(0.0, (-3.22 * $adglit * $dim - 0.018 * $dim + 420 * $adglit + 4.64) * 0.58 / $effPdi);
        }

        return 0.0;
    }

    public static function besoinPDIGestation(Ration $ration): float
    {
        if (self::cat($ration) === CategorieAnimal::AgneauCroissance || ! self::enFinGestation($ration)) {
            return 0.0;
        }
        $effPdi = self::effPdi($ration);
        if ($effPdi <= 0) {
            return 0.0;
        }
        $wbl = self::wbl($ration);
        $bwlit = self::bwLit($ration);

        // Éq. 20.22 (6 dernières semaines).
        return max(0.0, (-1.28 * $wbl * $bwlit + 12.6 * $bwlit - 3.41 * $wbl + 17.6) * 0.58 / $effPdi);
    }

    public static function besoinPDIGain(Ration $ration): float
    {
        if (self::cat($ration) !== CategorieAnimal::AgneauCroissance) {
            return 0.0;
        }
        $effPdi = self::effPdi($ration);

        return $effPdi > 0 ? 0.141 * self::adg($ration) / $effPdi : 0.0; // Éq. 20.54
    }

    // ─── Minéraux ────────────────────────────────────────────────────────────────

    public static function calculerBesoinCaabs(Ration $ration): float
    {
        $bw = self::bw($ration);
        $dmi = Apport::calculerApportTotalMS($ration);
        $cat = self::cat($ration);

        if ($cat === CategorieAnimal::AgneauCroissance) {
            $maint = 0.67 * $dmi + 0.01 * $bw;                 // Éq. 20.27
            $gain = (6.75 * pow(self::bwAdulte($ration), 0.28) * pow($bw, -0.28)) / 1000 * self::adg($ration); // Éq. 20.30

            return $maint + $gain;
        }

        // Tarie en fin de gestation : entretien gestation + terme fœtal.
        if (! self::enLactation($ration) && self::enFinGestation($ration)) {
            return 0.015 * $bw + self::caGestation($ration); // Éq. 20.28 + 20.31
        }

        $ca = 0.67 * $dmi + 0.01 * $bw + 1.9 * self::my($ration); // Éq. 20.29 + 20.32
        if (self::enFinGestation($ration)) {
            $ca += self::caGestation($ration);
        }

        return $ca;
    }

    public static function calculerBesoinPabs(Ration $ration): float
    {
        $bw = self::bw($ration);
        $dmi = Apport::calculerApportTotalMS($ration);
        $cat = self::cat($ration);

        if ($cat === CategorieAnimal::AgneauCroissance) {
            $maint = 0.905 * $dmi + 0.002 * $bw + 0.3; // Éq. 20.33
            $gain = (3.19 * pow(self::bwAdulte($ration), 0.28) * pow($bw, -0.28) + 1.2) / 1000 * self::adg($ration); // Éq. 20.34

            return $maint + $gain;
        }

        $p = 0.905 * $dmi + 0.002 * $bw + 0.3 + 1.5 * self::my($ration); // Éq. 20.33 + 20.36
        if (self::enFinGestation($ration)) {
            $p += self::pGestation($ration); // Éq. 20.35
        }

        return $p;
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private static function cat(Ration $ration): CategorieAnimal
    {
        return RationHelper::categorie($ration->categorie_animal);
    }

    private static function bw(Ration $ration): float
    {
        return RationHelper::poidsVif($ration);
    }

    private static function bcs(Ration $ration): float
    {
        return (float) ($ration->nec ?? 3);
    }

    private static function my(Ration $ration): float
    {
        return max(0.0, (float) ($ration->lait_objectif ?? 0));
    }

    /** Lait standard (sMY, l/j) : Éq. 20.14. MFC/MPC du lait de brebis (défauts 76 / 55 g/kg, p. 323). */
    private static function smy(Ration $ration): float
    {
        return self::my($ration) * self::facteurLaitStandard($ration);
    }

    /** Facteur de standardisation du lait de brebis (Éq. 20.14), défauts MFC 76 / MPC 55 g/kg (p. 323). */
    private static function facteurLaitStandard(Ration $ration): float
    {
        $mfc = (float) ($ration->mfc ?? 76);
        $mpc = (float) ($ration->mpc ?? 55);

        return 0.0071 * $mfc + 0.0043 * $mpc + 0.2224;
    }

    private static function estLacaune(Ration $ration): bool
    {
        return str_contains(RationHelper::normalizeRace($ration->race), 'lacaune');
    }

    private static function enLactation(Ration $ration): bool
    {
        if ($ration->stade_physiologique === 'tarie' || $ration->stade_physiologique === 'gestation') {
            return false;
        }
        if (in_array($ration->stade_physiologique, ['allaitement', 'traite', 'lactation'], true)) {
            return true;
        }

        return (float) ($ration->jours_lactation ?? 0) > 0;
    }

    private static function dim(Ration $ration): float
    {
        return max(0.0, (float) ($ration->jours_lactation ?? 0));
    }

    private static function adgLit(Ration $ration): float
    {
        return max(0.0, (float) ($ration->gmq_portee ?? 0) / 1000.0);
    }

    private static function adg(Ration $ration): float
    {
        return max(0.0, (float) ($ration->gmq ?? 0));
    }

    private static function nlit(Ration $ration): int
    {
        return max(1, (int) ($ration->nombre_jeunes ?? 1));
    }

    private static function bwLit(Ration $ration): float
    {
        return max(0.0, (float) ($ration->poids_portee ?? 0));
    }

    private static function bwAdulte(Ration $ration): float
    {
        $reference = $ration->poids_adulte !== null ? (float) $ration->poids_adulte : 70.0;

        return max(self::bw($ration), $reference);
    }

    /** En fin de gestation (6 dernières semaines) : DG dans [105, 147] jours. */
    private static function enFinGestation(Ration $ration): bool
    {
        $dg = (float) ($ration->jours_gestation ?? 0);

        return $dg >= (self::GESTATION_DAYS - 42) && $dg <= self::GESTATION_DAYS + 5;
    }

    /** Semaines avant agnelage (1 à 6). */
    private static function wbl(Ration $ration): float
    {
        $dg = (float) ($ration->jours_gestation ?? 0);

        return min(6.0, max(1.0, (self::GESTATION_DAYS - $dg) / 7.0));
    }

    private static function caGestation(Ration $ration): float
    {
        $wbl = self::wbl($ration);

        // Éq. 20.31.
        return (0.0093 * $wbl * $wbl - 0.127 * $wbl + 0.571) * self::bwLit($ration) + 0.263;
    }

    private static function pGestation(Ration $ration): float
    {
        $wbl = self::wbl($ration);

        // Éq. 20.35.
        return (-0.03 * $wbl + 0.24) * self::bwLit($ration) - 0.04 * $wbl + 0.33;
    }

    /** Efficience PDI : 0,58 pour les brebis (lactation), dynamique de la ration pour l'agneau. */
    private static function effPdi(Ration $ration): float
    {
        if (self::cat($ration) === CategorieAnimal::AgneauCroissance) {
            $eff = Apport::calculerEffPDI($ration);

            return $eff > 0 ? $eff : 0.51;
        }

        return self::PDIEFF_LACTATION;
    }
}
