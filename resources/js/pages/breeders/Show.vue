<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { Edit, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import {
    edit as breederEdit,
    index as breedersIndex,
    show as breederShow,
} from '@/actions/App/Http/Controllers/BreederController';
import AppLayout from '@/layouts/AppLayout.vue';
import { getModuleIcon } from '@/lib/moduleIcons';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface BreederInfo {
    id: number;
    name: string;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    phone: string | null;
    email: string | null;
    herd_number: string | null;
    analyses_count: number;
}

interface AnalysisRow {
    id: number;
    module: string;
    module_label: string;
    module_type: string;
    label: string;
    sub: string | null;
    analyzed_at: string;
    url: string;
}

interface PaginatedAnalyses {
    data: AnalysisRow[];
    total: number;
}

const props = defineProps<{
    breeder: BreederInfo;
    analyses: PaginatedAnalyses;
    filters: { search?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Eleveurs', href: breedersIndex() },
    { title: props.breeder.name, href: breederShow({ breeder: props.breeder.id }) },
];

const search = ref(props.filters.search ?? '');
let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            breederShow({ breeder: props.breeder.id }).url,
            { search: value },
            { preserveState: true, replace: true, reset: ['analyses'] },
        );
    }, 350);
});

function clearSearch(): void {
    search.value = '';
}

const contactLine = [
    [props.breeder.postal_code, props.breeder.city].filter(Boolean).join(' '),
    props.breeder.herd_number,
    props.breeder.phone ?? props.breeder.email,
]
    .filter(Boolean)
    .join(' · ');

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('fr-FR');
}

function moduleBadgeClass(type: string): string {
    return type === 'rapport'
        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
        : 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300';
}

function moduleIconBgClass(type: string): string {
    return type === 'rapport'
        ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
        : 'bg-violet-100 text-violet-600 dark:bg-violet-900/20 dark:text-violet-400';
}
</script>

<template>
    <Head :title="breeder.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ breeder.name }}</h1>
                    <p v-if="contactLine" class="text-sm text-muted-foreground">{{ contactLine }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ breeder.analyses_count }} analyse{{ breeder.analyses_count > 1 ? 's' : '' }}
                    </p>
                </div>
                <Link
                    :href="breederEdit({ breeder: breeder.id }).url"
                    class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                >
                    <Edit class="size-4" />
                    Modifier l'éleveur
                </Link>
            </div>

            <!-- Barre de recherche -->
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="search"
                    type="text"
                    name="search"
                    placeholder="Rechercher une analyse (module, animal, intervenant, date)..."
                    class="w-full rounded-lg border border-border bg-card py-2.5 pl-9 pr-9 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                />
                <button
                    v-if="search"
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="clearSearch"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Liste des analyses -->
            <div class="rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                    <h2 class="font-semibold text-foreground">
                        {{ search ? 'Résultats' : 'Analyses' }}
                    </h2>
                    <span class="text-xs text-muted-foreground">
                        {{ analyses.total }} élément{{ analyses.total !== 1 ? 's' : '' }}
                    </span>
                </div>

                <InfiniteScroll data="analyses" :buffer="400" only-next class="divide-y divide-border/50">
                    <template #default="{ loadingNext }">
                        <div v-if="analyses.data.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                            {{ search ? 'Aucune analyse ne correspond à cette recherche' : 'Aucune analyse pour cet éleveur' }}
                        </div>

                        <Link
                            v-for="analysis in analyses.data"
                            :key="analysis.id"
                            :href="analysis.url"
                            class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-accent/50"
                        >
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full"
                                :class="moduleIconBgClass(analysis.module_type)"
                            >
                                <component :is="getModuleIcon(analysis.module)" class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">{{ analysis.label }}</p>
                                <p v-if="analysis.sub" class="truncate text-xs text-muted-foreground">{{ analysis.sub }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="moduleBadgeClass(analysis.module_type)"
                                >
                                    {{ analysis.module_label }}
                                </span>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ formatDate(analysis.analyzed_at) }}
                                </p>
                            </div>
                        </Link>

                        <!-- Skeleton chargement suivant -->
                        <template v-if="loadingNext">
                            <div v-for="i in 3" :key="`skel-${i}`" class="flex animate-pulse items-center gap-3 px-4 py-2.5">
                                <div class="size-8 shrink-0 rounded-full bg-muted"></div>
                                <div class="flex-1 space-y-1.5">
                                    <div class="h-3 w-2/5 rounded bg-muted"></div>
                                    <div class="h-2.5 w-1/4 rounded bg-muted"></div>
                                </div>
                                <div class="h-5 w-14 shrink-0 rounded-full bg-muted"></div>
                            </div>
                        </template>
                    </template>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>
</template>
