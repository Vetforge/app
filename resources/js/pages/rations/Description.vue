<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import {
    index as plansIndex,
    show as planShow,
} from '@/actions/App/Http/Controllers/PlanRationnementController';
import { updateDescription } from '@/actions/App/Http/Controllers/RationController';
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
    poids_defaut: number;
}

interface EspeceGroup {
    espece: string;
    label: string;
    categories: CategorieOption[];
}

interface Ration {
    id: number;
    nom: string;
    effectif: number | null;
    lait_potentiel305j: number | null;
    poids_vif: number | null;
    pourcentage_primipare: number | null;
    nec: number | null;
    tb_annuel: number | null;
    tp_annuel: number | null;
    activite: string | null;
    temperature_ambiante: number | null;
    nec_velage: number | null;
    ivv: number | null;
    poids_veau_naissance: number | null;
    age_velage: number | null;
    lait_objectif305j: number | null;
    stade_moyen: number | null;
    lait_objectif: number | null;
    is_ration_semi_complete: boolean | null;
    ecart_variation_reserve: number | null;
    strategie: string | null;
    lait_objectif_auge: number | null;
    race: string | null;
    mois_lactation: number | null;
    mois_gestation: number | null;
    categorie_animal: string | null;
    gmq: number | null;
    stade_physiologique: string | null;
    jours_gestation: number | null;
    jours_lactation: number | null;
    nombre_jeunes: number | null;
    poids_portee: number | null;
    gmq_portee: number | null;
    mfc: number | null;
    mpc: number | null;
    type_production_ovin: string | null;
}

