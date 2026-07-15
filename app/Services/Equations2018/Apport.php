<?php

declare(strict_types=1);

namespace App\Services\Equations2018;

use App\Enums\CategorieAnimal;
use App\Enums\Espece;
use App\Models\Aliment;
use App\Models\Ration;
use App\Services\RationHelper;

/**
 * Calculs des apports nutritionnels INRA 2018.
 */
class Apport
{
    // ─── Cache de pré-calcul ───────────────────────────────────────────────────

    /** @var array<int, array{qty_ms: float, qty_mb: float, aliment: Aliment, type: string}>|null */
    private static ?array $ctxIngredients = null;

    private static ?float $ctxNI = null;

    private static ?float $ctxPCO = null;

    /**
     * Pré-calcule la liste plate d'ingrédients, NI et PCO en une seule passe.
     */
    public static function precompute(Ration $ration): void
    {
        $items = [];
        $totalMS = 0.0;
        $concentresMS = 0.0;

        foreach ($ration->rationAliments as $ra) {
            $qtyRaw = (float) ($ra->quantite ?? 0);
            if ($qtyRaw <= 0) {
                continue;
            }
            $ms = (float) ($ra->aliment->ms ?? 0);
            $qtyMS = $ra->is_mb ? $qtyRaw * $ms / 100 : $qtyRaw;
            $qtyMB = $ra->is_mb ? $qtyRaw : ($ms > 0 ? $qtyRaw * 100 / $ms : 0.0);
            $type = $ra->aliment->type ?? '';
            $items[] = ['qty_ms' => $qtyMS, 'qty_mb' => $qtyMB, 'aliment' => $ra->aliment, 'type' => $type];
            $totalMS += $qtyMS;
            if ($type === 'Conc') {
                $concentresMS += $qtyMS;
            }
        }

        foreach ($ration->melanges as $melange) {
            foreach ($melange->melangeAliments as $ma) {
                $contribution = $melange->effectiveContributionForAliment($ma);
                if ($contribution['qty_ms'] <= 0 && $contribution['qty_mb'] <= 0) {
                    continue;
                }
                $type = $ma->aliment->type ?? '';
                $items[] = ['qty_ms' => $contribution['qty_ms'], 'qty_mb' => $contribution['qty_mb'], 'aliment' => $ma->aliment, 'type' => $type];
                $totalMS += $contribution['qty_ms'];
                if ($type === 'Conc') {
                    $concentresMS += $contribution['qty_ms'];
                }
            }
        }

        $poidsVif = RationHelper::poidsVif($ration);
        self::$ctxIngredients = $items;
        self::$ctxNI = $poidsVif > 0 ? $totalMS / $poidsVif * 100 : 0.0;
        self::$ctxPCO = $totalMS > 0 ? $concentresMS / $totalMS : 0.0;
    }

    /** Efface le cache de pré-calcul. */
    public static function clearCache(): void
    {
        self::$ctxIngredients = null;
        self::$ctxNI = null;
        self::$ctxPCO = null;
    }

    /**
     * Retourne la liste plate d'ingrédients (depuis le cache ou reconstruite).
     *
     * @return array<int, array{qty_ms: float, qty_mb: float, aliment: Aliment, type: string}>
     */
    private static function getIngredients(Ration $ration): array
    {
        if (self::$ctxIngredients !== null) {
            return self::$ctxIngredients;
        }

        $items = [];
        foreach ($ration->rationAliments as $ra) {
            $qtyRaw = (float) ($ra->quantite ?? 0);
            if ($qtyRaw <= 0) {
                continue;
            }
            $ms = (float) ($ra->aliment->ms ?? 0);
            $qtyMS = $ra->is_mb ? $qtyRaw * $ms / 100 : $qtyRaw;
            $qtyMB = $ra->is_mb ? $qtyRaw : ($ms > 0 ? $qtyRaw * 100 / $ms : 0.0);
            $items[] = ['qty_ms' => $qtyMS, 'qty_mb' => $qtyMB, 'aliment' => $ra->aliment, 'type' => $ra->aliment->type ?? ''];
        }
        foreach ($ration->melanges as $melange) {
            foreach ($melange->melangeAliments as $ma) {
                $contribution = $melange->effectiveContributionForAliment($ma);
                if ($contribution['qty_ms'] <= 0 && $contribution['qty_mb'] <= 0) {
                    continue;
                }
                $items[] = ['qty_ms' => $contribution['qty_ms'], 'qty_mb' => $contribution['qty_mb'], 'aliment' => $ma->aliment, 'type' => $ma->aliment->type ?? ''];
            }
        }

        return $items;
    }

