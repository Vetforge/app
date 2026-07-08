<?php

declare(strict_types=1);

namespace App\Services\Equations2018;

use App\Enums\CategorieAnimal;
use App\Models\Ration;
use App\Services\RationHelper;
use App\Services\Reference\BovinCroissanceReference;

/**
 * Besoins des bovins en croissance / engraissement (INRA 2018, chapitre 19 — Agabriel et al.).
 *
 * Deux catégories : croissance ({@see CategorieAnimal::BovinCroissance}, système UFL) et
 * engraissement / finition ({@see CategorieAnimal::BovinEngraissement}, système UFV). Encombrement UEB.
 *
 * Le besoin énergétique repose sur le modèle mécaniste de composition du gain (Éq. 19.1-19.8).
 * L'implémentation a été validée pas à pas contre l'exemple chiffré Box 19.1 du livre (jeune bovin
 * Charolais 450 kg, 1 400 g/j → 7,7 UFV/j ; ProtGain 0,24 kg/j, LipGain 0,26 kg/j).
 */
class BovinCroissanceBesoin
{
    private const UF_MCAL = 1.76; // 1 UF = 1 760 kcal = 1,76 Mcal

    // ─── Capacité d'ingestion (UEB) ──────────────────────────────────────────────

    public static function calculerCapaciteIngestion(Ration $ration): float
    {
        $ref = self::reference($ration);

        return $ref['i_type'] * pow(self::bw($ration), $ref['c']); // Éq. 19.18
    }

    // ─── Énergie (UFL ou UFV) ────────────────────────────────────────────────────

    public static function besoinUFEntretien(Ration $ration): float
    {
        return self::energie($ration)['maint'];
    }

    public static function besoinUFGain(Ration $ration): float
    {
        return self::energie($ration)['gain'];
    }

    public static function calculerBesoinTotalUF(Ration $ration): float
    {
        $e = self::energie($ration);

        return $e['maint'] + $e['gain'];
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

        $efp = $dmi * (0.5 * (5.7 + 0.074 * $ndom)) / $effPdi; // Éq. 19.11
        $eup = 0.312 * $bw;                                    // Éq. 19.12
        $scurf = 0.2 * pow($bw, 0.6) / $effPdi;                // Éq. 19.13

        return $efp + $eup + $scurf;
    }

    /** Protéines déposées dans le gain (Éq. 19.9) : ProtGain / PDIeff. */
    public static function besoinPDIGain(Ration $ration): float
    {
        $effPdi = self::effPdi($ration);
        if ($effPdi <= 0) {
            return 0.0;
        }

        return self::composition($ration)['prot_gain'] * 1000.0 / $effPdi;
    }

    // ─── Minéraux ────────────────────────────────────────────────────────────────

    public static function calculerBesoinCaabs(Ration $ration): float
    {
        $bw = self::bw($ration);
        $bwAdult = self::bwAdulte($ration);
        $adg = self::adg($ration);

        // Éq. 19.14.
        return 0.015 * $bw + 9.83 * pow($bwAdult, 0.22) * pow($bw, -0.22) * $adg;
    }

    public static function calculerBesoinPabs(Ration $ration): float
    {
        $bw = self::bw($ration);
        $bwAdult = self::bwAdulte($ration);
        $adg = self::adg($ration);

        // Éq. 19.15.
        return 0.025 * $bw + (1.2 + 4.66 * pow($bwAdult, 0.22) * pow($bw, -0.22)) * $adg;
    }

    // ─── Modèle de composition du gain (Éq. 19.1-19.4) ───────────────────────────

