<?php

declare(strict_types=1);

namespace App\Services\Agrinir\Equations2007;

use App\Services\Agrinir\AgrinirAliment as Aliment;

class LuzerneEAvec
{
    /**
     * Modifier les valeurs alimentaires d'une ensilage de luzerne avec conservateur en fonction d'une analyse Agrinir
     *
     *
     * @return Aliment Aliment modifié
     */
    public static function modifierValeursAlimentaires(
        Aliment $aliment,
        float $humidite,
        float $proteine,
        float $ndf,
        float $adf,
        float $cendres,
        float $matiereGrasse,
        ?float $amidon = null,
        ?float $ca = null,
        ?float $p = null,
        ?float $mg = null,
        ?float $sucres = null,
    ): Aliment {
        $MS100 = 100 - $humidite;

        $MS = ($MS100 * 10);
        $MATgKgMB = ($proteine * 10);
        $MATgKgMS = ($MS != 0) ? ($MATgKgMB / $MS * 1000) : 0;
        $ndfgKgMB = ($ndf * 10);
        $ndfgKgMS = ($MS != 0) ? ($ndfgKgMB / $MS * 1000) : 0;
        $adfgKgMB = ($adf * 10);
        $adfgKgMS = ($MS != 0) ? ($adfgKgMB / $MS * 1000) : 0;
        $MM = ($cendres * 10);
        $MG = ($matiereGrasse * 10);

        // PANDI=7,9+0,08MAT-0,00033MAT*MAT-1,9
        $PANDI = (7.9 + (0.08 * $MATgKgMS) - (0.00033 * $MATgKgMS * $MATgKgMS) - 1.9);
        // MO=MS-MM (g/Kg de MB)
        $MOgKgMB = ($MS - $MM);
        $MOgKgMS = $MOgKgMB / $MS * 1000;
        // CB (g/kgMS)=1.572*ADF (g/kgMS)-209
        $CBkgMS = ((1.572 * $adfgKgMS) - 209);
        // CB (g/kgMB)=CB (g/kgMS)*MS/100
        $CBkgMB = ($CBkgMS * $MS / 1000);
        // CBo (g/Kg de MO)=CB/MO*1000
        $CBo = ($CBkgMB / $MOgKgMB * 1000);
        // MATo (g/Kg de MO)=MAT/MO*1000
        $MATo = ($MATgKgMB / $MOgKgMB * 1000);
        // EM/ED=(84,17-0,0099CBo-0,0196MATo+2,21*1,5)/100
        $EMED = ((84.17 - (0.0099 * $CBo) - (0.0196 * $MATo) + (2.21 * 1.5)) / 100);
        // dMO%=134,2-0,211*ADF(g/kgMS)
        $dMO = (134.2 - (0.211 * $adfgKgMS));
        // dE%=1,0263dMO-5,723
        $dE = ((1.0263 * $dMO) - 5.723);
        // Ebo=1,03*(4531+1,735*MATo+82)
        $EBo1 = (1.03 * (4531 + (1.735 * $MATo) + 82));
        // Ebo=1,03*(4531+1,735*MATo+82)
        $EBo2 = ($EBo1);
        // DT_N=0,96*(73,7+0,088MAT-0,00011MAT*MAT-0,25MS+4,2)
        $DT_N = ((73.7 + (0.088 * $MATgKgMS) - (0.00011 * $MATgKgMS * $MATgKgMS) - (0.25 * $MS100) + 4.2) * 0.96);
        // dr%=100*(1,11*(1-DT%/100)*MAT-PANDI)/(1,11*(1-DT%/100)*MAT)
        $dr = (100 * ((1.11 * (1 - ($DT_N / 100)) * $MATgKgMS) - $PANDI) / (1.11 * (1 - ($DT_N / 100)) * $MATgKgMS));
        // MS<30 EB1=EBo1*MO/1000
        $EB1 = ($EBo1 * $MOgKgMB / 1000);
        // MS>30 EB2=EBo2*MO/1000
        $EB2 = ($EBo2 * $MOgKgMB / 1000);
        // EM=EB*dE/100*(EM/ED)
        $EM = ($EB2 * $dE * $EMED / 100);
        // kl=0,6+0,24*(EM/EB-0,57)
        $kl = (0.6 + (0.24 * (($EM / $EB2) - 0.57)));
        // km=0,287*(EM/EB)+0,554
        $km = ((0.287 * ($EM / $EB2)) + 0.554);
        // kf=0,78*(EM/EB)+0,006
        $kf = ((0.78 * ($EM / $EB2)) + 0.006);
        // kmf=(km*kf*1,5)/(kf+0,5*km)
        $kmf = (($km * $kf * 1.5) / ($kf + (0.5 * $km)));
        // ENL=EM*kl
        $ENL = ($EM * $kl);
        // ENEV=EM*kmf
        $ENEV = ($EM * $kmf);
        // UFL=ENL/1700
        $UFL = ($ENL / 1700);
        // UFV=ENEV/1820
        $UFV = ($ENEV / 1820);
        // PDIA=MAT*(1,11*(1-DT/100))*dr/100
        $PDIA = ($MATgKgMB * (1.11 * (1 - ($DT_N / 100))) * $dr / 100);
        // PDIMN=MAT*(1-1,11*(1-DT/100))*0,9*0,8*0,8
        $PDIMN = ($MATgKgMB * (1 - (1.11 * (1 - $DT_N / 100))) * 0.9 * 0.8 * 0.8);
        // MOD=MO*dMO/100
        $MOD = ($MOgKgMB * $dMO / 100);
        // MS=25 MOF=MOD-MG-(MAT*(1-DT/100))-(80/1000*MS)
        $MOF25 = ($MOD - $MG - $MATgKgMB * (1 - $DT_N / 100) - 80 / 1000 * $MS);
        // MS=30 MOF=MOD-MG-(MAT*(1-DT/100))-(80/1000*MS)
        $MOF30 = ($MOD - $MG - $MATgKgMB * (1 - $DT_N / 100) - 80 / 1000 * $MS);
        // MS=35 MOF=MOD-MG-(MAT*(1-DT/100))-(80/1000*MS)
        $MOF35 = ($MOD - $MG - $MATgKgMB * (1 - $DT_N / 100) - 80 / 1000 * $MS);
        // MS=40 MOF=MOD-MG-(MAT*(1-DT/100))-(80/1000*MS)
        $MOF40 = ($MOD - $MG - $MATgKgMB * (1 - $DT_N / 100) - 80 / 1000 * $MS);
        // PDIME=MOF*0,145*0,8*0,8
        $PDIME = ($MOF35 * 0.145 * 0.8 * 0.8);
        // PDIE=PDIA+PDIME
        $PDIE = ($PDIA + $PDIME);
        // PDIN=PDIA+PDIMN
        $PDIN = ($PDIA + $PDIMN);
        // QIM=20,1+0,306*dMO+0,078MAT+13,4-3,7
        $QIM = (20.1 + (0.306 * $dMO) + (0.078 * ($MATgKgMS)) + 13.4 - 3.7);
        // QIL=99,3+0,167*dMO+0,128MAT+2,8+1,6
        $QIL = (99.3 + (0.167 * $dMO) + (0.128 * ($MATgKgMS)) + 2.8 + 1.6);
        // QIB=47+0,228*dMO+0,148MAT+2,8+1,9
        $QIB = (47 + (0.228 * $dMO) + (0.148 * ($MATgKgMS)) + 2.8 + 1.9);
        // UEM=75/QIM
        $UEM = (75 / $QIM);
        // UEL=140/QIL
        $UEL = (140 / $QIL);
        // UEB=95/QIB
        $UEB = (95 / $QIB);

        switch ($humidite) {
            case $humidite > 72.5:
                $mof = ($MOF25 * 1000 / $MS);
                break;
            case $humidite <= 72.5 && $humidite > 67.5:
                $mof = ($MOF30 * 1000 / $MS);
                break;
            case $humidite <= 67.5 && $humidite > 62.5:
                $mof = ($MOF35 * 1000 / $MS);
                break;
            case $humidite <= 62.5:
                $mof = ($MOF40 * 1000 / $MS);
                break;

            default:
                // code...
                break;
        }

        $aliment->setMS($MS100);
        $aliment->setADF($adfgKgMS);
        $aliment->setNDF($ndfgKgMS);
        $aliment->setUFL2007($UFL * 1000 / $MS);
        $aliment->setUFV2007($UFV * 1000 / $MS);
        $aliment->setPDIE2007($PDIE * 1000 / $MS);
        $aliment->setPDIN2007($PDIN * 1000 / $MS);
        $aliment->setPDIA2007($PDIA * 1000 / $MS);
        $aliment->setUEM2007($UEM);
        $aliment->setUEL2007($UEL);
        $aliment->setUEB2007($UEB);
        $aliment->setMO($MOgKgMS);
        $aliment->setDMO2007($dMO);
        $aliment->setMAT($MATgKgMS);
        $aliment->setDMA2007($MATgKgMS * (1 - 1.11 * (1 - $DT_N / 100)));
        $aliment->setCB($CBkgMS);

        if ($ca != null) {
            $aliment->setCa($ca);
            $aliment->setCaabs(0.3 * $ca);
        }

        if ($p != null) {
            $aliment->setP($p);
            $aliment->setPabs(0.65 * $p);
        }

        if ($mg != null) {
            $aliment->setMg($mg);
        }

        return $aliment;
    }
}
