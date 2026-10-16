<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2018\Apport;
use App\Services\RationCalculator;
use RuntimeException;

/**
 * Oracle indépendant : nombres imprimés par le logiciel de référence, jamais des snapshots du moteur.
 * Le mode brut préserve les absences. Le mode complété utilise les analogues INRA documentés.
 */
final class ReferenceRations
{
    private const FIELDS = [
        'Prix' => 'prix', 'MS' => 'ms', 'UEB' => 'ueb', 'UEL' => 'uel', 'bVEC' => 'b_vec',
        'MO' => 'mo', 'dMO Ruminants°' => 'd_mo', 'EB' => 'eb', 'EM Ruminants°' => 'em',
        'UFL°' => 'ufl', 'PB [MAT]' => 'mat', 'DT6 N°' => 'dt6_n', 'PDIA°' => 'pdia',
        'PDI°' => 'pdi', 'BPR°' => 'bpr', 'LysDI°' => 'lys_di', 'MetDI°' => 'met_di',
        'Amidon' => 'amidon', 'DT Amidon°' => 'dt6_ami', 'Sucres' => 'sucres',
        'CB' => 'cb', 'NDF' => 'ndf', 'ADF' => 'adf', 'Ca' => 'ca',
        'Ca abs. Ruminants' => 'caabs', 'P' => 'p', 'P abs. Ruminants' => 'pabs', 'Mg' => 'mg', 'K' => 'k',
    ];

    public const COMPOSITION = [
        'MS totale (kg)', 'Matière brute (kg)', 'Matière sèche (%)', 'Concentrés (% MS)',
        'Matières azotées totales (% MS)', 'Cellulose Brute (% MS)', 'NDF (% MS)',
        'NDF provenant des fourrages (% MS totale)', 'ADF (% MS)', 'Amidon (% MS)',
        'Ca abs. Ruminants (g)', 'Ca (g)', 'P abs. Ruminants (g)', 'P (g)',
        'Mg absorbable (g)', 'Mg (g)', 'Prix (€)',
    ];

