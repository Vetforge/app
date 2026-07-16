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
    validation: {
        version: string;
        modele: string;
        niveau: string;
        champs_requis: string[];
        champs_interdits: string[];
        domaines: Record<string, string>;
        limites: string[];
    };
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
    age_jours: number | null;
    sexe: string | null;
    parite: number | null;
    poids_adulte: number | null;
    reference_bovine: number | null;
    lait_potentiel: number | null;
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
    age_jours: props.ration.age_jours,
    sexe: props.ration.sexe ?? '',
    parite: props.ration.parite,
    poids_adulte: props.ration.poids_adulte,
    reference_bovine: props.ration.reference_bovine,
    lait_potentiel305j: props.ration.lait_potentiel305j,
    lait_potentiel: props.ration.lait_potentiel,
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
    nombre_jeunes: props.ration.nombre_jeunes,
    poids_portee: props.ration.poids_portee,
    gmq_portee: props.ration.gmq_portee,
    nec: props.ration.nec ?? 3,
    pourcentage_primipare: props.ration.pourcentage_primipare ?? 0,
    activite: props.ration.activite ?? 'stabulation',
    temperature_ambiante: props.ration.temperature_ambiante ?? 15,
    ecart_variation_reserve: props.ration.ecart_variation_reserve ?? 0,
    nec_velage: props.ration.nec_velage,
});

