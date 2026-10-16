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
        $ci = 1.3 + 0.016 * ($bw - 60) + 0.24 * self::laitStandard35Potentiel($ration);

        return max(0.0, $ci * self::indiceCILactation($ration)
            * self::indiceCIGestation($ration)
            * self::indiceCIProteines($ration));
    }

    // ─── Énergie (UFL) ───────────────────────────────────────────────────────────

    public static function besoinUFEntretien(Ration $ration): float
    {
        $facteurActivite = match (RationHelper::normalizeActivite2018($ration->activite)) {
            'plaine' => 1.20,
            'vallon' => 1.40,
            'montagne' => 1.60,
            default => 1.0,
        };

        return $facteurActivite * 0.0406 * pow(self::bw($ration), 0.75); // Éq. 21.6/21.8.
    }

    /** Éq. 21.7a, ou 21.7b si le TP du lait est renseigné. */
    public static function besoinUFLait(Ration $ration): float
    {
        return self::my($ration) * self::coutUFLParLitreLait($ration);
    }

    /**
     * Coût énergétique (UFL) d'un kilogramme de lait produit — Éq. 21.7a, ou 21.7b si le TP est renseigné.
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

    /** Gain de référence (Éq. 21.39a/b) ramené au GMQ demandé via la courbe 21.33. */
    public static function besoinUFGain(Ration $ration): float
    {
        if (! self::estChevrette($ration)) {
            return self::croissanceReproductrice($ration)['energie'];
        }

        $age = self::ageJours($ration);
        $neGain = self::estSaanen($ration)
            ? 0.530 * exp(-0.00198 * $age)
            : 0.506 * exp(-0.00239 * $age);

        return $neGain / 1.76 * self::rapportGainReference($ration);
    }

    /** Chapitre 21.1.4 : variation du poids corporel vide, UFL/kg. */
    public static function besoinUFReserves(Ration $ration): float
    {
        return self::estChevrette($ration) ? 0.0 : 2.22 * (float) ($ration->ecart_variation_reserve ?? 0);
    }

    public static function calculerBesoinTotalUF(Ration $ration): float
    {
        return self::besoinUFEntretien($ration)
            + self::besoinUFLait($ration)
            + self::besoinUFGestation($ration)
            + self::besoinUFGain($ration); // UFLreq (21.3) exclut les réserves (21.4).
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
     * Coût protéique (PDI) d'un kilogramme de lait produit — Éq. 21.15 : MPC / PDIeff.
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

    /** PDI de croissance de la chevrette selon l'âge et la race (Éq. 21.41a/b). */
    public static function besoinPDIGain(Ration $ration): float
    {
        if (! self::estChevrette($ration)) {
            return self::croissanceReproductrice($ration)['pdi'] * 0.50 / self::effPdi($ration);
        }
        $effPdi = self::effPdi($ration);

        if ($effPdi <= 0) {
            return 0.0;
        }
        $age = self::ageJours($ration);
        $proteineNette = self::estSaanen($ration)
            ? 31.0 * exp(-0.00235 * $age)
            : 30.0 * exp(-0.00281 * $age);

        return $proteineNette / $effPdi * self::rapportGainReference($ration);
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

        if (self::estChevrette($ration)) {
            $bw = self::bw($ration);
            $dmi = Apport::calculerApportTotalMS($ration);
            $gain = 6.75 * pow(self::bwAdulte($ration), 0.28) * pow($bw, -0.28) * self::gainKgJour($ration);

            return 0.67 * $dmi + 0.01 * $bw + $gain
                + (self::dg($ration) >= 100 ? (self::nombreKids($ration) >= 2 ? 2.0 : 1.0) : 0.0); // Éq. 21.42 + Table 8.1.
        }

        // Deux derniers mois de gestation, y compris en chevauchement avec la lactation (Éq. 21.31).
        if (self::dg($ration) >= 90) {
            return 2.52 * self::calculerBesoinTotalUF($ration)
                + (self::nombreKids($ration) >= 2 ? 1.0 : 0.0);
        }

        // Éq. 21.20.
        $growth = self::gainReproductriceKgJour($ration);
        $adultWeight = self::estSaanen($ration) ? 94.6 : 76.6;

        return 0.67 * Apport::calculerApportTotalMS($ration) + 0.01 * self::bw($ration) + 1.21 * $my
            + 6.75 * pow($adultWeight / self::bw($ration), 0.28) * $growth;
    }

    public static function calculerBesoinPabs(Ration $ration): float
    {
        $my = self::my($ration);

        if (self::estChevrette($ration)) {
            $bw = self::bw($ration);
            $dmi = Apport::calculerApportTotalMS($ration);
            $gain = (1.2 + 3.19 * pow(self::bwAdulte($ration), 0.28) * pow($bw, -0.28)) * self::gainKgJour($ration);

            return 0.3 + 0.905 * $dmi + 0.002 * $bw + $gain
                + (self::dg($ration) >= 100 ? (self::nombreKids($ration) >= 2 ? 1.2 : 0.6) : 0.0); // Éq. 21.43 + Table 8.1.
        }

        if (self::dg($ration) >= 90) {
            return 2.22 * self::calculerBesoinTotalUF($ration)
                + (self::nombreKids($ration) >= 2 ? 0.4 : 0.0);
        }

        // Éq. 21.21.
        $growth = self::gainReproductriceKgJour($ration);
        $adultWeight = self::estSaanen($ration) ? 94.6 : 76.6;

        return 0.905 * Apport::calculerApportTotalMS($ration) + 0.3 + 0.002 * self::bw($ration) + 0.92 * $my
            + (1.2 + 3.19 * pow($adultWeight / self::bw($ration), 0.28)) * $growth;
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

    /** MY35Pot demandé par l'Éq. 21.46, distinct de l'objectif lait réel. */
    private static function laitStandard35Potentiel(Ration $ration): float
    {
        $potentiel = max(0.0, (float) ($ration->lait_potentiel ?? 0));
        if ($potentiel <= 0) {
            return self::laitStandard35($ration);
        }
        $mfc = (float) ($ration->mfc ?? 35);
        $mpc = $ration->mpc !== null ? (float) $ration->mpc : null;
        $cout = $mpc !== null
            ? 0.389 + 0.0052 * ($mfc - 35) + 0.0029 * ($mpc - 31)
            : 0.389 + 0.0056 * ($mfc - 35);

        return $potentiel * $cout / 0.389;
    }

    /** Indice CI lié au stade de lactation (Éq. 21.47), numéro de semaine 1 à 5. */
    private static function indiceCILactation(Ration $ration): float
    {
        $dim = max(0.0, (float) ($ration->jours_lactation ?? 0));
        if ($dim <= 0) {
            return 1.0;
        }
        // Éq. 21.47 emploie le numéro de semaine de lactation (1..5), pas un DIM/7 continu :
        // à DIM = 1 l'indice vaut ≈ 0,726 (semaine 1) et non ≈ 0,541 (cf. CAP-03).
        $semaine = (int) ceil($dim / 7.0);

        return $semaine > 5 ? 1.0 : 0.5 + 0.5 * (1 - exp(-0.6 * $semaine));
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
        if (Apport::calculerApportTotalMS($ration) <= 0) {
            return 1.0;
        }
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

    /** Le coût par kg de gain suit l'âge et la race, puis s'applique au gain réel demandé. */
    private static function rapportGainReference(Ration $ration): float
    {
        [$poidsAdulte, $poidsNaissance, $taux] = self::estSaanen($ration)
            ? [94.6, 3.8, 0.00213]
            : [76.6, 4.5, 0.00259];
        $gainReference = $taux * ($poidsAdulte - $poidsNaissance) * exp(-$taux * self::ageJours($ration));

        return self::gainKgJour($ration) / $gainReference;
    }

    /**
     * Moyennes publiées en 21.3.2-21.3.3 pour les premières et secondes lactations/gestations.
     * Les PDI de référence sont exprimées à EffPDI = 0,50.
     *
     * @return array{energie: float, pdi: float}
     */
    private static function croissanceReproductrice(Ration $ration): array
    {
        $age = self::ageReproductriceJours($ration);
        if ($ration->age_mois !== null && $age !== null) {
            $ne = self::estSaanen($ration) ? 0.530 * exp(-0.00198 * $age) : 0.506 * exp(-0.00239 * $age);
            $protein = self::estSaanen($ration) ? 31 * exp(-0.00235 * $age) : 30 * exp(-0.00281 * $age);

            return ['energie' => $ne / 1.76, 'pdi' => $protein / 0.5];
        }
        $parite = (int) ($ration->parite ?? 0);
        if (self::dg($ration) > 0) {
            $parite++; // La parité compte les mises bas déjà réalisées.
        }
        if (! in_array($parite, [1, 2], true)) {
            return ['energie' => 0.0, 'pdi' => 0.0];
        }
        if (self::dg($ration) > 0) {
            [$energie, $pdi] = self::estSaanen($ration)
                ? [1 => [0.17, 26.6], 2 => [0.08, 11.2]][$parite]
                : [1 => [0.14, 21.9], 2 => [0.06, 7.7]][$parite];
        } elseif ((int) ($ration->jours_lactation ?? 0) > 0) {
            [$energie, $pdi] = self::estSaanen($ration)
                ? [1 => [0.12, 19.8], 2 => [0.06, 8.6]][$parite]
                : [1 => [0.09, 15.4], 2 => [0.04, 5.7]][$parite];
        } else {
            return ['energie' => 0.0, 'pdi' => 0.0];
        }

        return ['energie' => $energie, 'pdi' => $pdi];
    }

    /** Âge saisi, ou âges moyens de la Table 21.6 pour les deux premiers cycles. */
    private static function ageReproductriceJours(Ration $ration): ?float
    {
        if ($ration->age_mois !== null) {
            return (float) $ration->age_mois * 365.25 / 12;
        }
        $parity = (int) ($ration->parite ?? 0);
        $months = self::dg($ration) > 0
            ? match ($parity) {
                0 => 11, 1 => 21, default => null
            }
        : ((int) ($ration->jours_lactation ?? 0) > 0
            ? match ($parity) {
                1 => 17, 2 => 28, default => null
            } : null);

        return $months !== null ? $months * 365.25 / 12 : null;
    }

    public static function gainReproductriceKgJour(Ration $ration): float
    {
        $age = self::ageReproductriceJours($ration);
        if ($age === null) {
            return 0.0;
        }
        [$adult, $birth, $rate] = self::estSaanen($ration) ? [94.6, 3.8, 0.00213] : [76.6, 4.5, 0.00259];

        return $rate * ($adult - $birth) * exp(-$rate * $age);
    }

    private static function ageJours(Ration $ration): float
    {
        return max(1.0, (float) ($ration->age_jours ?? 1));
    }

    private static function estSaanen(Ration $ration): bool
    {
        return str_contains(RationHelper::normalizeRace($ration->race), 'saanen');
    }

    private static function bwAdulte(Ration $ration): float
    {
        return max(self::bw($ration), (float) ($ration->poids_adulte ?? 0));
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
