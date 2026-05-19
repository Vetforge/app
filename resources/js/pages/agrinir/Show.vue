<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Database,
    FlaskConical,
    Save,
    RefreshCw,
    ChevronRight,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    show as agrinirShow,
    calculer as agrinirCalculer,
    sauvegarder as agrinirSauvegarder,
    types as agrinirTypes,
} from '@/actions/App/Http/Controllers/AgrinirController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Calculateur AgriNIR', href: agrinirShow().url },
];

// ─── Définitions de types ──────────────────────────────────────────────────────

interface TypeOption {
    value: string;
    label: string;
}

interface TypeGroup {
    label: string;
    options: TypeOption[];
}

interface ReferenceAliment {
    id: number;
    code_inra: string | null;
    type: string | null;
    libelle0: string;
    libelle1: string | null;
    label: string;
}

interface Resultats {
    ms: number;
    mat: number;
    ndf: number;
    adf: number;
    mo: number;
    cb: number;
    eb: number;
    em: number;
    dmo: number;
    niref: number;
    dt_n: number;
    dr_n: number;
    ufl: number;
    ufv: number;
    pdia: number;
    pdi: number;
    bpr: number;
    uem: number;
    uel: number;
    ueb: number;
    ca?: number;
    caabs?: number;
    p?: number;
    pabs?: number;
    mg?: number;
}

interface Resultats2007 {
    ufl2007: number | null;
    ufv2007: number | null;
    pdia2007: number | null;
    pdie2007: number | null;
    pdin2007: number | null;
    dmo2007: number | null;
    dma2007: number | null;
    uem2007: number | null;
    uel2007: number | null;
    ueb2007: number | null;
}

type ParamFieldKey =
    | 'humidite'
    | 'proteine'
    | 'ndf'
    | 'adf'
    | 'cendres'
    | 'matiere_grasse'
    | 'amidon'
    | 'ca'
    | 'p'
    | 'mg';
type ParamValue = number | '';
type ParamState = Record<ParamFieldKey, ParamValue>;

interface ParamField {
    key: ParamFieldKey;
    label: string;
    unit: string;
}

const props = defineProps<{
    referenceAliments: ReferenceAliment[];
}>();

// ─── État ──────────────────────────────────────────────────────────────────────

const inra = '2018' as const;
const famille = ref('');
const typeSelected = ref('');
const typeGroups = ref<TypeGroup[]>([]);
const loadingTypes = ref(false);

const requiredFields: ParamField[] = [
    { key: 'humidite', label: 'Humidité', unit: '%' },
    { key: 'proteine', label: 'MAT brute', unit: '% MS' },
    { key: 'ndf', label: 'NDF', unit: '% MS' },
    { key: 'adf', label: 'ADF', unit: '% MS' },
    { key: 'cendres', label: 'Cendres', unit: '% MS' },
    { key: 'matiere_grasse', label: 'Matière grasse', unit: '% MS' },
];

const mineralFields: ParamField[] = [
    { key: 'ca', label: 'Ca', unit: '% MS' },
    { key: 'p', label: 'P', unit: '% MS' },
    { key: 'mg', label: 'Mg', unit: '% MS' },
];

const paramFieldKeys: ParamFieldKey[] = [
    'humidite',
    'proteine',
    'ndf',
    'adf',
    'cendres',
    'matiere_grasse',
    'amidon',
    'ca',
    'p',
    'mg',
];

const params = ref<ParamState>(makeDefaultParams());

const loading = ref(false);
const resultats = ref<Resultats | null>(null);
const resultats2007 = ref<Resultats2007 | null>(null);
const error = ref('');
const fieldErrors = ref<Partial<Record<ParamFieldKey, string>>>({});
const saveNom = ref('');
const saving = ref(false);
const saved = ref(false);
const referenceQuery = ref('');
const referenceAlimentId = ref<number | null>(null);

// ─── Propriétés calculées ──────────────────────────────────────────────────────

const familles = [
    { value: 'herbeG', label: 'Herbe — Graminées' },
    { value: 'herbePP', label: 'Herbe — Prairie permanente' },
    { value: 'mais', label: 'Maïs / Sorgho' },
    { value: 'legumineuse', label: 'Légumineuses (Luzerne…)' },
];

