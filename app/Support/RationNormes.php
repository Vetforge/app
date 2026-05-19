<?php

namespace App\Support;

use App\Models\User;

final class RationNormes
{
    /**
     * @var array<string, array{
     *     label: string,
     *     group: string,
     *     unit: string|null,
     *     decimals: int,
     *     default_min: float|int|null,
     *     default_max: float|int|null
     * }>
     */
    private const DEFINITIONS = [
        'eff_pdi' => [
            'label' => 'Efficacité des PDI',
            'group' => 'Protéines',
            'unit' => '%',
            'decimals' => 0,
            'default_min' => 85,
            'default_max' => null,
        ],
        'bpr' => [
            'label' => 'BPR',
            'group' => 'Protéines',
            'unit' => 'g/kg MS',
            'decimals' => 2,
            'default_min' => 0,
            'default_max' => 3,
        ],
        'be' => [
            'label' => 'Bilan électrolytique',
            'group' => 'Santé ruminale',
            'unit' => 'mEq/kg MS',
            'decimals' => 0,
            'default_min' => 200,
            'default_max' => 250,
        ],
        'amid_ru' => [
            'label' => 'Amidon digestible dans le rumen',
            'group' => 'Santé ruminale',
            'unit' => 'g/kg MS',
            'decimals' => 0,
            'default_min' => 200,
            'default_max' => 250,
        ],
        'pco_percent' => [
            'label' => 'Proportion de concentré',
            'group' => 'Santé ruminale',
            'unit' => '% MS',
            'decimals' => 0,
            'default_min' => 40,
            'default_max' => 50,
        ],
        'ndf_total' => [
            'label' => 'NDF total',
            'group' => 'Fibres et structure',
            'unit' => 'g/kg MS',
            'decimals' => 0,
            'default_min' => 300,
            'default_max' => 350,
        ],
        'ira' => [
            'label' => "Indice de risque d'acidose",
            'group' => 'Santé ruminale',
            'unit' => null,
            'decimals' => 2,
            'default_min' => 0.8,
            'default_max' => 1.2,
        ],
        'ph_ruminal' => [
            'label' => 'pH ruminal estimé via AmiD_ru',
            'group' => 'Santé ruminale',
            'unit' => null,
            'decimals' => 2,
            'default_min' => 6.2,
            'default_max' => null,
        ],
        'cb_par_kg_ms' => [
            'label' => 'Apport en CB',
            'group' => 'Fibres et structure',
            'unit' => 'g/kg MS',
            'decimals' => 0,
            'default_min' => 170,
            'default_max' => null,
        ],
        'bil_ufl' => [
            'label' => 'Bilan UFL',
            'group' => 'Énergie',
            'unit' => 'UFL/j',
            'decimals' => 2,
            'default_min' => 0,
            'default_max' => null,
        ],
    ];

    /**
     * @return array<string, array{
     *     label: string,
     *     group: string,
     *     unit: string|null,
     *     decimals: int,
     *     default_min: float|int|null,
     *     default_max: float|int|null
     * }>
     */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @return array<string, array{min: float|int|null, max: float|int|null}>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::definitions() as $key => $definition) {
            $defaults[$key] = [
                'min' => $definition['default_min'],
                'max' => $definition['default_max'],
            ];
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>|null  $storedOverrides
     * @return array<string, array{min: float|int|null, max: float|int|null}>
     */
    public static function active(?array $storedOverrides = null): array
    {
        $active = self::defaults();

        foreach (self::definitions() as $key => $definition) {
            $override = $storedOverrides[$key] ?? null;

            if (! is_array($override)) {
                continue;
            }

            $active[$key] = [
                'min' => self::supportsMin($definition)
                    ? self::normalizeMetricValue($key, $override['min'] ?? $active[$key]['min'])
                    : null,
                'max' => self::supportsMax($definition)
                    ? self::normalizeMetricValue($key, $override['max'] ?? $active[$key]['max'])
                    : null,
            ];
        }

        return $active;
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     group: string,
     *     unit: string|null,
     *     decimals: int,
     *     default_min: float|int|null,
     *     default_max: float|int|null
     * }>
     */
    public static function editable(): array
    {
        $editable = [];

        foreach (self::definitions() as $key => $definition) {
            $editable[] = [
                'key' => $key,
                'label' => $definition['label'],
                'group' => $definition['group'],
                'unit' => $definition['unit'],
                'decimals' => $definition['decimals'],
                'default_min' => $definition['default_min'],
                'default_max' => $definition['default_max'],
            ];
        }

        return $editable;
    }

    /**
     * @param  array<string, mixed>|null  $storedOverrides
     * @return array{
     *     active: array<string, array{min: float|int|null, max: float|int|null}>,
     *     editable: array<int, array{
     *         key: string,
     *         label: string,
     *         group: string,
     *         unit: string|null,
     *         decimals: int,
     *         default_min: float|int|null,
     *         default_max: float|int|null
     *     }>
     * }
     */
    public static function payload(?array $storedOverrides = null): array
    {
        return [
            'active' => self::active($storedOverrides),
            'editable' => self::editable(),
        ];
    }

    /**
     * @return array{
     *     active: array<string, array{min: float|int|null, max: float|int|null}>,
     *     editable: array<int, array{
     *         key: string,
     *         label: string,
     *         group: string,
     *         unit: string|null,
     *         decimals: int,
     *         default_min: float|int|null,
     *         default_max: float|int|null
     *     }>
     * }
     */
    public static function payloadForUser(?User $user): array
    {
        /** @var array<string, mixed>|null $storedOverrides */
        $storedOverrides = $user?->normes_personnalisees;

        return self::payload($storedOverrides);
    }

    /**
     * @param  array<string, mixed>  $activeNormes
     * @return array<string, array{min: float|int|null, max: float|int|null}>|null
     */
    public static function storeableOverrides(array $activeNormes): ?array
    {
        $defaults = self::defaults();
        $overrides = [];

        foreach (self::definitions() as $key => $definition) {
            $activeNorme = $activeNormes[$key] ?? [];

            if (! is_array($activeNorme)) {
                $activeNorme = [];
            }

            $normalized = [
                'min' => self::supportsMin($definition)
                    ? self::normalizeMetricValue($key, $activeNorme['min'] ?? $defaults[$key]['min'])
                    : null,
                'max' => self::supportsMax($definition)
                    ? self::normalizeMetricValue($key, $activeNorme['max'] ?? $defaults[$key]['max'])
                    : null,
            ];

            if ($normalized !== $defaults[$key]) {
                $overrides[$key] = $normalized;
            }
        }

        return $overrides === [] ? null : $overrides;
    }

    /**
     * @param  array{
     *     label: string,
     *     group: string,
     *     unit: string|null,
     *     decimals: int,
     *     default_min: float|int|null,
     *     default_max: float|int|null
     * }  $definition
     */
    private static function supportsMin(array $definition): bool
    {
        return $definition['default_min'] !== null;
    }

    /**
     * @param  array{
     *     label: string,
     *     group: string,
     *     unit: string|null,
     *     decimals: int,
     *     default_min: float|int|null,
     *     default_max: float|int|null
     * }  $definition
     */
    private static function supportsMax(array $definition): bool
    {
        return $definition['default_max'] !== null;
    }

    private static function normalizeMetricValue(string $key, mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $definition = self::definitions()[$key];
        $rounded = round((float) $value, $definition['decimals']);

        if ($definition['decimals'] === 0) {
            return (int) $rounded;
        }

        return $rounded;
    }
}
