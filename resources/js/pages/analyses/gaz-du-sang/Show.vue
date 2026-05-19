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
    { title: props.module.short_label, href: analysesIndex({ module: props.module.slug }).url },
    { title: `Analyse #${props.analysis.id}`, href: '#' },
];

const results = computed<Record<string, any>>(() => (props.analysis.results ?? {}) as Record<string, any>);
const payload = computed<Record<string, any>>(() => props.analysis.payload as Record<string, any>);

const resultCards = computed(() => [
    { label: 'Deshydratation', value: results.value.dehydration, unit: '%' },
    { label: 'Deficit hydrique', value: results.value.volume_deficit_l, unit: 'L' },
    { label: 'Deficit bicarbonate', value: results.value.deficit_bicarbonate_g, unit: 'g' },
    { label: 'Deficit glucose', value: results.value.deficit_glucose_g, unit: 'g' },
]);

const balanceRows = computed(() => [
    { label: 'Apports perfusions', bicarbonate: results.value.apports?.bicarbonate_g, glucose: results.value.apports?.glucose_g, volume: results.value.apports?.volume_l },
    { label: 'Reste a couvrir', bicarbonate: results.value.restes?.bicarbonate_g, glucose: results.value.restes?.glucose_g, volume: results.value.restes?.volume_l },
]);

const activePerfusions = computed(() => {
    const settingsItems: Array<Record<string, any>> = Array.isArray(props.analysis.settings_snapshot?.perfusions)
        ? (props.analysis.settings_snapshot.perfusions as Array<Record<string, any>>)
        : [];
    const settingsMap = Object.fromEntries(settingsItems.map((p) => [p.key, p]));
    const payloadPerfusions = (payload.value.perfusions ?? {}) as Record<string, unknown>;

    return Object.entries(payloadPerfusions)
        .filter(([, qty]) => parseFloat(String(qty)) > 0)
        .map(([key, qty]) => {
            const s = settingsMap[key] ?? {};
            const quantity = parseFloat(String(qty));
            return {
                key,
                label: s.label ?? key,
                unit: s.unit ?? '',
                quantity,
                bicarbonate: s.bicarbonate ? Math.round(quantity * parseFloat(s.bicarbonate) * 10) / 10 : null,
                glucose: s.glucose ? Math.round(quantity * parseFloat(s.glucose) * 10) / 10 : null,
                volume: s.volume ? Math.round(quantity * parseFloat(s.volume) * 10) / 10 : null,
            };
        });
});

const interpretationRows = computed(() =>
    Object.entries(results.value.interpretations ?? {}).map(([field, rawRow]) => {
        const row = rawRow as Record<string, unknown>;
        return {
            field,
            label: fieldLabel(field),
            value: row.value,
            min: row.min,
            max: row.max,
            status: String(row.status ?? ''),
        };
    }),
);

function fieldLabel(field: string): string {
    return ({ ph: 'pH', pco2: 'pCO2', hco3: 'HCO3', na: 'Na', k: 'K', cl: 'Cl', glycemia: 'Glycemie' } as Record<string, string>)[field] ?? field;
}

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR');
}

function formatResult(value: unknown, unit = ''): string {
    if (value === null || value === undefined || value === '') return '-';
    return `${value}${unit ? ` ${unit}` : ''}`;
}

function normStatusLabel(status: string): string {
    return status === 'low' ? 'Bas' : status === 'high' ? 'Haut' : status === 'normal' ? 'OK' : '-';
}

function normStatusClass(status: string): string {
    if (status === 'normal') return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    if (status === 'low' || status === 'high') return 'bg-red-50 text-red-700 ring-red-200';
    return 'bg-muted text-muted-foreground ring-border';
}
</script>

