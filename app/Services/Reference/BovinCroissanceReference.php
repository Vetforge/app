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
    /** Les 14 animaux de référence des Tables 19.2 et 19.3. */
    private const REFERENCES = [
        1 => ['bw_initial' => 300, 'bw_final' => 800, 'dc_initial' => 16, 'dc_final' => 11, 'lip_initial' => 8, 'lip_final' => 16, 'ne_maint' => 102, 'd1' => 1.1028, 'd2' => 0.0037, 'i_type' => 0.284, 'c' => 0.54],
        2 => ['bw_initial' => 300, 'bw_final' => 730, 'dc_initial' => 15, 'dc_final' => 10.8, 'lip_initial' => 8, 'lip_final' => 15, 'ne_maint' => 98, 'd1' => 1.1028, 'd2' => 0.0037, 'i_type' => 0.220, 'c' => 0.57],
        3 => ['bw_initial' => 250, 'bw_final' => 680, 'dc_initial' => 18, 'dc_final' => 12, 'lip_initial' => 10, 'lip_final' => 19.5, 'ne_maint' => 100, 'd1' => 1.4078, 'd2' => 0.003763, 'i_type' => 0.233, 'c' => 0.575],
        4 => ['bw_initial' => 200, 'bw_final' => 550, 'dc_initial' => 20, 'dc_final' => 12, 'lip_initial' => 7.5, 'lip_final' => 21, 'ne_maint' => 95, 'd1' => 1.4078, 'd2' => 0.003763, 'i_type' => 0.157, 'c' => 0.652],
        5 => ['bw_initial' => 500, 'bw_final' => 750, 'dc_initial' => 12, 'dc_final' => 10, 'lip_initial' => 11, 'lip_final' => 16.5, 'ne_maint' => 106, 'd1' => 0.629, 'd2' => 0.0035, 'i_type' => 0.2205, 'c' => 0.6],
        6 => ['bw_initial' => 540, 'bw_final' => 660, 'dc_initial' => 13, 'dc_final' => 11, 'lip_initial' => 16, 'lip_final' => 21.2, 'ne_maint' => 108, 'd1' => 0.629, 'd2' => 0.0035, 'i_type' => 0.2425, 'c' => 0.6],
        7 => ['bw_initial' => 450, 'bw_final' => 700, 'dc_initial' => 13, 'dc_final' => 10, 'lip_initial' => 13, 'lip_final' => 18, 'ne_maint' => 106, 'd1' => 0.6174, 'd2' => 0.0035, 'i_type' => 0.2205, 'c' => 0.6],
        8 => ['bw_initial' => 450, 'bw_final' => 650, 'dc_initial' => 19.8, 'dc_final' => 15.5, 'lip_initial' => 16, 'lip_final' => 31, 'ne_maint' => 110, 'd1' => 0.66114, 'd2' => 0.004, 'i_type' => 0.2261, 'c' => 0.6],
        9 => ['bw_initial' => 400, 'bw_final' => 600, 'dc_initial' => 21.2, 'dc_final' => 16.2, 'lip_initial' => 14.7, 'lip_final' => 29, 'ne_maint' => 110, 'd1' => 0.7439, 'd2' => 0.0035, 'i_type' => 0.2261, 'c' => 0.6],
        10 => ['bw_initial' => 300, 'bw_final' => 540, 'dc_initial' => 16, 'dc_final' => 12, 'lip_initial' => 8, 'lip_final' => 11, 'ne_maint' => 94, 'd1' => 1.114, 'd2' => 0.0025, 'i_type' => 0.03459, 'c' => 0.9],
        11 => ['bw_initial' => 200, 'bw_final' => 520, 'dc_initial' => 20, 'dc_final' => 13, 'lip_initial' => 7.5, 'lip_final' => 18, 'ne_maint' => 94, 'd1' => 1.5013, 'd2' => 0.0025, 'i_type' => 0.03915, 'c' => 0.9],
        12 => ['bw_initial' => 300, 'bw_final' => 550, 'dc_initial' => 18, 'dc_final' => 13, 'lip_initial' => 9, 'lip_final' => 14, 'ne_maint' => 94, 'd1' => 1.114, 'd2' => 0.0025, 'i_type' => 0.03459, 'c' => 0.9],
        13 => ['bw_initial' => 200, 'bw_final' => 480, 'dc_initial' => 20, 'dc_final' => 13, 'lip_initial' => 9, 'lip_final' => 21, 'ne_maint' => 94, 'd1' => 1.5013, 'd2' => 0.0025, 'i_type' => 0.03915, 'c' => 0.9],
        14 => ['bw_initial' => 200, 'bw_final' => 350, 'dc_initial' => 20, 'dc_final' => 19.3, 'lip_initial' => 7.5, 'lip_final' => 18.9, 'ne_maint' => 94, 'd1' => 1.06061, 'd2' => 0.002, 'i_type' => 0.03960, 'c' => 0.9],
    ];

    /** Jeunes bovins à l'engraissement (système UFV — Table 19.2 cat. 1-4, CI Table 19.3). */
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

    /** Bovins en croissance (système UFL — Table 19.2 cat. 10-13, CI Table 19.3). */
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
    public static function resolve(?string $race, bool $finition, ?int $reference = null): array
    {
        if ($reference !== null) {
            $valid = $finition ? range(1, 9) : range(10, 14);
            if (! in_array($reference, $valid, true)) {
                throw new \InvalidArgumentException("Référence bovine INRA incompatible : {$reference}");
            }

            return self::REFERENCES[$reference];
        }

        $table = $finition ? self::FINITION : self::CROISSANCE;
        // Compatibilité des anciennes rations enregistrées avant l'ajout du champ de référence.
        // Les nouvelles saisies exigent reference_bovine ; une race explicitement inconnue reste rejetée.
        if ($race === null || trim($race) === '') {
            return $table['charolaise'];
        }
        $key = self::normaliserRace($race);

        if (! isset($table[$key])) {
            throw new \InvalidArgumentException('Race bovine inconnue : une référence INRA explicite est requise.');
        }

        return $table[$key];
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
            str_contains($n, 'charolais'), str_contains($n, 'blonde') => 'charolaise',
            default => '',
        };
    }
}
