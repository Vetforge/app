<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index as plansIndex, show as planShow } from '@/actions/App/Http/Controllers/PlanRationnementController';
import RationWorkspace from '@/components/rations/RationWorkspace.vue';
import type { Aliment, Plan, Ration, RationNormesPayload, Resultats } from '@/components/rations/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    plan: Plan;
    ration: Ration;
    aliments_disponibles: Aliment[];
    resultats: Resultats;
    iterations_volonte: number;
    normes: RationNormesPayload;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Plans', href: plansIndex() },
    { title: props.plan.nom, href: planShow({ plan: props.plan.id }).url },
    { title: props.ration.nom, href: '#' },
    { title: 'Composition', href: '#' },
];
</script>

<template>
    <Head :title="`Composition : ${ration.nom}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <RationWorkspace
            :plan="plan"
            :ration="ration"
            :aliments_disponibles="aliments_disponibles"
            :resultats="resultats"
            :iterations_volonte="iterations_volonte"
            :normes="normes"
            active-view="composition"
        />
    </AppLayout>
</template>
