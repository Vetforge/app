<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Edit, FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    edit as analysisEdit,
    index as analysesIndex,
    pdf as analysisPdf,
} from '@/actions/App/Http/Controllers/VeterinaryAnalysisController';
import BseMetricBar from '@/components/BseMetricBar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface ModuleInfo {
    slug: string;
    label: string;
    short_label: string;
}

interface Analysis {
    id: number;
    breeder: {
        name: string;
        address: string | null;
        postal_code: string | null;
        city: string | null;
        herd_number: string | null;
    };
    animal_nom: string | null;
    sampled_at: string | null;
    analyzed_at: string | null;
    intervenant: string | null;
    payload: Record<string, unknown>;
    results: Record<string, unknown> | null;
    settings_snapshot: Record<string, unknown> | null;
}

interface ComparisonAnalysis {
    id: number;
    breeder: Analysis['breeder'];
    analyzed_at: string | null;
    payload: Record<string, unknown>;
    results: Record<string, unknown> | null;
}

const props = defineProps<{
    analysis: Analysis;
    comparisonAnalysis?: ComparisonAnalysis | null;
    module: ModuleInfo;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: props.module.short_label, href: analysesIndex({ module: props.module.slug }).url },
    { title: `Analyse #${props.analysis.id}`, href: '#' },
];

const results = computed<Record<string, any>>(() => (props.analysis.results ?? {}) as Record<string, any>);
const payload = computed<Record<string, any>>(() => props.analysis.payload as Record<string, any>);
const comparisonResults = computed<Record<string, any>>(() => (props.comparisonAnalysis?.results ?? {}) as Record<string, any>);
const comparisonPayload = computed<Record<string, any>>(() => (props.comparisonAnalysis?.payload ?? {}) as Record<string, any>);
const commentaires = computed<Record<string, { s: string; ns: string }>>(() => (results.value.commentaires ?? {}) as Record<string, { s: string; ns: string }>);

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR');
}

function fmt(value: unknown, decimals = 1, suffix = ''): string {
    if (value === null || value === undefined || value === '') return '-';
    const n = Number(value);
    if (isNaN(n)) return '-';
    return `${n.toFixed(decimals)}${suffix ? ' ' + suffix : ''}`;
}

function fmtInt(value: unknown, suffix = ''): string {
    if (value === null || value === undefined || value === '') return '-';
    const n = Number(value);
    if (isNaN(n)) return '-';
    return `${Math.round(n)}${suffix ? ' ' + suffix : ''}`;
}

function num(value: unknown): number | null {
    if (value === null || value === undefined || value === '') return null;
    const n = Number(value);
    return isNaN(n) ? null : n;
}

function compResult(key: string): number | null {
    return num(comparisonResults.value[key]);
}

function compPayload(key: string): number | null {
    return num(comparisonPayload.value[key]);
}

const comparisonLabel = computed(() => {
    if (!props.comparisonAnalysis) return '';
    return `Ancien bilan du ${formatDate(props.comparisonAnalysis.analyzed_at)}`;
});

interface ComparisonRow {
    label: string;
    current: string;
    previous: string;
}

const comparisonRows = computed((): ComparisonRow[] => {
    if (!props.comparisonAnalysis) return [];

    return [
        {
            label: 'Mortalité néonatale',
            current: fmt(results.value.tx_mortalite_neonatale, 1, '%'),
            previous: fmt(comparisonResults.value.tx_mortalite_neonatale, 1, '%'),
        },
        {
            label: 'Mammites',
            current: fmt(results.value.tx_mammites, 1, '%'),
            previous: fmt(comparisonResults.value.tx_mammites, 1, '%'),
        },
        {
            label: 'CCI > 250 000',
            current: fmt(results.value.tx_cci250, 1, '%'),
            previous: fmt(comparisonResults.value.tx_cci250, 1, '%'),
        },
        {
            label: 'Boiteries',
            current: fmt(results.value.tx_boiteries, 1, '%'),
            previous: fmt(comparisonResults.value.tx_boiteries, 1, '%'),
        },
        {
            label: 'IVV',
            current: fmtInt(payload.value.ivv, 'j'),
            previous: fmtInt(comparisonPayload.value.ivv, 'j'),
        },
        {
            label: 'Coût mammites',
            current: fmtInt(results.value.cout_mammites, '€'),
            previous: fmtInt(comparisonResults.value.cout_mammites, '€'),
        },
    ];
});

