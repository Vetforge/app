<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    store as planStore,
    update as planUpdate,
    index as plansIndex,
} from '@/actions/App/Http/Controllers/PlanRationnementController';
import BreederSelectWithCreate from '@/components/BreederSelectWithCreate.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Plan {
    id: number;
    breeder_id: number | null;
    nom: string;
    date: string | null;
    inra: string;
}

interface BreederOption {
    id: number;
    name: string;
    city: string | null;
    herd_number: string | null;
}

const props = defineProps<{
    plan?: Plan;
    breeders: BreederOption[];
    quickBreederStoreUrl: string;
}>();

const isEdit = !!props.plan;
const selectedBreederId = ref<string | number>(props.plan?.breeder_id ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Plans', href: plansIndex() },
    { title: isEdit ? 'Modifier' : 'Nouveau plan', href: '#' },
];
</script>

<template>
    <Head :title="isEdit ? 'Modifier le plan' : 'Nouveau plan'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-xl p-6">
            <h1 class="mb-6 text-2xl font-bold text-foreground">
                {{
                    isEdit ? 'Modifier le plan' : 'Nouveau plan de rationnement'
                }}
            </h1>

            <Form
                v-bind="
                    isEdit
                        ? planUpdate.form({ plan: plan!.id })
                        : planStore.form()
                "
                :defaults="{
                    breeder_id: plan?.breeder_id ?? '',
                    nom: plan?.nom ?? '',
                    date: plan?.date ?? '',
                    inra: plan?.inra ?? '2018',
                }"
                #default="{ errors, processing }"
                class="flex flex-col gap-5"
            >
                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-sm font-medium text-foreground"
                        for="breeder_id"
                    >
                        Eleveur <span class="text-destructive">*</span>
                    </label>
                    <BreederSelectWithCreate
                        v-model="selectedBreederId"
                        input-id="breeder_id"
                        name="breeder_id"
                        :breeders="breeders"
                        :create-url="quickBreederStoreUrl"
                        required
                    />
                    <InputError :message="errors.breeder_id" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-sm font-medium text-foreground"
                        for="nom"
                    >
                        Nom du plan <span class="text-destructive">*</span>
                    </label>
                    <input
                        id="nom"
                        name="nom"
                        type="text"
                        :value="plan?.nom ?? ''"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        placeholder="Ex : Troupeau vaches laitières printemps 2024"
                        required
                    />
                    <InputError :message="errors.nom" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-sm font-medium text-foreground"
                        for="date"
                        >Date <span class="text-destructive">*</span></label
                    >
                    <input
                        id="date"
                        name="date"
                        type="date"
                        :value="plan?.date ?? ''"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        required
                    />
                    <InputError :message="errors.date" />
                </div>

                <div v-if="!isEdit" class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-foreground">
                        Référentiel INRA <span class="text-destructive">*</span>
                    </label>
                    <p class="text-xs text-muted-foreground">
                        Le référentiel ne peut pas être modifié après la
                        création du plan.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-4 hover:border-primary/50 has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                        >
                            <input
                                type="radio"
                                name="inra"
                                value="2018"
                                checked
                                class="mt-0.5 accent-primary"
                            />
                            <div>
                                <p class="font-medium text-foreground">
                                    INRA 2018
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Dernières équations INRA — recommandé
                                </p>
                            </div>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-4 hover:border-primary/50 has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                        >
                            <input
                                type="radio"
                                name="inra"
                                value="2007"
                                class="mt-0.5 accent-primary"
                            />
                            <div>
                                <p class="font-medium text-foreground">
                                    INRA 2007
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Référentiel précédent
                                </p>
                            </div>
                        </label>
                    </div>
                    <InputError :message="errors.inra" />
                </div>

                <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{
                            processing
                                ? 'Enregistrement…'
                                : isEdit
                                  ? 'Mettre à jour'
                                  : 'Créer le plan'
                        }}
                    </button>
                    <a
                        :href="plansIndex().url"
                        class="inline-flex justify-center rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                    >
                        Annuler
                    </a>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
