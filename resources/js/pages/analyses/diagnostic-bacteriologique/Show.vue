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

const interpretedGerms = computed<Array<any>>(() => (results.value.interpreted_germs as Array<any>) ?? []);

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR');
}

function antibioticInterpClass(interp: string): string {
    if (interp === 'S') return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
    if (interp === 'I') return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
    if (interp === 'R') return 'bg-red-50 text-red-700 ring-1 ring-red-200';
    return 'bg-muted text-muted-foreground ring-1 ring-border';
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
                    <span v-if="payload.sample_nature">{{ payload.sample_nature }}</span>
                    <span v-if="payload.sample_identification">· {{ payload.sample_identification }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs uppercase text-muted-foreground">Germes isoles</p>
                        <p class="text-lg font-semibold">{{ results.germ_count ?? 0 }}</p>
                        <span v-if="results.contamination_status === 'sterile'" class="mt-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Sterile</span>
                        <span v-else-if="results.contamination_status === 'contaminated'" class="mt-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Contaminé</span>
                    </div>
                </div>
                <div v-if="payload.commemoratives" class="rounded-lg bg-muted/50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commemoratifs</p>
                    <p class="whitespace-pre-wrap text-sm">{{ payload.commemoratives }}</p>
                </div>
                <div v-for="germ in interpretedGerms" :key="germ.index" class="space-y-2">
                    <h3 class="font-semibold">Germe {{ germ.index }} — {{ germ.family ?? '-' }}</h3>
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
                <p v-if="interpretedGerms.length === 0" class="text-sm text-muted-foreground">Aucun germe enregistre.</p>
            </section>

            <section v-if="payload.advice" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Conseils bacteriologie</p>
                <p class="whitespace-pre-wrap text-sm">{{ payload.advice as string }}</p>
            </section>
        </div>
    </AppLayout>
</template>
