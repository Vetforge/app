<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
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
    age_mois: number | null;
    variation_nec: number | null;
    duree_variation_nec_jours: number | null;
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
    age_mois: props.ration.age_mois,
    variation_nec: props.ration.variation_nec,
    duree_variation_nec_jours: props.ration.duree_variation_nec_jours,
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

form.setError(usePage().props.errors);

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
        form.age_mois = null;
        form.variation_nec = null;
        form.duree_variation_nec_jours = null;
        form.lait_objectif = null;
        form.lait_potentiel = null;
        form.gmq = null;
        form.jours_lactation = null;
        form.jours_gestation = null;
        form.nombre_jeunes = null;
        form.poids_portee = null;
        form.gmq_portee = null;
        if (
            !['bovin_croissance', 'bovin_engraissement'].includes(value) ||
            (value === 'bovin_engraissement' &&
                Number(form.reference_bovine) >= 10) ||
            (value === 'bovin_croissance' && Number(form.reference_bovine) < 10)
        ) {
            form.reference_bovine = null;
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
    if (
        form.categorie_animal === 'bovin_engraissement' ||
        form.categorie_animal === 'agneau_croissance'
    ) {
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

// Catégories des animaux de référence INRA 2018, tableau 19.2.
const referencesBovines: Record<number, string> = {
    1: 'Bovins en finition - Taurillons 8-20 mois - Grand format tardifs (ex. : Charolais)',
    2: 'Bovins en finition - Taurillons 8-20 mois - Tardifs (ex. : Limousins)',
    3: 'Bovins en finition - Taurillons 8-20 mois - Précocité moyenne (ex. : Salers, Aubrac)',
    4: 'Bovins en finition - Taurillons 8-20 mois - Précoces, races laitières',
    5: 'Bovins en finition - Bœufs tardifs 20-33 mois',
    6: 'Bovins en finition - Bœufs précoces 24-28 mois',
    7: 'Bovins en finition - Génisses tardives 15-30 mois',
    8: 'Bovins en finition - Bœufs croisés race anglaise viande × laitière 20-24 mois',
    9: 'Bovins en finition - Génisses croisées race anglaise viande × laitière 20-24 mois',
    10: "Bovins d'élevage en croissance - Mâles tardifs 8-24 mois",
    11: "Bovins d'élevage en croissance - Mâles précoces 6-24 mois",
    12: "Bovins d'élevage en croissance - Génisses tardives 8-28 mois",
    13: "Bovins d'élevage en croissance - Génisses laitières 6-24 mois",
    14: "Bovins d'élevage en croissance - Génisses croisées race anglaise viande × laitière 6-12 mois",
};

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
    form.transform((data) => ({
        ...data,
        mois_lactation: estCroissance.value ? null : data.mois_lactation,
        mois_gestation: estCroissance.value ? null : data.mois_gestation,
    })).put(
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
                                :aria-invalid="Boolean(form.errors.nom)"
                                :aria-describedby="
                                    form.errors.nom ? 'nom-error' : undefined
                                "
                                id="nom"
                                v-model="form.nom"
                                type="text"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                required
                            />
                            <InputError
                                id="nom-error"
                                :message="form.errors.nom"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="categorie_animal"
                                >Catégorie</label
                            >
                            <select
                                :aria-invalid="
                                    Boolean(form.errors.categorie_animal)
                                "
                                :aria-describedby="
                                    form.errors.categorie_animal
                                        ? 'categorie_animal-error'
                                        : undefined
                                "
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
                                <p
                                    v-for="limit in selected.validation.limites"
                                    :key="limit"
                                >
                                    {{ limit }}
                                </p>
                            </div>
                            <InputError
                                id="categorie_animal-error"
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
                                :aria-invalid="Boolean(form.errors.race)"
                                :aria-describedby="
                                    form.errors.race ? 'race-error' : undefined
                                "
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
                            <InputError
                                id="race-error"
                                :message="form.errors.race"
                            />
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
                                :aria-invalid="Boolean(form.errors.poids_vif)"
                                :aria-describedby="
                                    form.errors.poids_vif
                                        ? 'poids_vif-error'
                                        : undefined
                                "
                                id="poids_vif"
                                v-model="form.poids_vif"
                                type="number"
                                min="0"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                            <InputError
                                id="poids_vif-error"
                                :message="form.errors.poids_vif"
                            />
                        </div>
                        <div v-if="estCroissance" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="gmq"
                                >GMQ (g/j)</label
                            >
                            <input
                                :aria-invalid="Boolean(form.errors.gmq)"
                                :aria-describedby="
                                    form.errors.gmq ? 'gmq-error' : undefined
                                "
                                id="gmq"
                                v-model="form.gmq"
                                type="number"
                                min="0"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                            <p class="text-xs text-muted-foreground">
                                Gain moyen quotidien visé.
                            </p>
                            <InputError
                                id="gmq-error"
                                :message="form.errors.gmq"
                            />
                        </div>
                        <div v-if="estCroissance" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="age_jours"
                                >Âge réel (jours)</label
                            >
                            <input
                                :aria-invalid="Boolean(form.errors.age_jours)"
                                :aria-describedby="
                                    form.errors.age_jours
                                        ? 'age_jours-error'
                                        : undefined
                                "
                                id="age_jours"
                                v-model="form.age_jours"
                                type="number"
                                min="1"
                                :max="isCaprin ? 730 : 5000"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                id="age_jours-error"
                                :message="form.errors.age_jours"
                            />
                        </div>
                        <div v-if="estCroissance" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="sexe"
                                >Sexe</label
                            >
                            <select
                                :aria-invalid="Boolean(form.errors.sexe)"
                                :aria-describedby="
                                    form.errors.sexe ? 'sexe-error' : undefined
                                "
                                id="sexe"
                                v-model="form.sexe"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Sélectionner</option>
                                <option value="femelle">Femelle</option>
                                <option v-if="!isCaprin" value="male">
                                    Mâle entier
                                </option>
                                <option v-if="!isCaprin" value="male_castre">
                                    Mâle castré
                                </option>
                            </select>
                            <InputError
                                id="sexe-error"
                                :message="form.errors.sexe"
                            />
                        </div>
                        <div v-if="estCroissance" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="poids_adulte"
                                >Poids adulte cible (kg)</label
                            >
                            <input
                                :aria-invalid="
                                    Boolean(form.errors.poids_adulte)
                                "
                                :aria-describedby="
                                    form.errors.poids_adulte
                                        ? 'poids_adulte-error'
                                        : undefined
                                "
                                id="poids_adulte"
                                v-model="form.poids_adulte"
                                type="number"
                                min="1"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                id="poids_adulte-error"
                                :message="form.errors.poids_adulte"
                            />
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
                                :aria-invalid="
                                    Boolean(form.errors.reference_bovine)
                                "
                                :aria-describedby="
                                    form.errors.reference_bovine
                                        ? 'reference_bovine-error'
                                        : undefined
                                "
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
                                        {{ referencesBovines[n] }}
                                    </option>
                                </template>
                                <template v-else>
                                    <option
                                        v-for="n in [10, 11, 12, 13, 14]"
                                        :key="n"
                                        :value="n"
                                    >
                                        {{ referencesBovines[n] }}
                                    </option>
                                </template>
                            </select>
                            <InputError
                                id="reference_bovine-error"
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
                                >{{
                                    isOvin
                                        ? 'Lait potentiel standard (l/j)'
                                        : 'Lait potentiel standard (kg/j)'
                                }}</label
                            >
                            <input
                                :aria-invalid="
                                    Boolean(form.errors.lait_potentiel)
                                "
                                :aria-describedby="
                                    form.errors.lait_potentiel
                                        ? 'lait_potentiel-error'
                                        : undefined
                                "
                                id="lait_potentiel"
                                v-model="form.lait_potentiel"
                                type="number"
                                min="0"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                id="lait_potentiel-error"
                                :message="form.errors.lait_potentiel"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="lait_objectif"
                                >{{
                                    form.categorie_animal ===
                                    'brebis_allaitante'
                                        ? 'Lait tété (l/j, estimé si laissé vide)'
                                        : isOvin
                                          ? 'Objectif de production (l/j)'
                                          : 'Objectif de production (kg/j)'
                                }}</label
                            >
                            <input
                                :aria-invalid="
                                    Boolean(form.errors.lait_objectif)
                                "
                                :aria-describedby="
                                    form.errors.lait_objectif
                                        ? 'lait_objectif-error'
                                        : undefined
                                "
                                id="lait_objectif"
                                v-model="form.lait_objectif"
                                type="number"
                                min="0"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                            <InputError
                                id="lait_objectif-error"
                                :message="form.errors.lait_objectif"
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
                                    >{{
                                        isOvin
                                            ? 'TB du lait (g/l)'
                                            : 'TB du lait (g/kg)'
                                    }}</label
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
                                    >{{
                                        isOvin
                                            ? 'TP du lait (g/l)'
                                            : 'TP du lait (g/kg)'
                                    }}</label
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
                                :aria-invalid="
                                    Boolean(form.errors.nombre_jeunes)
                                "
                                :aria-describedby="
                                    form.errors.nombre_jeunes
                                        ? 'nombre_jeunes-error'
                                        : undefined
                                "
                                id="nombre_jeunes"
                                v-model="form.nombre_jeunes"
                                type="number"
                                min="1"
                                max="6"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                            <InputError
                                id="nombre_jeunes-error"
                                :message="form.errors.nombre_jeunes"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="poids_portee"
                                >Poids attendu de la portée à la naissance
                                (kg)</label
                            >
                            <input
                                :aria-invalid="
                                    Boolean(form.errors.poids_portee)
                                "
                                :aria-describedby="
                                    form.errors.poids_portee
                                        ? 'poids_portee-error'
                                        : undefined
                                "
                                id="poids_portee"
                                v-model="form.poids_portee"
                                type="number"
                                min="0"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                            <InputError
                                id="poids_portee-error"
                                :message="form.errors.poids_portee"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="gmq_portee"
                                >GMQ portée entre 10 et 30 jours après agnelage
                                (g/j)</label
                            >
                            <input
                                :aria-invalid="Boolean(form.errors.gmq_portee)"
                                :aria-describedby="
                                    form.errors.gmq_portee
                                        ? 'gmq_portee-error'
                                        : undefined
                                "
                                id="gmq_portee"
                                v-model="form.gmq_portee"
                                type="number"
                                min="0"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                            <InputError
                                id="gmq_portee-error"
                                :message="form.errors.gmq_portee"
                            />
                        </div>
                    </div>
                </section>

                <section
                    v-if="
                        is2018 &&
                        form.categorie_animal === 'chevrette_croissance'
                    "
                    class="rounded-xl border border-border bg-card p-5 shadow-sm"
                >
                    <h2 class="mb-4 font-semibold text-foreground">
                        Gestation de la chevrette (si gestante)
                    </h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <label for="jours_gestation"
                                >Jours de gestation</label
                            >
                            <input
                                id="jours_gestation"
                                v-model="form.jours_gestation"
                                type="number"
                                min="0"
                                max="150"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                :message="form.errors.jours_gestation"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="nombre_jeunes"
                                >Nombre de chevreaux attendus</label
                            >
                            <input
                                id="nombre_jeunes"
                                v-model="form.nombre_jeunes"
                                type="number"
                                min="1"
                                max="2"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.nombre_jeunes" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="poids_portee"
                                >Poids total de la portée à la naissance
                                (kg)</label
                            >
                            <input
                                id="poids_portee"
                                v-model="form.poids_portee"
                                type="number"
                                min="0.1"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.poids_portee" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Laisser le stade vide ou à zéro si non gestante. Les
                        besoins de gestation s’ajoutent à la croissance ;
                        l’ingestion reste une estimation pour les chevrettes.
                    </p>
                </section>
                <section
                    v-if="is2018 && isOvin && !estCroissance"
                    class="rounded-xl border border-border bg-card p-5 shadow-sm"
                >
                    <h2 class="mb-4 font-semibold text-foreground">
                        Croissance et réserves de la brebis
                    </h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <label for="gmq"
                                >GMQ de la jeune reproductrice (g/j)</label
                            >
                            <input
                                id="gmq"
                                v-model="form.gmq"
                                type="number"
                                min="0"
                                max="3000"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.gmq" />
                            <p class="text-xs text-muted-foreground">
                                Uniquement pendant les 100 premiers jours de
                                gestation. Zéro pour une brebis adulte sans
                                croissance.
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="poids_adulte"
                                >Poids adulte cible (kg, si croissance)</label
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
                        <div class="flex flex-col gap-1.5">
                            <label for="variation_nec"
                                >Variation prévue de NEC (points)</label
                            >
                            <input
                                id="variation_nec"
                                v-model="form.variation_nec"
                                type="number"
                                min="-2"
                                max="2"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.variation_nec" />
                            <p class="text-xs text-muted-foreground">
                                Positive : reconstitution. Négative :
                                mobilisation, pendant les six premières semaines
                                de lactation.
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="duree_variation_nec_jours"
                                >Durée de variation de NEC (jours)</label
                            >
                            <input
                                id="duree_variation_nec_jours"
                                v-model="form.duree_variation_nec_jours"
                                type="number"
                                min="1"
                                max="730"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                :message="form.errors.duree_variation_nec_jours"
                            />
                        </div>
                    </div>
                </section>

                <p v-if="is2018" class="text-xs text-muted-foreground">
                    Référence des équations : INRA Feeding System for Ruminants,
                    2e édition anglaise (2019), système INRA 2018. Lait ovin :
                    l/j et TB/TP en g/l ; lait bovin et caprin : kg/j et TB/TP
                    en g/kg.
                </p>
                <!-- Lactation et gestation -->
                <section
                    v-if="!estCroissance"
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
                                    :aria-invalid="
                                        Boolean(form.errors.jours_lactation)
                                    "
                                    :aria-describedby="
                                        form.errors.jours_lactation
                                            ? 'jours_lactation-error'
                                            : undefined
                                    "
                                    id="jours_lactation"
                                    v-model="form.jours_lactation"
                                    type="number"
                                    min="0"
                                    :max="isOvin ? 140 : 365"
                                    step="1"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                                <InputError
                                    id="jours_lactation-error"
                                    :message="form.errors.jours_lactation"
                                />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label
                                    class="text-sm font-medium text-foreground"
                                    for="jours_gestation"
                                    >Jours de gestation</label
                                >
                                <input
                                    :aria-invalid="
                                        Boolean(form.errors.jours_gestation)
                                    "
                                    :aria-describedby="
                                        form.errors.jours_gestation
                                            ? 'jours_gestation-error'
                                            : undefined
                                    "
                                    id="jours_gestation"
                                    v-model="form.jours_gestation"
                                    type="number"
                                    min="0"
                                    :max="isOvin ? 147 : 150"
                                    step="1"
                                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                                <InputError
                                    id="jours_gestation-error"
                                    :message="form.errors.jours_gestation"
                                />
                            </div>
                        </template>
                        <div class="flex flex-col gap-1.5">
                            <span class="text-sm font-medium text-foreground"
                                >État physiologique</span
                            >
                            <div
                                class="h-10 rounded-lg border border-border bg-muted px-3 py-2 text-sm text-foreground"
                            >
                                {{ stadePhysiologique }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Déduit automatiquement de la catégorie et des
                                stades saisis.
                            </p>
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
                                step="any"
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
                        <div
                            v-if="
                                is2018 && estLaitiere && (isBovin || isCaprin)
                            "
                            class="flex flex-col gap-1.5"
                        >
                            <label for="age_mois">{{
                                isCaprin
                                    ? 'Âge de la chèvre (mois)'
                                    : 'Âge de la vache ou des primipares du lot (mois)'
                            }}</label>
                            <input
                                id="age_mois"
                                v-model="form.age_mois"
                                type="number"
                                :min="isCaprin ? 1 : 24"
                                max="240"
                                step="0.1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.age_mois" />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    isCaprin
                                        ? 'Âge réel pour les courbes de croissance. Si laissé vide, âges moyens des deux premiers cycles reproductifs (Table 21.6).'
                                        : 'Pour un animal : âge réel. Pour un lot : âge des primipares, besoins pondérés par leur pourcentage. Si laissé vide, estimation à 36 mois.'
                                }}
                            </p>
                        </div>
                        <div v-if="afficheParite" class="flex flex-col gap-1.5">
                            <label
                                class="text-sm font-medium text-foreground"
                                for="parite"
                                >Parité</label
                            >
                            <input
                                :aria-invalid="Boolean(form.errors.parite)"
                                :aria-describedby="
                                    form.errors.parite
                                        ? 'parite-error'
                                        : undefined
                                "
                                id="parite"
                                v-model="form.parite"
                                type="number"
                                min="0"
                                max="15"
                                step="1"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                id="parite-error"
                                :message="form.errors.parite"
                            />
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
                            v-if="
                                (isBovin && (estLaitiere || estAllaitante)) ||
                                (isCaprin && !estCroissance)
                            "
                            class="flex flex-col gap-1.5"
                        >
                            <label
                                class="text-sm font-medium text-foreground"
                                for="ecart_variation_reserve"
                                >{{
                                    isCaprin
                                        ? 'Variation du poids corporel vide (kg/j)'
                                        : 'Variation corrigée des réserves (kg/j)'
                                }}</label
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
                            <span class="text-sm font-medium text-foreground"
                                >Filière ovine</span
                            >
                            <div
                                class="h-10 rounded-lg border border-border bg-muted px-3 py-2 text-sm text-foreground"
                            >
                                {{ productionOvine }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Imposée par la catégorie : aucun modèle
                                incompatible ne peut être sélectionné.
                            </p>
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
                                step="0.1"
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
