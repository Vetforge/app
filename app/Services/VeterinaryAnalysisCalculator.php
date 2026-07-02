<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\LegacyHtmlCleaner;

final class VeterinaryAnalysisCalculator
{
    private const DIARRHEA_PATHOGEN_TESTS = [
        'rotavirus' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
        'coronavirus' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
        'ecoli_k99' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
        'ecoli_cs31a' => ['Speed V-Diar', 'Speed V-Diar 4'],
        'clostridium_perfringens' => ['Quick Diar 5'],
        'cryptosporidies' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
        'giardia' => ['Kitvia'],
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function calculate(string $module, array $payload, array $settings): array
    {
        return match ($module) {
            'coproscopie-parasitaire' => self::coproscopy($payload, $settings),
            'diarrhee-neonatale' => self::neonatalDiarrhea($payload, $settings),
            'gaz-du-sang' => self::bloodGas($payload, $settings),
            'comptage-cellulaire' => self::cellCount($payload, $settings),
            'diagnostic-bacteriologique' => self::bacteriology($payload, $settings),
            'bse-laitier' => self::bseLaitier($payload, $settings),
            'bse-allaitant' => self::bseAllaitant($payload, $settings),
            'analyse-diverse', 'autopsie', 'compte-rendu', 'tests-rapides', 'tests-biochimie', 'hemogramme' => [],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function coproscopy(array $payload, array $settings): array
    {
        $samples = self::arrayOfArrays($payload['samples'] ?? []);
        $parasites = self::arrayOfArrays($settings['parasites'] ?? []);
        $species = (string) ($payload['species'] ?? '');
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $positiveByParasite = [];

        foreach ($parasites as $parasite) {
            $key = (string) ($parasite['key'] ?? '');
            $requiredOption = (string) ($parasite['requires_option'] ?? '');

            if ($key === '' || ($parasite['enabled'] ?? true) === false || ! self::matchesSpecies($parasite, $species) || ($requiredOption !== '' && ($options[$requiredOption] ?? false) !== true)) {
                continue;
            }

            $values = collect($samples)
                ->map(fn (array $sample): int => (int) data_get($sample, "results.$key", 0))
                ->filter(fn (int $value): bool => $value > 0)
                ->values();

            if ($values->isNotEmpty()) {
                $positiveByParasite[$key] = [
                    'label' => $parasite['label'] ?? $key,
                    'positive_samples' => $values->count(),
                    'max_score' => $values->max(),
                ];
            }
        }

        return [
            'positive_count' => count($positiveByParasite),
            'positive_parasites' => $positiveByParasite,
            'sample_count' => count($samples),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function matchesSpecies(array $item, string $species): bool
    {
        if ($species === '') {
            return true;
        }

        $speciesList = $item['species'] ?? [];

        if (! is_array($speciesList) || $speciesList === []) {
            return true;
        }

        return in_array($species, array_map('strval', $speciesList), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function neonatalDiarrhea(array $payload, array $settings): array
    {
        $pathogens = self::arrayOfArrays($settings['pathogens'] ?? []);
        $results = is_array($payload['pathogens'] ?? null) ? $payload['pathogens'] : [];
        $testName = self::normalizedDiarrheaTestName((string) ($payload['test_name'] ?? ''));
        $coccidiosisTest = (bool) ($payload['coccidiosis_test'] ?? false);
        $positives = [];

        foreach ($pathogens as $pathogen) {
            $key = (string) ($pathogen['key'] ?? '');
            $value = (string) ($results[$key] ?? '0');

            if (
                $key !== ''
                && $value !== '0'
                && ($pathogen['enabled'] ?? true) !== false
                && self::matchesDiarrheaPathogen($pathogen, $testName, $coccidiosisTest)
            ) {
                $positives[$key] = [
                    'label' => $pathogen['label'] ?? $key,
                    'value' => $value,
                ];
            }
        }

        return [
            'positive_count' => count($positives),
            'positives' => $positives,
        ];
    }

    /**
     * @param  array<string, mixed>  $pathogen
     */
    private static function matchesDiarrheaPathogen(array $pathogen, string $testName, bool $coccidiosisTest): bool
    {
        $key = (string) ($pathogen['key'] ?? '');
        $requiredOption = (string) ($pathogen['requires_option'] ?? '');

        if (($requiredOption === 'coccidiosis_test' || $key === 'coccidies') && ! $coccidiosisTest) {
            return false;
        }

        $knownTests = ['kitvia', 'speed v-diar', 'quick diar 5'];

        if ($testName === '' || ! in_array($testName, $knownTests, true)) {
            return true;
        }

        $tests = $pathogen['tests'] ?? self::DIARRHEA_PATHOGEN_TESTS[$key] ?? [];

        if (! is_array($tests) || $tests === []) {
            return true;
        }

        return in_array(
            $testName,
            array_map(fn (mixed $test): string => self::normalizedDiarrheaTestName((string) $test), $tests),
            true,
        );
    }

    private static function normalizedDiarrheaTestName(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return match (true) {
            str_contains($value, 'kitvia') => 'kitvia',
            str_contains($value, 'quick diar') => 'quick diar 5',
            str_contains($value, 'speed v-diar') => 'speed v-diar',
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function bloodGas(array $payload, array $settings): array
    {
        $species = (string) ($payload['species'] ?? '');
        $speciesMeta = self::bloodGasSpeciesMeta($species, $settings);
        $weight = self::float($payload['weight'] ?? null);
        $ph = self::float($payload['ph'] ?? null);
        $hco3 = self::float($payload['hco3'] ?? null);
        $glycemia = self::float($payload['glycemia'] ?? null);
        $dehydration = self::float($payload['dehydration'] ?? null);

        if ($speciesMeta['calculation_profile'] === 'ruminant') {
            $enophtalmie = self::float($payload['enophtalmie'] ?? null);
            $dehydration = $dehydration ?: ((1.7 * $enophtalmie) + 0.38);
            $baseHco3 = 24.8;
            $basePh = 7.4;
            $glucoseDeficit = $glycemia < 54 ? 100 : ($glycemia < 90 ? 50 : 0);
        } else {
            $baseHco3 = 25.6;
            $basePh = 7.37;
            $glucoseDeficit = $glycemia < 70 ? 100 : ($glycemia < 120 ? 50 : 0);
        }

        $bicarbonateDeficit = -(((($hco3 - $baseHco3) + 16.2 * ($ph - $basePh)) * ($weight * 0.6)) * 84 / 1000);
        $volumeDeficit = ($weight * $dehydration) / 100;
        $perfusions = self::numericMap($payload['perfusions'] ?? []);
        $apports = self::perfusionContributions($perfusions, self::arrayOfArrays($settings['perfusions'] ?? []));
        $norms = self::bloodGasNorms($settings, $speciesMeta['norm_key'], $species);

        return [
            'species' => $speciesMeta['label'],
            'calculation_profile' => $speciesMeta['calculation_profile'],
            'norms' => $norms,
            'interpretations' => self::bloodGasInterpretations($payload, $norms),
            'dehydration' => round($dehydration, 1),
            'deficit_bicarbonate_g' => round($bicarbonateDeficit),
            'deficit_glucose_g' => $glucoseDeficit,
            'volume_deficit_l' => round($volumeDeficit, 1),
            'apports' => $apports,
            'restes' => [
                'bicarbonate_g' => round($bicarbonateDeficit - $apports['bicarbonate_g']),
                'glucose_g' => round($glucoseDeficit - $apports['glucose_g']),
                'volume_l' => round($volumeDeficit - $apports['volume_l'], 1),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{label: string, norm_key: string, calculation_profile: string}
     */
    private static function bloodGasSpeciesMeta(string $species, array $settings): array
    {
        foreach (self::arrayOfArrays($settings['species_options'] ?? []) as $option) {
            $value = (string) ($option['value'] ?? '');

            if ($value !== '' && $value === $species) {
                return [
                    'label' => (string) ($option['label'] ?? $value),
                    'norm_key' => (string) ($option['norm_key'] ?? $value),
                    'calculation_profile' => (string) ($option['calculation_profile'] ?? self::defaultBloodGasProfile($value)),
                ];
            }
        }

        $fallbackSpecies = $species !== '' ? $species : 'Bovin';

        return [
            'label' => $fallbackSpecies,
            'norm_key' => $fallbackSpecies,
            'calculation_profile' => self::defaultBloodGasProfile($fallbackSpecies),
        ];
    }

    private static function defaultBloodGasProfile(string $species): string
    {
        return in_array($species, ['Bovin', 'Ovin', 'Caprin'], true) ? 'ruminant' : 'equine';
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, array{0: float, 1: float}>
     */
    private static function bloodGasNorms(array $settings, string $normKey, string $species): array
    {
        $allNorms = is_array($settings['norms'] ?? null) ? $settings['norms'] : [];
        $legacyKeys = [
            'Bovin' => 'bovine',
            'Equin' => 'equine',
        ];

        foreach (array_unique([$normKey, $species, $legacyKeys[$species] ?? '']) as $candidate) {
            if ($candidate !== '' && is_array($allNorms[$candidate] ?? null)) {
                return self::normalizeBloodGasNorms($allNorms[$candidate]);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $norms
     * @return array<string, array{0: float, 1: float}>
     */
    private static function normalizeBloodGasNorms(array $norms): array
    {
        $normalized = [];

        foreach (['ph', 'pco2', 'hco3', 'na', 'k', 'cl', 'glycemia'] as $field) {
            $range = $norms[$field] ?? null;

            if (! is_array($range) || count($range) < 2) {
                continue;
            }

            $normalized[$field] = [self::float($range[0] ?? null), self::float($range[1] ?? null)];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array{0: float, 1: float}>  $norms
     * @return array<string, array{value: float, min: float, max: float, status: string}>
     */
    private static function bloodGasInterpretations(array $payload, array $norms): array
    {
        $interpretations = [];

        foreach ($norms as $field => $range) {
            $value = self::nullableFloat($payload[$field] ?? null);

            if ($value === null) {
                continue;
            }

            $interpretations[$field] = [
                'value' => $value,
                'min' => $range[0],
                'max' => $range[1],
                'status' => $value < $range[0] ? 'low' : ($value > $range[1] ? 'high' : 'normal'),
            ];
        }

        return $interpretations;
    }

    /**
     * @param  array<string, float>  $perfusions
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array{bicarbonate_g: float, glucose_g: float, volume_l: float}
     */
    private static function perfusionContributions(array $perfusions, array $definitions): array
    {
        $apports = ['bicarbonate_g' => 0.0, 'glucose_g' => 0.0, 'volume_l' => 0.0];

        foreach ($definitions as $definition) {
            $key = (string) ($definition['key'] ?? '');
            $quantity = $perfusions[$key] ?? 0.0;

            $apports['bicarbonate_g'] += $quantity * self::float($definition['bicarbonate'] ?? null);
            $apports['glucose_g'] += $quantity * self::float($definition['glucose'] ?? null);
            $apports['volume_l'] += $quantity * self::float($definition['volume'] ?? null);
        }

        return [
            'bicarbonate_g' => round($apports['bicarbonate_g']),
            'glucose_g' => round($apports['glucose_g']),
            'volume_l' => round($apports['volume_l'], 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function cellCount(array $payload, array $settings): array
    {
        $samples = self::arrayOfArrays($payload['samples'] ?? []);
        $counts = collect($samples)
            ->map(fn (array $sample): float => self::float($sample['count'] ?? null))
            ->filter(fn (float $count): bool => $count > 0)
            ->values();
        $alert = self::float(data_get($settings, 'norms.alert_threshold'));
        $critical = self::float(data_get($settings, 'norms.critical_threshold'));

        return [
            'sample_count' => count($samples),
            'average' => $counts->isEmpty() ? null : round($counts->avg(), 1),
            'max' => $counts->isEmpty() ? null : $counts->max(),
            'alert_samples' => $counts->filter(fn (float $count): bool => $count >= $alert)->count(),
            'critical_samples' => $counts->filter(fn (float $count): bool => $count >= $critical)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function bacteriology(array $payload, array $settings): array
    {
        $antibiotics = collect(self::arrayOfArrays($settings['antibiotics'] ?? []))
            ->filter(fn (array $antibiotic): bool => ($antibiotic['enabled'] ?? true) !== false)
            ->keyBy(fn (array $antibiotic): string => (string) ($antibiotic['code'] ?? ''));
        $germs = self::arrayOfArrays($payload['germs'] ?? []);
        $interpreted = [];

        foreach ($germs as $index => $germ) {
            $rows = [];
            $diameters = is_array($germ['antibiotics'] ?? null) ? $germ['antibiotics'] : [];

            foreach ($diameters as $code => $diameter) {
                $antibiotic = $antibiotics->get((string) $code);

                if (! $antibiotic) {
                    continue;
                }

                $diameterValue = self::float($diameter);
                $intermediateMin = self::float($antibiotic['intermediate_min'] ?? null);
                $sensitiveMin = self::float($antibiotic['sensitive_min'] ?? null);

                $rows[] = [
                    'code' => $code,
                    'label' => $antibiotic['label'] ?? $code,
                    'diameter' => $diameterValue,
                    'interpretation' => $diameterValue >= $sensitiveMin ? 'S' : ($diameterValue >= $intermediateMin ? 'I' : 'R'),
                    'thresholds' => [
                        'intermediate_min' => $intermediateMin,
                        'sensitive_min' => $sensitiveMin,
                    ],
                ];
            }

            $interpreted[] = [
                'index' => $index + 1,
                'family' => $germ['family'] ?? null,
                'antibiotics' => $rows,
            ];
        }

        $germCount = isset($payload['germ_count']) ? (int) $payload['germ_count'] : count($germs);

        return [
            'germ_count' => $germCount,
            'contamination_status' => $germCount === 0 ? 'sterile' : ($germCount >= 3 ? 'contaminated' : null),
            'interpreted_germs' => $interpreted,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function bseLaitier(array $payload, array $settings): array
    {
        $race = (string) ($payload['race'] ?? 'autre');
        $nbVaches = self::float($payload['nb_vaches_productrices'] ?? null);
        $ivv = self::float($payload['ivv'] ?? null);
        $concentrationCellulaire = self::float($payload['concentration_cellulaire_moyen'] ?? null);
        $productionLait = self::float($payload['production_annuelle_lait'] ?? null);
        $prixLaitTonne = self::float($payload['prix_lait_tonne'] ?? null);
        $txButyreux = self::float($payload['tx_butyreux_moyen'] ?? null);
        $txProteique = self::float($payload['tx_proteique_moyen'] ?? null);
        $nbVeauxNes = self::float($payload['nb_veaux_nes_vivants'] ?? null);
        $nbAvortons = self::float($payload['nb_avortons'] ?? 0);
        $nbMorts0a7 = self::float($payload['nb_morts_0a7'] ?? 0);
        $nbMorts8aSevr = self::float($payload['nb_morts_8a_sevr'] ?? 0);
        $nbMammitesLocales = self::float($payload['nb_mammites_locales'] ?? 0);
        $nbMammitesLocalesNG = self::float($payload['nb_mammites_locales_non_gueries'] ?? 0);
        $nbMammitesAigues = self::float($payload['nb_mammites_aigues'] ?? 0);
        $nbMammitesAiguesNG = self::float($payload['nb_mammites_aigues_non_gueries'] ?? 0);
        $nbCci250 = self::float($payload['nb_cci250'] ?? 0);
        $nbBoiteries = self::float($payload['nb_boiteries'] ?? 0);
        $nbBoiteriesNG = self::float($payload['nb_boiteries_non_gueries'] ?? 0);
        $nbFievresDeLait = self::float($payload['nb_fievres_de_lait'] ?? 0);
        $nbFievresDeLaitNG = self::float($payload['nb_fievres_de_lait_non_gueries'] ?? 0);
        $nbNonDelivrances = self::float($payload['nb_non_delivrances'] ?? 0);
        $nbMetrites = self::float($payload['nb_metrites'] ?? 0);
        $nbCaillettes = self::float($payload['nb_caillettes'] ?? 0);
        $nbCaillettesNG = self::float($payload['nb_caillettes_non_gueries'] ?? 0);
        $nbCetoses = self::float($payload['nb_cetoses'] ?? 0);
        $nbAcidoses = self::float($payload['nb_acidoses'] ?? 0);
        $prixVeauxMale = self::float($payload['prix_veaux_male'] ?? 0);
        $prixVeauxFemelle = self::float($payload['prix_veaux_femelle'] ?? 0);
        $haFoin = self::float($payload['ha_foin'] ?? 0);
        $haEnsilageHerbe = self::float($payload['ha_ensilage_herbe'] ?? 0);
        $haEnsilageMais = self::float($payload['ha_ensilage_mais'] ?? 0);
        $productionCereales = self::float($payload['production_cereales_tonnes'] ?? 0);
        $achatCerealesEuros = self::float($payload['achat_cereales_euros'] ?? 0);
        $achatComplEuros = self::float($payload['achat_complementaire_euros'] ?? 0);
        $achatAmvEuros = self::float($payload['achat_amv_euros'] ?? 0);

        $prixLaitLitre = $prixLaitTonne / 1000;
        $nbMortaliteNeonatale = $nbMorts0a7 + $nbMorts8aSevr;
        $productionMoyenneVache = $nbVaches > 0 ? ($productionLait * 1000 / $nbVaches) : 0.0;
        $nbVeauxSevres = $nbVeauxNes - $nbMortaliteNeonatale;
        $veauParVache = $nbVaches > 0 ? ($nbVeauxNes / $nbVaches) : null;

        $txMammitesLocales = $nbVaches > 0 ? ($nbMammitesLocales / $nbVaches * 100) : null;
        $txMammitesAigues = $nbVaches > 0 ? ($nbMammitesAigues / $nbVaches * 100) : null;
        $txCci250 = $nbVaches > 0 ? ($nbCci250 / $nbVaches * 100) : null;
        $txBoiteries = $nbVaches > 0 ? ($nbBoiteries / $nbVaches * 100) : null;
        $txFievresDeLait = $nbVaches > 0 ? ($nbFievresDeLait / $nbVaches * 100) : null;
        $txNonDelivrances = $nbVaches > 0 ? ($nbNonDelivrances / $nbVaches * 100) : null;
        $txMetrites = $nbVaches > 0 ? ($nbMetrites / $nbVaches * 100) : null;
        $txCaillettes = $nbVaches > 0 ? ($nbCaillettes / $nbVaches * 100) : null;
        $txCetoses = $nbVaches > 0 ? ($nbCetoses / $nbVaches * 100) : null;
        $txAcidoses = $nbVaches > 0 ? ($nbAcidoses / $nbVaches * 100) : null;
        $txMammites = ($txMammitesLocales ?? 0) + ($txMammitesAigues ?? 0);
        $txMetaboliques = ($txFievresDeLait ?? 0) + ($txCaillettes ?? 0) + ($txCetoses ?? 0) + ($txAcidoses ?? 0);

        $txNonGuerisonMammitesLocales = $nbMammitesLocales > 0 ? ($nbMammitesLocalesNG / $nbMammitesLocales * 100) : null;
        $txNonGuerisonMammitesAigues = $nbMammitesAigues > 0 ? ($nbMammitesAiguesNG / $nbMammitesAigues * 100) : null;
        $txNonGuerisonBoiteries = $nbBoiteries > 0 ? ($nbBoiteriesNG / $nbBoiteries * 100) : null;
        $txNonGuerisonFievresDeLait = $nbFievresDeLait > 0 ? ($nbFievresDeLaitNG / $nbFievresDeLait * 100) : null;
        $txNonGuerisonCaillettes = $nbCaillettes > 0 ? ($nbCaillettesNG / $nbCaillettes * 100) : null;

        $txMortaliteNeonatale = $nbVeauxNes > 0 ? ($nbMortaliteNeonatale / $nbVeauxNes * 100) : null;
        $txMorts0a7 = $nbVeauxNes > 0 ? ($nbMorts0a7 / $nbVeauxNes * 100) : null;
        $txMorts8aSevr = $nbVeauxNes > 0 ? ($nbMorts8aSevr / $nbVeauxNes * 100) : null;
        $txAvortements = $nbVeauxNes > 0 ? ($nbAvortons / $nbVeauxNes * 100) : null;

        $coutMortaliteNeonatale = (($prixVeauxMale + $prixVeauxFemelle) / 2) * $nbMortaliteNeonatale;

        if ($concentrationCellulaire < 300) {
            $coutCct = $productionLait * (-3);
        } elseif ($concentrationCellulaire < 400) {
            $coutCct = $productionLait * 6.1;
        } else {
            $coutCct = $productionLait * 12.2;
        }

        $coutMammites = ($nbMammitesLocales * 150) + ($nbMammitesAigues * 250) + $coutCct;

        [$poids, $productionRef] = match ($race) {
            'Prim Holstein' => [145, 846],
            'Normande' => [187, 1186],
            default => [187, 1152],
        };

        $boiteriesLegeres = ($prixLaitLitre * 50) + ($poids * 1.96 / 100) + ($productionRef * 0.04 / 100) + 6;
        $boiteriesModerees = ($prixLaitLitre * 250) + ($poids * 5.88 / 100) + ($productionRef * 0.12 / 100) + 15;
        $boiteriesSeveres = ($prixLaitLitre * 800) + ($poids * 35.5 / 100) + ($productionRef * 3.5 / 100) + 30;
        $boiteriesNonDetectees = ($prixLaitLitre * 100) + 3;
        $coutBoiteries = ((($boiteriesLegeres * 0.6) + ($boiteriesModerees * 0.38) + ($boiteriesSeveres * 0.02)) * $nbBoiteries) + (3 * $nbBoiteries * $boiteriesNonDetectees);

        $coutFl = match ($race) {
            'Prim Holstein' => 350.0,
            'Normande' => 378.0,
            default => 382.0,
        };

        $coutAcid = $txButyreux < 42
            ? ($productionLait * 0.05 * $prixLaitTonne) + (200 * $nbAcidoses)
            : (200 * $nbAcidoses);

        $coutCeto = $txProteique < 32
            ? ($productionLait * 0.05 * $prixLaitTonne) + (200 * $nbCetoses)
            : (200 * $nbCetoses);

        $gainTp = $txProteique > 32 ? (5.49 * $productionLait) : ($txProteique < 32 ? (-5.49 * $productionLait) : 0.0);

        if ($txButyreux > 38) {
            $gainTb = 2.70 * ($txButyreux - 38) * $productionLait;
        } elseif ($txButyreux < 38) {
            $gainTb = 2.45 * ($txButyreux - 38) * $productionLait;
        } else {
            $gainTb = 0.0;
        }

        $coutMetaboliques = $coutCeto + $coutAcid + $coutFl + ($nbCaillettes * 500);
        $gainTaux = $gainTb + $gainTp;

        $ivvRef = match ($race) {
            'Prim Holstein' => 400,
            'Normande' => 385,
            default => 380,
        };
        $coutReproduction = ($ivv - $ivvRef) * $nbVaches;

        $prixHaFoin = self::float($settings['prix_ha_foin'] ?? 600);
        $prixHaEnsilageHerbe = self::float($settings['prix_ha_ensilage_herbe'] ?? 800);
        $prixHaEnsilageMais = self::float($settings['prix_ha_ensilage_mais'] ?? 1000);
        $prixProductionCereales = self::float($settings['prix_production_cereales_tonnes'] ?? 150);

        $coutAlimentaire = ($haFoin * $prixHaFoin) + ($haEnsilageHerbe * $prixHaEnsilageHerbe) + ($haEnsilageMais * $prixHaEnsilageMais) + ($productionCereales * $prixProductionCereales) + $achatCerealesEuros + $achatComplEuros + $achatAmvEuros;
        $coutAlimentaireVache = $productionLait > 0 ? ($coutAlimentaire / $productionLait) : null;

        return [
            'race' => $race,
            'production_moyenne_vache' => round($productionMoyenneVache),
            'nb_mortalite_neonatale' => $nbMortaliteNeonatale,
            'nb_veaux_sevres' => $nbVeauxSevres,
            'veau_par_vache' => $veauParVache !== null ? round($veauParVache, 2) : null,
            'tx_mammites_locales' => $txMammitesLocales !== null ? round($txMammitesLocales, 1) : null,
            'tx_mammites_aigues' => $txMammitesAigues !== null ? round($txMammitesAigues, 1) : null,
            'tx_mammites' => round($txMammites, 1),
            'tx_cci250' => $txCci250 !== null ? round($txCci250, 1) : null,
            'tx_boiteries' => $txBoiteries !== null ? round($txBoiteries, 1) : null,
            'tx_fievres_de_lait' => $txFievresDeLait !== null ? round($txFievresDeLait, 1) : null,
            'tx_non_delivrances' => $txNonDelivrances !== null ? round($txNonDelivrances, 1) : null,
            'tx_metrites' => $txMetrites !== null ? round($txMetrites, 1) : null,
            'tx_caillettes' => $txCaillettes !== null ? round($txCaillettes, 1) : null,
            'tx_cetoses' => $txCetoses !== null ? round($txCetoses, 1) : null,
            'tx_acidoses' => $txAcidoses !== null ? round($txAcidoses, 1) : null,
            'tx_metaboliques' => round($txMetaboliques, 1),
            'tx_non_guerison_mammites_locales' => $txNonGuerisonMammitesLocales !== null ? round($txNonGuerisonMammitesLocales, 1) : null,
            'tx_non_guerison_mammites_aigues' => $txNonGuerisonMammitesAigues !== null ? round($txNonGuerisonMammitesAigues, 1) : null,
            'tx_non_guerison_boiteries' => $txNonGuerisonBoiteries !== null ? round($txNonGuerisonBoiteries, 1) : null,
            'tx_non_guerison_fievres_de_lait' => $txNonGuerisonFievresDeLait !== null ? round($txNonGuerisonFievresDeLait, 1) : null,
            'tx_non_guerison_caillettes' => $txNonGuerisonCaillettes !== null ? round($txNonGuerisonCaillettes, 1) : null,
            'tx_mortalite_neonatale' => $txMortaliteNeonatale !== null ? round($txMortaliteNeonatale, 1) : null,
            'tx_morts_0a7' => $txMorts0a7 !== null ? round($txMorts0a7, 1) : null,
            'tx_morts_8a_sevr' => $txMorts8aSevr !== null ? round($txMorts8aSevr, 1) : null,
            'tx_avortements' => $txAvortements !== null ? round($txAvortements, 1) : null,
            'cout_mortalite_neonatale' => round($coutMortaliteNeonatale),
            'cout_cct' => round($coutCct),
            'cout_mammites' => round($coutMammites),
            'cout_boiteries' => round($coutBoiteries),
            'cout_metaboliques' => round($coutMetaboliques),
            'cout_fl' => $coutFl,
            'cout_acid' => round($coutAcid),
            'cout_ceto' => round($coutCeto),
            'gain_tp' => round($gainTp),
            'gain_tb' => round($gainTb),
            'gain_taux' => round($gainTaux),
            'cout_reproduction' => round($coutReproduction),
            'cout_alimentaire' => round($coutAlimentaire),
            'cout_alimentaire_vache' => $coutAlimentaireVache !== null ? round($coutAlimentaireVache, 2) : null,
            'commentaires' => [
                'tx_mortalite_neonatale' => [
                    's' => self::plainText($settings['txt_tx_mortalite_neonatale_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_tx_mortalite_neonatale_ns'] ?? ''),
                ],
                'tx_mammites' => [
                    's' => self::plainText($settings['txt_tx_mammites_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_tx_mammites_ns'] ?? ''),
                ],
                'tx_boiteries' => [
                    's' => self::plainText($settings['txt_tx_boiteries_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_tx_boiteries_ns'] ?? ''),
                ],
                'tx_metaboliques' => [
                    's' => self::plainText($settings['txt_tx_metaboliques_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_tx_metaboliques_ns'] ?? ''),
                ],
                'cout_reproduction' => [
                    's' => self::plainText($settings['txt_cout_reproduction_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_cout_reproduction_ns'] ?? ''),
                ],
                'cout_alimentaire_vache_l' => [
                    's' => self::plainText($settings['txt_cout_alimentaire_vache_l_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_cout_alimentaire_vache_l_ns'] ?? ''),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function bseAllaitant(array $payload, array $settings): array
    {
        $nbVaches = self::float($payload['nb_vaches_reproductrices'] ?? null);
        $ivv = self::float($payload['ivv'] ?? null);
        $nbVeauxNes = self::float($payload['nb_veaux_nes_vivants'] ?? null);
        $nbJumeaux = self::float($payload['nb_jumeaux'] ?? 0);
        $nbAccidentsVelage = self::float($payload['nb_accidents_velage'] ?? 0);
        $nbAvortons = self::float($payload['nb_avortons'] ?? 0);
        $nbMortsPost24h = self::float($payload['nb_morts_post24h'] ?? 0);
        $nbSevres = self::float($payload['nb_sevres'] ?? 0);
        $nbMortsAvant3Mois = self::float($payload['nb_morts_avant3_mois'] ?? 0);
        $nbMaladesDiar1 = self::float($payload['nb_malades_diar1'] ?? 0);
        $nbMortsDiar1 = self::float($payload['nb_morts_diar1'] ?? 0);
        $nbMaladesDiar2et3 = self::float($payload['nb_malades_diar2et3'] ?? 0);
        $nbMortsDiar2et3 = self::float($payload['nb_morts_diar2et3'] ?? 0);
        $nbMaladesDiar4 = self::float($payload['nb_malades_diar4'] ?? 0);
        $nbMortsDiar4 = self::float($payload['nb_morts_diar4'] ?? 0);
        $nbDiarPerf = self::float($payload['nb_diar_perf'] ?? 0);
        $nbMaladesRespi = self::float($payload['nb_malades_respi'] ?? 0);
        $nbMortsRespi = self::float($payload['nb_morts_respi'] ?? 0);
        $nbMaladesOmphalite = self::float($payload['nb_malades_omphalite'] ?? 0);
        $nbMortsOmphalite = self::float($payload['nb_morts_omphalite'] ?? 0);
        $nbMaladesAutres = self::float($payload['nb_malades_autres'] ?? 0);
        $nbMortsAutres = self::float($payload['nb_morts_autres'] ?? 0);
        $nbMortsSubites = self::float($payload['nb_morts_subites'] ?? 0);
        $nbVelagesLongs = self::float($payload['nb_velages_longs'] ?? 0);
        $nbCesariennes = self::float($payload['nb_cesariennes'] ?? 0);
        $nbNonDelivrances = self::float($payload['nb_non_delivrances'] ?? 0);
        $nbTorsions = self::float($payload['nb_torsions_retournements_matrices'] ?? 0);
        $nbMetrites = self::float($payload['nb_metrites'] ?? 0);
        $haFoin = self::float($payload['ha_foin'] ?? 0);
        $haEnsilageHerbe = self::float($payload['ha_ensilage_herbe'] ?? 0);
        $haEnsilageMais = self::float($payload['ha_ensilage_mais'] ?? 0);
        $productionCereales = self::float($payload['production_cereales_tonnes'] ?? 0);
        $achatCerealesEuros = self::float($payload['achat_cereales_euros'] ?? 0);
        $achatComplEuros = self::float($payload['achat_complementaire_euros'] ?? 0);
        $achatAmvEuros = self::float($payload['achat_amv_euros'] ?? 0);

        // Legacy data can contain either a post-24h total, a cause breakdown, or both.
        $nbMortsParCause = $nbMortsDiar1
            + $nbMortsDiar2et3
            + $nbMortsDiar4
            + $nbMortsRespi
            + $nbMortsOmphalite
            + $nbMortsAutres
            + $nbMortsSubites;
        $nbMortsPost24hRetenus = max($nbMortsPost24h, $nbMortsParCause, max(0.0, $nbMortsAvant3Mois - $nbAccidentsVelage));
        $nbMorts = $nbAccidentsVelage + $nbMortsPost24hRetenus;
        $nbMortinatalite = $nbAccidentsVelage + $nbAvortons;
        $nbVivants24h = $nbVeauxNes - $nbAccidentsVelage;
        $nbVeauxSevres = $nbSevres;

        $letaliteDiar1 = $nbMaladesDiar1 > 0 ? ($nbMortsDiar1 / $nbMaladesDiar1 * 100) : null;
        $letaliteDiar2et3 = $nbMaladesDiar2et3 > 0 ? ($nbMortsDiar2et3 / $nbMaladesDiar2et3 * 100) : null;
        $letaliteDiar4 = $nbMaladesDiar4 > 0 ? ($nbMortsDiar4 / $nbMaladesDiar4 * 100) : null;
        $letaliteRespi = $nbMaladesRespi > 0 ? ($nbMortsRespi / $nbMaladesRespi * 100) : null;
        $letaliteOmphalite = $nbMaladesOmphalite > 0 ? ($nbMortsOmphalite / $nbMaladesOmphalite * 100) : null;
        $letaliteAutres = $nbMaladesAutres > 0 ? ($nbMortsAutres / $nbMaladesAutres * 100) : null;

        $txMortaliteTotalVeaux = $nbVeauxNes > 0 ? ($nbMorts / $nbVeauxNes * 100) : null;
        $txMortinataliteVeaux = $nbVeauxNes > 0 ? (($nbAccidentsVelage + $nbAvortons) / $nbVeauxNes * 100) : null;
        $txMortalite24hVeaux = $nbVeauxNes > 0 ? ($nbMortsPost24hRetenus / $nbVeauxNes * 100) : null;
        $txVenduSevreVeaux = $nbVeauxNes > 0 ? ($nbSevres / $nbVeauxNes * 100) : null;
        $txMaladesDiarTotal = $nbVeauxNes > 0 ? (($nbMaladesDiar1 + $nbMaladesDiar2et3 + $nbMaladesDiar4) / $nbVeauxNes * 100) : null;
        $txMaladesRespi = $nbVeauxNes > 0 ? ($nbMaladesRespi / $nbVeauxNes * 100) : null;
        $txMaladesOmphalite = $nbVeauxNes > 0 ? ($nbMaladesOmphalite / $nbVeauxNes * 100) : null;
        $txMortinatalite = $nbVeauxNes > 0 ? ($nbMortinatalite / $nbVeauxNes * 100) : null;
        $txMortsDiar1 = $nbVeauxNes > 0 ? ($nbMortsDiar1 / $nbVeauxNes * 100) : null;
        $txMortsDiar2et3 = $nbVeauxNes > 0 ? ($nbMortsDiar2et3 / $nbVeauxNes * 100) : null;
        $txMortsDiar4 = $nbVeauxNes > 0 ? ($nbMortsDiar4 / $nbVeauxNes * 100) : null;
        $txMortsRespi = $nbVeauxNes > 0 ? ($nbMortsRespi / $nbVeauxNes * 100) : null;
        $txMortsOmphalite = $nbVeauxNes > 0 ? ($nbMortsOmphalite / $nbVeauxNes * 100) : null;
        $txAvortements = $nbVeauxNes > 0 ? ($nbAvortons / $nbVeauxNes * 100) : null;
        $txVelagesLongs = $nbVeauxNes > 0 ? ($nbVelagesLongs / $nbVeauxNes * 100) : null;
        $txCesariennes = $nbVeauxNes > 0 ? ($nbCesariennes / $nbVeauxNes * 100) : null;
        $txNonDelivrances = $nbVeauxNes > 0 ? ($nbNonDelivrances / $nbVeauxNes * 100) : null;
        $txTorsions = $nbVeauxNes > 0 ? ($nbTorsions / $nbVeauxNes * 100) : null;
        $txMetrites = $nbVeauxNes > 0 ? ($nbMetrites / $nbVeauxNes * 100) : null;

        $prolificite = ($nbVeauxNes - $nbJumeaux) > 0 ? ($nbVeauxNes / ($nbVeauxNes - $nbJumeaux) * 100) : null;
        $veauParVache = $nbVaches > 0 ? ($nbVeauxNes / $nbVaches) : null;
        $txVivants3Mois = $nbVaches > 0 ? (($nbVeauxNes - $nbMortsAvant3Mois) / $nbVaches) : null;
        $txVenduSevreVaches = $nbVaches > 0 ? ($nbSevres / $nbVaches * 100) : null;

        $prixMalDiar1 = self::float($settings['prix_mal_diar1'] ?? 50);
        $prixMalDiar2et3 = self::float($settings['prix_mal_diar2et3'] ?? 75);
        $prixMalDiar4 = self::float($settings['prix_mal_diar4'] ?? 75);
        $prixPerfDiar = self::float($settings['prix_perf_diar'] ?? 30);
        $prixMalRespi = self::float($settings['prix_mal_respi'] ?? 75);
        $prixMalOmphalite = self::float($settings['prix_mal_omphalite'] ?? 50);
        $prixMortDiar1 = self::float($settings['prix_mort_diar1'] ?? 250);
        $prixMortDiar2et3 = self::float($settings['prix_mort_diar2et3'] ?? 350);
        $prixMortDiar4 = self::float($settings['prix_mort_diar4'] ?? 350);
        $prixMortRespi = self::float($settings['prix_mort_respi'] ?? 400);
        $prixMortOmphalite = self::float($settings['prix_mort_omphalite'] ?? 300);
        $prixMortAutres = self::float($settings['prix_mort_autres'] ?? 300);
        $prixMortSubite = self::float($settings['prix_mort_subite'] ?? 300);
        $prixVeauIvv = self::float($settings['prix_veau_ivv'] ?? 3);
        $prixVeauAvortement = self::float($settings['prix_veau_avortement'] ?? 200);
        $prixVeauAccidentVelage = self::float($settings['prix_veau_accident_velage'] ?? 200);
        $prixHaFoin = self::float($settings['prix_ha_foin'] ?? 600);
        $prixHaEnsilageHerbe = self::float($settings['prix_ha_ensilage_herbe'] ?? 800);
        $prixHaEnsilageMais = self::float($settings['prix_ha_ensilage_mais'] ?? 1000);
        $prixProductionCereales = self::float($settings['prix_production_cereales_tonnes'] ?? 150);

        $nbMortsPost24hNonDetaillees = max(0.0, $nbMortsPost24hRetenus - $nbMortsParCause);
        $coutMortalite = ($nbAvortons * $prixVeauAvortement) + ($nbAccidentsVelage * $prixVeauAccidentVelage) + ($nbMortsDiar1 * $prixMortDiar1) + ($nbMortsDiar2et3 * $prixMortDiar2et3) + ($nbMortsDiar4 * $prixMortDiar4) + ($nbMortsRespi * $prixMortRespi) + ($nbMortsOmphalite * $prixMortOmphalite) + ($nbMortsAutres * $prixMortAutres) + ($nbMortsSubites * $prixMortSubite) + ($nbMortsPost24hNonDetaillees * $prixMortAutres);
        $coutIvv = ((($ivv - 365) * $nbVaches) / 270) * $prixVeauIvv;
        $coutDiarrhee = ($nbMaladesDiar1 * $prixMalDiar1) + ($nbMortsDiar1 * $prixMortDiar1) + ($nbMaladesDiar2et3 * $prixMalDiar2et3) + ($nbMortsDiar2et3 * $prixMortDiar2et3) + ($nbMaladesDiar4 * $prixMalDiar4) + ($nbMortsDiar4 * $prixMortDiar4) + ($nbDiarPerf * $prixPerfDiar);
        $coutRespi = ($nbMaladesRespi * $prixMalRespi) + ($nbMortsRespi * $prixMortRespi);
        $coutOmphalite = ($nbMaladesOmphalite * $prixMalOmphalite) + ($nbMortsOmphalite * $prixMortOmphalite);
        $coutAlimentaire = ($haFoin * $prixHaFoin) + ($haEnsilageHerbe * $prixHaEnsilageHerbe) + ($haEnsilageMais * $prixHaEnsilageMais) + ($productionCereales * $prixProductionCereales) + $achatCerealesEuros + $achatComplEuros + $achatAmvEuros;
        $coutAlimentaireVache = $nbVaches > 0 ? ($coutAlimentaire / $nbVaches) : null;

        return [
            'nb_morts' => $nbMorts,
            'nb_morts_post24h_retenus' => $nbMortsPost24hRetenus,
            'nb_morts_post24h_non_detaillees' => $nbMortsPost24hNonDetaillees,
            'nb_mortinatalite' => $nbMortinatalite,
            'nb_vivants24h' => $nbVivants24h,
            'nb_veaux_sevres' => $nbVeauxSevres,
            'veau_par_vache' => $veauParVache !== null ? round($veauParVache, 2) : null,
            'prolificite' => $prolificite !== null ? round($prolificite, 1) : null,
            'tx_vivants3_mois' => $txVivants3Mois !== null ? round($txVivants3Mois, 2) : null,
            'tx_vendu_sevre_vaches' => $txVenduSevreVaches !== null ? round($txVenduSevreVaches, 1) : null,
            'letalite_malades_diar1' => $letaliteDiar1 !== null ? round($letaliteDiar1, 1) : null,
            'letalite_malades_diar2et3' => $letaliteDiar2et3 !== null ? round($letaliteDiar2et3, 1) : null,
            'letalite_malades_diar4' => $letaliteDiar4 !== null ? round($letaliteDiar4, 1) : null,
            'letalite_malades_respi' => $letaliteRespi !== null ? round($letaliteRespi, 1) : null,
            'letalite_malades_omphalite' => $letaliteOmphalite !== null ? round($letaliteOmphalite, 1) : null,
            'letalite_malades_autres' => $letaliteAutres !== null ? round($letaliteAutres, 1) : null,
            'tx_mortalite_total_veaux' => $txMortaliteTotalVeaux !== null ? round($txMortaliteTotalVeaux, 1) : null,
            'tx_mortinatalite_veaux' => $txMortinataliteVeaux !== null ? round($txMortinataliteVeaux, 1) : null,
            'tx_mortalite24h_veaux' => $txMortalite24hVeaux !== null ? round($txMortalite24hVeaux, 1) : null,
            'tx_vendu_sevre_veaux' => $txVenduSevreVeaux !== null ? round($txVenduSevreVeaux, 1) : null,
            'tx_malades_diar_total' => $txMaladesDiarTotal !== null ? round($txMaladesDiarTotal, 1) : null,
            'tx_malades_respi' => $txMaladesRespi !== null ? round($txMaladesRespi, 1) : null,
            'tx_malades_omphalite' => $txMaladesOmphalite !== null ? round($txMaladesOmphalite, 1) : null,
            'tx_mortinatalite' => $txMortinatalite !== null ? round($txMortinatalite, 1) : null,
            'tx_morts_diar1' => $txMortsDiar1 !== null ? round($txMortsDiar1, 1) : null,
            'tx_morts_diar2et3' => $txMortsDiar2et3 !== null ? round($txMortsDiar2et3, 1) : null,
            'tx_morts_diar4' => $txMortsDiar4 !== null ? round($txMortsDiar4, 1) : null,
            'tx_morts_respi' => $txMortsRespi !== null ? round($txMortsRespi, 1) : null,
            'tx_morts_omphalite' => $txMortsOmphalite !== null ? round($txMortsOmphalite, 1) : null,
            'tx_avortements' => $txAvortements !== null ? round($txAvortements, 1) : null,
            'tx_velages_longs' => $txVelagesLongs !== null ? round($txVelagesLongs, 1) : null,
            'tx_cesariennes' => $txCesariennes !== null ? round($txCesariennes, 1) : null,
            'tx_non_delivrances' => $txNonDelivrances !== null ? round($txNonDelivrances, 1) : null,
            'tx_torsions_retournements_matrices' => $txTorsions !== null ? round($txTorsions, 1) : null,
            'tx_metrites' => $txMetrites !== null ? round($txMetrites, 1) : null,
            'cout_mortalite' => round($coutMortalite),
            'cout_ivv' => round($coutIvv),
            'cout_diarrhee' => round($coutDiarrhee),
            'cout_respi' => round($coutRespi),
            'cout_omphalite' => round($coutOmphalite),
            'cout_alimentaire' => round($coutAlimentaire),
            'cout_alimentaire_vache' => $coutAlimentaireVache !== null ? round($coutAlimentaireVache, 2) : null,
            'commentaires' => [
                'tx_mortalite_total_veaux' => [
                    's' => self::plainText($settings['txt_tx_mortalite_total_veaux_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_tx_mortalite_total_veaux_ns'] ?? ''),
                ],
                'tx_diarrhee_veaux_total' => [
                    's' => self::plainText($settings['txt_tx_diarrhee_veaux_total_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_tx_diarrhee_veaux_total_ns'] ?? ''),
                ],
                'tx_respi_veaux' => [
                    's' => self::plainText($settings['txt_tx_respi_veaux_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_tx_respi_veaux_ns'] ?? ''),
                ],
                'tx_omphalite_veaux' => [
                    's' => self::plainText($settings['txt_tx_omphalite_veaux_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_tx_omphalite_veaux_ns'] ?? ''),
                ],
                'ivv' => [
                    's' => self::plainText($settings['txt_ivv_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_ivv_ns'] ?? ''),
                ],
                'cout_alimentaire_vache' => [
                    's' => self::plainText($settings['txt_cout_alimentaire_vache_s'] ?? ''),
                    'ns' => self::plainText($settings['txt_cout_alimentaire_vache_ns'] ?? ''),
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function arrayOfArrays(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn (mixed $item): bool => is_array($item)));
    }

    private static function float(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private static function plainText(mixed $value): string
    {
        return LegacyHtmlCleaner::plainText((string) $value);
    }

    /**
     * @return array<string, float>
     */
    private static function numericMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $item): float => self::float($item))
            ->all();
    }
}
