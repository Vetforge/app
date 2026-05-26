<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>{{ $module['label'] }} - Analyse #{{ $analysis->id }}</title>
    <style>
        @page { margin: 10mm 8mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #172033;
            font-family: "Helvetica Neue", Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            background: #fff;
        }
        h1, h2, h3, p { margin: 0; }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
        }
        .header-main, .header-side { display: table-cell; vertical-align: top; }
        .header-main { width: 66%; }
        .header-side { width: 34%; text-align: right; }
        .eyebrow {
            margin-bottom: 4px;
            color: #2563eb;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        .title { font-size: 20px; font-weight: 700; line-height: 1.15; }
        .subtitle { margin-top: 4px; color: #64748b; font-size: 10px; }
        .meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }
        .box {
            padding: 9px;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            background: #fff;
            page-break-inside: avoid;
        }
        .box-muted { background: #f8fafc; }
        .label {
            margin-bottom: 4px;
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .value { font-size: 12px; font-weight: 700; }
        .section { margin-top: 10px; page-break-inside: avoid; }
        .section h2 { margin-bottom: 6px; font-size: 13px; }
        .section h3 { margin-bottom: 4px; font-size: 11px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 5px 6px;
            border: 1px solid #dbe3ef;
            vertical-align: top;
            text-align: left;
        }
        th { background: #f1f5f9; color: #475569; font-size: 8px; text-transform: uppercase; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .muted { color: #64748b; }
        .tag {
            display: inline-block;
            min-width: 18px;
            padding: 2px 5px;
            border-radius: 999px;
            background: #e2e8f0;
            font-weight: 700;
            text-align: center;
        }
        .tag-s { background: #dcfce7; color: #166534; }
        .tag-i { background: #fef3c7; color: #92400e; }
        .tag-r { background: #fee2e2; color: #991b1b; }
        .pre {
            white-space: pre-wrap;
            word-break: break-word;
            color: #334155;
        }
        .info-bar {
            margin-bottom: 8px;
            padding: 5px 8px;
            border: 1px solid #dbe3ef;
            border-radius: 6px;
            background: #f8fafc;
            color: #475569;
            font-size: 9px;
        }
        .mt-2 { margin-top: 8px; }
    </style>
</head>
<body>
@php
    $payload = $analysis->payload ?? [];
    $results = $analysis->results ?? [];
    $settings = $analysis->settings_snapshot ?? [];
    $enabled = fn (string $key) => collect(data_get($settings, $key, []))
        ->filter(fn ($item) => is_array($item) && ($item['enabled'] ?? true) !== false);
    $scaleLabel = function (?string $value) use ($settings) {
        return collect(data_get($settings, 'scale', []))->firstWhere('value', (string) $value)['label'] ?? (string) $value;
    };
    $plainText = fn ($value): string => \App\Support\LegacyHtmlCleaner::plainText((string) $value);
    $plainTextWithBreaks = fn ($value): string => \App\Support\LegacyHtmlCleaner::plainTextWithBreaks((string) $value);
    $matchesSpecies = function ($item, string $species): bool {
        if ($species === '') {
            return true;
        }

        $speciesList = $item['species'] ?? null;

        if (! is_array($speciesList)) {
            return true;
        }

        return count($speciesList) > 0 && in_array($species, array_map('strval', $speciesList), true);
    };
    $configuredLabel = function (string $group, string $key) use ($settings): string {
        $item = collect(data_get($settings, $group, []))
            ->first(fn ($entry) => is_array($entry) && (string) ($entry['key'] ?? '') === $key);

        return is_array($item) && ($item['label'] ?? '') !== '' ? (string) $item['label'] : str_replace('_', ' ', $key);
    };
    $resultLabel = fn ($value): string => match ((string) $value) {
        'pos' => 'Positif',
        'neg' => 'Negatif',
        'douteux' => 'Douteux',
        default => (string) ($value ?: '-'),
    };
    $bandeletteLabels = [
        'densite' => 'Densite urinaire',
        'ph' => 'pH',
        'leucocytes' => 'Leucocytes',
        'nitrite' => 'Nitrite',
        'proteine' => 'Proteine',
        'glucose' => 'Glucose',
        'cetone' => 'Cetone',
        'urobilinogene' => 'Urobilinogene',
        'bilirubine' => 'Bilirubine',
        'sang' => 'Sang',
        'hemoglobine' => 'Hemoglobine',
    ];
    $normFor = fn (string $paramKey) => data_get($settings, 'norms.'.data_get($payload, 'species').'.'.$paramKey);
    $valueStatus = function (string $paramKey, $value) use ($normFor): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        $norm = $normFor($paramKey);

        if (! is_array($norm) || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if (($norm['min'] ?? null) !== null && $number < (float) $norm['min']) {
            return 'Bas';
        }

        if (($norm['max'] ?? null) !== null && $number > (float) $norm['max']) {
            return 'Haut';
        }

        return 'OK';
    };
    $visibleCoproscopyParasites = function () use ($enabled, $payload) {
        $species = (string) data_get($payload, 'species', '');
        $options = is_array(data_get($payload, 'options')) ? data_get($payload, 'options') : [];

        return $enabled('parasites')->filter(function ($parasite) use ($species, $options) {
            $speciesList = collect($parasite['species'] ?? [])->map(fn ($item) => (string) $item)->all();
            $requiredOption = (string) ($parasite['requires_option'] ?? '');

            if ($species !== '' && count($speciesList) > 0 && ! in_array($species, $speciesList, true)) {
                return false;
            }

            return $requiredOption === '' || (bool) data_get($options, $requiredOption, false) === true;
        });
    };
    $normalizeDiarrheaTestName = function ($value): string {
        $test = mb_strtolower(trim((string) $value));

        return match (true) {
            str_contains($test, 'kitvia') => 'kitvia',
            str_contains($test, 'quick diar') => 'quick diar 5',
            str_contains($test, 'speed v-diar') => 'speed v-diar',
            default => $test,
        };
    };
    $legacyDiarrheaPathogenTests = [
        'rotavirus' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
        'coronavirus' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
        'ecoli_k99' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
        'ecoli_cs31a' => ['Speed V-Diar', 'Speed V-Diar 4'],
        'clostridium_perfringens' => ['Quick Diar 5'],
        'cryptosporidies' => ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
        'giardia' => ['Kitvia'],
    ];
    $visibleDiarrheaPathogens = function () use ($enabled, $payload, $normalizeDiarrheaTestName, $legacyDiarrheaPathogenTests) {
        $testName = $normalizeDiarrheaTestName(data_get($payload, 'test_name', ''));
        $knownTests = ['kitvia', 'speed v-diar', 'quick diar 5'];

        return $enabled('pathogens')->filter(function ($pathogen) use ($payload, $testName, $knownTests, $normalizeDiarrheaTestName, $legacyDiarrheaPathogenTests) {
            $key = (string) ($pathogen['key'] ?? '');

            if ((($pathogen['requires_option'] ?? '') === 'coccidiosis_test' || $key === 'coccidies') && (bool) data_get($payload, 'coccidiosis_test', false) !== true) {
                return false;
            }

            $tests = $pathogen['tests'] ?? $legacyDiarrheaPathogenTests[$key] ?? [];

            if ($testName === '' || ! in_array($testName, $knownTests, true) || ! is_array($tests) || $tests === []) {
                return true;
            }

            $tests = array_map(fn ($test) => $normalizeDiarrheaTestName($test), $tests);

            return in_array($testName, $tests, true);
        });
    };
@endphp

<x-pdf.clinic-header :clinic-header="$clinicHeader ?? null" />

<div class="header">
    <div class="header-main">
        <h1 class="title">{{ $module['label'] }}</h1>
    </div>
    <div class="header-side">
        <p class="muted">{{ optional($analysis->analyzed_at)->format('d/m/Y') ?? 'Date non renseignee' }}</p>
    </div>
</div>

<div class="meta">
    <div class="box">
        <div class="label">Eleveur</div>
        <div class="value">{{ $analysis->breeder->name }}</div>
        <p class="muted">{{ trim(($analysis->breeder->postal_code ?? '').' '.($analysis->breeder->city ?? '')) ?: '-' }}</p>
        <p class="muted">{{ $analysis->breeder->herd_number ?? '-' }}</p>
    </div>
    <div class="box">
        <div class="label">Animal</div>
        <div class="value">{{ $analysis->animal_nom ?: '-' }}</div>
    </div>
    <div class="box">
        <div class="label">Prelevement</div>
        <div class="value">{{ optional($analysis->sampled_at)->format('d/m/Y') ?? '-' }}</div>
    </div>
    <div class="box">
        <div class="label">Intervenant</div>
        <div class="value">{{ $analysis->intervenant ?: '-' }}</div>
    </div>
</div>

@if($analysis->module === 'coproscopie-parasitaire')
    @php
        $infoParts = array_filter([
            data_get($payload, 'species') ? 'Espece : '.data_get($payload, 'species') : null,
            data_get($payload, 'sample_nature') ? 'Nature : '.data_get($payload, 'sample_nature') : null,
        ]);
    @endphp
    @if(count($infoParts) > 0)
        <div class="info-bar">{{ implode(' | ', $infoParts) }}</div>
    @endif
    <div class="section">
        <h2>Resultats parasitaires</h2>
        <table>
            <thead>
                <tr>
                    <th>Echantillon</th>
                    @foreach($visibleCoproscopyParasites() as $parasite)
                        <th>{{ $parasite['label'] ?? $parasite['key'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach(data_get($payload, 'samples', []) as $index => $sample)
                    <tr>
                        <td>{{ data_get($sample, 'name') ?: 'Echantillon '.($index + 1) }}</td>
                        @foreach($visibleCoproscopyParasites() as $parasite)
                            <td>{{ $scaleLabel(data_get($sample, 'results.'.$parasite['key'], '0')) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@elseif($analysis->module === 'diarrhee-neonatale')
    @php
        $infoParts = array_filter([
            data_get($payload, 'species') ? 'Espece : '.data_get($payload, 'species') : null,
            data_get($payload, 'test_name') ? 'Test : '.data_get($payload, 'test_name') : null,
            data_get($payload, 'sample_nature') ? 'Nature : '.data_get($payload, 'sample_nature') : null,
            data_get($payload, 'sample_name') ? 'Echantillon : '.data_get($payload, 'sample_name') : null,
        ]);
    @endphp
    @if(count($infoParts) > 0)
        <div class="info-bar">{{ implode(' | ', $infoParts) }}</div>
    @endif
    <div class="section">
        <h2>Tests diarrhee neonatale</h2>
        <table>
            <thead><tr><th>Agent</th><th>Resultat</th></tr></thead>
            <tbody>
                @foreach($visibleDiarrheaPathogens() as $pathogen)
                    <tr>
                        <td>{{ $pathogen['label'] ?? $pathogen['key'] }}</td>
                        <td>{{ $scaleLabel(data_get($payload, 'pathogens.'.$pathogen['key'], '0')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@elseif($analysis->module === 'gaz-du-sang')
    @php
        $perfusionSettings = collect(data_get($settings, 'perfusions', []))->keyBy('key')->all();
        $perfusionsPayload = is_array(data_get($payload, 'perfusions')) ? data_get($payload, 'perfusions') : [];
        $activePerfusions = collect($perfusionsPayload)->filter(fn($qty) => (float) $qty > 0)->all();
    @endphp
    <div class="section">
        <h2>Gaz du sang et calculs</h2>

        <div class="grid-2">
            <div class="box box-muted">
                <div class="label">Parametres cliniques</div>
                <p>Espece : {{ data_get($results, 'species') ?? data_get($payload, 'species') ?? '-' }}</p>
                <p>Poids : {{ data_get($payload, 'weight') !== null ? data_get($payload, 'weight').' kg' : '-' }}</p>
                @if(data_get($payload, 'dehydration') !== null)
                    <p>Deshydratation : {{ data_get($payload, 'dehydration') }} %</p>
                @elseif(data_get($payload, 'enophtalmie') !== null)
                    <p>Enophtalmie : {{ data_get($payload, 'enophtalmie') }} mm</p>
                    <p>Deshydratation calculee : {{ data_get($results, 'dehydration') ?? '-' }} %</p>
                @else
                    <p>Deshydratation : {{ data_get($results, 'dehydration') ?? '-' }} %</p>
                @endif
            </div>
            <div class="box box-muted">
                <div class="label">Parametres biologiques</div>
                <p>pH : {{ data_get($payload, 'ph') ?? '-' }}</p>
                <p>HCO3 : {{ data_get($payload, 'hco3') !== null ? data_get($payload, 'hco3').' mmol/L' : '-' }}</p>
                @if(data_get($payload, 'pco2') !== null)
                    <p>pCO2 : {{ data_get($payload, 'pco2') }} mmHg</p>
                @endif
                <p>Glycemie : {{ data_get($payload, 'glycemia') !== null ? data_get($payload, 'glycemia').' mg/dL' : '-' }}</p>
                @if(data_get($payload, 'na') !== null)
                    <p>Na : {{ data_get($payload, 'na') }} mmol/L</p>
                @endif
                @if(data_get($payload, 'k') !== null)
                    <p>K : {{ data_get($payload, 'k') }} mmol/L</p>
                @endif
                @if(data_get($payload, 'cl') !== null)
                    <p>Cl : {{ data_get($payload, 'cl') }} mmol/L</p>
                @endif
                @if(data_get($payload, 'angap') !== null)
                    <p>Anion gap : {{ data_get($payload, 'angap') }} mmol/L</p>
                @endif
                @if(data_get($payload, 'tco2') !== null)
                    <p>TCO2 : {{ data_get($payload, 'tco2') }} mmol/L</p>
                @endif
            </div>
        </div>

        <table class="mt-2">
            <thead>
                <tr>
                    <th>Deficit hydrique</th>
                    <th>Deficit bicarbonate</th>
                    <th>Deficit glucose</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ data_get($results, 'volume_deficit_l') ?? '-' }} L</td>
                    <td>{{ data_get($results, 'deficit_bicarbonate_g') ?? '-' }} g</td>
                    <td>{{ data_get($results, 'deficit_glucose_g') ?? '-' }} g</td>
                </tr>
            </tbody>
        </table>

        @if(count($activePerfusions) > 0)
            <h3 class="mt-2">Perfusions administrees</h3>
            <table>
                <thead>
                    <tr>
                        <th>Perfusion</th>
                        <th>Quantite</th>
                        <th>Bicarbonate apporte</th>
                        <th>Glucose apporte</th>
                        <th>Volume apporte</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activePerfusions as $key => $qty)
                        @php
                            $perf = $perfusionSettings[$key] ?? null;
                            $qty = (float) $qty;
                        @endphp
                        <tr>
                            <td>{{ $perf['label'] ?? $key }}</td>
                            <td>{{ $qty }} {{ $perf['unit'] ?? '' }}</td>
                            <td>{{ $perf ? round($qty * (float) ($perf['bicarbonate'] ?? 0), 1) : '-' }} g</td>
                            <td>{{ $perf ? round($qty * (float) ($perf['glucose'] ?? 0), 1) : '-' }} g</td>
                            <td>{{ $perf ? round($qty * (float) ($perf['volume'] ?? 0), 1) : '-' }} L</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <table class="mt-2">
            <thead><tr><th>Poste</th><th>Bicarbonate</th><th>Glucose</th><th>Volume</th></tr></thead>
            <tbody>
                <tr>
                    <td>Apports perfusions</td>
                    <td>{{ data_get($results, 'apports.bicarbonate_g') ?? '-' }} g</td>
                    <td>{{ data_get($results, 'apports.glucose_g') ?? '-' }} g</td>
                    <td>{{ data_get($results, 'apports.volume_l') ?? '-' }} L</td>
                </tr>
                <tr>
                    <td>Reste a couvrir</td>
                    <td>{{ data_get($results, 'restes.bicarbonate_g') ?? '-' }} g</td>
                    <td>{{ data_get($results, 'restes.glucose_g') ?? '-' }} g</td>
                    <td>{{ data_get($results, 'restes.volume_l') ?? '-' }} L</td>
                </tr>
            </tbody>
        </table>

        @if(count(data_get($results, 'interpretations', [])) > 0)
            <table class="mt-2">
                <thead><tr><th>Parametre</th><th>Valeur</th><th>Norme</th><th>Statut</th></tr></thead>
                <tbody>
                    @foreach(data_get($results, 'interpretations', []) as $field => $interpretation)
                        <tr>
                            <td>{{ ['ph' => 'pH', 'pco2' => 'pCO2', 'hco3' => 'HCO3', 'na' => 'Na', 'k' => 'K', 'cl' => 'Cl', 'glycemia' => 'Glycemie'][$field] ?? strtoupper($field) }}</td>
                            <td>{{ $interpretation['value'] ?? '-' }}</td>
                            <td>{{ $interpretation['min'] ?? '-' }} - {{ $interpretation['max'] ?? '-' }}</td>
                            <td>{{ ['low' => 'Bas', 'normal' => 'OK', 'high' => 'Haut'][$interpretation['status'] ?? ''] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

@elseif($analysis->module === 'comptage-cellulaire')
    @php
        $infoParts = array_filter([
            data_get($payload, 'species') ? 'Espece : '.data_get($payload, 'species') : null,
            data_get($payload, 'sample_nature') ? 'Nature : '.data_get($payload, 'sample_nature') : null,
        ]);
    @endphp
    @if(count($infoParts) > 0)
        <div class="info-bar">{{ implode(' | ', $infoParts) }}</div>
    @endif
    <div class="section">
        <h2>Comptage cellulaire</h2>
        @if(data_get($payload, 'commemoratives'))
            <div class="box box-muted" style="margin-bottom: 8px;">
                <div class="label">Commemoratifs</div>
                <p class="pre">{{ data_get($payload, 'commemoratives') }}</p>
            </div>
        @endif
        <table>
            <thead><tr><th>Echantillon</th><th>Comptage x1000</th></tr></thead>
            <tbody>
                @foreach(data_get($payload, 'samples', []) as $index => $sample)
                    <tr>
                        <td>{{ data_get($sample, 'name') ?: 'Echantillon '.($index + 1) }}</td>
                        <td>{{ data_get($sample, 'count') ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted mt-2">Moyenne : {{ data_get($results, 'average') ?? '-' }} - Maximum : {{ data_get($results, 'max') ?? '-' }}</p>
        @if(data_get($payload, 'comments'))
            <div class="box mt-2">
                <div class="label">Commentaires</div>
                <p class="pre">{{ data_get($payload, 'comments') }}</p>
            </div>
        @endif
    </div>

@elseif($analysis->module === 'diagnostic-bacteriologique')
    @php
        $infoParts = array_filter([
            data_get($payload, 'species') ? 'Espece : '.data_get($payload, 'species') : null,
            data_get($payload, 'sample_nature') ? 'Nature : '.data_get($payload, 'sample_nature') : null,
            data_get($payload, 'sample_identification') ? 'Identification : '.data_get($payload, 'sample_identification') : null,
        ]);
    @endphp
    @if(count($infoParts) > 0)
        <div class="info-bar">{{ implode(' | ', $infoParts) }}</div>
    @endif
    <div class="section">
        <h2>Diagnostic bacteriologique</h2>
        @if(data_get($payload, 'commemoratives'))
            <div class="box box-muted" style="margin-bottom: 8px;">
                <div class="label">Commemoratifs</div>
                <p class="pre">{{ data_get($payload, 'commemoratives') }}</p>
            </div>
        @endif
        @php $contaminationStatus = data_get($results, 'contamination_status'); @endphp
        @if($contaminationStatus === 'sterile')
            <p style="margin-bottom:6px;"><strong>Resultat :</strong> <span style="color:#166534;background:#dcfce7;padding:2px 8px;border-radius:999px;font-weight:700;">Sterile</span></p>
        @elseif($contaminationStatus === 'contaminated')
            <p style="margin-bottom:6px;"><strong>Resultat :</strong> <span style="color:#991b1b;background:#fee2e2;padding:2px 8px;border-radius:999px;font-weight:700;">Contaminé</span></p>
        @endif
        @foreach(data_get($results, 'interpreted_germs', []) as $germ)
            <h3>Germe {{ $germ['index'] }} - {{ $germ['family'] ?? '-' }}</h3>
            <table>
                <thead><tr><th>Antibiotique</th><th>Diametre</th><th>Interpretation</th></tr></thead>
                <tbody>
                    @foreach($germ['antibiotics'] ?? [] as $antibiotic)
                        <tr>
                            <td>{{ $antibiotic['code'] }} - {{ $antibiotic['label'] }}</td>
                            <td>{{ $antibiotic['diameter'] }}</td>
                            <td><span class="tag tag-{{ strtolower($antibiotic['interpretation']) }}">{{ $antibiotic['interpretation'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>

@elseif($analysis->module === 'analyse-diverse')
    @php
        $infoParts = array_filter([
            data_get($payload, 'species') ? 'Espece : '.data_get($payload, 'species') : null,
            data_get($payload, 'sample_count') ? 'Analyses : '.data_get($payload, 'sample_count') : null,
        ]);
    @endphp
    @if(count($infoParts) > 0)
        <div class="info-bar">{{ implode(' | ', $infoParts) }}</div>
    @endif
    <div class="section">
        <h2>Resultats d'analyses</h2>
        @if(data_get($payload, 'commemoratifs'))
            <div class="box box-muted" style="margin-bottom: 8px;">
                <div class="label">Commemoratifs</div>
                <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'commemoratifs')) }}</p>
            </div>
        @endif
        <table>
            <thead><tr><th>Analyse</th><th>Resultats</th></tr></thead>
            <tbody>
                @foreach(data_get($payload, 'analyses', []) as $index => $row)
                    <tr>
                        <td>{{ data_get($row, 'type') ?: 'Analyse '.($index + 1) }}</td>
                        <td class="pre">{{ $plainTextWithBreaks(data_get($row, 'results', '')) ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(data_get($payload, 'commentaires'))
            <div class="box mt-2">
                <div class="label">Commentaires</div>
                <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'commentaires')) }}</p>
            </div>
        @endif
    </div>

@elseif($analysis->module === 'tests-rapides')
    @php
        $infoParts = array_filter([
            data_get($payload, 'species') ? 'Espece : '.data_get($payload, 'species') : null,
            data_get($payload, 'sample_nature') ? 'Nature : '.data_get($payload, 'sample_nature') : null,
            data_get($payload, 'identification') ? 'Identification : '.data_get($payload, 'identification') : null,
        ]);
        $nonEmptyEntries = fn (string $key): array => array_filter((array) data_get($payload, $key, []), fn ($value) => $value !== null && $value !== '');
        $elisaEntries = $nonEmptyEntries('elisa');
        $pcrEntries = $nonEmptyEntries('pcr');
        $biochemEntries = $nonEmptyEntries('biochem_rapide');
        $bandeletteEntries = $nonEmptyEntries('bandelette');
        $frottisEntries = $nonEmptyEntries('frottis');
    @endphp
    @if(count($infoParts) > 0)
        <div class="info-bar">{{ implode(' | ', $infoParts) }}</div>
    @endif
    @if(data_get($payload, 'commemoratifs'))
        <div class="box box-muted">
            <div class="label">Commemoratifs</div>
            <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'commemoratifs')) }}</p>
        </div>
    @endif
    @if(count($elisaEntries) > 0)
        <div class="section">
            <h2>Tests ELISA</h2>
            <table>
                <thead><tr><th>Test</th><th>Resultat</th></tr></thead>
                <tbody>
                    @foreach($elisaEntries as $key => $value)
                        <tr><td>{{ $configuredLabel('elisa_tests', (string) $key) }}</td><td>{{ $resultLabel($value) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if(count($pcrEntries) > 0)
        <div class="section">
            <h2>PCR</h2>
            <table>
                <thead><tr><th>Test</th><th>Resultat</th></tr></thead>
                <tbody>
                    @foreach($pcrEntries as $key => $value)
                        <tr><td>{{ $configuredLabel('pcr_tests', (string) $key) }}</td><td>{{ $resultLabel($value) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if(count($biochemEntries) > 0)
        <div class="section">
            <h2>Biochimie rapide</h2>
            <table>
                <thead><tr><th>Parametre</th><th>Valeur</th><th>Unite</th></tr></thead>
                <tbody>
                    @foreach($biochemEntries as $key => $value)
                        @php
                            $param = collect(data_get($settings, 'biochem_rapide', []))->firstWhere('key', (string) $key);
                            $unit = is_array($param) ? (string) ($param['unit'] ?? '') : '';
                        @endphp
                        <tr><td>{{ $configuredLabel('biochem_rapide', (string) $key) }}</td><td>{{ $value }}</td><td>{{ $unit }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if(count($bandeletteEntries) > 0)
        <div class="section">
            <h2>{{ $configuredLabel('optional_sections', 'bandelette_urinaire') }}</h2>
            <table>
                <thead><tr><th>Parametre</th><th>Resultat</th></tr></thead>
                <tbody>
                    @foreach($bandeletteEntries as $key => $value)
                        <tr><td>{{ $bandeletteLabels[$key] ?? str_replace('_', ' ', (string) $key) }}</td><td>{{ $value }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if(count($frottisEntries) > 0)
        <div class="section">
            <h2>{{ $configuredLabel('optional_sections', 'frottis_sanguin') }}</h2>
            <table>
                <thead><tr><th>Parametre</th><th>Resultat</th></tr></thead>
                <tbody>
                    @foreach($frottisEntries as $key => $value)
                        <tr><td>{{ str_replace('_', ' ', (string) $key) }}</td><td>{{ $resultLabel($value) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if(data_get($payload, 'commentaires'))
        <div class="section">
            <div class="box">
                <div class="label">Commentaires</div>
                <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'commentaires')) }}</p>
            </div>
        </div>
    @endif

@elseif($analysis->module === 'tests-biochimie')
    @php
        $species = (string) data_get($payload, 'species', '');
        $infoParts = array_filter([
            $species ? 'Espece : '.$species : null,
            data_get($payload, 'sample_nature') ? 'Nature : '.data_get($payload, 'sample_nature') : null,
            data_get($payload, 'identification') ? 'Identification : '.data_get($payload, 'identification') : null,
        ]);
        $payloadParams = collect((array) data_get($payload, 'params', []))
            ->filter(fn ($value) => $value !== null && $value !== '');
        $configuredParams = collect(data_get($settings, 'params', []))
            ->filter(fn ($param) => is_array($param) && ($param['enabled'] ?? true) !== false && $matchesSpecies($param, $species))
            ->filter(fn ($param) => $payloadParams->has((string) ($param['key'] ?? '')))
            ->map(fn ($param) => [...$param, 'key' => (string) ($param['key'] ?? '')]);
        $configuredKeys = $configuredParams->pluck('key')->all();
        $fallbackParams = $payloadParams
            ->reject(fn ($value, $key) => in_array((string) $key, $configuredKeys, true))
            ->keys()
            ->map(fn ($key) => ['key' => (string) $key, 'label' => str_replace('_', ' ', (string) $key)]);
        $filledParams = $configuredParams->concat($fallbackParams);
    @endphp
    @if(count($infoParts) > 0)
        <div class="info-bar">{{ implode(' | ', $infoParts) }}</div>
    @endif
    @if(data_get($payload, 'commemoratifs'))
        <div class="box box-muted">
            <div class="label">Commemoratifs</div>
            <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'commemoratifs')) }}</p>
        </div>
    @endif
    <div class="section">
        <h2>Resultats biochimiques</h2>
        <table>
            <thead><tr><th>Parametre</th><th>Valeur</th><th>Normes</th><th>Unite</th><th>Statut</th></tr></thead>
            <tbody>
                @foreach($filledParams as $param)
                    @php
                        $key = (string) ($param['key'] ?? '');
                        $value = data_get($payload, 'params.'.$key);
                        $norm = $normFor($key);
                        $min = is_array($norm) ? ($norm['min'] ?? null) : null;
                        $max = is_array($norm) ? ($norm['max'] ?? null) : null;
                        $unit = is_array($norm) ? (string) ($norm['unit'] ?? '') : '';
                        $normText = trim(($min !== null ? (string) $min : '').($min !== null && $max !== null ? ' - ' : '').($max !== null ? (string) $max : '')) ?: '-';
                    @endphp
                    <tr>
                        <td>{{ $param['label'] ?? $key }}</td>
                        <td>{{ $value }}</td>
                        <td>{{ $normText }}</td>
                        <td>{{ $unit }}</td>
                        <td>{{ $valueStatus($key, $value) ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(data_get($payload, 'commentaires'))
            <div class="box mt-2">
                <div class="label">Commentaires</div>
                <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'commentaires')) }}</p>
            </div>
        @endif
    </div>

@elseif($analysis->module === 'hemogramme')
    @php
        $species = (string) data_get($payload, 'species', '');
        $infoParts = array_filter([
            $species ? 'Espece : '.$species : null,
            data_get($payload, 'sample_nature') ? 'Nature : '.data_get($payload, 'sample_nature') : null,
            data_get($payload, 'identification') ? 'Identification : '.data_get($payload, 'identification') : null,
        ]);
        $groupLabels = [
            'erythrocytes' => 'Erythrocytes',
            'leucocytes' => 'Leucocytes',
            'plaquettes' => 'Plaquettes',
            'autres' => 'Autres',
        ];
        $payloadParams = collect((array) data_get($payload, 'params', []))
            ->filter(fn ($value) => $value !== null && $value !== '');
        $configuredParams = collect(data_get($settings, 'params', []))
            ->filter(fn ($param) => is_array($param) && ($param['enabled'] ?? true) !== false && $matchesSpecies($param, $species))
            ->filter(fn ($param) => $payloadParams->has((string) ($param['key'] ?? '')))
            ->map(fn ($param) => [...$param, 'key' => (string) ($param['key'] ?? '')]);
        $configuredKeys = $configuredParams->pluck('key')->all();
        $fallbackParams = $payloadParams
            ->reject(fn ($value, $key) => in_array((string) $key, $configuredKeys, true))
            ->keys()
            ->map(fn ($key) => ['key' => (string) $key, 'label' => str_replace('_', ' ', (string) $key), 'group' => 'autres']);
        $paramsByGroup = $configuredParams
            ->concat($fallbackParams)
            ->groupBy(fn ($param) => (string) ($param['group'] ?? 'autres'));
    @endphp
    @if(count($infoParts) > 0)
        <div class="info-bar">{{ implode(' | ', $infoParts) }}</div>
    @endif
    @if(data_get($payload, 'commemoratifs'))
        <div class="box box-muted">
            <div class="label">Commemoratifs</div>
            <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'commemoratifs')) }}</p>
        </div>
    @endif
    @foreach($paramsByGroup as $group => $params)
        <div class="section">
            <h2>{{ $groupLabels[$group] ?? $group }}</h2>
            <table>
                <thead><tr><th>Parametre</th><th>Valeur</th><th>Normes</th><th>Unite</th><th>Statut</th></tr></thead>
                <tbody>
                    @foreach($params as $param)
                        @php
                            $key = (string) ($param['key'] ?? '');
                            $value = data_get($payload, 'params.'.$key);
                            $norm = $normFor($key);
                            $min = is_array($norm) ? ($norm['min'] ?? null) : null;
                            $max = is_array($norm) ? ($norm['max'] ?? null) : null;
                            $unit = is_array($norm) ? (string) ($norm['unit'] ?? '') : '';
                            $normText = trim(($min !== null ? (string) $min : '').($min !== null && $max !== null ? ' - ' : '').($max !== null ? (string) $max : '')) ?: '-';
                        @endphp
                        <tr>
                            <td>{{ $param['label'] ?? $key }}</td>
                            <td>{{ $value }}</td>
                            <td>{{ $normText }}</td>
                            <td>{{ $unit }}</td>
                            <td>{{ $valueStatus($key, $value) ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
    @if(data_get($payload, 'commentaires'))
        <div class="section">
            <div class="box">
                <div class="label">Commentaires</div>
                <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'commentaires')) }}</p>
            </div>
        </div>
    @endif

@elseif($analysis->module === 'autopsie')
    <div class="section">
        <h2>Identification necropsique</h2>
        <div class="grid-3">
            @foreach([
                'Identification' => data_get($payload, 'identification'),
                'Espece' => data_get($payload, 'species'),
                'Sexe' => data_get($payload, 'sexe'),
                'Poids' => data_get($payload, 'poids') !== null ? data_get($payload, 'poids').' kg' : null,
                'Engraissement' => data_get($payload, 'engraissement'),
                'Conformation' => data_get($payload, 'conformation'),
                'Conservation' => data_get($payload, 'conservation'),
            ] as $label => $value)
                <div class="box">
                    <div class="label">{{ $label }}</div>
                    <div class="value">{{ $value ?: '-' }}</div>
                </div>
            @endforeach
        </div>
        @if(data_get($payload, 'commemoratifs'))
            <div class="box mt-2">
                <div class="label">Commemoratifs</div>
                <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'commemoratifs')) }}</p>
            </div>
        @endif
    </div>
    @if(data_get($payload, 'lesions'))
        <div class="section">
            <h2>Lesions</h2>
            <div class="box">
                <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'lesions')) }}</p>
            </div>
        </div>
    @endif
    @if(data_get($payload, 'conclusion'))
        <div class="section">
            <h2>Conclusion necropsique</h2>
            <div class="box">
                <p class="pre">{{ $plainTextWithBreaks(data_get($payload, 'conclusion')) }}</p>
            </div>
        </div>
    @endif

