<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Edit, FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    edit as analysisEdit,
    index as analysesIndex,
    pdf as analysisPdf,
} from '@/actions/App/Http/Controllers/VeterinaryAnalysisController';
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

const props = defineProps<{
    analysis: Analysis;
    module: ModuleInfo;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    {
        title: props.module.short_label,
        href: analysesIndex({ module: props.module.slug }).url,
    },
    { title: `Analyse #${props.analysis.id}`, href: '#' },
];

const results = computed<Record<string, any>>(
    () => (props.analysis.results ?? {}) as Record<string, any>,
);
const payload = computed<Record<string, any>>(
    () => props.analysis.payload as Record<string, any>,
);
const bloodGasResultCards = computed(() => [
    { label: 'Deshydratation', value: results.value.dehydration, unit: '%' },
    {
        label: 'Deficit hydrique',
        value: results.value.volume_deficit_l,
        unit: 'L',
    },
    {
        label: 'Deficit bicarbonate',
        value: results.value.deficit_bicarbonate_g,
        unit: 'g',
    },
    {
        label: 'Deficit glucose',
        value: results.value.deficit_glucose_g,
        unit: 'g',
    },
]);
const bloodGasBalanceRows = computed(() => [
    {
        label: 'Apports perfusions',
        bicarbonate: results.value.apports?.bicarbonate_g,
        glucose: results.value.apports?.glucose_g,
        volume: results.value.apports?.volume_l,
    },
    {
        label: 'Reste a couvrir',
        bicarbonate: results.value.restes?.bicarbonate_g,
        glucose: results.value.restes?.glucose_g,
        volume: results.value.restes?.volume_l,
    },
]);
const bloodGasInterpretationRows = computed(() =>
    Object.entries(results.value.interpretations ?? {}).map(
        ([field, rawRow]) => {
            const row = rawRow as Record<string, unknown>;

            return {
                field,
                label: bloodGasFieldLabel(field),
                value: row.value,
                min: row.min,
                max: row.max,
                status: String(row.status ?? ''),
            };
        },
    ),
);

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR');
}

function formatResult(value: unknown, unit = ''): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return `${value}${unit ? ` ${unit}` : ''}`;
}

function bloodGasFieldLabel(field: string): string {
    return (
        {
            ph: 'pH',
            pco2: 'pCO2',
            hco3: 'HCO3',
            na: 'Na',
            k: 'K',
            cl: 'Cl',
            glycemia: 'Glycemie',
        }[field] ?? field
    );
}

function normStatusLabel(status: string): string {
    return status === 'low'
        ? 'Bas'
        : status === 'high'
          ? 'Haut'
          : status === 'normal'
            ? 'OK'
            : '-';
}

function normStatusClass(status: string): string {
    if (status === 'normal') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    }

    if (status === 'low' || status === 'high') {
        return 'bg-red-50 text-red-700 ring-red-200';
    }

    return 'bg-muted text-muted-foreground ring-border';
}

const settings = computed<Record<string, any>>(
    () => (props.analysis.settings_snapshot ?? {}) as Record<string, any>,
);

const enabledParasites = computed<Array<{ key: string; label: string }>>(() =>
    ((settings.value.parasites as Array<any>) ?? []).filter(
        (p: any) => p.enabled !== false,
    ),
);

const enabledPathogens = computed<Array<{ key: string; label: string }>>(() =>
    ((settings.value.pathogens as Array<any>) ?? []).filter(
        (p: any) => p.enabled !== false,
    ),
);

const interpretedGerms = computed<Array<any>>(
    () => (results.value.interpreted_germs as Array<any>) ?? [],
);

const cellulaireNorms = computed(() => {
    const norms = settings.value.norms as
        | { alert_threshold: number; critical_threshold: number; unit: string }
        | undefined;
    return norms ?? { alert_threshold: 300, critical_threshold: 800, unit: 'x 1000 cellules' };
});

function scaleLabel(value: string | number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    const scale = (settings.value.scale as Array<{ value: string; label: string }>) ?? [];
    return scale.find((s) => s.value === String(value))?.label ?? String(value);
}

function cellulaireCountClass(count: number | null): string {
    if (count === null || count === undefined) return '';
    if (count >= cellulaireNorms.value.critical_threshold) return 'font-semibold text-red-600';
    if (count >= cellulaireNorms.value.alert_threshold) return 'font-semibold text-amber-600';
    return 'text-emerald-700';
}

function antibioticInterpClass(interp: string): string {
    if (interp === 'S') return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
    if (interp === 'I') return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
    if (interp === 'R') return 'bg-red-50 text-red-700 ring-1 ring-red-200';
    return 'bg-muted text-muted-foreground ring-1 ring-border';
}