    /**
     * @return array{ebw: float, ebw_gain: float, lip_gain: float, ffm_gain: float, prot_gain: float}
     */
    private static function composition(Ration $ration): array
    {
        $ref = self::reference($ration);
        $bw = self::bw($ration);
        $adg = self::adg($ration);

        // Allométries de l'animal de référence.
        $ebwInitial = $ref['bw_initial'] * (100 - $ref['dc_initial']) / 100;
        $ebwFinal = $ref['bw_final'] * (100 - $ref['dc_final']) / 100;
        $c1 = log($ebwFinal / $ebwInitial) / log($ref['bw_final'] / $ref['bw_initial']); // Éq. 19.1
        $c0 = exp(log($ebwInitial) - $c1 * log($ref['bw_initial']));

        $lipInitial = $ref['lip_initial'] * $ebwInitial / 100;
        $lipFinal = $ref['lip_final'] * $ebwFinal / 100;
        $b1 = log($lipFinal / $lipInitial) / log($ebwFinal / $ebwInitial); // Éq. 19.2
        $b0 = exp(log($lipInitial) - $b1 * log($ebwInitial));

        $bwAdult = $ref['bw_initial'] * exp($ref['d1']); // courbe de Gompertz (jeune)

        // Application à l'animal réel au poids courant.
        $ebw = $c0 * pow($bw, $c1);
        $deBWdBW = $c1 * $ebw / $bw;
        $ebwGainActual = $deBWdBW * $adg;

        // ADG naturel de l'animal de référence au même poids (BWgain = d2·BW·ln(BWadult/BW)).
        $bwGainRef = $bw < $bwAdult ? $ref['d2'] * $bw * log($bwAdult / $bw) : 0.0;
        $ebwGainRef = $deBWdBW * $bwGainRef;

        $bodyLip = $b0 * pow($ebw, $b1);
        $lipGainRef = ($b1 * $bodyLip / $ebw) * $ebwGainRef;

        // Correction du dépôt lipidique par l'intensité du gain (Éq. 19.4, exposant 1,78).
        $lipGain = ($ebwGainRef > 0 && $lipGainRef > 0)
            ? ($lipGainRef / pow($ebwGainRef, 1.78)) * pow($ebwGainActual, 1.78)
            : 0.0;

        $ffmGain = $ebwGainActual - $lipGain;
        $ffm = $ebw - $bodyLip;
        $bodyProt = 0.1436 * pow($ffm, 1.0723); // Éq. 19.3
        $protGain = (1.0723 * $bodyProt / $ffm) * $ffmGain;

        return [
            'ebw' => $ebw,
            'ebw_gain' => $ebwGainActual,
            'lip_gain' => max(0.0, $lipGain),
            'ffm_gain' => $ffmGain,
            'prot_gain' => max(0.0, $protGain),
        ];
    }

    /**
     * Énergie d'entretien et de gain exprimée dans l'unité de la catégorie (UFV finition / UFL croissance).
     *
     * @return array{maint: float, gain: float}
     */
    private static function energie(Ration $ration): array
    {
        $ref = self::reference($ration);
        $bw = self::bw($ration);
        $comp = self::composition($ration);

        $neGain = 5.48 * $comp['prot_gain'] + 9.39 * $comp['lip_gain'];   // Mcal/j (Éq. 19.7)
        $neMaint = $ref['ne_maint'] * pow($bw, 0.75) / 1000.0;            // Mcal/j
        $ep = $neGain > 0 ? 5.48 * $comp['prot_gain'] / $neGain : 0.0;
        $kpf = 0.35 + 0.25 * pow(1 - $ep, 2);                             // Éq. 19.8

        if (self::finition($ration)) {
            // Croissance rapide : q = 0,60 → km = 0,73, kmf = 0,62 (UFV).
            $km = 0.287 * 0.60 + 0.554;
            $kf = 0.78 * 0.60 + 0.006;
            $kmf = ($km * $kf * 1.5) / ($kf + 0.5 * $km);
            $conv = $kmf;
            $kmMaint = $km;
        } else {
            // Croissance lente : q' fonction du gain, expression en UFL via kls.
            $qp = 0.62 - 0.262 * exp(-3.175 * self::adg($ration) / 1000.0);
            $kmMaint = 0.287 * $qp + 0.554;
            $conv = 0.65 + 0.247 * ($qp - 0.63); // kls
        }

        $maint = ($neMaint / $kmMaint) * $conv / self::UF_MCAL;
        $gain = $kpf > 0 ? ($neGain / $kpf) * $conv / self::UF_MCAL : 0.0;

        return ['maint' => $maint, 'gain' => $gain];
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /** @return array<string, float> */
    private static function reference(Ration $ration): array
    {
        return BovinCroissanceReference::resolve($ration->race, self::finition($ration));
    }

    private static function finition(Ration $ration): bool
    {
        return RationHelper::categorie($ration->categorie_animal) === CategorieAnimal::BovinEngraissement;
    }

    private static function bw(Ration $ration): float
    {
        return max(1.0, RationHelper::poidsVif($ration));
    }

    /** Gain moyen quotidien en kg/j. */
    private static function adg(Ration $ration): float
    {
        return max(0.0, (float) ($ration->gmq ?? 0) / 1000.0);
    }

    private static function bwAdulte(Ration $ration): float
    {
        $ref = self::reference($ration);

        return $ref['bw_initial'] * exp($ref['d1']);
    }

    private static function effPdi(Ration $ration): float
    {
        $eff = Apport::calculerEffPDI($ration);

        return $eff > 0 ? $eff : 0.60;
    }
}
