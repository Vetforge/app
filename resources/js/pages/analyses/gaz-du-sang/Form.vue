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

const bloodGasLabFields = [
    { key: 'ph', label: 'pH', unit: '', step: '0.01' },
    { key: 'pco2', label: 'pCO2', unit: 'mmHg', step: '0.1' },
    { key: 'hco3', label: 'HCO3', unit: 'mmol/L', step: '0.1' },
    { key: 'angap', label: 'Anion gap', unit: 'mmol/L', step: '0.1' },
    { key: 'tco2', label: 'TCO2', unit: 'mmol/L', step: '0.1' },
    { key: 'na', label: 'Na', unit: 'mmol/L', step: '0.1' },
    { key: 'k', label: 'K', unit: 'mmol/L', step: '0.1' },
    { key: 'cl', label: 'Cl', unit: 'mmol/L', step: '0.1' },
    { key: 'glycemia', label: 'Glycemie', unit: 'mg/dL', step: '0.1' },
];

const bloodGasNormFields = [
    { key: 'ph', label: 'pH' },
    { key: 'pco2', label: 'pCO2' },
    { key: 'hco3', label: 'HCO3' },
    { key: 'na', label: 'Na' },
    { key: 'k', label: 'K' },
    { key: 'cl', label: 'Cl' },
    { key: 'glycemia', label: 'Glycemie' },
];

const speciesOptions = computed(() => {
    const options = Array.isArray(props.settings.species_options) ? props.settings.species_options : [];
    if (options.length > 0) {
        return options
            .map((option: Record<string, any>) => ({
                value: String(option.value ?? option.label ?? ''),
                label: String(option.label ?? option.value ?? ''),
                normKey: String(option.norm_key ?? option.value ?? option.label ?? ''),
                calculationProfile: String(option.calculation_profile ?? defaultBloodGasProfile(String(option.value ?? ''))),
            }))
            .filter((option) => option.value !== '');
    }
    const norms = isRecord(props.settings.norms) ? props.settings.norms : {};
    return Object.keys(norms).map((key) => ({
        value: key,
        label: key,
        normKey: key,
        calculationProfile: defaultBloodGasProfile(key),
    }));
});

const selectedSpecies = computed(() => {
    const species = String(form.payload.species ?? '');
    return (
        speciesOptions.value.find((o) => o.value === species) ??
        speciesOptions.value[0] ?? {
            value: species || 'Bovin',
            label: species || 'Bovin',
            normKey: species || 'Bovin',
            calculationProfile: defaultBloodGasProfile(species || 'Bovin'),
        }
    );
});

const selectedNorms = computed(() => {
    const norms = isRecord(props.settings.norms) ? props.settings.norms : {};
    const legacyKeys: Record<string, string> = { Bovin: 'bovine', Equin: 'equine' };
    const candidates = [selectedSpecies.value.normKey, selectedSpecies.value.value, legacyKeys[selectedSpecies.value.value]].filter(Boolean);
    for (const candidate of candidates) {
        if (isRecord(norms[candidate])) return normalizeNorms(norms[candidate]);
    }
    return {};
});

const normRows = computed(() =>
    bloodGasNormFields.map((field) => {
        const range = selectedNorms.value[field.key];
        const value = toNumber(form.payload[field.key]);
        const status = value === null || !range ? 'unknown' : value < range[0] ? 'low' : value > range[1] ? 'high' : 'normal';
        return { ...field, value, range, status };
    }),
);

const perfusionRows = computed(() => {
    const items = props.settings.perfusions;
    if (!Array.isArray(items)) return [];
    return items.filter((item: any) => item.enabled !== false);
});

