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
            label: 'Mortalité totale',
            current: fmt(results.value.tx_mortalite_total_veaux, 1, '%'),
            previous: fmt(comparisonResults.value.tx_mortalite_total_veaux, 1, '%'),
        },
        {
            label: 'Veaux 90j / vache',
            current: fmt(results.value.tx_vivants3_mois, 2),
            previous: fmt(comparisonResults.value.tx_vivants3_mois, 2),
        },
        {
            label: 'IVV',
            current: fmtInt(payload.value.ivv, 'j'),
            previous: fmtInt(comparisonPayload.value.ivv, 'j'),
        },
        {
            label: 'Diarrhées',
            current: fmt(results.value.tx_malades_diar_total, 1, '%'),
            previous: fmt(comparisonResults.value.tx_malades_diar_total, 1, '%'),
        },
        {
            label: 'Respiratoire',
            current: fmt(results.value.tx_malades_respi, 1, '%'),
            previous: fmt(comparisonResults.value.tx_malades_respi, 1, '%'),
        },
        {
            label: 'Coût mortalité',
            current: fmtInt(results.value.cout_mortalite, '€'),
            previous: fmtInt(comparisonResults.value.cout_mortalite, '€'),
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

// Mortality zone label for race reference (Blonde d'Aquitaine mean = 9.12%)
const mortalityZoneLabel = computed(() => {
    const v = num(results.value.tx_mortalite_total_veaux);
    if (v === null) return '';
    if (v <= 5) return 'Parmi les 25% d\'exploitations avec la mortalité la plus faible';
    if (v <= 8) return 'Mortalité inférieure ou égale à la moitié des exploitations';
    if (v <= 12) return 'Mortalité supérieure à 50% des exploitations';
    if (v <= 22) return 'Mortalité supérieure à 75% des exploitations';
    return 'Mortalité supérieure à toutes les exploitations analysées';
});

const mortalityZoneColor = computed(() => {
    const v = num(results.value.tx_mortalite_total_veaux);
    if (v === null) return 'text-muted-foreground';
    if (v <= 5) return 'text-green-700';
    if (v <= 8) return 'text-lime-600';
    if (v <= 12) return 'text-amber-600';
    if (v <= 22) return 'text-orange-600';
    return 'text-red-700';
});

const mortalityBadgeColor = computed(() => {
    const v = num(results.value.tx_mortalite_total_veaux);
    if (v === null) return 'bg-gray-100 text-gray-700';
    if (v <= 5) return 'bg-green-100 text-green-800';
    if (v <= 8) return 'bg-lime-100 text-lime-800';
    if (v <= 12) return 'bg-amber-100 text-amber-800';
    if (v <= 22) return 'bg-orange-100 text-orange-800';
    return 'bg-red-100 text-red-800';
});

// Disease/mortality cause breakdown from raw counts in payload
const totalMalades = computed(() => {
    const d = (num(payload.value.nb_malades_diar1) ?? 0) + (num(payload.value.nb_malades_diar2et3) ?? 0) + (num(payload.value.nb_malades_diar4) ?? 0);
    const r = num(payload.value.nb_malades_respi) ?? 0;
    const o = num(payload.value.nb_malades_omphalite) ?? 0;
    const a = num(payload.value.nb_malades_autres) ?? 0;
    return d + r + o + a;
});

interface CauseBar {
    label: string;
    count: number;
    pct: number;
    color: string;
}

const causeMaladies = computed((): CauseBar[] => {
    const total = totalMalades.value;
    if (!total) return [];
    const d = (num(payload.value.nb_malades_diar1) ?? 0) + (num(payload.value.nb_malades_diar2et3) ?? 0) + (num(payload.value.nb_malades_diar4) ?? 0);
    const r = num(payload.value.nb_malades_respi) ?? 0;
    const o = num(payload.value.nb_malades_omphalite) ?? 0;
    const a = num(payload.value.nb_malades_autres) ?? 0;
    return [
        { label: 'Diarrhée', count: d, pct: (d / total) * 100, color: 'bg-amber-400' },
        { label: 'Respiratoire', count: r, pct: (r / total) * 100, color: 'bg-blue-400' },
        { label: 'Omphalite', count: o, pct: (o / total) * 100, color: 'bg-purple-400' },
        { label: 'Autres', count: a, pct: (a / total) * 100, color: 'bg-gray-400' },
    ].filter((c) => c.count > 0);
});

const totalMorts = computed(() => {
    const d = (num(payload.value.nb_morts_diar1) ?? 0) + (num(payload.value.nb_morts_diar2et3) ?? 0) + (num(payload.value.nb_morts_diar4) ?? 0);
    const r = num(payload.value.nb_morts_respi) ?? 0;
    const o = num(payload.value.nb_morts_omphalite) ?? 0;
    const a = num(payload.value.nb_morts_autres) ?? 0;
    const s = num(payload.value.nb_morts_subites) ?? 0;
    return d + r + o + a + s;
});

const causeMortalite = computed((): CauseBar[] => {
    const total = totalMorts.value;
    if (!total) return [];
    const d = (num(payload.value.nb_morts_diar1) ?? 0) + (num(payload.value.nb_morts_diar2et3) ?? 0) + (num(payload.value.nb_morts_diar4) ?? 0);
    const r = num(payload.value.nb_morts_respi) ?? 0;
    const o = num(payload.value.nb_morts_omphalite) ?? 0;
    const a = num(payload.value.nb_morts_autres) ?? 0;
    const s = num(payload.value.nb_morts_subites) ?? 0;
    return [
        { label: 'Diarrhée', count: d, pct: (d / total) * 100, color: 'bg-amber-400' },
        { label: 'Respiratoire', count: r, pct: (r / total) * 100, color: 'bg-blue-400' },
        { label: 'Omphalite', count: o, pct: (o / total) * 100, color: 'bg-purple-400' },
        { label: 'Mort subite', count: s, pct: (s / total) * 100, color: 'bg-gray-500' },
        { label: 'Autres', count: a, pct: (a / total) * 100, color: 'bg-gray-400' },
    ].filter((c) => c.count > 0);
});

// Lethality rates
interface LetaliteRow {
    label: string;
    pct: number | null;
}
const letaliteRows = computed((): LetaliteRow[] => [
    { label: 'Diarrhée 0-4j', pct: num(results.value.letalite_malades_diar1) },
    { label: 'Diarrhée 5-21j', pct: num(results.value.letalite_malades_diar2et3) },
    { label: 'Diarrhée >21j', pct: num(results.value.letalite_malades_diar4) },
    { label: 'Respiratoire', pct: num(results.value.letalite_malades_respi) },
    { label: 'Omphalite', pct: num(results.value.letalite_malades_omphalite) },
    { label: 'Autres', pct: num(results.value.letalite_malades_autres) },
]);

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
}