const needsAmidon = computed(() =>
    ['maisE', 'maisFV', 'sorghoE', 'sorghoFV'].includes(typeSelected.value),
);

const allTypeOptions = computed<TypeOption[]>(() =>
    typeGroups.value.flatMap((g) => g.options),
);

const selectedTypeLabel = computed(
    () =>
        allTypeOptions.value.find((o) => o.value === typeSelected.value)
            ?.label ?? typeSelected.value,
);

const referenceResultLimit = 40;

const matchingReferenceAliments = computed<ReferenceAliment[]>(() => {
    const queryTokens = tokenizeReferenceText(referenceQuery.value);

    if (queryTokens.length === 0) {
        return props.referenceAliments;
    }

    return props.referenceAliments.filter((aliment) => {
        const searchableText = normalizeReferenceText(
            [
                aliment.label,
                aliment.libelle0,
                aliment.libelle1,
                aliment.type,
                aliment.code_inra,
            ].join(' '),
        );

        return queryTokens.every((token) => searchableText.includes(token));
    });
});

const visibleReferenceAliments = computed<ReferenceAliment[]>(() =>
    matchingReferenceAliments.value.slice(0, referenceResultLimit),
);

const hasMoreReferenceAliments = computed(
    () =>
        matchingReferenceAliments.value.length >
        visibleReferenceAliments.value.length,
);

const selectedReferenceAliment = computed<ReferenceAliment | null>(
    () =>
        props.referenceAliments.find(
            (aliment) => aliment.id === referenceAlimentId.value,
        ) ?? null,
);

const step = computed<1 | 2 | 3>(() => {
    if (!famille.value || !typeSelected.value) return 1;
    if (!resultats.value) return 2;
    return 3;
});

// ─── Observateurs ───────────────────────────────────────────────────────────────

watch(famille, async (fam) => {
    typeSelected.value = '';
    typeGroups.value = [];
    resultats.value = null;
    resultats2007.value = null;
    resetCalculationFeedback();
    if (!fam) {
        return;
    }
    loadingTypes.value = true;
    try {
        const res = await fetch(
            agrinirTypes({ inra }, { query: { famille: fam } }).url,
        );
        const data = await res.json();
        typeGroups.value = data.groups ?? [];
    } finally {
        loadingTypes.value = false;
    }
});

watch(typeSelected, () => {
    resultats.value = null;
    resultats2007.value = null;
    resetCalculationFeedback();
    saved.value = false;
    saveNom.value = '';
});

watch(referenceAlimentId, () => {
    resultats.value = null;
    resultats2007.value = null;
    resetCalculationFeedback();
    saved.value = false;
});

// ─── Actions ───────────────────────────────────────────────────────────────────

async function calculer() {
    resetCalculationFeedback();
    resultats.value = null;
    resultats2007.value = null;
    loading.value = true;
    try {
        const csrfToken = resolveCsrfToken();
        const body: Record<string, unknown> = {
            type: typeSelected.value,
            inra,
            aliment_de_reference_id: referenceAlimentId.value,
            params: {
                humidite: params.value.humidite,
                proteine: params.value.proteine,
                ndf: params.value.ndf,
                adf: params.value.adf,
                cendres: params.value.cendres,
                matiere_grasse: params.value.matiere_grasse,
                amidon: needsAmidon.value ? params.value.amidon : null,
                ca: params.value.ca !== '' ? params.value.ca : null,
                p: params.value.p !== '' ? params.value.p : null,
                mg: params.value.mg !== '' ? params.value.mg : null,
            },
        };

        const res = await fetch(agrinirCalculer().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            applyCalculationErrors(data);
        } else {
            resultats.value =
                isRecord(data) && 'resultats' in data
                    ? (data.resultats as Resultats)
                    : null;
            resultats2007.value =
                isRecord(data) && 'resultats2007' in data
                    ? (data.resultats2007 as Resultats2007)
                    : null;
            saved.value = false;
        }
    } catch {
        error.value = 'Erreur réseau.';
    } finally {
        loading.value = false;
    }
}

