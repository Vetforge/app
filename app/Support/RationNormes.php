<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CategorieAnimal;
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
            'default_min' => null,
            'default_max' => null,
        ],
        'bpr' => [
            'label' => 'BPR',
            'group' => 'Protéines',
            'unit' => 'g/kg MS',
            'decimals' => 2,
            'default_min' => null,
            'default_max' => null,
        ],
        'be' => [
            'label' => 'Bilan électrolytique',
            'group' => 'Santé ruminale',
            'unit' => 'mEq/kg MS',
            'decimals' => 0,
            'default_min' => null,
            'default_max' => null,
        ],
        'amid_ru' => [
            'label' => 'Amidon digestible dans le rumen',
            'group' => 'Santé ruminale',
            'unit' => 'g/kg MS',
            'decimals' => 0,
            'default_min' => null,
            'default_max' => null,
        ],
        'pco_percent' => [
            'label' => 'Proportion de concentré',
            'group' => 'Santé ruminale',
            'unit' => '% MS',
            'decimals' => 0,
            'default_min' => null,
            'default_max' => null,
        ],
        'ndf_total' => [
            'label' => 'NDF total',
            'group' => 'Fibres et structure',
            'unit' => 'g/kg MS',
            'decimals' => 0,
            'default_min' => null,
            'default_max' => null,
        ],
        'ira' => [
            'label' => "Indice de risque d'acidose",
            'group' => 'Santé ruminale',
            'unit' => null,
            'decimals' => 2,
            'default_min' => null,
            'default_max' => null,
        ],
        'ph_ruminal' => [
            'label' => 'pH ruminal estimé via AmiD_ru',
            'group' => 'Santé ruminale',
            'unit' => null,
            'decimals' => 2,
            'default_min' => null,
            'default_max' => null,
        ],
        'cb_par_kg_ms' => [
            'label' => 'Apport en CB',
            'group' => 'Fibres et structure',
            'unit' => 'g/kg MS',
            'decimals' => 0,
            'default_min' => null,
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
        $definitions = self::DEFINITIONS;
        foreach ($definitions as &$definition) {
            $definition['decimals'] = max(2, $definition['decimals']);
        }
        unset($definition);

        $additional = [
            'pdi_par_kg_ms' => ['PDI par kg de matière sèche', 'Protéines', 'g/kg MS', 2],
            'azote_urinaire' => ['Azote urinaire', 'Protéines', 'g/j', 2],
            'azote_fecale' => ['Azote fécal', 'Protéines', 'g/j', 2],
            'rmic' => ['Rmic (INRA 2007)', 'Protéines', 'g PDI/UF', 2, 0],
            'mod_concentre' => ['MOD des concentrés (proxy interne)', 'Santé ruminale', 'g/kg MS', 2],
            'ndf_fourrages' => ['NDF des fourrages (proxy NDFfo)', 'Fibres et structure', 'g/kg MS', 2],
            'ms' => ['Matière sèche ingérée', 'Fibres et structure', 'kg MS/j', 2],
            'adf_par_kg_ms' => ['ADF', 'Fibres et structure', 'g/kg MS', 2],
            'ufl_par_kg_ms' => ['Densité énergétique (UFL ou UFV selon la catégorie)', 'Énergie', 'UF/kg MS', 3],
            'variation_poids_energie' => ['Variation de poids estimée (énergie)', 'Énergie', 'kg/j', 3],
            'ch4' => ['Production de méthane', 'Énergie', 'g/j', 2],
            'baca' => ['BACA', 'Minéraux et vitamines', 'mEq/kg MS', 2],
            'vit_a' => ['Supplémentation vitamine A', 'Minéraux et vitamines', 'UI/j', 2],
            'vit_d' => ['Supplémentation vitamine D', 'Minéraux et vitamines', 'UI/j', 2],
            'vit_e' => ['Supplémentation vitamine E', 'Minéraux et vitamines', 'UI/j', 2],
            'prod_agvt_jour' => ['Production totale d’AGV dans le rumen', 'Fermentation ruminale', 'mol/j', 2],
            'acetate' => ['Acide acétique', 'Fermentation ruminale', 'mol/100 mol AGV', 2],
            'propionate' => ['Acide propionique', 'Fermentation ruminale', 'mol/100 mol AGV', 2],
            'butyrate' => ['Acide butyrique', 'Fermentation ruminale', 'mol/100 mol AGV', 2],
            'ira_be' => ['Bilan électrolytique — paliers du score IRA', 'Barème de calcul de l’IRA', 'mEq/kg MS', 2, 200, 250],
            'ira_amid_ru' => ['Amidon ruminal — paliers du score IRA', 'Barème de calcul de l’IRA', 'g/kg MS', 2, 200, 250],
            'ira_pco_percent' => ['Concentrés — paliers du score IRA', 'Barème de calcul de l’IRA', '% MS', 2, 40, 50],
            'ira_ndf_fourrages' => ['NDF des fourrages — paliers du score IRA', 'Barème de calcul de l’IRA', 'g/kg MS', 2, 250, 300],
            'ira_ndf_total' => ['NDF total — paliers du score IRA', 'Barème de calcul de l’IRA', 'g/kg MS', 2, 300, 350],
        ];

        foreach (['uf' => 'Énergie (UFL ou UFV)', 'pdi' => 'Protéines (PDI, PDIE/PDIN)', 'ue' => 'Ingestion', 'lait' => 'Objectif laitier'] as $key => $label) {
            $additional['couverture_'.$key] = [$label.' — plage cible', 'Couverture des besoins', '% du besoin ou de l’objectif', 2, 100, $key === 'lait' ? null : 115];
            $additional['alerte_couverture_'.$key] = [$label.' — limites d’alerte prioritaire', 'Couverture des besoins', '% du besoin ou de l’objectif', 2, 95];
        }

        foreach (self::minerals() as $key => $label) {
            $additional['couverture_'.$key] = [$label.' — couverture du besoin', 'Minéraux et oligoéléments', '% du besoin', 2, 95, 140];
        }

        foreach (['uf' => 'Énergie', 'ue' => 'Encombrement', 'pdi' => 'PDI / PDIE', 'caabs' => 'Calcium absorbable', 'pabs' => 'Phosphore absorbable'] as $key => $label) {
            $additional['bilan_'.$key] = [$label.' — couverture dans le tableau des bilans', 'Bilans apports / besoins', '% du besoin', 2, 90, 110];
        }

        foreach ($additional as $key => $definition) {
            $definitions[$key] = [
                'label' => $definition[0],
                'group' => $definition[1],
                'unit' => $definition[2],
                'decimals' => $definition[3],
                'default_min' => $definition[4] ?? null,
                'default_max' => $definition[5] ?? null,
            ];
        }

        return $definitions;
    }

    /** @return array<string, string> */
    public static function minerals(): array
    {
        return ['caabs' => 'Calcium absorbable', 'pabs' => 'Phosphore absorbable', 'mgabs' => 'Magnésium absorbable', 'na' => 'Sodium', 'cl' => 'Chlore', 'k' => 'Potassium', 's' => 'Soufre', 'co' => 'Cobalt', 'se' => 'Sélénium', 'zn' => 'Zinc', 'mn' => 'Manganèse', 'cu' => 'Cuivre', 'i' => 'Iode', 'molybdene' => 'Molybdène'];
    }

    /** @return array{slug: string, label: string, short_label: string, description: string, type: string} */
    public static function module(): array
    {
        return ['slug' => 'ration', 'label' => 'Ration', 'short_label' => 'Ration', 'description' => 'Normes et bornes de lecture des rations INRA 2007 et 2018.', 'type' => 'ration'];
    }

    /**
     * @param  array<string, float>  $values
     * @param  array<string, array{min: float|int|null, max: float|int|null}>|null  $normes
     */
    public static function ira(array $values, ?array $normes = null): float
    {
        $normes ??= self::defaults();
        $score = 0;

        foreach (['be', 'amid_ru', 'pco_percent', 'ndf_fourrages', 'ndf_total'] as $key) {
            $bound = $normes['ira_'.$key] ?? ['min' => null, 'max' => null];
            $highRisk = in_array($key, ['amid_ru', 'pco_percent'], true);
            foreach (['min', 'max'] as $side) {
                if ($bound[$side] !== null && ($highRisk ? $values[$key] >= $bound[$side] : $values[$key] < $bound[$side])) {
                    $score++;
                }
            }
        }

        return $score / 5;
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
    public static function active(?array $storedOverrides = null, CategorieAnimal $categorie = CategorieAnimal::VacheLaitiere): array
    {
        // Les anciennes préférences communes restent lisibles jusqu'à leur première
        // modification, qui les répartit entre les catégories sans perte de réglages.
        if (isset($storedOverrides['categories']) && is_array($storedOverrides['categories'])) {
            $storedOverrides = $storedOverrides['categories'][$categorie->value] ?? [];
        }

        $active = self::defaults();

        foreach (self::definitions() as $key => $definition) {
            $override = $storedOverrides[$key] ?? null;

            if (! is_array($override)) {
                continue;
            }

            $active[$key] = [
                'min' => self::supportsMin($key)
                    ? self::normalizeMetricValue($key, array_key_exists('min', $override) ? $override['min'] : $active[$key]['min'])
                    : null,
                'max' => self::supportsMax($key)
                    ? self::normalizeMetricValue($key, array_key_exists('max', $override) ? $override['max'] : $active[$key]['max'])
                    : null,
            ];
        }

        return $active;
    }

    /**
     * @param  array<string, mixed>|null  $storedOverrides
     * @param  array<string, mixed>  $activeNormes
     * @return array<string, mixed>|null
     */
    public static function storeForCategory(?array $storedOverrides, CategorieAnimal $categorie, array $activeNormes): ?array
    {
        $categories = [];

        foreach (CategorieAnimal::cases() as $category) {
            $overrides = self::storeableOverrides($category === $categorie
                ? $activeNormes
                : self::active($storedOverrides, $category));

            if ($overrides !== null) {
                $categories[$category->value] = $overrides;
            }
        }

        return $categories === [] ? null : ['categories' => $categories];
    }

    /**
     * @return array<string, mixed>
     */
    public static function settingsPayloadForUser(?User $user, CategorieAnimal $categorie): array
    {
        $payload = self::payloadForUser($user, categorie: $categorie);
        $payload['categorie_animal'] = $categorie->value;
        $payload['categories'] = CategorieAnimal::optionsGroupedBySpecies();

        foreach (CategorieAnimal::cases() as $category) {
            $payload['profiles'][$category->value] = self::active($user?->normes_personnalisees, $category);
        }

        return $payload;
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     supports_min: bool,
     *     supports_max: bool,
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
                'supports_min' => self::supportsMin($key),
                'supports_max' => self::supportsMax($key),
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
     *         supports_min: bool,
     *         supports_max: bool,
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
     *         supports_min: bool,
     *         supports_max: bool,
     *         label: string,
     *         group: string,
     *         unit: string|null,
     *         decimals: int,
     *         default_min: float|int|null,
     *         default_max: float|int|null
     *     }>
     * }
     */
    public static function payloadForUser(?User $user, ?array $resultats = null, ?float $objectifLait = null, CategorieAnimal $categorie = CategorieAnimal::VacheLaitiere): array
    {
        /** @var array<string, mixed>|null $storedOverrides */
        $storedOverrides = $user?->normes_personnalisees;

        $payload = [
            'active' => self::active($storedOverrides, $categorie),
            'editable' => self::editable(),
        ];

        if ($resultats !== null) {
            $payload['statuses'] = self::statuses($resultats, $payload['active'], $objectifLait);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $resultats
     * @param  array<string, array{min: float|int|null, max: float|int|null}>  $normes
     * @return array<string, string>
     */
    public static function statuses(array $resultats, array $normes, ?float $objectifLait = null): array
    {
        $values = $resultats['indicateurs'] ?? [];
        $values['eff_pdi'] = isset($values['eff_pdi']) ? $values['eff_pdi'] * 100 : null;
        $values['ms'] = $resultats['apports']['ms'] ?? null;

        foreach (['bil_ufl', 'rmic', 'variation_poids_energie', 'ch4'] as $key) {
            $values[$key] = $resultats['impacts'][$key] ?? null;
        }

        foreach (['vit_a', 'vit_d', 'vit_e'] as $key) {
            $values[$key] = $resultats['supplementations'][$key] ?? null;
        }

        $apports = $resultats['apports'] ?? [];
        $besoins = $resultats['besoins'] ?? [];
        $protein = ($resultats['inra'] ?? '2018') === '2007'
            ? (isset($apports['pdie'], $apports['pdin']) ? min($apports['pdie'], $apports['pdin']) : null)
            : ($apports['pdi'] ?? null);
        $milkCandidates = array_filter([
            $resultats['impacts']['lait_par_ufl'] ?? null,
            $resultats['impacts']['lait_par_pdi'] ?? null,
            $resultats['impacts']['lait_par_pdie'] ?? null,
            $resultats['impacts']['lait_par_pdin'] ?? null,
        ], static fn ($value): bool => is_numeric($value));
        $ratios = [
            'uf' => [$apports['uf'] ?? $apports['ufl'] ?? null, $besoins['uf_total'] ?? null],
            'pdi' => [$protein, $besoins['pdi_total'] ?? null],
            'ue' => [$apports['ue'] ?? null, $besoins['ci'] ?? null],
            'lait' => [$resultats['impacts']['production_lait_attendue'] ?? $resultats['impacts']['lait_limitant'] ?? ($milkCandidates === [] ? null : min($milkCandidates)), $objectifLait],
        ];

        foreach (self::minerals() as $key => $label) {
            $ratios[$key] = [$apports[$key] ?? ($key === 'pabs' ? ($apports['p'] ?? null) : null), $besoins[$key] ?? null];
        }

        foreach ($ratios as $key => [$apport, $besoin]) {
            $value = is_numeric($apport) && is_numeric($besoin) && $besoin > 0 ? 100 * $apport / $besoin : null;
            $values['couverture_'.$key] = $value;
            $values['alerte_couverture_'.$key] = $value;
            $values['bilan_'.$key] = $value;
        }

        if (($resultats['inra'] ?? '2018') === '2007') {
            $values['bilan_pdi'] = isset($apports['pdie'], $besoins['pdi_total']) && $besoins['pdi_total'] > 0
                ? 100 * $apports['pdie'] / $besoins['pdi_total']
                : null;
        }

        $statuses = [];
        foreach (self::definitions() as $key => $definition) {
            $value = $values[$key] ?? null;
            $bound = $normes[$key] ?? ['min' => null, 'max' => null];
            $outsideMin = $value !== null && $bound['min'] !== null && $value < $bound['min'];
            $outsideMax = $value !== null && $bound['max'] !== null && $value > $bound['max'];
            $status = $value === null || ($bound['min'] === null && $bound['max'] === null)
                ? 'neutral'
                : (($outsideMin || $outsideMax) ? 'alert' : 'ok');

            if (str_starts_with($key, 'couverture_') || str_starts_with($key, 'bilan_')) {
                if ($outsideMax || ($outsideMin && array_key_exists('alerte_'.$key, $normes))) {
                    $status = 'watch';
                }
            }

            $statuses[$key] = $status;
        }

        foreach (['uf', 'pdi', 'ue', 'lait'] as $key) {
            if ($statuses['alerte_couverture_'.$key] === 'alert') {
                $statuses['couverture_'.$key] = 'alert';
            } elseif ($statuses['couverture_'.$key] === 'neutral' && $statuses['alerte_couverture_'.$key] === 'ok') {
                $statuses['couverture_'.$key] = 'ok';
            }
        }

        if (($resultats['meta']['mineraux_valides'] ?? true) === false) {
            foreach (array_keys(self::minerals()) as $key) {
                $statuses['couverture_'.$key] = 'neutral';
                $statuses['bilan_'.$key] = 'neutral';
            }
        }

        return $statuses;
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
                'min' => self::supportsMin($key)
                    ? self::normalizeMetricValue($key, array_key_exists('min', $activeNorme) ? $activeNorme['min'] : $defaults[$key]['min'])
                    : null,
                'max' => self::supportsMax($key)
                    ? self::normalizeMetricValue($key, array_key_exists('max', $activeNorme) ? $activeNorme['max'] : $defaults[$key]['max'])
                    : null,
            ];

            $normalizedDefaults = [
                'min' => self::normalizeMetricValue($key, $defaults[$key]['min']),
                'max' => self::normalizeMetricValue($key, $defaults[$key]['max']),
            ];

            if ($normalized !== $normalizedDefaults) {
                $overrides[$key] = $normalized;
            }
        }

        return $overrides === [] ? null : $overrides;
    }

    private static function supportsMin(string $key): bool
    {
        // Toutes les métriques proposaient historiquement une borne basse. Les valeurs par
        // défaut ont été retirées car elles n'étaient pas des normes INRA universelles, mais
        // une préférence locale explicite de l'utilisateur reste autorisée.
        return array_key_exists($key, self::definitions());
    }

    private static function supportsMax(string $key): bool
    {
        return array_key_exists($key, self::definitions());
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
