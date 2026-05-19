<?php

declare(strict_types=1);

namespace App\Services\Equations2007;

use App\Models\Aliment;
use App\Models\Ration;
use App\Services\RationHelper;

/**
 * Calculs des apports nutritionnels INRA 2007.
 */
class Apport
{
    /** @var array<int, array{qty_ms: float, qty_mb: float, aliment: Aliment, type: string}>|null */
    private static ?array $ctxIngredients = null;

    public static function precompute(Ration $ration): void
    {
        self::$ctxIngredients = self::buildIngredients($ration);
    }

    public static function clearCache(): void
    {
        self::$ctxIngredients = null;
    }

    /**
     * @return array<int, array{qty_ms: float, qty_mb: float, aliment: Aliment, type: string}>
     */
    private static function getIngredients(Ration $ration): array
    {
        return self::$ctxIngredients ?? self::buildIngredients($ration);
    }

    /**
     * @return array<int, array{qty_ms: float, qty_mb: float, aliment: Aliment, type: string}>
     */
    private static function buildIngredients(Ration $ration): array
    {
        $items = [];

        foreach ($ration->rationAliments as $rationAliment) {
            $quantite = (float) ($rationAliment->quantite ?? 0);
            if ($quantite <= 0) {
                continue;
            }

            $ms = (float) ($rationAliment->aliment->ms ?? 0);
            $qtyMs = $rationAliment->is_mb ? $quantite * $ms / 100 : $quantite;
            $qtyMb = $rationAliment->is_mb ? $quantite : ($ms > 0 ? $quantite * 100 / $ms : 0.0);

            $items[] = [
                'qty_ms' => $qtyMs,
                'qty_mb' => $qtyMb,
                'aliment' => $rationAliment->aliment,
                'type' => (string) ($rationAliment->aliment->type ?? ''),
            ];
        }

        foreach ($ration->melanges as $melange) {
            foreach ($melange->melangeAliments as $melangeAliment) {
                $contribution = $melange->effectiveContributionForAliment($melangeAliment);
                if ($contribution['qty_ms'] <= 0 && $contribution['qty_mb'] <= 0) {
                    continue;
                }

                $items[] = [
                    'qty_ms' => $contribution['qty_ms'],
                    'qty_mb' => $contribution['qty_mb'],
                    'aliment' => $melangeAliment->aliment,
                    'type' => (string) ($melangeAliment->aliment->type ?? ''),
                ];
            }
        }

        return $items;
    }

    public static function calculerApportTotalMS(Ration $ration): float
    {
        return self::sumQtyMs($ration);
    }

    public static function calculerApportMSParMB(Ration $ration): float
    {
        $apportMb = self::sumQtyMb($ration);

        return $apportMb > 0 ? self::calculerApportTotalMS($ration) / $apportMb : 0.0;
    }

    public static function calculerApportFourragesMS(Ration $ration): float
    {
        return self::sumQtyMs($ration, 'Fourrage');
    }

    public static function calculerApportConcentresMS(Ration $ration): float
    {
        return self::sumQtyMs($ration, 'Conc');
    }

    public static function calculerApportFourragesUE(Ration $ration): float
    {
        $categorie = RationHelper::normalizeCategorieAnimal($ration->categorie_animal ?? '');
        $champ = $categorie === 'vacheAllaitante' ? 'ueb2007' : 'uel2007';

        return self::sumProperty($ration, $champ, 'Fourrage');
    }

