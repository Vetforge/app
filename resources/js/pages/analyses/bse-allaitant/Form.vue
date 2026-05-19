<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Minus, Save, Settings } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { edit as moduleSettingsEdit } from '@/actions/App/Http/Controllers/Settings/ModuleSettingsController';
import {
    index as analysesIndex,
    show as analysisShow,
    store as analysisStore,
    update as analysisUpdate,
} from '@/actions/App/Http/Controllers/VeterinaryAnalysisController';
import BreederSelectWithCreate from '@/components/BreederSelectWithCreate.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { clonePlain } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface ModuleInfo {
    slug: string;
    label: string;
    short_label: string;
    description: string;
}

interface BreederOption {
    id: number;
    name: string;
    city: string | null;
    herd_number: string | null;
}

interface Analysis {
    id: number;
    breeder_id: number;
    animal_nom: string | null;
    sampled_at: string | null;
    analyzed_at: string | null;
    intervenant: string | null;
    payload: Record<string, any>;
}

interface FormState {
    breeder_id: string | number;
    animal_nom: string;
    sampled_at: string;
    analyzed_at: string;
    intervenant: string;
    payload: Record<string, any>;
}

type Errors = Record<string, string>;

const props = defineProps<{
    analysis?: Analysis;
    module: ModuleInfo;
    breeders: BreederOption[];
    quickBreederStoreUrl: string;
    settings: Record<string, any>;
    payloadTemplate: Record<string, any>;
}>();

const isEdit = computed(() => !!props.analysis);
const processing = ref(false);
const errors = ref<Errors>({});

const form = reactive<FormState>({
    breeder_id: props.analysis?.breeder_id ?? '',
    animal_nom: props.analysis?.animal_nom ?? '',
    sampled_at: props.analysis?.sampled_at?.slice(0, 10) ?? '',
    analyzed_at: props.analysis?.analyzed_at?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
    intervenant: props.analysis?.intervenant ?? '',
    payload: clonePlain(props.analysis?.payload ?? props.payloadTemplate),
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: props.module.short_label, href: analysesIndex({ module: props.module.slug }).url },
    { title: isEdit.value ? 'Modifier' : 'Nouvelle analyse', href: '#' },
];

const raceOptions = ['Charolaise', 'Limousine', 'Blonde d\'Aquitaine', 'Salers', 'Aubrac', 'Autre'];

function submit(): void {
    processing.value = true;
    errors.value = {};
    const options = {
        preserveScroll: true,
        onError: (serverErrors: Errors) => { errors.value = serverErrors; },
        onFinish: () => { processing.value = false; },
    };
    const data = clonePlain(form);
    if (props.analysis) {
        router.put(analysisUpdate({ analysis: props.analysis.id }).url, data, options);
        return;
    }
    router.post(analysisStore({ module: props.module.slug }).url, data, options);
}
</script>