    private const DYNAMIC = [
        'UEB', 'UEL', 'UFL', 'UFL réelles', 'UFL (/ kg MS)',
        'PDI pour croissance permise par les UFL', 'PDI (g)', 'PDI (g/kg MS)', 'PDI / UFL (g)',
        'BPR (g/kg MS)', 'LysDI (%PDI)', 'MétDI (% PDI)', 'Amidon dégradable (% MS)',
        'Production corresp. (sans adapt.)', 'Production corresp. (avec adapt.)',
        'Variation de lait due à UFL et PDI (kg)', 'Variation de TP due à UFL et PDI (g/kg)',
        'Variation deTP / 6,7% LysDI et 1,9% MétDI (g/kg)', 'Variation de TP totale (g/kg)',
        'Variation de TB due à UFL (g/kg)', 'Variation de NEC (points par mois)', 'Urée théorique du lait (g/L)',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function reports(): array
    {
        $reports = [];
        foreach (glob(__DIR__.'/../Fixtures/reference-rations/*.json') as $path) {
            $report = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $reports[$report['id']] = $report;
        }

        return $reports;
    }

    /** @return array<string, array{string, int}> */
    public static function scenarios(): array
    {
        $scenarios = [];
        foreach (self::reports() as $id => $report) {
            foreach ($report['scenarios'] as $index => $name) {
                $scenarios[$id.' / '.$name] = [$id, $index];
            }
        }

        return $scenarios;
    }

    /**
     * Les g/kg brut, kcal/kg brut, UF/kg brut et UE/kg brut passent en /kg MS.
     * Les pourcentages de digestibilité et %PDI restent inchangés ; MS passe de g/kg à %.
     *
     * @param array<string, mixed> $feed
     * @return array<string, float|null>
     */
    public static function attributes(array $feed): array
    {
        $fraction = (float) $feed['values']['MS']['value'] / 1000;
        if ($fraction <= 0 || $fraction > 1) {
            throw new RuntimeException('MS invalide : '.$feed['name']);
        }
        $attributes = [];
        foreach ($feed['values'] as $label => $cell) {
            $field = self::FIELDS[$label] ?? throw new RuntimeException('Champ alimentaire inconnu : '.$label);
            $value = $cell['value'] === null ? null : (float) $cell['value'];
            if ($field === 'ms') {
                $value = $fraction * 100;
            } elseif ($value !== null && $field !== 'prix' && ! str_contains($cell['unit'], '%') && $feed['basis'] === 'as_fed') {
                $value /= $fraction;
            }
            $attributes[$field] = $value;
        }

        return $attributes;
    }

    /** @param array<string, mixed> $report */
    public static function ration(array $report, int $scenario, bool $asMix = false, bool $completeWithInra = false): Ration
    {
        $ration = new Ration(array_merge($report['animal'], $report['scenario_animals'][$scenario] ?? []));
        $ration->setRelation('planRationnement', new PlanRationnement(['inra' => $report['system']]));
        $items = collect();
        $recipe = collect();
        $quantityMb = 0.0;

        foreach ($report['quantities'] as $quantity) {
            $amount = (float) $quantity['values'][$scenario];
            if ($amount === 0.0) {
                continue;
            }

            if ($quantity['name'] === 'AMV') {
                [$p, $ca, $mg] = $report['mineral_percentages'][$scenario];
                /**
                 * En mode brut, MS=100 normalise q MB × g/kg MB pour les seuls totaux Ca/P/Mg.
                 * En mode complété, la MS et les composants connus viennent de l'analogue PostgreSQL.
                 * Les absorbabilités et K inconnus ne deviennent jamais des analyses supposées.
                 */
                $mineralAttributes = $completeWithInra
                    ? InraFeedComplements::automaticMineral([$p, $ca, $mg])
                    : ['ms' => 100, 'p' => $p * 10, 'ca' => $ca * 10, 'mg' => $mg * 10];
                $aliment = new Aliment(array_merge($mineralAttributes, ['libelle0' => 'AMV', 'type' => 'mineral']));
            } else {
                $feed = self::findFeed($report, $quantity['name']);
                $type = str_contains($feed['category'], 'MINÉRAUX') ? 'mineral'
                    : (preg_match('/FOINS|ENSILAGES|PAILLES/u', $feed['category']) ? 'fourrage' : 'concentre');
                $feedAttributes = $completeWithInra ? InraFeedComplements::attributes($feed) : self::attributes($feed);
                $aliment = new Aliment(array_merge($feedAttributes, [
                    'libelle0' => $feed['name'], 'type' => $type,
                    ...Aliment::classificationFromReference($type, $feed['category'], $feed['name']),
                ]));
            }

            $attributes = ['quantite' => $amount, 'is_mb' => $quantity['basis'] === 'as_fed',
                'is_volonte' => false, 'ordre' => $items->count() + 1];
            $items->push((new RationAliment($attributes))->setRelation('aliment', $aliment));
            $recipe->push((new MelangeAliment($attributes))->setRelation('aliment', $aliment));
            $quantityMb += $attributes['is_mb'] ? $amount : $amount * 100 / $aliment->ms;
        }

        $mix = (new Melange(['nom' => 'Même ration en mélange', 'quantite' => $quantityMb,
            'is_mb' => true, 'is_volonte' => false]))->setRelation('melangeAliments', $recipe);
        $ration->setRelation('rationAliments', $asMix ? collect() : $items);
        $ration->setRelation('melanges', $asMix ? collect([$mix]) : collect());

        return $ration;
    }

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private static function findFeed(array $report, string $name): array
    {
        $matches = array_values(array_filter($report['feeds'], fn (array $feed): bool => str_starts_with($feed['name'], $name)));
        if (count($matches) !== 1) {
            throw new RuntimeException('Fiche alimentaire absente ou ambiguë : '.$name);
        }

        return $matches[0];
    }

    /** @param array<string, mixed> $report
     * @param array<string, mixed> $row
     */
    public static function blockedReason(array $report, array $row): ?string
    {
        $label = $row['label'];
        if (! in_array($label, [...self::COMPOSITION, ...self::DYNAMIC,
            'Mois par rapport au vêlage', 'Lait estimé (kg/j)', 'Production de lait (kg/j)',
            '(Amidon + Sucres (% MS))', 'Coût alimentaire du lait (€/1000 kg)',
            'Marge sur coût alimentaire (€/1000 kg)', 'Marge sur coût alimentaire (€/vache)',
        ], true)) {
            throw new RuntimeException('Résultat non répertorié : '.$label);
        }
        if ($row['column'] !== 'value') {
            return 'Contraintes et productions correspondantes : réglages/tables du logiciel de référence non fournis.';
        }
        if (in_array($label, ['Mois par rapport au vêlage', 'Lait estimé (kg/j)', 'Production de lait (kg/j)'], true)) {
            return 'Paramètre du scénario imprimé, pas une sortie calculée par notre moteur.';
        }
        if (in_array($label, self::DYNAMIC, true)) {
            return 'Recalcul dynamique non reproductible : précurseurs alimentaires incomplets (notamment dr_N, EB, AG, NIref) et/ou paramètres animaux absents.';
        }
        if ($label === '(Amidon + Sucres (% MS))') {
            return 'Teneur en sucres du foin indiquée « inconnu ».';
        }
        if ($report['mineral_percentages'] !== null && ! in_array($label, ['Ca (g)', 'P (g)', 'Mg (g)'], true)) {
            return 'Fiche AMV automatique absente : seules les teneurs Ca/P/Mg brutes sont imprimées.';
        }
        if ($label === 'ADF (% MS)' && $report['id'] === 'genisse-allaitante') {
            return 'ADF non imprimé dans ce rapport.';
        }

        return null;
    }

    /** @return array<string, float> */
    public static function calculate(Ration $ration): array
    {
        $result = RationCalculator::calculer($ration);

        return [
            'MS totale (kg)' => $result['apports']['ms'],
            'Matière brute (kg)' => Apport::calculerApportTotalMS($ration) / Apport::calculerApportMSParMB($ration),
            'Matière sèche (%)' => Apport::calculerApportMSParMB($ration) * 100,
            'Concentrés (% MS)' => $result['indicateurs']['pco_percent'],
            'Matières azotées totales (% MS)' => Apport::calculerApportMAT($ration) / 10,
            'Cellulose Brute (% MS)' => $result['indicateurs']['cb_par_kg_ms'] / 10,
            'NDF (% MS)' => $result['indicateurs']['ndf_total'] / 10,
            'NDF provenant des fourrages (% MS totale)' => $result['indicateurs']['ndf_fourrages'] / 10,
            'ADF (% MS)' => $result['indicateurs']['adf_par_kg_ms'] / 10,
            'Amidon (% MS)' => Apport::calculerApportAmidonParKgMS($ration) / 10,
            'Ca abs. Ruminants (g)' => $result['apports']['caabs'], 'Ca (g)' => $result['apports']['ca'],
            'P abs. Ruminants (g)' => $result['apports']['pabs'], 'P (g)' => $result['apports']['p'],
            'Mg absorbable (g)' => $result['apports']['mgabs'], 'Mg (g)' => $result['apports']['mg'],
            'Prix (€)' => $result['impacts']['cout_animal'],
            'UEB' => $result['apports']['ue'], 'UEL' => $result['apports']['ue'],
            'UFL' => $result['apports']['uf'], 'UFL réelles' => $result['apports']['uf'],
            'UFL (/ kg MS)' => Apport::calculerApportUFLParKgMS($ration),
            'PDI (g)' => $result['apports']['pdi'],
            'PDI pour croissance permise par les UFL' => $result['apports']['pdi'],
            'PDI (g/kg MS)' => $result['indicateurs']['pdi_par_kg_ms'],
            'PDI / UFL (g)' => $result['apports']['uf'] > 0 ? $result['apports']['pdi'] / $result['apports']['uf'] : 0.0,
            'BPR (g/kg MS)' => $result['indicateurs']['bpr'],
            'LysDI (%PDI)' => Apport::calculerApportLysDI($ration),
            'MétDI (% PDI)' => Apport::calculerApportMetDI($ration),
            'Amidon dégradable (% MS)' => $result['indicateurs']['amid_ru'] / 10,
            'besoins.uf' => $result['besoins']['uf_total'], 'besoins.pdi' => $result['besoins']['pdi_total'],
            'besoins.ue' => $result['besoins']['ci'], 'besoins.caabs' => $result['besoins']['caabs'],
            'besoins.pabs' => $result['besoins']['pabs'], 'besoins.mgabs' => $result['besoins']['mgabs'],
            'Production corresp. (sans adapt.)' => $result['impacts']['lait_par_ufl'] ?? 0.0,
            'lait_par_pdi' => $result['impacts']['lait_par_pdi'] ?? 0.0,
        ];
    }

    /**
     * Résultats comparables après complétion. Les champs réellement absents du catalogue et
     * les effets non exposés par le moteur restent explicites dans la liste des incomplets.
     *
     * @param array<string, mixed> $report
     * @param array<string, mixed> $row
     */
    public static function completedMetricKey(array $report, array $row): ?string
    {
        $label = $row['label'];
        if ($row['column'] === 'value') {
            if (in_array($label, [...self::COMPOSITION, 'UEB', 'UEL', 'UFL', 'UFL réelles',
                'UFL (/ kg MS)', 'PDI (g)', 'PDI pour croissance permise par les UFL',
                'PDI (g/kg MS)', 'PDI / UFL (g)', 'BPR (g/kg MS)', 'LysDI (%PDI)',
                'MétDI (% PDI)', 'Amidon dégradable (% MS)', 'Production corresp. (sans adapt.)',
            ], true)) {
                if ($report['mineral_percentages'] !== null && in_array($label, [
                    'Ca abs. Ruminants (g)', 'P abs. Ruminants (g)', 'Mg absorbable (g)', 'Prix (€)',
                ], true)) {
                    return null;
                }

                return $label;
            }
        }
        return null;
    }

    /** Tolérances convenues : 3 % de |Larelev| ; BPR : 3 points absolus. */
    public static function tolerance(string $label, string $printed): float
    {
        return $label === 'BPR (g/kg MS)' ? 3.0 : abs((float) $printed) * 0.03;
    }

    /** Demi-unité du dernier chiffre significatif imprimé (export à trois chiffres significatifs). */
    public static function roundingRadius(string $printed): float
    {
        $decimals = str_contains($printed, '.') ? strlen(explode('.', $printed)[1]) : 0;
        $value = abs((float) $printed);
        $step = 10 ** -$decimals;
        if ($value > 0) {
            $step = max($step, 10 ** (floor(log10($value)) - 2));
        }

        return $step / 2;
    }

    /** Neutralise uniquement la précision des flottants à la limite exacte de la tolérance. */
    public static function exceedsTolerance(float $actual, float $expected, float $tolerance): bool
    {
        if ($tolerance === 0.0) {
            return $actual !== $expected;
        }
        $precision = 4 * PHP_FLOAT_EPSILON * max(1.0, abs($actual), abs($expected));

        return abs($actual - $expected) > $tolerance + $precision;
    }
}
