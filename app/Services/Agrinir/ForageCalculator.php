<?php

declare(strict_types=1);

namespace App\Services\Agrinir;

/**
 * Calcul des valeurs alimentaires INRA 2018/2007 par analyse NIR.
 */
class ForageCalculator
{
    /**
     * @var array<string, string>
     */
    private static array $classMap2007 = [];

    /**
     * @param  array{humidite:float,proteine:float,ndf:float,adf:float,cendres:float,matiere_grasse:float,amidon?:float|null,ca?:float|null,p?:float|null,mg?:float|null}  $params
     * @return array<string, float|null>
     */
    public static function calculer2007(string $type, array $params): array
    {
        $aliment = self::executer2007($type, $params);

        return [
            'ufl2007' => self::roundValue($aliment->getUFL2007(), 3),
            'ufv2007' => self::roundValue($aliment->getUFV2007(), 3),
            'pdia2007' => self::roundValue($aliment->getPDIA2007(), 1),
            'pdie2007' => self::roundValue($aliment->getPDIE2007(), 1),
            'pdin2007' => self::roundValue($aliment->getPDIN2007(), 1),
            'dmo2007' => self::roundValue($aliment->getDMO2007(), 1),
            'dma2007' => self::roundValue($aliment->getDMA2007(), 1),
            'uem2007' => self::roundValue($aliment->getUEM2007(), 3),
            'uel2007' => self::roundValue($aliment->getUEL2007(), 3),
            'ueb2007' => self::roundValue($aliment->getUEB2007(), 3),
        ];
    }

    /**
     * @param  array{humidite:float,proteine:float,ndf:float,adf:float,cendres:float,matiere_grasse:float,amidon?:float|null,ca?:float|null,p?:float|null,mg?:float|null}  $params
     */
    private static function executer2007(string $type, array $params): AgrinirAliment
    {
        $classBasename = self::resolve2007ClassBasename($type);
        $className = "App\\Services\\Agrinir\\Equations2007\\{$classBasename}";
        $classPath = self::equations2007Directory().DIRECTORY_SEPARATOR.$classBasename.'.php';

        require_once $classPath;

        /** @var class-string $className */
        $aliment = new AgrinirAliment;

        $className::modifierValeursAlimentaires(
            $aliment,
            (float) $params['humidite'],
            (float) $params['proteine'],
            (float) $params['ndf'],
            (float) $params['adf'],
            (float) $params['cendres'],
            (float) $params['matiere_grasse'],
            self::nullableFloat($params['amidon'] ?? null),
            self::nullableFloat($params['ca'] ?? null),
            self::nullableFloat($params['p'] ?? null),
            self::nullableFloat($params['mg'] ?? null),
            null,
        );

        return $aliment;
    }

    private static function resolve2007ClassBasename(string $type): string
    {
        $normalizedType = strtolower($type);
        $classMap = self::classMap2007();

        if (! array_key_exists($normalizedType, $classMap)) {
            throw new \InvalidArgumentException("Type AgriNIR 2007 inconnu : {$type}");
        }

        return $classMap[$normalizedType];
    }

    /**
     * @return array<string, string>
     */
    private static function classMap2007(): array
    {
        if (self::$classMap2007 === []) {
            foreach (glob(self::equations2007Directory().DIRECTORY_SEPARATOR.'*.php') ?: [] as $path) {
                $basename = pathinfo($path, PATHINFO_FILENAME);
                self::$classMap2007[strtolower($basename)] = $basename;
            }
        }

        return self::$classMap2007;
    }

