<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Minus, Save } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
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

interface ElisaTest {
    key: string;
    label: string;
    species: string[];
    enabled: boolean;
}

interface BiochemTest {
    key: string;
    label: string;
    unit: string;
    species: string[];
    enabled: boolean;
}

interface QualitativeTest {
    key: string;
    label: string;
    species: string[];
    enabled: boolean;
}

interface OptionalSection {
    key: string;
    label: string;
    species: string[];
    enabled: boolean;
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

const form = reactive({
    breeder_id: props.analysis?.breeder_id ?? '',
    animal_nom: props.analysis?.animal_nom ?? '',
    sampled_at: props.analysis?.sampled_at?.slice(0, 10) ?? '',
    analyzed_at: props.analysis?.analyzed_at?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
    intervenant: props.analysis?.intervenant ?? '',
    payload: clonePlain(props.analysis?.payload ?? props.payloadTemplate) as {
        species: string;
        sample_nature: string;
        identification: string;
        commemoratifs: string;
        elisa: Record<string, string>;
        biochem_rapide: Record<string, string>;
        pcr: Record<string, string>;
        bandelette: Record<string, string>;
        frottis: Record<string, string>;
        commentaires: string;
    },
});
form.payload.elisa ??= {};
form.payload.biochem_rapide ??= {};
form.payload.pcr ??= {};
form.payload.bandelette ??= {};
form.payload.frottis ??= {};

const speciesOptions: string[] = Array.isArray(props.settings.species_options)
    ? props.settings.species_options
    : ['Bovin', 'Chien', 'Chat'];

const elisaTests = computed<ElisaTest[]>(() => {
    const all: ElisaTest[] = Array.isArray(props.settings.elisa_tests) ? props.settings.elisa_tests : [];
    return all.filter(t => t.enabled !== false && matchesSpecies(t.species, form.payload.species));
});

const pcrTests = computed<QualitativeTest[]>(() => {
    const all: QualitativeTest[] = Array.isArray(props.settings.pcr_tests) ? props.settings.pcr_tests : [];
    return all.filter(t => t.enabled !== false && matchesSpecies(t.species, form.payload.species));
});

const biochemTests = computed<BiochemTest[]>(() => {
    const all: BiochemTest[] = Array.isArray(props.settings.biochem_rapide) ? props.settings.biochem_rapide : [];
    return all.filter(t => t.enabled !== false && matchesSpecies(t.species, form.payload.species));
});

const optionalSections = computed<OptionalSection[]>(() =>
    Array.isArray(props.settings.optional_sections) ? props.settings.optional_sections : []
);
const showBandelette = computed(() => optionalSectionEnabled('bandelette_urinaire', props.settings.bandelette_urinaire === true));
const showFrottis = computed(() => optionalSectionEnabled('frottis_sanguin', props.settings.frottis_sanguin === true));

const BANDELETTE_ITEMS = [
    { key: 'densite', label: 'Densite urinaire' },
    { key: 'ph', label: 'pH' },
    { key: 'leucocytes', label: 'Leucocytes' },
    { key: 'nitrite', label: 'Nitrite' },
    { key: 'proteine', label: 'Proteine' },
    { key: 'glucose', label: 'Glucose' },
    { key: 'cetone', label: 'Cetone' },
    { key: 'urobilinogene', label: 'Urobilinogene' },
    { key: 'bilirubine', label: 'Bilirubine' },
    { key: 'sang', label: 'Sang' },
    { key: 'hemoglobine', label: 'Hemoglobine' },
];

const FROTTIS_ITEMS = [
    { key: 'babesia_canis', label: 'Babesia canis', species: ['Chien'] },
    { key: 'hemobartonnella_felis', label: 'Hemobartonnella felis', species: ['Chat'] },
    { key: 'ehrlichia_canis', label: 'Ehrlichia canis', species: ['Chien'] },
    { key: 'dirofilaria_immitis', label: 'Dirofilaria immitis', species: ['Chien', 'Chat'] },
    { key: 'hepatozoon_canis', label: 'Hepatozoon canis', species: ['Chien'] },
    { key: 'babesia_bovis', label: 'Babesia bovis', species: ['Bovin'] },
    { key: 'anaplasma_phago', label: 'Anaplasma phago.', species: ['Bovin', 'Chien'] },
    { key: 'babesia_equi', label: 'Babesia equi', species: ['Equin'] },
    { key: 'borrelia', label: 'Borrelia', species: ['Chien', 'Equin'] },
    { key: 'anaplasma_cv', label: 'Anaplasma CV', species: ['Equin'] },
];

const frottisItems = computed(() =>
    FROTTIS_ITEMS.filter(t => matchesSpecies(t.species, form.payload.species))
);

function matchesSpecies(speciesList: string[] | undefined, current: string): boolean {
    if (!Array.isArray(speciesList)) return true;
    if (speciesList.length === 0) return false;
    return speciesList.includes(current);
}

function optionalSectionEnabled(key: string, fallback: boolean): boolean {
    const section = optionalSections.value.find(item => item.key === key);

    if (!section) return fallback;

    return section.enabled !== false && matchesSpecies(section.species, form.payload.species);
}

function optionalSectionLabel(key: string, fallback: string): string {
    const section = optionalSections.value.find(item => item.key === key);

    return section?.label || fallback;
}

watch(() => form.payload.species, () => {
    // Reset ELISA results that no longer match the species
    const validElisaKeys = new Set(elisaTests.value.map(t => t.key));
    Object.keys(form.payload.elisa).forEach(k => {
        if (!validElisaKeys.has(k)) delete form.payload.elisa[k];
    });
    const validBiochemKeys = new Set(biochemTests.value.map(t => t.key));
    Object.keys(form.payload.biochem_rapide).forEach(k => {
        if (!validBiochemKeys.has(k)) delete form.payload.biochem_rapide[k];
    });
    const validPcrKeys = new Set(pcrTests.value.map(t => t.key));
    Object.keys(form.payload.pcr).forEach(k => {
        if (!validPcrKeys.has(k)) delete form.payload.pcr[k];
    });
    const validFrottisKeys = new Set(frottisItems.value.map(t => t.key));
    Object.keys(form.payload.frottis).forEach(k => {
        if (!validFrottisKeys.has(k)) delete form.payload.frottis[k];
    });
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: props.module.short_label, href: analysesIndex({ module: props.module.slug }).url },
    { title: isEdit.value ? 'Modifier' : 'Nouveau', href: '#' },
];

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
    <Head :title="isEdit ? `Modifier ${module.short_label}` : `Nouveau ${module.short_label}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="analysis-form mx-auto flex max-w-7xl flex-col gap-3 p-3 sm:p-4" @submit.prevent="submit">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-foreground">{{ isEdit ? 'Modifier' : 'Nouveau' }} {{ module.short_label }}</h1>
                    <p class="text-xs text-muted-foreground">{{ module.label }}</p>
                </div>
            </div>