function sauvegarder() {
    if (!saveNom.value.trim() || !resultats.value) {
        return;
    }
    saving.value = true;
    router.post(
        agrinirSauvegarder().url,
        {
            nom: saveNom.value.trim(),
            type: typeSelected.value,
            inra,
            aliment_de_reference_id: referenceAlimentId.value,
            params: {
                humidite: params.value.humidite,
                proteine: params.value.proteine,
                ndf: params.value.ndf,
                adf: params.value.adf,
                cendres: params.value.cendres,
                matiere_grasse: params.value.matiere_grasse,
                amidon: needsAmidon.value ? params.value.amidon : null,
                ca: params.value.ca !== '' ? params.value.ca : null,
                p: params.value.p !== '' ? params.value.p : null,
                mg: params.value.mg !== '' ? params.value.mg : null,
            },
            valeurs: resultats.value,
        },
        {
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function fmt(val: number | undefined | null, dec = 2): string {
    return formatNumber(val, dec);
}

function reset() {
    famille.value = '';
    typeSelected.value = '';
    typeGroups.value = [];
    params.value = makeDefaultParams();
    resultats.value = null;
    resultats2007.value = null;
    resetCalculationFeedback();
    saveNom.value = '';
    referenceQuery.value = '';
    referenceAlimentId.value = null;
    saved.value = false;
}

function normalizeReferenceText(
    value: string | number | null | undefined,
): string {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9]+/g, ' ')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

function tokenizeReferenceText(
    value: string | number | null | undefined,
): string[] {
    const normalizedValue = normalizeReferenceText(value);

    if (!normalizedValue) {
        return [];
    }

    return normalizedValue.split(' ');
}

function resolveCsrfToken(): string {
    const metaToken = (
        document.querySelector(
            'meta[name="csrf-token"]',
        ) as HTMLMetaElement | null
    )?.content;

    if (metaToken) {
        return metaToken;
    }

    const xsrfCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');

    return xsrfCookie ? decodeURIComponent(xsrfCookie) : '';
}

function makeDefaultParams(): ParamState {
    return {
        humidite: '',
        proteine: '',
        ndf: '',
        adf: '',
        cendres: '',
        matiere_grasse: '',
        amidon: '',
        ca: '',
        p: '',
        mg: '',
    };
}

function resetCalculationFeedback(): void {
    error.value = '';
    fieldErrors.value = {};
}

function applyCalculationErrors(payload: unknown): void {
    const response = isRecord(payload) ? payload : {};
    const validationErrors = normalizeCalculationErrors(response.errors);
    const responseMessage =
        typeof response.message === 'string' ? response.message : null;
    const explicitError =
        typeof response.error === 'string' ? response.error : null;

    fieldErrors.value = validationErrors;
    error.value =
        explicitError ??
        firstFieldError(validationErrors) ??
        (responseMessage && responseMessage !== 'The given data was invalid.'
            ? responseMessage
            : null) ??
        'Erreur de calcul.';
}

function normalizeCalculationErrors(
    errors: unknown,
): Partial<Record<ParamFieldKey, string>> {
    if (!isRecord(errors)) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(errors)
            .map(([key, messages]) => {
                const [, fieldKey] = key.split('.');

                if (
                    !fieldKey ||
                    !paramFieldKeys.includes(fieldKey as ParamFieldKey)
                ) {
                    return null;
                }

                const message = Array.isArray(messages)
                    ? messages.find(
                          (value): value is string => typeof value === 'string',
                      )
                    : typeof messages === 'string'
                      ? messages
                      : null;

                if (!message) {
                    return null;
                }

                return [fieldKey as ParamFieldKey, message];
            })
            .filter(
                (entry): entry is [ParamFieldKey, string] => entry !== null,
            ),
    );
}

function firstFieldError(
    errors: Partial<Record<ParamFieldKey, string>>,
): string | null {
    for (const key of paramFieldKeys) {
        if (errors[key]) {
            return errors[key] ?? null;
        }
    }

    return null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}
</script>

<template>
    <Head title="Calculateur AgriNIR" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto p-6">
            <!-- En-tête -->
            <div class="mb-6 flex items-center gap-3">
                <FlaskConical class="size-7 text-primary" />
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        Calculateur AgriNIR
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Calcul des valeurs alimentaires des fourrages par
                        spectroscopie NIR
                    </p>
                </div>
            </div>

            <!-- Indicateur d'étape -->
            <div class="mb-8 flex flex-wrap items-center gap-2 text-sm">
                <span
                    :class="
                        step >= 1
                            ? 'font-medium text-primary'
                            : 'text-muted-foreground'
                    "
                    >Type de fourrage</span
                >
                <ChevronRight class="size-4 text-muted-foreground/50" />
                <span
                    :class="
                        step >= 2
                            ? 'font-medium text-primary'
                            : 'text-muted-foreground'
                    "
                    >Paramètres NIR</span
                >
                <ChevronRight class="size-4 text-muted-foreground/50" />
                <span
                    :class="
                        step >= 3
                            ? 'font-medium text-primary'
                            : 'text-muted-foreground'
                    "
                    >Résultats</span
                >
            </div>

            <!-- ── Étape 1 : Sélection du type ─────────────────────────────── -->
            <div
                class="mb-6 rounded-xl border border-border bg-card p-6 shadow-sm"
            >
                <h2 class="mb-4 text-base font-semibold text-foreground">
                    1. Référentiels calculés & type de fourrage
                </h2>

                <!-- Famille -->
                <div class="mb-4">
                    <label
                        class="mb-1.5 block text-sm font-medium text-foreground"
                        >Famille</label
                    >
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="f in familles"
                            :key="f.value"
                            type="button"
                            class="rounded-lg border px-3 py-2.5 text-left text-sm transition-colors"
                            :class="
                                famille === f.value
                                    ? 'border-primary bg-primary/5 font-medium text-primary'
                                    : 'border-border hover:border-primary/50'
                            "
                            @click="famille = f.value"
                        >
                            {{ f.label }}
                        </button>
                    </div>
                </div>

                <!-- Sous-type -->
                <div v-if="famille">
                    <label
                        class="mb-1.5 block text-sm font-medium text-foreground"
                        >Type spécifique</label
                    >
                    <div
                        v-if="loadingTypes"
                        class="flex items-center gap-2 py-2 text-sm text-muted-foreground"
                    >
                        <RefreshCw class="size-4 animate-spin" />
                        Chargement…
                    </div>
                    <select
                        v-else
                        v-model="typeSelected"
                        class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    >
                        <option value="">Sélectionner un type…</option>
                        <optgroup
                            v-for="group in typeGroups"
                            :key="group.label"
                            :label="group.label"
                        >
                            <option
                                v-for="opt in group.options"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <!-- ── Étape 2 : Formulaire de paramètres NIR ─────────────────── -->
            <div
                v-if="typeSelected"
                class="mb-6 rounded-xl border border-border bg-card p-6 shadow-sm"
            >
                <h2 class="mb-4 text-base font-semibold text-foreground">
                    2. Paramètres d'analyse NIR
                </h2>
                <p class="mb-4 text-sm text-muted-foreground">
                    Saisir les valeurs brutes mesurées par spectroscopie. Les
                    résultats INRA 2018 et INRA 2007 seront calculés
                    automatiquement.
                </p>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="field in requiredFields"
                        :key="field.key"
                        class="flex flex-col gap-1"
                    >
                        <label class="text-xs font-medium text-foreground">
                            {{ field.label }}
                            <span class="text-muted-foreground"
                                >({{ field.unit }})</span
                            >
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            v-model="params[field.key]"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                            :placeholder="field.label"
                        />
                        <InputError :message="fieldErrors[field.key]" />
                    </div>

                    <div v-if="needsAmidon" class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-foreground">
                            Amidon
                            <span class="text-muted-foreground">(% MS)</span>
                            <span class="ml-1 text-xs text-primary">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            v-model="params.amidon"
                            class="rounded-lg border border-primary/50 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                            placeholder="Amidon"
                        />
                        <InputError :message="fieldErrors.amidon" />
                    </div>
                </div>

                <div class="mt-4 border-t border-border pt-4">
                    <p
                        class="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        MINÉRAUX (OPTIONNEL)
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="min in mineralFields"
                            :key="min.key"
                            class="flex flex-col gap-1"
                        >
                            <label class="text-xs font-medium text-foreground">
                                {{ min.label }}
                                <span class="text-muted-foreground"
                                    >({{ min.unit }})</span
                                >
                            </label>
                            <input
                                type="number"
                                step="0.001"
                                min="0"
                                v-model="params[min.key]"
                                class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                                :placeholder="min.label"
                            />
                            <InputError :message="fieldErrors[min.key]" />
                        </div>
                    </div>
                </div>

                <div
                    v-if="error"
                    class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800/50 dark:bg-red-900/20 dark:text-red-300"
                >
                    {{ error }}
                </div>

                <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                    <button
                        type="button"
                        :disabled="loading"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90 disabled:opacity-50"
                        @click="calculer"
                    >
                        <RefreshCw v-if="loading" class="size-4 animate-spin" />
                        <FlaskConical v-else class="size-4" />
                        {{
                            loading
                                ? 'Calcul en cours…'
                                : 'Calculer les valeurs'
                        }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-lg border border-border px-4 py-2.5 text-sm text-muted-foreground hover:border-primary/50 hover:text-foreground"
                        @click="reset"
                    >
                        Recommencer
                    </button>
                </div>
            </div>

            <!-- ── Étape 3 : Résultats ─────────────────────────────────────── -->
            <div
                v-if="resultats"
                class="rounded-xl border border-border bg-card p-6 shadow-sm"
            >
                <h2 class="mb-1 text-base font-semibold text-foreground">
                    3. Résultats — {{ selectedTypeLabel }}
                </h2>
                <p class="mb-5 text-sm text-muted-foreground">
                    Valeurs INRA 2018 et INRA 2007 calculées sur matière sèche
                </p>

                <!-- Composition de base -->
                <div class="mb-5">
                    <h3
                        class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Composition
                    </h3>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div
                            v-for="row in [
                                {
                                    label: 'MS',
                                    value: fmt(resultats.ms, 1),
                                    unit: '%',
                                },
                                {
                                    label: 'MO',
                                    value: fmt(resultats.mo, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'MAT',
                                    value: fmt(resultats.mat, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'NDF',
                                    value: fmt(resultats.ndf, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'ADF',
                                    value: fmt(resultats.adf, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'CB',
                                    value: fmt(resultats.cb, 1),
                                    unit: 'g/kg MS',
                                },
                            ]"
                            :key="row.label"
                            class="rounded-lg bg-muted/30 px-3 py-2.5 text-center"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ row.label }}
                            </p>
                            <p
                                class="mt-0.5 text-sm font-semibold text-foreground"
                            >
                                {{ row.value }}
                            </p>
                            <p class="text-xs text-muted-foreground/70">
                                {{ row.unit }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Énergie -->
                <div class="mb-5">
                    <h3
                        class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Énergie
                    </h3>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div
                            v-for="row in [
                                {
                                    label: 'EB',
                                    value: fmt(resultats.eb, 0),
                                    unit: 'kcal/kg MS',
                                },
                                {
                                    label: 'EM',
                                    value: fmt(resultats.em, 0),
                                    unit: 'kcal/kg MS',
                                },
                                {
                                    label: 'dMO',
                                    value: fmt(resultats.dmo, 1),
                                    unit: '%',
                                },
                                {
                                    label: 'UFL',
                                    value: fmt(resultats.ufl, 3),
                                    unit: 'UFL/kg MS',
                                },
                                {
                                    label: 'UFV',
                                    value: fmt(resultats.ufv, 3),
                                    unit: 'UFV/kg MS',
                                },
                                {
                                    label: 'UEL',
                                    value: fmt(resultats.uel, 3),
                                    unit: 'UEL/kg MS',
                                },
                                {
                                    label: 'UEM',
                                    value: fmt(resultats.uem, 3),
                                    unit: 'UEM/kg MS',
                                },
                                {
                                    label: 'UEB',
                                    value: fmt(resultats.ueb, 3),
                                    unit: 'UEB/kg MS',
                                },
                            ]"
                            :key="row.label"
                            class="rounded-lg bg-primary/5 px-3 py-2.5 text-center"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ row.label }}
                            </p>
                            <p
                                class="mt-0.5 text-sm font-semibold text-primary"
                            >
                                {{ row.value }}
                            </p>
                            <p class="text-xs text-muted-foreground/70">
                                {{ row.unit }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Protéines -->
                <div class="mb-5">
                    <h3
                        class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Protéines
                    </h3>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div
                            v-for="row in [
                                {
                                    label: 'NIref',
                                    value: fmt(resultats.niref, 2),
                                    unit: '',
                                },
                                {
                                    label: 'DT_N',
                                    value: fmt(resultats.dt_n, 1),
                                    unit: '%',
                                },
                                {
                                    label: 'dr_N',
                                    value: fmt(resultats.dr_n, 1),
                                    unit: '%',
                                },
                                {
                                    label: 'PDIA',
                                    value: fmt(resultats.pdia, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'PDI',
                                    value: fmt(resultats.pdi, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'BPR',
                                    value: fmt(resultats.bpr, 1),
                                    unit: 'g/kg MS',
                                },
                            ]"
                            :key="row.label"
                            class="rounded-lg bg-muted/30 px-3 py-2.5 text-center"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ row.label }}
                            </p>
                            <p
                                class="mt-0.5 text-sm font-semibold text-foreground"
                            >
                                {{ row.value }}
                            </p>
                            <p class="text-xs text-muted-foreground/70">
                                {{ row.unit }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Minéraux (si renseignés) -->
                <div
                    v-if="
                        resultats.ca != null ||
                        resultats.p != null ||
                        resultats.mg != null
                    "
                    class="mb-5"
                >
                    <h3
                        class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Minéraux
                    </h3>
                    <div
                        class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6"
                    >
                        <template v-if="resultats.ca != null">
                            <div
                                class="rounded-lg bg-muted/30 px-3 py-2.5 text-center"
                            >
                                <p class="text-xs text-muted-foreground">Ca</p>
                                <p
                                    class="mt-0.5 text-sm font-semibold text-foreground"
                                >
                                    {{ fmt(resultats.ca, 1) }}
                                </p>
                                <p class="text-xs text-muted-foreground/70">
                                    g/kg MS
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-muted/30 px-3 py-2.5 text-center"
                            >
                                <p class="text-xs text-muted-foreground">
                                    Ca abs.
                                </p>
                                <p
                                    class="mt-0.5 text-sm font-semibold text-foreground"
                                >
                                    {{ fmt(resultats.caabs, 1) }}
                                </p>
                                <p class="text-xs text-muted-foreground/70">
                                    g/kg MS
                                </p>
                            </div>
                        </template>
                        <template v-if="resultats.p != null">
                            <div
                                class="rounded-lg bg-muted/30 px-3 py-2.5 text-center"
                            >
                                <p class="text-xs text-muted-foreground">P</p>
                                <p
                                    class="mt-0.5 text-sm font-semibold text-foreground"
                                >
                                    {{ fmt(resultats.p, 1) }}
                                </p>
                                <p class="text-xs text-muted-foreground/70">
                                    g/kg MS
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-muted/30 px-3 py-2.5 text-center"
                            >
                                <p class="text-xs text-muted-foreground">
                                    P abs.
                                </p>
                                <p
                                    class="mt-0.5 text-sm font-semibold text-foreground"
                                >
                                    {{ fmt(resultats.pabs, 1) }}
                                </p>
                                <p class="text-xs text-muted-foreground/70">
                                    g/kg MS
                                </p>
                            </div>
                        </template>
                        <template v-if="resultats.mg != null">
                            <div
                                class="rounded-lg bg-muted/30 px-3 py-2.5 text-center"
                            >
                                <p class="text-xs text-muted-foreground">Mg</p>
                                <p
                                    class="mt-0.5 text-sm font-semibold text-foreground"
                                >
                                    {{ fmt(resultats.mg, 1) }}
                                </p>
                                <p class="text-xs text-muted-foreground/70">
                                    g/kg MS
                                </p>
                            </div>
                        </template>
                    </div>
                </div>

                <div v-if="resultats2007" class="mb-5">
                    <h3
                        class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Référentiel 2007
                    </h3>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <div
                            v-for="row in [
                                {
                                    label: 'UFL 2007',
                                    value: fmt(resultats2007.ufl2007, 3),
                                    unit: 'UFL/kg MS',
                                },
                                {
                                    label: 'UFV 2007',
                                    value: fmt(resultats2007.ufv2007, 3),
                                    unit: 'UFV/kg MS',
                                },
                                {
                                    label: 'PDIE 2007',
                                    value: fmt(resultats2007.pdie2007, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'PDIN 2007',
                                    value: fmt(resultats2007.pdin2007, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'PDIA 2007',
                                    value: fmt(resultats2007.pdia2007, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'dMO 2007',
                                    value: fmt(resultats2007.dmo2007, 1),
                                    unit: '%',
                                },
                                {
                                    label: 'dMA 2007',
                                    value: fmt(resultats2007.dma2007, 1),
                                    unit: 'g/kg MS',
                                },
                                {
                                    label: 'UEM 2007',
                                    value: fmt(resultats2007.uem2007, 3),
                                    unit: 'UEM/kg MS',
                                },
                                {
                                    label: 'UEL 2007',
                                    value: fmt(resultats2007.uel2007, 3),
                                    unit: 'UEL/kg MS',
                                },
                                {
                                    label: 'UEB 2007',
                                    value: fmt(resultats2007.ueb2007, 3),
                                    unit: 'UEB/kg MS',
                                },
                            ]"
                            :key="row.label"
                            class="rounded-lg bg-amber-50 px-3 py-2.5 text-center dark:bg-amber-950/20"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ row.label }}
                            </p>
                            <p
                                class="mt-0.5 text-sm font-semibold text-amber-700 dark:text-amber-300"
                            >
                                {{ row.value }}
                            </p>
                            <p class="text-xs text-muted-foreground/70">
                                {{ row.unit }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Enregistrer comme aliment -->
                <div class="border-t border-border pt-5">
                    <div
                        class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">
                                Sauvegarder comme aliment
                            </h3>
                            <p
                                class="mt-1 max-w-2xl text-xs text-muted-foreground"
                            >
                                Comme dans VetRation 2018, vous pouvez partir
                                d'un aliment de table comme modèle. Les valeurs
                                calculées AgriNIR 2018 et 2007 remplacent les
                                champs recalculés, et le reste est repris du
                                modèle choisi.
                            </p>
                        </div>
                        <div
                            class="inline-flex w-fit items-center rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-medium text-primary"
                        >
                            {{ props.referenceAliments.length }} modèles INRA
                            disponibles
                        </div>
                    </div>

                    <div
                        class="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]"
                    >
                        <div
                            class="rounded-2xl border border-border/80 bg-muted/20 p-4"
                        >
                            <div
                                class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p
                                        class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                    >
                                        Modèle facultatif
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Recherchez un aliment de la base pour
                                        conserver ses données non recalculées.
                                    </p>
                                </div>
                                <button
                                    v-if="referenceAlimentId !== null"
                                    type="button"
                                    class="rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-foreground transition hover:border-primary/40 hover:text-primary"
                                    @click="referenceAlimentId = null"
                                >
                                    Sans modèle
                                </button>
                            </div>

                            <div
                                class="rounded-xl border border-border bg-background px-3 py-2"
                            >
                                <div class="flex items-center gap-2">
                                    <Search
                                        class="size-4 text-muted-foreground"
                                    />
                                    <input
                                        v-model="referenceQuery"
                                        type="text"
                                        placeholder="Rechercher par code INRA, type ou libellé…"
                                        class="w-full border-0 bg-transparent p-0 text-sm text-foreground placeholder:text-muted-foreground focus:ring-0 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div
                                class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1"
                            >
                                <button
                                    v-for="aliment in visibleReferenceAliments"
                                    :key="aliment.id"
                                    type="button"
                                    class="w-full rounded-xl border px-3 py-3 text-left transition"
                                    :class="
                                        referenceAlimentId === aliment.id
                                            ? 'border-primary bg-primary/10 shadow-sm'
                                            : 'border-border bg-background hover:border-primary/40 hover:bg-background/90'
                                    "
                                    @click="referenceAlimentId = aliment.id"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-foreground"
                                            >
                                                {{ aliment.label }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{
                                                    aliment.type ??
                                                    'Type non renseigné'
                                                }}
                                            </p>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
                                        >
                                            INRA {{ aliment.code_inra }}
                                        </span>
                                    </div>
                                </button>

                                <div
                                    v-if="
                                        matchingReferenceAliments.length === 0
                                    "
                                    class="rounded-xl border border-dashed border-border bg-background px-4 py-5 text-sm text-muted-foreground"
                                >
                                    Aucun aliment de table ne correspond à cette
                                    recherche.
                                </div>
                            </div>

                            <p
                                v-if="hasMoreReferenceAliments"
                                class="mt-3 text-[11px] text-muted-foreground"
                            >
                                {{
                                    matchingReferenceAliments.length -
                                    visibleReferenceAliments.length
                                }}
                                autres modèles correspondent. Affinez la
                                recherche pour les afficher.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-border/80 bg-background p-4 shadow-sm"
                        >
                            <div
                                class="flex items-center gap-2 text-sm font-semibold text-foreground"
                            >
                                <Database class="size-4 text-primary" />
                                Résumé de création
                            </div>

                            <div
                                v-if="selectedReferenceAliment"
                                class="mt-4 rounded-2xl border border-primary/20 bg-primary/5 p-4"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-primary uppercase"
                                >
                                    Modèle sélectionné
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold text-foreground"
                                >
                                    {{ selectedReferenceAliment.label }}
                                </p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <div
                                        class="rounded-xl border border-border/80 bg-background/80 px-3 py-2"
                                    >
                                        <p
                                            class="text-[11px] tracking-wide text-muted-foreground uppercase"
                                        >
                                            Code INRA
                                        </p>
                                        <p
                                            class="mt-1 text-sm font-medium text-foreground"
                                        >
                                            {{
                                                selectedReferenceAliment.code_inra
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-xl border border-border/80 bg-background/80 px-3 py-2"
                                    >
                                        <p
                                            class="text-[11px] tracking-wide text-muted-foreground uppercase"
                                        >
                                            Type conservé
                                        </p>
                                        <p
                                            class="mt-1 text-sm font-medium text-foreground"
                                        >
                                            {{
                                                selectedReferenceAliment.type ??
                                                'Non renseigné'
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <p
                                    class="mt-3 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Les données de table non recalculées
                                    resteront présentes. Les champs AgriNIR 2018
                                    et 2007 seront recalculés à partir de votre
                                    analyse.
                                </p>
                            </div>

                            <div
                                v-else
                                class="mt-4 rounded-2xl border border-dashed border-border bg-muted/20 p-4"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                >
                                    Sans modèle
                                </p>
                                <p
                                    class="mt-2 text-sm font-medium text-foreground"
                                >
                                    Création autonome depuis AgriNIR
                                </p>
                                <p
                                    class="mt-2 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Seules les valeurs calculées seront
                                    enregistrées. Les champs non alimentés par
                                    AgriNIR resteront vides jusqu'à édition
                                    manuelle.
                                </p>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div>
                                    <label
                                        class="mb-1.5 block text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                    >
                                        Nom de l'aliment créé
                                    </label>
                                    <input
                                        type="text"
                                        v-model="saveNom"
                                        placeholder="Nom de l'aliment…"
                                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                                        @keydown.enter="sauvegarder"
                                    />
                                </div>

                                <button
                                    type="button"
                                    :disabled="!saveNom.trim() || saving"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-green-600 px-4 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 disabled:opacity-50"
                                    @click="sauvegarder"
                                >
                                    <Save class="size-4" />
                                    {{
                                        saving
                                            ? 'Enregistrement…'
                                            : 'Sauvegarder dans ma bibliothèque'
                                    }}
                                </button>
                            </div>

                            <p class="mt-3 text-xs text-muted-foreground">
                                L'aliment sera ajouté à votre bibliothèque
                                personnelle et utilisable dans vos rations.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