<template>
    <Head :title="isEdit ? `Modifier ${module.short_label}` : `Nouvelle ${module.short_label}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="analysis-form mx-auto flex max-w-7xl flex-col gap-3 p-3 sm:p-4" @submit.prevent="submit">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-foreground">{{ isEdit ? 'Modifier BSE' : 'Nouveau BSE Allaitant' }}</h1>
                    <p class="text-xs text-muted-foreground">{{ module.label }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="moduleSettingsEdit({ module: module.slug }).url" class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1 text-xs font-medium hover:bg-accent">
                        <Settings class="size-3.5" />
                        Reglages
                    </Link>
                </div>
            </div>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Identification</h2>
                        <p>Eleveur et dates du bilan.</p>
                    </div>
                </div>
                <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-12">
                    <div class="grid gap-1 xl:col-span-4">
                        <label class="text-sm font-medium" for="breeder_id">Eleveur *</label>
                        <BreederSelectWithCreate v-model="form.breeder_id" input-id="breeder_id" :breeders="breeders" :create-url="quickBreederStoreUrl" />
                        <input v-model="form.breeder_id" type="hidden" required />
                        <InputError :message="errors.breeder_id" />
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="annee_reference">Annee de reference</label>
                        <input id="annee_reference" v-model.number="form.payload.annee_reference" type="number" min="2000" max="2099" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="race">Race</label>
                        <select id="race" v-model="form.payload.race" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm">
                            <option v-for="race in raceOptions" :key="race" :value="race">{{ race }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="analyzed_at">Date du bilan</label>
                        <input id="analyzed_at" v-model="form.analyzed_at" type="date" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                        <InputError :message="errors.analyzed_at" />
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="intervenant">Intervenant</label>
                        <input id="intervenant" v-model="form.intervenant" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                        <InputError :message="errors.intervenant" />
                    </div>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Troupeau et veaux</h2>
                        <p>Effectifs, natalite et mortalite des veaux.</p>
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_vaches">Nb vaches reproductrices</label>
                        <input id="nb_vaches" v-model.number="form.payload.nb_vaches_reproductrices" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="ivv">IVV (jours)</label>
                        <input id="ivv" v-model.number="form.payload.ivv" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_veaux_nes">Nb veaux nes vivants</label>
                        <input id="nb_veaux_nes" v-model.number="form.payload.nb_veaux_nes_vivants" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_jumeaux">Nb jumeaux</label>
                        <input id="nb_jumeaux" v-model.number="form.payload.nb_jumeaux" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_accidents_velage">Nb accidents velage (mort-nes)</label>
                        <input id="nb_accidents_velage" v-model.number="form.payload.nb_accidents_velage" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_avortons">Nb avortons</label>
                        <input id="nb_avortons" v-model.number="form.payload.nb_avortons" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_morts_post24h">Nb morts apres 24h</label>
                        <input id="nb_morts_post24h" v-model.number="form.payload.nb_morts_post24h" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_sevres">Nb sevres</label>
                        <input id="nb_sevres" v-model.number="form.payload.nb_sevres" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_morts_avant3_mois">Nb morts avant 3 mois</label>
                        <input id="nb_morts_avant3_mois" v-model.number="form.payload.nb_morts_avant3_mois" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Pathologies veaux</h2>
                        <p>Maladies et mortalites par cause.</p>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border bg-muted/40">
                                <th class="px-3 py-2 text-left font-medium text-xs">Affection</th>
                                <th class="px-3 py-2 text-left font-medium text-xs">Nb malades</th>
                                <th class="px-3 py-2 text-left font-medium text-xs">Nb morts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-border/50">
                                <td class="px-3 py-1.5 font-medium">Diarrhee 0-4j</td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_malades_diar1" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_morts_diar1" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                            </tr>
                            <tr class="border-b border-border/50">
                                <td class="px-3 py-1.5 font-medium">Diarrhee 5-21j</td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_malades_diar2et3" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_morts_diar2et3" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                            </tr>
                            <tr class="border-b border-border/50">
                                <td class="px-3 py-1.5 font-medium">Diarrhee > 21j</td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_malades_diar4" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_morts_diar4" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                            </tr>
                            <tr class="border-b border-border/50">
                                <td class="px-3 py-1.5 text-muted-foreground text-xs pl-5">dont perfs diarrhee</td>
                                <td class="px-3 py-1.5" colspan="2"><input v-model.number="form.payload.nb_diar_perf" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                            </tr>
                            <tr class="border-b border-border/50">
                                <td class="px-3 py-1.5 font-medium">Respiratoire</td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_malades_respi" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_morts_respi" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                            </tr>
                            <tr class="border-b border-border/50">
                                <td class="px-3 py-1.5 font-medium">Omphalite</td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_malades_omphalite" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_morts_omphalite" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                            </tr>
                            <tr class="border-b border-border/50">
                                <td class="px-3 py-1.5 font-medium">Autres</td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_malades_autres" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_morts_autres" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                            </tr>
                            <tr class="border-b border-border/50 last:border-b-0">
                                <td class="px-3 py-1.5 font-medium">Morts subites</td>
                                <td class="px-3 py-1.5">-</td>
                                <td class="px-3 py-1.5"><input v-model.number="form.payload.nb_morts_subites" type="number" min="0" class="w-24 rounded border border-border bg-background px-2 py-1" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Velages et reproduction</h2>
                        <p>Dystocie et pathologies post-velage.</p>
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_velages_longs">Nb velages longs</label>
                        <input id="nb_velages_longs" v-model.number="form.payload.nb_velages_longs" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_cesariennes">Nb cesariennes</label>
                        <input id="nb_cesariennes" v-model.number="form.payload.nb_cesariennes" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_non_delivrances">Nb non-delivrances</label>
                        <input id="nb_non_delivrances" v-model.number="form.payload.nb_non_delivrances" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_torsions">Nb torsions / retournements de matrice</label>
                        <input id="nb_torsions" v-model.number="form.payload.nb_torsions_retournements_matrices" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_metrites">Nb metrites</label>
                        <input id="nb_metrites" v-model.number="form.payload.nb_metrites" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Alimentation</h2>
                        <p>Surfaces fourrageres et achats alimentaires.</p>
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="ha_foin">Ha foin</label>
                        <input id="ha_foin" v-model.number="form.payload.ha_foin" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="ha_ensilage_herbe">Ha ensilage herbe</label>
                        <input id="ha_ensilage_herbe" v-model.number="form.payload.ha_ensilage_herbe" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="ha_ensilage_mais">Ha ensilage mais</label>
                        <input id="ha_ensilage_mais" v-model.number="form.payload.ha_ensilage_mais" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="prod_cereales">Production cereales (t)</label>
                        <input id="prod_cereales" v-model.number="form.payload.production_cereales_tonnes" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="achat_cereales_t">Achat cereales (t)</label>
                        <input id="achat_cereales_t" v-model.number="form.payload.achat_cereales_tonnes" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="achat_cereales_eur">Achat cereales (€)</label>
                        <input id="achat_cereales_eur" v-model.number="form.payload.achat_cereales_euros" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="achat_compl_t">Achat complementaire (t)</label>
                        <input id="achat_compl_t" v-model.number="form.payload.achat_complementaire_tonnes" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="achat_compl_eur">Achat complementaire (€)</label>
                        <input id="achat_compl_eur" v-model.number="form.payload.achat_complementaire_euros" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="achat_amv">Achat AMV (€)</label>
                        <input id="achat_amv" v-model.number="form.payload.achat_amv_euros" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                </div>
            </section>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <Link
                    :href="analysis ? analysisShow({ analysis: analysis.id }).url : analysesIndex({ module: module.slug }).url"
                    class="inline-flex items-center justify-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium hover:bg-accent"
                >
                    <Minus class="size-3.5" />
                    Annuler
                </Link>
                <button type="submit" :disabled="processing" class="inline-flex items-center justify-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50">
                    <Save class="size-4" />
                    {{ processing ? 'Enregistrement...' : 'Enregistrer le BSE' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>

<style scoped>
.analysis-section {
    display: grid;
    gap: 0.625rem;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    background: var(--card);
    padding: 0.625rem;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.03);
    min-width: 0;
}
.analysis-section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    border-bottom: 1px solid var(--border);
    padding-bottom: 0.5rem;
}
.analysis-section-heading h2 {
    font-size: 0.8125rem;
    font-weight: 650;
    line-height: 1.25;
}
.analysis-section-heading p {
    margin-top: 0.0625rem;
    color: var(--muted-foreground);
    font-size: 0.7rem;
}
.analysis-form label {
    line-height: 1.2;
    font-size: 0.75rem;
}
.analysis-form :is(input:not([type='checkbox']):not([type='hidden']), select) {
    width: 100%;
    height: 1.875rem;
    min-height: 1.875rem;
    border-radius: 0.375rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.8125rem;
    line-height: 1.25rem;
}
.analysis-form select {
    padding-right: 2rem;
}
.analysis-form :is(input:not([type='checkbox']):not([type='hidden']), select):focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 22%, transparent);
    outline: none;
}
.analysis-form table :is(input:not([type='checkbox']):not([type='hidden'])) {
    height: 1.625rem;
    min-height: 1.625rem;
    padding: 0.125rem 0.375rem;
}
.analysis-form th,
.analysis-form td {
    vertical-align: middle;
}
:deep(.breeder-create-btn) {
    height: 1.875rem;
    width: 1.875rem;
    min-height: 1.875rem;
}
@media (max-width: 640px) {
    .analysis-section {
        padding: 0.5rem;
    }
}
</style>
