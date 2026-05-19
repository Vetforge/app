<?php

declare(strict_types=1);

namespace App\Services\Agrinir\Equations2007;

use App\Services\Agrinir\AgrinirAliment as Aliment;

class SorghoE
{
    /**
     * Modifier les valeurs alimentaires d'un ensilage de sorgho en fonction d'une analyse Agrinir
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
        $AmidonKgMB = ($amidon * 10);
        $AmidonKgMS = ($MS != 0) ? ($AmidonKgMB / $MS * 1000) : 0;
        $MATgKgMB = ($proteine * 10);
        $MATgKgMS = ($MS != 0) ? ($MATgKgMB / $MS * 1000) : 0;
        $ndfgKgMB = ($ndf * 10);
        $ndfgKgMS = ($MS != 0) ? ($ndfgKgMB / $MS * 1000) : 0;
        $adfgKgMB = ($adf * 10);
        $adfgKgMS = ($MS != 0) ? ($adfgKgMB / $MS * 1000) : 0;
        $MM = ($cendres * 10);
        $MG = ($matiereGrasse * 10);

        // MO=MS-MM (g/Kg de MB)
        $MOgKgMB = ($MS - $MM);
        $MOgKgMS = $MOgKgMB / $MS * 1000;
        // CB (g/kgMS)=0,87*ADF (g/kgMS)+9,5
        $CBkgMS = ((0.87 * $adfgKgMS) + 9.5);
        // CB (g/kgMB)=CB (g/kgMS)*MS/100
        $CBkgMB = ($CBkgMS * $MS / 1000);
        // CBo (g/Kg de MO)=CB/MO*1000
        $CBo = ($CBkgMB / $MOgKgMB * 1000);
        // MATo (g/Kg de MO)=MAT/MO*1000
        $MATo = ($MATgKgMB / $MOgKgMB * 1000);
        // EM/ED=(84,17-0,0099CBo-0,0196MATo+2,21*1,2)/100
        $EMED = ((84.17 - (0.0099 * $CBo) - (0.0196 * $MATo) + (2.21 * 1.2)) / 100);
        // dMO%=79,4-0,059*CBo+0,065*MATo
        $dMO = (79.4 - (0.059 * $CBo) + (0.065 * $MATo));
        // dE%=1,001dMO-2,86
        $dE = ((1.001 * $dMO) - 2.86);
        // MS<3O EBo1=1,02*(4478+1,265*MATo)
        $EBo1 = (1.02 * (4478 + (1.265 * $MATo)));
        // MS>30 EBo2=(4478+1,265*MATo)+25
        $EBo2 = ((4478 + (1.265 * $MATo)) + 25);
        // DT_N=72
        $DT_N = 72;
        // dr%=70
        $dr = 70;
        // MS<30 EB1=EBo1*MO/1000
        $EB1 = ($EBo1 * $MOgKgMB / 1000);
        // MS>30 EB2=EBo2*MO/1000
        $EB2 = ($EBo2 * $MOgKgMB / 1000);
        $EM = 0.0;
        // EM=EB*dE/100*(EM/ED)
        if ($MS100 < 30) {
            $EM = ($EB1 * $dE * $EMED / 100);
        }
        if ($MS100 >= 30) {
            $EM = ($EB2 * $dE * $EMED / 100);
        }
        // kl=0,6+0,24*(EM/EB-0,57)
        $kl = (0.6 + (0.24 * (($EM / $EB1) - 0.57)));
        // km=0,287*(EM/EB)+0,554
        $km = ((0.287 * ($EM / $EB1)) + 0.554);
        // kf=0,78*(EM/EB)+0,006
        $kf = ((0.78 * ($EM / $EB1)) + 0.006);
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
        $PDIA = ($MATgKgMB * (1.11 * (1 - (72 / 100))) * 70 / 100);
        // PDIMN=MAT*(1-1,11*(1-DT/100))*0,9*0,8*0,8
        $PDIMN = ($MATgKgMB * (1 - (1.11 * (1 - 72 / 100))) * 0.9 * 0.8 * 0.8);
        // MOD=MO*dMO/100
        $MOD = ($MOgKgMB * $dMO / 100);
        // MS=25 MOF=MOD-MG-(MAT*(1-DT/100))-(125/1000*MS)
        $MOF25 = ($MOD - $MG - $MATgKgMB * (1 - $DT_N / 100) - 125 / 1000 * $MS);
        // MS=30 MOF=MOD-MG-(MAT*(1-DT/100))-(100/1000*MS)
        $MOF30 = ($MOD - $MG - $MATgKgMB * (1 - $DT_N / 100) - 100 / 1000 * $MS);
        // MS=35 MOF=MOD-MG-(MAT*(1-DT/100))-(80/1000*MS)
        $MOF35 = ($MOD - $MG - $MATgKgMB * (1 - $DT_N / 100) - 80 / 1000 * $MS);
        // MS=40 MOF=MOD-MG-(MAT*(1-DT/100))-(60/1000*MS)
        $MOF40 = ($MOD - $MG - $MATgKgMB * (1 - $DT_N / 100) - 60 / 1000 * $MS);
        $PDIME = 0.0;
        // PDIME=MOF*0,145*0,8*0,8
        switch ($humidite) {
            case $humidite > 72.5:
                $PDIME = ($MOF25 * 0.145 * 0.8 * 0.8);
                break;
            case $humidite <= 72.5 && $humidite > 67.5:
                $PDIME = ($MOF30 * 0.145 * 0.8 * 0.8);
                break;
            case $humidite <= 67.5 && $humidite > 62.5:
                $PDIME = ($MOF35 * 0.145 * 0.8 * 0.8);
                break;
            case $humidite <= 62.5:
                $PDIME = ($MOF40 * 0.145 * 0.8 * 0.8);
                break;

            default:
                // code...
                break;
        }
        // PDIE=PDIA+PDIME
        $PDIE = ($PDIA + $PDIME);
        // PDIN=PDIA+PDIMN
        $PDIN = ($PDIA + $PDIMN);
        // QIM=-1701+48,92*dMO-0,34*dMO*dMO
        $QIM = (-1701 + (48.92 * $dMO) - (0.34 * $dMO * $dMO));
        // QIL=-76,4+2,38*dMO+1,44MS
        $QIL = ((2.39 * $dMO) - 76.4 + (1.44 * $MS100));
        // QIB=-45,49+1,34*dMO+1,15MS
        $QIB = ((1.34 * $dMO) - 45.49 + (1.15 * $MS100));
        // UEM=75/QIM
        $UEM = (75 / $QIM);
        // UEL=140/QIL
        $UEL = (140 / $QIL);
        // UEB=95/QIB
        $UEB = (95 / $QIB);

        $mof = 0.0;
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
        $aliment->setAmidon($AmidonKgMS);

        if ($ca != null) {
            $aliment->setCa($ca);
            $aliment->setCaabs(0.4 * $ca);
        }

        if ($p != null) {
            $aliment->setP($p);
            $aliment->setPabs(0.66 * $p);
        }

        if ($mg != null) {
            $aliment->setMg($mg);
        }

        return $aliment;
    }
}
