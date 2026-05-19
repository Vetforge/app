<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, RotateCcw, Save, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import {
    destroy as moduleSettingsDestroy,
    edit as moduleSettingsEdit,
    update as moduleSettingsUpdate,
} from '@/actions/App/Http/Controllers/Settings/ModuleSettingsController';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { clonePlain } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface ModuleInfo {
    slug: string;
    label: string;
    short_label: string;
    description: string;
}

type LooseSettings = Record<string, any>;

const props = defineProps<{
    module: ModuleInfo;
    modules: ModuleInfo[];
    settings: LooseSettings;
    defaults: LooseSettings;
}>();

const processing = ref(false);
const draft = reactive<LooseSettings>(clonePlain(props.settings));
const newCoproscopySpecies = ref('');
const newBloodGasSpecies = ref('');
const newRapidTestsSpecies = ref('');
const newParametricSpecies = ref('');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    {
        title: 'Reglages modules',
        href: moduleSettingsEdit({ module: props.module.slug }).url,
    },
];

const bloodGasNormFields = [
    { key: 'ph', label: 'pH', step: '0.01' },
    { key: 'pco2', label: 'pCO2', step: '0.1' },
    { key: 'hco3', label: 'HCO3', step: '0.1' },
    { key: 'na', label: 'Na', step: '0.1' },
    { key: 'k', label: 'K', step: '0.1' },
    { key: 'cl', label: 'Cl', step: '0.1' },
    { key: 'glycemia', label: 'Glycemie', step: '0.1' },
];

const bloodGasSpeciesRows = computed(() => {
    const options = Array.isArray(draft.species_options)
        ? draft.species_options
        : [];

    return options
        .map((option: LooseSettings, index: number) => ({
            index,
            value: String(option.value ?? option.label ?? ''),
            label: String(option.label ?? option.value ?? ''),
            normKey: String(
                option.norm_key ?? option.value ?? option.label ?? '',
            ),
            calculationProfile: String(
                option.calculation_profile ?? 'ruminant',
            ),
        }))
        .filter((option) => option.value !== '' && option.normKey !== '');
});
const coproscopySpeciesRows = computed<string[]>(() =>
    Array.isArray(draft.species_options)
        ? draft.species_options
              .map((species: unknown) => String(species))
              .filter((species: string) => species !== '')
        : [],
);
const rapidTestsSpeciesRows = computed<string[]>(() =>
    Array.isArray(draft.species_options)
        ? draft.species_options
              .map((species: unknown) => String(species))
              .filter((species: string) => species !== '')
        : [],
);
const parametricSpeciesRows = computed<string[]>(() =>
    Array.isArray(draft.species_options)
        ? draft.species_options
              .map((species: unknown) => String(species))
              .filter((species: string) => species !== '')
        : [],
);
const rapidTestGroups = [
    {
        key: 'elisa_tests',
        title: 'Tests ELISA',
        description: 'Tests qualitatifs avec resultat positif, negatif ou douteux.',
        prefix: 'elisa',
        withUnit: false,
    },
    {
        key: 'pcr_tests',
        title: 'PCR',
        description: 'PCR disponibles dans la saisie des tests rapides.',
        prefix: 'pcr',
        withUnit: false,
    },
    {
        key: 'biochem_rapide',
        title: 'Biochimie rapide',
        description: 'Parametres quantitatifs saisis avec une unite.',
        prefix: 'biochimie',
        withUnit: true,
    },
];
const hemogrammeGroups = [
    { value: 'erythrocytes', label: 'Erythrocytes' },
    { value: 'leucocytes', label: 'Leucocytes' },
    { value: 'plaquettes', label: 'Plaquettes' },
    { value: 'autres', label: 'Autres' },
];

if (props.module.slug === 'coproscopie-parasitaire') {
    normalizeCoproscopyDraft();
}

if (props.module.slug === 'gaz-du-sang') {
    normalizeBloodGasDraft();
}

if (props.module.slug === 'tests-rapides') {
    normalizeTestsRapidesDraft();
}

if (isParametricAnalysisModule()) {
    normalizeParametricAnalysisDraft();
}

function addRow(key: string, row: LooseSettings | string): void {
    if (!Array.isArray(draft[key])) {
        draft[key] = [];
    }

    draft[key].push(clonePlain(row));
}

function removeRow(key: string, index: number): void {
    draft[key].splice(index, 1);
}