    private static function equations2007Directory(): string
    {
        return __DIR__.DIRECTORY_SEPARATOR.'Equations2007';
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private static function roundValue(mixed $value, int $decimals): ?float
    {
        return $value === null ? null : round((float) $value, $decimals);
    }

    /**
     * @param  array{humidite:float,proteine:float,ndf:float,adf:float,cendres:float,matiere_grasse:float,amidon?:float|null,ca?:float|null,p?:float|null,mg?:float|null}  $params
     * @return array<string, float|null>
     */
    public static function calculer2018(string $type, array $params): array
    {
        $MS100 = 100.0 - $params['humidite'];
        $MS = $MS100 * 10.0; // g/kg MB

        if ($MS <= 0.0) {
            throw new \InvalidArgumentException('Humidité invalide : MS nulle.');
        }

        $MATgKgMB = $params['proteine'] * 10.0;
        $MATgKgMS = $MATgKgMB / $MS * 1000.0;
        $ndfgKgMS = $params['ndf'] * 10.0 / $MS * 1000.0;
        $adfgKgMS = $params['adf'] * 10.0 / $MS * 1000.0;
        $MM = $params['cendres'] * 10.0;
        $MG = $params['matiere_grasse'] * 10.0;
        $AGgKgMS = $MG / $MS * 1000.0;
        $MOgKgMB = $MS - $MM;
        $MOgKgMS = $MOgKgMB / $MS * 1000.0;
        $MATo = $MATgKgMB / $MOgKgMB * 1000.0;

        // --- variables spécifiques au type ---
        $vars = self::typeVars($type, $MS100, $MATgKgMS, $adfgKgMS, $MATo, $params);

        $dMO = $vars['dMO'];
        $dE = $vars['dE'];
        $EBo = $vars['EBo'];
        $NIref = $vars['NIref'];
        $DT_N = $vars['DT_N'];
        $dr_N = $vars['dr_N'];
        $MSVIM = $vars['MSVIM'];
        $MSVIL = $vars['MSVIL'];
        $MSVIB = $vars['MSVIB'];
        $PANDI = $vars['PANDI'];
        $PF = $vars['PF'];
        $CBkgMS = $vars['CBkgMS'];
        $Ami_int = $vars['Ami_int'];

        // --- chaîne énergétique commune ---
        $EBkcalKgMB = $EBo * $MOgKgMB / 1000.0;
        $EBkcalKgMS = $EBkcalKgMB / $MS100 * 100.0;
        $MOD = $MOgKgMS * 0.01 * $dMO;
        $CH4MOD = 45.42 - 6.66 * $NIref + 0.75 * $NIref * $NIref;
        $ECH4 = 12.5 * 0.001 * $MOD * $CH4MOD;
        $EUEB = 2.9 + 0.017 * $MATgKgMS - 0.47 * $NIref;
        $EU = $EUEB * $EBkcalKgMS * 0.01;
        $ED = $EBkcalKgMS * $dE * 0.01;
        $EM = $ED - $ECH4 - $EU;
        $ratio = $EM / $EBkcalKgMS;
        $kls = 0.65 + 0.247 * ($ratio - 0.63);
        $km = 0.287 * $ratio + 0.554;
        $kf = 0.78 * $ratio + 0.006;
        $kmf = ($km * $kf * 1.5) / ($kf + 0.5 * $km);
        $UFL = $EM * $kls / 1760.0;
        $UFV = $EM * $kmf / 1760.0;

        // --- chaîne protéique commune ---
        $PDIA = $MATgKgMS * (1.0 - $DT_N / 100.0) * ($dr_N / 100.0);
        $NDFND = 785.0 - 8.62 * $dMO;
        $NDFD_int = 11.4 + 0.08 * $NDFND;
        $AG_duo = 9.7 + 0.75 * $AGgKgMS;
        $AGD_int = 6.0 + 0.599 * $AG_duo;
        $MOF = $MOD - $PDIA - $Ami_int - $NDFD_int - $AGD_int - $PF;
        $PDIM = (41.67 + 71.9 * 0.001 * $MOF) * 0.8 * 0.8;
        $PDI = $PDIA + $PDIM;
        $BPR = ($MATgKgMS * 0.01 * $DT_N - (41.67 + 71.9 * 0.001 * $MOF)) - 14.2;

        // --- valeurs d'encombrement ---
        $UEM = 75.0 / $MSVIM;
        $UEL = 140.0 / $MSVIL;
        $UEB = 95.0 / $MSVIB;

        $ca = $params['ca'] ?? null;
        $p = $params['p'] ?? null;
        $mg = $params['mg'] ?? null;

        return [
            'ms' => round($MS100, 1),
            'mat' => round($MATgKgMS, 1),
            'ndf' => round($ndfgKgMS, 1),
            'adf' => round($adfgKgMS, 1),
            'mo' => round($MOgKgMS, 1),
            'cb' => round($CBkgMS, 1),
            'eb' => round($EBkcalKgMS, 1),
            'em' => round($EM, 1),
            'de' => round($dE, 2),
            'dmo' => round($dMO, 1),
            'niref' => round($NIref, 3),
            'dt_n' => round($DT_N, 1),
            'dr_n' => round($dr_N, 1),
            'ufl' => round($UFL, 3),
            'ufv' => round($UFV, 3),
            'pdia' => round($PDIA, 1),
            'pdi' => round($PDI, 1),
            'bpr' => round($BPR, 1),
            'uem' => round($UEM, 3),
            'uel' => round($UEL, 3),
            'ueb' => round($UEB, 3),
            'ca' => $ca !== null ? round((float) $ca, 2) : null,
            'caabs' => $ca !== null ? round((float) $ca * $vars['caAbsFactor'], 2) : null,
            'p' => $p !== null ? round((float) $p, 2) : null,
            'pabs' => $p !== null ? round((float) $p * $vars['pAbsFactor'], 2) : null,
            'mg' => $mg !== null ? round((float) $mg, 2) : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Variables spécifiques au type
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{dMO:float,dE:float,EBo:float,NIref:float,DT_N:float,dr_N:float,MSVIM:float,MSVIL:float,MSVIB:float,PANDI:float,PF:float,CBkgMS:float,Ami_int:float,caAbsFactor:float,pAbsFactor:float}
     */
    private static function typeVars(
        string $type,
        float $MS100,
        float $MATgKgMS,
        float $adfgKgMS,
        float $MATo,
        array $params,
    ): array {
        return match ($type) {
            'herbeGE1avec' => self::herbeGE1Avec($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbeGE1sans' => self::herbeGE1Sans($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbeGE2avec' => self::herbeGE2Avec($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbeGE2sans' => self::herbeGE2Sans($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbeGEnrub1' => self::herbeGEnrub1($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbeGEnrub2' => self::herbeGEnrub2($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbeGF1' => self::herbeGF1($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbeGF2' => self::herbeGF2($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbeGFV1' => self::herbeGFV1($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbeGFV2' => self::herbeGFV2($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPE1avec' => self::herbePPE1Avec($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPE1sans' => self::herbePPE1Sans($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPE2avec' => self::herbePPE2Avec($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPE2sans' => self::herbePPE2Sans($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPEnrub1' => self::herbePPEnrub1($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPEnrub2' => self::herbePPEnrub2($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPF1' => self::herbePPF1($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPF2' => self::herbePPF2($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPFV1' => self::herbePPFV1($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'herbePPFV2' => self::herbePPFV2($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'luzerneEavec' => self::luzerneEAvec($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'luzerneEsans' => self::luzerneESans($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'luzerneEnrub' => self::luzerneEnrub($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'luzerneF' => self::luzerneF($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'luzerneFV' => self::luzerneFV($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'luzerneD' => self::luzerneD($MS100, $MATgKgMS, $adfgKgMS, $MATo),
            'maisE' => self::maisE($MS100, $MATgKgMS, $adfgKgMS, $MATo, $params),
            'maisFV' => self::maisFV($MS100, $MATgKgMS, $adfgKgMS, $MATo, $params),
            'sorghoE' => self::sorghoE($MS100, $MATgKgMS, $adfgKgMS, $MATo, $params),
            'sorghoFV' => self::sorghoFV($MS100, $MATgKgMS, $adfgKgMS, $MATo, $params),
            default => throw new \InvalidArgumentException("Type AgriNIR inconnu : {$type}"),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fonctions utilitaires
    // ─────────────────────────────────────────────────────────────────────────

    /** NIref de référence à partir de MSVIM, PV = 60 kg. */
    private static function niref(float $MSVIM): float
    {
        return ($MSVIM * pow(60.0, 0.75) / 60.0) / 10.0;
    }

    /** DT_N de référence à partir de DT6_N et NIref. */
    private static function dtN(float $DT6_N, float $NIref): float
    {
        return 27.6 + 0.76 * $DT6_N - 0.000468 * $DT6_N * $DT6_N
             - 5.45 * $NIref + 0.0312 * $NIref * $DT6_N;
    }

    /** dr_N de référence. */
    private static function drN(float $MATgKgMS, float $DT_N, float $PANDI): float
    {
        return 100.0 * (((1.0 - $DT_N / 100.0) * $MATgKgMS) - $PANDI)
             / ((1.0 - $DT_N / 100.0) * $MATgKgMS);
    }

    /** Construit le tableau de retour. */
    private static function v(
        float $dMO, float $dE, float $EBo, float $NIref,
        float $DT_N, float $dr_N,
        float $MSVIM, float $MSVIL, float $MSVIB,
        float $PANDI, float $PF, float $CBkgMS,
        float $Ami_int = 0.0,
        float $caAbsFactor = 0.4, float $pAbsFactor = 0.6,
    ): array {
        return compact(
            'dMO', 'dE', 'EBo', 'NIref',
            'DT_N', 'dr_N',
            'MSVIM', 'MSVIL', 'MSVIB',
            'PANDI', 'PF', 'CBkgMS', 'Ami_int',
            'caAbsFactor', 'pAbsFactor'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Herbe Graminées — Ensilages
    // ─────────────────────────────────────────────────────────────────────────

    private static function herbeGE1Avec(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3;
        $dMO = 123.6 - 0.169 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 + 3.0,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 - 0.8,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 - 0.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 + 1.6,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo - 71.0);
        $DT6_N = (73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 4.9) * 0.96;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 95.0, $CB, 0.0, 0.4, 0.6);
    }

    private static function herbeGE1Sans(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3;
        $dMO = 123.6 - 0.169 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 + 0.3,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 - 10.1, 47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 - 9.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 + 1.6,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo - 71.0);
        $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 4.9;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 165.0, $CB, 0.0, 0.4, 0.6);
    }

    private static function herbeGE2Avec(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3;
        $dMO = 123.6 - 0.169 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 + 3.0,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 - 0.8,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 - 0.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 + 1.6,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo - 71.0);
        $DT6_N = (73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 2.5) * 0.96;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 95.0, $CB, 0.0, 0.4, 0.6);
    }

    private static function herbeGE2Sans(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3;
        $dMO = 123.6 - 0.169 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 + 0.3,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 - 10.1, 47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 - 9.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 + 1.6,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo - 71.0);
        $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 2.5;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 165.0, $CB, 0.0, 0.4, 0.6);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Herbe Graminées — Enrubannage (3 branches MS)
    // ─────────────────────────────────────────────────────────────────────────

    private static function herbeGEnrub1(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3;
        if ($MS100 < 40.0) {
            $dMO = 123.6 - 0.169 * $ADF;
            $dE = 1.0263 * $dMO - 5.723;
            $EBo = 1.03 * (4531.0 + 1.735 * $MATo - 71.0);
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 4.9;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 - 3.7;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 + 1.6;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 + 1.9;
        } elseif ($MS100 < 70.0) {
            $dMO = 123.6 - 0.169 * $ADF;
            $dE = 0.985 * $dMO - 2.556;
            $EBo = 4531.0 + 1.735 * $MATo - 71.0;
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 4.9;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9;
        } else {
            $dMO = 104.9 - 0.127 * $ADF + 0.014 * $MAT;
            $dE = 0.985 * $dMO - 2.556;
            $EBo = 4531.0 + 1.735 * $MATo - 11.0;
            $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT + 6.2;
            $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT - 0.8 + 2.9;
            $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT - 0.9 + 5.5;
            $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT - 1.4 + 5.2;
        }
        $PF = match (true) {
            $MS100 < 30.0 => 165.0,
            $MS100 < 50.0 => 80.0,
            $MS100 < 70.0 => 25.0,
            default => 0.0,
        };
        $NIref = self::niref($MSVIM);
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, $PF, $CB, 0.0, 0.4, 0.6);
    }

    private static function herbeGEnrub2(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3;
        if ($MS100 < 40.0) {
            $dMO = 123.6 - 0.169 * $ADF;
            $dE = 1.0263 * $dMO - 5.723;
            $EBo = 1.03 * (4531.0 + 1.735 * $MATo - 71.0);
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 2.5;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8 - 3.7;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4 + 1.6;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9 + 1.9;
        } elseif ($MS100 < 70.0) {
            $dMO = 123.6 - 0.169 * $ADF;
            $dE = 0.985 * $dMO - 2.556;
            $EBo = 4531.0 + 1.735 * $MATo - 71.0;
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 2.5;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.8;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT - 1.4;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT - 1.9;
        } else {
            $dMO = 104.9 - 0.127 * $ADF + 0.014 * $MAT;
            $dE = 0.985 * $dMO - 2.556;
            $EBo = 4531.0 + 1.735 * $MATo - 11.0;
            $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT + 3.2;
            $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT - 0.8 + 2.9;
            $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT - 0.9 + 5.5;
            $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT - 1.4 + 5.2;
        }
        $PF = match (true) {
            $MS100 < 30.0 => 165.0,
            $MS100 < 50.0 => 80.0,
            $MS100 < 70.0 => 25.0,
            default => 0.0,
        };
        $NIref = self::niref($MSVIM);
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, $PF, $CB, 0.0, 0.4, 0.6);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Herbe Graminées — Foins
    // ─────────────────────────────────────────────────────────────────────────

    private static function herbeGF1(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3;
        $dMO = 104.9 - 0.127 * $ADF + 0.014 * $MAT;
        $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT - 0.8 + 2.9;
        $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT - 0.9 + 5.5;
        $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT - 1.4 + 5.2;
        $NIref = self::niref($MSVIM);
        $dE = 0.985 * $dMO - 2.556;
        $EBo = 4531.0 + 1.735 * $MATo - 11.0;
        $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT + 6.2;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.4, 0.65);
    }

    private static function herbeGF2(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3;
        $dMO = 104.9 - 0.127 * $ADF + 0.014 * $MAT;
        $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT - 0.8 + 2.9;
        $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT - 0.9 + 5.5;
        $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT - 1.4 + 5.2;
        $NIref = self::niref($MSVIM);
        $dE = 0.985 * $dMO - 2.556;
        $EBo = 4531.0 + 1.735 * $MATo - 11.0;
        $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT + 3.2;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.4, 0.65);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Herbe Graminées — Fourrages verts
    // ─────────────────────────────────────────────────────────────────────────

    private static function herbeGFV1(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3 - 2.0;
        $dMO = 94.3 - 0.094 * $ADF + 0.033 * $MAT;
        $MSVIM = -16.0 + 0.806 * $dMO + 0.115 * $MAT + 0.686 * $MS100 - 1.7;
        $MSVIL = 66.3 + 0.655 * $dMO + 0.098 * $MAT + 0.626 * $MS100;
        $MSVIB = 6.44 + 0.782 * $dMO + 0.112 * $MAT + 0.679 * $MS100;
        $NIref = self::niref($MSVIM);
        $dE = 0.957 * $dMO - 0.068;
        $EBo = 4531.0 + 1.735 * $MATo - 71.0;
        $DT6_N = 51.2 + 0.14 * $MAT - 0.00017 * $MAT * $MAT + 8.8;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.4, 0.6);
    }

    private static function herbeGFV2(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 0.98 * $ADF - 19.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3 - 2.0;
        $dMO = 94.3 - 0.094 * $ADF + 0.033 * $MAT;
        $MSVIM = -16.0 + 0.806 * $dMO + 0.115 * $MAT + 0.686 * $MS100 - 1.7;
        $MSVIL = 66.3 + 0.655 * $dMO + 0.098 * $MAT + 0.626 * $MS100;
        $MSVIB = 6.44 + 0.782 * $dMO + 0.112 * $MAT + 0.679 * $MS100;
        $NIref = self::niref($MSVIM);
        $dE = 0.957 * $dMO - 0.068;
        $EBo = 4531.0 + 1.735 * $MATo - 71.0;
        $DT6_N = 51.2 + 0.14 * $MAT - 0.00017 * $MAT * $MAT + 4.6;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.4, 0.6);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Herbe Prairie Permanente — Ensilages
    // ─────────────────────────────────────────────────────────────────────────

    private static function herbePPE1Avec(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3;
        $dMO = 116.5 - 0.148 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 3.0,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 0.8,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 0.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT + 1.6,  47.0 + 0.228 * $dMO + 0.148 * $MAT + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo + 82.0);
        $DT6_N = (73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 2.5) * 0.96;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 95.0, $CB, 0.0, 0.35, 0.6);
    }

    private static function herbePPE1Sans(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3;
        $dMO = 116.5 - 0.148 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.3,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 0.8,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 0.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT + 1.6,  47.0 + 0.228 * $dMO + 0.148 * $MAT + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo + 82.0);
        $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 2.5;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 165.0, $CB, 0.0, 0.35, 0.6);
    }

    private static function herbePPE2Avec(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3;
        $dMO = 116.5 - 0.148 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 3.0,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 0.8,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 0.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT + 1.6,  47.0 + 0.228 * $dMO + 0.148 * $MAT + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo + 82.0);
        $DT6_N = (73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100) * 0.96;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 95.0, $CB, 0.0, 0.35, 0.6);
    }

    private static function herbePPE2Sans(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3;
        $dMO = 116.5 - 0.148 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 0.3,  99.3 + 0.167 * $dMO + 0.128 * $MAT - 0.8,  47.0 + 0.228 * $dMO + 0.148 * $MAT - 0.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT + 1.6,  47.0 + 0.228 * $dMO + 0.148 * $MAT + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo + 82.0);
        $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 165.0, $CB, 0.0, 0.35, 0.6);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Herbe Prairie Permanente — Enrubannage (3 branches MS)
    // ─────────────────────────────────────────────────────────────────────────

    private static function herbePPEnrub1(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3;
        if ($MS100 < 40.0) {
            $dMO = 116.5 - 0.148 * $ADF;
            $dE = 1.0263 * $dMO - 5.723;
            $EBo = 1.03 * (4531.0 + 1.735 * $MATo + 82.0);
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 2.5;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT - 3.7;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT + 1.6;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT + 1.9;
        } elseif ($MS100 < 70.0) {
            $dMO = 116.5 - 0.148 * $ADF;
            $dE = 0.985 * $dMO - 2.556;
            $EBo = 4531.0 + 1.735 * $MATo + 82.0;
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 2.5;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT;
        } else {
            $dMO = 58.5 - 0.026 * $ADF + 0.104 * $MAT;
            $dE = 0.985 * $dMO - 2.556;
            $EBo = 4531.0 + 1.735 * $MATo + 82.0;
            $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT + 1.9;
            $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT + 2.9;
            $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT + 5.5;
            $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT + 5.2;
        }
        $PF = match (true) {
            $MS100 < 30.0 => 165.0,
            $MS100 < 50.0 => 80.0,
            $MS100 < 70.0 => 25.0,
            default => 0.0,
        };
        $NIref = self::niref($MSVIM);
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, $PF, $CB, 0.0, 0.35, 0.6);
    }

    private static function herbePPEnrub2(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3;
        if ($MS100 < 40.0) {
            $dMO = 116.5 - 0.148 * $ADF;
            $dE = 1.0263 * $dMO - 5.723;
            $EBo = 1.03 * (4531.0 + 1.735 * $MATo + 82.0);
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT - 3.7;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT + 1.6;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT + 1.9;
        } elseif ($MS100 < 70.0) {
            $dMO = 116.5 - 0.148 * $ADF;
            $dE = 0.985 * $dMO - 2.556;
            $EBo = 4531.0 + 1.735 * $MATo + 82.0;
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT;
        } else {
            $dMO = 58.5 - 0.026 * $ADF + 0.104 * $MAT;
            $dE = 0.985 * $dMO - 2.556;
            $EBo = 4531.0 + 1.735 * $MATo + 82.0;
            $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT;
            $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT + 2.9;
            $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT + 5.5;
            $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT + 5.2;
        }
        $PF = match (true) {
            $MS100 < 30.0 => 165.0,
            $MS100 < 50.0 => 80.0,
            $MS100 < 70.0 => 25.0,
            default => 0.0,
        };
        $NIref = self::niref($MSVIM);
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, $PF, $CB, 0.0, 0.35, 0.6);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Herbe Prairie Permanente — Foins
    // ─────────────────────────────────────────────────────────────────────────

    private static function herbePPF1(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3;
        $dMO = 58.5 - 0.026 * $ADF + 0.104 * $MAT;
        $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT + 2.9;
        $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT + 5.5;
        $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT + 5.2;
        $NIref = self::niref($MSVIM);
        $dE = 0.985 * $dMO - 2.556;
        $EBo = 4531.0 + 1.735 * $MATo + 82.0;
        $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT + 1.9;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.35, 0.65);
    }

    private static function herbePPF2(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3;
        $dMO = 58.5 - 0.026 * $ADF + 0.104 * $MAT;
        $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT + 2.9;
        $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT + 5.5;
        $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT + 5.2;
        $NIref = self::niref($MSVIM);
        $dE = 0.985 * $dMO - 2.556;
        $EBo = 4531.0 + 1.735 * $MATo + 82.0;
        $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.35, 0.65);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Herbe Prairie Permanente — Fourrages verts
    // ─────────────────────────────────────────────────────────────────────────

    private static function herbePPFV1(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.3 - 2.0;
        $dMO = 99.0 - 0.115 * $ADF + 0.043 * $MAT;
        $MSVIM = -16.0 + 0.806 * $dMO + 0.115 * $MAT + 0.686 * $MS100;
        $MSVIL = 66.3 + 0.655 * $dMO + 0.098 * $MAT + 0.626 * $MS100;
        $MSVIB = 6.44 + 0.782 * $dMO + 0.112 * $MAT + 0.679 * $MS100;
        $NIref = self::niref($MSVIM);
        $dE = 0.957 * $dMO - 0.068;
        $EBo = 4531.0 + 1.735 * $MATo + 82.0;
        $DT6_N = 51.2 + 0.14 * $MAT - 0.00017 * $MAT * $MAT + 4.4;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.35, 0.7);
    }

    private static function herbePPFV2(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.19 * $ADF - 88.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 2.3 - 2.0;
        $dMO = 99.0 - 0.115 * $ADF + 0.043 * $MAT;
        $MSVIM = -16.0 + 0.806 * $dMO + 0.115 * $MAT + 0.686 * $MS100;
        $MSVIL = 66.3 + 0.655 * $dMO + 0.098 * $MAT + 0.626 * $MS100;
        $MSVIB = 6.44 + 0.782 * $dMO + 0.112 * $MAT + 0.679 * $MS100;
        $NIref = self::niref($MSVIM);
        $dE = 0.957 * $dMO - 0.068;
        $EBo = 4531.0 + 1.735 * $MATo + 82.0;
        $DT6_N = 51.2 + 0.14 * $MAT - 0.00017 * $MAT * $MAT;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.35, 0.7);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Légumineuses — Luzerne Ensilage
    // ─────────────────────────────────────────────────────────────────────────

    private static function luzerneEAvec(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.572 * $ADF - 209.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9;
        $dMO = 134.2 - 0.211 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 13.4 - 3.0,  99.3 + 0.167 * $dMO + 0.128 * $MAT + 2.8 - 0.8, 47.0 + 0.228 * $dMO + 0.148 * $MAT + 2.8 - 0.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT + 13.4 - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT + 2.8 + 1.6, 47.0 + 0.228 * $dMO + 0.148 * $MAT + 2.8 + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo + 82.0);
        $DT6_N = (73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 4.2) * 0.96;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 80.0, $CB, 0.0, 0.3, 0.65);
    }

    private static function luzerneESans(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.572 * $ADF - 209.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9;
        $dMO = 134.2 - 0.211 * $ADF;
        [$MSVIM, $MSVIL, $MSVIB] = $MS100 < 25
            ? [20.1 + 0.306 * $dMO + 0.078 * $MAT + 13.4 - 3.0,  99.3 + 0.167 * $dMO + 0.128 * $MAT + 2.8 - 0.8, 47.0 + 0.228 * $dMO + 0.148 * $MAT + 2.8 - 0.9]
            : [20.1 + 0.306 * $dMO + 0.078 * $MAT + 13.4 - 3.7,  99.3 + 0.167 * $dMO + 0.128 * $MAT + 2.8 + 1.6, 47.0 + 0.228 * $dMO + 0.148 * $MAT + 2.8 + 1.9];
        $NIref = self::niref($MSVIM);
        $dE = 1.0263 * $dMO - 5.723;
        $EBo = 1.03 * (4531.0 + 1.735 * $MATo + 82.0);
        $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 4.2;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 155.0, $CB, 0.0, 0.3, 0.65);
    }

