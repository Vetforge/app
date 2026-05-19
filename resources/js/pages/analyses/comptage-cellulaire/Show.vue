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
const settings = computed<Record<string, any>>(() => (props.analysis.settings_snapshot ?? {}) as Record<string, any>);

const norms = computed(() => {
    const n = settings.value.norms as { alert_threshold: number; critical_threshold: number; unit: string } | undefined;
    return n ?? { alert_threshold: 300, critical_threshold: 800, unit: 'x 1000 cellules' };
});

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR');
}

function formatResult(value: unknown, unit = ''): string {
    if (value === null || value === undefined || value === '') return '-';
    return `${value}${unit ? ` ${unit}` : ''}`;
}

function countClass(count: number | null): string {
    if (count === null || count === undefined) return '';
    if (count >= norms.value.critical_threshold) return 'font-semibold text-red-600';
    if (count >= norms.value.alert_threshold) return 'font-semibold text-amber-600';
    return 'text-emerald-700';
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
                        <p class="text-lg font-semibold" :class="countClass(results.average as number)">{{ formatResult(results.average, norms.unit) }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Maximum</p>
                        <p class="text-lg font-semibold" :class="countClass(results.max as number)">{{ formatResult(results.max, norms.unit) }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Alertes / Critiques</p>
                        <p class="text-lg font-semibold">{{ results.alert_samples ?? 0 }} / {{ results.critical_samples ?? 0 }}</p>
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
                                <th class="px-3 py-2 text-left font-medium">Comptage ({{ norms.unit }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(sample, i) in (payload.samples as any[])" :key="i" class="border-b border-border/50 last:border-b-0">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ sample.name || `Echantillon ${i + 1}` }}</div>
                                    <div class="mt-0.5 text-xs sm:hidden" :class="countClass(sample.count)">{{ sample.count ?? '-' }} {{ norms.unit }}</div>
                                </td>
                                <td class="hidden px-3 py-2 sm:table-cell" :class="countClass(sample.count)">{{ sample.count ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="payload.comments" class="rounded-lg bg-muted/50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commentaires</p>
                    <p class="whitespace-pre-wrap text-sm">{{ payload.comments }}</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
