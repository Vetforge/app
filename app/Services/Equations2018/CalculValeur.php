<?php

declare(strict_types=1);

namespace App\Services\Equations2018;

use App\Enums\Espece;
use App\Models\Aliment;
use App\Models\Ration;
use Illuminate\Support\Str;

/**
 * Calcul dynamique des valeurs nutritionnelles INRA 2018 pour un aliment dans le contexte d'une ration.
 */
class CalculValeur
{
    private float $bpr;

    private int $maxIterations = 50;

    public function __construct(
        private readonly Ration $ration,
        private readonly Aliment $aliment,
    ) {
        $this->bpr = (float) ($aliment->bpr ?? 0);
    }

    /**
     * Digestibilité de la matière organique corrigée (dMOc) en %.
     */
    public function calculerDMOcAliment(?float $bpr = null): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $bpr ??= $this->bpr;
        $dMO = (float) ($this->aliment->d_mo ?? 0);
        $NI = Apport::calculerNI($this->ration);
        $NIref = (float) ($this->aliment->niref ?? 2.0);
        $PCO = Apport::calculerPCO($this->ration);

        $deltaDMO_NI = -2.74 * ($NI - $NIref);

        // Correction liée à la proportion de concentré (ch. 3 p. 46). Le coefficient dépend de
        // l'espèce : plein effet chez le bovin, atténué (× 0,6) chez l'ovin, nul chez le caprin.
        $facteurCO = match ($this->ration->categorie()->espece()) {
            Espece::Ovin => 0.6,
            Espece::Caprin => 0.0,
            default => 1.0,
        };
        $deltaDMO_CO = ($PCO > 0 && $facteurCO > 0.0)
            ? $facteurCO * (-6.5 / (1 + pow(0.35 / $PCO, 3)))
            : 0.0;
        $BPRref = (float) ($this->aliment->bpr ?? 0);
        $deltaDMO_BPR = 0.06 * ($bpr - $BPRref);