    private static function luzerneEnrub(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.572 * $ADF - 209.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9;
        if ($MS100 < 40.0) {
            $dMO = 134.2 - 0.211 * $ADF;
            $dE = 1.0263 * $dMO - 5.723;
            $EBo = 1.03 * (4531.0 + 1.735 * $MATo + 82.0);
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 4.2;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT + 13.4 - 3.7;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT + 2.8 + 1.6;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT + 2.8 + 1.9;
        } elseif ($MS100 < 70.0) {
            $dMO = 134.2 - 0.211 * $ADF;
            $dE = 1.0263 * $dMO - 5.723;
            $EBo = 4531.0 + 1.735 * $MATo + 82.0;
            $DT6_N = 73.7 + 0.088 * $MAT - 0.00011 * $MAT * $MAT - 0.25 * $MS100 + 4.2;
            $MSVIM = 20.1 + 0.306 * $dMO + 0.078 * $MAT + 13.4;
            $MSVIL = 99.3 + 0.167 * $dMO + 0.128 * $MAT + 2.8;
            $MSVIB = 47.0 + 0.228 * $dMO + 0.148 * $MAT + 2.8;
        } else {
            $dMO = 98.5 - 0.114 * $ADF;
            $dE = 0.985 * $dMO - 2.556;
            $EBo = 4531.0 + 1.735 * $MATo + 82.0;
            $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT + 5.0;
            $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT + 9.4 + 2.9;
            $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT + 2.6 + 5.5;
            $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT + 3.4 + 5.2;
        }
        $PF = match (true) {
            $MS100 < 30.0 => 155.0,
            $MS100 < 50.0 => 80.0,
            $MS100 < 70.0 => 25.0,
            default => 0.0,
        };
        $NIref = self::niref($MSVIM);
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, $PF, $CB, 0.0, 0.3, 0.65);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Légumineuses — Luzerne Foin, FV, Déshydratée
    // ─────────────────────────────────────────────────────────────────────────