    // ─── Matière sèche ─────────────────────────────────────────────────────────

    public static function calculerApportTotalMS(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $total += $item['qty_ms'];
        }

        return $total;
    }

    public static function calculerApportMSParMB(Ration $ration): float
    {
        $totalMS = 0.0;
        $totalMB = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $totalMS += $item['qty_ms'];
            $totalMB += $item['qty_mb'];
        }

        return $totalMB > 0 ? $totalMS / $totalMB : 0.0;
    }

    public static function calculerApportFourragesMS(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] === 'Fourrage') {
                $total += $item['qty_ms'];
            }
        }

        return $total;
    }

    public static function calculerApportConcentresMS(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] === 'Conc') {
                $total += $item['qty_ms'];
            }
        }

        return $total;
    }

    // ─── NI, PCO ───────────────────────────────────────────────────────────────

    public static function calculerNI(Ration $ration): float
    {
        if (self::$ctxNI !== null) {
            return self::$ctxNI;
        }
        $MSI = self::calculerApportTotalMS($ration);
        $poidsVif = RationHelper::poidsVif($ration);

        return $poidsVif > 0 ? $MSI / $poidsVif * 100 : 0.0;
    }

    public static function calculerPCO(Ration $ration): float
    {
        if (self::$ctxPCO !== null) {
            return self::$ctxPCO;
        }
        $totalConc = self::calculerApportConcentresMS($ration);
        $totalMS = self::calculerApportTotalMS($ration);

        return $totalMS > 0 ? $totalConc / $totalMS : 0.0;
    }

    // ─── UF ────────────────────────────────────────────────────────────────────

    public static function calculerApportFourragesUE(Ration $ration): float
    {
        $champUE = RationHelper::categorie($ration->categorie_animal ?? '')->uniteEncombrement();
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] === 'Fourrage') {
                $total += $item['qty_ms'] * (float) ($item['aliment']->{$champUE} ?? 0);
            }
        }

        return $total;
    }

    public static function calculerApportFourragesUF(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] === 'Fourrage') {
                $total += $item['qty_ms'] * self::ufAliment($ration, $item['aliment']);
            }
        }

        return $total;
    }

    public static function calculerApportConcentresUF(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] === 'Conc') {
                $total += $item['qty_ms'] * self::ufAliment($ration, $item['aliment']);
            }
        }

        return $total;
    }

    public static function calculerApportTotalUF(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] === 'Fourrage' || $item['type'] === 'Conc') {
                $total += $item['qty_ms'] * self::ufAliment($ration, $item['aliment']);
            }
        }

        return $total;
    }

    /**
     * Valeur énergétique d'un aliment dans l'unité fourragère de la catégorie :
     * UFL calculée dynamiquement, ou UFV tabulée pour les catégories à l'engraissement.
     */
    private static function ufAliment(Ration $ration, Aliment $aliment): float
    {
        $cv = new CalculValeur($ration, $aliment);

        return RationHelper::categorie($ration->categorie_animal)->uniteFourragere() === 'ufv'
            ? $cv->calculerUFVAliment()
            : $cv->calculerUFLAliment();
    }

    // ─── TSg / Sg / VEC / UE ───────────────────────────────────────────────────

    public static function calculerbVEc(Ration $ration): float
    {
        $totalBVec = 0.0;
        $concentresMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] === 'Conc') {
                $totalBVec += $item['qty_ms'] * (float) ($item['aliment']->b_vec ?? 0);
                $concentresMS += $item['qty_ms'];
            }
        }

        return $concentresMS > 0 ? $totalBVec / $concentresMS : 0.0;
    }

    public static function calculerTSg(
        float $UEf,
        float $bVEc,
        float $UFLf,
        float $UFLc,
        float $PDI,
        float $UFL,
        float $RPCO,
        float $PCO
    ): float {
        $S0 = $UEf > 0 ? $bVEc / $UEf : 0.0;
        $S = $UFLf > 0 ? $UFLc / $UFLf : 0.0;
        $PI = $UFL > 0 ? 0.4 + 0.4 / (1 + exp(0.15 * ($PDI / $UFL))) : 0.0;
        $d = ($PI - $S0) != 0 ? ($S - $S0) / ($PI - $S0) : 0.0;

        $log = log(($d * exp(9.5 * ($RPCO - $PCO)) + 1) / max(PHP_FLOAT_EPSILON, $d * exp(9.5 * $RPCO) + 1));
        if ($log < 0) {
            $log = 0.0;
        }

        $denom = $d * exp(9.5 * $RPCO) + 1;
        $TSg = ($PCO > 0 && $denom > 0)
            ? ($S - $S0) * (1 + (1 / (9.5 * $PCO)) * $log) + $S0
            : 0.0;

        return min(0.8, max(0.3, $TSg));
    }

    public static function calculerRPCO(Ration $ration): float
    {
        $CI = Besoin::calculerCapaciteIngestion($ration);
        $UFLf = self::calculerApportFourragesUF($ration);
        $UFLc = self::calculerApportConcentresUF($ration);
        $UEf = self::calculerApportFourragesUE($ration);
        $besUFL = Besoin::calculerBesoinTotalUF($ration);
        $UFL_VPRpot = self::calculerApportUFL_VPR($ration, true);
        $PDI = self::calculerApportTotalPDI($ration);
        $UFL = $UFLf + $UFLc;
        $bVEc = self::calculerbVEc($ration);

        $RPCO1 = 1.0;
        $RPCO2 = 0.0;
        $compteur = 0;

        while ($RPCO1 > $RPCO2 && $compteur < 100) {
            $TSgRPCO = self::calculerTSg($UEf, $bVEc, $UFLf, $UFLc, $PDI, $UFL, $RPCO1, $RPCO1);
            $denomRPCO = ($besUFL - $UFL_VPRpot) * ($TSgRPCO - 1) - ($UEf > 0 ? ($CI / $UEf) * ($UFLc - $UFLf) : 0.0);
            $RPCO2 = ($UEf > 0 && $denomRPCO != 0)
                ? ($CI * ($UFLf / $UEf) - ($besUFL - $UFL_VPRpot)) / $denomRPCO
                : 0.0;
            $RPCO1 -= 0.01;
            $compteur++;
        }

        return $RPCO1;
    }

    public static function calculerSg(Ration $ration): float
    {
        $PCO = self::calculerPCO($ration);
        $UFLf = self::calculerApportFourragesUF($ration);
        $UFLc = self::calculerApportConcentresUF($ration);
        $fourragesMS = self::calculerApportFourragesMS($ration);
        $UEf = $fourragesMS > 0
            ? self::calculerApportFourragesUE($ration) / $fourragesMS
            : 0.0;
        $PDI = self::calculerApportTotalPDI($ration);
        $UFL = $UFLf + $UFLc;
        $bVEc = self::calculerbVEc($ration);
        $RPCO = self::calculerRPCO($ration);

        return self::calculerTSg($UEf, $bVEc, $UFLf, $UFLc, $PDI, $UFL, $RPCO, $PCO);
    }

    public static function calculerVEC(Ration $ration): float
    {
        $UEFourrage = self::calculerApportFourragesUE($ration);
        $fourragesMS = self::calculerApportFourragesMS($ration);
        $concentresMS = self::calculerApportConcentresMS($ration);
        $sg = self::calculerSg($ration);
        $VEF = $fourragesMS > 0 ? $UEFourrage / $fourragesMS : 0.0;

        return $sg * $concentresMS * $VEF;
    }

    public static function calculerApportTotalUE(Ration $ration): float
    {
        return self::calculerApportFourragesUE($ration) + self::calculerVEC($ration);
    }

    private static function calculerApportAttribut(Ration $ration, string $attribut): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $total += $item['qty_ms'] * (float) ($item['aliment']->{$attribut} ?? 0);
        }

        return $total;
    }

    private static function calculerApportAttributPourType(Ration $ration, string $attribut, string $type): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] !== $type) {
                continue;
            }

            $total += $item['qty_ms'] * (float) ($item['aliment']->{$attribut} ?? 0);
        }

        return $total;
    }

    private static function calculerApportAttributParKgMS(Ration $ration, string $attribut): float
    {
        $totalMS = self::calculerApportTotalMS($ration);

        return $totalMS > 0 ? self::calculerApportAttribut($ration, $attribut) / $totalMS : 0.0;
    }

    // ─── PDI ───────────────────────────────────────────────────────────────────

    public static function calculerApportTotalPDI(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $cv = new CalculValeur($ration, $item['aliment']);
            $total += $item['qty_ms'] * $cv->calculerPDIAliment();
        }

        return $total;
    }

    public static function calculerApportTotalPDIA(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $cv = new CalculValeur($ration, $item['aliment']);
            $total += $item['qty_ms'] * $cv->calculerPDIAAliment();
        }

        return $total;
    }

    public static function calculerApportPDIAParKgMS(Ration $ration): float
    {
        $totalMS = self::calculerApportTotalMS($ration);

        return $totalMS > 0 ? self::calculerApportTotalPDIA($ration) / $totalMS : 0.0;
    }

    public static function calculerApportPDIParKgMS(Ration $ration): float
    {
        $apportPDI = self::calculerApportTotalPDI($ration);
        $totalMS = self::calculerApportTotalMS($ration);

        return $totalMS > 0 ? $apportPDI / $totalMS : 0.0;
    }

    // ─── EffPDI ────────────────────────────────────────────────────────────────

    public static function calculerEffPDI_PDIMS(Ration $ration): float
    {
        $PDI = self::calculerApportTotalPDI($ration);
        $totalMS = self::calculerApportTotalMS($ration);
        $EffPDI = ($totalMS != 0) ? 0.67 * exp(-0.007 * (($PDI / $totalMS) - 100)) : 0.67;

        return min(1.0, $EffPDI);
    }

    public static function calculerApportLysDI(Ration $ration): float
    {
        // Numérateur et dénominateur doivent reposer sur le même PDI dynamique (et non le PDI
        // tabulé), sans quoi le diagnostic LysDI mélange deux référentiels (cf. ALI-11).
        $totalPDI = self::calculerApportTotalPDI($ration);
        $totalLysDI = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $pdiAliment = (new CalculValeur($ration, $item['aliment']))->calculerPDIAliment();
            $totalLysDI += $item['qty_ms'] * $pdiAliment * (float) ($item['aliment']->lys_di ?? 0) / 100;
        }

        return $totalPDI != 0 ? ($totalLysDI / $totalPDI) * 100 : 0.0;
    }

    public static function calculerApportMetDI(Ration $ration): float
    {
        $totalPDI = self::calculerApportTotalPDI($ration);
        $totalMetDI = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $pdiAliment = (new CalculValeur($ration, $item['aliment']))->calculerPDIAliment();
            $totalMetDI += $item['qty_ms'] * $pdiAliment * (float) ($item['aliment']->met_di ?? 0) / 100;
        }

        return $totalPDI != 0 ? ($totalMetDI / $totalPDI) * 100 : 0.0;
    }

    public static function calculerEffPDI_LysMet(Ration $ration): float
    {
        $poidsVif = RationHelper::poidsVif($ration);
        $PPha = 0.2 * pow($poidsVif, 0.6);
        $MOND = self::calculerApportMOND($ration);
        $totalMS = self::calculerApportTotalMS($ration);
        $PEF = $totalMS * 0.5 * (5.7 + 0.074 * $MOND);
        $besPDI_PUendo = 0.312 * $poidsVif;
        $semGestation = RationHelper::calculerSemainesGestation($ration);
        $poidsDuVeau = (float) ($ration->poids_veau_naissance ?? 50);
        $pourcentagePrimipare = (float) ($ration->pourcentage_primipare ?? 0);
        $apportPDI = self::calculerApportTotalPDI($ration);
        $UFL_VPR = self::calculerApportUFL_VPR($ration, false);

        $PDI_ut = RationHelper::calculerPDIUt($ration);
        $PDI_VPR = $PDI_ut + 33 * $UFL_VPR;

        $Prot_gest = ($semGestation > 0) ? 0.0448 * $poidsDuVeau * exp(0.111 * $semGestation) : 0.0;
        $Prot_gain = 56 * ($pourcentagePrimipare / 100);

        $lysDI = self::calculerApportLysDI($ration);
        $metDI = self::calculerApportMetDI($ration);
        $rep_TPLysLysMet = 0.62 + 1.75 * ($lysDI - 6.7);
        $rep_TPMet = 1.22 + 9.14 * ($metDI - 1.9) - 9.14 * 0.193 * log(1 + exp(($metDI - 1.9) / 0.193));
        $rep_TPLysMet = min($rep_TPLysLysMet, $rep_TPMet);

        $lait = RationHelper::calculerProductionLaitPotentielle($ration);
        $tp = Besoin::calculerTP($ration);
        $MP = $lait * $tp;
        $MPLysMet = $MP + ($rep_TPLysMet * $lait);

        if ($PDI_VPR > 0) {
            $EffPDI = ($apportPDI - $besPDI_PUendo != 0)
                ? ($PPha + $MPLysMet + $PEF + $Prot_gain + $PDI_VPR + $Prot_gest) / ($apportPDI - $besPDI_PUendo)
                : 0.0;
        } else {
            $EffPDI = ($apportPDI - $besPDI_PUendo - $PDI_VPR != 0)
                ? ($PPha + $MPLysMet + $PEF + $Prot_gain + $Prot_gest) / ($apportPDI - $besPDI_PUendo - $PDI_VPR)
                : 0.0;
        }

        return min(1.0, $EffPDI);
    }

    public static function calculerEffPDI(Ration $ration): float
    {
        $EffPDI_PDIMS = self::calculerEffPDI_PDIMS($ration);
        $EffPDI_LysMet = 0.0;

        $cat = RationHelper::categorie($ration->categorie_animal ?? '');
        if ($cat === CategorieAnimal::VacheLaitiere) {
            $EffPDI_LysMet = self::calculerEffPDI_LysMet($ration);
        }

        return ($EffPDI_LysMet != 0) ? min($EffPDI_PDIMS, $EffPDI_LysMet) : $EffPDI_PDIMS;
    }

    // ─── UFL_VPR (réserves corporelles mobilisables) ───────────────────────────

    public static function calculerApportUFL_VPR(Ration $ration, bool $potentiel = false): float
    {
        $semGestation = RationHelper::calculerSemainesGestation($ration);
        $semLactation = RationHelper::calculerSemainesLactation($ration);
        $NEC = (float) ($ration->nec ?? 0);
        $pourcentagePrimipare = (float) ($ration->pourcentage_primipare ?? 0);
        $PL = RationHelper::calculerProductionLaitPotentielle($ration);

        $partPrimipare = $pourcentagePrimipare / 100;
        $A = $partPrimipare * (-9.5 + 0.4 * $PL + 1.89 * $NEC)
            + (1 - $partPrimipare) * (-13.2 + 0.4 * $PL + 1.89 * $NEC);
        $B = ($NEC != 0 && $NEC != 1) ? 1 / $NEC : 0;
        $K = ($B != 0) ? $A / (52 * $B) : 0;

        return -$K + (($A / (1 - $B)) * (exp(-$B * $semLactation) - exp(-$semLactation)));
    }

    // ─── BPR, DT_N, MAT, MAmic_duo ────────────────────────────────────────────

    public static function calculerBPR(Ration $ration): float
    {
        $totalBPR = 0.0;
        $totalMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $cv = new CalculValeur($ration, $item['aliment']);
            $totalBPR += $item['qty_ms'] * $cv->calculerBPRAliment();
            $totalMS += $item['qty_ms'];
        }

        return $totalMS > 0 ? $totalBPR / $totalMS : 0.0;
    }

    public static function calculerApportMAT(Ration $ration): float
    {
        return self::calculerApportAttributParKgMS($ration, 'mat');
    }

    public static function calculerApportFourragesMAT(Ration $ration): float
    {
        return self::calculerApportAttributPourType($ration, 'mat', 'Fourrage');
    }

    public static function calculerApportCB(Ration $ration): float
    {
        return self::calculerApportAttribut($ration, 'cb');
    }

    public static function calculerApportCBParKgMS(Ration $ration): float
    {
        return self::calculerApportAttributParKgMS($ration, 'cb');
    }

    public static function calculerApportADF(Ration $ration): float
    {
        return self::calculerApportAttribut($ration, 'adf');
    }

    public static function calculerApportADFParKgMS(Ration $ration): float
    {
        return self::calculerApportAttributParKgMS($ration, 'adf');
    }

    public static function calculerApportAmidon(Ration $ration): float
    {
        return self::calculerApportAttribut($ration, 'amidon');
    }

    public static function calculerApportAmidonParKgMS(Ration $ration): float
    {
        return self::calculerApportAttributParKgMS($ration, 'amidon');
    }

    public static function calculerApportPF(Ration $ration): float
    {
        return self::calculerApportAttribut($ration, 'pf');
    }

    public static function calculerApportPFParKgMS(Ration $ration): float
    {
        return self::calculerApportAttributParKgMS($ration, 'pf');
    }

    public static function calculerApportAG(Ration $ration): float
    {
        return self::calculerApportAttribut($ration, 'ag');
    }

    public static function calculerApportAGParKgMS(Ration $ration): float
    {
        return self::calculerApportAttributParKgMS($ration, 'ag');
    }

    public static function calculerApportUFLParKgMS(Ration $ration): float
    {
        $apportUFL = self::calculerApportTotalUF($ration);
        $totalMS = self::calculerApportTotalMS($ration);

        return $totalMS > 0 ? $apportUFL / $totalMS : 0.0;
    }

    public static function calculerMAmic_duo(Ration $ration): float
    {
        $total = 0.0;
        foreach ($ration->rationAliments as $ra) {
            if (($ra->quantite ?? 0) <= 0 || ($ra->aliment->type ?? '') !== 'Fourrage') {
                continue;
            }

            $qtyMS = $ra->is_mb
                ? (float) $ra->quantite * (float) ($ra->aliment->ms ?? 0) / 100
                : (float) $ra->quantite;

            $cv = new CalculValeur($ration, $ra->aliment);
            $total += $qtyMS * $cv->calculerMAmic_duoAliment();
        }

        foreach ($ration->melanges as $melange) {
            if (($melange->quantite ?? 0) <= 0) {
                continue;
            }

            $moyenneMS = RationHelper::calculerMoyennePondereeMelange($ration, $melange, 'MS');
            $quantiteMelange = $melange->is_mb
                ? (float) $melange->quantite * $moyenneMS / 100
                : (float) $melange->quantite;
            $moyenneMAmicDuo = RationHelper::calculerMoyennePondereeMelange($ration, $melange, 'MAmic_duo');
            $total += $quantiteMelange * $moyenneMAmicDuo;
        }

        $totalMS = self::calculerApportTotalMS($ration);

        return $totalMS > 0 ? $total / $totalMS : 0.0;
    }

    // ─── NDF, AmiD_ru ──────────────────────────────────────────────────────────

    public static function calculerApportNDFf(Ration $ration): float
    {
        $total = 0.0;
        $totalMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] === 'Fourrage') {
                $total += $item['qty_ms'] * (float) ($item['aliment']->ndf ?? 0);
            }
            $totalMS += $item['qty_ms'];
        }

        return $totalMS > 0 ? $total / $totalMS : 0.0;
    }

    public static function calculerApportNDFParKgMS(Ration $ration): float
    {
        $total = 0.0;
        $totalMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $total += $item['qty_ms'] * (float) ($item['aliment']->ndf ?? 0);
            $totalMS += $item['qty_ms'];
        }

        return $totalMS > 0 ? $total / $totalMS : 0.0;
    }

    public static function calculerApportNDF(Ration $ration): float
    {
        return self::calculerApportAttribut($ration, 'ndf');
    }

    public static function calculerApportNDFNDParKgMS(Ration $ration): float
    {
        $total = 0.0;
        $totalMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $cv = new CalculValeur($ration, $item['aliment']);
            $total += $item['qty_ms'] * $cv->calculerNDFNDAliment();
            $totalMS += $item['qty_ms'];
        }

        return $totalMS > 0 ? $total / $totalMS : 0.0;
    }

    public static function calculerApportTotalNDFND(Ration $ration): float
    {
        return self::calculerApportNDFNDParKgMS($ration) * self::calculerApportTotalMS($ration);
    }

    public static function calculerApportAmiD_ru(Ration $ration): float
    {
        $total = 0.0;
        $totalMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ((float) ($item['aliment']->amidon ?? 0) > 0) {
                $cv = new CalculValeur($ration, $item['aliment']);
                $total += $item['qty_ms'] * $cv->calculerAmiD_ruAliment();
            }
            $totalMS += $item['qty_ms'];
        }

        return $totalMS > 0 ? $total / $totalMS : 0.0;
    }

    public static function calculerApportAmiD_int(Ration $ration): float
    {
        $total = 0.0;
        $totalMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ((float) ($item['aliment']->amidon ?? 0) > 0) {
                $cv = new CalculValeur($ration, $item['aliment']);
                $total += $item['qty_ms'] * $cv->calculerAmiD_intAliment();
            }
            $totalMS += $item['qty_ms'];
        }

        return $totalMS > 0 ? $total / $totalMS : 0.0;
    }

    public static function calculerApportMOD(Ration $ration): float
    {
        $total = 0.0;
        $totalMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $cv = new CalculValeur($ration, $item['aliment']);
            $total += $item['qty_ms'] * $cv->calculerMODAliment();
            $totalMS += $item['qty_ms'];
        }

        return $totalMS > 0 ? $total / $totalMS : 0.0;
    }

    public static function calculerApportConcentreMOD(Ration $ration): float
    {
        $total = 0.0;
        $totalMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            if ($item['type'] === 'Conc') {
                $cv = new CalculValeur($ration, $item['aliment']);
                $total += $item['qty_ms'] * $cv->calculerMODAliment();
            }
            $totalMS += $item['qty_ms'];
        }

        return $totalMS > 0 ? $total / $totalMS : 0.0;
    }

    public static function calculerDMOc(Ration $ration): float
    {
        $sommeDMO = 0.0;
        $totalMO = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $cv = new CalculValeur($ration, $item['aliment']);
            $mo = (float) ($item['aliment']->mo ?? 0);
            $sommeDMO += $item['qty_ms'] * $cv->calculerDMOcAliment() * $mo;
            $totalMO += $item['qty_ms'] * $mo;
        }

        return $totalMO > 0 ? $sommeDMO / $totalMO : 0.0;
    }

    public static function calculerDTAmi(Ration $ration): float
    {
        $totalAmidon = self::calculerApportAmidon($ration);
        $totalAmiD_ru = self::calculerApportAmiD_ru($ration);

        return $totalAmidon > 0 ? $totalAmiD_ru * 100 / $totalAmidon : 0.0;
    }

    public static function calculerApportNDFD(Ration $ration): float
    {
        return self::calculerApportNDFParKgMS($ration) - self::calculerApportNDFNDParKgMS($ration);
    }

    public static function calculerApportMOF(Ration $ration): float
    {
        $MOD = self::calculerApportMOD($ration);
        $AmiD_int = self::calculerApportAmiD_int($ration);
        $totalMS = self::calculerApportTotalMS($ration);
        $PDIA = $totalMS > 0 ? self::calculerApportTotalPDIA($ration) / $totalMS : 0.0;
        $NDFND = self::calculerApportNDFNDParKgMS($ration);
        $NDFD_int = (11.4 + 1.08 * $NDFND) - $NDFND;
        $AG = self::calculerApportAGParKgMS($ration);
        $AGD_int = 6 + 0.599 * (9.7 + 0.75 * $AG);
        $PF = self::calculerApportPFParKgMS($ration);

        return $MOD - $AmiD_int - $PDIA - $NDFD_int - $AGD_int - $PF;
    }

    public static function calculerProdAGVT(Ration $ration): float
    {
        $PCO = self::calculerPCO($ration);
        $MOF = self::calculerApportMOF($ration);

        return (8.35 - 1.1 * ($PCO - 0.43)) * 0.001 * $MOF;
    }

    // ─── NIref ─────────────────────────────────────────────────────────────────

    public static function calculerNIref(Ration $ration): float
    {
        $totalNIref = 0.0;
        $totalMS = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $NIref = $item['type'] === 'Fourrage'
                ? (float) ($item['aliment']->niref ?? 2.0)
                : 2.0;
            $totalNIref += $item['qty_ms'] * $NIref;
            $totalMS += $item['qty_ms'];
        }

        return $totalMS > 0 ? $totalNIref / $totalMS : 0.0;
    }

    // ─── Minéraux ──────────────────────────────────────────────────────────────

    private static function calculerApportMineral(Ration $ration, string $prop): float
    {
        return self::calculerApportAttribut($ration, $prop);
    }

    public static function calculerApportCa(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'ca');
    }

    public static function calculerApportCaabs(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $caabs = $item['aliment']->caabs;
            if ($caabs === null) {
                $caabs = ($item['aliment']->ca !== null && $item['type'] === 'Mineral')
                    ? (float) $item['aliment']->ca * 0.4
                    : 0.0;
            }
            $total += $item['qty_ms'] * (float) $caabs;
        }

        return $total;
    }

    public static function calculerApportP(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'p');
    }

    public static function calculerApportPabs(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $pabs = $item['aliment']->pabs;
            if ($pabs === null) {
                // Source minérale sans Pabs tabulée : absorbabilité moyenne P = 0,65 (p. 227).
                $pabs = ($item['aliment']->p !== null && $item['type'] === 'Mineral')
                    ? (float) $item['aliment']->p * 0.65
                    : 0.0;
            }
            $total += $item['qty_ms'] * (float) $pabs;
        }

        return $total;
    }

    public static function calculerApportMg(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'mg');
    }

    public static function calculerApportMgabs(Ration $ration): float
    {
        $apportK = self::calculerApportK($ration);
        $totalMS = self::calculerApportTotalMS($ration);
        $totalMg = self::calculerApportMg($ration);

        if ($totalMS <= 0) {
            return 0.0;
        }

        // Coefficient d'absorption du Mg (Table 5.2 p. 81) : bovins 0,254 − 0,003×[K] ;
        // ovins/caprins 0,456 − 0,004×[K]. [K] exprimé en g/kg MS.
        $concK = $apportK / $totalMS;
        $absorption = RationHelper::categorie($ration->categorie_animal ?? '')->espece() === Espece::Bovin
            ? 0.254 - 0.003 * $concK
            : 0.456 - 0.004 * $concK;

        return $totalMg * max(0.0, $absorption);
    }

    public static function calculerApportK(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'k');
    }

    public static function calculerApportNa(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'na');
    }

    public static function calculerApportCl(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'cl');
    }

    public static function calculerApportS(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 's');
    }

    public static function calculerApportCo(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'co');
    }

    public static function calculerApportSe(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'se');
    }

    public static function calculerApportZn(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'zn');
    }

    public static function calculerApportMn(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'mn');
    }

    public static function calculerApportCu(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'cu');
    }

    public static function calculerApportI(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'i');
    }

    public static function calculerApportVitA(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'vit_a');
    }

    public static function calculerApportVitD(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'vit_d');
    }

    public static function calculerApportVitE(Ration $ration): float
    {
        return self::calculerApportMineral($ration, 'vit_e');
    }

    // ─── Coût ──────────────────────────────────────────────────────────────────

    public static function calculerCoutParAnimal(Ration $ration): float
    {
        $total = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $total += $item['qty_mb'] * (float) ($item['aliment']->prix ?? 0);
        }

        return $total;
    }

    // ─── MOND ──────────────────────────────────────────────────────────────────

    public static function calculerApportMOND(Ration $ration): float
    {
        $moParKgMS = self::calculerApportAttributParKgMS($ration, 'mo');
        $modParKgMS = self::calculerApportMOD($ration);

        return $moParKgMS - $modParKgMS;
    }

    public static function calculerDT_N(Ration $ration): float
    {
        $totalDN = 0.0;
        $totalN = 0.0;
        foreach (self::getIngredients($ration) as $item) {
            $cv = new CalculValeur($ration, $item['aliment']);
            $mat = (float) ($item['aliment']->mat ?? 0);
            $totalDN += $item['qty_ms'] * $cv->calculerDT_NAliment() * $mat / 100;
            $totalN += $item['qty_ms'] * $mat;
        }

        return $totalN > 0 ? $totalDN / $totalN : 0.0;
    }
}