const costCards = computed((): CostCard[] => [
    {
        label: 'Mortalité des veaux',
        value: num(results.value.cout_mortalite),
        rateLabel: 'Mortalité',
        rateValue: fmt(results.value.tx_mortalite_total_veaux, 1, '%'),
        comparisonValue: compResult('cout_mortalite'),
        comparisonRateValue: fmt(comparisonResults.value.tx_mortalite_total_veaux, 1, '%'),
        commentKey: 'tx_mortalite_total_veaux',
        description:
            "L'audit maladies néonatales bovines permet de réduire le coût de traitement, les pertes de croissance et la mortalité. Une approche globale : conduite d'élevage, alimentation des mères et des veaux, vermifugation et vaccinations.",
    },
    {
        label: 'Diarrhées néonatales',
        value: num(results.value.cout_diarrhee),
        rateLabel: 'Tx diarrhées',
        rateValue: fmt(results.value.tx_malades_diar_total, 1, '%'),
        comparisonValue: compResult('cout_diarrhee'),
        comparisonRateValue: fmt(comparisonResults.value.tx_malades_diar_total, 1, '%'),
        commentKey: 'tx_diarrhee_veaux_total',
        description:
            'Le plan diarrhées néonatales étudie les facteurs de risque et met en place les solutions adaptées : alimentation des mères, gestion sanitaire, vaccination, qualité des colostrums.',
    },
    {
        label: 'Pathologies respiratoires',
        value: num(results.value.cout_respi),
        rateLabel: 'Tx respiratoire',
        rateValue: fmt(results.value.tx_malades_respi, 1, '%'),
        comparisonValue: compResult('cout_respi'),
        comparisonRateValue: fmt(comparisonResults.value.tx_malades_respi, 1, '%'),
        commentKey: 'tx_respi_veaux',
        description:
            'Mise en place ou redéfinition d\'un protocole de vaccination efficace contre la grippe adapté à votre situation. L\'alimentation des veaux et le bâtiment demandent également une gestion particulière.',
    },
    {
        label: 'Omphalites',
        value: num(results.value.cout_omphalite),
        rateLabel: 'Tx omphalites',
        rateValue: fmt(results.value.tx_malades_omphalite, 1, '%'),
        comparisonValue: compResult('cout_omphalite'),
        comparisonRateValue: fmt(comparisonResults.value.tx_malades_omphalite, 1, '%'),
        commentKey: 'tx_omphalite_veaux',
        description: 'Solutions préventives efficaces : hygiène, désinfection, oligoéléments.',
    },
    {
        label: 'Intervalle vêlage-vêlage',
        value: num(results.value.cout_ivv),
        rateLabel: 'IVV',
        rateValue: fmtInt(payload.value.ivv, 'j'),
        comparisonValue: compResult('cout_ivv'),
        comparisonRateValue: fmtInt(comparisonPayload.value.ivv, 'j'),
        commentKey: 'ivv',
        description:
            "Examens échographiques et gestion régulière de la reproduction. L'alimentation des vaches est la principale cause d'infertilité : un diagnostic nutritionnel précis puis un suivi régulier.",
    },
    {
        label: 'Coût alimentaire / vache',
        value: num(results.value.cout_alimentaire),
        rateLabel: 'Coût / vache',
        rateValue: fmt(results.value.cout_alimentaire_vache, 0, '€'),
        comparisonValue: compResult('cout_alimentaire'),
        comparisonRateValue: fmt(comparisonResults.value.cout_alimentaire_vache, 0, '€'),
        commentKey: 'cout_alimentaire_vache',
        description:
            "L'alimentation est le poste de dépenses le plus élevé et à l'origine de très nombreux problèmes. Diagnostic nutritionnel précis, analyse des fourrages, plan de rationnement et suivi régulier.",
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
                        <p class="font-medium">{{ payload.race ?? '-' }}</p>
                        <p class="text-sm text-muted-foreground">{{ payload.annee_reference ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Date du bilan</p>
                        <p class="text-sm">{{ formatDate(analysis.analyzed_at) }}</p>
                        <p class="mt-1 text-xs uppercase text-muted-foreground">Intervenant</p>
                        <p class="text-sm">{{ analysis.intervenant ?? '-' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs uppercase text-muted-foreground">Cheptel</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-sm">
                            <span class="text-muted-foreground">SAU</span><span class="font-medium">{{ fmtInt(payload.ha_sau, 'ha') }}</span>
                            <span class="text-muted-foreground">UGB</span><span class="font-medium">{{ fmtInt(payload.nb_ugb) }}</span>
                            <span class="text-muted-foreground">Vaches</span><span class="font-medium">{{ fmtInt(payload.nb_vaches) }}</span>
                            <span class="text-muted-foreground">Veaux nés</span><span class="font-medium">{{ fmtInt(payload.nb_veaux_nes_vivants) }}</span>
                            <span class="text-muted-foreground">Sevrés</span><span class="font-medium">{{ fmtInt(payload.nb_sevres) }}</span>
                            <span class="text-muted-foreground">Réformes</span><span class="font-medium">{{ fmtInt(payload.nb_reformes) }}</span>
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

            <!-- Niveau de mortalité des veaux -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Niveau de mortalité des veaux</h2>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <!-- Gauge circle display -->
                    <div class="flex shrink-0 flex-col items-center gap-1">
                        <div
                            class="flex h-24 w-24 items-center justify-center rounded-full border-4 text-2xl font-bold"
                            :class="[mortalityBadgeColor, 'border-current/30']"
                        >
                            {{ fmt(results.tx_mortalite_total_veaux, 1) }}%
                        </div>
                        <p class="text-xs text-muted-foreground">Mortalité totale</p>
                        <p v-if="compResult('tx_mortalite_total_veaux') !== null" class="text-[10px] text-blue-700">
                            Ancien: {{ fmt(compResult('tx_mortalite_total_veaux'), 1, '%') }}
                        </p>
                    </div>

                    <!-- Zone description -->
                    <div class="flex-1 space-y-3">
                        <p class="font-medium" :class="mortalityZoneColor">{{ mortalityZoneLabel }}</p>
                        <div class="space-y-1.5 text-xs text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-6 shrink-0 rounded-full bg-green-400"></span>
                                <span>De 0 à 5% : troupeau parmi les 25% avec la mortalité la plus faible</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-6 shrink-0 rounded-full bg-lime-400"></span>
                                <span>De 5 à 8% : mortalité inférieure ou égale à la moitié des exploitations</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-6 shrink-0 rounded-full bg-amber-400"></span>
                                <span>De 8 à 12% : mortalité supérieure à 50% des exploitations</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-6 shrink-0 rounded-full bg-orange-400"></span>
                                <span>De 12 à 22% : mortalité supérieure à 75% des exploitations</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-6 shrink-0 rounded-full bg-red-500"></span>
                                <span>Au-delà de 22% : mortalité supérieure à toutes les exploitations analysées</span>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Source : données BDNI de 33 982 troupeaux de 8 races allaitantes entre 2005 et 2009.
                        </p>
                    </div>

                    <!-- Key mortality sub-indicators -->
                    <div class="shrink-0 space-y-2 rounded-lg bg-muted/40 p-3 text-sm">
                        <div class="flex justify-between gap-6">
                            <span class="text-muted-foreground">Veaux nés</span>
                            <span class="font-medium">{{ fmtInt(payload.nb_veaux_nes_vivants) }}</span>
                        </div>
                        <div class="flex justify-between gap-6">
                            <span class="text-muted-foreground">Dont accidents vêlage</span>
                            <span class="font-medium">{{ fmtInt(payload.nb_accidents_velage, '') }}</span>
                        </div>
                        <div class="flex justify-between gap-6">
                            <span class="text-muted-foreground">Morts au-delà de 24h</span>
                            <span class="font-medium">{{ fmtInt(results.nb_morts_post24h_retenus ?? payload.nb_morts_post24h) }}</span>
                        </div>
                        <div class="flex justify-between gap-6">
                            <span class="text-muted-foreground">Avortements</span>
                            <span class="font-medium">{{ fmtInt(payload.nb_avortons) }}</span>
                        </div>
                        <div class="flex justify-between gap-6">
                            <span class="text-muted-foreground">Sevrés / vendus</span>
                            <span class="font-medium">{{ fmtInt(payload.nb_sevres) }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Causes de maladies et de mortalité -->
            <section v-if="causeMaladies.length > 0 || causeMortalite.length > 0" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Causes de maladies et de mortalité</h2>
                <div class="grid gap-6 sm:grid-cols-2">
                    <!-- Maladies -->
                    <div v-if="causeMaladies.length > 0">
                        <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">Causes de maladies</p>
                        <div class="space-y-2">
                            <div v-for="c in causeMaladies" :key="c.label" class="space-y-0.5">
                                <div class="flex items-center justify-between text-sm">
                                    <span>{{ c.label }}</span>
                                    <span class="font-medium">{{ c.pct.toFixed(1) }}%</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full transition-all" :class="c.color" :style="`width: ${c.pct}%`"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mortalité -->
                    <div v-if="causeMortalite.length > 0">
                        <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">Causes de mortalité</p>
                        <div class="space-y-2">
                            <div v-for="c in causeMortalite" :key="c.label" class="space-y-0.5">
                                <div class="flex items-center justify-between text-sm">
                                    <span>{{ c.label }}</span>
                                    <span class="font-medium">{{ c.pct.toFixed(1) }}%</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full transition-all" :class="c.color" :style="`width: ${c.pct}%`"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Taux de létalité -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Taux de létalité (% morts parmi les malades)</h2>
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                    <div v-for="row in letaliteRows" :key="row.label" class="flex flex-col items-center gap-1 rounded-lg bg-muted/40 p-2">
                        <p class="text-center text-[10px] text-muted-foreground">{{ row.label }}</p>
                        <p
                            class="text-lg font-bold"
                            :class="row.pct !== null && row.pct > 30 ? 'text-red-600' : row.pct !== null && row.pct > 10 ? 'text-amber-600' : 'text-green-700'"
                        >
                            {{ row.pct !== null ? row.pct.toFixed(1) + '%' : '–' }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Impact reproduction -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Impact de la reproduction</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Facteurs de risque : déséquilibres alimentaires, rétentions placentaires, qualité de détection des chaleurs, agents infectieux (BDV, fièvre Q, néosporose).
                </p>
                <div class="flex flex-wrap gap-4">
                    <BseMetricBar
                        :value="num(results.veau_par_vache)"
                        :thresholds="[1]"
                        unit=""
                        :decimals="2"
                        label="Veau / Vache"
                        :comparison-value="compResult('veau_par_vache')"
                        :higher-is-better="true"
                    />
                    <BseMetricBar
                        :value="num(results.tx_vivants3_mois)"
                        :thresholds="[1]"
                        unit=""
                        :decimals="2"
                        label="Veaux 90j / Vache"
                        :comparison-value="compResult('tx_vivants3_mois')"
                        :higher-is-better="true"
                    />
                    <BseMetricBar
                        :value="num(payload.ivv)"
                        :thresholds="[365, 390]"
                        unit="j"
                        :decimals="0"
                        label="IVV"
                        :comparison-value="compPayload('ivv')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_avortements)"
                        :thresholds="[2]"
                        unit="%"
                        label="Avortements"
                        :comparison-value="compResult('tx_avortements')"
                    />
                </div>
                <p v-if="commentForKey('ivv')" class="mt-3 rounded-md bg-muted/40 p-3 text-sm italic text-muted-foreground">
                    {{ commentForKey('ivv') }}
                </p>
            </section>

            <!-- Impact péripartum -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Impact des troubles péripartum</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Facteurs de risque : déséquilibres alimentaires, abreuvement, détection précoce et modalités de traitement, agents pathogènes multi-infectieux.
                </p>
                <div class="flex flex-wrap gap-4">
                    <BseMetricBar
                        :value="num(results.tx_velages_longs)"
                        :thresholds="[3, 6]"
                        unit="%"
                        label="Vêlages longs"
                        :comparison-value="compResult('tx_velages_longs')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_cesariennes)"
                        :thresholds="[5, 10]"
                        unit="%"
                        label="Césariennes"
                        :comparison-value="compResult('tx_cesariennes')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_non_delivrances)"
                        :thresholds="[5, 10]"
                        unit="%"
                        label="Non-délivrances"
                        :comparison-value="compResult('tx_non_delivrances')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_torsions_retournements_matrices)"
                        :thresholds="[2, 4]"
                        unit="%"
                        label="Torsions / retournements"
                        :comparison-value="compResult('tx_torsions_retournements_matrices')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_metrites)"
                        :thresholds="[5, 10]"
                        unit="%"
                        label="Métrites"
                        :comparison-value="compResult('tx_metrites')"
                    />
                </div>
            </section>

            <!-- Impact mortalité / maladies veaux -->
            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-400">Impact des pathologies veaux</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Facteurs de risque : difficulté de vêlage, surveillance, réanimation du veau, agents abortifs, alimentation.
                </p>
                <div class="flex flex-wrap gap-4">
                    <BseMetricBar
                        :value="num(results.tx_mortinatalite)"
                        :thresholds="[1, 2]"
                        unit="%"
                        label="Mortinatalité"
                        :comparison-value="compResult('tx_mortinatalite')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_malades_diar_total)"
                        :thresholds="[15, 30]"
                        unit="%"
                        label="Diarrhées (tx malades)"
                        :comparison-value="compResult('tx_malades_diar_total')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_morts_diar1)"
                        :thresholds="[1, 3]"
                        unit="%"
                        label="Morts diar 0-4j"
                        :comparison-value="compResult('tx_morts_diar1')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_malades_respi)"
                        :thresholds="[5, 15]"
                        unit="%"
                        label="Respiratoire (tx malades)"
                        :comparison-value="compResult('tx_malades_respi')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_morts_respi)"
                        :thresholds="[2, 4]"
                        unit="%"
                        label="Morts respiratoire"
                        :comparison-value="compResult('tx_morts_respi')"
                    />
                    <BseMetricBar
                        :value="num(results.tx_malades_omphalite)"
                        :thresholds="[2, 4]"
                        unit="%"
                        label="Omphalites (tx malades)"
                        :comparison-value="compResult('tx_malades_omphalite')"
                    />
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