<template>
    <Head :title="`${module.label} #${analysis.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="analysis-show mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-4 px-3 py-4 sm:gap-6 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ module.label }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ analysis.breeder.name }}<span v-if="analysis.animal_nom"> - {{ analysis.animal_nom }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="analysisEdit({ analysis: analysis.id }).url" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium hover:bg-accent">
                        <Edit class="size-4" />
                        Modifier
                    </Link>
                    <a :href="analysisPdf({ analysis: analysis.id }).url" target="_blank" rel="noreferrer" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                        <FileText class="size-4" />
                        PDF
                    </a>
                </div>
            </div>

            <section class="grid gap-4 rounded-xl border border-border bg-card p-3 sm:p-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs text-muted-foreground uppercase">Eleveur</p>
                    <p class="font-medium">{{ analysis.breeder.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ analysis.breeder.postal_code }} {{ analysis.breeder.city }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">Animal</p>
                    <p class="font-medium">{{ analysis.animal_nom ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">Dates</p>
                    <p class="text-sm">Prelevement : {{ formatDate(analysis.sampled_at) }}</p>
                    <p class="text-sm">Analyse : {{ formatDate(analysis.analyzed_at) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">Intervenant</p>
                    <p class="font-medium">{{ analysis.intervenant ?? '-' }}</p>
                </div>
            </section>

            <section class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-3 sm:p-5">
                <div>
                    <h2 class="font-semibold">Resultats calcules</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ results.species ?? payload.species ?? '-' }} - profil {{ results.calculation_profile === 'ruminant' ? 'ruminant' : 'equin / autre' }}
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="card in resultCards" :key="card.label" class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs text-muted-foreground uppercase">{{ card.label }}</p>
                        <p class="text-lg font-semibold">{{ formatResult(card.value, card.unit) }}</p>
                    </div>
                </div>
                <template v-if="activePerfusions.length > 0">
                    <h3 class="text-sm font-semibold text-foreground">Perfusions administrees</h3>
                    <div class="rounded-lg border border-border">
                        <table class="w-full text-sm">
                            <thead class="hidden sm:table-header-group">
                                <tr class="border-b border-border bg-muted/40">
                                    <th class="px-3 py-2 text-left font-medium">Perfusion</th>
                                    <th class="px-3 py-2 text-left font-medium">Quantite</th>
                                    <th class="px-3 py-2 text-left font-medium">Bicarbonate apporte</th>
                                    <th class="px-3 py-2 text-left font-medium">Glucose apporte</th>
                                    <th class="px-3 py-2 text-left font-medium">Volume apporte</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in activePerfusions" :key="p.key" class="border-b border-border/50 last:border-b-0">
                                    <td class="px-3 py-2">
                                        <div class="font-medium">{{ p.label }}</div>
                                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground sm:hidden">
                                            <span>{{ p.quantity }} {{ p.unit }}</span>
                                            <span v-if="p.bicarbonate !== null">Bicarb : {{ p.bicarbonate }} g</span>
                                            <span v-if="p.glucose !== null">Glucose : {{ p.glucose }} g</span>
                                            <span v-if="p.volume !== null">Vol : {{ p.volume }} L</span>
                                        </div>
                                    </td>
                                    <td class="hidden px-3 py-2 sm:table-cell">{{ p.quantity }} {{ p.unit }}</td>
                                    <td class="hidden px-3 py-2 sm:table-cell">{{ p.bicarbonate !== null ? `${p.bicarbonate} g` : '-' }}</td>
                                    <td class="hidden px-3 py-2 sm:table-cell">{{ p.glucose !== null ? `${p.glucose} g` : '-' }}</td>
                                    <td class="hidden px-3 py-2 sm:table-cell">{{ p.volume !== null ? `${p.volume} L` : '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

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
                            <tr v-for="row in balanceRows" :key="row.label" class="border-b border-border/50 last:border-b-0">
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
                            <tr v-for="row in interpretationRows" :key="row.field" class="border-b border-border/50 last:border-b-0">
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
                            <tr v-if="interpretationRows.length === 0">
                                <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">Aucune norme applicable.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="flex flex-col gap-4 rounded-xl border border-border bg-card p-3 sm:p-5">
                <div v-if="payload.treatment">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Traitement / remarques</p>
                    <p class="whitespace-pre-wrap text-sm">{{ payload.treatment as string }}</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
