<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use RuntimeException;

final class RationComparisonReport
{
    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, float>  $actual
     * @return list<array<string, mixed>>
     */
    public static function compare(array $report, int $scenario, array $actual): array
    {
        $rows = [];
        foreach ($report['results'] as $row) {
            if ($row['column'] !== 'value') {
                continue;
            }
            $key = ReferenceRations::completedMetricKey($report, $row);
            $printed = $row['values'][$scenario];
            $comparison = [
                'label' => $row['label'], 'column' => $row['column'], 'reference' => $printed,
                'actual' => null, 'delta' => null, 'percent' => null, 'tolerance' => null,
                'status' => 'Non comparé', 'reason' => null,
            ];
            if ($key === null) {
                $comparison['reason'] = match (true) {
                    in_array($row['label'], ['Mois par rapport au vêlage', 'Lait estimé (kg/j)', 'Production de lait (kg/j)'], true) => 'Paramètre fourni au calcul.',
                    $row['column'] !== 'value' => 'Contrainte ou production correspondante sans équivalence validée avec le moteur.',
                    $report['mineral_percentages'] !== null && in_array($row['label'], ['Ca abs. Ruminants (g)', 'P abs. Ruminants (g)', 'Mg absorbable (g)', 'Prix (€)'], true) => 'Composition complète de l’AMV automatique non disponible.',
                    default => 'Sortie non exposée par le moteur ou données insuffisantes pour une comparaison validée.',
                };
            } elseif (! array_key_exists($key, $actual) || ! is_finite($actual[$key])) {
                $comparison['status'] = 'Erreur';
                $comparison['reason'] = 'Résultat moteur absent ou non fini : '.$key;
            } else {
                $expected = (float) $printed;
                $delta = $actual[$key] - $expected;
                $tolerance = ReferenceRations::tolerance($row['label'], $printed);
                $comparison = array_replace($comparison, [
                    'actual' => $actual[$key], 'delta' => $delta,
                    'percent' => $expected !== 0.0 ? $delta / abs($expected) * 100 : null,
                    'tolerance' => $tolerance,
                    'status' => ReferenceRations::exceedsTolerance($actual[$key], $expected, $tolerance) ? 'Écart' : 'OK',
                ]);
            }
            $rows[] = $comparison;
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows */
    public static function record(string $directory, string $reportId, int $scenario, bool $asMix, array $rows): void
    {
        self::save($directory.'/'.$reportId.'-'.$scenario.'-'.($asMix ? 'melange' : 'direct').'.json',
            json_encode(['report_id' => $reportId, 'scenario' => $scenario, 'as_mix' => $asMix, 'rows' => $rows],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }

    public static function generate(string $directory, int $testExitCode): bool
    {
        $reports = ReferenceRations::reports();
        $cases = [];
        $summary = [];
        $complete = true;
        foreach (ReferenceRations::scenarios() as [$reportId, $scenario]) {
            $variants = [];
            foreach (['direct' => 'Aliments directs', 'melange' => 'Mélange'] as $mode => $label) {
                $path = $directory.'/cas/'.$reportId.'-'.$scenario.'-'.$mode.'.json';
                $variants[$label] = is_file($path) ? json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)['rows'] : null;
                if ($variants[$label] === null || array_filter($variants[$label], fn (array $row): bool => $row['status'] === 'Erreur')) {
                    $complete = false;
                }
            }
            $title = $reportId.' / '.$reports[$reportId]['scenarios'][$scenario];
            $summary[] = [$title, self::counts($variants['Aliments directs']), self::counts($variants['Mélange'])];
            $cases[] = ['title' => $title, 'report' => $reports[$reportId], 'variants' => $variants];
        }

        $date = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('d/m/Y à H:i:s T');
        $markdown = "# Comparaison Larelev 2018 / Nexa Ration\n\nGénéré le $date.\n\n"
            .count($cases)." rations, chacune testée en aliments directs et en mélange. Code de sortie Pest : $testExitCode.\n\n"
            .($complete ? 'Toutes les comparaisons ont été recueillies.' : '**Rapport incomplet : des calculs sont absents ou en erreur. Voir tests.log.**')."\n\n"
            ."Écart = Nexa − Larelev ; écart % = écart / |Larelev| × 100. Le pourcentage n’est pas défini quand Larelev vaut zéro. "
            ."« OK » signifie dans la tolérance des tests ; « Écart » signifie au-delà. Les unités figurent dans le nom des indicateurs.\n\n"
            ."Tolérance : ±3 % de la valeur absolue Larelev ; exception BPR : ±3 points. Une référence nulle implique une tolérance nulle (sauf BPR). "
            ."Seules les lignes Apport / valeur sont conservées ; les sous-lignes de contraintes et de production correspondante sont exclues.\n\n"
            ."Les analyses imprimées sont conservées ; les valeurs manquantes sont complétées par les analogues INRA documentés dans les références de test. "
            ."Ces approximations peuvent contribuer aux différences. « Non comparé » ne signifie pas validé.\n\n"
            ."## Synthèse\n\nLes compteurs sont : OK / écarts / non comparés / erreurs.\n\n"
            .self::table(['Ration / scénario', 'Aliments directs', 'Mélange'], $summary);

        foreach ($cases as $index => $case) {
            $markdown .= "\n## ".($index + 1).'. '.$case['title']."\n\nSource : ".self::cell($case['report']['source'])."\n\n";
            foreach ($case['variants'] as $mode => $rows) {
                $markdown .= '### '.$mode."\n\n";
                if ($rows === null) {
                    $markdown .= "**Calcul absent : consulter tests.log.**\n\n";

                    continue;
                }
                $comparable = array_values(array_filter($rows, fn (array $row): bool => $row['status'] !== 'Non comparé'));
                usort($comparable, fn (array $a, array $b): int => ($a['status'] === 'OK') <=> ($b['status'] === 'OK'));
                $markdown .= self::table(['Indicateur', 'Colonne Larelev', 'Larelev', 'Nexa', 'Écart', 'Écart %', 'Tolérance ±', 'Résultat'],
                    array_map(fn (array $row): array => [
                        $row['label'], $row['column'] === 'value' ? 'Apport / valeur' : $row['column'], $row['reference'],
                        self::number($row['actual']), self::number($row['delta'], true), self::number($row['percent'], true),
                        self::number($row['tolerance']), $row['reason'] ?? $row['status'],
                    ], $comparable));
            }
            $notCompared = array_filter($case['variants']['Aliments directs'] ?? $case['variants']['Mélange'] ?? [], fn (array $row): bool => $row['status'] === 'Non comparé');
            $markdown .= "### Non comparés\n\n".self::table(['Indicateur', 'Colonne Larelev', 'Larelev', 'Raison'],
                array_map(fn (array $row): array => [$row['label'], $row['column'], $row['reference'], $row['reason']], $notCompared));
        }
        $body = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        self::save($directory.'/rapport.md', $markdown);
        self::save($directory.'/rapport.html', self::html($body));

        return $complete;
    }

    /** @param list<array<string, mixed>>|null $rows */
    private static function counts(?array $rows): string
    {
        if ($rows === null) {
            return 'Calcul absent';
        }
        $counts = array_count_values(array_column($rows, 'status'));

        return implode(' / ', array_map(fn (string $key): int => $counts[$key] ?? 0, ['OK', 'Écart', 'Non comparé', 'Erreur']));
    }

    private static function number(?float $value, bool $signed = false): string
    {
        if ($value === null) {
            return '—';
        }
        $value = round($value, 5);

        return ($signed && $value > 0 ? '+' : '').rtrim(rtrim(number_format($value, 5, ',', ' '), '0'), ',');
    }

    private static function cell(string $value): string
    {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    /** @param list<string> $headers
     * @param array<array<string>> $rows
     */
    private static function table(array $headers, array $rows): string
    {
        $lines = [$headers, array_fill(0, count($headers), '---'), ...array_values($rows)];

        return implode("\n", array_map(fn (array $cells): string => '| '.implode(' | ', array_map(self::cell(...), $cells)).' |', $lines))."\n\n";
    }

    private static function save(string $path, string $content): void
    {
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException('Impossible d’écrire '.$path);
        }
    }

    private static function html(string $body): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="fr">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Comparaison Larelev / Nexa Ration</title>
<style>
body{font:15px/1.5 system-ui,sans-serif;color:#18252e;background:#f5f7fa;margin:0;padding:28px}
main{max-width:1500px;margin:auto}h1,h2,h3{line-height:1.2}h2{margin-top:40px;border-top:1px solid #ccd5dc;padding-top:24px}
table{border-collapse:collapse;width:100%;background:white;margin:16px 0 28px;font-size:13px;display:block;overflow-x:auto}
th,td{padding:9px 12px;border:1px solid #dbe1e7;text-align:right;white-space:nowrap}th{background:#e9eef3}td:first-child,th:first-child{text-align:left;white-space:normal;min-width:220px}
tr.gap{background:#fff0e8}tr.good{color:#3b6050}label{display:block;padding:12px;background:#e9eef3;position:sticky;top:0;z-index:1}p{max-width:1100px}details{margin:16px 0}summary{cursor:pointer;font-weight:600}
@media print{body{padding:0;background:white}label{display:none}table{font-size:10px}h2{break-before:page}}
</style>
<main>
<label><input id="show-ok" type="checkbox"> Afficher aussi les valeurs dans la tolérance (OK)</label>
HTML
            .$body.<<<'HTML'
</main>
<script>
const checkbox = document.getElementById('show-ok');
document.querySelectorAll('h3').forEach(heading => {
  const title = heading.textContent.trim();
  const table = heading.nextElementSibling;
  if (!['Mélange', 'Non comparés'].includes(title) || table?.tagName !== 'TABLE') return;
  const details = document.createElement('details');
  const summary = document.createElement('summary');
  summary.textContent = title;
  heading.replaceWith(details);
  details.append(summary, table);
});
document.querySelectorAll('tbody tr').forEach(row => {
  const status = row.lastElementChild.textContent.trim();
  if (status === 'OK') row.classList.add('good');
  if (status === 'Écart') row.classList.add('gap');
});
function filterRows() {
  document.querySelectorAll('tr.good').forEach(row => { row.hidden = !checkbox.checked; });
}
checkbox.addEventListener('change', filterRows);
filterRows();
</script>
</html>
HTML;
    }
}