// Sur une ration non encore enregistrée, proposer le poids de référence de l'espèce
// dès que la catégorie change (sans écraser un poids déjà saisi et sauvegardé).
watch(
    () => form.categorie_animal,
    (value) => {
        if (props.ration.poids_vif == null) {
            form.poids_vif = poidsDefautPourCategorie(value);
        }

        // Ne jamais resoumettre des données physiologiques appartenant à l'ancienne catégorie.
        form.clearErrors();
        // Une race de l'ancienne espèce n'a aucun sens pour la nouvelle : forcer une nouvelle sélection.
        const races = racesParEspece[espece.value] ?? [];
        if (!races.some((o) => o.value === form.race)) {
            form.race = '';
        }
        form.lait_objectif = null;
        form.lait_potentiel = null;
        form.gmq = null;
        form.jours_lactation = null;
        form.jours_gestation = null;
        form.nombre_jeunes = null;
        form.poids_portee = null;
        form.gmq_portee = null;
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
const affichePortee = computed(
    () => (isOvin.value || isCaprin.value) && !estCroissance.value,
);
const afficheParite = computed(() =>
    [
        'vache_allaitante',
        'brebis_laitiere',
        'brebis_allaitante',
        'chevre_laitiere',
    ].includes(form.categorie_animal),
);
const productionOvine = computed(() =>
    form.categorie_animal === 'brebis_laitiere' ? 'Lait' : 'Viande',
);
const stadePhysiologique = computed(() => {
    if (form.categorie_animal === 'bovin_engraissement' || form.categorie_animal === 'agneau_croissance') {
        return 'Engraissement';
    }
    if (estCroissance.value) {
        return 'Croissance';
    }
    const lactation = stadeEnJours.value
        ? Number(form.jours_lactation ?? 0)
        : Number(form.mois_lactation ?? 0);
    if (lactation > 0) {
        if (estAllaitante.value) return 'Allaitement';
        if (form.categorie_animal === 'brebis_laitiere') return 'Traite';
        return 'Lactation';
    }
    const gestation = stadeEnJours.value
        ? Number(form.jours_gestation ?? 0)
        : Number(form.mois_gestation ?? 0);
    return gestation > 0 ? 'Gestation' : 'Tarie / entretien';
});

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

// Races proposées par espèce : uniquement celles paramétrées dans les équations
// INRA (CI vache allaitante, références bovines Table 19.2/19.3, Lacaune vs autres
// brebis laitières, Alpine/Saanen caprins), plus « Autre » traitée par les valeurs
// par défaut du modèle (Alpine pour les caprins, race de référence).
const racesParEspece: Record<string, { label: string; value: string }[]> = {
    bovin: [
        { label: 'Charolaise', value: 'charolaise' },
        { label: 'Limousine', value: 'limousine' },
        { label: "Blonde d'Aquitaine", value: 'blonde_aquitaine' },
        { label: 'Salers / Aubrac', value: 'salers' },
        { label: "Prim'Holstein", value: 'prim_holstein' },
        { label: 'Montbéliarde', value: 'montbeliarde' },
        { label: 'Normande', value: 'normande' },
        { label: 'Croisée laitière', value: 'croiselaitiere' },
        { label: 'Autre', value: 'autre' },
    ],
    ovin: [
        { label: 'Lacaune', value: 'lacaune' },
        { label: 'Manech / Basco-Béarnaise', value: 'manech' },
        { label: 'Autre', value: 'autre' },
    ],
    caprin: [
        { label: 'Alpine', value: 'alpine' },
        { label: 'Saanen', value: 'saanen' },
        { label: 'Autre (calculée comme Alpine)', value: 'autre' },
    ],
};
const raceOptions = computed<{ label: string; value: string }[]>(() => {
    const options = racesParEspece[espece.value] ?? [];
    // Une race héritée d'une ancienne saisie reste visible tant qu'elle n'est pas remplacée.
    if (form.race !== '' && !options.some((o) => o.value === form.race)) {
        return [
            { label: `${form.race} (valeur actuelle)`, value: form.race },
            ...options,
        ];
    }

    return options;
});

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
                            <div
                                v-if="selected"
                                class="rounded-md border border-border bg-muted/40 p-2 text-xs text-muted-foreground"
                            >
                                <p class="font-medium text-foreground">
                                    Matrice {{ selected.validation.version }} ·
                                    {{ selected.validation.modele }}
                                </p>
                                <p v-for="limit in selected.validation.limites" :key="limit">
                                    {{ limit }}
                                </p>
                            </div>
                            <InputError
                                :message="form.errors.categorie_animal"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="race"
                                >Race
                                <span class="text-destructive">*</span></label
                            >
                            <select
                                id="race"
                                v-model="form.race"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                required
                            >
                                <option value="" disabled>
                                    Sélectionner une race…
                                </option>
                                <option
                                    v-for="option in raceOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.race" />
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
                            <InputError :message="form.errors.gmq" />
                        </div>
                        <div v-if="estCroissance" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="age_jours"
                                >Âge réel (jours)</label
                            >
                            <input
                                id="age_jours"
                                v-model="form.age_jours"
                                type="number"
                                min="1"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.age_jours" />
                        </div>
                        <div v-if="estCroissance" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="sexe"
                                >Sexe</label
                            >
                            <select
                                id="sexe"
                                v-model="form.sexe"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Sélectionner</option>
                                <option value="femelle">Femelle</option>
                                <option value="male">Mâle entier</option>
                                <option value="male_castre">Mâle castré</option>
                            </select>
                            <InputError :message="form.errors.sexe" />
                        </div>
                        <div v-if="estCroissance" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="poids_adulte"
                                >Poids adulte cible (kg)</label
                            >
                            <input
                                id="poids_adulte"
                                v-model="form.poids_adulte"
                                type="number"
                                min="1"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.poids_adulte" />
                        </div>
                        <div
                            v-if="estCroissance && isBovin"
                            class="flex flex-col gap-1.5 md:col-span-2"
                        >
                            <label
                                class="text-sm font-medium text-foreground"
                                for="reference_bovine"
                                >Référence animale INRA (Table 19.2)</label
                            >
                            <select
                                id="reference_bovine"
                                v-model="form.reference_bovine"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            >
                                <option :value="null">Sélectionner</option>
                                <template
                                    v-if="
                                        form.categorie_animal ===
                                        'bovin_engraissement'
                                    "
                                >
                                    <option v-for="n in 9" :key="n" :value="n">
                                        Référence {{ n }} — finition UFV
                                    </option>
                                </template>
                                <template v-else>
                                    <option
                                        v-for="n in [10, 11, 12, 13, 14]"
                                        :key="n"
                                        :value="n"
                                    >
                                        Référence {{ n }} — croissance UFL
                                    </option>
                                </template>
                            </select>
                            <InputError
                                :message="form.errors.reference_bovine"
                            />
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
                        <div
                            v-if="estLaitiere && isCaprin"
                            class="flex flex-col gap-1.5"
                        >
                            <label
                                class="text-sm font-medium text-foreground"
                                for="lait_potentiel"
                                >Lait potentiel standard (kg/j)</label
                            >
                            <input
                                id="lait_potentiel"
                                v-model="form.lait_potentiel"
                                type="number"
                                min="0"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.lait_potentiel" />
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
                    v-if="affichePortee"
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
                                min="1"
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
                        <div class="flex flex-col gap-1.5">
                            <span class="text-sm font-medium text-foreground">État physiologique</span>
                            <div class="h-10 rounded-lg border border-border bg-muted px-3 py-2 text-sm text-foreground">
                                {{ stadePhysiologique }}
                            </div>
                            <p class="text-xs text-muted-foreground">Déduit automatiquement de la catégorie et des stades saisis.</p>
                        </div>
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
                        <div v-if="afficheParite" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="parite"
                                >Parité</label
                            >
                            <input
                                id="parite"
                                v-model="form.parite"
                                type="number"
                                min="0"
                                max="15"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.parite" />
                        </div>
                        <div
                            v-if="estLaitiere && isBovin"
                            class="flex flex-col gap-1.5"
                        >
                            <label
                                class="text-sm font-medium text-foreground"
                                for="nec_velage"
                                >NEC au vêlage</label
                            >
                            <input
                                id="nec_velage"
                                v-model="form.nec_velage"
                                type="number"
                                min="0"
                                max="5"
                                step="0.25"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div
                            v-if="estLaitiere || estAllaitante"
                            class="flex flex-col gap-1.5"
                        >
                            <label
                                class="text-sm font-medium text-foreground"
                                for="ecart_variation_reserve"
                                >Variation corrigée des réserves (kg/j)</label
                            >
                            <input
                                id="ecart_variation_reserve"
                                v-model="form.ecart_variation_reserve"
                                type="number"
                                step="0.01"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div v-if="isOvin" class="flex flex-col gap-1.5">
                            <span class="text-sm font-medium text-foreground">Filière ovine</span>
                            <div class="h-10 rounded-lg border border-border bg-muted px-3 py-2 text-sm text-foreground">
                                {{ productionOvine }}
                            </div>
                            <p class="text-xs text-muted-foreground">Imposée par la catégorie : aucun modèle incompatible ne peut être sélectionné.</p>
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
