<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserModuleSetting;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class VeterinaryModules
{
    /**
     * @var array<string, array{label: string, short_label: string, description: string, type: string}>
     */
    private const MODULES = [
        'coproscopie-parasitaire' => [
            'label' => 'Coproscopie parasitaire',
            'short_label' => 'Coproscopie',
            'description' => 'Lecture multi-echantillons des parasites digestifs et respiratoires.',
            'type' => 'analyse',
        ],
        'diarrhee-neonatale' => [
            'label' => 'Diarrhee neonatale',
            'short_label' => 'Diarrhee',
            'description' => 'Tests rapides et conseils preventifs/curatifs pour veaux nouveau-nes.',
            'type' => 'analyse',
        ],
        'gaz-du-sang' => [
            'label' => 'Gaz du sang',
            'short_label' => 'Gaz du sang',
            'description' => 'Interpretation acido-basique et plan de perfusion.',
            'type' => 'analyse',
        ],
        'comptage-cellulaire' => [
            'label' => 'Comptage cellulaire',
            'short_label' => 'Cellules',
            'description' => 'Saisie de comptages cellulaires sur plusieurs echantillons.',
            'type' => 'analyse',
        ],
        'diagnostic-bacteriologique' => [
            'label' => 'Diagnostic bacteriologique',
            'short_label' => 'Bacteriologie',
            'description' => 'Bacteriologie et antibiogramme avec interpretation S/I/R.',
            'type' => 'analyse',
        ],
        'analyse-diverse' => [
            'label' => 'Analyses diverses',
            'short_label' => 'Analyses diverses',
            'description' => 'Saisie libre de resultats d\'analyses de laboratoire.',
            'type' => 'analyse',
        ],
        'tests-rapides' => [
            'label' => 'Tests rapides',
            'short_label' => 'Tests rapides',
            'description' => 'Tests ELISA, biochimie rapide, bandelette et frottis sanguin.',
            'type' => 'analyse',
        ],
        'tests-biochimie' => [
            'label' => 'Biochimie',
            'short_label' => 'Biochimie',
            'description' => 'Panel de biochimie serique avec normes configurables par espece.',
            'type' => 'analyse',
        ],
        'hemogramme' => [
            'label' => 'Hemogramme',
            'short_label' => 'Hemogramme',
            'description' => 'Numeration formule sanguine avec normes configurables par espece.',
            'type' => 'analyse',
        ],
        'bse-laitier' => [
            'label' => 'BSE Laitier',
            'short_label' => 'BSE Laitier',
            'description' => 'Bilan sanitaire et economique des elevages bovins laitiers.',
            'type' => 'rapport',
        ],
        'bse-allaitant' => [
            'label' => 'BSE Allaitant',
            'short_label' => 'BSE Allaitant',
            'description' => 'Bilan sanitaire et economique des elevages bovins allaitants.',
            'type' => 'rapport',
        ],
        'autopsie' => [
            'label' => 'Autopsie',
            'short_label' => 'Autopsie',
            'description' => 'Examen necropique avec description des lesions et conclusion.',
            'type' => 'rapport',
        ],
        'compte-rendu' => [
            'label' => 'Compte-rendu',
            'short_label' => 'Compte-rendu',
            'description' => 'Compte-rendu de visite en une ou plusieurs pages.',
            'type' => 'rapport',
        ],
    ];

    /**
     * @return array<string, array{label: string, short_label: string, description: string}>
     */
    public static function all(): array
    {
        return self::MODULES;
    }

    /**
     * @return array<int, array{slug: string, label: string, short_label: string, description: string, type: string}>
     */
    public static function navigation(): array
    {
        return collect(self::MODULES)
            ->map(fn (array $module, string $slug): array => ['slug' => $slug, ...$module])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{slug: string, label: string, short_label: string, description: string, type: string}>
     */
    public static function navigationByType(string $type): array
    {
        return collect(self::MODULES)
            ->filter(fn (array $module): bool => $module['type'] === $type)
            ->map(fn (array $module, string $slug): array => ['slug' => $slug, ...$module])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_keys(self::MODULES);
    }

    /**
     * @return array{label: string, short_label: string, description: string}
     */
    public static function get(string $module): array
    {
        self::assertExists($module);

        return self::MODULES[$module];
    }

    public static function assertExists(string $module): void
    {
        if (! array_key_exists($module, self::MODULES)) {
            throw new InvalidArgumentException("Module veterinaire inconnu [$module].");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function settingsForUser(User $user, string $module): array
    {
        self::assertExists($module);

        $stored = UserModuleSetting::query()
            ->where('user_id', $user->id)
            ->where('module', $module)
            ->first()?->settings;

        return self::normalizeSettings($module, is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function normalizeSettings(string $module, array $settings): array
    {
        $normalized = array_replace_recursive(self::defaultSettings($module), $settings);

        if ($module === 'coproscopie-parasitaire') {
            return self::normalizeCoproscopySettings($normalized);
        }

        if ($module === 'tests-rapides') {
            foreach (['species_options', 'elisa_tests', 'biochem_rapide', 'pcr_tests', 'optional_sections'] as $key) {
                if (array_key_exists($key, $settings)) {
                    $normalized[$key] = $settings[$key];
                }
            }

            if (! array_key_exists('optional_sections', $settings) && is_array($normalized['optional_sections'] ?? null)) {
                foreach (['bandelette_urinaire', 'frottis_sanguin'] as $legacyKey) {
                    if (! array_key_exists($legacyKey, $settings)) {
                        continue;
                    }

                    foreach ($normalized['optional_sections'] as &$section) {
                        if (is_array($section) && ($section['key'] ?? null) === $legacyKey) {
                            $section['enabled'] = $settings[$legacyKey];
                        }
                    }
                    unset($section);
                }
            }

            return self::normalizeTestsRapidesSettings($normalized);
        }

        if (in_array($module, ['tests-biochimie', 'hemogramme'], true)) {
            foreach (['species_options', 'params', 'norms'] as $key) {
                if (array_key_exists($key, $settings)) {
                    $normalized[$key] = $settings[$key];
                }
            }

            return self::normalizeParametricAnalysisSettings($module, $normalized);
        }

        if (in_array($module, ['bse-laitier', 'bse-allaitant'], true)) {
            return self::normalizeBseSettings($normalized);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(string $module): array
    {
        self::assertExists($module);

        return match ($module) {
            'coproscopie-parasitaire' => [
                'species_options' => ['Bovin', 'Ovin', 'Caprin', 'Equin', 'Chien', 'Chat'],
                'parasites' => self::defaultCoproscopyParasites(),
                'scale' => [
                    ['value' => '0', 'label' => '0'],
                    ['value' => '1', 'label' => '+'],
                    ['value' => '2', 'label' => '++'],
                    ['value' => '3', 'label' => '+++'],
                ],
            ],
            'diarrhee-neonatale' => [
                'tests' => ['Kitvia', 'Speed V-Diar', 'Quick Diar 5', 'Speed V-Diar 4', 'Test rapide cabinet', 'PCR laboratoire'],
                'pathogens' => [
                    ['key' => 'rotavirus', 'label' => 'Rotavirus', 'enabled' => true, 'tests' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5']],
                    ['key' => 'coronavirus', 'label' => 'Coronavirus', 'enabled' => true, 'tests' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5']],
                    ['key' => 'ecoli_k99', 'label' => 'E. coli K99', 'enabled' => true, 'tests' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5']],
                    ['key' => 'ecoli_cs31a', 'label' => 'E. coli CS31A', 'enabled' => true, 'tests' => ['Speed V-Diar', 'Speed V-Diar 4']],
                    ['key' => 'clostridium_perfringens', 'label' => 'Clostridium perfringens', 'enabled' => true, 'tests' => ['Quick Diar 5']],
                    ['key' => 'cryptosporidies', 'label' => 'Cryptosporidies', 'enabled' => true, 'tests' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5']],
                    ['key' => 'giardia', 'label' => 'Giardia', 'enabled' => true, 'tests' => ['Kitvia']],
                    ['key' => 'coccidies', 'label' => 'Coccidies', 'enabled' => true, 'requires_option' => 'coccidiosis_test'],
                ],
                'scale' => [
                    ['value' => '0', 'label' => 'Negatif'],
                    ['value' => '1', 'label' => 'Faible'],
                    ['value' => '2', 'label' => 'Positif'],
                    ['value' => '3', 'label' => 'Fort'],
                ],
            ],
            'gaz-du-sang' => [
                'species_options' => [
                    ['value' => 'Bovin', 'label' => 'Bovin', 'norm_key' => 'Bovin', 'calculation_profile' => 'ruminant'],
                    ['value' => 'Ovin', 'label' => 'Ovin', 'norm_key' => 'Ovin', 'calculation_profile' => 'ruminant'],
                    ['value' => 'Caprin', 'label' => 'Caprin', 'norm_key' => 'Caprin', 'calculation_profile' => 'ruminant'],
                    ['value' => 'Equin', 'label' => 'Equin', 'norm_key' => 'Equin', 'calculation_profile' => 'equine'],
                ],
                'norms' => [
                    'Bovin' => ['ph' => [7.35, 7.45], 'pco2' => [35, 44], 'hco3' => [21, 28], 'na' => [130, 144], 'k' => [4, 5.5], 'cl' => [95, 105], 'glycemia' => [90, 110]],
                    'Ovin' => ['ph' => [7.35, 7.45], 'pco2' => [35, 44], 'hco3' => [21, 28], 'na' => [130, 144], 'k' => [4, 5.5], 'cl' => [95, 105], 'glycemia' => [90, 110]],
                    'Caprin' => ['ph' => [7.35, 7.45], 'pco2' => [35, 44], 'hco3' => [21, 28], 'na' => [130, 144], 'k' => [4, 5.5], 'cl' => [95, 105], 'glycemia' => [90, 110]],
                    'Equin' => ['ph' => [7.35, 7.45], 'pco2' => [35, 44], 'hco3' => [21, 28], 'na' => [130, 144], 'k' => [4, 5.5], 'cl' => [95, 105], 'glycemia' => [90, 110]],
                ],
                'perfusions' => [
                    ['key' => 'bica_iso_1l', 'label' => 'Bica iso 1 L', 'unit' => 'L', 'bicarbonate' => 14, 'glucose' => 0, 'volume' => 1],
                    ['key' => 'speciale', 'label' => 'Speciale', 'unit' => 'unite', 'bicarbonate' => 20, 'glucose' => 7.5, 'volume' => 0.5],
                    ['key' => 'carbi', 'label' => 'Carbi', 'unit' => 'unite', 'bicarbonate' => 28, 'glucose' => 0, 'volume' => 0.5],
                    ['key' => 'dhydrat', 'label' => 'Dhydrat', 'unit' => 'unite', 'bicarbonate' => 5, 'glucose' => 15, 'volume' => 1.5],
                    ['key' => 'lodevil', 'label' => 'Lodevil', 'unit' => 'unite', 'bicarbonate' => 4.2, 'glucose' => 10, 'volume' => 1],
                    ['key' => 'glucose_5_1l', 'label' => 'Glucose 5% 1 L', 'unit' => 'L', 'bicarbonate' => 0, 'glucose' => 50, 'volume' => 1],
                    ['key' => 'glucose_30_100ml', 'label' => 'Glucose 30% 100 mL', 'unit' => 'flacon', 'bicarbonate' => 0, 'glucose' => 30, 'volume' => 0.5],
                    ['key' => 'nacl_10_100ml', 'label' => 'NaCl 10% 100 mL', 'unit' => 'flacon', 'bicarbonate' => 0, 'glucose' => 0, 'volume' => 0.5],
                    ['key' => 'ringer_1l', 'label' => 'Ringer 1 L', 'unit' => 'L', 'bicarbonate' => 0, 'glucose' => 0, 'volume' => 1],
                    ['key' => 'nacl_1l', 'label' => 'NaCl 1 L', 'unit' => 'L', 'bicarbonate' => 0, 'glucose' => 0, 'volume' => 1],
                ],
            ],
            'comptage-cellulaire' => [
                'norms' => [
                    'alert_threshold' => 300,
                    'critical_threshold' => 800,
                    'unit' => 'x 1000 cellules',
                ],
            ],
            'diagnostic-bacteriologique' => [
                'germ_families' => ['Escherichia coli', 'Pasteurella spp', 'Staphylococcus aureus', 'Staphylocoque coag neg', 'Streptococcus spp', 'Streptococcus uberis', 'Enterococcus spp', 'Klebsiella', 'Campylobacter', 'Pseudomonas', 'Autre'],
                'antibiotics' => self::defaultAntibiotics(),
            ],
            'analyse-diverse' => [],
            'tests-rapides' => self::defaultTestsRapidesSettings(),
            'tests-biochimie' => self::defaultTestsBiochimieSettings(),
            'hemogramme' => self::defaultHemogrammeSettings(),
            'bse-laitier' => self::defaultBseLaitierSettings(),
            'bse-allaitant' => self::defaultBseAllaitantSettings(),
            'autopsie' => [],
            'compte-rendu' => [],
            default => throw new InvalidArgumentException("Module veterinaire inconnu [$module]."),
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function payloadTemplate(string $module, array $settings): array
    {
        return match ($module) {
            'coproscopie-parasitaire' => [
                'species' => 'Bovin',
                'sample_nature' => '',
                'sample_count' => 1,
                'options' => ['dictyocaules' => false, 'cryptosporidies' => false, 'comptage' => true],
                'samples' => self::sampleRows(1, self::settingKeys($settings, 'parasites', 'Bovin')),
                'advice_preventive' => '',
                'advice_curative' => '',
            ],
            'diarrhee-neonatale' => [
                'species' => 'Bovin',
                'test_name' => data_get($settings, 'tests.0', ''),
                'sample_nature' => 'Feces',
                'sample_name' => '',
                'coccidiosis_test' => false,
                'pathogens' => collect(self::settingKeys($settings, 'pathogens'))->mapWithKeys(fn (string $key): array => [$key => '0'])->all(),
                'advice_preventive' => '',
                'advice_curative' => '',
            ],
            'gaz-du-sang' => [
                'species' => 'Bovin',
                'weight' => null,
                'enophtalmie' => null,
                'dehydration' => null,
                'ph' => null,
                'pco2' => null,
                'hco3' => null,
                'angap' => null,
                'tco2' => null,
                'na' => null,
                'k' => null,
                'cl' => null,
                'glycemia' => null,
                'treatment' => '',
                'perfusions' => collect(self::settingKeys($settings, 'perfusions'))->mapWithKeys(fn (string $key): array => [$key => 0])->all(),
            ],
            'comptage-cellulaire' => [
                'species' => 'Bovin',
                'sample_nature' => 'Lait',
                'sample_count' => 1,
                'commemoratives' => '',
                'samples' => [['name' => '', 'count' => null]],
                'comments' => '',
            ],
            'diagnostic-bacteriologique' => [
                'species' => 'Bovin',
                'sample_nature' => '',
                'sample_identification' => '',
                'commemoratives' => '',
                'germ_count' => 1,
                'germs' => [['family' => data_get($settings, 'germ_families.0', 'Autre'), 'antibiotics' => []]],
                'advice' => '',
            ],
            'bse-laitier' => [
                'race' => 'Prim Holstein',
                'annee_reference' => (int) date('Y') - 1,
                'nb_vaches_productrices' => null,
                'ivv' => null,
                'concentration_cellulaire_moyen' => null,
                'production_annuelle_lait' => null,
                'prix_lait_tonne' => null,
                'tx_butyreux_moyen' => null,
                'tx_proteique_moyen' => null,
                'nb_veaux_nes_vivants' => null,
                'nb_avortons' => 0,
                'nb_jumeaux' => 0,
                'prix_veaux_male' => null,
                'prix_veaux_femelle' => null,
                'ha_foin' => null,
                'ha_ensilage_herbe' => null,
                'ha_ensilage_mais' => null,
                'production_cereales_tonnes' => null,
                'achat_cereales_tonnes' => null,
                'achat_cereales_euros' => 0,
                'achat_complementaire_tonnes' => null,
                'achat_complementaire_euros' => 0,
                'achat_amv_euros' => 0,
                'nb_mammites_locales' => 0,
                'nb_mammites_locales_non_gueries' => 0,
                'nb_mammites_aigues' => 0,
                'nb_mammites_aigues_non_gueries' => 0,
                'nb_cci250' => 0,
                'nb_boiteries' => 0,
                'nb_boiteries_non_gueries' => 0,
                'nb_fievres_de_lait' => 0,
                'nb_fievres_de_lait_non_gueries' => 0,
                'nb_non_delivrances' => 0,
                'nb_metrites' => 0,
                'nb_caillettes' => 0,
                'nb_caillettes_non_gueries' => 0,
                'nb_cetoses' => 0,
                'nb_acidoses' => 0,
                'nb_malades_0a7' => 0,
                'nb_morts_0a7' => 0,
                'nb_malades_8a_sevr' => 0,
                'nb_morts_8a_sevr' => 0,
                'nb_ivia1' => null,
                'nb_iviaf' => null,
                'tx_reussite_ia1' => null,
                'tx_ia3' => null,
                'boolean_depistage_metrite' => false,
            ],
            'bse-allaitant' => [
                'race' => 'Charolaise',
                'annee_reference' => (int) date('Y') - 1,
                'nb_vaches_reproductrices' => null,
                'ivv' => null,
                'nb_veaux_nes_vivants' => null,
                'nb_jumeaux' => 0,
                'nb_accidents_velage' => 0,
                'nb_avortons' => 0,
                'nb_morts_post24h' => 0,
                'nb_sevres' => null,
                'ha_foin' => null,
                'ha_ensilage_herbe' => null,
                'ha_ensilage_mais' => null,
                'production_cereales_tonnes' => null,
                'achat_cereales_tonnes' => null,
                'achat_cereales_euros' => 0,
                'achat_complementaire_tonnes' => null,
                'achat_complementaire_euros' => 0,
                'achat_amv_euros' => 0,
                'nb_malades_diar1' => 0,
                'nb_morts_diar1' => 0,
                'nb_malades_diar2et3' => 0,
                'nb_morts_diar2et3' => 0,
                'nb_malades_diar4' => 0,
                'nb_morts_diar4' => 0,
                'nb_diar_perf' => 0,
                'nb_malades_respi' => 0,
                'nb_morts_respi' => 0,
                'nb_malades_omphalite' => 0,
                'nb_morts_omphalite' => 0,
                'nb_malades_autres' => 0,
                'nb_morts_autres' => 0,
                'nb_morts_subites' => 0,
                'nb_morts_avant3_mois' => 0,
                'nb_velages_longs' => 0,
                'nb_cesariennes' => 0,
                'nb_non_delivrances' => 0,
                'nb_torsions_retournements_matrices' => 0,
                'nb_metrites' => 0,
            ],
            'analyse-diverse' => [
                'species' => 'Bovin',
                'sample_count' => 1,
                'commemoratifs' => '',
                'analyses' => [['type' => '', 'results' => '']],
                'commentaires' => '',
            ],
            'tests-rapides' => [
                'species' => 'Bovin',
                'sample_nature' => '',
                'identification' => '',
                'commemoratifs' => '',
                'elisa' => [],
                'biochem_rapide' => [],
                'pcr' => [],
                'bandelette' => [],
                'frottis' => [],
                'commentaires' => '',
            ],
            'tests-biochimie' => [
                'species' => 'Bovin',
                'sample_nature' => 'Serum',
                'identification' => '',
                'commemoratifs' => '',
                'params' => [],
                'commentaires' => '',
            ],
            'hemogramme' => [
                'species' => 'Bovin',
                'sample_nature' => 'Sang EDTA',
                'identification' => '',
                'commemoratifs' => '',
                'params' => [],
                'commentaires' => '',
            ],
            'autopsie' => [
                'identification' => '',
                'species' => 'Bovin',
                'sexe' => '',
                'conformation' => '',
                'conservation' => '',
                'engraissement' => '',
                'poids' => null,
                'commemoratifs' => '',
                'lesions' => '',
                'conclusion' => '',
            ],
            'compte-rendu' => [
                'pages' => [''],
                'nb_pages' => 1,
            ],
            default => throw new InvalidArgumentException("Module veterinaire inconnu [$module]."),
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private static function settingKeys(array $settings, string $key, ?string $species = null): array
    {
        $items = $settings[$key] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['enabled'] ?? true) !== false && isset($item['key']) && self::matchesSpecies($item, $species))
            ->pluck('key')
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function matchesSpecies(array $item, ?string $species): bool
    {
        if ($species === null || $species === '') {
            return true;
        }

        $speciesList = $item['species'] ?? [];

        if (! is_array($speciesList) || $speciesList === []) {
            return true;
        }

        return in_array($species, array_map('strval', $speciesList), true);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function normalizeCoproscopySettings(array $settings): array
    {
        $defaultParasites = collect(self::defaultCoproscopyParasites())
            ->keyBy(fn (array $parasite): string => $parasite['key'])
            ->all();

        if (! isset($settings['species_options']) || ! is_array($settings['species_options']) || $settings['species_options'] === []) {
            $settings['species_options'] = ['Bovin', 'Ovin', 'Caprin', 'Equin', 'Chien', 'Chat'];
        }

        $settings['species_options'] = collect($settings['species_options'])
            ->map(fn (mixed $species): string => is_array($species) ? (string) ($species['value'] ?? $species['label'] ?? '') : (string) $species)
            ->filter(fn (string $species): bool => $species !== '')
            ->unique()
            ->values()
            ->all();

        if (! isset($settings['parasites']) || ! is_array($settings['parasites'])) {
            $settings['parasites'] = [];
        }

        $settings['parasites'] = collect($settings['parasites'])
            ->filter(fn (mixed $parasite): bool => is_array($parasite))
            ->map(function (array $parasite) use ($defaultParasites, $settings): array {
                $key = (string) ($parasite['key'] ?? '');
                $defaults = $defaultParasites[$key] ?? [];

                if (! isset($parasite['species']) || ! is_array($parasite['species']) || $parasite['species'] === []) {
                    $parasite['species'] = $defaults['species'] ?? $settings['species_options'];
                }

                if (! array_key_exists('requires_option', $parasite) && array_key_exists('requires_option', $defaults)) {
                    $parasite['requires_option'] = $defaults['requires_option'];
                }

                $parasite['species'] = collect($parasite['species'])
                    ->map(fn (mixed $species): string => (string) $species)
                    ->filter(fn (string $species): bool => $species !== '')
                    ->unique()
                    ->values()
                    ->all();

                return $parasite;
            })
            ->values()
            ->all();

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function normalizeTestsRapidesSettings(array $settings): array
    {
        if (! isset($settings['species_options']) || ! is_array($settings['species_options']) || $settings['species_options'] === []) {
            $settings['species_options'] = self::defaultTestsRapidesSettings()['species_options'];
        }

        $settings['species_options'] = collect($settings['species_options'])
            ->map(fn (mixed $species): string => (string) $species)
            ->map(fn (string $species): string => trim($species))
            ->filter(fn (string $species): bool => $species !== '')
            ->unique()
            ->values()
            ->all();

        $species = $settings['species_options'];

        $settings['elisa_tests'] = self::normalizeTestsRapidesItems($settings['elisa_tests'] ?? [], 'elisa', $species);
        $settings['biochem_rapide'] = self::normalizeTestsRapidesItems($settings['biochem_rapide'] ?? [], 'biochimie', $species, true);
        $settings['pcr_tests'] = self::normalizeTestsRapidesItems($settings['pcr_tests'] ?? [], 'pcr', $species);
        $settings['optional_sections'] = self::normalizeTestsRapidesItems($settings['optional_sections'] ?? [], 'section', $species);

        $settings['bandelette_urinaire'] = collect($settings['optional_sections'])
            ->contains(fn (array $section): bool => $section['key'] === 'bandelette_urinaire' && $section['enabled'] !== false);
        $settings['frottis_sanguin'] = collect($settings['optional_sections'])
            ->contains(fn (array $section): bool => $section['key'] === 'frottis_sanguin' && $section['enabled'] !== false);

        return $settings;
    }

    /**
     * @param  array<int, mixed>|mixed  $items
     * @param  array<int, string>  $species
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeTestsRapidesItems(mixed $items, string $prefix, array $species, bool $withUnit = false): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item, int $index) use ($prefix, $species, $withUnit): array {
                $label = trim((string) ($item['label'] ?? $item['key'] ?? ''));
                $key = self::settingKey($item['key'] ?? $label, "{$prefix}_".($index + 1));
                $activeSpecies = array_key_exists('species', $item) && is_array($item['species'])
                    ? collect($item['species'])
                        ->map(fn (mixed $value): string => trim((string) $value))
                        ->filter(fn (string $value): bool => $value !== '' && in_array($value, $species, true))
                        ->unique()
                        ->values()
                        ->all()
                    : $species;

                $normalized = [
                    'key' => $key,
                    'label' => $label !== '' ? $label : $key,
                    'species' => $activeSpecies,
                    'enabled' => self::truthy($item['enabled'] ?? true),
                ];

                if ($withUnit || array_key_exists('unit', $item)) {
                    $normalized['unit'] = trim((string) ($item['unit'] ?? ''));
                }

                return $normalized;
            })
            ->values()
            ->all();
    }

    private static function settingKey(mixed $value, string $fallback): string
    {
        $key = Str::slug((string) $value, '_');

        return $key !== '' ? $key : $fallback;
    }

    private static function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function normalizeParametricAnalysisSettings(string $module, array $settings): array
    {
        $defaults = self::defaultSettings($module);

        if (! isset($settings['species_options']) || ! is_array($settings['species_options']) || $settings['species_options'] === []) {
            $settings['species_options'] = $defaults['species_options'] ?? ['Bovin', 'Ovin', 'Caprin', 'Equin', 'Chien', 'Chat'];
        }

        $settings['species_options'] = collect($settings['species_options'])
            ->map(fn (mixed $species): string => trim((string) $species))
            ->filter(fn (string $species): bool => $species !== '')
            ->unique()
            ->values()
            ->all();

        $species = $settings['species_options'];
        $defaultParams = collect($defaults['params'] ?? [])
            ->filter(fn (mixed $param): bool => is_array($param) && isset($param['key']))
            ->keyBy(fn (array $param): string => (string) $param['key'])
            ->all();

        $settings['params'] = collect(is_array($settings['params'] ?? null) ? $settings['params'] : [])
            ->filter(fn (mixed $param): bool => is_array($param))
            ->map(function (array $param, int $index) use ($defaultParams, $module, $species): array {
                $key = self::parametricSettingKey($param['key'] ?? $param['label'] ?? null, 'param_'.($index + 1));
                $defaults = $defaultParams[$key] ?? [];
                $label = trim((string) ($param['label'] ?? $defaults['label'] ?? $key));
                $activeSpecies = array_key_exists('species', $param) && is_array($param['species'])
                    ? collect($param['species'])
                        ->map(fn (mixed $value): string => trim((string) $value))
                        ->filter(fn (string $value): bool => $value !== '' && in_array($value, $species, true))
                        ->unique()
                        ->values()
                        ->all()
                    : ($defaults['species'] ?? $species);

                $normalized = [
                    'key' => $key,
                    'label' => $label !== '' ? $label : $key,
                    'species' => $activeSpecies,
                    'enabled' => self::truthy($param['enabled'] ?? true),
                ];

                if ($module === 'hemogramme') {
                    $group = trim((string) ($param['group'] ?? $defaults['group'] ?? 'autres'));
                    $normalized['group'] = $group !== '' ? $group : 'autres';
                }

                return $normalized;
            })
            ->values()
            ->all();

        $settings['norms'] = self::normalizeParametricNorms(
            is_array($settings['norms'] ?? null) ? $settings['norms'] : [],
            is_array($defaults['norms'] ?? null) ? $defaults['norms'] : [],
            $settings['species_options'],
            $settings['params'],
        );

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $norms
     * @param  array<string, mixed>  $defaults
     * @param  array<int, string>  $speciesOptions
     * @param  array<int, array<string, mixed>>  $params
     * @return array<string, array<string, array{min: int|float|null, max: int|float|null, unit: string}>>
     */
    private static function normalizeParametricNorms(array $norms, array $defaults, array $speciesOptions, array $params): array
    {
        $normalized = [];

        foreach ($speciesOptions as $species) {
            $normalized[$species] = [];

            foreach ($params as $param) {
                $key = (string) ($param['key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $norm = data_get($norms, "{$species}.{$key}");

                if (! is_array($norm)) {
                    $norm = data_get($defaults, "{$species}.{$key}");
                }

                if (! is_array($norm)) {
                    $norm = data_get($defaults, "Bovin.{$key}");
                }

                if (! is_array($norm)) {
                    $norm = ['min' => null, 'max' => null, 'unit' => ''];
                }

                $normalized[$species][$key] = [
                    'min' => self::normValue($norm['min'] ?? null),
                    'max' => self::normValue($norm['max'] ?? null),
                    'unit' => trim((string) ($norm['unit'] ?? '')),
                ];
            }
        }

        return $normalized;
    }

    private static function parametricSettingKey(mixed $value, string $fallback): string
    {
        $key = trim((string) $value);
        $key = preg_replace('/\s+/', '_', $key) ?? '';
        $key = preg_replace('/[^\pL\pN_.-]+/u', '_', $key) ?? '';

        return $key !== '' ? $key : $fallback;
    }

    private static function normValue(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return null;
    }

    /**
     * @return array<int, array{key: string, label: string, unit: string|null, species: array<int, string>, enabled: bool, requires_option?: string}>
     */
    private static function defaultCoproscopyParasites(): array
    {
        $ruminants = ['Bovin', 'Ovin', 'Caprin'];
        $smallRuminants = ['Ovin', 'Caprin'];
        $carnivores = ['Chien', 'Chat'];

        return [
            ['key' => 'paramphistome', 'label' => 'Paramphistome', 'unit' => 'FSZ', 'species' => $ruminants, 'enabled' => true],
            ['key' => 'grande_douve', 'label' => 'Grande douve', 'unit' => 'FSZ', 'species' => $ruminants, 'enabled' => true],
            ['key' => 'petite_douve', 'label' => 'Petite douve', 'unit' => 'FSZ', 'species' => $ruminants, 'enabled' => true],
            ['key' => 'coccidies', 'label' => 'Coccidies', 'unit' => 'FSZ', 'species' => $ruminants, 'enabled' => true],
            ['key' => 'strongles_digestifs', 'label' => 'Strongles intestinaux', 'unit' => 'FSZ', 'species' => $ruminants, 'enabled' => true],
            ['key' => 'nematodirus', 'label' => 'Nematodirus', 'unit' => 'FSZ', 'species' => $ruminants, 'enabled' => true],
            ['key' => 'strongyloides', 'label' => 'Strongyloides', 'unit' => 'FSZ', 'species' => $ruminants, 'enabled' => true],
            ['key' => 'trichure', 'label' => 'Trichure', 'unit' => 'FSZ', 'species' => $ruminants, 'enabled' => true],
            ['key' => 'taenia', 'label' => 'Taenia', 'unit' => 'FSZ', 'species' => $ruminants, 'enabled' => true],
            ['key' => 'strongles_pulmonaires', 'label' => 'Strongles pulmonaires', 'unit' => 'TdB', 'species' => ['Bovin'], 'enabled' => true, 'requires_option' => 'dictyocaules'],
            ['key' => 'marshallagia_marshalli', 'label' => 'Marshallagia marshalli', 'unit' => 'FSZ', 'species' => $smallRuminants, 'enabled' => true],
            ['key' => 'cryptosporidie', 'label' => 'Cryptosporidies', 'unit' => 'AAR', 'species' => $ruminants, 'enabled' => true, 'requires_option' => 'cryptosporidies'],
            ['key' => 'eimeria_leuckarti', 'label' => 'Eimeria leuckarti', 'unit' => 'FSZ', 'species' => ['Equin'], 'enabled' => true],
            ['key' => 'parascaris_equorum', 'label' => 'Parascaris equorum', 'unit' => 'FSZ', 'species' => ['Equin'], 'enabled' => true],
            ['key' => 'anoplocephalides', 'label' => 'Anoplocephalides', 'unit' => 'FSZ', 'species' => ['Equin'], 'enabled' => true],
            ['key' => 'habronema_sp', 'label' => 'Habronema sp', 'unit' => 'FSZ', 'species' => ['Equin'], 'enabled' => true],
            ['key' => 'strongyloides_westeri', 'label' => 'Strongyloides westeri', 'unit' => 'FSZ', 'species' => ['Equin'], 'enabled' => true],
            ['key' => 'dictyocaulus_arnfieldi', 'label' => 'Dictyocaulus arnfieldi', 'unit' => 'TdB', 'species' => ['Equin'], 'enabled' => true, 'requires_option' => 'dictyocaules'],
            ['key' => 'strongylus_spp', 'label' => 'Strongylus spp', 'unit' => 'FSZ', 'species' => ['Equin'], 'enabled' => true],
            ['key' => 'cyathostomes', 'label' => 'Cyathostomes', 'unit' => 'FSZ', 'species' => ['Equin'], 'enabled' => true],
            ['key' => 'oxyuris_equi', 'label' => 'Oxyuris equi', 'unit' => 'FSZ', 'species' => ['Equin'], 'enabled' => true],
            ['key' => 'cryptosporidium_parvum', 'label' => 'Cryptosporidium parvum', 'unit' => 'AAR', 'species' => ['Equin'], 'enabled' => true, 'requires_option' => 'cryptosporidies'],
            ['key' => 'diphyllobotrium_latum', 'label' => 'Diphyllobotrium latum', 'unit' => 'FSZ', 'species' => $carnivores, 'enabled' => true],
            ['key' => 'dipylidium_caninum', 'label' => 'Dipylidium caninum', 'unit' => 'FSZ', 'species' => $carnivores, 'enabled' => true],
            ['key' => 'taenia_cnct', 'label' => 'Taenia', 'unit' => 'FSZ', 'species' => $carnivores, 'enabled' => true],
            ['key' => 'toxocara', 'label' => 'Toxocara', 'unit' => 'FSZ', 'species' => $carnivores, 'enabled' => true],
            ['key' => 'toxascaris_leonina', 'label' => 'Toxascaris leonina', 'unit' => 'FSZ', 'species' => $carnivores, 'enabled' => true],
            ['key' => 'capillaria', 'label' => 'Capillaria', 'unit' => 'FSZ', 'species' => $carnivores, 'enabled' => true],
            ['key' => 'strongyloides_cnct', 'label' => 'Strongyloides', 'unit' => 'FSZ', 'species' => $carnivores, 'enabled' => true],
            ['key' => 'giardia', 'label' => 'Giardia', 'unit' => 'FSZ', 'species' => $carnivores, 'enabled' => true],
            ['key' => 'sarcocystis', 'label' => 'Sarcocystis', 'unit' => 'FSZ', 'species' => $carnivores, 'enabled' => true],
            ['key' => 'toxoplasma', 'label' => 'Toxoplasma', 'unit' => 'FSZ', 'species' => ['Chat'], 'enabled' => true],
            ['key' => 'cryptosporidium_cnct', 'label' => 'Cryptosporidium', 'unit' => 'AAR', 'species' => $carnivores, 'enabled' => true, 'requires_option' => 'cryptosporidies'],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function normalizeBseSettings(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_string($value) && is_numeric($value)) {
                $settings[$key] = $value + 0;
            }

            if (is_string($value) && str_starts_with((string) $key, 'txt_')) {
                $settings[$key] = LegacyHtmlCleaner::plainText($value);
            }
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultTestsRapidesSettings(): array
    {
        $speciesOptions = ['Bovin', 'Ovin', 'Caprin', 'Equin', 'Chien', 'Chat'];

        return [
            'species_options' => $speciesOptions,
            'elisa_tests' => [
                ['key' => 'rotavirus_bv', 'label' => 'Rotavirus Ag', 'species' => ['Bovin', 'Ovin', 'Caprin'], 'enabled' => true],
                ['key' => 'coronavirus_bv', 'label' => 'Coronavirus Ag', 'species' => ['Bovin', 'Ovin', 'Caprin'], 'enabled' => true],
                ['key' => 'ecoli_k99', 'label' => 'E. coli K99', 'species' => ['Bovin', 'Ovin', 'Caprin'], 'enabled' => true],
                ['key' => 'ecoli_cs31a', 'label' => 'E. coli CS31A', 'species' => ['Bovin', 'Ovin', 'Caprin'], 'enabled' => true],
                ['key' => 'cryptosporidium_bv', 'label' => 'Cryptosporidium Ag', 'species' => ['Bovin', 'Ovin', 'Caprin'], 'enabled' => true],
                ['key' => 'bvd_ag', 'label' => 'BVD Ag', 'species' => ['Bovin'], 'enabled' => true],
                ['key' => 'rsv_bv', 'label' => 'RSV Ag', 'species' => ['Bovin'], 'enabled' => true],
                ['key' => 'igg_colostrum', 'label' => 'IgG Colostrum', 'species' => ['Bovin', 'Ovin', 'Caprin'], 'enabled' => true],
                ['key' => 'igg_sg_veau', 'label' => 'IgG Sg veau', 'species' => ['Bovin', 'Ovin', 'Caprin'], 'enabled' => true],
                ['key' => 'babesia_bovis', 'label' => 'Babesia bovis', 'species' => ['Bovin'], 'enabled' => true],
                ['key' => 'anaplasma_bv', 'label' => 'Anaplasma phago.', 'species' => ['Bovin'], 'enabled' => true],
                ['key' => 'giardia_eq', 'label' => 'Giardia Ag', 'species' => ['Equin'], 'enabled' => true],
                ['key' => 'igg_poulain', 'label' => 'IgG Poulain', 'species' => ['Equin'], 'enabled' => true],
                ['key' => 'babesia_equi', 'label' => 'Babesia equi', 'species' => ['Equin'], 'enabled' => true],
                ['key' => 'borrelia_eq', 'label' => 'Borrelia', 'species' => ['Equin'], 'enabled' => true],
                ['key' => 'anaplasma_eq', 'label' => 'Anaplasma CV', 'species' => ['Equin'], 'enabled' => true],
                ['key' => 'leptospira_eq', 'label' => 'Leptospira CV', 'species' => ['Equin'], 'enabled' => true],
                ['key' => 'parvovirus_cn', 'label' => 'Parvovirus Ag', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'coronavirus_cn', 'label' => 'Coronavirus Ag', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'giardia_cn', 'label' => 'Giardia Ag', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'leptospirose_cn', 'label' => 'Leptospirose IgM', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'ehrlichiose_cn', 'label' => 'Ehrlichiose Ac', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'leishmaniose_cn', 'label' => 'Leishmaniose Ac', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'dirofilariose_cn', 'label' => 'Dirofilariose Ag', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'maladie_carre_cn', 'label' => 'Maladie de Carre Ag', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'adenovirus_cn', 'label' => 'Adenovirus Ag', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'maladie_lyme_cn', 'label' => 'Maladie de Lyme Ac', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'angiostrongylose_cn', 'label' => 'Angiostrongylose Ag', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'cpl_cn', 'label' => 'cPL', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'anaplasmose_cn', 'label' => 'Anaplasmose Ac', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'parvovirose_cn', 'label' => 'Parvovirose Ac', 'species' => ['Chien'], 'enabled' => true],
                ['key' => 'felv_ct', 'label' => 'FeLV Ag', 'species' => ['Chat'], 'enabled' => true],
                ['key' => 'fiv_ct', 'label' => 'FIV Ac', 'species' => ['Chat'], 'enabled' => true],
                ['key' => 'giardia_ct', 'label' => 'Giardia Ag', 'species' => ['Chat'], 'enabled' => true],
                ['key' => 'coronavirus_ac_ct', 'label' => 'Coronavirus Ac', 'species' => ['Chat'], 'enabled' => true],
                ['key' => 'panleucopenie_ct', 'label' => 'Panleucopenie Ag', 'species' => ['Chat'], 'enabled' => true],
                ['key' => 'dirofilariose_ct', 'label' => 'Dirofilariose Ag', 'species' => ['Chat'], 'enabled' => true],
                ['key' => 'chlamydophila_ct', 'label' => 'Chlamydophila Ag', 'species' => ['Chat'], 'enabled' => true],
                ['key' => 'fpl_ct', 'label' => 'fPL', 'species' => ['Chat'], 'enabled' => true],
            ],
            'biochem_rapide' => [
                ['key' => 'glycemie', 'label' => 'Glycemie', 'unit' => 'g/L', 'species' => $speciesOptions, 'enabled' => true],
                ['key' => 'cetones', 'label' => 'Corps cetoniques', 'unit' => 'mmol/L', 'species' => $speciesOptions, 'enabled' => true],
                ['key' => 'uree', 'label' => 'Uree', 'unit' => 'mmol/L', 'species' => $speciesOptions, 'enabled' => true],
                ['key' => 'lactate', 'label' => 'Lactate', 'unit' => 'mmol/L', 'species' => $speciesOptions, 'enabled' => true],
                ['key' => 'igg_colostrum_q', 'label' => 'IgG Colostrum', 'unit' => 'g/L', 'species' => ['Bovin', 'Ovin', 'Caprin'], 'enabled' => true],
                ['key' => 'igg_sg_veau_q', 'label' => 'IgG Sg veau', 'unit' => 'g/L', 'species' => ['Bovin', 'Ovin', 'Caprin'], 'enabled' => true],
                ['key' => 'lipase', 'label' => 'Lipase', 'unit' => 'U/L', 'species' => ['Chien', 'Chat'], 'enabled' => true],
                ['key' => 't4', 'label' => 'T4', 'unit' => 'nmol/L', 'species' => ['Chien', 'Chat'], 'enabled' => true],
                ['key' => 'acides_biliaires', 'label' => 'Acides biliaires', 'unit' => 'umol/L', 'species' => ['Chien', 'Chat'], 'enabled' => true],
                ['key' => 'cortisol', 'label' => 'Cortisol', 'unit' => 'nmol/L', 'species' => ['Chien'], 'enabled' => true],
            ],
            'pcr_tests' => [],
            'optional_sections' => [
                ['key' => 'bandelette_urinaire', 'label' => 'Bandelette urinaire', 'species' => $speciesOptions, 'enabled' => true],
                ['key' => 'frottis_sanguin', 'label' => 'Frottis sanguin', 'species' => $speciesOptions, 'enabled' => true],
            ],
            'bandelette_urinaire' => true,
            'frottis_sanguin' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultTestsBiochimieSettings(): array
    {
        return [
            'species_options' => ['Bovin', 'Ovin', 'Caprin', 'Equin', 'Chien', 'Chat'],
            'params' => [
                ['key' => 'ALB', 'label' => 'Albumine', 'enabled' => true],
                ['key' => 'ALKP', 'label' => 'ALKP', 'enabled' => true],
                ['key' => 'ALT', 'label' => 'ALAT', 'enabled' => true],
                ['key' => 'AMYL', 'label' => 'Amylase', 'enabled' => true],
                ['key' => 'AST', 'label' => 'ASAT', 'enabled' => true],
                ['key' => 'UREE', 'label' => 'Uree', 'enabled' => true],
                ['key' => 'Ca', 'label' => 'Calcium', 'enabled' => true],
                ['key' => 'CHOL', 'label' => 'Cholesterol', 'enabled' => true],
                ['key' => 'CK', 'label' => 'CK', 'enabled' => true],
                ['key' => 'Cl', 'label' => 'Chlore', 'enabled' => true],
                ['key' => 'CREA', 'label' => 'Creatinine', 'enabled' => true],
                ['key' => 'UREA_CREA', 'label' => 'Uree/Creat.', 'enabled' => true],
                ['key' => 'FRU', 'label' => 'Fructosamine', 'enabled' => false],
                ['key' => 'GGT', 'label' => 'GGT', 'enabled' => true],
                ['key' => 'GLOB', 'label' => 'Globulines', 'enabled' => true],
                ['key' => 'ALB_GLOB', 'label' => 'Alb/Glob', 'enabled' => false],
                ['key' => 'GLU', 'label' => 'Glucose', 'enabled' => true],
                ['key' => 'K', 'label' => 'Potassium', 'enabled' => true],
                ['key' => 'LAC', 'label' => 'Lactate', 'enabled' => true],
                ['key' => 'LDH', 'label' => 'LDH', 'enabled' => true],
                ['key' => 'LIPA', 'label' => 'Lipase', 'enabled' => true],
                ['key' => 'Mg', 'label' => 'Magnesium', 'enabled' => true],
                ['key' => 'Na', 'label' => 'Sodium', 'enabled' => true],
                ['key' => 'Na_K', 'label' => 'Na/K', 'enabled' => false],
                ['key' => 'NH3', 'label' => 'Ammoniaque', 'enabled' => false],
                ['key' => 'OSMOL', 'label' => 'Osmolalite', 'enabled' => false],
                ['key' => 'PHBR', 'label' => 'BHB', 'enabled' => true],
                ['key' => 'PHOS', 'label' => 'Phosphore', 'enabled' => true],
                ['key' => 'TBIL', 'label' => 'Bilirubine totale', 'enabled' => true],
                ['key' => 'TP', 'label' => 'Proteines totales', 'enabled' => true],
                ['key' => 'TRIG', 'label' => 'Triglycerides', 'enabled' => true],
                ['key' => 'UCREA', 'label' => 'Creat. urinaire', 'enabled' => false],
                ['key' => 'UPC', 'label' => 'UPC', 'enabled' => false],
                ['key' => 'UPRO', 'label' => 'Prot. urinaires', 'enabled' => false],
                ['key' => 'URIC', 'label' => 'Acide urique', 'enabled' => false],
            ],
            'norms' => [
                'Bovin' => [
                    'ALB' => ['min' => 30, 'max' => 39, 'unit' => 'g/L'],
                    'ALKP' => ['min' => 0, 'max' => 176, 'unit' => 'U/L'],
                    'ALT' => ['min' => 0, 'max' => 38, 'unit' => 'U/L'],
                    'AMYL' => ['min' => null, 'max' => null, 'unit' => 'U/L'],
                    'AST' => ['min' => 60, 'max' => 125, 'unit' => 'U/L'],
                    'UREE' => ['min' => 3.0, 'max' => 8.0, 'unit' => 'mmol/L'],
                    'Ca' => ['min' => 2.10, 'max' => 2.60, 'unit' => 'mmol/L'],
                    'CHOL' => ['min' => 2.0, 'max' => 4.0, 'unit' => 'mmol/L'],
                    'CK' => ['min' => 0, 'max' => 143, 'unit' => 'U/L'],
                    'Cl' => ['min' => 97, 'max' => 111, 'unit' => 'mmol/L'],
                    'CREA' => ['min' => 44, 'max' => 133, 'unit' => 'umol/L'],
                    'UREA_CREA' => ['min' => null, 'max' => null, 'unit' => ''],
                    'FRU' => ['min' => null, 'max' => null, 'unit' => 'umol/L'],
                    'GGT' => ['min' => 0, 'max' => 50, 'unit' => 'U/L'],
                    'GLOB' => ['min' => 30, 'max' => 55, 'unit' => 'g/L'],
                    'ALB_GLOB' => ['min' => null, 'max' => null, 'unit' => ''],
                    'GLU' => ['min' => 2.8, 'max' => 4.4, 'unit' => 'mmol/L'],
                    'K' => ['min' => 3.9, 'max' => 5.8, 'unit' => 'mmol/L'],
                    'LAC' => ['min' => 0.0, 'max' => 2.0, 'unit' => 'mmol/L'],
                    'LDH' => ['min' => 0, 'max' => 1500, 'unit' => 'U/L'],
                    'LIPA' => ['min' => 0, 'max' => 50, 'unit' => 'U/L'],
                    'Mg' => ['min' => 0.74, 'max' => 1.07, 'unit' => 'mmol/L'],
                    'Na' => ['min' => 132, 'max' => 150, 'unit' => 'mmol/L'],
                    'Na_K' => ['min' => null, 'max' => null, 'unit' => ''],
                    'NH3' => ['min' => null, 'max' => null, 'unit' => 'umol/L'],
                    'OSMOL' => ['min' => 280, 'max' => 300, 'unit' => 'mOsm/kg'],
                    'PHBR' => ['min' => 0.0, 'max' => 0.7, 'unit' => 'mmol/L'],
                    'PHOS' => ['min' => 1.29, 'max' => 2.26, 'unit' => 'mmol/L'],
                    'TBIL' => ['min' => 0, 'max' => 5.1, 'unit' => 'umol/L'],
                    'TP' => ['min' => 60, 'max' => 84, 'unit' => 'g/L'],
                    'TRIG' => ['min' => 0.0, 'max' => 0.20, 'unit' => 'mmol/L'],
                    'UCREA' => ['min' => null, 'max' => null, 'unit' => 'mmol/L'],
                    'UPC' => ['min' => null, 'max' => null, 'unit' => ''],
                    'UPRO' => ['min' => null, 'max' => null, 'unit' => 'mg/L'],
                    'URIC' => ['min' => null, 'max' => null, 'unit' => 'umol/L'],
                ],
                'Chien' => [
                    'ALB' => ['min' => 26, 'max' => 40, 'unit' => 'g/L'],
                    'ALKP' => ['min' => 0, 'max' => 90, 'unit' => 'U/L'],
                    'ALT' => ['min' => 10, 'max' => 100, 'unit' => 'U/L'],
                    'AMYL' => ['min' => 200, 'max' => 1200, 'unit' => 'U/L'],
                    'AST' => ['min' => 0, 'max' => 40, 'unit' => 'U/L'],
                    'UREE' => ['min' => 3.6, 'max' => 9.0, 'unit' => 'mmol/L'],
                    'Ca' => ['min' => 2.25, 'max' => 2.90, 'unit' => 'mmol/L'],
                    'CHOL' => ['min' => 3.0, 'max' => 8.0, 'unit' => 'mmol/L'],
                    'CK' => ['min' => 0, 'max' => 187, 'unit' => 'U/L'],
                    'Cl' => ['min' => 105, 'max' => 115, 'unit' => 'mmol/L'],
                    'CREA' => ['min' => 53, 'max' => 124, 'unit' => 'umol/L'],
                    'UREA_CREA' => ['min' => null, 'max' => null, 'unit' => ''],
                    'FRU' => ['min' => null, 'max' => null, 'unit' => 'umol/L'],
                    'GGT' => ['min' => 0, 'max' => 7, 'unit' => 'U/L'],
                    'GLOB' => ['min' => 27, 'max' => 44, 'unit' => 'g/L'],
                    'ALB_GLOB' => ['min' => null, 'max' => null, 'unit' => ''],
                    'GLU' => ['min' => 3.0, 'max' => 6.1, 'unit' => 'mmol/L'],
                    'K' => ['min' => 3.9, 'max' => 5.1, 'unit' => 'mmol/L'],
                    'LAC' => ['min' => 0.0, 'max' => 2.5, 'unit' => 'mmol/L'],
                    'LDH' => ['min' => 0, 'max' => 400, 'unit' => 'U/L'],
                    'LIPA' => ['min' => 0, 'max' => 200, 'unit' => 'U/L'],
                    'Mg' => ['min' => 0.74, 'max' => 1.07, 'unit' => 'mmol/L'],
                    'Na' => ['min' => 139, 'max' => 154, 'unit' => 'mmol/L'],
                    'Na_K' => ['min' => null, 'max' => null, 'unit' => ''],
                    'NH3' => ['min' => null, 'max' => null, 'unit' => 'umol/L'],
                    'OSMOL' => ['min' => 285, 'max' => 310, 'unit' => 'mOsm/kg'],
                    'PHBR' => ['min' => null, 'max' => null, 'unit' => 'mmol/L'],
                    'PHOS' => ['min' => 0.81, 'max' => 1.61, 'unit' => 'mmol/L'],
                    'TBIL' => ['min' => 0, 'max' => 7.5, 'unit' => 'umol/L'],
                    'TP' => ['min' => 54, 'max' => 80, 'unit' => 'g/L'],
                    'TRIG' => ['min' => 0.0, 'max' => 1.13, 'unit' => 'mmol/L'],
                    'UCREA' => ['min' => null, 'max' => null, 'unit' => 'mmol/L'],
                    'UPC' => ['min' => null, 'max' => null, 'unit' => ''],
                    'UPRO' => ['min' => null, 'max' => null, 'unit' => 'mg/L'],
                    'URIC' => ['min' => null, 'max' => null, 'unit' => 'umol/L'],
                ],
                'Chat' => [
                    'ALB' => ['min' => 24, 'max' => 39, 'unit' => 'g/L'],
                    'ALKP' => ['min' => 0, 'max' => 62, 'unit' => 'U/L'],
                    'ALT' => ['min' => 22, 'max' => 84, 'unit' => 'U/L'],
                    'AMYL' => ['min' => 500, 'max' => 1800, 'unit' => 'U/L'],
                    'AST' => ['min' => 0, 'max' => 38, 'unit' => 'U/L'],
                    'UREE' => ['min' => 7.1, 'max' => 12.5, 'unit' => 'mmol/L'],
                    'Ca' => ['min' => 2.20, 'max' => 2.80, 'unit' => 'mmol/L'],
                    'CHOL' => ['min' => 2.5, 'max' => 5.0, 'unit' => 'mmol/L'],
                    'CK' => ['min' => 0, 'max' => 187, 'unit' => 'U/L'],
                    'Cl' => ['min' => 107, 'max' => 120, 'unit' => 'mmol/L'],
                    'CREA' => ['min' => 71, 'max' => 168, 'unit' => 'umol/L'],
                    'UREA_CREA' => ['min' => null, 'max' => null, 'unit' => ''],
                    'FRU' => ['min' => null, 'max' => null, 'unit' => 'umol/L'],
                    'GGT' => ['min' => 0, 'max' => 2, 'unit' => 'U/L'],
                    'GLOB' => ['min' => 25, 'max' => 45, 'unit' => 'g/L'],
                    'ALB_GLOB' => ['min' => null, 'max' => null, 'unit' => ''],
                    'GLU' => ['min' => 3.9, 'max' => 6.1, 'unit' => 'mmol/L'],
                    'K' => ['min' => 3.5, 'max' => 5.8, 'unit' => 'mmol/L'],
                    'LAC' => ['min' => 0.0, 'max' => 2.5, 'unit' => 'mmol/L'],
                    'LDH' => ['min' => 0, 'max' => 395, 'unit' => 'U/L'],
                    'LIPA' => ['min' => 0, 'max' => 200, 'unit' => 'U/L'],
                    'Mg' => ['min' => 0.74, 'max' => 1.07, 'unit' => 'mmol/L'],
                    'Na' => ['min' => 147, 'max' => 162, 'unit' => 'mmol/L'],
                    'Na_K' => ['min' => null, 'max' => null, 'unit' => ''],
                    'NH3' => ['min' => null, 'max' => null, 'unit' => 'umol/L'],
                    'OSMOL' => ['min' => 285, 'max' => 310, 'unit' => 'mOsm/kg'],
                    'PHBR' => ['min' => null, 'max' => null, 'unit' => 'mmol/L'],
                    'PHOS' => ['min' => 0.81, 'max' => 1.94, 'unit' => 'mmol/L'],
                    'TBIL' => ['min' => 0, 'max' => 10.3, 'unit' => 'umol/L'],
                    'TP' => ['min' => 54, 'max' => 82, 'unit' => 'g/L'],
                    'TRIG' => ['min' => 0.0, 'max' => 1.14, 'unit' => 'mmol/L'],
                    'UCREA' => ['min' => null, 'max' => null, 'unit' => 'mmol/L'],
                    'UPC' => ['min' => null, 'max' => null, 'unit' => ''],
                    'UPRO' => ['min' => null, 'max' => null, 'unit' => 'mg/L'],
                    'URIC' => ['min' => null, 'max' => null, 'unit' => 'umol/L'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultHemogrammeSettings(): array
    {
        return [
            'species_options' => ['Bovin', 'Ovin', 'Caprin', 'Equin', 'Chien', 'Chat'],
            'params' => [
                ['key' => 'GR', 'label' => 'GR', 'group' => 'erythrocytes', 'enabled' => true],
                ['key' => 'HCT', 'label' => 'HCT', 'group' => 'erythrocytes', 'enabled' => true],
                ['key' => 'HGB', 'label' => 'HGB', 'group' => 'erythrocytes', 'enabled' => true],
                ['key' => 'VGM', 'label' => 'VGM', 'group' => 'erythrocytes', 'enabled' => true],
                ['key' => 'TCMH', 'label' => 'TCMH', 'group' => 'erythrocytes', 'enabled' => true],
                ['key' => 'CCMH', 'label' => 'CCMH', 'group' => 'erythrocytes', 'enabled' => true],
                ['key' => 'IDR', 'label' => 'IDR', 'group' => 'erythrocytes', 'enabled' => true],
                ['key' => 'PRETIC', 'label' => 'Reticul. %', 'group' => 'erythrocytes', 'enabled' => true],
                ['key' => 'RETIC', 'label' => 'Reticul.', 'group' => 'erythrocytes', 'enabled' => true],
                ['key' => 'GB', 'label' => 'GB', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'PNEU', 'label' => 'Neut. %', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'PLYM', 'label' => 'Lymph. %', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'PMONO', 'label' => 'Mono. %', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'PEOS', 'label' => 'Eosino. %', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'PBASO', 'label' => 'Baso. %', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'NEU', 'label' => 'Neutrophiles', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'LYM', 'label' => 'Lymphocytes', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'MONO', 'label' => 'Monocytes', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'EOS', 'label' => 'Eosinophiles', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'BASO', 'label' => 'Basophiles', 'group' => 'leucocytes', 'enabled' => true],
                ['key' => 'PGRA', 'label' => 'Gran. %', 'group' => 'leucocytes', 'enabled' => false],
                ['key' => 'GRA', 'label' => 'Granulocytes', 'group' => 'leucocytes', 'enabled' => false],
                ['key' => 'PLT', 'label' => 'Plaquettes', 'group' => 'plaquettes', 'enabled' => true],
                ['key' => 'VMP', 'label' => 'VMP', 'group' => 'plaquettes', 'enabled' => true],
                ['key' => 'IDP', 'label' => 'IDP', 'group' => 'plaquettes', 'enabled' => false],
                ['key' => 'PCT', 'label' => 'PCT', 'group' => 'plaquettes', 'enabled' => false],
            ],
            'norms' => [
                'Bovin' => [
                    'GR' => ['min' => 5.0, 'max' => 10.0, 'unit' => 'T/L'],
                    'HCT' => ['min' => 22, 'max' => 38, 'unit' => '%'],
                    'HGB' => ['min' => 8.0, 'max' => 14.0, 'unit' => 'g/dL'],
                    'VGM' => ['min' => 40, 'max' => 60, 'unit' => 'fL'],
                    'TCMH' => ['min' => 11.0, 'max' => 17.0, 'unit' => 'pg'],
                    'CCMH' => ['min' => 30, 'max' => 36, 'unit' => 'g/dL'],
                    'IDR' => ['min' => 15, 'max' => 20, 'unit' => '%'],
                    'PRETIC' => ['min' => null, 'max' => null, 'unit' => '%'],
                    'RETIC' => ['min' => null, 'max' => null, 'unit' => 'G/L'],
                    'GB' => ['min' => 4.0, 'max' => 12.0, 'unit' => 'G/L'],
                    'PNEU' => ['min' => 10, 'max' => 50, 'unit' => '%'],
                    'PLYM' => ['min' => 40, 'max' => 75, 'unit' => '%'],
                    'PMONO' => ['min' => 2, 'max' => 7, 'unit' => '%'],
                    'PEOS' => ['min' => 0, 'max' => 20, 'unit' => '%'],
                    'PBASO' => ['min' => 0, 'max' => 2, 'unit' => '%'],
                    'NEU' => ['min' => 0.6, 'max' => 4.0, 'unit' => 'G/L'],
                    'LYM' => ['min' => 2.5, 'max' => 7.5, 'unit' => 'G/L'],
                    'MONO' => ['min' => 0.0, 'max' => 0.8, 'unit' => 'G/L'],
                    'EOS' => ['min' => 0.0, 'max' => 2.4, 'unit' => 'G/L'],
                    'BASO' => ['min' => 0.0, 'max' => 0.2, 'unit' => 'G/L'],
                    'PGRA' => ['min' => 0, 'max' => 10, 'unit' => '%'],
                    'GRA' => ['min' => 0.0, 'max' => 1.0, 'unit' => 'G/L'],
                    'PLT' => ['min' => 100, 'max' => 800, 'unit' => 'G/L'],
                    'VMP' => ['min' => 5.0, 'max' => 15.0, 'unit' => 'fL'],
                    'IDP' => ['min' => 10, 'max' => 25, 'unit' => '%'],
                    'PCT' => ['min' => 0.1, 'max' => 1.2, 'unit' => '%'],
                ],
                'Chien' => [
                    'GR' => ['min' => 5.5, 'max' => 8.5, 'unit' => 'T/L'],
                    'HCT' => ['min' => 37, 'max' => 55, 'unit' => '%'],
                    'HGB' => ['min' => 12.0, 'max' => 18.0, 'unit' => 'g/dL'],
                    'VGM' => ['min' => 60, 'max' => 77, 'unit' => 'fL'],
                    'TCMH' => ['min' => 19.0, 'max' => 23.0, 'unit' => 'pg'],
                    'CCMH' => ['min' => 31, 'max' => 34, 'unit' => 'g/dL'],
                    'IDR' => ['min' => 12, 'max' => 17, 'unit' => '%'],
                    'PRETIC' => ['min' => 0.0, 'max' => 1.5, 'unit' => '%'],
                    'RETIC' => ['min' => 0, 'max' => 80, 'unit' => 'G/L'],
                    'GB' => ['min' => 6.0, 'max' => 17.0, 'unit' => 'G/L'],
                    'PNEU' => ['min' => 60, 'max' => 77, 'unit' => '%'],
                    'PLYM' => ['min' => 12, 'max' => 30, 'unit' => '%'],
                    'PMONO' => ['min' => 3, 'max' => 10, 'unit' => '%'],
                    'PEOS' => ['min' => 2, 'max' => 10, 'unit' => '%'],
                    'PBASO' => ['min' => 0, 'max' => 1, 'unit' => '%'],
                    'NEU' => ['min' => 3.0, 'max' => 11.5, 'unit' => 'G/L'],
                    'LYM' => ['min' => 1.0, 'max' => 4.8, 'unit' => 'G/L'],
                    'MONO' => ['min' => 0.15, 'max' => 1.35, 'unit' => 'G/L'],
                    'EOS' => ['min' => 0.10, 'max' => 1.25, 'unit' => 'G/L'],
                    'BASO' => ['min' => 0.0, 'max' => 0.1, 'unit' => 'G/L'],
                    'PGRA' => ['min' => 0, 'max' => 5, 'unit' => '%'],
                    'GRA' => ['min' => 0.0, 'max' => 0.5, 'unit' => 'G/L'],
                    'PLT' => ['min' => 200, 'max' => 500, 'unit' => 'G/L'],
                    'VMP' => ['min' => 6.0, 'max' => 12.0, 'unit' => 'fL'],
                    'IDP' => ['min' => 15, 'max' => 25, 'unit' => '%'],
                    'PCT' => ['min' => 0.10, 'max' => 0.60, 'unit' => '%'],
                ],
                'Chat' => [
                    'GR' => ['min' => 5.0, 'max' => 10.0, 'unit' => 'T/L'],
                    'HCT' => ['min' => 24, 'max' => 45, 'unit' => '%'],
                    'HGB' => ['min' => 8.0, 'max' => 15.0, 'unit' => 'g/dL'],
                    'VGM' => ['min' => 39, 'max' => 55, 'unit' => 'fL'],
                    'TCMH' => ['min' => 12.0, 'max' => 17.0, 'unit' => 'pg'],
                    'CCMH' => ['min' => 30, 'max' => 36, 'unit' => 'g/dL'],
                    'IDR' => ['min' => 14, 'max' => 18, 'unit' => '%'],
                    'PRETIC' => ['min' => 0.0, 'max' => 0.4, 'unit' => '%'],
                    'RETIC' => ['min' => 0, 'max' => 50, 'unit' => 'G/L'],
                    'GB' => ['min' => 5.5, 'max' => 19.5, 'unit' => 'G/L'],
                    'PNEU' => ['min' => 35, 'max' => 75, 'unit' => '%'],
                    'PLYM' => ['min' => 20, 'max' => 55, 'unit' => '%'],
                    'PMONO' => ['min' => 1, 'max' => 4, 'unit' => '%'],
                    'PEOS' => ['min' => 2, 'max' => 12, 'unit' => '%'],
                    'PBASO' => ['min' => 0, 'max' => 1, 'unit' => '%'],
                    'NEU' => ['min' => 2.5, 'max' => 12.5, 'unit' => 'G/L'],
                    'LYM' => ['min' => 1.5, 'max' => 7.0, 'unit' => 'G/L'],
                    'MONO' => ['min' => 0.05, 'max' => 0.85, 'unit' => 'G/L'],
                    'EOS' => ['min' => 0.10, 'max' => 1.9, 'unit' => 'G/L'],
                    'BASO' => ['min' => 0.0, 'max' => 0.1, 'unit' => 'G/L'],
                    'PGRA' => ['min' => 0, 'max' => 5, 'unit' => '%'],
                    'GRA' => ['min' => 0.0, 'max' => 0.5, 'unit' => 'G/L'],
                    'PLT' => ['min' => 200, 'max' => 600, 'unit' => 'G/L'],
                    'VMP' => ['min' => 7.0, 'max' => 12.0, 'unit' => 'fL'],
                    'IDP' => ['min' => 15, 'max' => 25, 'unit' => '%'],
                    'PCT' => ['min' => 0.14, 'max' => 0.72, 'unit' => '%'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultBseLaitierSettings(): array
    {
        return [
            'prix_mal_diar1' => 50,
            'prix_mal_diar2et3' => 75,
            'prix_mal_diar4' => 75,
            'prix_perf_diar' => 30,
            'prix_mal_respi' => 75,
            'prix_mal_omphalite' => 50,
            'prix_mort_diar1' => 250,
            'prix_mort_diar2et3' => 350,
            'prix_mort_diar4' => 350,
            'prix_mort_respi' => 400,
            'prix_mort_omphalite' => 300,
            'prix_veau_ivv' => 3,
            'prix_ha_foin' => 600,
            'prix_ha_ensilage_herbe' => 800,
            'prix_ha_ensilage_mais' => 1000,
            'prix_production_cereales_tonnes' => 150,
            'txt_tx_mortalite_neonatale_s' => '',
            'txt_tx_mortalite_neonatale_ns' => '',
            'txt_tx_mammites_s' => '',
            'txt_tx_mammites_ns' => '',
            'txt_tx_boiteries_s' => '',
            'txt_tx_boiteries_ns' => '',
            'txt_tx_metaboliques_s' => '',
            'txt_tx_metaboliques_ns' => '',
            'txt_cout_reproduction_s' => '',
            'txt_cout_reproduction_ns' => '',
            'txt_cout_alimentaire_vache_l_s' => '',
            'txt_cout_alimentaire_vache_l_ns' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultBseAllaitantSettings(): array
    {
        return [
            'prix_mal_diar1' => 50,
            'prix_mal_diar2et3' => 75,
            'prix_mal_diar4' => 75,
            'prix_perf_diar' => 30,
            'prix_mal_respi' => 75,
            'prix_mal_omphalite' => 50,
            'prix_mort_diar1' => 250,
            'prix_mort_diar2et3' => 350,
            'prix_mort_diar4' => 350,
            'prix_mort_respi' => 400,
            'prix_mort_omphalite' => 300,
            'prix_mort_autres' => 300,
            'prix_mort_subite' => 300,
            'prix_veau_ivv' => 3,
            'prix_veau_avortement' => 200,
            'prix_veau_accident_velage' => 200,
            'prix_ha_foin' => 600,
            'prix_ha_ensilage_herbe' => 800,
            'prix_ha_ensilage_mais' => 1000,
            'prix_production_cereales_tonnes' => 150,
            'txt_tx_mortalite_total_veaux_s' => '',
            'txt_tx_mortalite_total_veaux_ns' => '',
            'txt_tx_diarrhee_veaux_total_s' => '',
            'txt_tx_diarrhee_veaux_total_ns' => '',
            'txt_tx_respi_veaux_s' => '',
            'txt_tx_respi_veaux_ns' => '',
            'txt_tx_omphalite_veaux_s' => '',
            'txt_tx_omphalite_veaux_ns' => '',
            'txt_ivv_s' => '',
            'txt_ivv_ns' => '',
            'txt_cout_alimentaire_vache_s' => '',
            'txt_cout_alimentaire_vache_ns' => '',
        ];
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array{name: string, results: array<string, string>}>
     */
    private static function sampleRows(int $count, array $keys): array
    {
        return collect(range(1, $count))
            ->map(fn (): array => [
                'name' => '',
                'results' => collect($keys)->mapWithKeys(fn (string $key): array => [$key => '0'])->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{code: string, label: string, dose: string|null, intermediate_min: int, sensitive_min: int, enabled: bool}>
     */
    private static function defaultAntibiotics(): array
    {
        return [
            ['code' => 'AMX', 'label' => 'Amoxicilline', 'dose' => '25 ug', 'intermediate_min' => 17, 'sensitive_min' => 21, 'enabled' => true],
            ['code' => 'AMC', 'label' => 'Amoxicilline-ac. clavulanique', 'dose' => '20 + 10 ug', 'intermediate_min' => 17, 'sensitive_min' => 21, 'enabled' => true],
            ['code' => 'GM', 'label' => 'Gentamicine', 'dose' => '15 ug', 'intermediate_min' => 16, 'sensitive_min' => 18, 'enabled' => true],
            ['code' => 'GEN', 'label' => 'Gentamicine', 'dose' => '500 ug', 'intermediate_min' => 16, 'sensitive_min' => 18, 'enabled' => true],
            ['code' => 'K', 'label' => 'Kanamycine', 'dose' => '30 ug', 'intermediate_min' => 14, 'sensitive_min' => 18, 'enabled' => true],
            ['code' => 'NEO', 'label' => 'Neomycine', 'dose' => '30 ug', 'intermediate_min' => 13, 'sensitive_min' => 17, 'enabled' => true],
            ['code' => 'CN', 'label' => 'Cefalexine', 'dose' => '30 ug', 'intermediate_min' => 18, 'sensitive_min' => 21, 'enabled' => true],
            ['code' => 'CPR', 'label' => 'Cefapirine', 'dose' => '30 ug', 'intermediate_min' => 18, 'sensitive_min' => 21, 'enabled' => true],
            ['code' => 'CZN', 'label' => 'Cefazoline', 'dose' => '30 ug', 'intermediate_min' => 18, 'sensitive_min' => 21, 'enabled' => true],
            ['code' => 'CNM', 'label' => 'Cefalonium', 'dose' => '30 ug', 'intermediate_min' => 18, 'sensitive_min' => 21, 'enabled' => true],
            ['code' => 'FOX', 'label' => 'Cefoxitine', 'dose' => '30 ug', 'intermediate_min' => 18, 'sensitive_min' => 22, 'enabled' => true],
            ['code' => 'XNL', 'label' => 'Ceftiofur', 'dose' => '30 ug', 'intermediate_min' => 21, 'sensitive_min' => 25, 'enabled' => true],
            ['code' => 'CXM', 'label' => 'Cefuroxime', 'dose' => '30 ug', 'intermediate_min' => 18, 'sensitive_min' => 23, 'enabled' => true],
            ['code' => 'CEQ', 'label' => 'Cefquinome', 'dose' => '30 ug', 'intermediate_min' => 18, 'sensitive_min' => 22, 'enabled' => true],
            ['code' => 'L', 'label' => 'Lincomycine', 'dose' => '15 ug', 'intermediate_min' => 15, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'E', 'label' => 'Erythromycine', 'dose' => '15 ug', 'intermediate_min' => 14, 'sensitive_min' => 18, 'enabled' => true],
            ['code' => 'SP', 'label' => 'Spiramycine', 'dose' => '100 ug', 'intermediate_min' => 15, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'TY', 'label' => 'Tylosine', 'dose' => '30 ug', 'intermediate_min' => 15, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'TIP', 'label' => 'Tildipirosine', 'dose' => '60 ug', 'intermediate_min' => 15, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'P', 'label' => 'Penicilline', 'dose' => '6 ug', 'intermediate_min' => 17, 'sensitive_min' => 21, 'enabled' => true],
            ['code' => 'OX', 'label' => 'Oxacilline', 'dose' => '5 ug', 'intermediate_min' => 11, 'sensitive_min' => 13, 'enabled' => true],
            ['code' => 'FFC', 'label' => 'Florfenicol', 'dose' => '30 ug', 'intermediate_min' => 15, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'NA', 'label' => 'Acide nalidixique', 'dose' => '30 ug', 'intermediate_min' => 14, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'ENR', 'label' => 'Enrofloxacine', 'dose' => '5 ug', 'intermediate_min' => 17, 'sensitive_min' => 22, 'enabled' => true],
            ['code' => 'MAR', 'label' => 'Marbofloxacine', 'dose' => '5 ug', 'intermediate_min' => 17, 'sensitive_min' => 22, 'enabled' => true],
            ['code' => 'SXT', 'label' => 'Trimethoprime-sulfamide', 'dose' => '25 ug', 'intermediate_min' => 11, 'sensitive_min' => 16, 'enabled' => true],
            ['code' => 'TE', 'label' => 'Tetracycline', 'dose' => '30 ug', 'intermediate_min' => 15, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'CT', 'label' => 'Colistine', 'dose' => '10 ug', 'intermediate_min' => 11, 'sensitive_min' => 14, 'enabled' => true],
            ['code' => 'DAN', 'label' => 'Danofloxacine', 'dose' => '5 ug', 'intermediate_min' => 17, 'sensitive_min' => 22, 'enabled' => true],
            ['code' => 'RIF', 'label' => 'Rifamycine', 'dose' => null, 'intermediate_min' => 15, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'BLA', 'label' => 'Beta-lactamase', 'dose' => null, 'intermediate_min' => 1, 'sensitive_min' => 1, 'enabled' => true],
            ['code' => 'PS', 'label' => 'Penistrepto', 'dose' => null, 'intermediate_min' => 15, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'PIR', 'label' => 'Pirlimicine', 'dose' => null, 'intermediate_min' => 15, 'sensitive_min' => 19, 'enabled' => true],
            ['code' => 'CFK', 'label' => 'Cefakana', 'dose' => null, 'intermediate_min' => 18, 'sensitive_min' => 22, 'enabled' => true],
            ['code' => 'NBT', 'label' => 'NBT', 'dose' => null, 'intermediate_min' => 1, 'sensitive_min' => 1, 'enabled' => true],
            ['code' => 'NDP', 'label' => 'NDP', 'dose' => null, 'intermediate_min' => 1, 'sensitive_min' => 1, 'enabled' => true],
        ];
    }
}