function plainText(value: string): string {
    return value
        .replace(/<\s*(br|\/p|\/div|\/li)\s*\/?>/gi, ' ')
        .replace(/<\s*(p|div|li)\b[^>]*>/gi, ' ')
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&quot;/gi, '"')
        .replace(/&#039;|&apos;/gi, "'")
        .replace(/\s+/g, ' ')
        .trim();
}

function commentForKey(key: string): string {
    const block = commentaires.value[key];
    if (!block) return '';
    return plainText(block.s || block.ns || '');
}

// Mastitis rate zone
const mammitesBadge = computed(() => {
    const v = num(results.value.tx_mammites);
    if (v === null) return 'bg-gray-100 text-gray-700';
    if (v <= 15) return 'bg-green-100 text-green-800';
    if (v <= 30) return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
});

const cci250Badge = computed(() => {
    const v = num(results.value.tx_cci250);
    if (v === null) return 'bg-gray-100 text-gray-700';
    if (v <= 3) return 'bg-green-100 text-green-800';
    if (v <= 10) return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
});

// Disease breakdown
interface CauseBar {
    label: string;
    value: number | null;
    color: string;
}

const causesMaladies = computed((): CauseBar[] => {
    const total = num(payload.value.nb_vaches_productrices) || 1;
    return [
        { label: 'Mammites locales', value: num(payload.value.nb_mammites_locales), color: 'bg-red-400' },
        { label: 'Mammites aiguës', value: num(payload.value.nb_mammites_aigues), color: 'bg-red-600' },
        { label: 'Boiteries', value: num(payload.value.nb_boiteries), color: 'bg-orange-400' },
        { label: 'Fièvres de lait', value: num(payload.value.nb_fievres_de_lait), color: 'bg-yellow-400' },
        { label: 'Caillettes', value: num(payload.value.nb_caillettes), color: 'bg-purple-400' },
        { label: 'Cétoses', value: num(payload.value.nb_cetoses), color: 'bg-blue-400' },
        { label: 'Acidoses', value: num(payload.value.nb_acidoses), color: 'bg-cyan-400' },
    ]
        .filter((c) => c.value !== null && c.value > 0)
        .map((c) => ({ ...c, pct: ((c.value! / total) * 100) }));
});

// Non-cure rates
interface NonGuerison {
    label: string;
    pct: number | null;
}
const nonGuerison = computed((): NonGuerison[] => [
    { label: 'Mammites locales', pct: num(results.value.tx_non_guerison_mammites_locales) },
    { label: 'Mammites aiguës', pct: num(results.value.tx_non_guerison_mammites_aigues) },
    { label: 'Boiteries', pct: num(results.value.tx_non_guerison_boiteries) },
    { label: 'Fièvres de lait', pct: num(results.value.tx_non_guerison_fievres_de_lait) },
    { label: 'Caillettes', pct: num(results.value.tx_non_guerison_caillettes) },
]);

// Gain quality
const gainPositive = (v: number | null) => v !== null && v > 0;

// Cost cards
interface CostCard {
    label: string;
    value: number | null;
    rateLabel: string;
    rateValue: string;
    comparisonValue?: number | null;
    comparisonRateValue?: string;
    commentKey: string;
    description: string;
    isGain?: boolean;
}