function isObject(value: unknown): value is LooseSettings {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function normalizeCoproscopyDraft(): void {
    if (
        !Array.isArray(draft.species_options) ||
        draft.species_options.length === 0
    ) {
        draft.species_options = clonePlain(
            props.defaults.species_options ?? [
                'Bovin',
                'Ovin',
                'Caprin',
                'Equin',
                'Chien',
                'Chat',
            ],
        );
    }

    const defaultByKey = new Map(
        (Array.isArray(props.defaults.parasites)
            ? props.defaults.parasites
            : []
        ).map((parasite: LooseSettings) => [parasite.key, parasite]),
    );
    const allSpecies = coproscopySpeciesRows.value;

    if (!Array.isArray(draft.parasites)) {
        draft.parasites = [];
    }

    draft.parasites.forEach((parasite: LooseSettings) => {
        const defaults = defaultByKey.get(parasite.key) ?? {};

        if (!Array.isArray(parasite.species) || parasite.species.length === 0) {
            parasite.species = clonePlain(defaults.species ?? allSpecies);
        }

        parasite.requires_option = parasite.requires_option ?? defaults.requires_option ?? '';
    });
}

function normalizeBloodGasDraft(): void {
    if (!isObject(draft.norms)) {
        draft.norms = {};
    }

    const legacyNormKeys: Record<string, string> = {
        Bovin: 'bovine',
        Equin: 'equine',
    };

    Object.entries(legacyNormKeys).forEach(([species, legacyKey]) => {
        if (
            !isObject(draft.norms[species]) &&
            isObject(draft.norms[legacyKey])
        ) {
            draft.norms[species] = clonePlain(draft.norms[legacyKey]);
        }
    });

    if (
        !Array.isArray(draft.species_options) ||
        draft.species_options.length === 0
    ) {
        draft.species_options = clonePlain(
            props.defaults.species_options ?? [
                {
                    value: 'Bovin',
                    label: 'Bovin',
                    norm_key: 'Bovin',
                    calculation_profile: 'ruminant',
                },
                {
                    value: 'Equin',
                    label: 'Equin',
                    norm_key: 'Equin',
                    calculation_profile: 'equine',
                },
            ],
        );
    }

    draft.species_options.forEach((option: LooseSettings) => {
        option.value = String(option.value ?? option.label ?? '');
        option.label = String(option.label ?? option.value);
        option.norm_key = String(option.norm_key ?? option.value);
        option.calculation_profile = String(
            option.calculation_profile ?? defaultBloodGasProfile(option.value),
        );
        ensureBloodGasNorm(String(option.norm_key), String(option.value));
    });
}

function defaultBloodGasProfile(species: string): string {
    return ['Bovin', 'Ovin', 'Caprin'].includes(species)
        ? 'ruminant'
        : 'equine';
}

function ensureBloodGasNorm(normKey: string, species: string = normKey): void {
    if (!isObject(draft.norms)) {
        draft.norms = {};
    }

    if (!isObject(draft.norms[normKey])) {
        const defaults = props.defaults.norms ?? {};
        draft.norms[normKey] = clonePlain(
            defaults[normKey] ?? defaults[species] ?? defaults.Bovin ?? {},
        );
    }

    bloodGasNormFields.forEach((field) => {
        if (!Array.isArray(draft.norms[normKey][field.key])) {
            const defaults = props.defaults.norms ?? {};
            draft.norms[normKey][field.key] = clonePlain(
                defaults[normKey]?.[field.key] ??
                    defaults.Bovin?.[field.key] ?? [0, 0],
            );
        }
    });
}

function addBloodGasSpecies(): void {
    const value = newBloodGasSpecies.value.trim();

    if (value === '') {
        return;
    }

    if (!Array.isArray(draft.species_options)) {
        draft.species_options = [];
    }

    if (
        draft.species_options.some(
            (option: LooseSettings) => option.value === value,
        )
    ) {
        newBloodGasSpecies.value = '';
        return;
    }

    draft.species_options.push({
        value,
        label: value,
        norm_key: value,
        calculation_profile: defaultBloodGasProfile(value),
    });
    ensureBloodGasNorm(value);
    newBloodGasSpecies.value = '';
}

function addCoproscopySpecies(): void {
    const value = newCoproscopySpecies.value.trim();

    if (value === '') {
        return;
    }

    if (!Array.isArray(draft.species_options)) {
        draft.species_options = [];
    }

    if (!draft.species_options.includes(value)) {
        draft.species_options.push(value);
    }

    newCoproscopySpecies.value = '';
}

function removeCoproscopySpecies(index: number): void {
    const value = String(draft.species_options[index] ?? '');

    draft.species_options.splice(index, 1);

    if (value !== '' && Array.isArray(draft.parasites)) {
        draft.parasites.forEach((parasite: LooseSettings) => {
            if (Array.isArray(parasite.species)) {
                parasite.species = parasite.species.filter(
                    (species: string) => species !== value,
                );
            }
        });
    }
}

function removeBloodGasSpecies(index: number): void {
    const option = draft.species_options[index];
    const normKey = String(option?.norm_key ?? option?.value ?? '');

    draft.species_options.splice(index, 1);

    if (normKey !== '' && isObject(draft.norms)) {
        delete draft.norms[normKey];
    }
}

function normalizeTestsRapidesDraft(): void {
    if (
        !Array.isArray(draft.species_options) ||
        draft.species_options.length === 0
    ) {
        draft.species_options = clonePlain(
            props.defaults.species_options ?? [
                'Bovin',
                'Ovin',
                'Caprin',
                'Equin',
                'Chien',
                'Chat',
            ],
        );
    }

    ['elisa_tests', 'biochem_rapide', 'pcr_tests'].forEach((key) => {
        if (!Array.isArray(draft[key])) {
            draft[key] = [];
        }

        draft[key].forEach((item: LooseSettings, index: number) => {
            normalizeRapidTestItem(item, key, index);
        });
    });

    if (!Array.isArray(draft.optional_sections)) {
        draft.optional_sections = clonePlain(
            props.defaults.optional_sections ?? [
                {
                    key: 'bandelette_urinaire',
                    label: 'Bandelette urinaire',
                    enabled: draft.bandelette_urinaire !== false,
                    species: clonePlain(rapidTestsSpeciesRows.value),
                },
                {
                    key: 'frottis_sanguin',
                    label: 'Frottis sanguin',
                    enabled: draft.frottis_sanguin !== false,
                    species: clonePlain(rapidTestsSpeciesRows.value),
                },
            ],
        );
    }

    draft.optional_sections.forEach((item: LooseSettings, index: number) => {
        normalizeRapidTestItem(item, 'optional_sections', index);
    });
}

function normalizeRapidTestItem(
    item: LooseSettings,
    groupKey: string,
    index: number,
): void {
    item.key = String(item.key ?? nextRapidTestKey(groupKey, index));
    item.label = String(item.label ?? item.key ?? '');
    item.enabled = item.enabled !== false;

    if (groupKey === 'biochem_rapide') {
        item.unit = String(item.unit ?? '');
    }

    if (!Array.isArray(item.species)) {
        item.species = clonePlain(rapidTestsSpeciesRows.value);
    }
}

function nextRapidTestKey(groupKey: string, index = 0): string {
    const group = rapidTestGroups.find((item) => item.key === groupKey);
    const prefix = group?.prefix ?? 'item';
    const used = new Set(
        (Array.isArray(draft[groupKey]) ? draft[groupKey] : [])
            .map((item: LooseSettings) => String(item.key ?? ''))
            .filter((key: string) => key !== ''),
    );
    let suffix = index + 1;
    let key = `${prefix}_${suffix}`;

    while (used.has(key)) {
        suffix += 1;
        key = `${prefix}_${suffix}`;
    }

    return key;
}

function addRapidTestItem(groupKey: string): void {
    if (!Array.isArray(draft[groupKey])) {
        draft[groupKey] = [];
    }

    const group = rapidTestGroups.find((item) => item.key === groupKey);

    draft[groupKey].push({
        key: nextRapidTestKey(groupKey),
        label: '',
        unit: group?.withUnit ? '' : undefined,
        species: clonePlain(rapidTestsSpeciesRows.value),
        enabled: true,
    });
}

function addRapidTestsSpecies(): void {
    const value = newRapidTestsSpecies.value.trim();

    if (value === '') {
        return;
    }

    if (!Array.isArray(draft.species_options)) {
        draft.species_options = [];
    }

    if (!draft.species_options.includes(value)) {
        draft.species_options.push(value);
        forEachRapidTestConfig((item) => {
            if (!Array.isArray(item.species)) {
                item.species = [];
            }

            item.species.push(value);
        });
    }

    newRapidTestsSpecies.value = '';
}

function removeRapidTestsSpecies(index: number): void {
    const value = String(draft.species_options[index] ?? '');

    draft.species_options.splice(index, 1);

    if (value !== '') {
        forEachRapidTestConfig((item) => {
            if (Array.isArray(item.species)) {
                item.species = item.species.filter(
                    (species: string) => species !== value,
                );
            }
        });
    }
}

function forEachRapidTestConfig(callback: (item: LooseSettings) => void): void {
    ['elisa_tests', 'biochem_rapide', 'pcr_tests', 'optional_sections'].forEach(
        (key) => {
            if (Array.isArray(draft[key])) {
                draft[key].forEach((item: LooseSettings) => callback(item));
            }
        },
    );
}

function isParametricAnalysisModule(): boolean {
    return ['tests-biochimie', 'hemogramme'].includes(props.module.slug);
}

function normalizeParametricAnalysisDraft(): void {
    if (
        !Array.isArray(draft.species_options) ||
        draft.species_options.length === 0
    ) {
        draft.species_options = clonePlain(
            props.defaults.species_options ?? [
                'Bovin',
                'Ovin',
                'Caprin',
                'Equin',
                'Chien',
                'Chat',
            ],
        );
    }

    if (!Array.isArray(draft.params)) {
        draft.params = [];
    }

    draft.params.forEach((param: LooseSettings, index: number) => {
        normalizeParametricParam(param, index);
    });

    if (!isObject(draft.norms)) {
        draft.norms = {};
    }

    ensureParametricNorms();
}

function normalizeParametricParam(
    param: LooseSettings,
    index: number,
): void {
    param.key = String(param.key ?? nextParametricParamKey(index));
    param.label = String(param.label ?? param.key ?? '');
    param.enabled = param.enabled !== false;

    if (!Array.isArray(param.species)) {
        param.species = clonePlain(parametricSpeciesRows.value);
    }

    if (props.module.slug === 'hemogramme') {
        param.group = String(param.group ?? 'autres');
    }
}

function nextParametricParamKey(index = 0): string {
    const used = new Set(
        (Array.isArray(draft.params) ? draft.params : [])
            .map((param: LooseSettings) => String(param.key ?? ''))
            .filter((key: string) => key !== ''),
    );
    let suffix = index + 1;
    let key = `param_${suffix}`;

    while (used.has(key)) {
        suffix += 1;
        key = `param_${suffix}`;
    }

    return key;
}

function addParametricSpecies(): void {
    const value = newParametricSpecies.value.trim();

    if (value === '') {
        return;
    }

    if (!Array.isArray(draft.species_options)) {
        draft.species_options = [];
    }

    if (!draft.species_options.includes(value)) {
        draft.species_options.push(value);

        if (!isObject(draft.norms)) {
            draft.norms = {};
        }

        draft.norms[value] = {};

        if (Array.isArray(draft.params)) {
            draft.params.forEach((param: LooseSettings) => {
                if (!Array.isArray(param.species)) {
                    param.species = [];
                }

                param.species.push(value);
            });
        }

        ensureParametricNorms();
    }

    newParametricSpecies.value = '';
}

function removeParametricSpecies(index: number): void {
    const value = String(draft.species_options[index] ?? '');

    draft.species_options.splice(index, 1);

    if (value !== '' && isObject(draft.norms)) {
        delete draft.norms[value];
    }

    if (value !== '' && Array.isArray(draft.params)) {
        draft.params.forEach((param: LooseSettings) => {
            if (Array.isArray(param.species)) {
                param.species = param.species.filter(
                    (species: string) => species !== value,
                );
            }
        });
    }
}

function addParametricParam(): void {
    if (!Array.isArray(draft.params)) {
        draft.params = [];
    }

    draft.params.push({
        key: nextParametricParamKey(),
        label: '',
        group: props.module.slug === 'hemogramme' ? 'autres' : undefined,
        species: clonePlain(parametricSpeciesRows.value),
        enabled: true,
    });
    ensureParametricNorms();
}

function removeParametricParam(index: number): void {
    const key = String(draft.params[index]?.key ?? '');

    draft.params.splice(index, 1);

    if (key !== '' && isObject(draft.norms)) {
        Object.values(draft.norms).forEach((speciesNorms: unknown) => {
            if (isObject(speciesNorms)) {
                delete speciesNorms[key];
            }
        });
    }
}

function ensureParametricNorms(): void {
    if (!isObject(draft.norms)) {
        draft.norms = {};
    }

    parametricSpeciesRows.value.forEach((species) => {
        if (!isObject(draft.norms[species])) {
            draft.norms[species] = {};
        }

        if (Array.isArray(draft.params)) {
            draft.params.forEach((param: LooseSettings) => {
                const key = String(param.key ?? '');

                if (key === '' || isObject(draft.norms[species][key])) {
                    return;
                }

                draft.norms[species][key] = clonePlain(
                    props.defaults.norms?.[species]?.[key] ??
                        props.defaults.norms?.Bovin?.[key] ?? {
                            min: null,
                            max: null,
                            unit: '',
                        },
                );
            });
        }
    });
}

function parametricParamsForSpecies(species: string): LooseSettings[] {
    if (!Array.isArray(draft.params)) {
        return [];
    }

    return draft.params.filter((param: LooseSettings) => {
        if (param.enabled === false) {
            return false;
        }

        if (!Array.isArray(param.species)) {
            return true;
        }

        return param.species.includes(species);
    });
}

function save(): void {
    processing.value = true;
    router.patch(
        moduleSettingsUpdate({ module: props.module.slug }).url,
        { settings: clonePlain(draft) },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function resetToDefaults(): void {
    Object.keys(draft).forEach((key) => delete draft[key]);
    Object.assign(draft, clonePlain(props.defaults));

    if (props.module.slug === 'coproscopie-parasitaire') {
        normalizeCoproscopyDraft();
    }

    if (props.module.slug === 'gaz-du-sang') {
        normalizeBloodGasDraft();
    }

    if (props.module.slug === 'tests-rapides') {
        normalizeTestsRapidesDraft();
    }

    if (isParametricAnalysisModule()) {
        normalizeParametricAnalysisDraft();
    }
}

function deleteCustomSettings(): void {
    if (
        confirm(`Reinitialiser les reglages du module ${props.module.label} ?`)
    ) {
        router.delete(
            moduleSettingsDestroy({ module: props.module.slug }).url,
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Reglages - ${module.label}`" />

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    :title="module.label"
                    description="Ajustez les listes, seuils et references utilises lors de la creation des analyses."
                />

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="item in modules"
                        :key="item.slug"
                        :href="moduleSettingsEdit({ module: item.slug }).url"
                        class="rounded-lg border px-3 py-2 text-sm"
                        :class="
                            item.slug === module.slug
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border text-muted-foreground hover:bg-accent'
                        "
                    >
                        {{ item.short_label }}
                    </Link>
                </div>

                <form class="space-y-6" @submit.prevent="save">
                    <section
                        v-if="module.slug === 'coproscopie-parasitaire'"
                        class="space-y-6"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between"
                            >
                                <div>
                                    <h2 class="font-semibold">
                                        Especes disponibles
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        Cette liste alimente le selecteur de
                                        l'analyse coproscopie.
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <input
                                        v-model="newCoproscopySpecies"
                                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                                        placeholder="Nouvelle espece"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                        @click="addCoproscopySpecies"
                                    >
                                        <Plus class="size-4" />
                                        Ajouter
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="(
                                        species, index
                                    ) in draft.species_options"
                                    :key="`${species}-${index}`"
                                    class="inline-flex items-center gap-2 rounded-full border border-border bg-muted/40 px-3 py-1 text-sm"
                                >
                                    {{ species }}
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-destructive"
                                        @click="removeCoproscopySpecies(index)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <h2 class="font-semibold">Parasites</h2>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                    @click="
                                        addRow('parasites', {
                                            key: '',
                                            label: '',
                                            unit: null,
                                            species: clonePlain(
                                                coproscopySpeciesRows,
                                            ),
                                            enabled: true,
                                        })
                                    "
                                >
                                    <Plus class="size-4" />
                                    Ajouter
                                </button>
                            </div>
                            <div
                                v-for="(parasite, index) in draft.parasites"
                                :key="index"
                                class="space-y-3 rounded-lg border border-border p-4"
                            >
                                <div class="grid gap-3 sm:grid-cols-[1fr_1.5fr_0.6fr_1fr_auto_auto]">
                                    <label class="grid gap-1.5 text-sm">
                                        Cle
                                        <input
                                            v-model="parasite.key"
                                            class="rounded border border-border bg-background px-3 py-2 text-sm"
                                            placeholder="cle"
                                        />
                                    </label>
                                    <label class="grid gap-1.5 text-sm">
                                        Libelle
                                        <input
                                            v-model="parasite.label"
                                            class="rounded border border-border bg-background px-3 py-2 text-sm"
                                            placeholder="Libelle"
                                        />
                                    </label>
                                    <label class="grid gap-1.5 text-sm">
                                        Technique
                                        <input
                                            v-model="parasite.unit"
                                            class="rounded border border-border bg-background px-3 py-2 text-sm"
                                            placeholder="Technique"
                                        />
                                    </label>
                                    <label class="grid gap-1.5 text-sm">
                                        Option
                                        <select
                                            v-model="parasite.requires_option"
                                            class="h-10 rounded border border-border bg-background px-3 py-2 text-sm"
                                        >
                                            <option value=""></option>
                                            <option value="dictyocaules">Dictyocaules</option>
                                            <option value="cryptosporidies">Cryptosporidies</option>
                                        </select>
                                    </label>
                                    <label class="flex flex-col items-center gap-2 self-end pb-1.5 text-sm">
                                        Actif
                                        <input v-model="parasite.enabled" type="checkbox" class="size-4" />
                                    </label>
                                    <button
                                        type="button"
                                        class="self-end rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        @click="removeRow('parasites', index)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                                <div>
                                    <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Especes</p>
                                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                                        <label
                                            v-for="species in coproscopySpeciesRows"
                                            :key="species"
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                v-model="parasite.species"
                                                type="checkbox"
                                                :value="species"
                                            />
                                            {{ species }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="module.slug === 'diarrhee-neonatale'"
                        class="space-y-6"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <h2 class="font-semibold">Tests</h2>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                    @click="addRow('tests', '')"
                                >
                                    <Plus class="size-4" />
                                    Ajouter
                                </button>
                            </div>
                            <div
                                v-for="(_test, index) in draft.tests"
                                :key="index"
                                class="grid gap-3 rounded-lg border border-border p-3 md:grid-cols-[1fr_auto]"
                            >
                                <input
                                    v-model="draft.tests[index]"
                                    class="rounded border border-border bg-background px-3 py-2 text-sm"
                                    placeholder="Nom du test"
                                />
                                <button
                                    type="button"
                                    class="rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    @click="removeRow('tests', index)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <h2 class="font-semibold">Agents recherches</h2>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                    @click="
                                        addRow('pathogens', {
                                            key: '',
                                            label: '',
                                            enabled: true,
                                        })
                                    "
                                >
                                    <Plus class="size-4" />
                                    Ajouter
                                </button>
                            </div>
                            <div
                                v-for="(pathogen, index) in draft.pathogens"
                                :key="index"
                                class="grid gap-3 rounded-lg border border-border p-3 md:grid-cols-[1fr_1.5fr_auto_auto]"
                            >
                                <input
                                    v-model="pathogen.key"
                                    class="rounded border border-border bg-background px-3 py-2 text-sm"
                                    placeholder="cle"
                                />
                                <input
                                    v-model="pathogen.label"
                                    class="rounded border border-border bg-background px-3 py-2 text-sm"
                                    placeholder="Libelle"
                                />
                                <label class="flex items-center gap-2 text-sm"
                                    ><input
                                        v-model="pathogen.enabled"
                                        type="checkbox"
                                    />
                                    Actif</label
                                >
                                <button
                                    type="button"
                                    class="rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    @click="removeRow('pathogens', index)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="module.slug === 'gaz-du-sang'"
                        class="space-y-6"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between"
                            >
                                <div>
                                    <h2 class="font-semibold">
                                        Especes et normes
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        Les especes disponibles ici alimentent
                                        le selecteur de saisie et determinent le
                                        profil de calcul.
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <input
                                        v-model="newBloodGasSpecies"
                                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                                        placeholder="Nouvelle espece"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                        @click="addBloodGasSpecies"
                                    >
                                        <Plus class="size-4" />
                                        Ajouter
                                    </button>
                                </div>
                            </div>

                            <div
                                v-for="row in bloodGasSpeciesRows"
                                :key="row.normKey"
                                class="space-y-4 rounded-lg border border-border p-4"
                            >
                                <div
                                    class="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_auto]"
                                >
                                    <label class="grid gap-2 text-sm">
                                        Libelle
                                        <input
                                            v-model="
                                                draft.species_options[row.index]
                                                    .label
                                            "
                                            class="rounded border border-border bg-background px-3 py-2"
                                        />
                                    </label>
                                    <label class="grid gap-2 text-sm">
                                        Valeur selecteur
                                        <input
                                            v-model="
                                                draft.species_options[row.index]
                                                    .value
                                            "
                                            class="rounded border border-border bg-background px-3 py-2"
                                        />
                                    </label>
                                    <label class="grid gap-2 text-sm">
                                        Profil de calcul
                                        <select
                                            v-model="
                                                draft.species_options[row.index]
                                                    .calculation_profile
                                            "
                                            class="h-10 rounded border border-border bg-background px-3 py-2"
                                        >
                                            <option value="ruminant">
                                                Ruminant
                                            </option>
                                            <option value="equine">
                                                Equin / autre
                                            </option>
                                        </select>
                                    </label>
                                    <button
                                        type="button"
                                        class="self-end rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        @click="
                                            removeBloodGasSpecies(row.index)
                                        "
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>

                                <div
                                    class="overflow-x-auto rounded-lg border border-border"
                                >
                                    <table class="w-full min-w-[640px] text-sm">
                                        <thead>
                                            <tr
                                                class="border-b border-border bg-muted/40"
                                            >
                                                <th
                                                    class="px-3 py-2 text-left font-medium"
                                                >
                                                    Parametre
                                                </th>
                                                <th
                                                    class="px-3 py-2 text-left font-medium"
                                                >
                                                    Minimum
                                                </th>
                                                <th
                                                    class="px-3 py-2 text-left font-medium"
                                                >
                                                    Maximum
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="field in bloodGasNormFields"
                                                :key="field.key"
                                                class="border-b border-border/50 last:border-b-0"
                                            >
                                                <td
                                                    class="px-3 py-2 font-medium"
                                                >
                                                    {{ field.label }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input
                                                        v-model.number="
                                                            draft.norms[
                                                                row.normKey
                                                            ][field.key][0]
                                                        "
                                                        type="number"
                                                        :step="field.step"
                                                        class="w-full rounded border border-border bg-background px-2 py-1"
                                                    />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input
                                                        v-model.number="
                                                            draft.norms[
                                                                row.normKey
                                                            ][field.key][1]
                                                        "
                                                        type="number"
                                                        :step="field.step"
                                                        class="w-full rounded border border-border bg-background px-2 py-1"
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <h2 class="font-semibold">Perfusions</h2>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                    @click="
                                        addRow('perfusions', {
                                            key: '',
                                            label: '',
                                            unit: 'unite',
                                            bicarbonate: 0,
                                            glucose: 0,
                                            volume: 0,
                                        })
                                    "
                                >
                                    <Plus class="size-4" />
                                    Ajouter
                                </button>
                            </div>
                            <div
                                class="overflow-x-auto rounded-lg border border-border"
                            >
                                <table class="w-full min-w-[920px] text-sm">
                                    <thead class="bg-muted/40">
                                        <tr class="border-b border-border">
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Cle
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Libelle
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Unite saisie
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Bicarbonate (g)
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Glucose (g)
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Volume (L)
                                            </th>
                                            <th
                                                class="px-3 py-2 text-center font-medium"
                                            >
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50">
                                        <tr
                                            v-for="(
                                                perfusion, index
                                            ) in draft.perfusions"
                                            :key="index"
                                        >
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="perfusion.key"
                                                    class="w-36 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="cle"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="perfusion.label"
                                                    class="w-56 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="Libelle"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="perfusion.unit"
                                                    class="w-28 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="Unite"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model.number="
                                                        perfusion.bicarbonate
                                                    "
                                                    type="number"
                                                    step="0.1"
                                                    class="w-32 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="0"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model.number="
                                                        perfusion.glucose
                                                    "
                                                    type="number"
                                                    step="0.1"
                                                    class="w-32 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="0"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model.number="
                                                        perfusion.volume
                                                    "
                                                    type="number"
                                                    step="0.1"
                                                    class="w-32 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="0"
                                                />
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button
                                                    type="button"
                                                    class="rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                    @click="
                                                        removeRow(
                                                            'perfusions',
                                                            index,
                                                        )
                                                    "
                                                >
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="module.slug === 'comptage-cellulaire'"
                        class="space-y-3"
                    >
                        <h2 class="font-semibold">Seuils cellulaires</h2>
                        <div
                            class="grid gap-3 rounded-lg border border-border p-3 md:grid-cols-3"
                        >
                            <label class="grid gap-2 text-sm">
                                Seuil alerte
                                <input
                                    v-model.number="draft.norms.alert_threshold"
                                    type="number"
                                    class="rounded border border-border bg-background px-3 py-2"
                                />
                            </label>
                            <label class="grid gap-2 text-sm">
                                Seuil critique
                                <input
                                    v-model.number="
                                        draft.norms.critical_threshold
                                    "
                                    type="number"
                                    class="rounded border border-border bg-background px-3 py-2"
                                />
                            </label>
                            <label class="grid gap-2 text-sm">
                                Unite
                                <input
                                    v-model="draft.norms.unit"
                                    class="rounded border border-border bg-background px-3 py-2"
                                />
                            </label>
                        </div>
                    </section>

                    <section
                        v-if="module.slug === 'diagnostic-bacteriologique'"
                        class="space-y-6"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <h2 class="font-semibold">
                                    Familles de germes
                                </h2>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                    @click="addRow('germ_families', '')"
                                >
                                    <Plus class="size-4" />
                                    Ajouter
                                </button>
                            </div>
                            <div
                                v-for="(_family, index) in draft.germ_families"
                                :key="index"
                                class="grid gap-3 rounded-lg border border-border p-3 md:grid-cols-[1fr_auto]"
                            >
                                <input
                                    v-model="draft.germ_families[index]"
                                    class="rounded border border-border bg-background px-3 py-2 text-sm"
                                    placeholder="Famille"
                                />
                                <button
                                    type="button"
                                    class="rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    @click="removeRow('germ_families', index)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <h2 class="font-semibold">Antibiotiques</h2>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                    @click="
                                        addRow('antibiotics', {
                                            code: '',
                                            label: '',
                                            dose: null,
                                            intermediate_min: 0,
                                            sensitive_min: 0,
                                            enabled: true,
                                        })
                                    "
                                >
                                    <Plus class="size-4" />
                                    Ajouter
                                </button>
                            </div>
                            <div
                                class="overflow-x-auto rounded-lg border border-border"
                            >
                                <table class="w-full min-w-[980px] text-sm">
                                    <thead class="bg-muted/40">
                                        <tr class="border-b border-border">
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Code
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Antibiotique
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Dose
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Intermediaire min
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Sensible min
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Statut
                                            </th>
                                            <th
                                                class="px-3 py-2 text-center font-medium"
                                            >
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50">
                                        <tr
                                            v-for="(
                                                antibiotic, index
                                            ) in draft.antibiotics"
                                            :key="index"
                                        >
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="antibiotic.code"
                                                    class="w-24 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="Code"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="antibiotic.label"
                                                    class="w-64 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="Libelle"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="antibiotic.dose"
                                                    class="w-36 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="Dose"
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model.number="
                                                        antibiotic.intermediate_min
                                                    "
                                                    type="number"
                                                    class="w-32 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="I >="
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model.number="
                                                        antibiotic.sensitive_min
                                                    "
                                                    type="number"
                                                    class="w-32 rounded border border-border bg-background px-3 py-2 text-sm"
                                                    placeholder="S >="
                                                />
                                            </td>
                                            <td class="px-3 py-2">
                                                <label
                                                    class="flex items-center gap-2 text-sm"
                                                    ><input
                                                        v-model="
                                                            antibiotic.enabled
                                                        "
                                                        type="checkbox"
                                                    />
                                                    Actif</label
                                                >
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button
                                                    type="button"
                                                    class="rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                    @click="
                                                        removeRow(
                                                            'antibiotics',
                                                            index,
                                                        )
                                                    "
                                                >
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section v-if="module.slug === 'bse-laitier'" class="space-y-6">
                        <div class="space-y-3">
                            <h2 class="font-semibold">Prix fourrages et cereales</h2>
                            <div class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2 lg:grid-cols-4">
                                <label class="grid gap-1.5 text-sm">
                                    Prix ha foin (€)
                                    <input v-model.number="draft.prix_ha_foin" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Prix ha ensilage herbe (€)
                                    <input v-model.number="draft.prix_ha_ensilage_herbe" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Prix ha ensilage mais (€)
                                    <input v-model.number="draft.prix_ha_ensilage_mais" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Prix production cereales (€/t)
                                    <input v-model.number="draft.prix_production_cereales_tonnes" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h2 class="font-semibold">Modeles de commentaires</h2>
                            <p class="text-sm text-muted-foreground">Textes affichés en commentaire selon que l'indicateur est satisfaisant (S) ou non satisfaisant (NS).</p>
                            <div v-for="(item, key) in { tx_mortalite_neonatale: 'Mortalite neonatale', tx_mammites: 'Mammites', tx_boiteries: 'Boiteries', tx_metaboliques: 'Metaboliques', cout_reproduction: 'Cout reproduction', cout_alimentaire_vache_l: 'Cout alimentaire / tonne lait' }" :key="key" class="rounded-lg border border-border p-3 space-y-2">
                                <p class="text-sm font-medium">{{ item }}</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label class="grid gap-1 text-xs">
                                        Satisfaisant
                                        <textarea v-model="draft[`txt_${key}_s`]" rows="2" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
                                    </label>
                                    <label class="grid gap-1 text-xs">
                                        Non satisfaisant
                                        <textarea v-model="draft[`txt_${key}_ns`]" rows="2" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="module.slug === 'bse-allaitant'" class="space-y-6">
                        <div class="space-y-3">
                            <h2 class="font-semibold">Prix traitements et mortalites</h2>
                            <div class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2 lg:grid-cols-4">
                                <label class="grid gap-1.5 text-sm">
                                    Traitement diarrhee 0-4j (€)
                                    <input v-model.number="draft.prix_mal_diar1" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Traitement diarrhee 5-21j (€)
                                    <input v-model.number="draft.prix_mal_diar2et3" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Traitement diarrhee >21j (€)
                                    <input v-model.number="draft.prix_mal_diar4" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Perf diarrhee (€)
                                    <input v-model.number="draft.prix_perf_diar" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Traitement respiratoire (€)
                                    <input v-model.number="draft.prix_mal_respi" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Traitement omphalite (€)
                                    <input v-model.number="draft.prix_mal_omphalite" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Mort diarrhee 0-4j (€)
                                    <input v-model.number="draft.prix_mort_diar1" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Mort diarrhee 5-21j (€)
                                    <input v-model.number="draft.prix_mort_diar2et3" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Mort diarrhee >21j (€)
                                    <input v-model.number="draft.prix_mort_diar4" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Mort respiratoire (€)
                                    <input v-model.number="draft.prix_mort_respi" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Mort omphalite (€)
                                    <input v-model.number="draft.prix_mort_omphalite" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Mort autres (€)
                                    <input v-model.number="draft.prix_mort_autres" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Mort subite (€)
                                    <input v-model.number="draft.prix_mort_subite" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Veau IVV (€/jour)
                                    <input v-model.number="draft.prix_veau_ivv" type="number" min="0" step="0.1" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Veau avortement (€)
                                    <input v-model.number="draft.prix_veau_avortement" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Veau accident velage (€)
                                    <input v-model.number="draft.prix_veau_accident_velage" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <h2 class="font-semibold">Prix fourrages et cereales</h2>
                            <div class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2 lg:grid-cols-4">
                                <label class="grid gap-1.5 text-sm">
                                    Prix ha foin (€)
                                    <input v-model.number="draft.prix_ha_foin" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Prix ha ensilage herbe (€)
                                    <input v-model.number="draft.prix_ha_ensilage_herbe" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Prix ha ensilage mais (€)
                                    <input v-model.number="draft.prix_ha_ensilage_mais" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                                <label class="grid gap-1.5 text-sm">
                                    Prix production cereales (€/t)
                                    <input v-model.number="draft.prix_production_cereales_tonnes" type="number" min="0" class="rounded border border-border bg-background px-3 py-2" />
                                </label>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h2 class="font-semibold">Modeles de commentaires</h2>
                            <p class="text-sm text-muted-foreground">Textes affichés en commentaire selon que l'indicateur est satisfaisant (S) ou non satisfaisant (NS).</p>
                            <div v-for="(item, key) in { tx_mortalite_total_veaux: 'Mortalite totale veaux', tx_diarrhee_veaux_total: 'Diarrhee veaux', tx_respi_veaux: 'Respiratoire veaux', tx_omphalite_veaux: 'Omphalite veaux', ivv: 'IVV', cout_alimentaire_vache: 'Cout alimentaire / vache' }" :key="key" class="rounded-lg border border-border p-3 space-y-2">
                                <p class="text-sm font-medium">{{ item }}</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label class="grid gap-1 text-xs">
                                        Satisfaisant
                                        <textarea v-model="draft[`txt_${key}_s`]" rows="2" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
                                    </label>
                                    <label class="grid gap-1 text-xs">
                                        Non satisfaisant
                                        <textarea v-model="draft[`txt_${key}_ns`]" rows="2" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Tests rapides -->
                    <section v-if="module.slug === 'tests-rapides'" class="space-y-6">
                        <div class="space-y-3">
                            <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                                <div>
                                    <h2 class="font-semibold">Especes disponibles</h2>
                                    <p class="text-sm text-muted-foreground">Ces especes servent a filtrer les tests proposes lors de la saisie.</p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <input
                                        v-model="newRapidTestsSpecies"
                                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                                        placeholder="Nouvelle espece"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                        @click="addRapidTestsSpecies"
                                    >
                                        <Plus class="size-4" />
                                        Ajouter
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="(species, index) in draft.species_options"
                                    :key="`${species}-${index}`"
                                    class="inline-flex items-center gap-2 rounded-full border border-border bg-muted/40 px-3 py-1 text-sm"
                                >
                                    {{ species }}
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-destructive"
                                        @click="removeRapidTestsSpecies(index)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div
                            v-for="group in rapidTestGroups"
                            :key="group.key"
                            class="space-y-3"
                        >
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="font-semibold">{{ group.title }}</h2>
                                    <p class="text-sm text-muted-foreground">{{ group.description }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                    @click="addRapidTestItem(group.key)"
                                >
                                    <Plus class="size-4" />
                                    Ajouter
                                </button>
                            </div>
                            <div
                                v-if="Array.isArray(draft[group.key]) && draft[group.key].length > 0"
                                class="space-y-3"
                            >
                                <div
                                    v-for="(test, index) in draft[group.key]"
                                    :key="`${group.key}-${index}`"
                                    class="space-y-3 rounded-lg border border-border p-4"
                                >
                                    <div
                                        class="grid gap-3 md:grid-cols-[1fr_1.5fr_0.8fr_auto_auto]"
                                    >
                                        <label class="grid gap-1.5 text-sm">
                                            Cle
                                            <input
                                                v-model="test.key"
                                                class="rounded border border-border bg-background px-3 py-2 text-sm"
                                                placeholder="cle_unique"
                                            />
                                        </label>
                                        <label class="grid gap-1.5 text-sm">
                                            Libelle
                                            <input
                                                v-model="test.label"
                                                class="rounded border border-border bg-background px-3 py-2 text-sm"
                                                placeholder="Libelle"
                                            />
                                        </label>
                                        <label
                                            v-if="group.withUnit"
                                            class="grid gap-1.5 text-sm"
                                        >
                                            Unite
                                            <input
                                                v-model="test.unit"
                                                class="rounded border border-border bg-background px-3 py-2 text-sm"
                                                placeholder="Unite"
                                            />
                                        </label>
                                        <div v-else></div>
                                        <label class="flex flex-col items-center gap-2 self-end pb-1.5 text-sm">
                                            Actif
                                            <input
                                                v-model="test.enabled"
                                                type="checkbox"
                                                class="size-4"
                                            />
                                        </label>
                                        <button
                                            type="button"
                                            class="self-end rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            @click="removeRow(group.key, index)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                    <div>
                                        <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Especes actives</p>
                                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                                            <label
                                                v-for="species in rapidTestsSpeciesRows"
                                                :key="species"
                                                class="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    v-model="test.species"
                                                    type="checkbox"
                                                    :value="species"
                                                />
                                                {{ species }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="rounded-lg border border-dashed border-border px-3 py-4 text-sm text-muted-foreground">
                                Aucun element configure.
                            </p>
                        </div>

                        <div class="space-y-3">
                            <h2 class="font-semibold">Sections optionnelles</h2>
                            <p class="text-sm text-muted-foreground">Activez chaque section et les especes pour lesquelles elle doit apparaitre.</p>
                            <div
                                v-if="Array.isArray(draft.optional_sections)"
                                class="space-y-3"
                            >
                                <div
                                    v-for="(section, index) in draft.optional_sections"
                                    :key="section.key"
                                    class="space-y-3 rounded-lg border border-border p-4"
                                >
                                    <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                                        <label class="grid gap-1.5 text-sm">
                                            Libelle
                                            <input
                                                v-model="section.label"
                                                class="rounded border border-border bg-background px-3 py-2 text-sm"
                                                placeholder="Libelle"
                                            />
                                        </label>
                                        <label class="flex flex-col items-center gap-2 self-end pb-1.5 text-sm">
                                            Actif
                                            <input
                                                v-model="section.enabled"
                                                type="checkbox"
                                                class="size-4"
                                            />
                                        </label>
                                    </div>
                                    <div>
                                        <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Especes actives</p>
                                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                                            <label
                                                v-for="species in rapidTestsSpeciesRows"
                                                :key="species"
                                                class="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    v-model="draft.optional_sections[index].species"
                                                    type="checkbox"
                                                    :value="species"
                                                />
                                                {{ species }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Biochimie / Hemogramme -->
                    <section v-if="module.slug === 'tests-biochimie' || module.slug === 'hemogramme'" class="space-y-6">
                        <div class="space-y-3">
                            <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                                <div>
                                    <h2 class="font-semibold">Especes disponibles</h2>
                                    <p class="text-sm text-muted-foreground">Ces especes alimentent le selecteur de saisie et les tableaux de normes.</p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <input
                                        v-model="newParametricSpecies"
                                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                                        placeholder="Nouvelle espece"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                        @click="addParametricSpecies"
                                    >
                                        <Plus class="size-4" />
                                        Ajouter
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="(species, index) in draft.species_options"
                                    :key="`${species}-${index}`"
                                    class="inline-flex items-center gap-2 rounded-full border border-border bg-muted/40 px-3 py-1 text-sm"
                                >
                                    {{ species }}
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-destructive"
                                        @click="removeParametricSpecies(index)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="font-semibold">Parametres</h2>
                                    <p class="text-sm text-muted-foreground">Chaque parametre peut etre active globalement et par espece.</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                                    @click="addParametricParam"
                                >
                                    <Plus class="size-4" />
                                    Ajouter
                                </button>
                            </div>
                            <div v-if="Array.isArray(draft.params) && draft.params.length > 0" class="space-y-3">
                                <div
                                    v-for="(param, index) in draft.params"
                                    :key="`${param.key}-${index}`"
                                    class="space-y-3 rounded-lg border border-border p-4"
                                >
                                    <div class="grid gap-3 md:grid-cols-[1fr_1.5fr_1fr_auto_auto]">
                                        <label class="grid gap-1.5 text-sm">
                                            Cle
                                            <input
                                                v-model="param.key"
                                                class="rounded border border-border bg-background px-3 py-2 text-sm"
                                                placeholder="CLE"
                                                @change="ensureParametricNorms"
                                            />
                                        </label>
                                        <label class="grid gap-1.5 text-sm">
                                            Libelle
                                            <input
                                                v-model="param.label"
                                                class="rounded border border-border bg-background px-3 py-2 text-sm"
                                                placeholder="Libelle"
                                            />
                                        </label>
                                        <label v-if="module.slug === 'hemogramme'" class="grid gap-1.5 text-sm">
                                            Groupe
                                            <select
                                                v-model="param.group"
                                                class="h-10 rounded border border-border bg-background px-3 py-2 text-sm"
                                            >
                                                <option
                                                    v-for="group in hemogrammeGroups"
                                                    :key="group.value"
                                                    :value="group.value"
                                                >
                                                    {{ group.label }}
                                                </option>
                                            </select>
                                        </label>
                                        <div v-else></div>
                                        <label class="flex flex-col items-center gap-2 self-end pb-1.5 text-sm">
                                            Actif
                                            <input
                                                v-model="param.enabled"
                                                type="checkbox"
                                                class="size-4"
                                            />
                                        </label>
                                        <button
                                            type="button"
                                            class="self-end rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            @click="removeParametricParam(index)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                    <div>
                                        <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Especes actives</p>
                                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                                            <label
                                                v-for="species in parametricSpeciesRows"
                                                :key="species"
                                                class="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    v-model="param.species"
                                                    type="checkbox"
                                                    :value="species"
                                                />
                                                {{ species }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="rounded-lg border border-dashed border-border px-3 py-4 text-sm text-muted-foreground">
                                Aucun parametre configure.
                            </p>
                        </div>

                        <div v-if="isObject(draft.norms)" class="space-y-4">
                            <h2 class="font-semibold">Normes par espece</h2>
                            <div
                                v-for="species in parametricSpeciesRows"
                                :key="species"
                                class="space-y-2"
                            >
                                <h3 class="text-sm font-medium text-muted-foreground">{{ species }}</h3>
                                <div v-if="Array.isArray(draft.params)" class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-border text-xs text-muted-foreground">
                                                <th class="w-40 py-1 pr-3 text-left font-medium">Parametre</th>
                                                <th class="py-1 pr-2 text-left font-medium">Min</th>
                                                <th class="py-1 pr-2 text-left font-medium">Max</th>
                                                <th class="py-1 text-left font-medium">Unite</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="param in parametricParamsForSpecies(species)"
                                                :key="param.key"
                                                class="border-b border-border/50"
                                            >
                                                <td class="py-1 pr-3 text-xs font-medium">{{ param.label }}</td>
                                                <td class="py-1 pr-2">
                                                    <input
                                                        v-if="isObject(draft.norms[species]) && isObject(draft.norms[species][param.key])"
                                                        v-model.number="draft.norms[species][param.key].min"
                                                        type="number"
                                                        step="any"
                                                        class="w-20 rounded border border-border bg-background px-1.5 py-0.5 text-sm"
                                                        placeholder="–"
                                                    />
                                                </td>
                                                <td class="py-1 pr-2">
                                                    <input
                                                        v-if="isObject(draft.norms[species]) && isObject(draft.norms[species][param.key])"
                                                        v-model.number="draft.norms[species][param.key].max"
                                                        type="number"
                                                        step="any"
                                                        class="w-20 rounded border border-border bg-background px-1.5 py-0.5 text-sm"
                                                        placeholder="–"
                                                    />
                                                </td>
                                                <td class="py-1">
                                                    <input
                                                        v-if="isObject(draft.norms[species]) && isObject(draft.norms[species][param.key])"
                                                        v-model="draft.norms[species][param.key].unit"
                                                        type="text"
                                                        class="w-24 rounded border border-border bg-background px-1.5 py-0.5 text-xs"
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div
                        class="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium hover:bg-accent"
                                @click="resetToDefaults"
                            >
                                <RotateCcw class="size-4" />
                                Defaults
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-destructive hover:bg-destructive/10"
                                @click="deleteCustomSettings"
                            >
                                <Trash2 class="size-4" />
                                Reinitialiser compte
                            </button>
                        </div>
                        <button
                            type="submit"
                            :disabled="processing"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                        >
                            <Save class="size-4" />
                            {{
                                processing
                                    ? 'Enregistrement...'
                                    : 'Enregistrer les reglages'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