const calculation = computed(() => {
    const profile = selectedSpecies.value.calculationProfile;
    const weight = toNumber(form.payload.weight);
    const explicitDehydration = toNumber(form.payload.dehydration);
    const enophtalmie = toNumber(form.payload.enophtalmie);
    const ph = toNumber(form.payload.ph);
    const hco3 = toNumber(form.payload.hco3);
    const glycemia = toNumber(form.payload.glycemia);
    const dehydration = profile === 'ruminant'
        ? (explicitDehydration ?? (enophtalmie === null ? null : round1(1.7 * enophtalmie + 0.38)))
        : explicitDehydration;
    const baseHco3 = profile === 'ruminant' ? 24.8 : 25.6;
    const basePh = profile === 'ruminant' ? 7.4 : 7.37;
    const volumeDeficit = weight === null || dehydration === null ? null : round1((weight * dehydration) / 100);
    const bicarbonateDeficit = weight === null || ph === null || hco3 === null ? null
        : Math.round(-(((hco3 - baseHco3 + 16.2 * (ph - basePh)) * (weight * 0.6) * 84) / 1000));
    const glucoseDeficit = glycemia === null ? null
        : profile === 'ruminant'
          ? glycemia < 54 ? 100 : glycemia < 90 ? 50 : 0
          : glycemia < 70 ? 100 : glycemia < 120 ? 50 : 0;
    const apports = perfusionRows.value.reduce(
        (totals, perfusion) => {
            const quantity = toNumber(form.payload.perfusions?.[perfusion.key]) ?? 0;
            totals.bicarbonateG += quantity * (toNumber(perfusion.bicarbonate) ?? 0);
            totals.glucoseG += quantity * (toNumber(perfusion.glucose) ?? 0);
            totals.volumeL += quantity * (toNumber(perfusion.volume) ?? 0);
            return totals;
        },
        { bicarbonateG: 0, glucoseG: 0, volumeL: 0 },
    );
    apports.bicarbonateG = Math.round(apports.bicarbonateG);
    apports.glucoseG = Math.round(apports.glucoseG);
    apports.volumeL = round2(apports.volumeL);
    return {
        profile,
        dehydration,
        volumeDeficit,
        bicarbonateDeficit,
        glucoseDeficit,
        apports,
        restes: {
            bicarbonateG: bicarbonateDeficit === null ? null : Math.round(bicarbonateDeficit - apports.bicarbonateG),
            glucoseG: glucoseDeficit === null ? null : Math.round(glucoseDeficit - apports.glucoseG),
            volumeL: volumeDeficit === null ? null : round1(volumeDeficit - apports.volumeL),
        },
    };
});

function defaultBloodGasProfile(species: string): string {
    return ['Bovin', 'Ovin', 'Caprin'].includes(species) ? 'ruminant' : 'equine';
}

function isRecord(value: unknown): value is Record<string, any> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function normalizeNorms(norms: Record<string, any>): Record<string, [number, number]> {
    return Object.fromEntries(
        bloodGasNormFields
            .map((field) => {
                const range = norms[field.key];
                if (!Array.isArray(range) || range.length < 2) return null;
                return [field.key, [Number(range[0]), Number(range[1])]];
            })
            .filter((entry): entry is [string, [number, number]] => entry !== null),
    );
}

function toNumber(value: unknown): number | null {
    if (value === null || value === undefined || value === '') return null;
    const normalized = typeof value === 'string' ? value.replace(',', '.') : value;
    const number = Number(normalized);
    return Number.isFinite(number) ? number : null;
}

function round1(value: number): number {
    return Math.round(value * 10) / 10;
}

function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

function formatResult(value: number | null | undefined, unit = ''): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '-';
    return `${value}${unit ? ` ${unit}` : ''}`;
}

function normStatusLabel(status: string): string {
    return status === 'low' ? 'Bas' : status === 'high' ? 'Haut' : status === 'normal' ? 'OK' : '-';
}

function normStatusClass(status: string): string {
    if (status === 'normal') return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    if (status === 'low' || status === 'high') return 'bg-red-50 text-red-700 ring-red-200';
    return 'bg-muted text-muted-foreground ring-border';
}

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

watch(
    speciesOptions,
    (options) => {
        if (options.length > 0 && !options.some((o) => o.value === form.payload.species)) {
            form.payload.species = options[0].value;
        }
    },
    { immediate: true },
);