@elseif($analysis->module === 'compte-rendu')
    @foreach(data_get($payload, 'pages', []) as $index => $page)
        @php $pageText = $plainTextWithBreaks($page); @endphp
        <div class="section" style="page-break-inside: avoid;">
            <h2>Page {{ $index + 1 }} / {{ max(1, count(data_get($payload, 'pages', []))) }}</h2>
            <div class="box">
                <p class="pre">{{ $pageText !== '' ? $pageText : '-' }}</p>
            </div>
        </div>
    @endforeach

@elseif($analysis->module === 'bse-allaitant')
    @php
        $r  = fn (string $k) => data_get($results, $k);
        $p  = fn (string $k) => data_get($payload, $k);
        $fv = fn (string $k) => is_numeric(data_get($results, $k)) ? (float) data_get($results, $k) : null;
        $fp = fn (string $k) => is_numeric(data_get($payload,  $k)) ? (float) data_get($payload,  $k) : null;

        $bar = function (?float $value, array $thresholds, string $unit, string $label, bool $inv = false, int $dec = 1): string {
            $PB = 68; $PH = 60; $BX = 32; $BW = 24; $LX = 30;
            $maxT = count($thresholds) ? max($thresholds) : 10;
            $v    = (float) ($value ?? 0);
            $dmax = max($v * 1.3, $maxT * 1.5, 0.001);
            if ($value === null) {
                $c = '#94a3b8';
            } elseif ($inv) {
                $lo = $thresholds[0] ?? 0; $hi = $thresholds[1] ?? $lo;
                $c = $value >= $hi ? '#16a34a' : ($value >= $lo ? '#d97706' : '#dc2626');
            } else {
                $lo = $thresholds[0] ?? 0; $hi = $thresholds[1] ?? INF;
                $c = $value < $lo ? '#16a34a' : ($value < $hi ? '#d97706' : '#dc2626');
            }
            $barY = $value !== null ? ($PB - ($v / $dmax) * $PH) : $PB;
            $barH = max(0.0, $PB - $barY);
            $fv   = $value !== null ? number_format($value, $dec, ',', '') . ($unit ? ' ' . $unit : '') : '–';
            $cx   = $BX + $BW / 2;
            $x2   = $BX + $BW + 4;
            $lx   = $LX - 2;
            $out  = '';
            foreach ($thresholds as $t) {
                $ty   = $PB - ($t / $dmax) * $PH;
                $ty3  = $ty + 3;
                $out .= "<line x1=\"{$LX}\" y1=\"{$ty}\" x2=\"{$x2}\" y2=\"{$ty}\" stroke=\"#9ca3af\" stroke-width=\"1\" stroke-dasharray=\"3,2\"/><text x=\"{$lx}\" y=\"{$ty3}\" text-anchor=\"end\" font-size=\"7\" fill=\"#6b7280\">{$t}{$unit}</text>";
            }
            $out .= "<line x1=\"{$LX}\" y1=\"{$PB}\" x2=\"{$x2}\" y2=\"{$PB}\" stroke=\"#d1d5db\" stroke-width=\"1\"/>";
            if ($value !== null) {
                if ($barH > 0.5) {
                    $ty2  = $barY - 3;
                    $out .= "<rect x=\"{$BX}\" y=\"{$barY}\" width=\"{$BW}\" height=\"{$barH}\" fill=\"{$c}\" rx=\"2\"/>";
                    $out .= "<text x=\"{$cx}\" y=\"{$ty2}\" text-anchor=\"middle\" font-size=\"8\" font-weight=\"600\" fill=\"{$c}\">{$fv}</text>";
                } else {
                    $ty2  = $PB - 4;
                    $out .= "<text x=\"{$cx}\" y=\"{$ty2}\" text-anchor=\"middle\" font-size=\"8\" font-weight=\"600\" fill=\"{$c}\">{$fv}</text>";
                }
            } else {
                $ty2  = $PB - 15;
                $out .= "<text x=\"{$cx}\" y=\"{$ty2}\" text-anchor=\"middle\" font-size=\"9\" fill=\"#94a3b8\">–</text>";
            }
            $lbl = htmlspecialchars($label);
            return "<div style=\"display:flex;flex-direction:column;align-items:center;gap:3px;\"><svg viewBox=\"0 0 76 80\" width=\"76\" height=\"80\" overflow=\"visible\">{$out}</svg><p style=\"max-width:76px;text-align:center;font-size:9px;line-height:1.2;color:#64748b;margin:0;\">{$lbl}</p></div>";
        };

        $txMort          = $fv('tx_mortalite_total_veaux');
        $mortColor       = $txMort === null ? '#94a3b8' : ($txMort < 5 ? '#16a34a' : ($txMort < 9 ? '#d97706' : '#dc2626'));
        $mortLabel       = $txMort === null ? '–' : ($txMort < 5 ? 'Excellent' : ($txMort < 9 ? 'Satisfaisant' : ($txMort < 12 ? 'Défavorable' : 'Très défavorable')));
        $mortCircleStyle = "display:inline-flex;align-items:center;justify-content:center;width:80px;height:80px;border-radius:50%;background:{$mortColor};color:#fff;font-size:20px;font-weight:700;";

        $coms    = data_get($results, 'commentaires', []);
        $comText = function (string $key) use ($coms, $plainText): string {
            $s  = trim($plainText(data_get($coms, $key . '.s',  '')));
            $ns = trim($plainText(data_get($coms, $key . '.ns', '')));
            return trim($s . ($s && $ns ? "\n" : '') . $ns);
        };

        $letalites = array_filter([
            ['Létalité diarrhée J1',    $r('letalite_malades_diar1')],
            ['Létalité diarrhée J2-J3', $r('letalite_malades_diar2et3')],
            ['Létalité diarrhée J4',    $r('letalite_malades_diar4')],
            ['Létalité respiratoire',   $r('letalite_malades_respi')],
            ['Létalité omphalite',      $r('letalite_malades_omphalite')],
        ], fn ($l) => $l[1] !== null);
    @endphp

    <div class="section">
        <h2>Troupeau allaitant</h2>
        <div class="meta">
            <div class="box">
                <div class="label">Vaches reproductrices</div>
                <div class="value">{{ $p('nb_vaches_reproductrices') ?? '–' }}</div>
            </div>
            <div class="box">
                <div class="label">Veaux nés vivants</div>
                <div class="value">{{ $p('nb_veaux_nes_vivants') ?? '–' }}</div>
            </div>
            <div class="box">
                <div class="label">Veau / vache</div>
                <div class="value">{{ $r('veau_par_vache') ?? '–' }}</div>
            </div>
            <div class="box">
                <div class="label">IVV</div>
                <div class="value">{{ $p('ivv') !== null ? $p('ivv') . ' j' : '–' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Mortalité globale des veaux</h2>
        <div class="grid-2">
            <div class="box" style="text-align:center;padding:16px 8px;">
                <div style="{{ $mortCircleStyle }}">{{ $txMort !== null ? $txMort . '%' : '–' }}</div>
                <p style="margin-top:8px;font-weight:600;">{{ $mortLabel }}</p>
                <p class="muted">Mortalité totale veaux</p>
            </div>
            <div class="box box-muted">
                <div class="label">Détail</div>
                <p>Mortinatalité : {{ $r('tx_mortinatalite_veaux') !== null ? $r('tx_mortinatalite_veaux') . '%' : '–' }}</p>
                <p>Morts &lt;24h : {{ $r('tx_mortalite24h_veaux') !== null ? $r('tx_mortalite24h_veaux') . '%' : '–' }}</p>
                <p>Avortements : {{ $r('tx_avortements') !== null ? $r('tx_avortements') . '%' : '–' }}</p>
                <p>Veaux sevrés : {{ $r('nb_veaux_sevres') ?? '–' }}</p>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Pathologies veaux</h2>
        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-around;padding:8px 0;">
            {!! $bar($fv('tx_mortinatalite_veaux'), [1, 2],   '%', 'Mortinatalité') !!}
            {!! $bar($fv('tx_malades_diar_total'),  [15, 30], '%', 'Diarrhée malades') !!}
            {!! $bar($fv('tx_malades_respi'),        [2, 4],   '%', 'Respiratoire malades') !!}
            {!! $bar($fv('tx_malades_omphalite'),    [5, 10],  '%', 'Omphalite malades') !!}
        </div>
        @if(count($letalites) > 0)
            <table class="mt-2">
                <thead><tr><th>Pathologie</th><th>Létalité</th></tr></thead>
                <tbody>
                    @foreach($letalites as [$lbl, $val])
                        <tr><td>{{ $lbl }}</td><td>{{ $val . '%' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section">
        <h2>Impact reproduction</h2>
        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-around;padding:8px 0;">
            {!! $bar($fv('tx_avortements'),                    [2],      '%', 'Avortements') !!}
            {!! $bar($fv('tx_velages_longs'),                  [3, 6],   '%', 'Vélages longs') !!}
            {!! $bar($fv('tx_cesariennes'),                    [5, 10],  '%', 'Césariennes') !!}
            {!! $bar($fv('tx_non_delivrances'),                [5, 10],  '%', 'Non-délivrances') !!}
            {!! $bar($fv('tx_torsions_retournements_matrices'),[2, 4],   '%', 'Torsions/retournements') !!}
            {!! $bar($fv('tx_metrites'),                       [5, 10],  '%', 'Métrites') !!}
            {!! $bar($fp('ivv'),                               [365,390],'j', 'IVV', false, 0) !!}
            {!! $bar($fv('veau_par_vache'),                    [1],      'v/v','Veau/vache', true, 2) !!}
        </div>
    </div>

    <div class="section">
        <h2>Estimation des performances et coûts</h2>
        <p style="margin:0 0 8px 0;font-size:9px;color:#64748b;">Chacun de ces problèmes persistants nécessite un service de conseils spécialisés de vos vétérinaires.</p>
        @php
            $n1d = fn ($v) => $v !== null ? number_format((float)$v, 1, ',', '') : null;
            $n0  = fn ($v) => $v !== null ? number_format((float)$v, 0, ',', ' ') : null;
            $costCards = [
                [
                    'label'      => 'Mortalité des veaux',
                    'cost'       => $r('cout_mortalite'),
                    'rateLabel'  => 'Mortalité',
                    'rateValue'  => ($n1d($r('tx_mortalite_total_veaux')) ?? '–') . ($r('tx_mortalite_total_veaux') !== null ? ' %' : ''),
                    'commentKey' => 'tx_mortalite_total_veaux',
                    'desc'       => "L'audit maladies néonatales bovines permet de réduire le coût de traitement, les pertes de croissance et la mortalité. Une approche globale : conduite d'élevage, alimentation des mères et des veaux, vermifugation et vaccinations.",
                ],
                [
                    'label'      => 'Diarrhées néonatales',
                    'cost'       => $r('cout_diarrhee'),
                    'rateLabel'  => 'Tx diarrhées',
                    'rateValue'  => ($n1d($r('tx_malades_diar_total')) ?? '–') . ($r('tx_malades_diar_total') !== null ? ' %' : ''),
                    'commentKey' => 'tx_diarrhee_veaux_total',
                    'desc'       => "Le plan diarrhées néonatales étudie les facteurs de risque et met en place les solutions adaptées : alimentation des mères, gestion sanitaire, vaccination, qualité des colostrums.",
                ],
                [
                    'label'      => 'Pathologies respiratoires',
                    'cost'       => $r('cout_respi'),
                    'rateLabel'  => 'Tx respiratoire',
                    'rateValue'  => ($n1d($r('tx_malades_respi')) ?? '–') . ($r('tx_malades_respi') !== null ? ' %' : ''),
                    'commentKey' => 'tx_respi_veaux',
                    'desc'       => "Mise en place ou redéfinition d'un protocole de vaccination efficace contre la grippe adapté à votre situation. L'alimentation des veaux et le bâtiment demandent également une gestion particulière.",
                ],
                [
                    'label'      => 'Omphalites',
                    'cost'       => $r('cout_omphalite'),
                    'rateLabel'  => 'Tx omphalites',
                    'rateValue'  => ($n1d($r('tx_malades_omphalite')) ?? '–') . ($r('tx_malades_omphalite') !== null ? ' %' : ''),
                    'commentKey' => 'tx_omphalite_veaux',
                    'desc'       => 'Solutions préventives efficaces : hygiène, désinfection, oligoéléments.',
                ],
                [
                    'label'      => 'Intervalle vêlage-vêlage',
                    'cost'       => $r('cout_ivv'),
                    'rateLabel'  => 'IVV',
                    'rateValue'  => $p('ivv') !== null ? $p('ivv') . ' j' : '–',
                    'commentKey' => 'ivv',
                    'desc'       => "Examens échographiques et gestion régulière de la reproduction. L'alimentation des vaches est la principale cause d'infertilité : un diagnostic nutritionnel précis puis un suivi régulier.",
                ],
                [
                    'label'      => 'Coût alimentaire / vache',
                    'cost'       => $r('cout_alimentaire'),
                    'rateLabel'  => 'Coût / vache',
                    'rateValue'  => ($n0($r('cout_alimentaire_vache')) ?? '–') . ($r('cout_alimentaire_vache') !== null ? ' €' : ''),
                    'commentKey' => 'cout_alimentaire_vache',
                    'desc'       => "L'alimentation est le poste de dépenses le plus élevé et à l'origine de très nombreux problèmes. Diagnostic nutritionnel précis, analyse des fourrages, plan de rationnement et suivi régulier.",
                ],
            ];
        @endphp
        @foreach($costCards as $card)
            @php
                $cost         = is_numeric($card['cost']) ? (float)$card['cost'] : null;
                $isRed        = $cost !== null && $cost > 0;
                $circleStyle  = $isRed
                    ? 'display:flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:50%;border:2px solid #fca5a5;background:#fef2f2;color:#b91c1c;font-size:9px;font-weight:700;text-align:center;line-height:1.2;padding:4px;'
                    : 'display:flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:50%;border:2px solid #86efac;background:#f0fdf4;color:#15803d;font-size:9px;font-weight:700;text-align:center;line-height:1.2;padding:4px;';
                $costStyle    = 'font-size:11px;font-weight:600;margin:0;color:' . ($isRed ? '#b91c1c' : '#15803d') . ';';
                $costFmt      = $cost !== null ? number_format($cost, 0, ',', ' ') . ' €' : '–';
                $comment      = $comText($card['commentKey']);
            @endphp
            <div style="display:flex;gap:10px;padding:8px;border:1px solid #dbe3ef;border-radius:8px;margin-bottom:6px;page-break-inside:avoid;">
                <div style="flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:3px;width:62px;">
                    <div style="{{ $circleStyle }}"><span>{{ $card['rateValue'] }}</span></div>
                    <p style="font-size:8px;color:#64748b;text-align:center;margin:0;line-height:1.2;">{{ $card['rateLabel'] }}</p>
                    <p style="{{ $costStyle }}">{{ $costFmt }}</p>
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="font-weight:600;margin:0 0 3px 0;">{{ $card['label'] }}</p>
                    <p style="font-size:9px;color:#64748b;line-height:1.4;margin:0;">{{ $card['desc'] }}</p>
                    @if($comment)
                        <p style="font-size:9px;color:#64748b;font-style:italic;margin:4px 0 0 0;">{{ $comment }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

@elseif($analysis->module === 'bse-laitier')
    @php
        $r  = fn (string $k) => data_get($results, $k);
        $p  = fn (string $k) => data_get($payload,  $k);
        $fv = fn (string $k) => is_numeric(data_get($results, $k)) ? (float) data_get($results, $k) : null;
        $fp = fn (string $k) => is_numeric(data_get($payload,  $k)) ? (float) data_get($payload,  $k) : null;

        $bar = function (?float $value, array $thresholds, string $unit, string $label, bool $inv = false, int $dec = 1): string {
            $PB = 68; $PH = 60; $BX = 32; $BW = 24; $LX = 30;
            $maxT = count($thresholds) ? max($thresholds) : 10;
            $v    = (float) ($value ?? 0);
            $dmax = max($v * 1.3, $maxT * 1.5, 0.001);
            if ($value === null) {
                $c = '#94a3b8';
            } elseif ($inv) {
                $lo = $thresholds[0] ?? 0; $hi = $thresholds[1] ?? $lo;
                $c = $value >= $hi ? '#16a34a' : ($value >= $lo ? '#d97706' : '#dc2626');
            } else {
                $lo = $thresholds[0] ?? 0; $hi = $thresholds[1] ?? INF;
                $c = $value < $lo ? '#16a34a' : ($value < $hi ? '#d97706' : '#dc2626');
            }
            $barY = $value !== null ? ($PB - ($v / $dmax) * $PH) : $PB;
            $barH = max(0.0, $PB - $barY);
            $fv   = $value !== null ? number_format($value, $dec, ',', '') . ($unit ? ' ' . $unit : '') : '–';
            $cx   = $BX + $BW / 2;
            $x2   = $BX + $BW + 4;
            $lx   = $LX - 2;
            $out  = '';
            foreach ($thresholds as $t) {
                $ty   = $PB - ($t / $dmax) * $PH;
                $ty3  = $ty + 3;
                $out .= "<line x1=\"{$LX}\" y1=\"{$ty}\" x2=\"{$x2}\" y2=\"{$ty}\" stroke=\"#9ca3af\" stroke-width=\"1\" stroke-dasharray=\"3,2\"/><text x=\"{$lx}\" y=\"{$ty3}\" text-anchor=\"end\" font-size=\"7\" fill=\"#6b7280\">{$t}{$unit}</text>";
            }
            $out .= "<line x1=\"{$LX}\" y1=\"{$PB}\" x2=\"{$x2}\" y2=\"{$PB}\" stroke=\"#d1d5db\" stroke-width=\"1\"/>";
            if ($value !== null) {
                if ($barH > 0.5) {
                    $ty2  = $barY - 3;
                    $out .= "<rect x=\"{$BX}\" y=\"{$barY}\" width=\"{$BW}\" height=\"{$barH}\" fill=\"{$c}\" rx=\"2\"/>";
                    $out .= "<text x=\"{$cx}\" y=\"{$ty2}\" text-anchor=\"middle\" font-size=\"8\" font-weight=\"600\" fill=\"{$c}\">{$fv}</text>";
                } else {
                    $ty2  = $PB - 4;
                    $out .= "<text x=\"{$cx}\" y=\"{$ty2}\" text-anchor=\"middle\" font-size=\"8\" font-weight=\"600\" fill=\"{$c}\">{$fv}</text>";
                }
            } else {
                $ty2  = $PB - 15;
                $out .= "<text x=\"{$cx}\" y=\"{$ty2}\" text-anchor=\"middle\" font-size=\"9\" fill=\"#94a3b8\">–</text>";
            }
            $lbl = htmlspecialchars($label);
            return "<div style=\"display:flex;flex-direction:column;align-items:center;gap:3px;\"><svg viewBox=\"0 0 76 80\" width=\"76\" height=\"80\" overflow=\"visible\">{$out}</svg><p style=\"max-width:76px;text-align:center;font-size:9px;line-height:1.2;color:#64748b;margin:0;\">{$lbl}</p></div>";
        };

        $gainColor = fn (?float $v): string => $v === null ? '#64748b' : ($v >= 0 ? '#16a34a' : '#dc2626');

        $coms    = data_get($results, 'commentaires', []);
        $comText = function (string $key) use ($coms, $plainText): string {
            $s  = trim($plainText(data_get($coms, $key . '.s',  '')));
            $ns = trim($plainText(data_get($coms, $key . '.ns', '')));
            return trim($s . ($s && $ns ? "\n" : '') . $ns);
        };

        $nonGuerisons = array_filter([
            ['Mammites locales', $r('tx_non_guerison_mammites_locales')],
            ['Mammites aigues',  $r('tx_non_guerison_mammites_aigues')],
            ['Boiteries',        $r('tx_non_guerison_boiteries')],
            ['Fièvres de lait',  $r('tx_non_guerison_fievres_de_lait')],
            ['Caillettes',       $r('tx_non_guerison_caillettes')],
        ], fn ($g) => $g[1] !== null);
    @endphp

    <div class="section">
        <h2>Troupeau laitier</h2>
        <div class="meta">
            <div class="box">
                <div class="label">Vaches productrices</div>
                <div class="value">{{ $p('nb_vaches_productrices') ?? '–' }}</div>
            </div>
            <div class="box">
                <div class="label">Production annuelle</div>
                <div class="value">{{ $p('production_annuelle_lait') !== null ? $p('production_annuelle_lait') . ' T' : '–' }}</div>
            </div>
            <div class="box">
                <div class="label">Production / vache</div>
                <div class="value">{{ $r('production_moyenne_vache') !== null ? $r('production_moyenne_vache') . ' L' : '–' }}</div>
            </div>
            <div class="box">
                <div class="label">CCI moyen</div>
                <div class="value">{{ $p('concentration_cellulaire_moyen') !== null ? $p('concentration_cellulaire_moyen') . ' k' : '–' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Pathologies adultes</h2>
        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-around;padding:8px 0;">
            {!! $bar($fv('tx_mammites_locales'),  [30, 50], '%', 'Mammites locales') !!}
            {!! $bar($fv('tx_mammites_aigues'),   [5, 10],  '%', 'Mammites aigues') !!}
            {!! $bar($fv('tx_cci250'),            [20, 40], '%', 'CCI > 250k') !!}
            {!! $bar($fv('tx_boiteries'),         [5, 10],  '%', 'Boiteries') !!}
            {!! $bar($fv('tx_fievres_de_lait'),   [5, 10],  '%', 'Fièvres de lait') !!}
            {!! $bar($fv('tx_non_delivrances'),   [5, 10],  '%', 'Non-délivrances') !!}
            {!! $bar($fv('tx_metrites'),          [5, 10],  '%', 'Métrites') !!}
            {!! $bar($fv('tx_caillettes'),        [2, 5],   '%', 'Caillettes') !!}
            {!! $bar($fv('tx_cetoses'),           [5, 10],  '%', 'Cétoses') !!}
            {!! $bar($fv('tx_acidoses'),          [5, 10],  '%', 'Acidoses') !!}
        </div>
        @if(count($nonGuerisons) > 0)
            <h3 class="mt-2">Taux de non-guérison</h3>
            <table>
                <thead><tr><th>Pathologie</th><th>Tx non-guérison</th></tr></thead>
                <tbody>
                    @foreach($nonGuerisons as [$lbl, $val])
                        <tr><td>{{ $lbl }}</td><td>{{ $val . '%' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section">
        <h2>Mortalité veaux / Reproduction</h2>
        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-around;padding:8px 0;">
            {!! $bar($fv('tx_mortalite_neonatale'), [5, 10],   '%',   'Mortalité néonatale') !!}
            {!! $bar($fv('tx_avortements'),         [2, 5],    '%',   'Avortements') !!}
            {!! $bar($fp('iv_ia1'),                 [85, 100], 'j',   'IV-IA1', false, 0) !!}
            {!! $bar($fp('iv_iaf'),                 [110,140], 'j',   'IV-IAF', false, 0) !!}
            {!! $bar($fp('tx_reussite_ia1'),        [50, 65],  '%',   'Réussite IA1', true, 1) !!}
            {!! $bar($fp('tx_ia3'),                 [15, 30],  '%',   '≥ 3 IA') !!}
            {!! $bar($fp('ivv'),                    [400,420], 'j',   'IVV', false, 0) !!}
            {!! $bar($fv('veau_par_vache'),          [1],       'v/v', 'Veau/vache', true, 2) !!}
        </div>
    </div>

    <div class="section">
        <h2>Qualité du lait (gains / pertes)</h2>
        <div class="grid-3">
            @foreach([['Gain TB', $r('gain_tb')], ['Gain TP', $r('gain_tp')], ['Gain taux total', $r('gain_taux')]] as [$lbl, $val])
            @php $gainStyle = 'color:' . $gainColor(is_numeric($val) ? (float)$val : null) . ';'; @endphp
            <div class="box" style="text-align:center;">
                <div class="label">{{ $lbl }}</div>
                <div class="value" style="{{ $gainStyle }}">{{ $val !== null ? number_format($val, 0, ',', ' ') . ' €' : '–' }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="section">
        <h2>Estimation des performances et coûts</h2>
        <p style="margin:0 0 8px 0;font-size:9px;color:#64748b;">Chacun de ces problèmes persistants nécessite un service de conseils spécialisés de vos vétérinaires.</p>
        @php
            $n1d = fn ($v) => $v !== null ? number_format((float)$v, 1, ',', '') : null;
            $n0  = fn ($v) => $v !== null ? number_format((float)$v, 0, ',', ' ') : null;
            $costCards = [
                [
                    'label'      => 'Mortalité néonatale',
                    'cost'       => $r('cout_mortalite_neonatale'),
                    'rateLabel'  => 'Mortalité',
                    'rateValue'  => ($n1d($r('tx_mortalite_neonatale')) ?? '–') . ($r('tx_mortalite_neonatale') !== null ? ' %' : ''),
                    'commentKey' => 'tx_mortalite_neonatale',
                    'desc'       => "Le plan diarrhées néonatales étudie les facteurs de risque et met en place les solutions adaptées : alimentation des mères, gestion sanitaire, vaccination, qualité des colostrums. Réduction du coût de traitement, du temps de soins, des pertes de croissance et de la mortalité.",
                ],
                [
                    'label'      => 'Mammites',
                    'cost'       => $r('cout_mammites'),
                    'rateLabel'  => 'Tx mammites',
                    'rateValue'  => ($n1d($r('tx_mammites')) ?? '–') . ($r('tx_mammites') !== null ? ' %' : ''),
                    'commentKey' => 'tx_mammites',
                    'desc'       => "Seule une approche globale par votre vétérinaire peut maîtriser les contaminations : diagnostic épidémiologique, diagnostic étiologique, visite de traite pour identifier les points critiques et établir un plan d'action ciblé.",
                ],
                [
                    'label'      => 'Boiteries',
                    'cost'       => $r('cout_boiteries'),
                    'rateLabel'  => 'Tx boiteries',
                    'rateValue'  => ($n1d($r('tx_boiteries')) ?? '–') . ($r('tx_boiteries') !== null ? ' %' : ''),
                    'commentKey' => 'tx_boiteries',
                    'desc'       => "Seul un diagnostic précis associé à la visite conjointe d'un pareur et de votre vétérinaire peut permettre d'objectiver les causes des boiteries et établir un plan de lutte personnalisé.",
                ],
                [
                    'label'      => 'Troubles métaboliques',
                    'cost'       => $r('cout_metaboliques'),
                    'rateLabel'  => 'Tx métaboliques',
                    'rateValue'  => ($n1d($r('tx_metaboliques')) ?? '–') . ($r('tx_metaboliques') !== null ? ' %' : ''),
                    'commentKey' => 'tx_metaboliques',
                    'desc'       => "Solutions préventives grâce à un diagnostic nutritionnel précis, un plan de prévention efficace puis un suivi régulier adapté à votre situation.",
                ],
                [
                    'label'      => 'Reproduction (IVV)',
                    'cost'       => $r('cout_reproduction'),
                    'rateLabel'  => 'IVV',
                    'rateValue'  => $p('ivv') !== null ? $p('ivv') . ' j' : '–',
                    'commentKey' => 'cout_reproduction',
                    'desc'       => "Examens échographiques et gestion régulière de la reproduction. L'alimentation des vaches est la principale cause d'infertilité : un diagnostic nutritionnel précis puis un suivi régulier.",
                ],
                [
                    'label'      => 'Coût alimentaire / tonne lait',
                    'cost'       => $r('cout_alimentaire'),
                    'rateLabel'  => '€/t lait',
                    'rateValue'  => ($n0($r('cout_alimentaire_vache')) ?? '–') . ($r('cout_alimentaire_vache') !== null ? ' €/t' : ''),
                    'commentKey' => 'cout_alimentaire_vache_l',
                    'desc'       => "L'alimentation est le poste le plus élevé et à l'origine de très nombreux problèmes. Diagnostic nutritionnel, analyse des fourrages, plan de rationnement et suivi régulier.",
                ],
            ];
        @endphp
        @foreach($costCards as $card)
            @php
                $cost         = is_numeric($card['cost']) ? (float)$card['cost'] : null;
                $isRed        = $cost !== null && $cost > 0;
                $circleStyle  = $isRed
                    ? 'display:flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:50%;border:2px solid #fca5a5;background:#fef2f2;color:#b91c1c;font-size:9px;font-weight:700;text-align:center;line-height:1.2;padding:4px;'
                    : 'display:flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:50%;border:2px solid #86efac;background:#f0fdf4;color:#15803d;font-size:9px;font-weight:700;text-align:center;line-height:1.2;padding:4px;';
                $costStyle    = 'font-size:11px;font-weight:600;margin:0;color:' . ($isRed ? '#b91c1c' : '#15803d') . ';';
                $costFmt      = $cost !== null ? number_format($cost, 0, ',', ' ') . ' €' : '–';
                $comment      = $comText($card['commentKey']);
            @endphp
            <div style="display:flex;gap:10px;padding:8px;border:1px solid #dbe3ef;border-radius:8px;margin-bottom:6px;page-break-inside:avoid;">
                <div style="flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:3px;width:62px;">
                    <div style="{{ $circleStyle }}"><span>{{ $card['rateValue'] }}</span></div>
                    <p style="font-size:8px;color:#64748b;text-align:center;margin:0;line-height:1.2;">{{ $card['rateLabel'] }}</p>
                    <p style="{{ $costStyle }}">{{ $costFmt }}</p>
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="font-weight:600;margin:0 0 3px 0;">{{ $card['label'] }}</p>
                    <p style="font-size:9px;color:#64748b;line-height:1.4;margin:0;">{{ $card['desc'] }}</p>
                    @if($comment)
                        <p style="font-size:9px;color:#64748b;font-style:italic;margin:4px 0 0 0;">{{ $comment }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

@endif

@if($analysis->module === 'gaz-du-sang')
    <div class="section">
        @if(data_get($payload, 'treatment'))
            <div class="box" style="margin-bottom: 8px;">
                <div class="label">Traitement / remarques</div>
                <p class="pre">{{ data_get($payload, 'treatment') }}</p>
            </div>
        @endif
        <div class="grid-2">
            <div class="box">
                <div class="label">Conseils preventifs</div>
                <p class="pre">{{ data_get($payload, 'advice_preventive') ?: '-' }}</p>
            </div>
            <div class="box">
                <div class="label">Conseils curatifs</div>
                <p class="pre">{{ data_get($payload, 'advice_curative') ?: '-' }}</p>
            </div>
        </div>
    </div>
@elseif(in_array($analysis->module, ['coproscopie-parasitaire', 'diarrhee-neonatale', 'diagnostic-bacteriologique'], true))
    <div class="section grid-2">
        <div class="box">
            <div class="label">Conseils preventifs</div>
            <p class="pre">{{ data_get($payload, 'advice_preventive') ?: '-' }}</p>
        </div>
        <div class="box">
            <div class="label">Conseils curatifs</div>
            <p class="pre">{{ data_get($payload, 'advice_curative') ?: data_get($payload, 'advice') ?: '-' }}</p>
        </div>
    </div>
@endif
</body>
</html>