    public static function calculerSg(Ration $ration): float
    {
        $fourragesUFL = self::calculerApportFourragesUF($ration);
        $fourragesUE = self::calculerApportFourragesUE($ration);
        $concentresMS = self::calculerApportConcentresMS($ration);
        $apportTotalMS = self::calculerApportTotalMS($ration);
        $laitObjectif = (float) ($ration->lait_objectif ?? 0);

        if ($laitObjectif === 0.0) {
            $tauxConcentre = $apportTotalMS > 0 ? $concentresMS / $apportTotalMS : 0.0;

            return match (true) {
                $tauxConcentre <= 0.1 => 0.4,
                $tauxConcentre <= 0.15 => 0.45,
                $tauxConcentre <= 0.2 => 0.5,
                $tauxConcentre <= 0.25 => 0.6,
                $tauxConcentre <= 0.3 => 0.7,
                default => 0.8,
            };
        }

        $d = 1.1 - (0.14 * ((float) ($ration->pourcentage_primipare ?? 0) / 100));
        $sgE = $fourragesUE !== 0.0
            ? $d * pow($laitObjectif, -0.62) * exp(1.32 * ($fourragesUFL / $fourragesUE))
            : 1.0;

        return -0.43 + ($sgE * 1.82) + (0.035 * $concentresMS) - (0.00053 * $laitObjectif * $concentresMS);
    }

    public static function calculerVEC(Ration $ration): float
    {
        $ueFourrage = self::calculerApportFourragesUE($ration);
        $fourragesMS = self::calculerApportFourragesMS($ration);
        $concentresMS = self::calculerApportConcentresMS($ration);
        $vef = $fourragesMS > 0 ? $ueFourrage / $fourragesMS : 0.0;

        return self::calculerSg($ration) * $concentresMS * $vef;
    }

    public static function calculerApportTotalUE(Ration $ration): float
    {
        return self::calculerApportFourragesUE($ration) + self::calculerVEC($ration);
    }

    public static function calculerApportFourragesUF(Ration $ration): float
    {
        return self::sumProperty($ration, 'ufl2007', 'Fourrage');
    }

    public static function calculerApportFourragesUFL(Ration $ration): float
    {
        return self::calculerApportFourragesUF($ration);
    }

    public static function calculerApportConcentresUF(Ration $ration): float
    {
        return self::sumProperty($ration, 'ufl2007', 'Conc');
    }

    public static function calculerApportConcentresUFL(Ration $ration): float
    {
        return self::calculerApportConcentresUF($ration);
    }

    public static function calculerApportTotalUF(Ration $ration): float
    {
        return self::calculerApportFourragesUF($ration) + self::calculerApportConcentresUF($ration);
    }

    public static function calculerApportTotalPDIE(Ration $ration): float
    {
        return self::sumProperty($ration, 'pdie2007');
    }

    public static function calculerApportTotalPDIN(Ration $ration): float
    {
        return self::sumProperty($ration, 'pdin2007');
    }

    public static function calculerApportFourragesMAT(Ration $ration): float
    {
        return self::sumProperty($ration, 'mat', 'Fourrage');
    }

    public static function calculerApportCB(Ration $ration): float
    {
        return self::sumProperty($ration, 'cb');
    }

    public static function calculerApportAmidon(Ration $ration): float
    {
        return self::sumProperty($ration, 'amidon');
    }

    public static function calculerApportADF(Ration $ration): float
    {
        return self::sumProperty($ration, 'adf');
    }

    public static function calculerApportNDF(Ration $ration): float
    {
        return self::sumProperty($ration, 'ndf');
    }

    public static function calculerApportCa(Ration $ration): float
    {
        return self::sumProperty($ration, 'ca');
    }

    public static function calculerApportCaabs(Ration $ration): float
    {
        return self::sumSpecialProperty($ration, 'caabs', 'ca');
    }

    public static function calculerApportP(Ration $ration): float
    {
        return self::sumProperty($ration, 'p');
    }

    public static function calculerApportPabs(Ration $ration): float
    {
        return self::sumSpecialProperty($ration, 'pabs', 'p');
    }

    public static function calculerApportMg(Ration $ration): float
    {
        return self::sumProperty($ration, 'mg');
    }

    public static function calculerApportMgabs(Ration $ration): float
    {
        $apportK = self::calculerApportK($ration);
        $apportTotalMS = self::calculerApportTotalMS($ration);
        $apportMg = self::calculerApportMg($ration);

        return $apportTotalMS > 0 ? $apportMg * (0.264 - (0.003 * ($apportK / $apportTotalMS))) : 0.0;
    }

    public static function calculerApportK(Ration $ration): float
    {
        return self::sumProperty($ration, 'k');
    }

