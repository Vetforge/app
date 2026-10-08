<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { ClipboardList, Edit, Search, Wheat, X } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import type { Component } from 'vue';
import { edit as alimentEdit } from '@/actions/App/Http/Controllers/AlimentController';
import {
    edit as breederEdit,
    index as breedersIndex,
    show as breederShow,
} from '@/actions/App/Http/Controllers/BreederController';
import { composition as rationComposition } from '@/actions/App/Http/Controllers/RationController';
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
    rations_count: number;
    aliments_count: number;
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

interface RationRow {
    id: number;
    nom: string;
    plan_rationnement_id: number;
    plan_rationnement: { id: number; nom: string };
    updated_at: string;
}

interface AlimentRow {
    id: number;
    libelle0: string | null;
    libelle1: string | null;
    libelle2: string | null;
    libelle3: string | null;
    libelle4: string | null;
    type: string | null;
    usage_aliment: number | null;
    updated_at: string;
}

interface PaginatedRows<T> {
    data: T[];
    total: number;
}

type SectionKey = 'analyses' | 'rations' | 'aliments';

interface DossierRow {
    id: number;
    label: string;
    sub: string | null;
    date: string;
    url: string;
    badge: string;
    type: string;
    icon: Component;
}

const props = defineProps<{
    breeder: BreederInfo;
    analyses: PaginatedRows<AnalysisRow>;
    rations: PaginatedRows<RationRow>;
    aliments: PaginatedRows<AlimentRow>;
    filters: { search?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Eleveurs', href: breedersIndex() },
    {
        title: props.breeder.name,
        href: breederShow({ breeder: props.breeder.id }),
    },
];

const search = ref(props.filters.search ?? '');
const activeSection = ref<SectionKey>('analyses');
const sections = computed(() => [
    {
        key: 'analyses' as const,
        label: 'Analyses et comptes rendus',
        count: props.breeder.analyses_count,
    },
    {
        key: 'rations' as const,
        label: 'Rations',
        count: props.breeder.rations_count,
    },
    {
        key: 'aliments' as const,
        label: 'Aliments',
        count: props.breeder.aliments_count,
    },
]);
const currentSection = computed(
    () =>
        sections.value.find((section) => section.key === activeSection.value)!,
);
const currentTotal = computed(() => props[activeSection.value].total);
const rows = computed<DossierRow[]>(() => {
    if (activeSection.value === 'rations') {
        return props.rations.data.map((ration) => ({
            id: ration.id,
            label: ration.nom,
            sub: ration.plan_rationnement.nom,
            date: ration.updated_at,
            url: rationComposition({
                plan: ration.plan_rationnement_id,
                ration: ration.id,
            }).url,
            badge: 'Ration',
            type: 'ration',
            icon: ClipboardList,
        }));
    }

    if (activeSection.value === 'aliments') {
        return props.aliments.data.map((aliment) => ({
            id: aliment.id,
            label:
                aliment.libelle0 ||
                aliment.libelle1 ||
                aliment.libelle2 ||
                aliment.libelle3 ||
                aliment.libelle4 ||
                'Aliment',
            sub:
                [
                    aliment.usage_aliment === 2 ? 'Aliment de ration' : null,
                    aliment.libelle1,
                    aliment.libelle2,
                    aliment.libelle3,
                    aliment.libelle4,
                ]
                    .filter(Boolean)
                    .join(' · ') || null,
            date: aliment.updated_at,
            url: alimentEdit({ aliment: aliment.id }).url,
            badge: aliment.type || 'Aliment',
            type: 'aliment',
            icon: Wheat,
        }));
    }

    return props.analyses.data.map((analysis) => ({
        id: analysis.id,
        label: analysis.label,
        sub: analysis.sub,
        date: analysis.analyzed_at,
        url: analysis.url,
        badge: analysis.module_label,
        type: analysis.module_type,
        icon: getModuleIcon(analysis.module),
    }));
});
const emptyMessage = computed(() => {
    if (search.value) {
        return 'Aucun élément ne correspond à cette recherche';
    }

    return {
        analyses: 'Aucune analyse ni compte rendu pour cet éleveur',
        rations: 'Aucune ration pour cet éleveur',
        aliments: 'Aucun aliment pour cet éleveur',
    }[activeSection.value];
});
let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            breederShow({ breeder: props.breeder.id }).url,
            { search: value },
            {
                preserveState: true,
                replace: true,
                reset: ['analyses', 'rations', 'aliments'],
            },
        );
    }, 350);
});