const costCards = computed((): CostCard[] => [
    {
        label: 'Mortalité néonatale',
        value: num(results.value.cout_mortalite_neonatale),
        rateLabel: 'Mortalité',
        rateValue: fmt(results.value.tx_mortalite_neonatale, 1, '%'),
        comparisonValue: compResult('cout_mortalite_neonatale'),
        comparisonRateValue: fmt(comparisonResults.value.tx_mortalite_neonatale, 1, '%'),
        commentKey: 'tx_mortalite_neonatale',
        description:
            "Le plan diarrhées néonatales étudie les facteurs de risque et met en place les solutions adaptées : alimentation des mères, gestion sanitaire, vaccination, qualité des colostrums. Réduction du coût de traitement, du temps de soins, des pertes de croissance et de la mortalité.",
    },
    {
        label: 'Mammites',
        value: num(results.value.cout_mammites),
        rateLabel: 'Tx mammites',
        rateValue: fmt(results.value.tx_mammites, 1, '%'),
        comparisonValue: compResult('cout_mammites'),
        comparisonRateValue: fmt(comparisonResults.value.tx_mammites, 1, '%'),
        commentKey: 'tx_mammites',
        description:
            "Seule une approche globale par votre vétérinaire peut maîtriser les contaminations : diagnostic épidémiologique, diagnostic étiologique, visite de traite pour identifier les points critiques et établir un plan d'action ciblé.",
    },
    {
        label: 'Boiteries',
        value: num(results.value.cout_boiteries),
        rateLabel: 'Tx boiteries',
        rateValue: fmt(results.value.tx_boiteries, 1, '%'),
        comparisonValue: compResult('cout_boiteries'),
        comparisonRateValue: fmt(comparisonResults.value.tx_boiteries, 1, '%'),
        commentKey: 'tx_boiteries',
        description:
            "Seul un diagnostic précis associé à la visite conjointe d'un pareur et de votre vétérinaire peut permettre d'objectiver les causes des boiteries et établir un plan de lutte personnalisé.",
    },
    {
        label: 'Troubles métaboliques',
        value: num(results.value.cout_metaboliques),
        rateLabel: 'Tx métaboliques',
        rateValue: fmt(results.value.tx_metaboliques, 1, '%'),
        comparisonValue: compResult('cout_metaboliques'),
        comparisonRateValue: fmt(comparisonResults.value.tx_metaboliques, 1, '%'),
        commentKey: 'tx_metaboliques',
        description:
            "Solutions préventives grâce à un diagnostic nutritionnel précis, un plan de prévention efficace puis un suivi régulier adapté à votre situation.",
    },
    {
        label: 'Reproduction (IVV)',
        value: num(results.value.cout_reproduction),
        rateLabel: 'IVV',
        rateValue: fmtInt(payload.value.ivv, 'j'),
        comparisonValue: compResult('cout_reproduction'),
        comparisonRateValue: fmtInt(comparisonPayload.value.ivv, 'j'),
        commentKey: 'cout_reproduction',
        description:
            "Examens échographiques et gestion régulière de la reproduction. L'alimentation des vaches est la principale cause d'infertilité : un diagnostic nutritionnel précis puis un suivi régulier.",
    },
    {
        label: 'Coût alimentaire / tonne lait',
        value: num(results.value.cout_alimentaire),
        rateLabel: '€/t lait',
        rateValue: fmt(results.value.cout_alimentaire_vache, 0, '€/t'),
        comparisonValue: compResult('cout_alimentaire'),
        comparisonRateValue: fmt(comparisonResults.value.cout_alimentaire_vache, 0, '€/t'),
        commentKey: 'cout_alimentaire_vache_l',
        description:
            "L'alimentation est le poste le plus élevé et à l'origine de très nombreux problèmes. Diagnostic nutritionnel, analyse des fourrages, plan de rationnement et suivi régulier.",
    },
]);
</script>