        return $dMO + $deltaDMO_NI + $deltaDMO_CO + $deltaDMO_BPR;
    }

    /**
     * dE (digestibilité de l'énergie) en % pour l'aliment.
     */
    public function calculerDEAliment(): float
    {
        $dMO = $this->calculerDMOcAliment();
        $famille = self::normaliserLibelle((string) ($this->aliment->libelle0 ?? ''));
        $estMais = $this->estMais();

        if ($famille === 'fourrages verts') {
            return $estMais
                ? -2.35 + 0.997 * $dMO
                : -0.068 + 0.957 * $dMO;
        }

        if ($famille === 'ensilages') {
            if ($estMais) {
                return -2.86 + 1.001 * $dMO;
            }
            $ms = (float) ($this->aliment->ms ?? 0);

            return $ms < 50
                ? -5.723 + 1.0263 * $dMO
                : -2.556 + 0.985 * $dMO;
        }

        if ($famille === 'foins' || $famille === 'pailles, fourrages lignifies') {
            return -2.556 + 0.985 * $dMO;
        }

        if ($this->estLuzerneDeshydratee()) {
            return -3 + 1.003 * $dMO;
        }

        // Aliment concentré
        $mat = (float) ($this->aliment->mat ?? 0);

        return -2.9 + $dMO + 0.0051 * $mat;
    }

    /**
     * Normalise un libellé de famille (casse et accents) pour une comparaison robuste.
     * Les CSV du référentiel stockent les familles en majuscules accentuées (« FOURRAGES VERTS »,
     * « PAILLES, FOURRAGES LIGNIFIÉS »…) : la sélection de l'équation ne doit pas être sensible à la casse.
     */
    private static function normaliserLibelle(string $valeur): string
    {
        return Str::of($valeur)->lower()->ascii()->squish()->value();
    }

    /** Détail botanique/technologique normalisé (libellés 4 puis 1). */
    private function detailAliment(): string
    {
        return self::normaliserLibelle(
            ((string) ($this->aliment->libelle4 ?? '')).' '.((string) ($this->aliment->libelle1 ?? ''))
        );
    }

    private function estMais(): bool
    {
        return preg_match('/\bmais\b/', $this->detailAliment()) === 1;
    }

    private function estLuzerneDeshydratee(): bool
    {
        $detail = $this->detailAliment();

        return str_contains($detail, 'luzerne') && str_contains($detail, 'deshydrat');
    }

    /**
     * UFL de l'aliment (kcal ENL / 1760).
     */
    public function calculerUFLAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $mat = (float) ($this->aliment->mat ?? 0);
        $mo = (float) ($this->aliment->mo ?? 0);
        $eb = (float) ($this->aliment->eb ?? 0);
        $dMO = $this->calculerDMOcAliment();
        $NI = Apport::calculerNI($this->ration);
        $PCO = Apport::calculerPCO($this->ration);
        $dE = $this->calculerDEAliment();

        $MOD = $mo * 0.01 * $dMO;
        $CH4MOD = 45.42 - 6.66 * $NI + 0.75 * $NI * $NI + 19.65 * $PCO - 35 * $PCO * $PCO - 2.69 * $NI * $PCO;
        $ECH4 = 12.5 * 0.001 * $MOD * $CH4MOD;
        $EUEB = 2.9 + 0.017 * $mat - 0.47 * $NI - 1.64 * $PCO;
        $EU = $EUEB * $eb * 0.01;
        $ED = $eb * $dE * 0.01;
        $EM = $ED - $ECH4 - $EU;
        $kls = $eb > 0 ? 0.65 + 0.247 * (($EM / $eb) - 0.63) : 0.0;
        $ENL = $EM * $kls;

        return $ENL / 1760;
    }

    /**
     * UFV de l'aliment (unité fourragère viande) en UFV/kg MS.
     *
     * v1 : valeur tabulée INRA lue depuis la colonne `ufv` (comme INRAtion®). Le calcul dynamique
     * complet de l'UFV (efficience d'engraissement kmf) pourra être ajouté ultérieurement.
     */
    public function calculerUFVAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        return (float) ($this->aliment->ufv ?? 0);
    }

    /**
     * PDI de l'aliment en g/kg MS.
     */
    public function calculerPDIAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $PDIA = $this->calculerPDIAAliment();
        $MAmic_duo = $this->calculerMAmic_duoAliment();

        return $PDIA + $MAmic_duo * 0.8 * 0.8;
    }

    /**
     * PDIA de l'aliment en g/kg MS.
     */
    public function calculerPDIAAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $mat = (float) ($this->aliment->mat ?? 0);
        $drN = (float) ($this->aliment->dr_n ?? 0);
        $DT_N = $this->calculerDT_NAliment();

        return $mat * (1 - $DT_N / 100) * ($drN / 100);
    }

    /**
     * MOD de l'aliment en g/kg MS.
     */
    public function calculerMODAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $mo = (float) ($this->aliment->mo ?? 0);
        $dMO = $this->calculerDMOcAliment();

        return $mo * 0.01 * $dMO;
    }

    /**
     * NDFND (NDF non digestible intestinal) de l'aliment en g/kg MS.
     */
    public function calculerNDFNDAliment(?float $bpr = null): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $dMO = $this->calculerDMOcAliment($bpr);

        return match ($this->aliment->type) {
            'Fourrage' => 785 - 8.63 * $dMO,
            'Conc' => 591 - 6.09 * $dMO,
            default => 0.0,
        };
    }

    /**
     * NDFD_int (NDF digestible intestinal) de l'aliment en g/kg MS.
     */
    public function calculerNDFD_intAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        return 11.4 + 0.08 * $this->calculerNDFNDAliment();
    }

    /**
     * DT_N (taux de dégradabilité de l'azote) en % pour l'aliment.
     */
    public function calculerDT_NAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $PCO = Apport::calculerPCO($this->ration);
        $NI = Apport::calculerNI($this->ration);
        $DT6_N = (float) ($this->aliment->dt6_n ?? 0);

        return match ($this->aliment->type) {
            'Fourrage' => 27.6 + 0.76 * $DT6_N - 0.000468 * $DT6_N * $DT6_N - 5.45 * $NI + 0.0312 * $NI * $DT6_N + 10.6 * $PCO * $PCO,
            'Conc' => 17.9 + 0.888 * $DT6_N - 0.000811 * $DT6_N * $DT6_N - 5.49 * $NI + 0.0285 * $NI * $DT6_N + 7.42 * $PCO * $PCO,
            default => 0.0,
        };
    }

    /**
     * AmiD_int (amidon digéré intestinalement) en g/kg MS.
     */
    public function calculerAmiD_intAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $PCO = Apport::calculerPCO($this->ration);
        $NI = Apport::calculerNI($this->ration);
        $DT6_Ami = (float) ($this->aliment->dt6_ami ?? 0);
        $amidon = (float) ($this->aliment->amidon ?? 0);

        $DT_Ami = 18.8 + 1.3 * $DT6_Ami - 0.00575 * $DT6_Ami * $DT6_Ami
            - 9.42 * $NI + 9.14 * $PCO * $PCO + 0.0897 * $NI * $DT6_Ami - 1.97 * $NI * $PCO;

        return 0.826 * $amidon * (1 - 0.01 * $DT_Ami);
    }

    /**
     * AmiD_ru (amidon digéré dans le rumen) en g/kg MS.
     */
    public function calculerAmiD_ruAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        return (float) ($this->aliment->amidon ?? 0) - $this->calculerAmiD_intAliment();
    }

    /**
     * MAmic_duo (matières azotées microbiennes au duodénum) en g/kg MS.
     */
    public function calculerMAmic_duoAliment(?float $bpr = null): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $ag = (float) ($this->aliment->ag ?? 0);
        $mo = (float) ($this->aliment->mo ?? 0);
        $PCO = Apport::calculerPCO($this->ration);
        $AmiD_int = $this->calculerAmiD_intAliment();
        $NDFD_int = $this->calculerNDFD_intAliment();
        $pf = (float) ($this->aliment->pf ?? 0);
        $dMO = $this->calculerDMOcAliment($bpr);
        $mat = (float) ($this->aliment->mat ?? 0);
        $drN = (float) ($this->aliment->dr_n ?? 0);
        $DT_N = $this->calculerDT_NAliment();

        $MOD = $mo * 0.01 * $dMO;
        $AG_duo = 9.7 + 0.75 * $ag;
        $AGD_int = 6 + 0.599 * $AG_duo;
        $PDIA = $mat * (1 - $DT_N / 100) * ($drN / 100);
        $MOF = $MOD - $PDIA - $AmiD_int - $NDFD_int - $AGD_int - $pf;

        return 41.67 + 71.9 * 0.001 * $MOF + 8.4 * $PCO;
    }

    /**
     * BPR (besoin protéique du rumen) de l'aliment en g/kg MS (itératif).
     */
    public function calculerBPRAliment(): float
    {
        if ($this->aliment->type === 'Mineral') {
            return 0.0;
        }

        $bpr = $this->bpr;
        $DT_N = $this->calculerDT_NAliment();
        $mat = (float) ($this->aliment->mat ?? 0);

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $MAmic_duo = $this->calculerMAmic_duoAliment($bpr);
            $MAF = $mat * 0.01 * $DT_N;
            $newBpr = round($MAF - $MAmic_duo - 14.2);

            if ($bpr == $newBpr) {
                break;
            }

            $bpr = $newBpr;
        }

        return $bpr;
    }
}