onUnmounted(() => clearTimeout(searchTimeout));

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
    if (type === 'ration') {
        return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300';
    }

    if (type === 'aliment') {
        return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
    }

    return type === 'rapport'
        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
        : 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300';
}

function moduleIconBgClass(type: string): string {
    if (type === 'ration') {
        return 'bg-orange-100 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400';
    }

    if (type === 'aliment') {
        return 'bg-green-100 text-green-600 dark:bg-green-900/20 dark:text-green-400';
    }

    return type === 'rapport'
        ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
        : 'bg-violet-100 text-violet-600 dark:bg-violet-900/20 dark:text-violet-400';
}
</script>

<template>
    <Head :title="breeder.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-5">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ breeder.name }}
                    </h1>
                    <p v-if="contactLine" class="text-sm text-muted-foreground">
                        {{ contactLine }}
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

            <div
                class="flex flex-wrap gap-2"
                aria-label="Éléments de l'éleveur"
            >
                <button
                    v-for="section in sections"
                    :key="section.key"
                    type="button"
                    :data-section="section.key"
                    :aria-pressed="activeSection === section.key"
                    class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                    :class="
                        activeSection === section.key
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border text-foreground hover:bg-accent'
                    "
                    @click="activeSection = section.key"
                >
                    {{ section.label }} ({{ section.count }})
                </button>
            </div>

            <!-- Barre de recherche -->
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="search"
                    type="text"
                    name="search"
                    aria-label="Rechercher dans le dossier de l'éleveur"
                    placeholder="Rechercher une analyse, un compte rendu, une ration ou un aliment..."
                    class="w-full rounded-lg border border-border bg-card py-2.5 pr-9 pl-9 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
                <button
                    v-if="search"
                    type="button"
                    aria-label="Effacer la recherche"
                    class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="clearSearch"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Éléments du dossier -->
            <div class="rounded-xl border border-border bg-card shadow-sm">
                <div
                    class="flex items-center justify-between border-b border-border px-4 py-2.5"
                >
                    <h2 class="font-semibold text-foreground">
                        {{ currentSection.label }}
                    </h2>
                    <span class="text-xs text-muted-foreground">
                        {{ currentTotal }} élément{{
                            currentTotal !== 1 ? 's' : ''
                        }}
                    </span>
                </div>

                <InfiniteScroll
                    :key="activeSection"
                    :data="activeSection"
                    :buffer="400"
                    preserve-url
                    data-testid="dossier-list"
                    class="divide-y divide-border/50"
                >
                    <template #default="{ loading }">
                        <div
                            v-if="rows.length === 0"
                            class="px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            {{ emptyMessage }}
                        </div>

                        <Link
                            v-for="row in rows"
                            :key="row.id"
                            :href="row.url"
                            class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-accent/50"
                        >
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full"
                                :class="moduleIconBgClass(row.type)"
                            >
                                <component :is="row.icon" class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium text-foreground"
                                >
                                    {{ row.label }}
                                </p>
                                <p
                                    v-if="row.sub"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ row.sub }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="moduleBadgeClass(row.type)"
                                >
                                    {{ row.badge }}
                                </span>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ formatDate(row.date) }}
                                </p>
                            </div>
                        </Link>

                        <!-- Skeleton chargement suivant -->
                        <template v-if="loading">
                            <div
                                v-for="i in 3"
                                :key="`skel-${i}`"
                                class="flex animate-pulse items-center gap-3 px-4 py-2.5"
                            >
                                <div
                                    class="size-8 shrink-0 rounded-full bg-muted"
                                ></div>
                                <div class="flex-1 space-y-1.5">
                                    <div
                                        class="h-3 w-2/5 rounded bg-muted"
                                    ></div>
                                    <div
                                        class="h-2.5 w-1/4 rounded bg-muted"
                                    ></div>
                                </div>
                                <div
                                    class="h-5 w-14 shrink-0 rounded-full bg-muted"
                                ></div>
                            </div>
                        </template>
                    </template>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>
</template>
