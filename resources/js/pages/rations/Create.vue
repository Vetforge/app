<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import {
    index as plansIndex,
    show as planShow,
} from '@/actions/App/Http/Controllers/PlanRationnementController';
import { store as rationStore } from '@/actions/App/Http/Controllers/RationController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Plan {
    id: number;
    nom: string;
    inra: string;
}

const props = defineProps<{
    plan: Plan;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Plans', href: plansIndex() },
    { title: props.plan.nom, href: planShow({ plan: props.plan.id }).url },
    { title: 'Nouvelle ration', href: '#' },
];
</script>

<template>
    <Head title="Nouvelle ration" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-md p-6">
            <h1 class="mb-6 text-2xl font-bold text-foreground">
                Nouvelle ration
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Dans le plan <strong>{{ plan.nom }}</strong> (INRA
                {{ plan.inra }})
            </p>

            <Form
                v-bind="rationStore.form({ plan: plan.id })"
                #default="{ errors, processing }"
                class="flex flex-col gap-5"
            >
                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-sm font-medium text-foreground"
                        for="nom"
                    >
                        Nom de la ration <span class="text-destructive">*</span>
                    </label>
                    <input
                        id="nom"
                        name="nom"
                        type="text"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        placeholder="Ex : Vaches laitières hautes productrices"
                        required
                    />
                    <InputError :message="errors.nom" />
                </div>

                <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ processing ? 'Création…' : 'Créer et configurer' }}
                    </button>
                    <a
                        :href="planShow({ plan: plan.id }).url"
                        class="inline-flex justify-center rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                    >
                        Annuler
                    </a>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
