<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Rapport ration — {{ $ration->nom }}</title>
    <style>
        @page {
            margin: 10mm 8mm;
        }

        :root {
            color-scheme: light;
            --ink: #14213d;
            --muted: #5f6b7a;
            --line: #d7deea;
            --line-strong: #b9c5d7;
            --surface: #f7f9fc;
            --surface-strong: #eef3f9;
            --hero-start: #e0f2fe;
            --hero-end: #fff7ed;
            --sky: #0f6cbd;
            --sky-soft: #dbeafe;
            --emerald: #047857;
            --emerald-soft: #ecfdf5;
            --emerald-line: #a7f3d0;
            --blue: #1d4ed8;
            --blue-soft: #eff6ff;
            --blue-line: #bfdbfe;
            --rose: #be123c;
            --rose-soft: #fff1f2;
            --rose-line: #fecdd3;
            --slate: #475569;
            --slate-soft: #f8fafc;
            --slate-line: #cbd5e1;
            --amber: #b45309;
            --amber-soft: #fffbeb;
            --amber-line: #fde68a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--ink);
            font-family: "Helvetica Neue", Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            background: #ffffff;
        }

        h1, h2, h3, h4, p {
            margin: 0;
        }

        .report {
            display: block;
        }

        .hero {
            position: relative;
            overflow: hidden;
            margin-bottom: 10px;
            padding: 14px 16px;
            border: 1px solid #cbdbe8;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--hero-start), #ffffff 52%, var(--hero-end));
        }

        .hero::before,
        .hero::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
        }

        .hero::before {
            top: -45px;
            right: -20px;
            width: 150px;
            height: 150px;
            background: rgba(14, 165, 233, 0.10);
        }

        .hero::after {
            bottom: -55px;
            left: -20px;
            width: 170px;
            height: 170px;
            background: rgba(251, 146, 60, 0.10);
        }

        .hero-head {
            position: relative;
            z-index: 1;
            display: table;
            width: 100%;
        }

        .hero-copy,
        .hero-side {
            display: table-cell;
            vertical-align: top;
        }

        .hero-copy {
            width: 68%;
            padding-right: 14px;
        }

        .hero-side {
            width: 32%;
        }

        .eyebrow {
            margin-bottom: 6px;
            color: var(--sky);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.24em;
            text-transform: uppercase;
        }

        .hero-title {
            font-size: 22px;
            line-height: 1.1;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .hero-subtitle {
            margin-top: 6px;
            max-width: 500px;
            color: var(--muted);
            font-size: 11px;
        }

        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.82);
            color: var(--ink);
            font-size: 9px;
            font-weight: 600;
        }

        .side-card {
            position: relative;
            z-index: 1;
            padding: 11px;
            border: 1px solid rgba(15, 108, 189, 0.15);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.84);
            box-shadow: 0 10px 22px rgba(20, 33, 61, 0.04);
        }

        .side-card + .side-card {
            margin-top: 8px;
        }

        .side-label {
            color: var(--muted);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .side-value {
            margin-top: 3px;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.1;
        }

        .side-note {
            margin-top: 4px;
            color: var(--muted);
            font-size: 9px;
        }

        .panel-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 8px;
            margin-top: 14px;
        }

        .panel-card {
            min-height: 96px;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.78);
            page-break-inside: avoid;
        }

        .panel-label {
            color: var(--muted);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            padding: 2px 6px;
            border: 1px solid var(--slate-line);
            border-radius: 999px;
            background: var(--slate-soft);
            color: var(--slate);
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .panel-value {
            margin-top: 9px;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .panel-note {
            margin-top: 5px;
            color: var(--muted);
            font-size: 9px;
        }

        .progress {
            height: 6px;
            margin-top: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.18);
        }

        .progress-bar {
            height: 100%;
            border-radius: 999px;
        }

        .bar-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .bar-table td {
            height: 100%;
            padding: 0;
            border: none;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .metric-card {
            min-height: 78px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #ffffff;
            page-break-inside: avoid;
        }

        .metric-label {
            color: var(--muted);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .metric-value {
            margin-top: 6px;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.1;
        }

        .metric-note {
            margin-top: 4px;
            color: var(--muted);
            font-size: 9px;
        }

        .sheet {
            margin-top: 10px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #ffffff;
        }

        .page-break-before {
            page-break-before: always;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 10px;
            margin-bottom: 10px;
        }

        .section-title {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .section-copy {
            color: var(--muted);
            font-size: 10px;
            max-width: 480px;
        }

        .split {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .stack {
            display: grid;
            gap: 10px;
        }

        .card {
            padding: 11px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
            page-break-inside: avoid;
        }

        .card h3 {
            font-size: 12px;
            font-weight: 700;
        }

        .card-copy {
            margin-top: 3px;
            color: var(--muted);
            font-size: 9px;
        }

        .chart-list {
            margin-top: 8px;
        }

        .chart-row + .chart-row {
            margin-top: 8px;
        }

        .chart-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 4px;
        }

        .chart-label {
            font-weight: 600;
            color: var(--ink);
        }

        .chart-note {
            color: var(--muted);
            font-size: 9px;
        }

        .chart-value {
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
        }

        .chart-rail {
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.15);
        }

        .chart-fill {
            height: 100%;
            border-radius: 999px;
        }

        .insight-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .insight-block {
            padding: 11px;
            border-radius: 14px;
            page-break-inside: avoid;
        }

        .insight-block h3 {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .insight-list {
            margin-top: 10px;
        }

        .insight-item + .insight-item {
            margin-top: 8px;
        }

        .insight-card {
            padding: 10px;
            border: 1px solid rgba(255, 255, 255, 0.65);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.82);
        }

        .insight-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }

        .insight-label {
            font-weight: 700;
        }

        .insight-note {
            margin-top: 2px;
            color: var(--muted);
            font-size: 9px;
        }

        .insight-value {
            font-weight: 700;
            white-space: nowrap;
        }

        .table-wrap {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .balance-table {
            table-layout: fixed;
        }

        .balance-table col {
            width: 20%;
        }

        .balance-table td,
        .balance-table th {
            word-break: break-word;
        }

        th,
        td {
            padding: 7px 9px;
            border-bottom: 1px solid #e5ebf3;
            vertical-align: top;
        }

        thead th {
            color: var(--muted);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            background: var(--surface-strong);
        }

        tbody tr:last-child td,
        tbody tr:last-child th {
            border-bottom: none;
        }

        .table-number {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .table-note {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 9px;
        }

        .table-chip {
            display: inline-flex;
            align-items: center;
            margin-left: 6px;
            padding: 2px 6px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .mini-bar {
            width: 100%;
            height: 5px;
            margin-top: 4px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.15);
        }

        .mini-bar-fill {
            height: 100%;
            border-radius: 999px;
        }

        .table-section {
            margin-top: 10px;
        }

        .table-section:first-child {
            margin-top: 0;
        }

        .table-section-title {
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        .component-name {
            font-weight: 700;
        }

        .component-sub {
            margin-top: 3px;
            color: var(--muted);
            font-size: 9px;
        }

        .component-list {
            display: grid;
            gap: 8px;
        }

        .component-card-pdf {
            padding: 10px 11px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
            page-break-inside: avoid;
        }

        .component-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .component-head-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
        }

        .component-entry {
            margin-top: 5px;
            color: var(--muted);
            font-size: 9px;
        }

        .component-metrics {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .component-metric {
            padding: 8px;
            border: 1px solid #e5ebf3;
            border-radius: 12px;
            background: #ffffff;
        }

        .component-metric-label {
            color: var(--muted);
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .component-metric-value {
            margin-top: 3px;
            font-weight: 700;
            line-height: 1.25;
        }

        .agv-bar {
            display: flex;
            height: 12px;
            overflow: hidden;
            margin-top: 8px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.12);
        }

        .agv-segment {
            height: 100%;
        }

        .legend {
            margin-top: 8px;
        }

        .legend-item + .legend-item {
            margin-top: 4px;
        }

        .legend-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-right: 5px;
            border-radius: 999px;
            vertical-align: middle;
        }

        .footer-note {
            margin-top: 10px;
            color: var(--muted);
            font-size: 9px;
            text-align: right;
        }

        .status-ok {
            color: var(--emerald);
        }

        .status-watch {
            color: var(--blue);
        }

        .status-alert {
            color: var(--rose);
        }

        .status-surface-ok {
            border-color: var(--emerald-line);
            background: var(--emerald-soft);
        }

        .status-surface-watch {
            border-color: var(--blue-line);
            background: var(--blue-soft);
        }

        .status-surface-alert {
            border-color: var(--rose-line);
            background: var(--rose-soft);
        }

        .status-surface-neutral {
            border-color: var(--slate-line);
            background: var(--slate-soft);
        }

        .status-text-ok {
            color: var(--emerald);
        }

        .status-text-watch {
            color: var(--blue);
        }

        .status-text-alert {
            color: var(--rose);
        }

        .status-text-neutral {
            color: var(--slate);
        }

        .status-chip-ok {
            border: 1px solid var(--emerald-line);
            background: var(--emerald-soft);
            color: var(--emerald);
        }

        .status-chip-watch {
            border: 1px solid var(--blue-line);
            background: var(--blue-soft);
            color: var(--blue);
        }

        .status-chip-alert {
            border: 1px solid var(--rose-line);
            background: var(--rose-soft);
            color: var(--rose);
        }

        .status-chip-neutral,
        .type-chip {
            border: 1px solid var(--slate-line);
            background: var(--slate-soft);
            color: var(--slate);
        }

        .status-bar-ok {
            background: #10b981;
        }

        .status-bar-watch {
            background: #3b82f6;
        }

        .status-bar-alert {
            background: #f43f5e;
        }

        .status-bar-neutral {
            background: #94a3b8;
        }

        .status-row-ok td {
            background: var(--emerald-soft);
        }

        .status-row-watch td {
            background: var(--blue-soft);
        }

        .status-row-alert td {
            background: var(--rose-soft);
        }

        .status-row-neutral td {
            background: #ffffff;
        }

        .muted {
            color: var(--muted);
        }
    </style>
</head>
<body>
    @php
        $apports = $resultats['apports'] ?? [];
        $besoins = $resultats['besoins'] ?? [];
        $impacts = $resultats['impacts'] ?? [];
        $bilans = $resultats['bilans'] ?? [];
        $indicateurs = $resultats['indicateurs'] ?? [];
        $meta = $resultats['meta'] ?? [];
        // Unités affichées propres à la catégorie (UFV pour engraissement/agneaux ; UEL/UEM/UEB).
        $uniteEnergie = $meta['unite_fourragere'] ?? 'UFL';
        $uniteEncombrement = $meta['unite_encombrement'] ?? 'UE';
        $is2018 = ($resultats['inra'] ?? '2018') === '2018';
        $normes = $normes ?? \App\Support\RationNormes::payloadForUser(request()->user());
        $activeNormes = $normes['active'] ?? [];
        $normeDefinitions = [];

        foreach (($normes['editable'] ?? []) as $definition) {
            if (isset($definition['key'])) {
                $normeDefinitions[$definition['key']] = $definition;
            }
        }

        $format = static function ($value, int $decimals = 2): string {
            if ($value === null || $value === '') {
                return '–';
            }

            $decimals = max(0, min($decimals, 2));
            $formatted = number_format((float) $value, $decimals, ',', ' ');

            if ($decimals > 0) {
                $formatted = rtrim(rtrim($formatted, '0'), ',');
            }

            return $formatted === '-0' ? '0' : $formatted;
        };

        $metricNorme = static function (string $key) use ($activeNormes): array {
            $norme = $activeNormes[$key] ?? [];

            return [
                'min' => $norme['min'] ?? null,
                'max' => $norme['max'] ?? null,
            ];
        };

        $metricLabel = static function (string $key) use ($format, $metricNorme, $normeDefinitions): string {
            if (! isset($normeDefinitions[$key])) {
                return $key;
            }

            $definition = $normeDefinitions[$key];
            $norme = $metricNorme($key);
            $parts = array_values(array_filter([
                $norme['min'] !== null ? $format($norme['min'], $definition['decimals'] ?? 2) : null,
                $norme['max'] !== null ? $format($norme['max'], $definition['decimals'] ?? 2) : null,
            ], static fn ($value): bool => $value !== null));

            return $parts !== [] ? ($definition['label'] ?? $key).' ('.implode(' - ', $parts).')' : ($definition['label'] ?? $key);
        };

        $metricNote = static function (string $key) use ($format, $metricNorme, $normeDefinitions): string {
            if (! isset($normeDefinitions[$key])) {
                return $key;
            }

            $definition = $normeDefinitions[$key];
            $norme = $metricNorme($key);
            $unit = $definition['unit'] ?? null;
            $suffix = $unit ? ' '.$unit : '';

            if ($norme['min'] !== null && $norme['max'] !== null) {
                return 'Cible '.$format($norme['min'], $definition['decimals'] ?? 2).' à '.$format($norme['max'], $definition['decimals'] ?? 2).$suffix;
            }

            if ($norme['min'] !== null) {
                return 'Seuil '.$format($norme['min'], $definition['decimals'] ?? 2).$suffix;
            }

            if ($norme['max'] !== null) {
                return 'Seuil '.$format($norme['max'], $definition['decimals'] ?? 2).$suffix;
            }

            return $definition['label'] ?? $key;
        };

        $bilanSign = static function ($value, int $decimals = 1) use ($format): string {
            if ($value === null || $value === '') {
                return '–';
            }

            $value = (float) $value;

            return $value >= 0 ? '+'.$format($value, $decimals) : $format($value, $decimals);
        };

        $clamp = static function ($value, float $min, float $max): float {
            return max($min, min((float) $value, $max));
        };

        $safeRatio = static function ($apport, $besoin): ?float {
            if ($apport === null || $besoin === null) {
                return null;
            }

            $apport = (float) $apport;
            $besoin = (float) $besoin;

            if ($besoin <= 0) {
                return null;
            }

            return $apport / $besoin;
        };

        $coverageStatus = static function (?float $ratio): string {
            if ($ratio === null) {
                return 'neutral';
            }

            if ($ratio < 0.95) {
                return 'alert';
            }

            if ($ratio < 1 || $ratio > 1.15) {
                return 'watch';
            }

            return 'ok';
        };

        $goalStatus = static function ($limitant, $objectif): string {
            if ($limitant === null || $objectif === null) {
                return 'neutral';
            }

            $limitant = (float) $limitant;
            $objectif = (float) $objectif;

            if ($objectif <= 0) {
                return 'neutral';
            }

            if ($limitant < $objectif * 0.95) {
                return 'alert';
            }

            if ($limitant < $objectif) {
                return 'watch';
            }

            return 'ok';
        };

        $detailStatus = static function (array $row): string {
            $value = $row['value'] ?? null;

            if ($value === null || is_nan((float) $value)) {
                return 'neutral';
            }

            if (array_key_exists('min', $row) && $row['min'] !== null && $value < $row['min']) {
                return 'alert';
            }

            if (array_key_exists('max', $row) && $row['max'] !== null && $value > $row['max']) {
                return 'alert';
            }

            if (array_key_exists('goodAbove', $row) && $row['goodAbove'] !== null && $value < $row['goodAbove']) {
                return 'alert';
            }

            if (array_key_exists('goodBelow', $row) && $row['goodBelow'] !== null && $value > $row['goodBelow']) {
                return 'alert';
            }

            if (
                array_key_exists('min', $row)
                || array_key_exists('max', $row)
                || array_key_exists('goodAbove', $row)
                || array_key_exists('goodBelow', $row)
            ) {
                return 'ok';
            }

            return 'neutral';
        };

        $healthDetailStatus = static function (array $row) use ($detailStatus): string {
            $value = $row['value'] ?? null;
            $metricKey = $row['metricKey'] ?? null;

            if ($value === null || is_nan((float) $value)) {
                return 'neutral';
            }

            if ($metricKey === 'be') {
                if (($row['max'] ?? null) !== null && $value > $row['max']) {
                    return 'ok';
                }

                if (($row['min'] ?? null) !== null && $value > $row['min']) {
                    return 'watch';
                }

                return 'alert';
            }

            if (in_array($metricKey, ['amid_ru', 'pco_percent', 'ira'], true)) {
                if (($row['min'] ?? null) !== null && $value < $row['min']) {
                    return 'ok';
                }

                if (($row['max'] ?? null) !== null && $value < $row['max']) {
                    return 'watch';
                }

                return 'alert';
            }

            if ($metricKey === 'ndf_total') {
                if (($row['max'] ?? null) !== null && $value > $row['max']) {
                    return 'ok';
                }

                if (($row['min'] ?? null) !== null && $value > $row['min']) {
                    return 'watch';
                }

                return 'alert';
            }

            if ($metricKey === 'ph_ruminal') {
                return (($row['min'] ?? null) !== null && $value < $row['min']) ? 'alert' : 'ok';
            }

            return $detailStatus($row);
        };

        $fiberDetailStatus = static function (array $row) use ($detailStatus): string {
            $value = $row['value'] ?? null;
            $label = $row['label'] ?? '';
            $metricKey = $row['metricKey'] ?? null;

            if ($value === null || is_nan((float) $value)) {
                return 'neutral';
            }

            if ($metricKey === 'cb_par_kg_ms') {
                return (($row['min'] ?? null) !== null && $value < $row['min']) ? 'alert' : 'ok';
            }

            if (in_array($label, ['Apport en ADF', 'Apport en MS'], true)) {
                return 'neutral';
            }

            if ($metricKey === 'ndf_total') {
                if (($row['max'] ?? null) !== null && $value > $row['max']) {
                    return 'ok';
                }

                if (($row['min'] ?? null) !== null && $value > $row['min']) {
                    return 'watch';
                }

                return 'alert';
            }

            return $detailStatus($row);
        };

        $comparisonStatus = static function (array $row) use ($safeRatio): string {
            $ratio = $safeRatio($row['apport'] ?? null, $row['besoin'] ?? null);

            if ($ratio === null) {
                return 'neutral';
            }

            if ($ratio < 0.95) {
                return 'alert';
            }

            if ($ratio > 1.4) {
                return 'watch';
            }

            return 'ok';
        };

        $balanceStatus = static function (array $row) use ($safeRatio): string {
            $ratio = $safeRatio($row['apport'] ?? null, $row['besoin'] ?? null);

            if ($ratio === null) {
                return 'neutral';
            }

            if ($ratio <= 0.9) {
                return 'alert';
            }

            if ($ratio >= 1.1) {
                return 'watch';
            }

            return 'ok';
        };

        $statusLabel = static function (string $status): string {
            return match ($status) {
                'ok' => 'équilibré',
                'watch' => 'à surveiller',
                'alert' => 'prioritaire',
                default => 'info',
            };
        };

        $isIssue = static function (string $status): bool {
            return in_array($status, ['alert', 'watch'], true);
        };

        $aggregateStatus = static function (array $statuses): string {
            if (in_array('alert', $statuses, true)) {
                return 'alert';
            }

            if (in_array('watch', $statuses, true)) {
                return 'watch';
            }

            if (in_array('ok', $statuses, true)) {
                return 'ok';
            }

            return 'neutral';
        };

        $palette = static function (string $status): array {
            return match ($status) {
                'ok' => [
                    'text' => '#047857',
                    'soft' => '#ecfdf5',
                    'line' => '#a7f3d0',
                    'bar' => '#10b981',
                ],
                'watch' => [
                    'text' => '#1d4ed8',
                    'soft' => '#eff6ff',
                    'line' => '#bfdbfe',
                    'bar' => '#3b82f6',
                ],
                'alert' => [
                    'text' => '#be123c',
                    'soft' => '#fff1f2',
                    'line' => '#fecdd3',
                    'bar' => '#f43f5e',
                ],
                default => [
                    'text' => '#475569',
                    'soft' => '#f8fafc',
                    'line' => '#cbd5e1',
                    'bar' => '#94a3b8',
                ],
            };
        };

        $normalizedStatus = static function (?string $status): string {
            return in_array($status, ['ok', 'watch', 'alert'], true) ? $status : 'neutral';
        };

        $statusSurfaceClass = static fn (?string $status): string => 'status-surface-'.$normalizedStatus($status);
        $statusTextClass = static fn (?string $status): string => 'status-text-'.$normalizedStatus($status);
        $statusChipClass = static fn (?string $status): string => 'table-chip status-chip-'.$normalizedStatus($status);
        $statusBarClass = static fn (?string $status): string => 'status-bar-'.$normalizedStatus($status);
        $statusRowClass = static fn (?string $status): string => 'status-row-'.$normalizedStatus($status);

        $percentAttr = static function ($value) use ($clamp): string {
            $percent = number_format($clamp((float) $value, 0, 100), 2, '.', '');

            return rtrim(rtrim($percent, '0'), '.').'%';
        };

        $remainingPercentAttr = static function ($value) use ($clamp): string {
            $remaining = 100 - $clamp((float) $value, 0, 100);

            return rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.').'%';
        };

        $ratioPercent = static function ($ratio) use ($clamp): float {
            if ($ratio === null) {
                return 0;
            }

            return $clamp($ratio * 100, 0, 100);
        };

        $formatQuantity = static function ($quantity, bool $isVolonte, int $decimals = 2) use ($format): string {
            return $isVolonte ? 'à volonté' : $format($quantity, $decimals);
        };

        $findRowByLabel = static function (array $rows, string $label): ?array {
            foreach ($rows as $row) {
                if (($row['label'] ?? null) === $label) {
                    return $row;
                }
            }

            return null;
        };

        $findRowByMetricKey = static function (array $rows, string $metricKey): ?array {
            foreach ($rows as $row) {
                if (($row['metricKey'] ?? null) === $metricKey) {
                    return $row;
                }
            }

            return null;
        };

        $feedField = static function ($aliment, string $metric) use ($is2018) {
            if (! $aliment) {
                return null;
            }

            return match ($metric) {
                'ufl' => $is2018 ? $aliment->ufl : ($aliment->ufl2007 ?? $aliment->ufl),
                'ufv' => $is2018 ? $aliment->ufv : ($aliment->ufv2007 ?? $aliment->ufv),
                'protein_primary' => $is2018 ? $aliment->pdi : ($aliment->pdie2007 ?? $aliment->pdi),
                'protein_secondary' => $is2018 ? $aliment->bpr : ($aliment->pdin2007 ?? null),
                'ms' => $aliment->ms,
                default => $aliment->{$metric} ?? null,
            };
        };

        $weightedAverage = static function ($items, string $metric) use ($feedField): ?float {
            $totalWeight = 0.0;
            $weightedValue = 0.0;

            foreach ($items as $item) {
                $quantity = (float) ($item->quantite ?? 0);
                $value = $feedField($item->aliment ?? null, $metric);

                if ($quantity <= 0 || $value === null) {
                    continue;
                }

                $totalWeight += $quantity;
                $weightedValue += $quantity * (float) $value;
            }

            return $totalWeight > 0 ? $weightedValue / $totalWeight : null;
        };

        $componentDryMatter = static function ($quantity, bool $isMb, $ms): float {
            $quantity = (float) ($quantity ?? 0);

            if ($quantity <= 0) {
                return 0;
            }

            if ($isMb) {
                return $quantity * ((float) ($ms ?? 0)) / 100;
            }

            return $quantity;
        };

        $laitObjectif = (float) ($ration->lait_objectif ?? 0);
        $ufRatio = $safeRatio($apports['ufl'] ?? null, $besoins['uf_total'] ?? null);
        $proteinApport = $is2018
            ? ($apports['pdi'] ?? null)
            : min($apports['pdie'] ?? INF, $apports['pdin'] ?? INF);
        $proteinApport = is_infinite((float) $proteinApport) ? null : $proteinApport;
        $proteinRatio = $safeRatio($proteinApport, $besoins['pdi_total'] ?? null);
        $ueRatio = $safeRatio($apports['ue'] ?? null, $besoins['ci'] ?? null);

        $laitPermisRows = $is2018
            ? [
                ['label' => 'Par UFL', 'value' => $impacts['lait_par_ufl'] ?? null, 'decimals' => 2, 'unit' => 'kg/j'],
                ['label' => 'Par PDI', 'value' => $impacts['lait_par_pdi'] ?? null, 'decimals' => 2, 'unit' => 'kg/j'],
                ['label' => 'Lait limitant', 'value' => $impacts['lait_limitant'] ?? null, 'decimals' => 2, 'unit' => 'kg/j'],
            ]
            : [
                ['label' => 'Par UFL', 'value' => $impacts['lait_par_ufl'] ?? null, 'decimals' => 2, 'unit' => 'kg/j'],
                ['label' => 'Par PDIE', 'value' => $impacts['lait_par_pdie'] ?? null, 'decimals' => 2, 'unit' => 'kg/j'],
                ['label' => 'Par PDIN', 'value' => $impacts['lait_par_pdin'] ?? null, 'decimals' => 2, 'unit' => 'kg/j'],
            ];

        if ($is2018 && array_key_exists('production_lait_attendue', $impacts)) {
            $laitPermisRows[] = ['label' => 'Production attendue', 'value' => $impacts['production_lait_attendue'], 'decimals' => 2, 'unit' => 'kg/j'];
        }

        $laitLimitant = $impacts['lait_limitant'] ?? null;
        $productionLaitAttendue = $impacts['production_lait_attendue'] ?? null;

        if ($laitLimitant === null) {
            $laitCandidates = array_values(array_filter(array_map(
                static fn (array $row) => $row['value'] ?? null,
                $laitPermisRows
            ), static fn ($value) => $value !== null));
            $laitLimitant = $laitCandidates !== [] ? min($laitCandidates) : null;
        }

        $limitingMilkSource = 'Analyse indisponible';
        $limitingCandidates = array_values(array_filter(
            $laitPermisRows,
            static fn (array $row): bool => ! in_array($row['label'] ?? '', ['Lait limitant', 'Production attendue'], true) && ($row['value'] ?? null) !== null
        ));

        if ($limitingCandidates !== []) {
            usort($limitingCandidates, static fn (array $first, array $second) => ($first['value'] ?? INF) <=> ($second['value'] ?? INF));
            $limitingMilkSource = $limitingCandidates[0]['label'];
        }

        $laitComparable = $productionLaitAttendue ?? $laitLimitant;
        $laitDelta = $laitObjectif > 0 && $laitComparable !== null ? $laitComparable - $laitObjectif : null;
        $laitStatus = $goalStatus($laitComparable, $laitObjectif > 0 ? $laitObjectif : null);

        $proteinsRows = $is2018 ? [
            ['label' => 'Apport PDI', 'value' => $apports['pdi'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'Besoin PDI', 'value' => $besoins['pdi_total'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'PDI', 'value' => $indicateurs['pdi_par_kg_ms'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 2],
            ['metricKey' => 'eff_pdi', 'label' => $metricLabel('eff_pdi'), 'value' => isset($indicateurs['eff_pdi']) ? $indicateurs['eff_pdi'] * 100 : null, 'unit' => '%', 'decimals' => 0, ...$metricNorme('eff_pdi')],
            ['metricKey' => 'bpr', 'label' => $metricLabel('bpr'), 'value' => $indicateurs['bpr'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 2, ...$metricNorme('bpr')],
            ['label' => 'Lait permis par les PDI', 'value' => $impacts['lait_par_pdi'] ?? null, 'unit' => 'kg/j', 'decimals' => 2],
            ['label' => 'Production laitière attendue', 'value' => $impacts['production_lait_attendue'] ?? null, 'unit' => 'kg/j', 'decimals' => 2],
            ['label' => 'Azote urinaire', 'value' => $indicateurs['azote_urinaire'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'Azote fecale', 'value' => $indicateurs['azote_fecale'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
        ] : [];

        $healthRows = $is2018 ? [
            ['metricKey' => 'be', 'label' => $metricLabel('be'), 'value' => $indicateurs['be'] ?? null, 'unit' => 'mEq/kg MS', 'decimals' => 0, ...$metricNorme('be')],
            ['label' => 'MOD des concentrés (proxy interne)', 'value' => $indicateurs['mod_concentre'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 0],
            ['metricKey' => 'amid_ru', 'label' => $metricLabel('amid_ru'), 'value' => $indicateurs['amid_ru'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 0, ...$metricNorme('amid_ru')],
            ['metricKey' => 'pco_percent', 'label' => $metricLabel('pco_percent'), 'value' => $indicateurs['pco_percent'] ?? null, 'unit' => '% MS', 'decimals' => 0, ...$metricNorme('pco_percent')],
            ['label' => 'NDF des fourrages (proxy NDFfo)', 'value' => $indicateurs['ndf_fourrages'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 0],
            ['metricKey' => 'ndf_total', 'label' => $metricLabel('ndf_total'), 'value' => $indicateurs['ndf_total'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 0, ...$metricNorme('ndf_total')],
            ['metricKey' => 'ira', 'label' => $metricLabel('ira'), 'value' => $indicateurs['ira'] ?? null, 'unit' => null, 'decimals' => 2, ...$metricNorme('ira')],
            ['metricKey' => 'ph_ruminal', 'label' => $metricLabel('ph_ruminal'), 'value' => $indicateurs['ph_ruminal'] ?? null, 'unit' => null, 'decimals' => 2, ...$metricNorme('ph_ruminal')],
        ] : [];

        $fiberRows = $is2018 ? [
            ['label' => 'Apport en MS', 'value' => $apports['ms'] ?? null, 'unit' => 'kg', 'decimals' => 2],
            ['metricKey' => 'cb_par_kg_ms', 'label' => $metricLabel('cb_par_kg_ms'), 'value' => $indicateurs['cb_par_kg_ms'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 0, ...$metricNorme('cb_par_kg_ms')],
            ['label' => 'Apport en ADF', 'value' => $indicateurs['adf_par_kg_ms'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 0],
            ['metricKey' => 'ndf_total', 'label' => $metricLabel('ndf_total'), 'value' => $indicateurs['ndf_total'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 0, ...$metricNorme('ndf_total')],
            ['label' => 'NDF des fourrages (proxy NDFfo)', 'value' => $indicateurs['ndf_fourrages'] ?? null, 'unit' => 'g/kg MS', 'decimals' => 0],
        ] : [];

        $energyRows = $is2018 ? [
            ['label' => 'Apport '.$uniteEnergie, 'value' => $apports['ufl'] ?? null, 'unit' => $uniteEnergie.'/j', 'decimals' => 2],
            ['label' => 'Besoin '.$uniteEnergie, 'value' => $besoins['uf_total'] ?? null, 'unit' => $uniteEnergie.'/j', 'decimals' => 2],
            ['label' => 'Apport '.$uniteEnergie.'/kg MS', 'value' => $indicateurs['ufl_par_kg_ms'] ?? null, 'unit' => $uniteEnergie.'/kg MS', 'decimals' => 2],
            ['metricKey' => 'bil_ufl', 'label' => $metricLabel('bil_ufl'), 'value' => $impacts['bil_ufl'] ?? null, 'unit' => $uniteEnergie.'/j', 'decimals' => 2, ...$metricNorme('bil_ufl')],
            ['label' => 'Lait permis par les '.$uniteEnergie, 'value' => $impacts['lait_par_ufl'] ?? null, 'unit' => 'kg/j', 'decimals' => 2],
            ['label' => 'PLPot', 'value' => $indicateurs['pl_pot'] ?? null, 'unit' => 'kg/j', 'decimals' => 2],
            ['label' => 'Production CH4', 'value' => $impacts['ch4'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
        ] : [];

        $mineralBalanceRows = $is2018 ? [
            ['label' => 'BACA', 'value' => $indicateurs['baca'] ?? null, 'unit' => 'mEq/kg MS', 'decimals' => 0],
        ] : [];

        $mineralRows = $is2018 ? [
            ['label' => 'Calcium abs', 'apport' => $apports['caabs'] ?? null, 'besoin' => $besoins['caabs'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'Phosphore abs', 'apport' => $apports['pabs'] ?? ($apports['p'] ?? null), 'besoin' => $besoins['pabs'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'Magnésium abs', 'apport' => $apports['mgabs'] ?? null, 'besoin' => $besoins['mgabs'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'Sodium', 'apport' => $apports['na'] ?? null, 'besoin' => $besoins['na'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'Chlore', 'apport' => $apports['cl'] ?? null, 'besoin' => $besoins['cl'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'Potassium', 'apport' => $apports['k'] ?? null, 'besoin' => $besoins['k'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'Soufre', 'apport' => $apports['s'] ?? null, 'besoin' => $besoins['s'] ?? null, 'unit' => 'g/j', 'decimals' => 0],
            ['label' => 'Cobalt', 'apport' => $apports['co'] ?? null, 'besoin' => $besoins['co'] ?? null, 'unit' => 'mg/j', 'decimals' => 0],
            ['label' => 'Selenium', 'apport' => $apports['se'] ?? null, 'besoin' => $besoins['se'] ?? null, 'unit' => 'mg/j', 'decimals' => 0],
            ['label' => 'Zinc', 'apport' => $apports['zn'] ?? null, 'besoin' => $besoins['zn'] ?? null, 'unit' => 'mg/j', 'decimals' => 0],
            ['label' => 'Manganèse', 'apport' => $apports['mn'] ?? null, 'besoin' => $besoins['mn'] ?? null, 'unit' => 'mg/j', 'decimals' => 0],
            ['label' => 'Cuivre', 'apport' => $apports['cu'] ?? null, 'besoin' => $besoins['cu'] ?? null, 'unit' => 'mg/j', 'decimals' => 0],
            ['label' => 'Iode', 'apport' => $apports['i'] ?? null, 'besoin' => $besoins['i'] ?? null, 'unit' => 'mg/j', 'decimals' => 0],
            ['label' => 'Vitamine A', 'apport' => $apports['vit_a'] ?? null, 'besoin' => $besoins['vit_a'] ?? null, 'unit' => 'UI/j', 'decimals' => 0],
            ['label' => 'Vitamine D', 'apport' => $apports['vit_d'] ?? null, 'besoin' => $besoins['vit_d'] ?? null, 'unit' => 'UI/j', 'decimals' => 0],
            ['label' => 'Vitamine E', 'apport' => $apports['vit_e'] ?? null, 'besoin' => $besoins['vit_e'] ?? null, 'unit' => 'UI/j', 'decimals' => 0],
        ] : [];

        $balanceRows = [
            [
                'label' => $uniteEnergie,
                'apport' => $apports['ufl'] ?? null,
                'besoin' => $besoins['uf_total'] ?? null,
                'bilan' => $bilans['ufl'] ?? null,
                'decimals' => 2,
            ],
            [
                'label' => $uniteEncombrement.' (kg MS)',
                'apport' => $apports['ue'] ?? null,
                'besoin' => $besoins['ci'] ?? null,
                'bilan' => $bilans['ue'] ?? null,
                'decimals' => 2,
                'besoinSuffix' => 'CI',
            ],
        ];

        if ($is2018) {
            $balanceRows[] = [
                'label' => 'PDI (g/j)',
                'apport' => $apports['pdi'] ?? null,
                'besoin' => $besoins['pdi_total'] ?? null,
                'bilan' => $bilans['pdi'] ?? null,
                'decimals' => 0,
            ];
        } else {
            $balanceRows[] = [
                'label' => 'PDIE (g/j)',
                'apport' => $apports['pdie'] ?? null,
                'besoin' => $besoins['pdi_total'] ?? null,
                'bilan' => $bilans['pdie'] ?? null,
                'decimals' => 0,
            ];
            $balanceRows[] = [
                'label' => 'PDIN (g/j)',
                'apport' => $apports['pdin'] ?? null,
                'besoin' => null,
                'bilan' => $bilans['pdin'] ?? null,
                'decimals' => 0,
            ];
        }

        $balanceRows[] = [
            'label' => 'Ca abs (g/j)',
            'apport' => $apports['caabs'] ?? null,
            'besoin' => $besoins['caabs'] ?? null,
            'bilan' => $bilans['caabs'] ?? null,
            'decimals' => 1,
        ];
        $balanceRows[] = [
            'label' => 'P abs (g/j)',
            'apport' => $apports['pabs'] ?? ($apports['p'] ?? null),
            'besoin' => $besoins['pabs'] ?? null,
            'bilan' => $bilans['pabs'] ?? null,
            'decimals' => 1,
        ];

        $fattyAcidRows = $is2018 ? [
            ['label' => 'Production AGV totale dans le rumen', 'value' => $indicateurs['prod_agvt_jour'] ?? null, 'unit' => 'mol/j', 'decimals' => 2],
            ['label' => 'Acide acétique', 'value' => $indicateurs['acetate'] ?? null, 'unit' => 'mol/100 mol AGV', 'decimals' => 0],
            ['label' => 'Acide propionique', 'value' => $indicateurs['propionate'] ?? null, 'unit' => 'mol/100 mol AGV', 'decimals' => 0],
            ['label' => 'Acide butyrique', 'value' => $indicateurs['butyrate'] ?? null, 'unit' => 'mol/100 mol AGV', 'decimals' => 0],
        ] : [];

        $annotateRows = static function (array $rows, callable $resolver): array {
            return array_map(static function (array $row) use ($resolver): array {
                $row['status'] = $resolver($row);

                return $row;
            }, $rows);
        };

        $proteinsRows = $annotateRows($proteinsRows, $detailStatus);
        $healthRows = $annotateRows($healthRows, $healthDetailStatus);
        $fiberRows = $annotateRows($fiberRows, $fiberDetailStatus);
        $energyRows = $annotateRows($energyRows, $detailStatus);
        $mineralBalanceRows = $annotateRows($mineralBalanceRows, $detailStatus);
        $mineralRows = $annotateRows($mineralRows, $comparisonStatus);
        $balanceRows = $annotateRows($balanceRows, $balanceStatus);
        $fattyAcidRows = $annotateRows($fattyAcidRows, $detailStatus);

        $healthStatuses = array_values(array_filter(array_map(static fn (array $row) => $row['status'], $healthRows), static fn (string $status) => $status !== 'neutral'));
        $healthStatus = $aggregateStatus($healthStatuses);
        $healthIssueCount = count(array_filter($healthRows, static fn (array $row) => $isIssue($row['status'])));

        $mineralStatuses = array_values(array_filter(array_map(static fn (array $row) => $row['status'], $mineralRows), static fn (string $status) => $status !== 'neutral'));
        $mineralStatus = $aggregateStatus($mineralStatuses);
        $mineralIssueCount = count(array_filter($mineralRows, static fn (array $row) => $isIssue($row['status'])));

        $technicalPanels = [
            [
                'label' => 'Énergie',
                'valueLabel' => $ufRatio === null ? '–' : $format($ufRatio * 100, 0).' %',
                'note' => $format($apports['ufl'] ?? null, 2).' / '.$format($besoins['uf_total'] ?? null, 2).' '.$uniteEnergie,
                'percent' => $ratioPercent($ufRatio),
                'status' => $coverageStatus($ufRatio),
            ],
            [
                'label' => 'Protéines',
                'valueLabel' => $proteinRatio === null ? '–' : $format($proteinRatio * 100, 0).' %',
                'note' => $format($proteinApport, 0).' / '.$format($besoins['pdi_total'] ?? null, 0).' g',
                'percent' => $ratioPercent($proteinRatio),
                'status' => $coverageStatus($proteinRatio),
            ],
            [
                'label' => 'Ingestion',
                'valueLabel' => $ueRatio === null ? '–' : $format($ueRatio * 100, 0).' %',
                'note' => $format($apports['ue'] ?? null, 2).' / '.$format($besoins['ci'] ?? null, 2).' CI',
                'percent' => $ratioPercent($ueRatio),
                'status' => $coverageStatus($ueRatio),
            ],
        ];

        if ($is2018) {
            $technicalPanels[] = [
                'label' => 'Santé ruminale',
                'valueLabel' => $healthIssueCount === 0 ? 'Stable' : $healthIssueCount.' écart'.($healthIssueCount > 1 ? 's' : ''),
                'note' => 'pH AmiD_ru '.$format($indicateurs['ph_ruminal'] ?? null, 2).' · IRA '.$format($indicateurs['ira'] ?? null, 2),
                'percent' => $clamp(100 - $healthIssueCount * 22, 18, 100),
                'status' => $healthStatus,
            ];
            $technicalPanels[] = [
                'label' => 'Minéraux',
                'valueLabel' => $mineralIssueCount === 0 ? 'Couverts' : $mineralIssueCount.' déficit'.($mineralIssueCount > 1 ? 's' : ''),
                'note' => 'Macro, oligos et vitamines',
                'percent' => $clamp(100 - $mineralIssueCount * 10, 18, 100),
                'status' => $mineralStatus,
            ];
        } else {
            $technicalPanels[] = [
                'label' => 'Rmic',
                'valueLabel' => $format($impacts['rmic'] ?? null, 2),
                'note' => 'Couverture microbienne',
                'percent' => (($impacts['rmic'] ?? 0) >= 0) ? 100 : 35,
                'status' => (($impacts['rmic'] ?? 0) >= 0) ? 'ok' : 'alert',
            ];
            $technicalPanels[] = [
                'label' => 'Minéraux',
                'valueLabel' => $format($bilans['caabs'] ?? null, 1),
                'note' => 'Lecture calcium absorbable',
                'percent' => $ratioPercent($safeRatio($apports['caabs'] ?? null, $besoins['caabs'] ?? null)),
                'status' => $coverageStatus($safeRatio($apports['caabs'] ?? null, $besoins['caabs'] ?? null)),
            ];
        }

        $topMetrics = [
            [
                'label' => $productionLaitAttendue !== null ? 'Lait attendu' : 'Lait permis',
                'valueLabel' => $format($laitComparable, 1).' kg/j',
                'note' => $productionLaitAttendue !== null
                    ? 'PLPot '.$format($indicateurs['pl_pot'] ?? null, 1).' · plafond '.$format($laitLimitant, 1)
                    : $limitingMilkSource,
                'status' => $laitStatus,
            ],
            [
                'label' => 'Objectif',
                'valueLabel' => $laitObjectif > 0 ? $format($laitObjectif, 0).' kg/j' : 'Sans cible',
                'note' => $laitDelta === null ? 'Aucun objectif saisi' : 'Écart '.$bilanSign($laitDelta, 1).' kg/j',
                'status' => $laitStatus,
            ],
            [
                'label' => 'Couverture '.$uniteEnergie,
                'valueLabel' => $ufRatio === null ? '–' : $format($ufRatio * 100, 0).' %',
                'note' => $format($apports['ufl'] ?? null, 2).' / '.$format($besoins['uf_total'] ?? null, 2).' '.$uniteEnergie,
                'status' => $coverageStatus($ufRatio),
            ],
            [
                'label' => 'Couverture protéines',
                'valueLabel' => $proteinRatio === null ? '–' : $format($proteinRatio * 100, 0).' %',
                'note' => $is2018 ? 'PDI' : 'PDIE / PDIN',
                'status' => $coverageStatus($proteinRatio),
            ],
            $is2018
                ? [
                    'label' => 'Santé ruminale',
                    'valueLabel' => $format($indicateurs['ph_ruminal'] ?? null, 2),
                    'note' => 'IRA '.$format($indicateurs['ira'] ?? null, 2),
                    'status' => ($healthRows[array_key_last($healthRows)]['status'] ?? 'neutral'),
                ]
                : [
                    'label' => 'Rmic',
                    'valueLabel' => $format($impacts['rmic'] ?? null, 2),
                    'note' => 'Bilan microbien',
                    'status' => (($impacts['rmic'] ?? 0) >= 0) ? 'ok' : 'alert',
                ],
        ];

        $insightCandidates = [
            [
                'label' => 'Objectif laitier',
                'valueLabel' => $laitDelta === null ? 'Sans cible' : $bilanSign($laitDelta, 1).' kg/j',
                'note' => $productionLaitAttendue !== null
                    ? 'Lait attendu '.$format($productionLaitAttendue, 1).' kg/j'
                    : 'Lait permis '.$format($laitLimitant, 1).' kg/j',
                'status' => $laitStatus,
                'percent' => $laitObjectif > 0 ? $ratioPercent($safeRatio($laitComparable, $laitObjectif)) : 0,
            ],
            [
                'label' => 'Énergie',
                'valueLabel' => $ufRatio === null ? '–' : $format($ufRatio * 100, 0).' %',
                'note' => $format($apports['ufl'] ?? null, 2).' apportés pour '.$format($besoins['uf_total'] ?? null, 2).' requis',
                'status' => $coverageStatus($ufRatio),
                'percent' => $ratioPercent($ufRatio),
            ],
            [
                'label' => 'Protéines',
                'valueLabel' => $proteinRatio === null ? '–' : $format($proteinRatio * 100, 0).' %',
                'note' => $is2018 ? 'Couverture PDI' : 'Couverture PDIE / PDIN',
                'status' => $coverageStatus($proteinRatio),
                'percent' => $ratioPercent($proteinRatio),
            ],
            [
                'label' => 'Ingestion',
                'valueLabel' => $ueRatio === null ? '–' : $format($ueRatio * 100, 0).' %',
                'note' => $format($apports['ue'] ?? null, 2).' ingérés pour '.$format($besoins['ci'] ?? null, 2).' visés',
                'status' => $coverageStatus($ueRatio),
                'percent' => $ratioPercent($ueRatio),
            ],
        ];

        if ($is2018) {
            $iraRow = $findRowByMetricKey($healthRows, 'ira');
            $phRow = $findRowByMetricKey($healthRows, 'ph_ruminal');
            $bprRow = $findRowByMetricKey($proteinsRows, 'bpr');

            $insightCandidates[] = [
                'label' => 'Santé ruminale',
                'valueLabel' => $healthIssueCount === 0 ? 'Stable' : $healthIssueCount.' écart'.($healthIssueCount > 1 ? 's' : ''),
                'note' => 'pH AmiD_ru '.$format($indicateurs['ph_ruminal'] ?? null, 2).' · IRA '.$format($indicateurs['ira'] ?? null, 2),
                'status' => $healthStatus,
                'percent' => $clamp(100 - $healthIssueCount * 22, 18, 100),
            ];
            $insightCandidates[] = [
                'label' => 'Acidose',
                'valueLabel' => $format($indicateurs['ira'] ?? null, 2),
                'note' => $metricNote('ira'),
                'status' => $iraRow['status'] ?? 'neutral',
                'percent' => match ($iraRow['status'] ?? 'neutral') {
                    'ok' => 100,
                    'watch' => 60,
                    'alert' => 25,
                    default => 0,
                },
            ];
            $insightCandidates[] = [
                'label' => 'pH ruminal',
                'valueLabel' => $format($indicateurs['ph_ruminal'] ?? null, 2),
                'note' => 'Équation 15.4 via AmiD_ru',
                'status' => $phRow['status'] ?? 'neutral',
                'percent' => $clamp(
                    ((float) ($indicateurs['ph_ruminal'] ?? 0) / max((float) (($metricNorme('ph_ruminal')['min'] ?? 0) ?: 1), 0.01)) * 100,
                    0,
                    100,
                ),
            ];
            $insightCandidates[] = [
                'label' => 'BPR',
                'valueLabel' => $format($indicateurs['bpr'] ?? null, 2),
                'note' => $metricNote('bpr'),
                'status' => $bprRow['status'] ?? 'neutral',
                'percent' => match ($bprRow['status'] ?? 'neutral') {
                    'ok' => 100,
                    'watch' => 60,
                    'alert' => 25,
                    default => 0,
                },
            ];
            $insightCandidates[] = [
                'label' => 'Minéraux et vitamines',
                'valueLabel' => $mineralIssueCount === 0 ? 'Couverts' : $mineralIssueCount.' écart'.($mineralIssueCount > 1 ? 's' : ''),
                'note' => 'Macro, oligos et vitamines',
                'status' => $mineralStatus,
                'percent' => $clamp(100 - $mineralIssueCount * 10, 18, 100),
            ];
        } else {
            $insightCandidates[] = [
                'label' => 'Rmic',
                'valueLabel' => $format($impacts['rmic'] ?? null, 2),
                'note' => 'Bilan microbien',
                'status' => (($impacts['rmic'] ?? 0) >= 0) ? 'ok' : 'alert',
                'percent' => (($impacts['rmic'] ?? 0) >= 0) ? 100 : 35,
            ];
        }

        $priorityInsights = array_slice(array_values(array_filter(
            $insightCandidates,
            static fn (array $item): bool => $isIssue($item['status'] ?? 'neutral')
        )), 0, 4);
        $strengthInsights = array_slice(array_values(array_filter(
            $insightCandidates,
            static fn (array $item): bool => ($item['status'] ?? 'neutral') === 'ok'
        )), 0, 4);

        $milkGraphRows = array_values(array_filter(
            array_merge(
                $laitPermisRows,
                $laitObjectif > 0 ? [['label' => 'Objectif', 'value' => $laitObjectif, 'decimals' => 2, 'unit' => 'kg/j']] : []
            ),
            static fn (array $row): bool => ($row['value'] ?? null) !== null
        ));
        $milkGraphMax = $milkGraphRows !== [] ? max(array_map(static fn (array $row) => (float) $row['value'], $milkGraphRows)) : 0;

        $componentRows = [];

        foreach ($ration->rationAliments as $rationAliment) {
            $ms = $feedField($rationAliment->aliment, 'ms');
            $dryMatter = $componentDryMatter($rationAliment->quantite, (bool) $rationAliment->is_mb, $ms);
            $quantityMb = $dryMatter > 0 && $ms !== null && (float) $ms > 0
                ? $dryMatter / (((float) $ms) / 100)
                : null;

            $componentRows[] = [
                'label' => $rationAliment->aliment->libelle0,
                'subtitle' => $rationAliment->aliment->libelle1,
                'type' => $rationAliment->aliment->type ?? 'Aliment',
                'quantity' => $rationAliment->quantite,
                'quantity_label' => $formatQuantity($rationAliment->quantite, (bool) $rationAliment->is_volonte),
                'mode' => $rationAliment->is_volonte ? 'à volonté' : ($rationAliment->is_mb ? 'kg MB/j' : 'kg MS/j'),
                'quantity_mb' => $quantityMb,
                'ms' => $ms,
                'dry_matter' => $dryMatter,
                'composition' => null,
            ];
        }

        foreach ($ration->melanges as $melange) {
            $melangeMs = $weightedAverage($melange->melangeAliments, 'ms');
            $dryMatter = $componentDryMatter($melange->quantite, (bool) $melange->is_mb, $melangeMs);
            $quantityMb = $dryMatter > 0 && $melangeMs !== null && (float) $melangeMs > 0
                ? $dryMatter / (((float) $melangeMs) / 100)
                : null;
            $ingredientNames = array_values(array_filter(array_map(static function ($melangeAliment) {
                return $melangeAliment->aliment?->libelle0;
            }, $melange->melangeAliments->all())));

            $componentRows[] = [
                'label' => $melange->nom ?: 'Mélange',
                'subtitle' => count($ingredientNames) > 0 ? implode(' · ', $ingredientNames) : null,
                'type' => 'Mélange',
                'quantity' => $melange->quantite,
                'quantity_label' => $formatQuantity($melange->quantite, (bool) $melange->is_volonte),
                'mode' => $melange->is_volonte ? 'à volonté' : ($melange->is_mb ? 'kg MB/j' : 'kg MS/j'),
                'quantity_mb' => $quantityMb,
                'ms' => $melangeMs,
                'dry_matter' => $dryMatter,
                'composition' => $ingredientNames,
            ];
        }

        $totalDryMatter = array_sum(array_map(static fn (array $row): float => (float) $row['dry_matter'], $componentRows));

        foreach ($componentRows as $index => $componentRow) {
            $componentRows[$index]['share'] = $totalDryMatter > 0 ? ((float) $componentRow['dry_matter'] / $totalDryMatter) * 100 : 0;
        }

        usort($componentRows, static fn (array $first, array $second) => ($second['share'] ?? 0) <=> ($first['share'] ?? 0));

        $feedValueRows = [];

        foreach ($ration->rationAliments as $rationAliment) {
            $feedValueRows[] = [
                'label' => $rationAliment->aliment->libelle0,
                'subtitle' => $rationAliment->aliment->libelle1,
                'type' => $rationAliment->aliment->type ?? 'Aliment',
                'ufl' => $feedField($rationAliment->aliment, 'ufl'),
                'ufv' => $feedField($rationAliment->aliment, 'ufv'),
                'protein_primary' => $feedField($rationAliment->aliment, 'protein_primary'),
                'protein_secondary' => $feedField($rationAliment->aliment, 'protein_secondary'),
                'ms' => $feedField($rationAliment->aliment, 'ms'),
            ];
        }

        foreach ($ration->melanges as $melange) {
            $feedValueRows[] = [
                'label' => $melange->nom ?: 'Mélange',
                'subtitle' => count($melange->melangeAliments) > 0 ? 'Moyenne pondérée des ingrédients' : null,
                'type' => 'Mélange',
                'ufl' => $weightedAverage($melange->melangeAliments, 'ufl'),
                'ufv' => $weightedAverage($melange->melangeAliments, 'ufv'),
                'protein_primary' => $weightedAverage($melange->melangeAliments, 'protein_primary'),
                'protein_secondary' => $weightedAverage($melange->melangeAliments, 'protein_secondary'),
                'ms' => $weightedAverage($melange->melangeAliments, 'ms'),
            ];
        }

        $proteinPrimaryLabel = $is2018 ? 'PDI' : 'PDIE';
        $proteinSecondaryLabel = $is2018 ? 'BPR' : 'PDIN';
        $generatedAt = now()->format('d/m/Y');
        $objectifLabel = $laitObjectif > 0 ? $format($laitObjectif, 0).' kg/j' : 'Sans objectif';
        $coutTotal = ($ration->effectif ?? null) && isset($impacts['cout_animal']) ? (float) $ration->effectif * (float) $impacts['cout_animal'] : null;
        $impactSummaryRows = [
            ['label' => 'Coût / animal / jour', 'value' => $impacts['cout_animal'] ?? null, 'unit' => '€', 'decimals' => 2],
            ['label' => 'Coût / 1 000 L', 'value' => $impacts['cout_1000l'] ?? null, 'unit' => '€', 'decimals' => 2],
            ['label' => 'Eau bue estimée', 'value' => $impacts['eau_bue'] ?? null, 'unit' => 'L/j', 'decimals' => 1],
        ];
        $agvTotal = ($indicateurs['acetate'] ?? 0) + ($indicateurs['propionate'] ?? 0) + ($indicateurs['butyrate'] ?? 0);
        $agvOther = max(0, 100 - $agvTotal);
        $acetateWidth = $clamp((float) ($indicateurs['acetate'] ?? 0), 0, 100);
        $propionateWidth = $clamp((float) ($indicateurs['propionate'] ?? 0), 0, 100);
        $butyrateWidth = $clamp((float) ($indicateurs['butyrate'] ?? 0), 0, 100);
        $otherAgvWidth = $clamp((float) $agvOther, 0, 100);
    @endphp

    <div class="report">
        <x-pdf.clinic-header :clinic-header="$clinicHeader ?? null" />

        <section class="hero">
            <div class="hero-head">
                <div class="hero-copy">
                    <p class="eyebrow">Rapport ration</p>
                    <h1 class="hero-title">{{ $ration->nom }}</h1>

                    <div class="meta-row">
                        <span class="badge">Plan {{ $plan->nom }}</span>
                        <span class="badge">INRA {{ $plan->inra }}</span>
                        <span class="badge">{{ $ration->categorie_animal ?? 'Catégorie non renseignée' }}</span>
                        <span class="badge">Objectif {{ $objectifLabel }}</span>
                    </div>
                </div>
            </div>

            <div class="panel-grid">
                    @foreach($technicalPanels as $panel)
                    @php
                        $panelColors = $palette($panel['status']);
                    @endphp
                    <article class="panel-card {{ $statusSurfaceClass($panel['status']) }}">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                            <p class="panel-label">{{ $panel['label'] }}</p>
                        </div>
                        <p class="panel-value {{ $statusTextClass($panel['status']) }}">{{ $panel['valueLabel'] }}</p>
                        <p class="panel-note">{{ $panel['note'] }}</p>
                        <div class="progress">
                            <table class="bar-table" role="presentation">
                                <tr>
                                    <td class="progress-bar {{ $statusBarClass($panel['status']) }}" width="{{ $percentAttr($panel['percent']) }}"></td>
                                    <td width="{{ $remainingPercentAttr($panel['percent']) }}"></td>
                                </tr>
                            </table>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="metric-grid">
            @foreach($topMetrics as $metric)
                @php
                    $metricColors = $palette($metric['status']);
                @endphp
                <article class="metric-card {{ $statusSurfaceClass($metric['status']) }}">
                    <p class="metric-label">{{ $metric['label'] }}</p>
                    <p class="metric-value {{ $statusTextClass($metric['status']) }}">{{ $metric['valueLabel'] }}</p>
                    <p class="metric-note">{{ $metric['note'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="section-head" style="margin-top: 24px;">
            <div>
                <h2 class="section-title">Graphiques d’analyse</h2>
            </div>
        </div>

        <div class="split">
            <article class="card">
                <h3>Couvertures nutritionnelles</h3>

                <div class="chart-list">
                    @foreach($technicalPanels as $panel)
                        @php
                            $panelColors = $palette($panel['status']);
                        @endphp
                        <div class="chart-row">
                            <div class="chart-head">
                                <div>
                                    <div class="chart-label">{{ $panel['label'] }}</div>
                                    <div class="chart-note">{{ $panel['note'] }}</div>
                                </div>
                                <div class="chart-value {{ $statusTextClass($panel['status']) }}">{{ $panel['valueLabel'] }}</div>
                            </div>
                            <div class="chart-rail">
                                <table class="bar-table" role="presentation">
                                    <tr>
                                        <td class="chart-fill {{ $statusBarClass($panel['status']) }}" width="{{ $percentAttr($panel['percent']) }}"></td>
                                        <td width="{{ $remainingPercentAttr($panel['percent']) }}"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="card">
                <h3>Lait permis et objectif</h3>

                <div class="chart-list">
                    @if($milkGraphRows !== [])
                        @foreach($milkGraphRows as $row)
                            @php
                                $rowStatus = ($row['label'] ?? '') === 'Objectif'
                                    ? $laitStatus
                                    : (($row['label'] ?? '') === 'Lait limitant' ? $laitStatus : 'neutral');
                                $rowColors = $palette($rowStatus);
                                $rowPercent = $milkGraphMax > 0 ? $clamp(((float) $row['value'] / $milkGraphMax) * 100, 0, 100) : 0;
                            @endphp
                            <div class="chart-row">
                                <div class="chart-head">
                                    <div class="chart-label">{{ $row['label'] }}</div>
                                    <div class="chart-value {{ $statusTextClass($rowStatus) }}">
                                        {{ $format($row['value'], $row['decimals'] ?? 2) }}
                                        <span class="muted">{{ $row['unit'] ?? '' }}</span>
                                    </div>
                                </div>
                                <div class="chart-rail">
                                    <table class="bar-table" role="presentation">
                                        <tr>
                                            <td class="chart-fill {{ $statusBarClass($rowStatus) }}" width="{{ $percentAttr($rowPercent) }}"></td>
                                            <td width="{{ $remainingPercentAttr($rowPercent) }}"></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="card-copy">Aucune donnée exploitable pour ce graphique.</p>
                    @endif
                </div>
            </article>
        </div>

        <div class="split" style="margin-top: 12px;">
            <article class="card">
                <h3>Répartition de la ration</h3>

                <div class="chart-list">
                    @if($componentRows !== [])
                        @foreach($componentRows as $row)
                            @php
                                $rowColors = $palette($row['share'] >= 20 ? 'ok' : ($row['share'] >= 10 ? 'watch' : 'neutral'));
                            @endphp
                            <div class="chart-row">
                                <div class="chart-head">
                                    <div>
                                        <div class="chart-label">{{ $row['label'] }}</div>
                                        @if($row['subtitle'])
                                            <div class="chart-note">{{ $row['subtitle'] }}</div>
                                        @endif
                                    </div>
                                    <div class="chart-value">
                                        {{ $format($row['share'], 1) }} %
                                        <span class="muted">· {{ $format($row['dry_matter'], 2) }} kg MS</span>
                                    </div>
                                </div>
                                <div class="chart-rail">
                                    <table class="bar-table" role="presentation">
                                        <tr>
                                            <td class="chart-fill {{ $statusBarClass($row['share'] >= 20 ? 'ok' : ($row['share'] >= 10 ? 'watch' : 'neutral')) }}" width="{{ $percentAttr($row['share']) }}"></td>
                                            <td width="{{ $remainingPercentAttr($row['share']) }}"></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="card-copy">La ration ne contient pas encore de constituants exploitables.</p>
                    @endif
                </div>
            </article>

            <article class="card">
                <h3>Profil ruminal</h3>
                <p class="card-copy">
                    @if($is2018)
                        Répartition des AGV produits dans le rumen et lecture rapide de la fermentation.
                    @else
                        Les indicateurs AGV détaillés sont disponibles uniquement en INRA 2018.
                    @endif
                </p>

                @if($is2018)
                    <div class="agv-bar">
                        <table class="bar-table" role="presentation">
                            <tr>
                                <td class="agv-segment" width="{{ $percentAttr($acetateWidth) }}" bgcolor="#0f766e"></td>
                                <td class="agv-segment" width="{{ $percentAttr($propionateWidth) }}" bgcolor="#2563eb"></td>
                                <td class="agv-segment" width="{{ $percentAttr($butyrateWidth) }}" bgcolor="#c2410c"></td>
                                @if($agvOther > 0)
                                    <td class="agv-segment" width="{{ $percentAttr($otherAgvWidth) }}" bgcolor="#cbd5e1"></td>
                                @endif
                            </tr>
                        </table>
                    </div>

                    <div class="legend">
                        <div class="legend-item"><span class="legend-dot" style="background:#0f766e;"></span> Acétate: {{ $format($indicateurs['acetate'] ?? null, 0) }} mol/100 mol AGV</div>
                        <div class="legend-item"><span class="legend-dot" style="background:#2563eb;"></span> Propionate: {{ $format($indicateurs['propionate'] ?? null, 0) }} mol/100 mol AGV</div>
                        <div class="legend-item"><span class="legend-dot" style="background:#c2410c;"></span> Butyrate: {{ $format($indicateurs['butyrate'] ?? null, 0) }} mol/100 mol AGV</div>
                        @if($agvOther > 0)
                            <div class="legend-item"><span class="legend-dot" style="background:#cbd5e1;"></span> Autres AGV: {{ $format($agvOther, 0) }} mol/100 mol AGV</div>
                        @endif
                        <div class="legend-item" style="margin-top:10px;">Production totale: {{ $format($indicateurs['prod_agvt_jour'] ?? null, 2) }} mol/j</div>
                    </div>
                @else
                    <div class="chart-list">
                        <div class="chart-row">
                            <div class="chart-head">
                                <div class="chart-label">Rmic</div>
                                <div class="chart-value">{{ $format($impacts['rmic'] ?? null, 2) }}</div>
                            </div>
                            <div class="chart-rail">
                                <table class="bar-table" role="presentation">
                                    <tr>
                                        <td class="chart-fill {{ $statusBarClass((($impacts['rmic'] ?? 0) >= 0) ? 'ok' : 'alert') }}" width="{{ $percentAttr((($impacts['rmic'] ?? 0) >= 0) ? 100 : 35) }}"></td>
                                        <td></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </article>
        </div>

        <section class="sheet page-break-before">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Composition et valeurs alimentaires</h2>
                </div>
            </div>

            <div class="table-section">
                <h3 class="table-section-title">Composition de la ration</h3>
                <div class="component-list">
                    @if($componentRows !== [])
                        @foreach($componentRows as $row)
                            <article class="component-card-pdf">
                                <div class="component-head">
                                    <div>
                                        <span class="component-name">{{ $row['label'] }}</span>
                                        @if($row['subtitle'])
                                            <span class="component-sub">{{ $row['subtitle'] }}</span>
                                        @endif
                                        <div class="component-entry">
                                            Saisie :
                                            {{ $row['quantity_label'] }}
                                            @if($row['quantity_label'] !== 'à volonté')
                                                <span class="muted">{{ $row['mode'] }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="component-head-meta">
                                            <span class="table-chip type-chip">{{ $row['type'] }}</span>
                                    </div>
                                </div>

                                <div class="component-metrics">
                                    <div class="component-metric">
                                        <div class="component-metric-label">Quantité</div>
                                        <div class="component-metric-value">
                                            {{ $row['quantity_label'] }}
                                            @if($row['quantity_label'] !== 'à volonté')
                                                <span class="table-note">{{ $row['mode'] }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="component-metric">
                                        <div class="component-metric-label">Quantité MB</div>
                                        <div class="component-metric-value">
                                            {{ $format($row['quantity_mb'], 2) }}
                                            @if($row['quantity_mb'] !== null)
                                                <span class="table-note">kg MB/j</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="component-metric">
                                        <div class="component-metric-label">MS</div>
                                        <div class="component-metric-value">
                                            {{ $format($row['ms'], 2) }}
                                            @if($row['ms'] !== null)
                                                <span class="table-note">%</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="component-metric">
                                        <div class="component-metric-label">Apport MS</div>
                                        <div class="component-metric-value">
                                            {{ $format($row['dry_matter'], 2) }}
                                            @if($row['dry_matter'] !== null)
                                                <span class="table-note">kg MS/j</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="component-metric">
                                        <div class="component-metric-label">Part ration</div>
                                        <div class="component-metric-value">
                                            {{ $format($row['share'], 1) }} %
                                            <div class="mini-bar">
                                                <table class="bar-table" role="presentation">
                                                    <tr>
                                                        <td class="mini-bar-fill" width="{{ $percentAttr($row['share']) }}" bgcolor="#0f6cbd"></td>
                                                        <td width="{{ $remainingPercentAttr($row['share']) }}"></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    @else
                        <div class="component-card-pdf">Aucun constituant dans cette ration.</div>
                    @endif
                </div>
            </div>

            <div class="table-section">
                <h3 class="table-section-title">Valeurs alimentaires des constituants</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Constituant</th>
                                <th class="table-number">UFL</th>
                                <th class="table-number">UFV</th>
                                <th class="table-number">{{ $proteinPrimaryLabel }}</th>
                                <th class="table-number">{{ $proteinSecondaryLabel }}</th>
                                <th class="table-number">MS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($feedValueRows !== [])
                                @foreach($feedValueRows as $row)
                                    <tr>
                                        <td>
                                            <span class="component-name">{{ $row['label'] }}</span>
                                            <span class="table-chip type-chip">{{ $row['type'] }}</span>
                                            @if($row['subtitle'])
                                                <span class="component-sub">{{ $row['subtitle'] }}</span>
                                            @endif
                                        </td>
                                        <td class="table-number">{{ $format($row['ufl'], 2) }}</td>
                                        <td class="table-number">{{ $format($row['ufv'], 2) }}</td>
                                        <td class="table-number">{{ $format($row['protein_primary'], $is2018 ? 0 : 0) }}</td>
                                        <td class="table-number">{{ $format($row['protein_secondary'], $is2018 ? 2 : 0) }}</td>
                                        <td class="table-number">{{ $format($row['ms'], 2) }} %</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6">Aucune valeur alimentaire disponible.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="page-break-before">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Points clés</h2>
                </div>
            </div>

            <div class="insight-grid">
                <article class="insight-block" style="background: #fff1f2; border: 1px solid #fecdd3;">
                    <h3 style="color:#be123c;">Écarts</h3>
                    <div class="insight-list">
                        @if($priorityInsights !== [])
                            @foreach($priorityInsights as $item)
                                @php
                                    $itemColors = $palette($item['status']);
                                @endphp
                                <div class="insight-item">
                                    <div class="insight-card">
                                        <div class="insight-top">
                                            <div>
                                                <div class="insight-label">{{ $item['label'] }}</div>
                                                <div class="insight-note">{{ $item['note'] }}</div>
                                            </div>
                                            <div class="insight-value {{ $statusTextClass($item['status']) }}">{{ $item['valueLabel'] }}</div>
                                        </div>
                                        <div class="mini-bar">
                                            <table class="bar-table" role="presentation">
                                                <tr>
                                                    <td class="mini-bar-fill {{ $statusBarClass($item['status']) }}" width="{{ $percentAttr($item['percent'] ?? 0) }}"></td>
                                                    <td width="{{ $remainingPercentAttr($item['percent'] ?? 0) }}"></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="card-copy">Aucun écart prioritaire détecté sur les indicateurs disponibles.</p>
                        @endif
                    </div>
                </article>

                <article class="insight-block" style="background: #ecfdf5; border: 1px solid #a7f3d0;">
                    <h3 style="color:#047857;">Équilibres</h3>
                    <div class="insight-list">
                        @if($strengthInsights !== [])
                            @foreach($strengthInsights as $item)
                                @php
                                    $itemColors = $palette($item['status']);
                                @endphp
                                <div class="insight-item">
                                    <div class="insight-card">
                                        <div class="insight-top">
                                            <div>
                                                <div class="insight-label">{{ $item['label'] }}</div>
                                                <div class="insight-note">{{ $item['note'] }}</div>
                                            </div>
                                            <div class="insight-value {{ $statusTextClass($item['status']) }}">{{ $item['valueLabel'] }}</div>
                                        </div>
                                        <div class="mini-bar">
                                            <table class="bar-table" role="presentation">
                                                <tr>
                                                    <td class="mini-bar-fill {{ $statusBarClass($item['status']) }}" width="{{ $percentAttr($item['percent'] ?? 100) }}"></td>
                                                    <td width="{{ $remainingPercentAttr($item['percent'] ?? 100) }}"></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="card-copy">Les données sont trop incomplètes pour isoler des atouts nets.</p>
                        @endif
                    </div>
                </article>
            </div>
            <div class="section-head" style="margin-top: 24px;">
                <div>
                    <h2 class="section-title">Apports vs besoins</h2>
                </div>
            </div>

            <div class="table-wrap">
                <table class="balance-table">
                    <colgroup>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Nutriment</th>
                            <th class="table-number">Apport</th>
                            <th class="table-number">Besoin</th>
                            <th class="table-number">Bilan</th>
                            <th class="table-number">Couverture</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($balanceRows as $row)
                            @php
                                $rowColors = $palette($row['status']);
                                $rowRatio = $safeRatio($row['apport'] ?? null, $row['besoin'] ?? null);
                            @endphp
                            <tr class="{{ $statusRowClass($row['status'] !== 'neutral' ? $row['status'] : 'neutral') }}">
                                <td>
                                    <span class="component-name">{{ $row['label'] }}</span>
                                    @if($row['status'] !== 'neutral')
                                        <span class="{{ $statusChipClass($row['status']) }}">
                                            {{ $statusLabel($row['status']) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="table-number">{{ $format($row['apport'], $row['decimals'] ?? 1) }}</td>
                                <td class="table-number">
                                    {{ $format($row['besoin'], $row['decimals'] ?? 1) }}
                                    @if(($row['besoinSuffix'] ?? null))
                                        <span class="table-note">{{ $row['besoinSuffix'] }}</span>
                                    @endif
                                </td>
                                <td class="table-number {{ $statusTextClass($row['status']) }}">{{ $bilanSign($row['bilan'], $row['decimals'] ?? 1) }}</td>
                                <td class="table-number">
                                    @if($rowRatio !== null)
                                        {{ $format($rowRatio * 100, 0) }} %
                                        <div class="mini-bar">
                                            <table class="bar-table" role="presentation">
                                                <tr>
                                                    <td class="mini-bar-fill {{ $statusBarClass($row['status']) }}" width="{{ $percentAttr($rowRatio * 100) }}"></td>
                                                    <td width="{{ $remainingPercentAttr($rowRatio * 100) }}"></td>
                                                </tr>
                                            </table>
                                        </div>
                                    @else
                                        –
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="section-head" style="margin-top: 24px;">
                <div>
                    <h2 class="section-title">Economie</h2>
                </div>
            </div>

            <div class="table-wrap" style="margin-top: 12px;">
                <table>
                    <thead>
                        <tr>
                            <th>Indicateur</th>
                            <th class="table-number">Valeur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($impactSummaryRows as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="table-number">
                                    {{ $format($row['value'], $row['decimals'] ?? 1) }}
                                    @if($row['unit'])
                                        <span class="table-note">{{ $row['unit'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($is2018)
                <div class="split" style="margin-top: 14px;">
                    <div class="stack">
                        <article class="card">
                            <h3>Protéines</h3>
                            <div class="table-wrap" style="margin-top: 12px;">
                                <table>
                                    <tbody>
                                        @foreach($proteinsRows as $row)
                                            @php
                                                $rowColors = $palette($row['status']);
                                            @endphp
                                            <tr class="{{ $statusRowClass($row['status'] !== 'neutral' ? $row['status'] : 'neutral') }}">
                                                <td>
                                                    {{ $row['label'] }}
                                                    @if($row['status'] !== 'neutral')
                                                        <span class="{{ $statusChipClass($row['status']) }}">
                                                            {{ $statusLabel($row['status']) }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="table-number {{ $statusTextClass($row['status']) }}">
                                                    {{ $format($row['value'], $row['decimals'] ?? 1) }}
                                                    @if($row['unit'])
                                                        <span class="table-note">{{ $row['unit'] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>

                        <article class="card">
                            <h3>Santé ruminale</h3>
                            <div class="table-wrap" style="margin-top: 12px;">
                                <table>
                                    <tbody>
                                        @foreach($healthRows as $row)
                                            @php
                                                $rowColors = $palette($row['status']);
                                            @endphp
                                            <tr class="{{ $statusRowClass($row['status']) }}">
                                                <td>
                                                    {{ $row['label'] }}
                                                    <span class="{{ $statusChipClass($row['status']) }}">
                                                        {{ $statusLabel($row['status']) }}
                                                    </span>
                                                </td>
                                                <td class="table-number {{ $statusTextClass($row['status']) }}">
                                                    {{ $format($row['value'], $row['decimals'] ?? 1) }}
                                                    @if($row['unit'])
                                                        <span class="table-note">{{ $row['unit'] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </div>

                    <div class="stack">
                        <article class="card">
                            <h3>Fibres</h3>
                            <div class="table-wrap" style="margin-top: 12px;">
                                <table>
                                    <tbody>
                                        @foreach($fiberRows as $row)
                                            @php
                                                $rowColors = $palette($row['status']);
                                            @endphp
                                            <tr class="{{ $statusRowClass($row['status'] !== 'neutral' ? $row['status'] : 'neutral') }}">
                                                <td>
                                                    {{ $row['label'] }}
                                                    @if($row['status'] !== 'neutral')
                                                        <span class="{{ $statusChipClass($row['status']) }}">
                                                            {{ $statusLabel($row['status']) }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="table-number {{ $statusTextClass($row['status']) }}">
                                                    {{ $format($row['value'], $row['decimals'] ?? 1) }}
                                                    @if($row['unit'])
                                                        <span class="table-note">{{ $row['unit'] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>

                        <article class="card">
                            <h3>Énergie</h3>
                            <div class="table-wrap" style="margin-top: 12px;">
                                <table>
                                    <tbody>
                                        @foreach($energyRows as $row)
                                            @php
                                                $rowColors = $palette($row['status']);
                                            @endphp
                                            <tr class="{{ $statusRowClass($row['status'] !== 'neutral' ? $row['status'] : 'neutral') }}">
                                                <td>
                                                    {{ $row['label'] }}
                                                    @if($row['status'] !== 'neutral')
                                                        <span class="{{ $statusChipClass($row['status']) }}">
                                                            {{ $statusLabel($row['status']) }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="table-number {{ $statusTextClass($row['status']) }}">
                                                    {{ $format($row['value'], $row['decimals'] ?? 1) }}
                                                    @if($row['unit'])
                                                        <span class="table-note">{{ $row['unit'] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="split" style="margin-top: 14px;">
                    <article class="card">
                        <h3>Minéraux et vitamines</h3>
                        <div class="table-wrap" style="margin-top: 12px;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Élément</th>
                                        <th class="table-number">Apport</th>
                                        <th class="table-number">Besoin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($mineralRows as $row)
                                        @php
                                            $rowColors = $palette($row['status']);
                                        @endphp
                                        <tr class="{{ $statusRowClass($row['status'] !== 'neutral' ? $row['status'] : 'neutral') }}">
                                            <td>
                                                {{ $row['label'] }}
                                                @if($row['status'] !== 'neutral')
                                                    <span class="{{ $statusChipClass($row['status']) }}">
                                                        {{ $statusLabel($row['status']) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="table-number {{ $statusTextClass($row['status']) }}">
                                                {{ $format($row['apport'], $row['decimals'] ?? 1) }}
                                                <span class="table-note">{{ $row['unit'] }}</span>
                                            </td>
                                            <td class="table-number">
                                                {{ $format($row['besoin'], $row['decimals'] ?? 1) }}
                                                <span class="table-note">{{ $row['unit'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($mineralBalanceRows !== [])
                            <div class="table-wrap" style="margin-top: 12px;">
                                <table>
                                    <tbody>
                                        @foreach($mineralBalanceRows as $row)
                                            @php
                                                $rowColors = $palette($row['status']);
                                            @endphp
                                            <tr class="{{ $statusRowClass($row['status'] !== 'neutral' ? $row['status'] : 'neutral') }}">
                                                <td>{{ $row['label'] }}</td>
                                                <td class="table-number {{ $statusTextClass($row['status']) }}">
                                                    {{ $format($row['value'], $row['decimals'] ?? 1) }}
                                                    @if($row['unit'])
                                                        <span class="table-note">{{ $row['unit'] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </article>

                    <article class="card">
                        <h3>Acides gras volatils</h3>
                        <div class="table-wrap" style="margin-top: 12px;">
                            <table>
                                <tbody>
                                    @foreach($fattyAcidRows as $row)
                                        @php
                                            $rowColors = $palette($row['status']);
                                        @endphp
                                        <tr class="{{ $statusRowClass($row['status'] !== 'neutral' ? $row['status'] : 'neutral') }}">
                                            <td>{{ $row['label'] }}</td>
                                            <td class="table-number {{ $statusTextClass($row['status']) }}">
                                                {{ $format($row['value'], $row['decimals'] ?? 1) }}
                                                @if($row['unit'])
                                                    <span class="table-note">{{ $row['unit'] }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            @else
                <div class="split" style="margin-top: 14px;">
                    <article class="card">
                        <h3>Lecture 2007</h3>
                        <div class="table-wrap" style="margin-top: 12px;">
                            <table>
                                <tbody>
                                    <tr>
                                        <td>Lait permis par UFL</td>
                                        <td class="table-number">{{ $format($impacts['lait_par_ufl'] ?? null, 2) }} <span class="table-note">kg/j</span></td>
                                    </tr>
                                    <tr>
                                        <td>Lait permis par PDIE</td>
                                        <td class="table-number">{{ $format($impacts['lait_par_pdie'] ?? null, 2) }} <span class="table-note">kg/j</span></td>
                                    </tr>
                                    <tr>
                                        <td>Lait permis par PDIN</td>
                                        <td class="table-number">{{ $format($impacts['lait_par_pdin'] ?? null, 2) }} <span class="table-note">kg/j</span></td>
                                    </tr>
                                    <tr>
                                        <td>Rmic</td>
                                        <td class="table-number">{{ $format($impacts['rmic'] ?? null, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Eau bue estimée</td>
                                        <td class="table-number">{{ $format($impacts['eau_bue'] ?? null, 1) }} <span class="table-note">L/j</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            @endif

            <p class="footer-note">Rapport généré le {{ $generatedAt }} · {{ $plan->nom }} · {{ $ration->nom }}</p>
        </section>
    </div>
</body>
</html>