function pathogenResultClass(value: string | number | null | undefined): string {
    const v = String(value ?? '0');
    if (v === '0') return 'text-muted-foreground';
    return 'font-medium text-amber-700';
}
</script>

<template>
    <Head :title="`${module.label} #${analysis.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="analysis-show mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-4 px-3 py-4 sm:gap-6 sm:p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ module.label }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ analysis.breeder.name
                        }}<span v-if="analysis.animal_nom">
                            - {{ analysis.animal_nom }}</span
                        >
                    </p>
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

            <section
                class="grid gap-4 rounded-xl border border-border bg-card p-3 sm:p-5 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Eleveur
                    </p>
                    <p class="font-medium">{{ analysis.breeder.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ analysis.breeder.postal_code }}
                        {{ analysis.breeder.city }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Animal
                    </p>
                    <p class="font-medium">{{ analysis.animal_nom ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">Dates</p>
                    <p class="text-sm">
                        Prelevement : {{ formatDate(analysis.sampled_at) }}
                    </p>
                    <p class="text-sm">
                        Analyse : {{ formatDate(analysis.analyzed_at) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Intervenant
                    </p>
                    <p class="font-medium">{{ analysis.intervenant ?? '-' }}</p>
                </div>
            </section>

            <section
                v-if="module.slug === 'gaz-du-sang'"
                class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-3 sm:p-5 shadow-sm"
            >
                <div>
                    <h2 class="font-semibold">Resultats calcules</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ results.species ?? payload.species ?? '-' }} - profil
                        {{
                            results.calculation_profile === 'ruminant'
                                ? 'ruminant'
                                : 'equin / autre'
                        }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="card in bloodGasResultCards"
                        :key="card.label"
                        class="rounded-lg bg-muted/50 p-3"
                    >
                        <p class="text-xs text-muted-foreground uppercase">
                            {{ card.label }}
                        </p>
                        <p class="text-lg font-semibold">
                            {{ formatResult(card.value, card.unit) }}
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead class="hidden sm:table-header-group">
                            <tr class="border-b border-border bg-muted/40">
                                <th class="px-3 py-2 text-left font-medium">Poste</th>
                                <th class="px-3 py-2 text-left font-medium">Bicarbonate</th>
                                <th class="px-3 py-2 text-left font-medium">Glucose</th>
                                <th class="px-3 py-2 text-left font-medium">Volume</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in bloodGasBalanceRows" :key="row.label" class="border-b border-border/50 last:border-b-0">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ row.label }}</div>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground sm:hidden">
                                        <span>Bicarb : {{ formatResult(row.bicarbonate, 'g') }}</span>
                                        <span>Glucose : {{ formatResult(row.glucose, 'g') }}</span>
                                        <span>Vol : {{ formatResult(row.volume, 'L') }}</span>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-2 sm:table-cell">{{ formatResult(row.bicarbonate, 'g') }}</td>
                                <td class="hidden px-3 py-2 sm:table-cell">{{ formatResult(row.glucose, 'g') }}</td>
                                <td class="hidden px-3 py-2 sm:table-cell">{{ formatResult(row.volume, 'L') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead class="hidden sm:table-header-group">
                            <tr class="border-b border-border bg-muted/40">
                                <th class="px-3 py-2 text-left font-medium">Parametre</th>
                                <th class="px-3 py-2 text-left font-medium">Valeur</th>
                                <th class="px-3 py-2 text-left font-medium">Norme</th>
                                <th class="px-3 py-2 text-left font-medium">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in bloodGasInterpretationRows" :key="row.field" class="border-b border-border/50 last:border-b-0">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ row.label }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground sm:hidden">
                                        <span>{{ formatResult(row.value) }}</span>
                                        <span>{{ row.min }} - {{ row.max }}</span>
                                        <span class="inline-flex rounded-full px-2 py-0.5 font-medium ring-1" :class="normStatusClass(row.status)">{{ normStatusLabel(row.status) }}</span>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-2 sm:table-cell">{{ formatResult(row.value) }}</td>
                                <td class="hidden px-3 py-2 text-muted-foreground sm:table-cell">{{ row.min }} - {{ row.max }}</td>
                                <td class="hidden px-3 py-2 sm:table-cell">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium ring-1" :class="normStatusClass(row.status)">{{ normStatusLabel(row.status) }}</span>
                                </td>
                            </tr>
                            <tr v-if="bloodGasInterpretationRows.length === 0">
                                <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">Aucune norme applicable.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Comptage cellulaire -->
            <section
                v-else-if="module.slug === 'comptage-cellulaire'"
                class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-3 sm:p-5"
            >
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    <span v-if="payload.species">{{ payload.species }}</span>
                    <span v-if="payload.sample_nature">· {{ payload.sample_nature }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Echantillons</p>
                        <p class="text-lg font-semibold">{{ results.sample_count ?? '-' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Moyenne</p>
                        <p class="text-lg font-semibold" :class="cellulaireCountClass(results.average as number)">
                            {{ formatResult(results.average, cellulaireNorms.unit) }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Maximum</p>
                        <p class="text-lg font-semibold" :class="cellulaireCountClass(results.max as number)">
                            {{ formatResult(results.max, cellulaireNorms.unit) }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Alertes / Critiques</p>
                        <p class="text-lg font-semibold">
                            {{ results.alert_samples ?? 0 }} / {{ results.critical_samples ?? 0 }}
                        </p>
                    </div>
                </div>
                <div v-if="payload.commemoratives" class="rounded-lg bg-muted/50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commemoratifs</p>
                    <p class="whitespace-pre-wrap text-sm">{{ payload.commemoratives }}</p>
                </div>
                <div class="rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead class="hidden sm:table-header-group">
                            <tr class="border-b border-border bg-muted/40">
                                <th class="px-3 py-2 text-left font-medium">Echantillon</th>
                                <th class="px-3 py-2 text-left font-medium">Comptage ({{ cellulaireNorms.unit }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(sample, i) in (payload.samples as any[])" :key="i" class="border-b border-border/50 last:border-b-0">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ sample.name || `Echantillon ${i + 1}` }}</div>
                                    <div class="mt-0.5 text-xs sm:hidden" :class="cellulaireCountClass(sample.count)">{{ sample.count ?? '-' }} {{ cellulaireNorms.unit }}</div>
                                </td>
                                <td class="hidden px-3 py-2 sm:table-cell" :class="cellulaireCountClass(sample.count)">{{ sample.count ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="payload.comments" class="rounded-lg bg-muted/50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commentaires</p>
                    <p class="whitespace-pre-wrap text-sm">{{ payload.comments }}</p>
                </div>
            </section>

            <!-- Diarrhée néonatale -->
            <section
                v-else-if="module.slug === 'diarrhee-neonatale'"
                class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-3 sm:p-5"
            >
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    <span v-if="payload.species">{{ payload.species }}</span>
                    <span v-if="payload.test_name">· Test : {{ payload.test_name }}</span>
                    <span v-if="payload.sample_nature">· {{ payload.sample_nature }}</span>
                    <span v-if="payload.sample_name">· {{ payload.sample_name }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Agents positifs</p>
                        <p class="text-lg font-semibold">{{ results.positive_count ?? 0 }}</p>
                    </div>
                </div>
                <div class="rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead class="hidden sm:table-header-group">
                            <tr class="border-b border-border bg-muted/40">
                                <th class="px-3 py-2 text-left font-medium">Agent pathogene</th>
                                <th class="px-3 py-2 text-left font-medium">Resultat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="pathogen in enabledPathogens" :key="pathogen.key" class="border-b border-border/50 last:border-b-0">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ pathogen.label }}</div>
                                    <div class="mt-0.5 text-xs sm:hidden" :class="pathogenResultClass((payload.pathogens as any)?.[pathogen.key])">
                                        {{ scaleLabel((payload.pathogens as any)?.[pathogen.key] ?? '0') }}
                                    </div>
                                </td>
                                <td class="hidden px-3 py-2 sm:table-cell" :class="pathogenResultClass((payload.pathogens as any)?.[pathogen.key])">
                                    {{ scaleLabel((payload.pathogens as any)?.[pathogen.key] ?? '0') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="results.positives && Object.keys(results.positives as object).length > 0" class="space-y-2">
                    <p class="text-sm font-semibold">Agents detectes</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="(pos, key) in (results.positives as Record<string, any>)"
                            :key="key"
                            class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800"
                        >
                            {{ pos.label }}
                        </span>
                    </div>
                </div>
            </section>

            <!-- Coproscopie parasitaire -->
            <section
                v-else-if="module.slug === 'coproscopie-parasitaire'"
                class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-3 sm:p-5"
            >
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    <span v-if="payload.species">{{ payload.species }}</span>
                    <span v-if="payload.sample_nature">· {{ payload.sample_nature }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Echantillons</p>
                        <p class="text-lg font-semibold">{{ results.sample_count ?? payload.sample_count ?? '-' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Echantillons positifs</p>
                        <p class="text-lg font-semibold">{{ results.positive_count ?? 0 }}</p>
                    </div>
                </div>
                <div class="rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead class="hidden sm:table-header-group">
                            <tr class="border-b border-border bg-muted/40">
                                <th class="px-3 py-2 text-left font-medium">Echantillon</th>
                                <th v-for="parasite in enabledParasites" :key="parasite.key" class="px-3 py-2 text-left font-medium">{{ parasite.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(sample, i) in (payload.samples as any[])" :key="i" class="border-b border-border/50 last:border-b-0">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ sample.name || `Echantillon ${i + 1}` }}</div>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs sm:hidden">
                                        <span v-for="parasite in enabledParasites" :key="parasite.key" :class="pathogenResultClass(sample.results?.[parasite.key])">
                                            {{ parasite.label }} : {{ scaleLabel(sample.results?.[parasite.key] ?? '0') }}
                                        </span>
                                    </div>
                                </td>
                                <td v-for="parasite in enabledParasites" :key="parasite.key" class="hidden px-3 py-2 sm:table-cell" :class="pathogenResultClass(sample.results?.[parasite.key])">
                                    {{ scaleLabel(sample.results?.[parasite.key] ?? '0') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="results.positive_parasites && Object.keys(results.positive_parasites as object).length > 0"
                    class="space-y-2"
                >
                    <p class="text-sm font-semibold">Parasites detectes</p>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="(parasite, key) in (results.positive_parasites as Record<string, any>)"
                            :key="key"
                            class="rounded-lg border border-amber-200 bg-amber-50 p-3"
                        >
                            <p class="font-medium text-amber-800">{{ parasite.label }}</p>
                            <p class="text-xs text-amber-700">
                                {{ parasite.positive_samples }} ech. positif(s) · Score max : {{ parasite.max_score }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Diagnostic bacteriologique -->
            <section
                v-else-if="module.slug === 'diagnostic-bacteriologique'"
                class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-3 sm:p-5"
            >
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    <span v-if="payload.species">{{ payload.species }}</span>
                    <span v-if="payload.sample_nature">· {{ payload.sample_nature }}</span>
                    <span v-if="payload.sample_identification">· {{ payload.sample_identification }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Germes isoles</p>
                        <p class="text-lg font-semibold">{{ results.germ_count ?? 0 }}</p>
                        <span
                            v-if="results.contamination_status === 'sterile'"
                            class="mt-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800"
                        >Sterile</span>
                        <span
                            v-else-if="results.contamination_status === 'contaminated'"
                            class="mt-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800"
                        >Contaminé</span>
                    </div>
                </div>
                <div v-if="payload.commemoratives" class="rounded-lg bg-muted/50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commemoratifs</p>
                    <p class="whitespace-pre-wrap text-sm">{{ payload.commemoratives }}</p>
                </div>
                <div
                    v-for="germ in interpretedGerms"
                    :key="germ.index"
                    class="space-y-2"
                >
                    <h3 class="font-semibold">
                        Germe {{ germ.index }} — {{ germ.family ?? '-' }}
                    </h3>
                    <div v-if="germ.antibiotics?.length" class="rounded-lg border border-border">
                        <table class="w-full text-sm">
                            <thead class="hidden sm:table-header-group">
                                <tr class="border-b border-border bg-muted/40">
                                    <th class="px-3 py-2 text-left font-medium">Antibiotique</th>
                                    <th class="px-3 py-2 text-left font-medium">Diametre (mm)</th>
                                    <th class="px-3 py-2 text-left font-medium">Interpretation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ab in germ.antibiotics" :key="ab.code" class="border-b border-border/50 last:border-b-0">
                                    <td class="px-3 py-2">
                                        <span class="font-medium">{{ ab.code }}</span>
                                        <span class="ml-1 text-muted-foreground">{{ ab.label }}</span>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs sm:hidden">
                                            <span class="text-muted-foreground">{{ ab.diameter ?? '-' }} mm</span>
                                            <span class="inline-flex rounded-full px-2 py-0.5 font-semibold" :class="antibioticInterpClass(ab.interpretation)">{{ ab.interpretation }}</span>
                                        </div>
                                    </td>
                                    <td class="hidden px-3 py-2 sm:table-cell">{{ ab.diameter ?? '-' }}</td>
                                    <td class="hidden px-3 py-2 sm:table-cell">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="antibioticInterpClass(ab.interpretation)">{{ ab.interpretation }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Aucun antibiogramme saisi.</p>
                </div>
                <p v-if="interpretedGerms.length === 0" class="text-sm text-muted-foreground">
                    Aucun germe enregistre.
                </p>
            </section>

            <section class="flex flex-col gap-4 rounded-xl border border-border bg-card p-3 sm:p-5">
                <div
                    v-if="module.slug === 'gaz-du-sang' && payload.treatment"
                >
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                        Traitement / remarques
                    </p>
                    <p class="whitespace-pre-wrap text-sm">
                        {{ payload.treatment as string }}
                    </p>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                            Conseils preventifs
                        </p>
                        <p class="whitespace-pre-wrap text-sm">
                            {{ (payload.advice_preventive as string) || '-' }}
                        </p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                            Conseils curatifs
                        </p>
                        <p class="whitespace-pre-wrap text-sm">
                            {{ (payload.advice_curative as string) || (payload.advice as string) || '-' }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