    public static function calculerApportNa(Ration $ration): float
    {
        return self::sumProperty($ration, 'na');
    }

    public static function calculerApportCl(Ration $ration): float
    {
        return self::sumProperty($ration, 'cl');
    }

    public static function calculerApportS(Ration $ration): float
    {
        return self::sumProperty($ration, 's');
    }

    public static function calculerApportCo(Ration $ration): float
    {
        return self::sumProperty($ration, 'co');
    }

    public static function calculerApportCu(Ration $ration): float
    {
        return self::sumProperty($ration, 'cu');
    }

    public static function calculerApportI(Ration $ration): float
    {
        return self::sumProperty($ration, 'i');
    }

    public static function calculerApportMn(Ration $ration): float
    {
        return self::sumProperty($ration, 'mn');
    }

    public static function calculerApportSe(Ration $ration): float
    {
        return self::sumProperty($ration, 'se');
    }

    public static function calculerApportZn(Ration $ration): float
    {
        return self::sumProperty($ration, 'zn');
    }

    public static function calculerApportUFLParKgMS(Ration $ration): float
    {
        return self::divideByMs($ration, self::calculerApportTotalUF($ration));
    }

    public static function calculerApportPDIEParKgMS(Ration $ration): float
    {
        return self::divideByMs($ration, self::calculerApportTotalPDIE($ration));
    }

    public static function calculerApportPDINParKgMS(Ration $ration): float
    {
        return self::divideByMs($ration, self::calculerApportTotalPDIN($ration));
    }

    public static function calculerApportCBParKgMS(Ration $ration): float
    {
        return self::divideByMs($ration, self::calculerApportCB($ration));
    }

    public static function calculerApportAmidonParKgMS(Ration $ration): float
    {
        return self::divideByMs($ration, self::calculerApportAmidon($ration));
    }

    public static function calculerApportADFParKgMS(Ration $ration): float
    {
        return self::divideByMs($ration, self::calculerApportADF($ration));
    }

    public static function calculerApportNDFParKgMS(Ration $ration): float
    {
        return self::divideByMs($ration, self::calculerApportNDF($ration));
    }

    public static function calculerCoutParAnimal(Ration $ration): float
    {
        $total = 0.0;

        foreach (self::getIngredients($ration) as $item) {
            $total += $item['qty_mb'] * (float) ($item['aliment']->prix ?? 0);
        }

        return $total;
    }

    private static function sumQtyMs(Ration $ration, ?string $type = null): float
    {
        $total = 0.0;

        foreach (self::getIngredients($ration) as $item) {
            if ($type !== null && $item['type'] !== $type) {
                continue;
            }

            $total += $item['qty_ms'];
        }

        return $total;
    }

    private static function sumQtyMb(Ration $ration): float
    {
        $total = 0.0;

        foreach (self::getIngredients($ration) as $item) {
            $total += $item['qty_mb'];
        }

        return $total;
    }

    private static function sumProperty(Ration $ration, string $property, ?string $type = null): float
    {
        $total = 0.0;

        foreach (self::getIngredients($ration) as $item) {
            if ($type !== null && $item['type'] !== $type) {
                continue;
            }

            $total += $item['qty_ms'] * (float) ($item['aliment']->{$property} ?? 0);
        }

        return $total;
    }

    private static function sumSpecialProperty(Ration $ration, string $property, string $fallbackProperty): float
    {
        $total = 0.0;

        foreach (self::getIngredients($ration) as $item) {
            $value = $item['aliment']->{$property};
            if ($value === null) {
                $value = $item['type'] === 'Mineral'
                    ? (float) ($item['aliment']->{$fallbackProperty} ?? 0) * 0.4
                    : 0.0;
            }

            $total += $item['qty_ms'] * (float) $value;
        }

        return $total;
    }

    private static function divideByMs(Ration $ration, float $value): float
    {
        $apportMS = self::calculerApportTotalMS($ration);

        return $apportMS > 0 ? $value / $apportMS : 0.0;
    }
}