<template>
    <Head :title="`${module.label} #${analysis.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="analysis-show mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-4 px-3 py-4 sm:gap-6 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ module.label }}</h1>
                    <p class="text-muted-foreground">{{ analysis.breeder.name }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="analysisEdit({ analysis: analysis.id }).url"
                        class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium hover:bg-accent"
                    >
                        <Edit class="size-4" />
                        Modifier
                    </Link>
                    <a
                        :href="analysisPdf({ analysis: analysis.id }).url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        <FileText class="size-4" />
                        PDF
                    </a>
                </div>
            </div>

            <!-- Infos générales -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Coordonnées et description générale</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Eleveur</p>
                        <p class="font-medium">{{ analysis.breeder.name }}</p>
                        <p class="text-sm text-muted-foreground">{{ analysis.breeder.postal_code }} {{ analysis.breeder.city }}</p>
                        <p v-if="analysis.breeder.herd_number" class="text-xs text-muted-foreground">n° {{ analysis.breeder.herd_number }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Race / Année</p>
                        <p class="font-medium">{{ results.race ?? payload.race ?? '-' }}</p>
                        <p class="text-sm text-muted-foreground">{{ payload.annee_reference ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Date du bilan</p>
                        <p class="text-sm">{{ formatDate(analysis.analyzed_at) }}</p>
                        <p class="mt-1 text-xs uppercase text-muted-foreground">Intervenant</p>
                        <p class="text-sm">{{ analysis.intervenant ?? '-' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs uppercase text-muted-foreground">Production laitière</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-sm">
                            <span class="text-muted-foreground">Vaches</span><span class="font-medium">{{ fmtInt(payload.nb_vaches_productrices) }}</span>
                            <span class="text-muted-foreground">Production</span><span class="font-medium">{{ fmtInt(payload.production_annuelle_lait, 't') }}</span>
                            <span class="text-muted-foreground">Moy./vache</span><span class="font-medium">{{ fmtInt(results.production_moyenne_vache, 'L') }}</span>
                            <span class="text-muted-foreground">TB moyen</span><span class="font-medium">{{ fmt(payload.tx_butyreux_moyen, 1) }}</span>
                            <span class="text-muted-foreground">TP moyen</span><span class="font-medium">{{ fmt(payload.tx_proteique_moyen, 1) }}</span>
                            <span class="text-muted-foreground">CCT moyen</span><span class="font-medium">{{ fmtInt(payload.concentration_cellulaire_moyen, 'k') }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="comparisonAnalysis" class="rounded-xl border border-blue-200 bg-blue-50/60 p-3 sm:p-5 dark:border-blue-900 dark:bg-blue-950/20">
                <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Comparaison ancien bilan</h2>
                        <p class="text-xs text-blue-700/80 dark:text-blue-200/80">{{ comparisonLabel }}</p>
                    </div>
                    <p class="text-xs text-blue-700/80 dark:text-blue-200/80">{{ comparisonAnalysis.breeder.name }}</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="row in comparisonRows" :key="row.label" class="rounded-lg bg-background/80 p-3 text-sm">
                        <p class="text-xs uppercase text-muted-foreground">{{ row.label }}</p>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <div>
                                <p class="text-[10px] uppercase text-muted-foreground">Actuel</p>
                                <p class="font-semibold">{{ row.current }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase text-blue-700 dark:text-blue-300">Ancien</p>
                                <p class="font-semibold text-blue-700 dark:text-blue-300">{{ row.previous }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Taux de mammites -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Taux de mammites</h2>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <!-- Gauge: Vaches > 250k CCI -->
                    <div class="flex flex-col items-center gap-1">
                        <div
                            class="flex h-20 w-20 items-center justify-center rounded-full border-4 text-xl font-bold"
                            :class="[cci250Badge, 'border-current/30']"
                        >
                            {{ fmt(results.tx_cci250, 1) }}%
                        </div>
                        <p class="text-center text-xs text-muted-foreground">Vaches &gt; 250 000 cells</p>
                        <p v-if="compResult('tx_cci250') !== null" class="text-center text-[10px] text-blue-700">
                            Ancien: {{ fmt(compResult('tx_cci250'), 1, '%') }}
                        </p>
                    </div>

                    <!-- Gauge: Tx mammites -->
                    <div class="flex flex-col items-center gap-1">
                        <div
                            class="flex h-20 w-20 items-center justify-center rounded-full border-4 text-xl font-bold"
                            :class="[mammitesBadge, 'border-current/30']"
                        >
                            {{ fmt(results.tx_mammites, 1) }}%
                        </div>
                        <p class="text-center text-xs text-muted-foreground">Taux de mammites</p>
                        <p v-if="compResult('tx_mammites') !== null" class="text-center text-[10px] text-blue-700">
                            Ancien: {{ fmt(compResult('tx_mammites'), 1, '%') }}
                        </p>
                    </div>

                    <div class="flex-1 rounded-lg bg-muted/40 p-3 text-xs text-muted-foreground">
                        <p>En race Prim Holstein, le taux de mammites moyen est de 39%.</p>
                        <p class="mt-1">
                            Sources (INRA 2005) : Bilan et paramètres génétiques des mammites cliniques collectées par le contrôle laitier dans les races Montbéliarde, Normande et Prim'Holstein. B. BONAITI, S. MOUREAUX, S. MATTALIA.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Causes de pathologie et non-guérison -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Pathologies du troupeau</h2>
                <div class="grid gap-6 sm:grid-cols-2">
                    <!-- Causes de maladies -->
                    <div v-if="causesMaladies.length > 0">
                        <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">Causes de maladies</p>
                        <div class="space-y-2">
                            <div v-for="c in causesMaladies" :key="c.label" class="space-y-0.5">
                                <div class="flex items-center justify-between text-sm">
                                    <span>{{ c.label }}</span>
                                    <span class="font-medium">{{ c.value }} cas</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full" :class="c.color" :style="`width: ${Math.min(100, (c as any).pct)}%`"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Taux de non-guérison -->
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">Taux de non-guérison (%)</p>
                        <div class="space-y-2">
                            <div v-for="row in nonGuerison" :key="row.label" class="flex items-center gap-3">
                                <span class="w-32 shrink-0 text-sm text-muted-foreground">{{ row.label }}</span>
                                <div class="relative h-5 flex-1 overflow-hidden rounded bg-muted">
                                    <div
                                        v-if="row.pct !== null"
                                        class="h-full rounded transition-all"
                                        :class="row.pct > 15 ? 'bg-red-400' : row.pct > 5 ? 'bg-amber-400' : 'bg-green-400'"
                                        :style="`width: ${Math.min(100, row.pct)}%`"
                                    ></div>
                                </div>
                                <span class="w-10 text-right text-sm font-medium">{{ row.pct !== null ? row.pct.toFixed(1) + '%' : '–' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Impact des pathologies -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Impact relatif des pathologies</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Taux de morbidité : nombre de vaches atteintes d'une pathologie par rapport au nombre total de vaches du troupeau.
                </p>
                <div class="flex flex-wrap gap-4">
                    <BseMetricBar :value="num(results.tx_mortalite_neonatale)" :thresholds="[5, 10]" unit="%" label="Mortalité néonatale" :comparison-value="compResult('tx_mortalite_neonatale')" />
                    <BseMetricBar :value="num(results.tx_boiteries)" :thresholds="[5, 10]" unit="%" label="Boiteries" :comparison-value="compResult('tx_boiteries')" />
                    <BseMetricBar :value="num(results.tx_fievres_de_lait)" :thresholds="[5, 10]" unit="%" label="Fièvres de lait" :comparison-value="compResult('tx_fievres_de_lait')" />
                    <BseMetricBar :value="num(results.tx_non_delivrances)" :thresholds="[5, 10]" unit="%" label="Non-délivrances" :comparison-value="compResult('tx_non_delivrances')" />
                    <BseMetricBar :value="num(results.tx_metrites)" :thresholds="[5, 10]" unit="%" label="Métrites" :comparison-value="compResult('tx_metrites')" />
                    <BseMetricBar :value="num(results.tx_mammites_locales)" :thresholds="[30, 50]" unit="%" label="Mammites locales" :comparison-value="compResult('tx_mammites_locales')" />
                    <BseMetricBar :value="num(results.tx_mammites_aigues)" :thresholds="[5, 10]" unit="%" label="Mammites aiguës" :comparison-value="compResult('tx_mammites_aigues')" />
                    <BseMetricBar :value="num(results.tx_cci250)" :thresholds="[20, 40]" unit="%" label="CCI > 250 000" :comparison-value="compResult('tx_cci250')" />
                    <BseMetricBar :value="num(results.tx_cetoses)" :thresholds="[5, 10]" unit="%" label="Cétoses" :comparison-value="compResult('tx_cetoses')" />
                    <BseMetricBar :value="num(results.tx_acidoses)" :thresholds="[5, 10]" unit="%" label="Acidoses" :comparison-value="compResult('tx_acidoses')" />
                    <BseMetricBar :value="num(results.tx_caillettes)" :thresholds="[1, 2]" unit="%" label="Caillettes" :comparison-value="compResult('tx_caillettes')" />
                </div>
                <p v-if="commentForKey('tx_mammites')" class="mt-3 rounded-md bg-muted/40 p-3 text-sm italic text-muted-foreground">
                    Mammites : {{ commentForKey('tx_mammites') }}
                </p>
                <p v-if="commentForKey('tx_boiteries')" class="mt-2 rounded-md bg-muted/40 p-3 text-sm italic text-muted-foreground">
                    Boiteries : {{ commentForKey('tx_boiteries') }}
                </p>
                <p v-if="commentForKey('tx_metaboliques')" class="mt-2 rounded-md bg-muted/40 p-3 text-sm italic text-muted-foreground">
                    Métaboliques : {{ commentForKey('tx_metaboliques') }}
                </p>
            </section>

            <!-- Reproduction -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Impact de la reproduction</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Facteurs de risque : alimentation (équilibre énergétique et azoté), détection des chaleurs, agents infectieux, carences oligovitaminiques.
                </p>
                <div class="flex flex-wrap gap-4">
                    <BseMetricBar :value="num(payload.nb_ivia1)" :thresholds="[85, 100]" unit="j" :decimals="0" label="IV-IA1" :comparison-value="compPayload('nb_ivia1')" />
                    <BseMetricBar :value="num(payload.nb_iviaf)" :thresholds="[110, 140]" unit="j" :decimals="0" label="IV-IAF" :comparison-value="compPayload('nb_iviaf')" />
                    <BseMetricBar
                        :value="num(payload.tx_reussite_ia1)"
                        :thresholds="[50, 65]"
                        unit="%"
                        label="Réussite 1ère IA"
                        :comparison-value="compPayload('tx_reussite_ia1')"
                        :higher-is-better="true"
                    />
                    <BseMetricBar :value="num(payload.tx_ia3)" :thresholds="[15, 30]" unit="%" label="3 IA et plus" :comparison-value="compPayload('tx_ia3')" />
                    <BseMetricBar :value="num(payload.ivv)" :thresholds="[400, 420]" unit="j" :decimals="0" label="IVV" :comparison-value="compPayload('ivv')" />
                    <BseMetricBar :value="num(results.veau_par_vache)" :thresholds="[1]" unit="" :decimals="2" label="Veau / Vache" :comparison-value="compResult('veau_par_vache')" :higher-is-better="true" />
                    <BseMetricBar :value="num(results.tx_avortements)" :thresholds="[1]" unit="%" label="Avortements" :comparison-value="compResult('tx_avortements')" />
                </div>
            </section>

            <!-- Gains qualité du lait -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Gains qualité du lait</h2>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Gain TB</p>
                        <p class="text-xl font-semibold" :class="gainPositive(num(results.gain_tb)) ? 'text-green-700' : 'text-red-600'">
                            {{ fmtInt(results.gain_tb, '€') }}
                        </p>
                        <p class="text-xs text-muted-foreground">TB moyen : {{ fmt(payload.tx_butyreux_moyen, 1) }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Gain TP</p>
                        <p class="text-xl font-semibold" :class="gainPositive(num(results.gain_tp)) ? 'text-green-700' : 'text-red-600'">
                            {{ fmtInt(results.gain_tp, '€') }}
                        </p>
                        <p class="text-xs text-muted-foreground">TP moyen : {{ fmt(payload.tx_proteique_moyen, 1) }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Gain taux total</p>
                        <p class="text-xl font-semibold" :class="gainPositive(num(results.gain_taux)) ? 'text-green-700' : 'text-red-600'">
                            {{ fmtInt(results.gain_taux, '€') }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Estimation des performances et coûts -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Estimation des performances et coûts</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Chacun de ces problèmes persistants nécessite un service de conseils spécialisés de vos vétérinaires.
                </p>
                <div class="space-y-3">
                    <div v-for="card in costCards" :key="card.label" class="grid gap-4 rounded-lg border border-border p-4 sm:grid-cols-[auto_1fr]">
                        <!-- Gauge indicator -->
                        <div class="flex flex-col items-center gap-1">
                            <div
                                class="flex h-16 w-16 items-center justify-center rounded-full border-2 text-xs font-bold"
                                :class="
                                    card.value !== null && card.value > 0
                                        ? 'border-red-300 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300'
                                        : 'border-green-300 bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300'
                                "
                            >
                                <span class="text-center leading-tight">{{ card.rateValue }}</span>
                            </div>
                            <p class="text-center text-[10px] text-muted-foreground">{{ card.rateLabel }}</p>
                            <p
                                class="text-sm font-semibold"
                                :class="card.value !== null && card.value > 0 ? 'text-red-700 dark:text-red-400' : 'text-green-700 dark:text-green-400'"
                            >
                                {{ card.value !== null ? fmtInt(card.value, '€') : '-' }}
                            </p>
                            <p v-if="card.comparisonValue !== null && card.comparisonValue !== undefined" class="text-center text-[10px] leading-tight text-blue-700">
                                Ancien {{ fmtInt(card.comparisonValue, '€') }} · {{ card.comparisonRateValue }}
                            </p>
                        </div>
                        <!-- Description -->
                        <div>
                            <p class="mb-1 font-medium">{{ card.label }}</p>
                            <p class="text-sm text-muted-foreground">{{ card.description }}</p>
                            <p v-if="commentForKey(card.commentKey)" class="mt-2 text-sm italic text-muted-foreground">
                                {{ commentForKey(card.commentKey) }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
