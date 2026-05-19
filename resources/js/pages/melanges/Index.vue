<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
];
</script>

<template>
    <Head title="Mélanges" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 text-center text-muted-foreground">
            Les mélanges sont désormais créés directement dans les rations.
        </div>
    </AppLayout>
</template>
