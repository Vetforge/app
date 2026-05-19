<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Activity, Biohazard, ChartScatter, ClipboardCheck, ClipboardList, Columns4, Droplets, FlaskConical, Leaf, NotebookPen, Salad, Scissors, Search, SquareActivity, TestTube2, Users, Worm, X, Zap } from 'lucide-vue-next';
import type { Component } from 'vue';
import { search as dashboardSearch } from '@/actions/App/Http/Controllers/DashboardController';
import { create as alimentCreate } from '@/actions/App/Http/Controllers/AlimentController';
import { create as planCreate } from '@/actions/App/Http/Controllers/PlanRationnementController';
import { create as analysisCreate } from '@/actions/App/Http/Controllers/VeterinaryAnalysisController';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface AnalysisModule {
    slug: string;
    label: string;
    short_label: string;
    description: string;
    type: string;
}

interface RecentElement {
    type: 'analysis' | 'breeder' | 'plan' | 'ration' | 'aliment';
    type_label: string;
    id: number;
    label: string;
    sub: string | null;
    updated_at: string;
    url: string;
}

const props = defineProps<{
    analysis_modules: AnalysisModule[];
    recent_elements: RecentElement[];
    recent_next_cursor: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Accueil', href: dashboard() }];

const analyseModules = computed(() => props.analysis_modules.filter((m) => m.type === 'analyse'));
const rapportModules = computed(() => props.analysis_modules.filter((m) => m.type === 'rapport'));

const moduleIconMap: Record<string, Component> = {
    'coproscopie-parasitaire': Worm,
    'diarrhee-neonatale': Columns4,
    'gaz-du-sang': SquareActivity,
    'comptage-cellulaire': Droplets,
    'diagnostic-bacteriologique': Biohazard,
    'analyse-diverse': TestTube2,
    'tests-rapides': Zap,
    'tests-biochimie': FlaskConical,
    hemogramme: ChartScatter,
    'bse-laitier': ClipboardCheck,
    'bse-allaitant': ClipboardCheck,
    autopsie: Scissors,
    'compte-rendu': NotebookPen,
};

function getModuleIcon(slug: string): Component {
    return moduleIconMap[slug] ?? Activity;
}

const query = ref('');
const searchResults = ref<RecentElement[]>([]);
const isSearching = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function clearSearch() {
    query.value = '';
    searchResults.value = [];
}

watch(query, (val) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    const trimmed = val.trim();
    if (trimmed.length < 2) {
        searchResults.value = [];
        return;
    }
    debounceTimer = setTimeout(async () => {
        isSearching.value = true;
        try {
            const res = await fetch(dashboardSearch({ query: { q: trimmed } }).url);
            searchResults.value = await res.json();
        } finally {
            isSearching.value = false;
        }
    }, 300);
});

onBeforeUnmount(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
    observer?.disconnect();
});

// Infinite scroll
const sentinelRef = ref<HTMLElement | null>(null);
const isLoadingMore = ref(false);
let observer: IntersectionObserver | null = null;

function loadMore() {
    if (!props.recent_next_cursor || isLoadingMore.value || isSearchActive.value) return;
    isLoadingMore.value = true;
    router.reload({
        only: ['recent_elements', 'recent_next_cursor'],
        data: { cursor: props.recent_next_cursor },
        preserveScroll: true,
        onFinish: () => {
            isLoadingMore.value = false;
        },
    });
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting) loadMore();
        },
        { rootMargin: '200px' },
    );
    if (sentinelRef.value) observer.observe(sentinelRef.value);
});

const isSearchActive = computed(() => query.value.trim().length >= 2);
const displayedElements = computed(() => (isSearchActive.value ? searchResults.value : props.recent_elements));

const typeIconMap = {
    analysis: Activity,
    breeder: Users,
    plan: ClipboardList,
    ration: Salad,
    aliment: Leaf,
};

function getTypeIcon(type: string) {
    return typeIconMap[type as keyof typeof typeIconMap] ?? Activity;
}

function typeBadgeClass(type: string): string {
    const map: Record<string, string> = {
        analysis: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
        breeder: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
        plan: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        ration: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        aliment: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
    };
    return map[type] ?? 'bg-muted text-muted-foreground';
}

