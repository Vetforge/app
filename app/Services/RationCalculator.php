<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ration;
use App\Services\Equations2007\Apport as Apport2007;
use App\Services\Equations2007\Besoin as Besoin2007;
use App\Services\Equations2007\Impact as Impact2007;
use App\Services\Equations2018\Apport as Apport2018;
use App\Services\Equations2018\Besoin as Besoin2018;
use App\Services\Equations2018\Impact as Impact2018;

/**
 * Orchestrateur des calculs nutritionnels — dispatche vers INRA 2007 ou 2018.
 */
class RationCalculator
{
    /**
     * Calculer tous les résultats nutritionnels d'une ration.
     *
     * @return array<string, mixed>
     */
    public static function calculer(Ration $ration): array
    {
        $inra = $ration->planRationnement->inra ?? '2018';

        return $inra === '2007'
            ? self::calculer2007($ration)
            : self::calculer2018($ration);
    }

    // ─── INRA 2007 ─────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private static function calculer2007(Ration $ration): array
    {
        Apport2007::precompute($ration);

        try {
            return self::calculer2007Inner($ration);
        } finally {
            Apport2007::clearCache();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function calculer2007Inner(Ration $ration): array
    {
        // Apports
        $apportMS = Apport2007::calculerApportTotalMS($ration);
        $apportUE = Apport2007::calculerApportTotalUE($ration);
        $apportUFL = Apport2007::calculerApportTotalUF($ration);
        $apportPDIE = Apport2007::calculerApportTotalPDIE($ration);
        $apportPDIN = Apport2007::calculerApportTotalPDIN($ration);
        $apportCa = Apport2007::calculerApportCa($ration);
        $apportCaabs = Apport2007::calculerApportCaabs($ration);
        $apportP = Apport2007::calculerApportP($ration);
        $apportPabs = Apport2007::calculerApportPabs($ration);
        $apportMg = Apport2007::calculerApportMg($ration);
        $apportMgabs = Apport2007::calculerApportMgabs($ration);
        $apportK = Apport2007::calculerApportK($ration);
        $apportNa = Apport2007::calculerApportNa($ration);
        $apportUFLParKgMS = Apport2007::calculerApportUFLParKgMS($ration);
        $apportPDIEParKgMS = Apport2007::calculerApportPDIEParKgMS($ration);
        $apportPDINParKgMS = Apport2007::calculerApportPDINParKgMS($ration);
        $apportCBParKgMS = Apport2007::calculerApportCBParKgMS($ration);
        $apportAmidonParKgMS = Apport2007::calculerApportAmidonParKgMS($ration);
        $apportADFParKgMS = Apport2007::calculerApportADFParKgMS($ration);
        $apportNDFParKgMS = Apport2007::calculerApportNDFParKgMS($ration);

        // Besoins
        $besoinUF_NP = Besoin2007::calculerBesoinUF_NP($ration);
        $besoinUF_PL = Besoin2007::calculerBesoinUF_PL($ration);
        $besoinUF_gest = Besoin2007::calculerBesoinUF_gest($ration);
        $besoinUF_gain = Besoin2007::calculerBesoinUF_gain($ration);
        $besoinTotalUF = Besoin2007::calculerBesoinTotalUF($ration);
        $besoinPDI_NP = Besoin2007::calculerBesoinPDI_NP($ration);
        $besoinPDI_PL = Besoin2007::calculerBesoinPDI_PL($ration);
        $besoinTotalPDI = Besoin2007::calculerBesoinTotalPDI($ration);
        $CI = Besoin2007::calculerCapaciteIngestion($ration);
        $besoinCaabs = Besoin2007::calculerBesoinCaabs($ration);
        $besoinPabs = Besoin2007::calculerBesoinPabs($ration);
        $besoinMgabs = Besoin2007::calculerBesoinMgabs($ration);
        $TB = Besoin2007::calculerTB($ration);
        $TP = Besoin2007::calculerTP($ration);

        // Impacts
        $laitParUFL = Impact2007::calculerLaitPermisParUFL($ration);
        $laitParPDIE = Impact2007::calculerLaitPermisParPDIE($ration);
        $laitParPDIN = Impact2007::calculerLaitPermisParPDIN($ration);
        $eauBue = Impact2007::calculerEauBue($ration);
        $coutParAnimal = Impact2007::calculerCoutParAnimal($ration);
        $coutPar1000 = Impact2007::calculerCoutPar1000Lait($ration);
        $rmic = Impact2007::calculerRapportRmic($ration);
        $PDINmin = Impact2007::calculerMinimumPDIN($ration);
        $PDINmax = Impact2007::calculerMaximumPDIN($ration);

        return [
            'inra' => '2007',
            'apports' => [
                'ms' => round($apportMS, 2),
                'ue' => round($apportUE, 2),
                'ufl' => round($apportUFL, 2),
                'pdie' => round($apportPDIE, 1),
                'pdin' => round($apportPDIN, 1),
                'ca' => round($apportCa, 1),
                'caabs' => round($apportCaabs, 1),
                'p' => round($apportP, 1),
                'pabs' => round($apportPabs, 1),
                'mg' => round($apportMg, 1),
                'mgabs' => round($apportMgabs, 1),
                'k' => round($apportK, 1),
                'na' => round($apportNa, 1),
            ],
            'besoins' => [
                'uf_entretien' => round($besoinUF_NP, 2),
                'uf_production' => round($besoinUF_PL, 2),
                'uf_gestation' => round($besoinUF_gest, 2),
                'uf_croissance' => round($besoinUF_gain, 2),
                'uf_total' => round($besoinTotalUF, 2),
                'pdi_entretien' => round($besoinPDI_NP, 1),
                'pdi_production' => round($besoinPDI_PL, 1),
                'pdi_total' => round($besoinTotalPDI, 1),
                'ci' => round($CI, 2),
                'caabs' => round($besoinCaabs, 1),
                'pabs' => round($besoinPabs, 1),
                'mgabs' => round($besoinMgabs, 1),
                'tb_ajuste' => round($TB, 1),
                'tp_ajuste' => round($TP, 1),
            ],
            'impacts' => [
                'lait_par_ufl' => round($laitParUFL, 2),
                'lait_par_pdie' => round($laitParPDIE, 2),
                'lait_par_pdin' => round($laitParPDIN, 2),
                'lait_limitant' => round(min($laitParUFL, $laitParPDIE, $laitParPDIN), 2),
                'eau_bue' => round($eauBue, 1),
                'cout_animal' => round($coutParAnimal, 2),
                'cout_1000l' => round($coutPar1000, 2),
                'rmic' => round($rmic, 2),
            ],
            'bilans' => [
                'ufl' => round($apportUFL - $besoinTotalUF, 2),
                'ue' => round($apportUE - $CI, 2),
                'pdie' => round($apportPDIE - $besoinTotalPDI, 1),
                'pdin' => round($apportPDIN - $besoinTotalPDI, 1),
                'caabs' => round($apportCaabs - $besoinCaabs, 1),
                'pabs' => round($apportPabs - $besoinPabs, 1),
                'mgabs' => round($apportMgabs - $besoinMgabs, 1),
            ],
            'indicateurs' => [
                'ufl_par_kg_ms' => round($apportUFLParKgMS, 2),
                'pdie_par_kg_ms' => round($apportPDIEParKgMS, 2),
                'pdin_par_kg_ms' => round($apportPDINParKgMS, 2),
                'cb_par_kg_ms' => round($apportCBParKgMS, 1),
                'amidon_par_kg_ms' => round($apportAmidonParKgMS, 1),
                'adf_par_kg_ms' => round($apportADFParKgMS, 1),
                'ndf_total' => round($apportNDFParKgMS, 1),
                'rmic' => round($rmic, 2),
                'pdin_min' => round($PDINmin, 1),
                'pdin_max' => round($PDINmax, 1),
            ],
        ];
    }

    // ─── INRA 2018 ─────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private static function calculer2018(Ration $ration): array
    {
        Apport2018::precompute($ration);

        try {
            return self::calculer2018Inner($ration);
        } finally {
            Apport2018::clearCache();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function calculer2018Inner(Ration $ration): array
    {
        // Apports
        $apportMS = Apport2018::calculerApportTotalMS($ration);
        $apportUE = Apport2018::calculerApportTotalUE($ration);
        $apportUFL = Apport2018::calculerApportTotalUF($ration);
        $apportUFLParKgMS = Apport2018::calculerApportUFLParKgMS($ration);
        $apportPDI = Apport2018::calculerApportTotalPDI($ration);
        $apportPDIA = Apport2018::calculerApportTotalPDIA($ration);
        $apportCBParKgMS = Apport2018::calculerApportCBParKgMS($ration);
        $apportADFParKgMS = Apport2018::calculerApportADFParKgMS($ration);
        $apportCa = Apport2018::calculerApportCa($ration);
        $apportCaabs = Apport2018::calculerApportCaabs($ration);
        $apportP = Apport2018::calculerApportP($ration);
        $apportPabs = Apport2018::calculerApportPabs($ration);
        $apportMg = Apport2018::calculerApportMg($ration);
        $apportMgabs = Apport2018::calculerApportMgabs($ration);
        $apportK = Apport2018::calculerApportK($ration);
        $apportNa = Apport2018::calculerApportNa($ration);
        $apportCl = Apport2018::calculerApportCl($ration);
        $apportS = Apport2018::calculerApportS($ration);
        $apportCo = Apport2018::calculerApportCo($ration);
        $apportSe = Apport2018::calculerApportSe($ration);
        $apportZn = Apport2018::calculerApportZn($ration);
        $apportMn = Apport2018::calculerApportMn($ration);
        $apportCu = Apport2018::calculerApportCu($ration);
        $apportI = Apport2018::calculerApportI($ration);
        $apportVitA = Apport2018::calculerApportVitA($ration);
        $apportVitD = Apport2018::calculerApportVitD($ration);
        $apportVitE = Apport2018::calculerApportVitE($ration);
        $NI = Apport2018::calculerNI($ration);
        $PCO = Apport2018::calculerPCO($ration);
        $Sg = Apport2018::calculerSg($ration);
        $EffPDI = Apport2018::calculerEffPDI($ration);
        $apportPDIParKgMS = Apport2018::calculerApportPDIParKgMS($ration);
        $BPR = Apport2018::calculerBPR($ration);
        $amidonRumen = Apport2018::calculerApportAmiD_ru($ration);
        $apportConcentreMOD = Apport2018::calculerApportConcentreMOD($ration);
        $apportNDFf = Apport2018::calculerApportNDFf($ration);
        $apportNDF = Apport2018::calculerApportNDFParKgMS($ration);

        // Besoins
        $besoinUF_NP = Besoin2018::calculerBesoinUF_NP($ration);
        $besoinUF_PL = Besoin2018::calculerBesoinUF_PL($ration);
        $besoinUF_gest = Besoin2018::calculerBesoinUF_gest($ration);
        $besoinUF_gain = Besoin2018::calculerBesoinUF_gain($ration);
        $besoinUF_DRC = Besoin2018::calculerBesoinUF_DRC($ration);
        $besoinTotalUF = Besoin2018::calculerBesoinTotalUF($ration);
        $besoinPDI_NP = Besoin2018::calculerBesoinPDI_NP($ration);
        $besoinPDI_PL = Besoin2018::calculerBesoinPDI_PL($ration);
        $besoinPDI_gest = Besoin2018::calculerBesoinPDI_gest($ration);
        $besoinPDI_gain = Besoin2018::calculerBesoinPDI_gain($ration);
        $besoinPDI_DRC = Besoin2018::calculerBesoinPDI_DRC($ration);
        $besoinTotalPDI = Besoin2018::calculerBesoinTotalPDI($ration);
        $CI = Besoin2018::calculerCapaciteIngestion($ration);
        $besoinCaabs = Besoin2018::calculerBesoinCaabs($ration);
        $besoinPabs = Besoin2018::calculerBesoinPabs($ration);
        $besoinMgabs = Besoin2018::calculerBesoinMgabs($ration);
        $besoinNa = Besoin2018::calculerBesoinNa($ration);
        $besoinK = Besoin2018::calculerBesoinK($ration);
        $besoinCl = Besoin2018::calculerBesoinCl($ration);
        $besoinS = Besoin2018::calculerBesoinS($ration);
        $besoinCo = Besoin2018::calculerBesoinCo($ration);
        $besoinSe = Besoin2018::calculerBesoinSe($ration);
        $besoinZn = Besoin2018::calculerBesoinZn($ration);
        $besoinMn = Besoin2018::calculerBesoinMn($ration);
        $besoinCu = Besoin2018::calculerBesoinCu($ration);
        $besoinI = Besoin2018::calculerBesoinI($ration);
        $besoinVitA = Besoin2018::calculerBesoinVitA($ration);
        $besoinVitD = Besoin2018::calculerBesoinVitD($ration);
        $besoinVitE = Besoin2018::calculerBesoinVitE($ration);
        $TB = Besoin2018::calculerTB($ration);
        $TP = Besoin2018::calculerTP($ration);

        // Impacts
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $laitParUFL = Impact2018::calculerLaitPermisParUF($ration);
        $laitParPDI = Impact2018::calculerLaitPermisParPDI($ration);
        $laitLimitant = min($laitParUFL, $laitParPDI);
        $productionLaitAttendue = $categorie === 'vacheLaitiere'
            ? Impact2018::calculerPL($ration)
            : null;
        $eauBue = Impact2018::calculerEauBue($ration);
        $coutParAnimal = Impact2018::calculerCoutParAnimal($ration);
        $coutPar1000 = Impact2018::calculerCoutPar1000Lait($ration);
        $productionCH4 = Impact2018::calculerCH4($ration);
        $bilUFL = Impact2018::calculerBilUFL($ration);
        $BE = Impact2018::calculerBE($ration);
        $BACA = Impact2018::calculerBACA($ration);
        $IRA = Impact2018::calculerIRA($ration);
        $phRuminal = Impact2018::calculerPH($ration);
        $azoteUrinaire = Impact2018::calculerNU($ration);
        $azoteFecale = Impact2018::calculerNND($ration);
        $productionAGV = Apport2018::calculerProdAGVT($ration) * $apportMS;
        $acetate = Impact2018::calculerPourcentageAcetate($ration);
        $propionate = Impact2018::calculerPourcentagePropionate($ration);
        $butyrate = Impact2018::calculerPourcentageButyrate($ration);
        $productionPotentielle = RationHelper::calculerProductionLaitPotentielle($ration);

        $impacts = [
            'lait_par_ufl' => round($laitParUFL, 2),
            'lait_par_pdi' => round($laitParPDI, 2),
            'lait_limitant' => round($laitLimitant, 2),
            'eau_bue' => round($eauBue, 1),
            'cout_animal' => round($coutParAnimal, 2),
            'cout_1000l' => round($coutPar1000, 2),
            'ch4' => round($productionCH4, 1),
            'bil_ufl' => round($bilUFL, 2),
        ];

        if ($productionLaitAttendue !== null) {
            $impacts['production_lait_attendue'] = round($productionLaitAttendue, 2);
        }

        return [
            'inra' => '2018',
            'apports' => [
                'ms' => round($apportMS, 2),
                'ue' => round($apportUE, 2),
                'ufl' => round($apportUFL, 2),
                'pdi' => round($apportPDI, 1),
                'pdia' => round($apportPDIA, 1),
                'ca' => round($apportCa, 1),
                'caabs' => round($apportCaabs, 1),
                'p' => round($apportP, 1),
                'pabs' => round($apportPabs, 1),
                'mg' => round($apportMg, 1),
                'mgabs' => round($apportMgabs, 1),
                'k' => round($apportK, 1),
                'na' => round($apportNa, 1),
                'cl' => round($apportCl, 1),
                's' => round($apportS, 1),
                'co' => round($apportCo, 1),
                'se' => round($apportSe, 1),
                'zn' => round($apportZn, 1),
                'mn' => round($apportMn, 1),
                'cu' => round($apportCu, 1),
                'i' => round($apportI, 1),
                'vit_a' => round($apportVitA, 0),
                'vit_d' => round($apportVitD, 0),
                'vit_e' => round($apportVitE, 0),
            ],
            'besoins' => [
                'uf_entretien' => round($besoinUF_NP, 2),
                'uf_production' => round($besoinUF_PL, 2),
                'uf_gestation' => round($besoinUF_gest, 2),
                'uf_croissance' => round($besoinUF_gain, 2),
                'uf_drc' => round($besoinUF_DRC, 2),
                'uf_total' => round($besoinTotalUF, 2),
                'pdi_entretien' => round($besoinPDI_NP, 1),
                'pdi_production' => round($besoinPDI_PL, 1),
                'pdi_gestation' => round($besoinPDI_gest, 1),
                'pdi_croissance' => round($besoinPDI_gain, 1),
                'pdi_drc' => round($besoinPDI_DRC, 1),
                'pdi_total' => round($besoinTotalPDI, 1),
                'ci' => round($CI, 2),
                'caabs' => round($besoinCaabs, 1),
                'pabs' => round($besoinPabs, 1),
                'mgabs' => round($besoinMgabs, 1),
                'na' => round($besoinNa, 1),
                'k' => round($besoinK, 1),
                'cl' => round($besoinCl, 1),
                's' => round($besoinS, 1),
                'co' => round($besoinCo, 1),
                'se' => round($besoinSe, 1),
                'zn' => round($besoinZn, 1),
                'mn' => round($besoinMn, 1),
                'cu' => round($besoinCu, 1),
                'i' => round($besoinI, 1),
                'vit_a' => round($besoinVitA, 0),
                'vit_d' => round($besoinVitD, 0),
                'vit_e' => round($besoinVitE, 0),
                'tb_ajuste' => round($TB, 1),
                'tp_ajuste' => round($TP, 1),
            ],
            'impacts' => $impacts,
            'bilans' => [
                'ufl' => round($apportUFL - $besoinTotalUF, 2),
                'ue' => round($apportUE - $CI, 2),
                'pdi' => round($apportPDI - $besoinTotalPDI, 1),
                'caabs' => round($apportCaabs - $besoinCaabs, 1),
                'pabs' => round($apportPabs - $besoinPabs, 1),
                'mgabs' => round($apportMgabs - $besoinMgabs, 1),
            ],
            'indicateurs' => [
                'ni' => round($NI, 2),
                'pco' => round($PCO, 3),
                'sg' => round($Sg, 3),
                'pl_pot' => round($productionPotentielle, 2),
                'eff_pdi' => round($EffPDI, 3),
                'ufl_par_kg_ms' => round($apportUFLParKgMS, 2),
                'cb_par_kg_ms' => round($apportCBParKgMS, 1),
                'adf_par_kg_ms' => round($apportADFParKgMS, 1),
                'pdi_par_kg_ms' => round($apportPDIParKgMS, 2),
                'bpr' => round($BPR, 2),
                'baca' => round($BACA, 1),
                'be' => round($BE, 1),
                'mod_concentre' => round($apportConcentreMOD, 1),
                'amid_ru' => round($amidonRumen, 1),
                'pco_percent' => round($PCO * 100, 1),
                'ndf_fourrages' => round($apportNDFf, 1),
                'ndf_total' => round($apportNDF, 1),
                'ira' => round($IRA, 2),
                'ph_ruminal' => round($phRuminal, 2),
                'prod_agvt_jour' => round($productionAGV, 2),
                'acetate' => round($acetate, 1),
                'propionate' => round($propionate, 1),
                'butyrate' => round($butyrate, 1),
                'azote_urinaire' => round($azoteUrinaire, 1),
                'azote_fecale' => round($azoteFecale, 1),
            ],
        ];
    }
}
