<?php

declare(strict_types=1);

namespace App\Services\Reference;

use App\Services\RationHelper;

/**
 * Animaux de référence bovins en croissance/engraissement (INRA 2018, ch. 19).
 *
 * Regroupe les paramètres du modèle de composition du gain (Table 19.2) et les coefficients
 * d'allométrie de la capacité d'ingestion (Table 19.3), résolus par race et type de production.
 *
 * @phpstan-type ReferenceAnimal array{
 *     bw_initial: float, bw_final: float, dc_initial: float, dc_final: float,
 *     lip_initial: float, lip_final: float, ne_maint: float, d1: float, d2: float,
 *     i_type: float, c: float
 * }
 */
class BovinCroissanceReference
{
    /**
     * Jeunes bovins à l'engraissement (système UFV — Table 19.2 cat. 1-4, CI Table 19.3).
     *
     * @var array<string, array<string, float>>
     */
    private const FINITION = [
        // Charolais (réf.), Blonde d'Aquitaine — grand gabarit tardif (cat. 1).
        'charolaise' => ['bw_initial' => 300, 'bw_final' => 800, 'dc_initial' => 16, 'dc_final' => 11, 'lip_initial' => 8, 'lip_final' => 16, 'ne_maint' => 102, 'd1' => 1.1028, 'd2' => 0.0037, 'i_type' => 0.284, 'c' => 0.54],
        // Limousin — CI réduite (cat. 2).
        'limousine' => ['bw_initial' => 300, 'bw_final' => 730, 'dc_initial' => 15, 'dc_final' => 10.8, 'lip_initial' => 8, 'lip_final' => 15, 'ne_maint' => 98, 'd1' => 1.1028, 'd2' => 0.0037, 'i_type' => 0.220, 'c' => 0.57],
        // Salers, Aubrac — maturité moyenne (cat. 3).
        'salers' => ['bw_initial' => 250, 'bw_final' => 680, 'dc_initial' => 18, 'dc_final' => 12, 'lip_initial' => 10, 'lip_final' => 19.5, 'ne_maint' => 100, 'd1' => 1.4078, 'd2' => 0.003763, 'i_type' => 0.233, 'c' => 0.575],
        // Races laitières (cat. 4).
        'laitiere' => ['bw_initial' => 200, 'bw_final' => 550, 'dc_initial' => 20, 'dc_final' => 12, 'lip_initial' => 7.5, 'lip_final' => 21, 'ne_maint' => 95, 'd1' => 1.4078, 'd2' => 0.003763, 'i_type' => 0.157, 'c' => 0.652],
    ];

    /**
     * Bovins en croissance (système UFL — Table 19.2 cat. 10-13, CI Table 19.3).
     *
     * @var array<string, array<string, float>>
     */
    private const CROISSANCE = [
        // Mâles tardifs / génisses de renouvellement tardives, Charolais (cat. 10).
        'charolaise' => ['bw_initial' => 300, 'bw_final' => 540, 'dc_initial' => 16, 'dc_final' => 12, 'lip_initial' => 8, 'lip_final' => 11, 'ne_maint' => 94, 'd1' => 1.114, 'd2' => 0.0025, 'i_type' => 0.03459, 'c' => 0.90],
        // Idem, Limousin (CI réduite, cat. 10 low IC).
        'limousine' => ['bw_initial' => 300, 'bw_final' => 540, 'dc_initial' => 16, 'dc_final' => 12, 'lip_initial' => 8, 'lip_final' => 11, 'ne_maint' => 94, 'd1' => 1.114, 'd2' => 0.0025, 'i_type' => 0.03115, 'c' => 0.90],
        // Génisses laitières (cat. 13).
        'laitiere' => ['bw_initial' => 200, 'bw_final' => 480, 'dc_initial' => 20, 'dc_final' => 13, 'lip_initial' => 9, 'lip_final' => 21, 'ne_maint' => 94, 'd1' => 1.5013, 'd2' => 0.0025, 'i_type' => 0.03915, 'c' => 0.90],
    ];

    /**
     * Résout l'animal de référence pour une race et un type de production (engraissement/croissance).
     *
     * @return array<string, float>
     */
    public static function resolve(?string $race, bool $finition): array
    {
        $table = $finition ? self::FINITION : self::CROISSANCE;
        $key = self::normaliserRace($race);

        return $table[$key] ?? $table['charolaise'];
    }

    private static function normaliserRace(?string $race): string
    {
        $n = RationHelper::normalizeRace($race);

        return match (true) {
            str_contains($n, 'limousin') => 'limousine',
            str_contains($n, 'salers'), str_contains($n, 'aubrac') => 'salers',
            str_contains($n, 'laitiere'), str_contains($n, 'holstein'),
            str_contains($n, 'montbeliarde'), str_contains($n, 'normande'),
            str_contains($n, 'prim'), str_contains($n, 'croiselaitiere') => 'laitiere',
            default => 'charolaise', // Charolaise, Blonde et gabarits tardifs par défaut.
        };
    }
}
