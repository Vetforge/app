<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    ClipboardList,
    Edit,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import {
    create as planCreate,
    destroy as planDestroy,
    edit as planEdit,
    index as plansIndex,
    show as planShow,
} from '@/actions/App/Http/Controllers/PlanRationnementController';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Plan {
    id: number;
    nom: string;
    date: string | null;
    inra: string | null;
    rations_count: number;
    updated_at: string;
    breeder: {
        name: string;
        city: string | null;
        herd_number: string | null;
    } | null;
}

interface PaginatedPlans {
    data: Plan[];
    total: number;
}

const props = defineProps<{
    plans: PaginatedPlans;
    filters: { search?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Plans de rationnement', href: plansIndex() },
];

const search = ref(props.filters.search ?? '');
let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            plansIndex().url,
            { search: value },
            { preserveState: true, replace: true, reset: ['plans'] },
        );
    }, 350);
});

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('fr-FR');
}

function deletePlan(plan: Plan): void {
    if (confirm(`Supprimer le plan "${plan.nom}" et toutes ses rations ?`)) {
        router.delete(planDestroy({ plan: plan.id }).url);
    }
}
</script>

<template>
    <Head title="Plans de rationnement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <h1 class="text-2xl font-bold text-foreground">
                    Plans de rationnement
                </h1>
                <Link
                    :href="planCreate().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 sm:w-auto"
                >
                    <Plus class="size-4" />
                    Nouveau plan
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
                        placeholder="Rechercher par plan, eleveur, ville, cheptel, date ou INRA..."
                        class="h-10 w-full rounded-lg border border-border bg-background py-2 pr-3 pl-10 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ plans.total }} plan{{ plans.total !== 1 ? 's' : '' }}
                </p>
            </div>

            <div
                v-if="plans.data.length === 0"
                class="rounded-xl border border-dashed border-border p-12 text-center"
            >
                <ClipboardList
                    class="mx-auto mb-4 size-12 text-muted-foreground/50"
                />
                <p class="text-muted-foreground">
                    {{
                        search
                            ? 'Aucun plan ne correspond a cette recherche.'
                            : 'Aucun plan de rationnement.'
                    }}
                </p>
                <Link
                    v-if="!search"
                    :href="planCreate().url"
                    class="mt-4 inline-flex items-center gap-2 text-sm text-primary hover:underline"
                >
                    Créer votre premier plan
                </Link>
            </div>

            <InfiniteScroll
                v-else
                data="plans"
                as="div"
                :buffer="400"
                only-next
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <template #default="{ loadingNext }">
                    <div
                        v-for="plan in plans.data"
                        :key="plan.id"
                        class="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 shadow-sm transition hover:border-primary/50 hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2
                                    class="text-base font-semibold text-card-foreground"
                                >
                                    {{ plan.nom }}
                                </h2>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                                    >
                                        INRA {{ plan.inra ?? '-' }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ plan.rations_count }} ration{{
                                            plan.rations_count !== 1 ? 's' : ''
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Link
                                    :href="planEdit({ plan: plan.id }).url"
                                    class="rounded p-1 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                    title="Modifier"
                                >
                                    <Edit class="size-4" />
                                </Link>
                                <button
                                    class="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    title="Supprimer"
                                    @click="deletePlan(plan)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="plan.breeder"
                            class="rounded-lg bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                        >
                            <p class="font-medium text-foreground">
                                {{ plan.breeder.name }}
                            </p>
                            <p>
                                {{
                                    plan.breeder.city ?? 'Ville non renseignee'
                                }}
                                <span v-if="plan.breeder.herd_number">
                                    - {{ plan.breeder.herd_number }}
                                </span>
                            </p>
                        </div>

                        <div
                            v-if="plan.date"
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <CalendarDays class="size-3.5" />
                            {{ formatDate(plan.date) }}
                        </div>

                        <Link
                            :href="planShow({ plan: plan.id }).url"
                            class="mt-auto inline-flex items-center justify-center rounded-lg border border-border bg-background px-3 py-1.5 text-sm font-medium text-foreground hover:bg-accent"
                        >
                            Voir les rations
                        </Link>
                    </div>

                    <div
                        v-for="item in loadingNext ? 3 : 0"
                        :key="`loading-plan-${item}`"
                        class="h-44 animate-pulse rounded-xl border border-border bg-muted/30"
                    />
                </template>
            </InfiniteScroll>
        </div>
    </AppLayout>
</template>
