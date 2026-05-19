<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import { index as plansIndex, show as planShow } from '@/actions/App/Http/Controllers/PlanRationnementController';
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
}

const props = defineProps<{
    plan: Plan;
    ration: Ration;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Plans', href: plansIndex() },
    { title: props.plan.nom, href: planShow({ plan: props.plan.id }).url },
    { title: props.ration.nom, href: '#' },
    { title: 'Paramètres animal', href: '#' },
];

const formDefaults = {
    nom: props.ration.nom,
    effectif: props.ration.effectif,
    lait_potentiel305j: props.ration.lait_potentiel305j,
    poids_vif: props.ration.poids_vif ?? 650,
    pourcentage_primipare: props.ration.pourcentage_primipare ?? 0,
    nec: props.ration.nec ?? 3,
    tb_annuel: props.ration.tb_annuel ?? 40,
    tp_annuel: props.ration.tp_annuel ?? 32,
    activite: props.ration.activite ?? 'stabulation',
    temperature_ambiante: props.ration.temperature_ambiante ?? 15,
    nec_velage: props.ration.nec_velage ?? 3,
    ivv: props.ration.ivv ?? 380,
    poids_veau_naissance: props.ration.poids_veau_naissance ?? 42,
    age_velage: props.ration.age_velage ?? 28,
    lait_objectif305j: props.ration.lait_objectif305j,
    stade_moyen: props.ration.stade_moyen ?? 100,
    lait_objectif: props.ration.lait_objectif,
    is_ration_semi_complete: props.ration.is_ration_semi_complete ?? false,
    ecart_variation_reserve: props.ration.ecart_variation_reserve ?? 0,
    strategie: props.ration.strategie ?? 'Ration complète',
    lait_objectif_auge: props.ration.lait_objectif_auge,
    race: props.ration.race ?? '',
    mois_lactation: props.ration.mois_lactation ?? 3,
    mois_gestation: props.ration.mois_gestation ?? 0,
    categorie_animal: props.ration.categorie_animal ?? 'Vache laitière',
};

const baseActivityOptions = [
    { label: 'Stabulation', value: 'stabulation' },
    { label: 'Entravée', value: 'entravee' },
    { label: 'Plaine', value: 'plaine' },
    { label: 'Vallon', value: 'vallon' },
    { label: 'Montagne', value: 'montagne' },
];

const activityOptions = formDefaults.activite !== '' && !baseActivityOptions.some((option) => option.value === formDefaults.activite)
    ? [{ label: `${formDefaults.activite} (valeur actuelle)`, value: formDefaults.activite }, ...baseActivityOptions]
    : baseActivityOptions;

const baseRaceOptions = [
    { label: 'Autre / non précisée', value: '' },
    { label: 'Limousine', value: 'limousine' },
    { label: 'Croisée laitière', value: 'croiselaitiere' },
];

const raceOptions = formDefaults.race !== '' && !baseRaceOptions.some((option) => option.value === formDefaults.race)
    ? [{ label: `${formDefaults.race} (valeur actuelle)`, value: formDefaults.race }, ...baseRaceOptions]
    : baseRaceOptions;

function inputValue(value: string | number | null | undefined): string | number {
    return value ?? '';
}
</script>

<template>
    <Head :title="`Description : ${ration.nom}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-foreground">Paramètres animal</h1>
                <p class="text-sm text-muted-foreground">{{ ration.nom }} · {{ plan.nom }} (INRA {{ plan.inra }})</p>
            </div>

            <Form
                v-bind="updateDescription.form({ plan: plan.id, ration: ration.id })"
                #default="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <!-- Identification -->
                <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 font-semibold text-foreground">Identification</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-1.5 md:col-span-2">
                            <label class="text-sm font-medium text-foreground" for="nom">Nom de la ration *</label>
                            <input id="nom" name="nom" type="text" :value="inputValue(formDefaults.nom)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" required />
                            <InputError :message="errors.nom" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="categorie_animal">Catégorie</label>
                            <select id="categorie_animal" name="categorie_animal" :value="formDefaults.categorie_animal" class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="Vache laitière">Vache laitière</option>
                                <option value="Vache allaitante">Vache allaitante</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="race">Race</label>
                            <select id="race" name="race" :value="formDefaults.race" class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                <option v-for="option in raceOptions" :key="option.value || 'empty'" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="effectif">Effectif</label>
                            <input id="effectif" name="effectif" type="number" min="1" :value="inputValue(formDefaults.effectif)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="poids_vif">Poids vif (kg)</label>
                            <input id="poids_vif" name="poids_vif" type="number" min="0" step="1" :value="inputValue(formDefaults.poids_vif)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>
                </section>

                <!-- Production laitière -->
                <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 font-semibold text-foreground">Production laitière</h2>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="lait_potentiel305j">Lait potentiel 305 j (kg)</label>
                            <input id="lait_potentiel305j" name="lait_potentiel305j" type="number" min="0" step="1" :value="inputValue(formDefaults.lait_potentiel305j)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="lait_objectif">Objectif de production (kg/j)</label>
                            <input id="lait_objectif" name="lait_objectif" type="number" min="0" step="0.1" :value="inputValue(formDefaults.lait_objectif)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="tb_annuel">TB annuel (g/kg)</label>
                            <input id="tb_annuel" name="tb_annuel" type="number" min="0" step="0.1" :value="inputValue(formDefaults.tb_annuel)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="tp_annuel">TP annuel (g/kg)</label>
                            <input id="tp_annuel" name="tp_annuel" type="number" min="0" step="0.1" :value="inputValue(formDefaults.tp_annuel)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>
                </section>

                <!-- Lactation et gestation -->
                <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 font-semibold text-foreground">Lactation &amp; Gestation</h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="mois_lactation">Mois de lactation</label>
                            <input id="mois_lactation" name="mois_lactation" type="number" min="0" max="12" step="0.01" :value="inputValue(formDefaults.mois_lactation)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                            <p class="text-xs text-muted-foreground">Valeur décimale autorisée. Exemple : 0,23 pour la 1re semaine.</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="mois_gestation">Mois de gestation</label>
                            <input id="mois_gestation" name="mois_gestation" type="number" min="0" max="9" step="0.01" :value="inputValue(formDefaults.mois_gestation)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="poids_veau_naissance">Poids veau nais. (kg)</label>
                            <input id="poids_veau_naissance" name="poids_veau_naissance" type="number" min="0" :value="inputValue(formDefaults.poids_veau_naissance)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>
                </section>

                <!-- Gestion -->
                <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 font-semibold text-foreground">Gestion &amp; Conditions</h2>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="nec">NEC</label>
                            <input id="nec" name="nec" type="number" min="0" max="5" step="0.5" :value="inputValue(formDefaults.nec)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="pourcentage_primipare">% primipares</label>
                            <input id="pourcentage_primipare" name="pourcentage_primipare" type="number" min="0" max="100" :value="inputValue(formDefaults.pourcentage_primipare)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="activite">Activité</label>
                            <select id="activite" name="activite" :value="formDefaults.activite" class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                <option v-for="option in activityOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-foreground" for="temperature_ambiante">Température (°C)</label>
                            <input id="temperature_ambiante" name="temperature_ambiante" type="number" step="1" :value="inputValue(formDefaults.temperature_ambiante)" class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                    </div>
                </section>

                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ processing ? 'Enregistrement…' : 'Enregistrer et continuer →' }}
                    </button>
                    <a
                        :href="planShow({ plan: plan.id }).url"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                    >
                        Retour au plan
                    </a>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
