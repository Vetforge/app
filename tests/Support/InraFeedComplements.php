<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/** Compléments figés, utilisables sans PostgreSQL et sans modifier l'oracle Larelev. */
final class InraFeedComplements
{
    /** @return array<string, mixed> */
    public static function catalogue(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/inra-feed-complements.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $feed
     * @return array<string, float|null>
     */
    public static function attributes(array $feed): array
    {
        $original = ReferenceRations::attributes($feed);
        $catalogue = self::catalogue();
        $match = $catalogue['matches'][$feed['name']] ?? null;
        if ($match === null) {
            return $original;
        }

        $supplement = isset($match['profile'])
            ? $catalogue['profiles'][$match['profile']]['attributes']
            : self::recipeAttributes($match['recipe'], $catalogue['profiles']);

        return array_replace($supplement, array_filter($original, fn (?float $value): bool => $value !== null));
    }

    /** @param array{int, int, int} $percentages
     * @return array<string, int|float>
     */
    public static function automaticMineral(array $percentages): array
    {
        $catalogue = self::catalogue();
        $key = implode('/', $percentages);
        $match = $catalogue['automatic_minerals'][$key] ?? throw new RuntimeException('AMV non rapproché : '.$key);
        $attributes = $catalogue['profiles'][$match['profile']]['attributes'];
        [$p, $ca, $mg] = $percentages;
        $fraction = $attributes['ms'] / 100;

        return array_replace($attributes, ['p' => $p * 10.0 / $fraction, 'ca' => $ca * 10.0 / $fraction, 'mg' => $mg * 10.0 / $fraction]);
    }

    /**
     * Recette brut/brut : concentrations massiques pondérées par la MS, dMO par la MO,
     * DT6_N par la MAT, DT6_Ami par l'amidon, dr_N par la MAT non dégradée à DT6_N, Lys/Met par les PDI.
     * L'agrégation reste une approximation des interactions entre ingrédients individuels.
     *
     * @param  array<string, float>  $recipe
     * @param  array<string, array<string, mixed>>  $profiles
     * @return array<string, float>
     */
    private static function recipeAttributes(array $recipe, array $profiles): array
    {
        if (abs(array_sum($recipe) - 100) > 1e-8) {
            throw new RuntimeException('La recette de référence doit totaliser 100 % brut/brut.');
        }

        $totalMs = 0.0;
        $parts = [];
        foreach ($recipe as $code => $percent) {
            $attributes = $profiles[$code]['attributes'];
            $weight = $percent / 100 * $attributes['ms'] / 100;
            $totalMs += $weight;
            $parts[] = ['weight' => $weight, 'attributes' => $attributes];
        }

        $result = ['ms' => $totalMs * 100];
        $commonFields = array_keys($parts[0]['attributes']);
        foreach ($parts as $part) {
            $commonFields = array_intersect($commonFields, array_keys($part['attributes']));
        }

        foreach ($commonFields as $field) {
            if ($field === 'ms') {
                continue;
            }
            $numerator = $denominator = 0.0;
            foreach ($parts as $part) {
                $attributes = $part['attributes'];
                $factor = match ($field) {
                    'd_mo' => $attributes['mo'],
                    'dt6_n' => $attributes['mat'],
                    'dt6_ami' => $attributes['amidon'],
                    'dr_n' => $attributes['mat'] * (1 - $attributes['dt6_n'] / 100),
                    'lys_di', 'met_di' => $attributes['pdi'],
                    default => 1.0,
                };
                $weight = $part['weight'] * $factor;
                $numerator += $weight * $attributes[$field];
                $denominator += $weight;
            }
            if ($denominator > 0) {
                $result[$field] = $numerator / $denominator;
            }
        }

        return $result;
    }
}
