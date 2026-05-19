<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Trash2, ClipboardList, Settings } from 'lucide-vue-next';
import {
    index as plansIndex,
    show as planShow,
} from '@/actions/App/Http/Controllers/PlanRationnementController';
import {
    create as rationCreate,
    destroy as rationDestroy,
} from '@/actions/App/Http/Controllers/RationController';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Ration {
    id: number;
    nom: string;
    categorie_animal: string | null;
    lait_objectif: number | null;
    updated_at: string;
}

interface Plan {
    id: number;
    nom: string;
    inra: string;
    date: string | null;
    rations: Ration[];
}

const props = defineProps<{
    plan: Plan;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Plans', href: plansIndex() },
    { title: props.plan.nom, href: planShow({ plan: props.plan.id }).url },
];

function deleteRation(ration: Ration) {
    if (confirm(`Supprimer la ration "${ration.nom}" ?`)) {
        router.delete(
            rationDestroy({ plan: props.plan.id, ration: ration.id }).url,
        );
    }
}
</script>

<template>
    <Head :title="`Plan : ${plan.nom}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ plan.nom }}
                    </h1>
                    <span
                        class="mt-1 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                    >
                        INRA {{ plan.inra }}
                    </span>
                </div>
                <Link
                    :href="rationCreate({ plan: plan.id }).url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 sm:w-auto"
                >
                    <Plus class="size-4" />
                    Nouvelle ration
                </Link>
            </div>

            <div
                v-if="plan.rations.length === 0"
                class="rounded-xl border border-dashed border-border p-12 text-center"
            >
                <ClipboardList
                    class="mx-auto mb-4 size-12 text-muted-foreground/50"
                />
                <p class="text-muted-foreground">Aucune ration dans ce plan.</p>
            </div>

            <div
                v-else
                class="flex flex-col divide-y divide-border rounded-xl border border-border bg-card shadow-sm"
            >
                <div
                    v-for="ration in plan.rations"
                    :key="ration.id"
                    class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-accent/30"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-card-foreground">
                            {{ ration.nom }}
                        </p>
                        <p
                            v-if="ration.categorie_animal"
                            class="text-xs text-muted-foreground"
                        >
                            {{ ration.categorie_animal }}
                            <span v-if="ration.lait_objectif">
                                · {{ ration.lait_objectif }} kg lait/j</span
                            >
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <Link
                            :href="`/plans/${plan.id}/rations/${ration.id}/description`"
                            class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                            title="Paramètres"
                        >
                            <Settings class="size-4" />
                        </Link>
                        <Link
                            :href="`/plans/${plan.id}/rations/${ration.id}/composition`"
                            class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                            title="Composition"
                        >
                            <ClipboardList class="size-4" />
                        </Link>
                        <button
                            @click="deleteRation(ration)"
                            class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            title="Supprimer"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
