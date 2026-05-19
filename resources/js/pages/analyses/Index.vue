<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { Edit, FileText, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import {
    create as analysisCreate,
    destroy as analysisDestroy,
    edit as analysisEdit,
    index as analysesIndex,
    pdf as analysisPdf,
    show as analysisShow,
} from '@/actions/App/Http/Controllers/VeterinaryAnalysisController';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface ModuleInfo {
    slug: string;
    label: string;
    short_label: string;
    description: string;
    type: string;
}

interface AnalysisRow {
    id: number;
    breeder: { name: string; city: string | null; herd_number: string | null };
    animal_nom: string | null;
    sampled_at: string | null;
    analyzed_at: string | null;
    intervenant: string | null;
    results: Record<string, unknown> | null;
}

interface PaginatedAnalyses {
    data: AnalysisRow[];
    total: number;
}

const props = defineProps<{
    module: ModuleInfo;
    modules: ModuleInfo[];
    analyses: PaginatedAnalyses;
    filters: { search?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    {
        title: props.module.short_label,
        href: analysesIndex({ module: props.module.slug }).url,
    },
];

const search = ref(props.filters.search ?? '');
const tableStartElement = ref<HTMLElement | null>(null);
const tableEndElement = ref<HTMLElement | null>(null);
let searchTimeout: ReturnType<typeof setTimeout>;

const getTableStartElement = () => tableStartElement.value;
const getTableEndElement = () => tableEndElement.value;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            analysesIndex({ module: props.module.slug }).url,
            { search: value },
            { preserveState: true, replace: true, reset: ['analyses'] },
        );
    }, 350);
});

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('fr-FR');
}

function deleteAnalysis(analysis: AnalysisRow): void {
    if (confirm('Supprimer cette analyse ?')) {
        router.delete(analysisDestroy({ analysis: analysis.id }).url);
    }
}
</script>

<template>
    <Head :title="module.label" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-3 sm:gap-6 sm:p-6">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="border-l-4 border-brand-orange pl-4">
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ module.label }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ module.description }}
                    </p>
                </div>
                <Link
                    :href="analysisCreate({ module: module.slug }).url"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                >
                    <Plus class="size-4" />
                    {{
                        module.type === 'rapport'
                            ? 'Nouveau rapport'
                            : 'Nouvelle analyse'
                    }}
                </Link>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="item in modules"
                    :key="item.slug"
                    :href="analysesIndex({ module: item.slug }).url"
                    class="rounded-lg border px-3 py-2 text-sm"
                    :class="
                        item.slug === module.slug
                            ? 'border-brand-orange bg-brand-orange-soft text-primary shadow-sm'
                            : 'border-border text-muted-foreground hover:border-primary/30 hover:bg-accent'
                    "
                >
                    {{ item.short_label }}
                </Link>
            </div>

            <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-center">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Rechercher par eleveur, animal, intervenant, ville, cheptel ou date..."
                        class="h-10 w-full rounded-lg border border-border bg-background py-2 pr-3 pl-10 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ analyses.total }} élément{{
                        analyses.total > 1 ? 's' : ''
                    }}
                </p>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-t-4 border-border border-t-brand-orange bg-card shadow-sm"
            >
                <div ref="tableStartElement" class="h-px" />
                <table class="w-full text-sm">
                    <thead class="hidden border-b border-border bg-muted/50 sm:table-header-group">
                        <tr>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Eleveur
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Animal
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Dates
                            </th>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Intervenant
                            </th>
                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <InfiniteScroll
                        data="analyses"
                        as="tbody"
                        :buffer="400"
                        :start-element="getTableStartElement"
                        :end-element="getTableEndElement"
                        only-next
                        class="divide-y divide-border/50"
                    >
                        <template #default="{ loadingNext }">
                            <tr
                                v-for="analysis in analyses.data"
                                :key="analysis.id"
                                class="hover:bg-accent/20"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="
                                            analysisShow({
                                                analysis: analysis.id,
                                            }).url
                                        "
                                        class="font-medium text-foreground hover:underline"
                                    >
                                        {{ analysis.breeder.name }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        {{ analysis.breeder.city ?? '' }}
                                    </p>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground sm:hidden">
                                        <span v-if="analysis.animal_nom">{{ analysis.animal_nom }}</span>
                                        <span>{{ formatDate(analysis.analyzed_at) }}</span>
                                        <span v-if="analysis.intervenant">{{ analysis.intervenant }}</span>
                                        <div class="flex gap-1">
                                            <Link
                                                :href="analysisEdit({ analysis: analysis.id }).url"
                                                class="rounded p-1 text-muted-foreground hover:bg-accent"
                                                title="Modifier"
                                            >
                                                <Edit class="size-3.5" />
                                            </Link>
                                            <a
                                                :href="analysisPdf({ analysis: analysis.id }).url"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="rounded p-1 text-muted-foreground hover:bg-accent"
                                                title="PDF"
                                            >
                                                <FileText class="size-3.5" />
                                            </a>
                                            <button
                                                class="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                title="Supprimer"
                                                @click="deleteAnalysis(analysis)"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                                    {{ analysis.animal_nom ?? '-' }}
                                </td>
                                <td class="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                                    <p>Analyse : {{ formatDate(analysis.analyzed_at) }}</p>
                                    <p class="text-xs">Prelevement : {{ formatDate(analysis.sampled_at) }}</p>
                                </td>
                                <td class="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                                    {{ analysis.intervenant ?? '-' }}
                                </td>
                                <td class="hidden px-4 py-3 sm:table-cell">
                                    <div class="flex items-center justify-center gap-1">
                                        <Link
                                            :href="analysisEdit({ analysis: analysis.id }).url"
                                            class="rounded p-1.5 text-muted-foreground hover:bg-accent"
                                            title="Modifier"
                                        >
                                            <Edit class="size-4" />
                                        </Link>
                                        <a
                                            :href="analysisPdf({ analysis: analysis.id }).url"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="rounded p-1.5 text-muted-foreground hover:bg-accent"
                                            title="PDF"
                                        >
                                            <FileText class="size-4" />
                                        </a>
                                        <button
                                            class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            title="Supprimer"
                                            @click="deleteAnalysis(analysis)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="analyses.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    {{
                                        search
                                            ? 'Aucune analyse ne correspond a cette recherche.'
                                            : 'Aucune analyse pour ce module.'
                                    }}
                                </td>
                            </tr>
                            <template v-if="loadingNext">
                                <tr
                                    v-for="item in 3"
                                    :key="`loading-analysis-${item}`"
                                    class="animate-pulse"
                                >
                                    <td class="px-4 py-3">
                                        <div class="h-4 w-32 rounded bg-muted" />
                                        <div class="mt-2 h-3 w-20 rounded bg-muted/70" />
                                    </td>
                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        <div class="h-4 w-24 rounded bg-muted" />
                                    </td>
                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        <div class="h-4 w-28 rounded bg-muted" />
                                        <div class="mt-2 h-3 w-24 rounded bg-muted/70" />
                                    </td>
                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        <div class="h-4 w-28 rounded bg-muted" />
                                    </td>
                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        <div class="mx-auto h-7 w-24 rounded bg-muted" />
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </InfiniteScroll>
                </table>
                <div ref="tableEndElement" class="h-px" />
            </div>
        </div>
    </AppLayout>
</template>
