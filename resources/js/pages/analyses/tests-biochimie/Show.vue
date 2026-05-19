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
    breeder: { name: string; address: string | null; postal_code: string | null; city: string | null; herd_number: string | null };
    animal_nom: string | null;
    sampled_at: string | null;
    analyzed_at: string | null;
    intervenant: string | null;
    payload: Record<string, unknown>;
    settings_snapshot: Record<string, unknown> | null;
}

const props = defineProps<{
    analysis: Analysis;
    module: ModuleInfo;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: props.module.short_label, href: analysesIndex({ module: props.module.slug }).url },
    { title: `#${props.analysis.id}`, href: '#' },
];

const payload = computed(() => props.analysis.payload as {
    species: string;
    sample_nature: string;
    identification: string;
    commemoratifs: string;
    params: Record<string, string>;
    commentaires: string;
});

const settings = computed(() => (props.analysis.settings_snapshot ?? {}) as Record<string, any>);

interface ParamDef { key: string; label: string; species?: string[]; enabled: boolean }
interface NormRange { min: number | null; max: number | null; unit: string }

const enabledParams = computed<ParamDef[]>(() => {
    const all: ParamDef[] = Array.isArray(settings.value.params) ? settings.value.params : [];
    return all.filter(p => p.enabled !== false && matchesSpecies(p.species, payload.value.species));
});

function matchesSpecies(speciesList: string[] | undefined, current: string): boolean {
    if (!Array.isArray(speciesList)) return true;
    if (speciesList.length === 0) return false;
    return speciesList.includes(current);
}

function normForSpecies(paramKey: string): NormRange | null {
    const norms = settings.value.norms;
    if (!norms || typeof norms !== 'object') return null;
    const speciesNorms = (norms as Record<string, any>)[payload.value.species];
    if (!speciesNorms || typeof speciesNorms !== 'object') return null;
    return speciesNorms[paramKey] ?? null;
}

function valueStatus(paramKey: string, value: string | undefined): 'low' | 'high' | 'normal' | null {
    if (!value) return null;
    const norm = normForSpecies(paramKey);
    if (!norm) return null;
    const num = parseFloat(value);
    if (isNaN(num)) return null;
    if (norm.min !== null && num < norm.min) return 'low';
    if (norm.max !== null && num > norm.max) return 'high';
    return 'normal';
}

function statusClass(status: ReturnType<typeof valueStatus>): string {
    if (status === 'low') return 'text-blue-700 dark:text-blue-400';
    if (status === 'high') return 'text-red-700 dark:text-red-400';
    return '';
}

function statusIcon(status: ReturnType<typeof valueStatus>): string {
    if (status === 'low') return '↓';
    if (status === 'high') return '↑';
    return '';
}

const filledParams = computed(() =>
    enabledParams.value.filter(p => payload.value.params[p.key] !== undefined && payload.value.params[p.key] !== '')
);

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR');
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
                    <p class="text-xs uppercase text-muted-foreground">Eleveur</p>
                    <p class="font-medium">{{ analysis.breeder.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ analysis.breeder.postal_code }} {{ analysis.breeder.city }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Animal / Espece</p>
                    <p class="font-medium">{{ analysis.animal_nom ?? '-' }}</p>
                    <p class="text-sm text-muted-foreground">{{ payload.species }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Dates</p>
                    <p class="text-sm">Prel. : {{ formatDate(analysis.sampled_at) }}</p>
                    <p class="text-sm">Anal. : {{ formatDate(analysis.analyzed_at) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Intervenant</p>
                    <p class="font-medium">{{ analysis.intervenant ?? '-' }}</p>
                    <p v-if="payload.sample_nature" class="text-xs text-muted-foreground">{{ payload.sample_nature }}</p>
                </div>
            </section>

            <section v-if="filledParams.length > 0" class="min-w-0 rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">Resultats biochimiques</p>
                <table class="w-full text-sm">
                    <thead class="hidden sm:table-header-group">
                        <tr class="border-b border-border text-xs text-muted-foreground">
                            <th class="py-1 pr-4 text-left font-medium">Parametre</th>
                            <th class="py-1 pr-4 text-right font-medium">Valeur</th>
                            <th class="py-1 pr-4 text-left font-medium">Normes</th>
                            <th class="py-1 text-left font-medium">Unite</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="param in filledParams" :key="param.key" class="border-b border-border/50">
                            <td class="py-1.5 pr-4 text-xs font-medium text-muted-foreground">
                                {{ param.label }}
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 font-normal sm:hidden" :class="statusClass(valueStatus(param.key, payload.params[param.key]))">
                                    <span class="font-semibold tabular-nums">{{ payload.params[param.key] }}{{ statusIcon(valueStatus(param.key, payload.params[param.key])) }}</span>
                                    <span class="text-muted-foreground">
                                        <template v-if="normForSpecies(param.key)">
                                            <span v-if="normForSpecies(param.key)!.min !== null">{{ normForSpecies(param.key)!.min }}</span>
                                            <span v-if="normForSpecies(param.key)!.min !== null && normForSpecies(param.key)!.max !== null"> – </span>
                                            <span v-if="normForSpecies(param.key)!.max !== null">{{ normForSpecies(param.key)!.max }}</span>
                                        </template>
                                        <span v-else>–</span>
                                        {{ normForSpecies(param.key)?.unit ?? '' }}
                                    </span>
                                </div>
                            </td>
                            <td :class="['hidden py-1.5 pr-4 text-right font-semibold tabular-nums sm:table-cell', statusClass(valueStatus(param.key, payload.params[param.key]))]">
                                {{ payload.params[param.key] }}
                                <span class="text-xs">{{ statusIcon(valueStatus(param.key, payload.params[param.key])) }}</span>
                            </td>
                            <td class="hidden py-1.5 pr-4 text-xs text-muted-foreground sm:table-cell">
                                <template v-if="normForSpecies(param.key)">
                                    <span v-if="normForSpecies(param.key)!.min !== null">{{ normForSpecies(param.key)!.min }}</span>
                                    <span v-if="normForSpecies(param.key)!.min !== null && normForSpecies(param.key)!.max !== null"> – </span>
                                    <span v-if="normForSpecies(param.key)!.max !== null">{{ normForSpecies(param.key)!.max }}</span>
                                </template>
                                <span v-else>–</span>
                            </td>
                            <td class="hidden py-1.5 text-xs text-muted-foreground sm:table-cell">{{ normForSpecies(param.key)?.unit ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section v-if="payload.commemoratifs || payload.commentaires" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <div v-if="payload.commemoratifs" class="mb-3">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commemoratifs</p>
                    <p class="text-sm">{{ payload.commemoratifs }}</p>
                </div>
                <div v-if="payload.commentaires">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commentaires</p>
                    <div class="text-sm" v-html="String(payload.commentaires || '')"></div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