function typeIconBgClass(type: string): string {
    const map: Record<string, string> = {
        analysis: 'bg-violet-100 text-violet-600 dark:bg-violet-900/20 dark:text-violet-400',
        breeder: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400',
        plan: 'bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400',
        ration: 'bg-amber-100 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400',
        aliment: 'bg-teal-100 text-teal-600 dark:bg-teal-900/20 dark:text-teal-400',
    };
    return map[type] ?? 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Accueil" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-5">
            <!-- Actions rapides -->
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="flex flex-col gap-3">
                    <div class="flex items-start gap-3">
                        <span class="mt-1.5 w-20 shrink-0 text-right text-xs font-medium text-muted-foreground">Ration</span>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                :href="planCreate().url"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition-colors hover:bg-accent"
                            >
                                <ClipboardList class="size-3.5" />
                                Plan de rationnement
                            </Link>
                            <Link
                                :href="alimentCreate().url"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition-colors hover:bg-accent"
                            >
                                <Salad class="size-3.5" />
                                Aliment
                            </Link>
                        </div>
                    </div>

                    <div v-if="analyseModules.length" class="flex items-start gap-3">
                        <span class="mt-1.5 w-20 shrink-0 text-right text-xs font-medium text-muted-foreground">Analyses</span>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="module in analyseModules"
                                :key="module.slug"
                                :href="analysisCreate({ module: module.slug }).url"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition-colors hover:bg-accent"
                            >
                                <component :is="getModuleIcon(module.slug)" class="size-3.5" />
                                {{ module.short_label }}
                            </Link>
                        </div>
                    </div>

                    <div v-if="rapportModules.length" class="flex items-start gap-3">
                        <span class="mt-1.5 w-20 shrink-0 text-right text-xs font-medium text-muted-foreground">Rapports</span>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="module in rapportModules"
                                :key="module.slug"
                                :href="analysisCreate({ module: module.slug }).url"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground shadow-sm transition-colors hover:bg-accent"
                            >
                                <component :is="getModuleIcon(module.slug)" class="size-3.5" />
                                {{ module.short_label }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche -->
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="query"
                    type="text"
                    placeholder="Rechercher un éleveur, une analyse, une ration..."
                    class="w-full rounded-lg border border-border bg-card py-2.5 pl-9 pr-9 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                />
                <button
                    v-if="query"
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="clearSearch"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Liste des éléments -->
            <div class="rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                    <h2 class="font-semibold text-foreground">
                        {{ isSearchActive ? 'Résultats' : 'Eléments récents' }}
                    </h2>
                    <span v-if="isSearchActive && !isSearching" class="text-xs text-muted-foreground">
                        {{ searchResults.length }} résultat{{ searchResults.length !== 1 ? 's' : '' }}
                    </span>
                    <span v-else-if="isSearching" class="text-xs text-muted-foreground">Recherche...</span>
                </div>

                <!-- Skeleton de chargement -->
                <div v-if="isSearching" class="divide-y divide-border/50">
                    <div v-for="i in 4" :key="i" class="flex animate-pulse items-center gap-3 px-4 py-2.5">
                        <div class="size-8 shrink-0 rounded-full bg-muted"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="h-3 w-2/5 rounded bg-muted"></div>
                            <div class="h-2.5 w-1/4 rounded bg-muted"></div>
                        </div>
                        <div class="h-5 w-14 shrink-0 rounded-full bg-muted"></div>
                    </div>
                </div>

                <div v-else-if="displayedElements.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                    {{ isSearchActive ? 'Aucun résultat pour cette recherche' : 'Aucun élément récent' }}
                </div>

                <div v-else class="divide-y divide-border/50">
                    <Link
                        v-for="element in displayedElements"
                        :key="`${element.type}-${element.id}`"
                        :href="element.url"
                        class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-accent/50"
                    >
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-full"
                            :class="typeIconBgClass(element.type)"
                        >
                            <component :is="getTypeIcon(element.type)" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-foreground">{{ element.label }}</p>
                            <p v-if="element.sub" class="truncate text-xs text-muted-foreground">{{ element.sub }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="typeBadgeClass(element.type)"
                            >
                                {{ element.type_label }}
                            </span>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ new Date(element.updated_at).toLocaleDateString('fr-FR') }}
                            </p>
                        </div>
                    </Link>

                    <!-- Skeleton chargement suivant -->
                    <template v-if="isLoadingMore && !isSearchActive">
                        <div v-for="i in 3" :key="`skel-${i}`" class="flex animate-pulse items-center gap-3 px-4 py-2.5">
                            <div class="size-8 shrink-0 rounded-full bg-muted"></div>
                            <div class="flex-1 space-y-1.5">
                                <div class="h-3 w-2/5 rounded bg-muted"></div>
                                <div class="h-2.5 w-1/4 rounded bg-muted"></div>
                            </div>
                            <div class="h-5 w-14 shrink-0 rounded-full bg-muted"></div>
                        </div>
                    </template>
                </div>

                <!-- Sentinel pour l'infinite scroll -->
                <div v-if="!isSearchActive" ref="sentinelRef" class="h-px"></div>
            </div>
        </div>
    </AppLayout>
</template>