            <!-- Identification -->
            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Identification</h2>
                        <p>Eleveur, animal et contexte.</p>
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
                        <label class="text-sm font-medium" for="animal_nom">Animal</label>
                        <input id="animal_nom" v-model="form.animal_nom" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Nom / ID" />
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="analyzed_at">Date</label>
                        <input id="analyzed_at" v-model="form.analyzed_at" type="date" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="intervenant">Intervenant</label>
                        <input id="intervenant" v-model="form.intervenant" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="species">Espece</label>
                        <select id="species" v-model="form.payload.species" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm">
                            <option v-for="sp in speciesOptions" :key="sp" :value="sp">{{ sp }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1 xl:col-span-3">
                        <label class="text-sm font-medium" for="sample_nature">Nature echantillon</label>
                        <input id="sample_nature" v-model="form.payload.sample_nature" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-1 xl:col-span-3">
                        <label class="text-sm font-medium" for="identification">Identification</label>
                        <input id="identification" v-model="form.payload.identification" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-1 xl:col-span-6">
                        <label class="text-sm font-medium" for="commemoratifs">Commemoratifs</label>
                        <input id="commemoratifs" v-model="form.payload.commemoratifs" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                </div>
            </section>

            <!-- ELISA Tests -->
            <section v-if="elisaTests.length > 0" class="analysis-section">
                <div class="analysis-section-heading">
                    <div><h2>Tests ELISA</h2></div>
                </div>
                <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div v-for="test in elisaTests" :key="test.key" class="grid gap-1 xl:col-span-2">
                        <label class="min-w-0 flex-1 text-xs">{{ test.label }}</label>
                        <select v-model="form.payload.elisa[test.key]" class="w-24 rounded border border-border bg-background px-1.5 py-1 text-xs">
                            <option value="">-</option>
                            <option value="pos">Positif</option>
                            <option value="neg">Negatif</option>
                            <option value="douteux">Douteux</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- PCR -->
            <section v-if="pcrTests.length > 0" class="analysis-section">
                <div class="analysis-section-heading">
                    <div><h2>PCR</h2></div>
                </div>
                <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div v-for="test in pcrTests" :key="test.key" class="grid gap-1 xl:col-span-2">
                        <label class="min-w-0 flex-1 text-xs">{{ test.label }}</label>
                        <select v-model="form.payload.pcr[test.key]" class="w-24 rounded border border-border bg-background px-1.5 py-1 text-xs">
                            <option value="">-</option>
                            <option value="pos">Positif</option>
                            <option value="neg">Negatif</option>
                            <option value="douteux">Douteux</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Biochimie rapide -->
            <section v-if="biochemTests.length > 0" class="analysis-section">
                <div class="analysis-section-heading">
                    <div><h2>Biochimie rapide</h2></div>
                </div>
                <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div v-for="test in biochemTests" :key="test.key" class="grid gap-1 xl:col-span-2">
                        <label class="min-w-0 flex-1 text-xs">{{ test.label }}<span v-if="test.unit" class="text-muted-foreground"> ({{ test.unit }})</span></label>
                        <input v-model="form.payload.biochem_rapide[test.key]" type="text" class="w-24 rounded border border-border bg-background px-1.5 py-1 text-xs" placeholder="-" />
                    </div>
                </div>
            </section>

            <!-- Bandelette urinaire -->
            <section v-if="showBandelette" class="analysis-section">
                <div class="analysis-section-heading">
                    <div><h2>{{ optionalSectionLabel('bandelette_urinaire', 'Bandelette urinaire') }}</h2></div>
                </div>
                <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div v-for="item in BANDELETTE_ITEMS" :key="item.key" class="grid gap-1 xl:col-span-2">
                        <label class="min-w-0 flex-1 text-xs">{{ item.label }}</label>
                        <input v-model="form.payload.bandelette[item.key]" type="text" class="w-24 rounded border border-border bg-background px-1.5 py-1 text-xs" placeholder="-" />
                    </div>
                </div>
            </section>

            <!-- Frottis sanguin -->
            <section v-if="showFrottis && frottisItems.length > 0" class="analysis-section">
                <div class="analysis-section-heading">
                    <div><h2>{{ optionalSectionLabel('frottis_sanguin', 'Frottis sanguin') }}</h2></div>
                </div>
                <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div v-for="item in frottisItems" :key="item.key" class="grid gap-1 xl:col-span-2">
                        <label class="min-w-0 flex-1 text-xs">{{ item.label }}</label>
                        <select v-model="form.payload.frottis[item.key]" class="w-24 rounded border border-border bg-background px-1.5 py-1 text-xs">
                            <option value="">-</option>
                            <option value="pos">Positif</option>
                            <option value="neg">Negatif</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Commentaires -->
            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div><h2>Commentaires</h2></div>
                </div>
                <textarea v-model="form.payload.commentaires" rows="3" class="rounded border border-border bg-background px-2 py-1 text-sm" placeholder="Commentaires..."></textarea>
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
                    {{ processing ? 'Enregistrement...' : 'Enregistrer' }}
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
.analysis-form textarea {
    width: 100%;
    min-height: 3.5rem;
    border-radius: 0.375rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.8125rem;
    line-height: 1.25rem;
}
.analysis-form :is(input:not([type='checkbox']):not([type='hidden']), select, textarea):focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 22%, transparent);
    outline: none;
}
:deep(.breeder-create-btn) {
    height: 1.875rem;
    width: 1.875rem;
    min-height: 1.875rem;
}
</style>