    private static function luzerneF(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.572 * $ADF - 209.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9;
        $dMO = 98.5 - 0.114 * $ADF;
        $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT + 9.4 + 2.9;
        $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT + 2.6 + 5.5;
        $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT + 3.4 + 5.2;
        $NIref = self::niref($MSVIM);
        $dE = 0.985 * $dMO - 2.556;
        $EBo = 4531.0 + 1.735 * $MATo + 82.0;
        $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT + 5.0;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.3, 0.6);
    }

    private static function luzerneFV(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.572 * $ADF - 209.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9 - 2.0;
        $dMO = 114.5 - 0.152 * $ADF;
        $MSVIM = -16.0 + 0.806 * $dMO + 0.115 * $MAT - 0.686 * $MS100 + 4.2;
        $MSVIL = 66.3 + 0.655 * $dMO + 0.098 * $MAT - 0.626 * $MS100 + 1.0;
        $MSVIB = 6.44 + 0.782 * $dMO + 0.112 * $MAT - 0.679 * $MS100 + 4.1;
        $NIref = self::niref($MSVIM);
        $dE = 0.957 * $dMO - 0.068;
        $EBo = 4531.0 + 1.735 * $MATo + 82.0;
        $DT6_N = 51.2 + 0.14 * $MAT - 0.00017 * $MAT * $MAT + 6.8;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.3, 0.7);
    }

    private static function luzerneD(float $MS100, float $MAT, float $ADF, float $MATo): array
    {
        $CB = 1.572 * $ADF - 209.0;
        $PANDI = 7.9 + 0.08 * $MAT - 0.00033 * $MAT * $MAT - 1.9;
        $dMO = 65.9 - 0.0919 * ($ADF - 298.3);
        $MSVIM = 11.8 + 0.432 * $dMO + 0.100 * $MAT + 9.4 + 2.9;
        $MSVIL = 82.4 + 0.491 * $dMO + 0.114 * $MAT + 2.6 + 5.5;
        $MSVIB = 30.3 + 0.559 * $dMO + 0.132 * $MAT + 3.4 + 5.2;
        $NIref = self::niref($MSVIM);
        $dE = 1.003 * $dMO - 3.0;
        $EBo = 4618.0 + 2.051 * $MATo;
        $DT6_N = 50.8 + 0.12 * $MAT - 0.00018 * $MAT * $MAT + 5.0;
        $DT_N = self::dtN($DT6_N, $NIref);
        $dr_N = self::drN($MAT, $DT_N, $PANDI);

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, 0.0, 0.3, 0.6);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Maïs & Sorgho
    // ─────────────────────────────────────────────────────────────────────────

    private static function maisE(float $MS100, float $MAT, float $ADF, float $MATo, array $params): array
    {
        $CB = 0.87 * $ADF + 9.5;
        $ami = (float) ($params['amidon'] ?? 0);
        $MS = $MS100 * 10.0;
        $AmidonKgMS = $ami * 10.0 / $MS * 1000.0;
        $dMO = 79.4 - 0.0609 * ($CB / $MATo * 1000.0 /* CBo approx */) + 0.0687 * $MATo;
        // Recalcule correctement CBo
        $MOgKgMB = $MS - ($params['cendres'] ?? 0) * 10.0;
        $CBkgMB = $CB * $MS / 1000.0;
        $CBo = $CBkgMB / $MOgKgMB * 1000.0;
        $dMO = 79.4 - 0.0609 * $CBo + 0.0687 * $MATo;
        $MSVIM = -1701.0 + 48.92 * $dMO - 0.34 * $dMO * $dMO;
        $MSVIL = 2.39 * $dMO - 76.4 + 1.44 * $MS100;
        $MSVIB = 1.34 * $dMO - 45.49 + 1.15 * $MS100;
        $NIref = 1.44;
        $dE = 1.001 * $dMO - 2.86;
        $AmidonMBo = $ami * 10.0 / $MOgKgMB * 1000.0;
        $EBo = 4722.0 - 0.458 * $AmidonMBo + 1.42 * $MATo;
        $DT6_N = 72.0;
        $DT_N = 75.0;
        $dr_N = 62.0;
        // Amidon intestinal
        $DT6_Ami = 109.72 - 0.9707 * $MS100 + 0.01799 * $AmidonKgMS;
        $DT_Ami = 18.8 + 1.30 * $DT6_Ami - 0.00575 * $DT6_Ami * $DT6_Ami
                  - 9.42 * $NIref + 0.0897 * $NIref * $DT6_Ami;
        $Ami_int = 0.826 * $AmidonKgMS * (1.0 - 0.01 * $DT_Ami);
        $PF = match (true) {
            $MS100 <= 25.0 => 125.0,
            $MS100 <= 30.0 => 100.0,
            $MS100 <= 35.0 => 80.0,
            default => 60.0,
        };
        $PANDI = 0.0; // not used for fixed DT_N path

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, $PF, $CB, $Ami_int, 0.4, 0.7);
    }

    private static function maisFV(float $MS100, float $MAT, float $ADF, float $MATo, array $params): array
    {
        $CB = 0.87 * $ADF + 9.5;
        $ami = (float) ($params['amidon'] ?? 0);
        $MS = $MS100 * 10.0;
        $AmidonKgMS = $ami * 10.0 / $MS * 1000.0;
        $MOgKgMB = $MS - ($params['cendres'] ?? 0) * 10.0;
        $CBkgMB = $CB * $MS / 1000.0;
        $CBo = $CBkgMB / $MOgKgMB * 1000.0;
        $dMO = 79.4 - 0.0609 * $CBo + 0.0687 * $MATo;
        $MSVIM = -1701.0 + 48.92 * $dMO - 0.34 * $dMO * $dMO;
        $MSVIL = 2.39 * $dMO - 76.4 + 1.44 * $MS100;
        $MSVIB = 1.34 * $dMO - 45.49 + 1.15 * $MS100;
        $NIref = 1.44;
        $dE = 0.997 * $dMO - 2.35;
        $EBo = 4487.0 + 2.019 * $MATo;
        $DT6_N = 73.0;
        $DT_N = 76.0;
        $dr_N = 69.0;
        $DT6_Ami = 109.72 - 0.9707 * $MS100 + 0.01799 * $AmidonKgMS;
        $DT_Ami = 18.8 + 1.30 * $DT6_Ami - 0.00575 * $DT6_Ami * $DT6_Ami
                  - 9.42 * $NIref + 0.0897 * $NIref * $DT6_Ami;
        $Ami_int = 0.826 * $AmidonKgMS * (1.0 - 0.01 * $DT_Ami);
        $PANDI = 0.0;

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, $Ami_int, 0.4, 0.7);
    }

    private static function sorghoE(float $MS100, float $MAT, float $ADF, float $MATo, array $params): array
    {
        $CB = 0.87 * $ADF + 9.5;
        $ami = (float) ($params['amidon'] ?? 0);
        $MS = $MS100 * 10.0;
        $AmidonKgMS = $ami * 10.0 / $MS * 1000.0;
        $MOgKgMB = $MS - ($params['cendres'] ?? 0) * 10.0;
        $CBkgMB = $CB * $MS / 1000.0;
        $CBo = $CBkgMB / $MOgKgMB * 1000.0;
        $dMO = 79.4 - 0.0609 * $CBo + 0.0687 * $MATo;
        $MSVIM = -1701.0 + 48.92 * $dMO - 0.34 * $dMO * $dMO;
        $MSVIL = 2.39 * $dMO - 76.4 + 1.44 * $MS100;
        $MSVIB = 1.34 * $dMO - 45.49 + 1.15 * $MS100;
        $NIref = self::niref($MSVIM);
        $dE = 1.001 * $dMO - 2.86;
        $AmidonMBo = $ami * 10.0 / $MOgKgMB * 1000.0;
        $EBo = 4722.0 - 0.458 * $AmidonMBo + 1.42 * $MATo;
        $DT6_N = 72.0;
        $DT_N = 75.0;
        $dr_N = 69.0;
        $DT6_Ami = 109.72 - 0.9707 * $MS100 + 0.01799 * $AmidonKgMS;
        $DT_Ami = 18.8 + 1.30 * $DT6_Ami - 0.00575 * $DT6_Ami * $DT6_Ami
                  - 9.42 * $NIref + 0.0897 * $NIref * $DT6_Ami;
        $Ami_int = 0.826 * $AmidonKgMS * (1.0 - 0.01 * $DT_Ami);
        $PF = match (true) {
            $MS100 <= 25.0 => 125.0,
            $MS100 <= 30.0 => 100.0,
            $MS100 <= 35.0 => 80.0,
            default => 60.0,
        };
        $PANDI = 0.0;

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, $PF, $CB, $Ami_int, 0.4, 0.66);
    }

    private static function sorghoFV(float $MS100, float $MAT, float $ADF, float $MATo, array $params): array
    {
        $CB = 0.87 * $ADF + 9.5;
        $ami = (float) ($params['amidon'] ?? 0);
        $MS = $MS100 * 10.0;
        $AmidonKgMS = $ami * 10.0 / $MS * 1000.0;
        $MOgKgMB = $MS - ($params['cendres'] ?? 0) * 10.0;
        $CBkgMB = $CB * $MS / 1000.0;
        $CBo = $CBkgMB / $MOgKgMB * 1000.0;
        $dMO = 79.4 - 0.0609 * $CBo + 0.0687 * $MATo;
        $MSVIM = -1701.0 + 48.92 * $dMO - 0.34 * $dMO * $dMO;
        $MSVIL = 2.39 * $dMO - 76.4 + 1.44 * $MS100;
        $MSVIB = 1.34 * $dMO - 45.49 + 1.15 * $MS100;
        $NIref = self::niref($MSVIM);
        $dE = 0.997 * $dMO - 2.35;
        $EBo = 4478.0 + 1.265 * $MATo;
        $DT6_N = 73.0;
        $DT_N = 74.0;
        $dr_N = 71.0;
        $DT6_Ami = 109.72 - 0.9707 * $MS100 + 0.01799 * $AmidonKgMS;
        $DT_Ami = 18.8 + 1.30 * $DT6_Ami - 0.00575 * $DT6_Ami * $DT6_Ami
                  - 9.42 * $NIref + 0.0897 * $NIref * $DT6_Ami;
        $Ami_int = 0.826 * $AmidonKgMS * (1.0 - 0.01 * $DT_Ami);
        $PANDI = 0.0;

        return self::v($dMO, $dE, $EBo, $NIref, $DT_N, $dr_N, $MSVIM, $MSVIL, $MSVIB, $PANDI, 0.0, $CB, $Ami_int, 0.4, 0.66);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Aide pour la liste des types côté interface
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourne la liste groupée des sous-types pour chaque famille végétale.
     *
     * @return list<array{label:string, options:list<array{value:string,label:string}>}>
     */
    public static function typesForFamille(string $famille): array
    {
        return match ($famille) {
            'herbeG' => [
                ['label' => 'Ensilage (Graminées)', 'options' => [
                    ['value' => 'herbeGE1sans',  'label' => 'Ensilage 1ère coupe sans conservateur'],
                    ['value' => 'herbeGE1avec',  'label' => 'Ensilage 1ère coupe avec conservateur'],
                    ['value' => 'herbeGE2sans',  'label' => 'Ensilage 2ème coupe sans conservateur'],
                    ['value' => 'herbeGE2avec',  'label' => 'Ensilage 2ème coupe avec conservateur'],
                ]],
                ['label' => 'Enrubannage (Graminées)', 'options' => [
                    ['value' => 'herbeGEnrub1', 'label' => 'Enrubannage 1ère coupe'],
                    ['value' => 'herbeGEnrub2', 'label' => 'Enrubannage 2ème coupe'],
                ]],
                ['label' => 'Foin (Graminées)', 'options' => [
                    ['value' => 'herbeGF1', 'label' => 'Foin 1ère coupe'],
                    ['value' => 'herbeGF2', 'label' => 'Foin 2ème coupe'],
                ]],
                ['label' => 'Fourrage vert (Graminées)', 'options' => [
                    ['value' => 'herbeGFV1', 'label' => 'Fourrage vert 1ère coupe'],
                    ['value' => 'herbeGFV2', 'label' => 'Fourrage vert 2ème coupe'],
                ]],
            ],
            'herbePP' => [
                ['label' => 'Ensilage (Prairie permanente)', 'options' => [
                    ['value' => 'herbePPE1sans',  'label' => 'Ensilage 1ère coupe sans conservateur'],
                    ['value' => 'herbePPE1avec',  'label' => 'Ensilage 1ère coupe avec conservateur'],
                    ['value' => 'herbePPE2sans',  'label' => 'Ensilage 2ème coupe sans conservateur'],
                    ['value' => 'herbePPE2avec',  'label' => 'Ensilage 2ème coupe avec conservateur'],
                ]],
                ['label' => 'Enrubannage (Prairie permanente)', 'options' => [
                    ['value' => 'herbePPEnrub1', 'label' => 'Enrubannage 1ère coupe'],
                    ['value' => 'herbePPEnrub2', 'label' => 'Enrubannage 2ème coupe'],
                ]],
                ['label' => 'Foin (Prairie permanente)', 'options' => [
                    ['value' => 'herbePPF1', 'label' => 'Foin 1ère coupe'],
                    ['value' => 'herbePPF2', 'label' => 'Foin 2ème coupe'],
                ]],
                ['label' => 'Fourrage vert (Prairie permanente)', 'options' => [
                    ['value' => 'herbePPFV1', 'label' => 'Fourrage vert 1ère coupe'],
                    ['value' => 'herbePPFV2', 'label' => 'Fourrage vert 2ème coupe'],
                ]],
            ],
            'mais' => [
                ['label' => 'Ensilage', 'options' => [
                    ['value' => 'maisE',   'label' => 'Ensilage de Maïs'],
                    ['value' => 'sorghoE', 'label' => 'Ensilage de Sorgho'],
                ]],
                ['label' => 'Fourrage vert', 'options' => [
                    ['value' => 'maisFV',   'label' => 'Fourrage vert de Maïs'],
                    ['value' => 'sorghoFV', 'label' => 'Fourrage vert de Sorgho'],
                ]],
            ],
            'legumineuse' => [
                ['label' => 'Ensilage (Légumineuses)', 'options' => [
                    ['value' => 'luzerneEsans', 'label' => 'Ensilage sans conservateur'],
                    ['value' => 'luzerneEavec', 'label' => 'Ensilage avec conservateur'],
                ]],
                ['label' => 'Enrubannage (Légumineuses)', 'options' => [
                    ['value' => 'luzerneEnrub', 'label' => 'Enrubannage'],
                ]],
                ['label' => 'Foin (Légumineuses)', 'options' => [
                    ['value' => 'luzerneF', 'label' => 'Foin'],
                ]],
                ['label' => 'Fourrage vert (Légumineuses)', 'options' => [
                    ['value' => 'luzerneFV', 'label' => 'Fourrage vert'],
                ]],
                ['label' => 'Déshydratée (Légumineuses)', 'options' => [
                    ['value' => 'luzerneD', 'label' => 'Luzerne déshydratée'],
                ]],
            ],
            default => [],
        };
    }

    /**
     * Indique si un type nécessite le paramètre 'amidon'.
     */
    public static function requiresAmidon(string $type): bool
    {
        return in_array($type, ['maisE', 'maisFV', 'sorghoE', 'sorghoFV'], true);
    }
}