const props = defineProps<{
    plan: Plan;
    ration: Ration;
    categorie_options: EspeceGroup[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Plans', href: plansIndex() },
    { title: props.plan.nom, href: planShow({ plan: props.plan.id }).url },
    { title: props.ration.nom, href: '#' },
    { title: 'Paramètres animal', href: '#' },
];

const is2018 = computed(() => String(props.plan.inra) === '2018');
const legacyBovins = ['vache_laitiere', 'vache_allaitante'];

const allOptions = computed<CategorieOption[]>(() =>
    props.categorie_options.flatMap((group) => group.categories),
);

function isSelectable(option: CategorieOption): boolean {
    if (!option.disponible) {
        return false;
    }
    return is2018.value || legacyBovins.includes(option.value);
}

const initialCategorie =
    allOptions.value.find((o) => o.value === props.ration.categorie_animal)
        ?.value ?? 'vache_laitiere';

function poidsDefautPourCategorie(value: string): number {
    return allOptions.value.find((o) => o.value === value)?.poids_defaut ?? 650;
}

const form = useForm({
    nom: props.ration.nom,
    categorie_animal: initialCategorie,
    race: props.ration.race ?? '',
    effectif: props.ration.effectif,
    poids_vif:
        props.ration.poids_vif ?? poidsDefautPourCategorie(initialCategorie),
    lait_potentiel305j: props.ration.lait_potentiel305j,
    lait_objectif: props.ration.lait_objectif,
    tb_annuel: props.ration.tb_annuel ?? 40,
    tp_annuel: props.ration.tp_annuel ?? 32,
    mfc: props.ration.mfc,
    mpc: props.ration.mpc,
    mois_lactation: props.ration.mois_lactation ?? 3,
    mois_gestation: props.ration.mois_gestation ?? 0,
    jours_lactation: props.ration.jours_lactation,
    jours_gestation: props.ration.jours_gestation,
    poids_veau_naissance: props.ration.poids_veau_naissance ?? 42,
    gmq: props.ration.gmq,
    stade_physiologique: props.ration.stade_physiologique ?? '',
    type_production_ovin: props.ration.type_production_ovin ?? 'lait',
    nombre_jeunes: props.ration.nombre_jeunes,
    poids_portee: props.ration.poids_portee,
    gmq_portee: props.ration.gmq_portee,
    nec: props.ration.nec ?? 3,
    pourcentage_primipare: props.ration.pourcentage_primipare ?? 0,
    activite: props.ration.activite ?? 'stabulation',
    temperature_ambiante: props.ration.temperature_ambiante ?? 15,
    ecart_variation_reserve: props.ration.ecart_variation_reserve ?? 0,
});

// Sur une ration non encore enregistrée, proposer le poids de référence de l'espèce
// dès que la catégorie change (sans écraser un poids déjà saisi et sauvegardé).
watch(
    () => form.categorie_animal,
    (value) => {
        if (props.ration.poids_vif == null) {
            form.poids_vif = poidsDefautPourCategorie(value);
        }
    },
);

const selected = computed<CategorieOption | undefined>(() =>
    allOptions.value.find((o) => o.value === form.categorie_animal),
);
const espece = computed<string>(() => {
    const group = props.categorie_options.find((g) =>
        g.categories.some((c) => c.value === form.categorie_animal),
    );
    return group?.espece ?? 'bovin';
});

const isBovin = computed(() => espece.value === 'bovin');
const isOvin = computed(() => espece.value === 'ovin');
const isCaprin = computed(() => espece.value === 'caprin');
const estLaitiere = computed(() => selected.value?.est_laitiere ?? false);
const estCroissance = computed(() => selected.value?.est_croissance ?? false);
const estAllaitante = computed(() =>
    form.categorie_animal.includes('allaitante'),
);
// La lactation en jours (DIM) est utilisée pour ovins/caprins ; les bovins raisonnent en mois.
const stadeEnJours = computed(() => isOvin.value || isCaprin.value);
// Un objectif de production laitière concerne les laitières et les mères allaitantes (lait tété).
const afficheObjectifLait = computed(
    () => estLaitiere.value || estAllaitante.value,
);

const baseActivityOptions = [
    { label: 'Stabulation', value: 'stabulation' },
    { label: 'Entravée', value: 'entravee' },
    { label: 'Plaine', value: 'plaine' },
    { label: 'Vallon', value: 'vallon' },
    { label: 'Montagne', value: 'montagne' },
];
const activityOptions =
    form.activite !== '' &&
    !baseActivityOptions.some((o) => o.value === form.activite)
        ? [
              {
                  label: `${form.activite} (valeur actuelle)`,
                  value: form.activite,
              },
              ...baseActivityOptions,
          ]
        : baseActivityOptions;

const baseRaceOptions = [
    { label: 'Autre / non précisée', value: '' },
    { label: 'Limousine', value: 'limousine' },
    { label: 'Croisée laitière', value: 'croiselaitiere' },
    { label: 'Lacaune', value: 'lacaune' },
];
const raceOptions =
    form.race !== '' && !baseRaceOptions.some((o) => o.value === form.race)
        ? [
              { label: `${form.race} (valeur actuelle)`, value: form.race },
              ...baseRaceOptions,
          ]
        : baseRaceOptions;

function submit(): void {
    form.put(
        updateDescription({ plan: props.plan.id, ration: props.ration.id }).url,
    );
}
</script>

<template>
    <Head :title="`Description : ${ration.nom}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-foreground">
                    Paramètres animal
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ ration.nom }} · {{ plan.nom }} (INRA {{ plan.inra }})
                </p>
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <!-- Identification -->
                <section
                    class="rounded-xl border border-border bg-card p-5 shadow-sm"
                >
                    <h2 class="mb-4 font-semibold text-foreground">
                        Identification
                    </h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-1.5 md:col-span-2">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="nom"
                                >Nom de la ration *</label
                            >
                            <input
                                id="nom"
                                v-model="form.nom"
                                type="text"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                required
                            />
                            <InputError :message="form.errors.nom" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="categorie_animal"
                                >Catégorie</label
                            >
                            <select
                                id="categorie_animal"
                                v-model="form.categorie_animal"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            >
                                <optgroup
                                    v-for="group in categorie_options"
                                    :key="group.espece"
                                    :label="group.label"
                                >
                                    <option
                                        v-for="option in group.categories"
                                        :key="option.value"
                                        :value="option.value"
                                        :disabled="
                                            !isSelectable(option) &&
                                            option.value !==
                                                form.categorie_animal
                                        "
                                    >
                                        {{ option.label
                                        }}{{
                                            !option.disponible
                                                ? ' — bientôt'
                                                : ''
                                        }}
                                    </option>
                                </optgroup>
                            </select>
                            <p
                                v-if="selected"
                                class="text-xs text-muted-foreground"
                            >
                                Encombrement {{ selected.unite_encombrement }} ·
                                Énergie {{ selected.unite_fourragere }}
                            </p>
                            <InputError
                                :message="form.errors.categorie_animal"
                            />
                        </div>
                        <div
                            v-if="isBovin || isOvin"
                            class="flex flex-col gap-1.5"
                        >
                            <label
                                class="text-sm font-medium text-foreground"
                                for="race"
                                >Race</label
                            >
                            <select
                                id="race"
                                v-model="form.race"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            >
                                <option
                                    v-for="option in raceOptions"
                                    :key="option.value || 'empty'"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="effectif"
                                >Effectif</label
                            >
                            <input
                                id="effectif"
                                v-model="form.effectif"
                                type="number"
                                min="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="poids_vif"
                                >Poids vif (kg)</label
                            >
                            <input
                                id="poids_vif"
                                v-model="form.poids_vif"
                                type="number"
                                min="0"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <div v-if="estCroissance" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="gmq"
                                >GMQ (g/j)</label
                            >
                            <input
                                id="gmq"
                                v-model="form.gmq"
                                type="number"
                                min="0"
                                step="10"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                            <p class="text-xs text-muted-foreground">
                                Gain moyen quotidien visé.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Production laitière -->
                <section
                    v-if="afficheObjectifLait"
                    class="rounded-xl border border-border bg-card p-5 shadow-sm"
                >
                    <h2 class="mb-4 font-semibold text-foreground">
                        Production laitière
                    </h2>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div
                            v-if="estLaitiere && isBovin"
                            class="flex flex-col gap-1.5"
                        >
                            <label
                                class="text-sm font-medium text-foreground"
                                for="lait_potentiel305j"
                                >Lait potentiel 305 j (kg)</label
                            >
                            <input
                                id="lait_potentiel305j"
                                v-model="form.lait_potentiel305j"
                                type="number"
                                min="0"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="lait_objectif"
                                >Objectif de production (kg/j)</label
                            >
                            <input
                                id="lait_objectif"
                                v-model="form.lait_objectif"
                                type="number"
                                min="0"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <template v-if="estLaitiere && isBovin">
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="tb_annuel"
                                    >TB annuel (g/kg)</label
                                >
                                <input
                                    id="tb_annuel"
                                    v-model="form.tb_annuel"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="tp_annuel"
                                    >TP annuel (g/kg)</label
                                >
                                <input
                                    id="tp_annuel"
                                    v-model="form.tp_annuel"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                            </div>
                        </template>
                        <template v-if="estLaitiere && (isOvin || isCaprin)">
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="mfc"
                                    >TB du lait (g/kg)</label
                                >
                                <input
                                    id="mfc"
                                    v-model="form.mfc"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="mpc"
                                    >TP du lait (g/kg)</label
                                >
                                <input
                                    id="mpc"
                                    v-model="form.mpc"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                            </div>
                        </template>
                    </div>
                </section>

                <!-- Portée (mère allaitante) -->
                <section
                    v-if="estAllaitante && (isOvin || isCaprin)"
                    class="rounded-xl border border-border bg-card p-5 shadow-sm"
                >
                    <h2 class="mb-4 font-semibold text-foreground">Portée</h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="nombre_jeunes"
                                >Nombre de jeunes</label
                            >
                            <input
                                id="nombre_jeunes"
                                v-model="form.nombre_jeunes"
                                type="number"
                                min="0"
                                max="6"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="poids_portee"
                                >Poids de portée (kg)</label
                            >
                            <input
                                id="poids_portee"
                                v-model="form.poids_portee"
                                type="number"
                                min="0"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="gmq_portee"
                                >GMQ portée (g/j)</label
                            >
                            <input
                                id="gmq_portee"
                                v-model="form.gmq_portee"
                                type="number"
                                min="0"
                                step="10"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                    </div>
                </section>

                <!-- Lactation et gestation -->
                <section
                    class="rounded-xl border border-border bg-card p-5 shadow-sm"
                >
                    <h2 class="mb-4 font-semibold text-foreground">
                        Lactation &amp; Gestation
                    </h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <template v-if="!stadeEnJours">
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="mois_lactation"
                                    >Mois de lactation</label
                                >
                                <input
                                    id="mois_lactation"
                                    v-model="form.mois_lactation"
                                    type="number"
                                    min="0"
                                    max="12"
                                    step="0.01"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Valeur décimale autorisée. Exemple : 0,23
                                    pour la 1re semaine.
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="mois_gestation"
                                    >Mois de gestation</label
                                >
                                <input
                                    id="mois_gestation"
                                    v-model="form.mois_gestation"
                                    type="number"
                                    min="0"
                                    max="9"
                                    step="0.01"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="jours_lactation"
                                    >Jours de lactation (DIM)</label
                                >
                                <input
                                    id="jours_lactation"
                                    v-model="form.jours_lactation"
                                    type="number"
                                    min="0"
                                    max="400"
                                    step="1"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="jours_gestation"
                                    >Jours de gestation</label
                                >
                                <input
                                    id="jours_gestation"
                                    v-model="form.jours_gestation"
                                    type="number"
                                    min="0"
                                    max="290"
                                    step="1"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                            </div>
                        </template>
                        <div v-if="isBovin" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="poids_veau_naissance"
                                >Poids veau nais. (kg)</label
                            >
                            <input
                                id="poids_veau_naissance"
                                v-model="form.poids_veau_naissance"
                                type="number"
                                min="0"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                    </div>
                </section>

                <!-- Gestion -->
                <section
                    class="rounded-xl border border-border bg-card p-5 shadow-sm"
                >
                    <h2 class="mb-4 font-semibold text-foreground">
                        Gestion &amp; Conditions
                    </h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="nec"
                                >NEC / BCS</label
                            >
                            <input
                                id="nec"
                                v-model="form.nec"
                                type="number"
                                min="0"
                                max="5"
                                step="0.5"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <div
                            v-if="estLaitiere && isBovin"
                            class="flex flex-col gap-1.5"
                        >
                            <label
                                class="text-sm font-medium text-foreground"
                                for="pourcentage_primipare"
                                >% primipares</label
                            >
                            <input
                                id="pourcentage_primipare"
                                v-model="form.pourcentage_primipare"
                                type="number"
                                min="0"
                                max="100"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                        <div v-if="isOvin" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="type_production_ovin"
                                >Type de production</label
                            >
                            <select
                                id="type_production_ovin"
                                v-model="form.type_production_ovin"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            >
                                <option value="lait">Lait</option>
                                <option value="viande">Viande</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="activite"
                                >Activité</label
                            >
                            <select
                                id="activite"
                                v-model="form.activite"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            >
                                <option
                                    v-for="option in activityOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="temperature_ambiante"
                                >Température (°C)</label
                            >
                            <input
                                id="temperature_ambiante"
                                v-model="form.temperature_ambiante"
                                type="number"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>
                    </div>
                </section>

                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Enregistrement…'
                                : 'Enregistrer et continuer →'
                        }}
                    </button>
                    <a
                        :href="planShow({ plan: plan.id }).url"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                    >
                        Retour au plan
                    </a>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