watch(
    perfusionRows,
    (rows) => {
        if (!isRecord(form.payload.perfusions)) {
            form.payload.perfusions = {};
        }
        rows.forEach((perfusion) => {
            if (form.payload.perfusions[perfusion.key] === undefined) {
                form.payload.perfusions[perfusion.key] = 0;
            }
        });
    },
    { immediate: true },
);

watch(
    () => form.payload.enophtalmie,
    (val) => {
        const n = val !== null && val !== '' && val !== undefined ? Number(val) : null;
        form.payload.dehydration = n !== null ? Math.round(1.7 * n + 0.38) : null;
    },
);
</script>

<template>
    <Head :title="isEdit ? `Modifier ${module.short_label}` : `Nouvelle ${module.short_label}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="analysis-form mx-auto flex max-w-7xl flex-col gap-3 p-3 sm:p-4" @submit.prevent="submit">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-foreground">{{ isEdit ? 'Modifier analyse' : 'Nouvelle analyse' }}</h1>
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
                        <p>Eleveur, animal et dates du dossier.</p>
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
                        <input id="animal_nom" v-model="form.animal_nom" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Nom libre" />
                        <InputError :message="errors.animal_nom" />
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="sampled_at">Prelevement</label>
                        <input id="sampled_at" v-model="form.sampled_at" type="date" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                        <InputError :message="errors.sampled_at" />
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="analyzed_at">Analyse</label>
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
                        <h2>Gaz du sang</h2>
                        <p>Saisie clinique, valeurs mesurees et perfusions.</p>
                    </div>
                </div>

                <div class="grid gap-2 xl:grid-cols-[minmax(18rem,0.85fr)_minmax(0,1.6fr)]">
                    <div class="analysis-subsection">
                        <h3>Contexte clinique</h3>
                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                            <div class="grid gap-1">
                                <label class="text-sm font-medium" for="blood_species">Espece</label>
                                <select id="blood_species" v-model="form.payload.species" class="rounded border border-border bg-background px-2 py-1 text-sm">
                                    <option v-for="species in speciesOptions" :key="species.value" :value="species.value">{{ species.label }}</option>
                                </select>
                            </div>
                            <div class="grid gap-1">
                                <label class="text-sm font-medium" for="blood-weight">Poids</label>
                                <div class="analysis-unit-field analysis-unit-field--with-unit">
                                    <input id="blood-weight" v-model="form.payload.weight" type="number" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                                    <span class="analysis-unit">kg</span>
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <label class="text-sm font-medium" for="blood-enophtalmie">Enophtalmie</label>
                                <div class="analysis-unit-field analysis-unit-field--with-unit">
                                    <input id="blood-enophtalmie" v-model="form.payload.enophtalmie" type="number" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                                    <span class="analysis-unit">mm</span>
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <label class="text-sm font-medium" for="blood-dehydration">Deshydratation</label>
                                <div class="analysis-unit-field analysis-unit-field--with-unit">
                                    <input id="blood-dehydration" v-model="form.payload.dehydration" type="number" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" @input="form.payload.enophtalmie = null" />
                                    <span class="analysis-unit">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="analysis-subsection">
                        <h3>Valeurs mesurees</h3>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <div v-for="field in bloodGasLabFields" :key="field.key" class="grid gap-1">
                                <label class="text-sm font-medium" :for="`blood-${field.key}`">{{ field.label }}</label>
                                <div class="analysis-unit-field" :class="{ 'analysis-unit-field--with-unit': field.unit }">
                                    <input :id="`blood-${field.key}`" v-model="form.payload[field.key]" type="number" :step="field.step" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                                    <span v-if="field.unit" class="analysis-unit">{{ field.unit }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="analysis-subsection">
                    <h3>Perfusions administrees</h3>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="perfusion in perfusionRows" :key="perfusion.key" class="grid gap-1">
                            <label class="text-sm font-medium" :for="`perf-${perfusion.key}`">{{ perfusion.label }}</label>
                            <div class="analysis-unit-field" :class="{ 'analysis-unit-field--with-unit': perfusion.unit }">
                                <input :id="`perf-${perfusion.key}`" v-model.number="form.payload.perfusions[perfusion.key]" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                                <span v-if="perfusion.unit" class="analysis-unit">{{ perfusion.unit }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-2 xl:grid-cols-[1fr_1fr]">
                    <div class="analysis-subsection">
                        <div>
                            <h3>Resultats calcules</h3>
                            <p class="text-sm text-muted-foreground">Profil {{ calculation.profile === 'ruminant' ? 'ruminant' : 'equin / autre' }}</p>
                        </div>
                        <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-2">
                            <div class="rounded-md bg-muted/50 p-2">
                                <p class="text-[0.65rem] text-muted-foreground uppercase">Deshydratation</p>
                                <p class="text-base font-semibold">{{ formatResult(calculation.dehydration, '%') }}</p>
                            </div>
                            <div class="rounded-md bg-muted/50 p-2">
                                <p class="text-[0.65rem] text-muted-foreground uppercase">Deficit hydrique</p>
                                <p class="text-base font-semibold">{{ formatResult(calculation.volumeDeficit, 'L') }}</p>
                            </div>
                            <div class="rounded-md bg-muted/50 p-2">
                                <p class="text-[0.65rem] text-muted-foreground uppercase">Deficit bicarbonate</p>
                                <p class="text-base font-semibold">{{ formatResult(calculation.bicarbonateDeficit, 'g') }}</p>
                            </div>
                            <div class="rounded-md bg-muted/50 p-2">
                                <p class="text-[0.65rem] text-muted-foreground uppercase">Deficit glucose</p>
                                <p class="text-base font-semibold">{{ formatResult(calculation.glucoseDeficit, 'g') }}</p>
                            </div>
                        </div>
                        <div class="analysis-table-wrap">
                            <table class="w-full text-sm">
                                <thead class="hidden sm:table-header-group">
                                    <tr class="border-b border-border bg-muted/40">
                                        <th class="px-2 py-1 text-left font-medium text-xs">Poste</th>
                                        <th class="px-2 py-1 text-left font-medium text-xs">Bicarbonate</th>
                                        <th class="px-2 py-1 text-left font-medium text-xs">Glucose</th>
                                        <th class="px-2 py-1 text-left font-medium text-xs">Volume</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-border/50">
                                        <td class="px-2 py-1">
                                            <div class="font-medium">Apports perfusions</div>
                                            <div class="mt-0.5 flex flex-wrap gap-x-2 gap-y-0.5 text-xs text-muted-foreground sm:hidden">
                                                <span>Bicarb : {{ formatResult(calculation.apports.bicarbonateG, 'g') }}</span>
                                                <span>Glucose : {{ formatResult(calculation.apports.glucoseG, 'g') }}</span>
                                                <span>Vol : {{ formatResult(calculation.apports.volumeL, 'L') }}</span>
                                            </div>
                                        </td>
                                        <td class="hidden px-2 py-1 sm:table-cell">{{ formatResult(calculation.apports.bicarbonateG, 'g') }}</td>
                                        <td class="hidden px-2 py-1 sm:table-cell">{{ formatResult(calculation.apports.glucoseG, 'g') }}</td>
                                        <td class="hidden px-2 py-1 sm:table-cell">{{ formatResult(calculation.apports.volumeL, 'L') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-2 py-1">
                                            <div class="font-medium">Reste a couvrir</div>
                                            <div class="mt-0.5 flex flex-wrap gap-x-2 gap-y-0.5 text-xs text-muted-foreground sm:hidden">
                                                <span>Bicarb : {{ formatResult(calculation.restes.bicarbonateG, 'g') }}</span>
                                                <span>Glucose : {{ formatResult(calculation.restes.glucoseG, 'g') }}</span>
                                                <span>Vol : {{ formatResult(calculation.restes.volumeL, 'L') }}</span>
                                            </div>
                                        </td>
                                        <td class="hidden px-2 py-1 sm:table-cell">{{ formatResult(calculation.restes.bicarbonateG, 'g') }}</td>
                                        <td class="hidden px-2 py-1 sm:table-cell">{{ formatResult(calculation.restes.glucoseG, 'g') }}</td>
                                        <td class="hidden px-2 py-1 sm:table-cell">{{ formatResult(calculation.restes.volumeL, 'L') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="analysis-subsection">
                        <div>
                            <h3>Normes {{ selectedSpecies.label }}</h3>
                            <p class="text-sm text-muted-foreground">Lecture des valeurs saisies par rapport aux references de l'espece.</p>
                        </div>
                        <div class="analysis-table-wrap">
                            <table class="w-full text-sm">
                                <thead class="hidden sm:table-header-group">
                                    <tr class="border-b border-border bg-muted/40">
                                        <th class="px-2 py-1 text-left font-medium text-xs">Parametre</th>
                                        <th class="px-2 py-1 text-left font-medium text-xs">Valeur</th>
                                        <th class="px-2 py-1 text-left font-medium text-xs">Norme</th>
                                        <th class="px-2 py-1 text-left font-medium text-xs">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in normRows" :key="row.key" class="border-b border-border/50 last:border-b-0">
                                        <td class="px-2 py-1">
                                            <div class="font-medium">{{ row.label }}</div>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs sm:hidden">
                                                <span>{{ formatResult(row.value) }}</span>
                                                <span class="text-muted-foreground"><span v-if="row.range">{{ row.range[0] }} - {{ row.range[1] }}</span><span v-else>-</span></span>
                                                <span class="inline-flex rounded-full px-1.5 py-0.5 font-medium ring-1" :class="normStatusClass(row.status)">{{ normStatusLabel(row.status) }}</span>
                                            </div>
                                        </td>
                                        <td class="hidden px-2 py-1 sm:table-cell">{{ formatResult(row.value) }}</td>
                                        <td class="hidden px-2 py-1 text-muted-foreground sm:table-cell">
                                            <span v-if="row.range">{{ row.range[0] }} - {{ row.range[1] }}</span>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="hidden px-2 py-1 sm:table-cell">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium ring-1" :class="normStatusClass(row.status)">{{ normStatusLabel(row.status) }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="grid gap-1">
                    <label class="text-sm font-medium" for="treatment">Traitement / remarques</label>
                    <textarea id="treatment" v-model="form.payload.treatment" rows="3" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
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
                    {{ processing ? 'Enregistrement...' : "Enregistrer l'analyse" }}
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
.analysis-section-heading h2,
.analysis-subsection h3 {
    font-size: 0.8125rem;
    font-weight: 650;
    line-height: 1.25;
}
.analysis-section-heading p {
    margin-top: 0.0625rem;
    color: var(--muted-foreground);
    font-size: 0.7rem;
}
.analysis-subsection {
    display: grid;
    gap: 0.5rem;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    background: color-mix(in srgb, var(--muted) 28%, transparent);
    padding: 0.625rem;
    min-width: 0;
}
.analysis-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
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
.analysis-form input[type='checkbox'] {
    width: 0.875rem;
    height: 0.875rem;
    accent-color: var(--primary);
}
.analysis-form table :is(input:not([type='checkbox']):not([type='hidden']), select) {
    height: 1.625rem;
    min-height: 1.625rem;
    padding: 0.125rem 0.375rem;
}
.analysis-form table select {
    padding-right: 1.5rem;
}
.analysis-form th,
.analysis-form td {
    vertical-align: middle;
}
.analysis-unit-field {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-items: stretch;
}
.analysis-unit-field--with-unit {
    grid-template-columns: minmax(0, 1fr) max-content;
}
.analysis-unit-field input {
    height: 1.875rem;
    width: 100%;
    min-width: 0;
}
.analysis-unit-field--with-unit input {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}
.analysis-unit {
    display: inline-flex;
    height: 1.875rem;
    min-width: 2.75rem;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-left: 0;
    border-radius: 0 0.375rem 0.375rem 0;
    background: color-mix(in srgb, var(--muted) 36%, var(--background));
    padding: 0 0.375rem;
    color: var(--muted-foreground);
    font-size: 0.6875rem;
    line-height: 1;
    pointer-events: none;
    white-space: nowrap;
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
