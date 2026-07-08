<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

interface CategorieOption {
    value: string;
    label: string;
    disponible: boolean;
    est_laitiere: boolean;
    est_croissance: boolean;
    unite_encombrement: string;
    unite_fourragere: string;
}

interface EspeceGroup {
    espece: string;
    label: string;
    categories: CategorieOption[];
}

const props = defineProps<{
    plan: Plan;
    categorie_options: EspeceGroup[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Plans', href: plansIndex() },
    { title: props.plan.nom, href: planShow({ plan: props.plan.id }).url },
    { title: 'Nouvelle ration', href: '#' },
];

const especeIcons: Record<string, string> = {
    bovin: '🐄',
    ovin: '🐑',
    caprin: '🐐',
};

const is2018 = computed(() => String(props.plan.inra) === '2018');
const legacyBovins = ['vache_laitiere', 'vache_allaitante'];

// Une catégorie est sélectionnable si elle est implémentée ET compatible avec le référentiel du plan.
function isSelectable(option: CategorieOption): boolean {
    if (!option.disponible) {
        return false;
    }
    return is2018.value || legacyBovins.includes(option.value);
}

function unavailableReason(option: CategorieOption): string {
    if (!option.disponible) {
        return 'Bientôt';
    }
    if (!is2018.value && !legacyBovins.includes(option.value)) {
        return 'INRA 2018 requis';
    }
    return '';
}

// Espèce sélectionnée : par défaut celle qui contient une catégorie disponible (Bovins).
const firstSelectableEspece =
    props.categorie_options.find((group) =>
        group.categories.some((option) => isSelectable(option)),
    )?.espece ??
    props.categorie_options[0]?.espece ??
    'bovin';

const selectedEspece = ref<string>(firstSelectableEspece);

const form = useForm({
    nom: '',
    categorie_animal:
        props.categorie_options
            .flatMap((group) => group.categories)
            .find((option) => isSelectable(option))?.value ?? '',
});

const categoriesForEspece = computed<CategorieOption[]>(
    () =>
        props.categorie_options.find(
            (group) => group.espece === selectedEspece.value,
        )?.categories ?? [],
);

function selectEspece(espece: string): void {
    selectedEspece.value = espece;
    const firstAvailable = (
        props.categorie_options.find((group) => group.espece === espece)
            ?.categories ?? []
    ).find((option) => isSelectable(option));
    if (firstAvailable) {
        form.categorie_animal = firstAvailable.value;
    }
}

function selectCategorie(option: CategorieOption): void {
    if (!isSelectable(option)) {
        return;
    }
    form.categorie_animal = option.value;
}

function submit(): void {
    form.post(rationStore({ plan: props.plan.id }).url);
}
</script>

<template>
    <Head title="Nouvelle ration" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl p-6">
            <h1 class="mb-1 text-2xl font-bold text-foreground">
                Nouvelle ration
            </h1>
            <p class="mb-8 text-sm text-muted-foreground">
                Plan <strong>{{ plan.nom }}</strong> · référentiel INRA
                {{ plan.inra }}
            </p>

            <form class="flex flex-col gap-8" @submit.prevent="submit">
                <!-- Étape 1 : espèce -->
                <section>
                    <div class="mb-3 flex items-baseline gap-2">
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                            >1</span
                        >
                        <h2 class="font-semibold text-foreground">Espèce</h2>
                        <span class="text-sm text-muted-foreground"
                            >détermine tout le calcul de la ration</span
                        >
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <button
                            v-for="group in categorie_options"
                            :key="group.espece"
                            type="button"
                            :aria-pressed="selectedEspece === group.espece"
                            class="flex items-center gap-3 rounded-xl border p-4 text-left transition"
                            :class="
                                selectedEspece === group.espece
                                    ? 'border-primary bg-primary/5 ring-2 ring-primary'
                                    : 'border-border bg-card hover:bg-accent'
                            "
                            @click="selectEspece(group.espece)"
                        >
                            <span class="text-2xl">{{
                                especeIcons[group.espece]
                            }}</span>
                            <span class="font-medium text-foreground">{{
                                group.label
                            }}</span>
                        </button>
                    </div>
                </section>

                <!-- Étape 2 : catégorie -->
                <section>
                    <div class="mb-3 flex items-baseline gap-2">
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                            >2</span
                        >
                        <h2 class="font-semibold text-foreground">Catégorie</h2>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="option in categoriesForEspece"
                            :key="option.value"
                            type="button"
                            :disabled="!isSelectable(option)"
                            :aria-pressed="
                                form.categorie_animal === option.value
                            "
                            class="flex flex-col gap-2 rounded-xl border p-4 text-left transition"
                            :class="[
                                form.categorie_animal === option.value
                                    ? 'border-primary bg-primary/5 ring-2 ring-primary'
                                    : 'border-border bg-card',
                                isSelectable(option)
                                    ? 'cursor-pointer hover:bg-accent'
                                    : 'cursor-not-allowed opacity-55',
                            ]"
                            @click="selectCategorie(option)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-medium text-foreground">{{
                                    option.label
                                }}</span>
                                <span
                                    v-if="!isSelectable(option)"
                                    class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                    >{{ unavailableReason(option) }}</span
                                >
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    class="rounded bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground"
                                    >Encombrement
                                    {{ option.unite_encombrement }}</span
                                >
                                <span
                                    class="rounded bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground"
                                    >Énergie {{ option.unite_fourragere }}</span
                                >
                                <span
                                    v-if="option.est_laitiere"
                                    class="rounded bg-blue-500/15 px-1.5 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400"
                                    >Lait</span
                                >
                                <span
                                    v-if="option.est_croissance"
                                    class="rounded bg-emerald-500/15 px-1.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                    >Croissance</span
                                >
                            </div>
                        </button>
                    </div>
                    <InputError
                        class="mt-2"
                        :message="form.errors.categorie_animal"
                    />
                </section>

                <!-- Étape 3 : nom -->
                <section>
                    <div class="mb-3 flex items-baseline gap-2">
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                            >3</span
                        >
                        <h2 class="font-semibold text-foreground">
                            Nom de la ration
                        </h2>
                    </div>
                    <input
                        id="nom"
                        v-model="form.nom"
                        name="nom"
                        type="text"
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        placeholder="Ex : Vaches laitières hautes productrices"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.nom" />
                </section>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button
                        type="submit"
                        :disabled="form.processing || !form.categorie_animal"
                        class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Création…'
                                : 'Créer et configurer'
                        }}
                    </button>
                    <a
                        :href="planShow({ plan: plan.id }).url"
                        class="inline-flex justify-center rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                    >
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
