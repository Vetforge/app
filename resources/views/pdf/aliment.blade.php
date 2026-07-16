<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Fiche aliment - {{ collect([$aliment->libelle0, $aliment->libelle1])->filter()->implode(' - ') }}</title>
    <style>
        :root {
            --ink: #14213d;
            --muted: #5c6b82;
            --line: #d8e0ea;
            --panel: #f8fbff;
            --panel-strong: #eef5fb;
            --accent-2018: #0f766e;
            --accent-2018-soft: #d9f3ee;
            --accent-2007: #8a5a0a;
            --accent-2007-soft: #f9e9c9;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--ink);
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            font-size: 10px;
        }

        .page {
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .hero {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 12px;
            padding: 16px 18px;
            border-radius: 22px;
            color: #fff;
        }

        .page--2018 .hero {
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.24), transparent 32%),
                linear-gradient(135deg, #0f766e 0%, #115e59 55%, #0b3d3d 100%);
        }

        .page--2007 .hero {
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.22), transparent 32%),
                linear-gradient(135deg, #a16207 0%, #8a5a0a 58%, #5b3b08 100%);
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .hero h1 {
            margin: 10px 0 0;
            font-size: 24px;
            line-height: 1.04;
        }

        .hero-subtitle {
            margin: 6px 0 0;
            max-width: 470px;
            color: rgba(255, 255, 255, 0.84);
            font-size: 11px;
            line-height: 1.45;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .meta-chip {
            min-width: 110px;
            padding: 8px 10px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.12);
        }

        .meta-chip-label {
            display: block;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.72);
        }

        .meta-chip-value {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
        }

        .page-badge {
            min-width: 108px;
            padding: 12px 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.12);
            text-align: right;
        }

        .page-badge-label {
            display: block;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.72);
        }

        .page-badge-value {
            display: block;
            margin-top: 6px;
            font-size: 18px;
            font-weight: 800;
            color: #fff;
        }

        .page-title-row {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 12px;
        }

        .page-title-row h2 {
            margin: 0;
            font-size: 16px;
        }

        .page-title-row p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 10px;
        }

        .section-tag {
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .page--2018 .section-tag {
            color: var(--accent-2018);
            background: var(--accent-2018-soft);
        }

        .page--2007 .section-tag {
            color: var(--accent-2007);
            background: var(--accent-2007-soft);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .metric-card {
            padding: 12px 13px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: linear-gradient(180deg, #fff 0%, var(--panel) 100%);
        }

        .metric-card-label {
            display: block;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .metric-card-value {
            display: block;
            margin-top: 8px;
            font-size: 20px;
            font-weight: 800;
            line-height: 1;
            color: var(--ink);
        }

        .metric-card-unit {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 9px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .card {
            break-inside: avoid;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: #fff;
            overflow: hidden;
        }

        .card-header {
            padding: 12px 14px 10px;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(180deg, var(--panel) 0%, #fff 100%);
        }

        .page--2018 .card-header-accent {
            background: linear-gradient(180deg, var(--accent-2018-soft) 0%, #fff 100%);
        }

        .page--2007 .card-header-accent {
            background: linear-gradient(180deg, var(--accent-2007-soft) 0%, #fff 100%);
        }

        .card-eyebrow {
            display: block;
            color: var(--muted);
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .card-title {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            font-weight: 800;
            color: var(--ink);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }

        .data-table tr:last-child th,
        .data-table tr:last-child td {
            border-bottom: 0;
        }

        .data-table th {
            width: 52%;
            color: #334155;
            font-size: 9px;
            font-weight: 600;
            text-align: left;
        }

        .data-table td.value {
            width: 28%;
            color: var(--ink);
            font-size: 9px;
            font-weight: 700;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .data-table td.unit {
            width: 20%;
            color: var(--muted);
            font-size: 8px;
            text-align: right;
            white-space: nowrap;
        }

        .footer-note {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 10px;
            color: var(--muted);
            font-size: 8px;
        }
    </style>
</head>
<body>
    @php
        $formatValue = static function (mixed $value, int $decimals = 1, bool $numeric = true): string {
            if ($value === null || $value === '') {
                return '—';
            }

            if (! $numeric || ! is_numeric($value)) {
                return (string) $value;
            }

            $formatted = number_format((float) $value, max(0, $decimals), ',', ' ');

            if ($decimals > 0) {
                $formatted = rtrim(rtrim($formatted, '0'), ',');
            }

            return $formatted === '-0' ? '0' : $formatted;
        };

        $row = static function (string $label, mixed $value, string $unit = '', int $decimals = 1, bool $numeric = true): array {
            return compact('label', 'value', 'unit', 'decimals', 'numeric');
        };

        $fieldRows = static function (array $fields, string $defaultUnit = '', int $defaultDecimals = 1, bool $numeric = true) use ($aliment, $row): array {
            return collect($fields)->map(
                static fn (array $field): array => $row(
                    $field[1],
                    data_get($aliment, $field[0]),
                    $field[2] ?? $defaultUnit,
                    $field[3] ?? $defaultDecimals,
                    $field[4] ?? $numeric,
                )
            )->all();
        };

        $section = static fn (string $eyebrow, string $title, array $rows): array => compact('eyebrow', 'title', 'rows');

        $displayName = collect([$aliment->libelle0, $aliment->libelle1])->filter()->implode(' - ');
        $referenceStatus = $aliment->code_inra ? 'INRA '.$aliment->code_inra : 'Bibliothèque utilisateur';
        $printedAt = now()->format('d/m/Y');

        $heroMeta = [
            ['label' => 'Type', 'value' => $formatValue($aliment->type, 0, false)],
            ['label' => 'Référence', 'value' => $referenceStatus],
            ['label' => 'Prix', 'value' => $aliment->prix !== null ? $formatValue($aliment->prix, 2).' €/unité MB' : '—'],
            ['label' => 'Impression', 'value' => $printedAt],
        ];

        $metrics2018 = [
            $row('UFL', $aliment->ufl, '/kg MS', 3),
            $row('UFV', $aliment->ufv, '/kg MS', 3),
            $row('PDI', $aliment->pdi, 'g/kg MS', 1),
            $row('UEM', $aliment->uem, '/kg MS', 3),
        ];

        $metrics2007 = [
            $row('UFL 2007', $aliment->ufl2007, '/kg MS', 3),
            $row('UFV 2007', $aliment->ufv2007, '/kg MS', 3),
            $row('PDIE 2007', $aliment->pdie2007, 'g/kg MS', 1),
            $row('PDIN 2007', $aliment->pdin2007, 'g/kg MS', 1),
        ];

        $sections2018Page1 = [
            $section('Base analytique', 'Composition', $fieldRows([
                ['ms', 'MS', '%'],
                ['mo', 'MO', 'g/kg MS'],
                ['mat', 'MAT', 'g/kg MS'],
                ['cb', 'CB', 'g/kg MS'],
                ['ndf', 'NDF', 'g/kg MS'],
                ['adf', 'ADF', 'g/kg MS'],
                ['adl', 'ADL', 'g/kg MS'],
                ['ee', 'EE', 'g/kg MS'],
                ['ag', 'AG', 'g/kg MS'],
                ['amidon', 'Amidon', 'g/kg MS'],
                ['sucres', 'Sucres', 'g/kg MS'],
                ['pf', 'PF', 'g/kg MS'],
            ]), true),
            $section('Énergie', 'Valeurs alimentaires 2018', $fieldRows([
                ['eb', 'EB', 'kcal/kg MS'],
                ['em', 'EM', 'kcal/kg MS'],
                ['d_mo', 'dMO', '%'],
                ['d_e', 'dE', '%', 2],
                ['ufl', 'UFL', '/kg MS', 3],
                ['ufv', 'UFV', '/kg MS', 3],
                ['uem', 'UEM', '/kg MS', 3],
                ['uel', 'UEL', '/kg MS', 3],
                ['ueb', 'UEB', '/kg MS', 3],
                ['niref', 'NIref', '', 3],
            ])),
            $section('Protéines', 'Dégradabilité et PDI', $fieldRows([
                ['pdia', 'PDIA', 'g/kg MS'],
                ['pdi', 'PDI', 'g/kg MS'],
                ['bpr', 'BPR', 'g/kg MS'],
                ['dt_n', 'DT_N', '%'],
                ['dt6_n', 'DT6_N', '%'],
                ['dr_n', 'dr_N', '%'],
                ['dt_ami', 'DT_AMI', '%'],
                ['dt6_ami', 'DT6_AMI', '%'],
                ['dt_ms', 'DT_MS', '%'],
                ['dt6_ms', 'DT6_MS', '%'],
            ])),
            $section('Minéraux majeurs', 'Équilibres du fourrage', $fieldRows([
                ['ca', 'Ca', 'g/kg MS'],
                ['caabs', 'Ca absorbable', 'g/kg MS'],
                ['p', 'P', 'g/kg MS'],
                ['pabs', 'P absorbable', 'g/kg MS'],
                ['mg', 'Mg', 'g/kg MS'],
                ['na', 'Na', 'g/kg MS'],
                ['k', 'K', 'g/kg MS'],
                ['cl', 'Cl', 'g/kg MS'],
                ['s', 'S', 'g/kg MS'],
                ['be', 'BE', '', 1],
                ['baca', 'BACA', '', 1],
            ]), true),
        ];

        $sections2018Page2 = [
            $section('Acides aminés digestibles', 'Profil DI', $fieldRows([
                ['lys_di', 'LysDI', '% PDI', 2],
                ['met_di', 'MetDI', '% PDI', 2],
                ['his_di', 'HisDI', '% PDI', 2],
                ['arg_di', 'ArgDI', '% PDI', 2],
                ['thr_di', 'ThrDI', '% PDI', 2],
                ['val_di', 'ValDI', '% PDI', 2],
                ['ile_di', 'IleDI', '% PDI', 2],
                ['leu_di', 'LeuDI', '% PDI', 2],
                ['phe_di', 'PheDI', '% PDI', 2],
                ['asp_di', 'AspDI', '% PDI', 2],
                ['ser_di', 'SerDI', '% PDI', 2],
                ['glu_di', 'GluDI', '% PDI', 2],
                ['pro_di', 'ProDI', '% PDI', 2],
                ['gly_di', 'GlyDI', '% PDI', 2],
                ['ala_di', 'AlaDI', '% PDI', 2],
                ['tyr_di', 'TyrDI', '% PDI', 2],
            ])),
            $section('Acides aminés BP', 'Profil BP', $fieldRows([
                ['lys_bp', 'LysBP', 'g/kg MS', 2],
                ['his_bp', 'HisBP', 'g/kg MS', 2],
                ['arg_bp', 'ArgBP', 'g/kg MS', 2],
                ['thr_bp', 'ThrBP', 'g/kg MS', 2],
                ['val_bp', 'ValBP', 'g/kg MS', 2],
                ['met_bp', 'MetBP', 'g/kg MS', 2],
                ['ile_bp', 'IleBP', 'g/kg MS', 2],
                ['leu_bp', 'LeuBP', 'g/kg MS', 2],
                ['phe_bp', 'PheBP', 'g/kg MS', 2],
                ['asp_bp', 'AspBP', 'g/kg MS', 2],
                ['ser_bp', 'SerBP', 'g/kg MS', 2],
                ['glu_bp', 'GluBP', 'g/kg MS', 2],
                ['pro_bp', 'ProBP', 'g/kg MS', 2],
                ['gly_bp', 'GlyBP', 'g/kg MS', 2],
                ['ala_bp', 'AlaBP', 'g/kg MS', 2],
                ['tyr_bp', 'TyrBP', 'g/kg MS', 2],
                ['cys_trp_bp', 'Cys/Trp BP', 'g/kg MS', 2],
            ])),
            $section('Oligoéléments', 'Micronutriments', $fieldRows([
                ['cu', 'Cu', 'mg/kg MS'],
                ['zn', 'Zn', 'mg/kg MS'],
                ['mn', 'Mn', 'mg/kg MS'],
                ['co', 'Co', 'mg/kg MS'],
                ['se', 'Se', 'mg/kg MS'],
                ['i', 'I', 'mg/kg MS'],
                ['molybdene', 'Mo', 'mg/kg MS'],
                ['vit_a', 'Vitamine A', 'UI/kg MS', 0],
                ['vit_d', 'Vitamine D', 'UI/kg MS', 0],
                ['vit_e', 'Vitamine E', 'UI/kg MS'],
            ]), true),
            $section('Lipides', 'Acides gras', $fieldRows([
                ['c6_10', 'C6:10', 'g/kg MS'],
                ['c12_0', 'C12:0', 'g/kg MS'],
                ['c14_0', 'C14:0', 'g/kg MS'],
                ['c16_0', 'C16:0', 'g/kg MS'],
                ['c16_1', 'C16:1', 'g/kg MS'],
                ['c18_0', 'C18:0', 'g/kg MS'],
                ['c18_1', 'C18:1', 'g/kg MS'],
                ['c18_2', 'C18:2', 'g/kg MS'],
                ['c18_3', 'C18:3', 'g/kg MS'],
                ['c20_0', 'C20:0', 'g/kg MS'],
                ['c20_1', 'C20:1', 'g/kg MS'],
                ['c22_0', 'C22:0', 'g/kg MS'],
                ['c22_1', 'C22:1', 'g/kg MS'],
                ['c24_0', 'C24:0', 'g/kg MS'],
                ['b_vec', 'B_vec', 'g/kg MS'],
            ]), true),
        ];

        $sections2007 = [
            $section('Référentiel 2007', 'Valeurs alimentaires principales', $fieldRows([
                ['ms', 'MS', '%'],
                ['ufl2007', 'UFL 2007', '/kg MS', 3],
                ['ufv2007', 'UFV 2007', '/kg MS', 3],
                ['pdia2007', 'PDIA 2007', 'g/kg MS'],
                ['pdie2007', 'PDIE 2007', 'g/kg MS'],
                ['pdin2007', 'PDIN 2007', 'g/kg MS'],
                ['uem2007', 'UEM 2007', '/kg MS', 3],
                ['uel2007', 'UEL 2007', '/kg MS', 3],
                ['ueb2007', 'UEB 2007', '/kg MS', 3],
            ]), true),
            $section('Digestibilité', 'Énergie et dégradations 2007', $fieldRows([
                ['d_mo2007', 'dMO 2007', '%'],
                ['d_ma2007', 'dMA 2007', '%'],
                ['d_cb2007', 'dCB 2007', '%'],
                ['d_ndf2007', 'dNDF 2007', '%'],
                ['d_adf2007', 'dADF 2007', '%'],
                ['d_e2007', 'dE 2007', '%'],
                ['eb2007', 'EB 2007', 'kcal/kg MS'],
                ['em2007', 'EM 2007', 'kcal/kg MS'],
            ])),
        ];
    @endphp

    <section class="page page--2018">
        <x-pdf.clinic-header :clinic-header="$clinicHeader ?? null" />

        <header class="hero">
            <div>
                <span class="hero-kicker">Fiche aliment</span>
                <h1>{{ collect([$aliment->libelle1, $aliment->libelle2])->filter()->implode(' · ') }}</h1>
                @if($aliment->libelle0)
                    <p class="hero-subtitle">
                        {{ $aliment->libelle0 ?: 'Aliment sans libellé' }}
                    </p>
                @endif

                <div class="hero-meta">
                    @foreach($heroMeta as $meta)
                        <div class="meta-chip">
                            <span class="meta-chip-label">{{ $meta['label'] }}</span>
                            <span class="meta-chip-value">{{ $meta['value'] !== '—' ? $meta['value'] : 'Non renseigné' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </header>

        <div class="page-title-row">
            <div>
                <h2>Valeurs 2018</h2>
            </div>
            <span class="section-tag">Données complètes 2018</span>
        </div>

        <div class="metrics-grid">
            @foreach($metrics2018 as $metric)
                <div class="metric-card">
                    <span class="metric-card-label">{{ $metric['label'] }}</span>
                    <span class="metric-card-value">{{ $formatValue($metric['value'], $metric['decimals'], $metric['numeric']) }}</span>
                    <span class="metric-card-unit">{{ $metric['unit'] ?: 'valeur stockée' }}</span>
                </div>
            @endforeach
        </div>

        <div class="cards-grid">
            @foreach($sections2018Page1 as $section)
                <article class="card">
                    <div class="card-header card-header-accent">
                        <span class="card-eyebrow">{{ $section['eyebrow'] }}</span>
                        <span class="card-title">{{ $section['title'] }}</span>
                    </div>

                    <table class="data-table">
                        <tbody>
                            @foreach($section['rows'] as $entry)
                                <tr>
                                    <th>{{ $entry['label'] }}</th>
                                    <td class="value">{{ $formatValue($entry['value'], $entry['decimals'], $entry['numeric']) }}</td>
                                    <td class="unit">{{ $entry['unit'] ?: ' ' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </article>
            @endforeach
        </div>

        <div class="footer-note">
            <span>{{ $displayName ?: 'Fiche aliment' }}</span>
            <span>Page 1/3 - Référentiel INRA 2018</span>
        </div>
    </section>

    <section class="page page--2018">

        <div class="cards-grid">
            @foreach($sections2018Page2 as $section)
                <article class="card">
                    <div class="card-header card-header-accent">
                        <span class="card-eyebrow">{{ $section['eyebrow'] }}</span>
                        <span class="card-title">{{ $section['title'] }}</span>
                    </div>

                    <table class="data-table">
                        <tbody>
                            @foreach($section['rows'] as $entry)
                                <tr>
                                    <th>{{ $entry['label'] }}</th>
                                    <td class="value">{{ $formatValue($entry['value'], $entry['decimals'], $entry['numeric']) }}</td>
                                    <td class="unit">{{ $entry['unit'] ?: ' ' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </article>
            @endforeach
        </div>

        <div class="footer-note">
            <span>{{ $displayName ?: 'Fiche aliment' }}</span>
            <span>Page 2/3 - Référentiel INRA 2018</span>
        </div>
    </section>

    <section class="page page--2007">
        <header class="hero">
            <div>
                <span class="hero-kicker">Comparatif historique</span>
                <h1>Référentiel INRA 2007</h1>
                <p class="hero-subtitle">
                    Page dédiée aux valeurs 2007 conservées sur la fiche, avec rappel des informations utiles du fourrage.
                </p>

                <div class="hero-meta">
                    <div class="meta-chip">
                        <span class="meta-chip-label">Libellé</span>
                        <span class="meta-chip-value">{{ $aliment->libelle0 ?: 'Non renseigné' }}</span>
                    </div>
                    <div class="meta-chip">
                        <span class="meta-chip-label">Libellé 1</span>
                        <span class="meta-chip-value">{{ $aliment->libelle1 ?: 'Non renseigné' }}</span>
                    </div>
                    <div class="meta-chip">
                        <span class="meta-chip-label">Référence</span>
                        <span class="meta-chip-value">{{ $referenceStatus }}</span>
                    </div>
                    <div class="meta-chip">
                        <span class="meta-chip-label">Impression</span>
                        <span class="meta-chip-value">{{ $printedAt }}</span>
                    </div>
                </div>
            </div>

            <div class="page-badge">
                <span class="page-badge-label">Page</span>
                <span class="page-badge-value">3/3</span>
                <span class="page-badge-label" style="margin-top: 10px;">Référentiel 2007</span>
            </div>
        </header>

        <div class="metrics-grid">
            @foreach($metrics2007 as $metric)
                <div class="metric-card">
                    <span class="metric-card-label">{{ $metric['label'] }}</span>
                    <span class="metric-card-value">{{ $formatValue($metric['value'], $metric['decimals'], $metric['numeric']) }}</span>
                    <span class="metric-card-unit">{{ $metric['unit'] ?: 'valeur stockée' }}</span>
                </div>
            @endforeach
        </div>

        <div class="cards-grid">
            @foreach($sections2007 as $section)
                <article class="card">
                    <div class="card-header card-header-accent">
                        <span class="card-eyebrow">{{ $section['eyebrow'] }}</span>
                        <span class="card-title">{{ $section['title'] }}</span>
                    </div>

                    <table class="data-table">
                        <tbody>
                            @foreach($section['rows'] as $entry)
                                <tr>
                                    <th>{{ $entry['label'] }}</th>
                                    <td class="value">{{ $formatValue($entry['value'], $entry['decimals'], $entry['numeric']) }}</td>
                                    <td class="unit">{{ $entry['unit'] ?: ' ' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </article>
            @endforeach
        </div>

        <div class="footer-note">
            <span>{{ $displayName ?: 'Fiche aliment' }}</span>
            <span>Page 3/3 - Référentiel INRA 2007</span>
        </div>
    </section>
</body>
</html>
