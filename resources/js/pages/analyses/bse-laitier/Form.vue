<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Minus, Save, Settings } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
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

interface ComparisonOption {
    id: number;
    breeder_id: number;
    label: string;
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
    comparisonAnalyses: ComparisonOption[];
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

if (form.payload.comparison_analysis_id === undefined) {
    form.payload.comparison_analysis_id = null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: props.module.short_label, href: analysesIndex({ module: props.module.slug }).url },
    { title: isEdit.value ? 'Modifier' : 'Nouvelle analyse', href: '#' },
];

const raceOptions = ['Prim Holstein', 'Normande', 'Autre'];

const filteredComparisonAnalyses = computed(() => {
    if (!form.breeder_id) return [];
    const breederId = Number(form.breeder_id);
    return props.comparisonAnalyses.filter((analysis) => analysis.breeder_id === breederId);
});

watch(
    () => form.breeder_id,
    () => {
        const selectedComparisonId = Number(form.payload.comparison_analysis_id);
        if (!selectedComparisonId) return;
        if (!filteredComparisonAnalyses.value.some((analysis) => analysis.id === selectedComparisonId)) {
            form.payload.comparison_analysis_id = null;
        }
    },
);

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
                    <h1 class="text-xl font-bold text-foreground">{{ isEdit ? 'Modifier BSE' : 'Nouveau BSE Laitier' }}</h1>
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
                    <div class="grid gap-1 xl:col-span-4">
                        <label class="text-sm font-medium" for="comparison_analysis_id">Comparer avec</label>
                        <select
                            id="comparison_analysis_id"
                            v-model="form.payload.comparison_analysis_id"
                            class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                            :disabled="!form.breeder_id || filteredComparisonAnalyses.length === 0"
                        >
                            <option :value="null">Aucun ancien bilan</option>
                            <option v-for="comparison in filteredComparisonAnalyses" :key="comparison.id" :value="comparison.id">
                                {{ comparison.label }}
                            </option>
                        </select>
                        <InputError :message="errors['payload.comparison_analysis_id']" />
                    </div>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Troupeau et production</h2>
                        <p>Effectifs, production laitiere et parametres qualite.</p>
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_vaches">Nb vaches productrices</label>
                        <input id="nb_vaches" v-model.number="form.payload.nb_vaches_productrices" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="ivv">IVV (jours)</label>
                        <input id="ivv" v-model.number="form.payload.ivv" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="cci">Concentration cellulaire moyenne (x1000)</label>
                        <input id="cci" v-model.number="form.payload.concentration_cellulaire_moyen" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="prod_lait">Production annuelle lait (tonnes)</label>
                        <input id="prod_lait" v-model.number="form.payload.production_annuelle_lait" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="prix_lait">Prix du lait (€/tonne)</label>
                        <input id="prix_lait" v-model.number="form.payload.prix_lait_tonne" type="number" min="0" step="0.01" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="tb">TB moyen (g/kg)</label>
                        <input id="tb" v-model.number="form.payload.tx_butyreux_moyen" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="tp">TP moyen (g/kg)</label>
                        <input id="tp" v-model.number="form.payload.tx_proteique_moyen" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Reproduction et veaux</h2>
                        <p>Natalite, mortalite neonatale et valorisation des veaux.</p>
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_veaux_nes">Nb veaux nes vivants</label>
                        <input id="nb_veaux_nes" v-model.number="form.payload.nb_veaux_nes_vivants" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_avortons">Nb avortons</label>
                        <input id="nb_avortons" v-model.number="form.payload.nb_avortons" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_jumeaux">Nb jumeaux</label>
                        <input id="nb_jumeaux" v-model.number="form.payload.nb_jumeaux" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="prix_veaux_male">Prix veaux males (€)</label>
                        <input id="prix_veaux_male" v-model.number="form.payload.prix_veaux_male" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="prix_veaux_femelle">Prix veaux femelles (€)</label>
                        <input id="prix_veaux_femelle" v-model.number="form.payload.prix_veaux_femelle" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_malades_0a7">Nb malades 0-7j</label>
                        <input id="nb_malades_0a7" v-model.number="form.payload.nb_malades_0a7" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_morts_0a7">Nb morts 0-7j</label>
                        <input id="nb_morts_0a7" v-model.number="form.payload.nb_morts_0a7" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_malades_8a_sevr">Nb malades 8j-sevrage</label>
                        <input id="nb_malades_8a_sevr" v-model.number="form.payload.nb_malades_8a_sevr" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_morts_8a_sevr">Nb morts 8j-sevrage</label>
                        <input id="nb_morts_8a_sevr" v-model.number="form.payload.nb_morts_8a_sevr" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_ivia1">IV-IA1 (jours)</label>
                        <input id="nb_ivia1" v-model.number="form.payload.nb_ivia1" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_iviaf">IV-IAF (jours)</label>
                        <input id="nb_iviaf" v-model.number="form.payload.nb_iviaf" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="tx_reussite_ia1">Tx reussite IA1 (%)</label>
                        <input id="tx_reussite_ia1" v-model.number="form.payload.tx_reussite_ia1" type="number" min="0" max="100" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="tx_ia3">Tx IA3 (%)</label>
                        <input id="tx_ia3" v-model.number="form.payload.tx_ia3" type="number" min="0" max="100" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <input v-model="form.payload.boolean_depistage_metrite" type="checkbox" class="h-4 w-4 accent-primary" />
                            Depistage metrites
                        </label>
                    </div>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Pathologies vaches</h2>
                        <p>Cas cliniques et taux de non-guerison.</p>
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_mammites_locales">Nb mammites locales</label>
                        <input id="nb_mammites_locales" v-model.number="form.payload.nb_mammites_locales" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_mammites_locales_ng">dont non gueries</label>
                        <input id="nb_mammites_locales_ng" v-model.number="form.payload.nb_mammites_locales_non_gueries" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_mammites_aigues">Nb mammites aigues</label>
                        <input id="nb_mammites_aigues" v-model.number="form.payload.nb_mammites_aigues" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_mammites_aigues_ng">dont non gueries</label>
                        <input id="nb_mammites_aigues_ng" v-model.number="form.payload.nb_mammites_aigues_non_gueries" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_cci250">Nb CCI > 250 000</label>
                        <input id="nb_cci250" v-model.number="form.payload.nb_cci250" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_boiteries">Nb boiteries</label>
                        <input id="nb_boiteries" v-model.number="form.payload.nb_boiteries" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_boiteries_ng">dont non gueries</label>
                        <input id="nb_boiteries_ng" v-model.number="form.payload.nb_boiteries_non_gueries" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_fievres_de_lait">Nb fievres de lait</label>
                        <input id="nb_fievres_de_lait" v-model.number="form.payload.nb_fievres_de_lait" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_fievres_ng">dont non gueries</label>
                        <input id="nb_fievres_ng" v-model.number="form.payload.nb_fievres_de_lait_non_gueries" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_non_delivrances">Nb non-delivrances</label>
                        <input id="nb_non_delivrances" v-model.number="form.payload.nb_non_delivrances" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_metrites">Nb metrites</label>
                        <input id="nb_metrites" v-model.number="form.payload.nb_metrites" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_caillettes">Nb caillettes</label>
                        <input id="nb_caillettes" v-model.number="form.payload.nb_caillettes" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_caillettes_ng">dont non gueries</label>
                        <input id="nb_caillettes_ng" v-model.number="form.payload.nb_caillettes_non_gueries" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_cetoses">Nb cetoses</label>
                        <input id="nb_cetoses" v-model.number="form.payload.nb_cetoses" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="nb_acidoses">Nb acidoses</label>
                        <input id="nb_acidoses" v-model.number="form.payload.nb_acidoses" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
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
.analysis-form input[type='checkbox'] {
    width: 0.875rem;
    height: 0.875rem;
    accent-color: var(--primary);
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
