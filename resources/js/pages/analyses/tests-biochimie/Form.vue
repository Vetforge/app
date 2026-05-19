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

interface ParamDef {
    key: string;
    label: string;
    species?: string[];
    enabled: boolean;
}

interface NormRange {
    min: number | null;
    max: number | null;
    unit: string;
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
        params: Record<string, string>;
        commentaires: string;
    },
});
form.payload.params ??= {};

const speciesOptions: string[] = Array.isArray(props.settings.species_options)
    ? props.settings.species_options
    : ['Bovin', 'Chien', 'Chat'];

const enabledParams = computed<ParamDef[]>(() => {
    const all: ParamDef[] = Array.isArray(props.settings.params) ? props.settings.params : [];
    return all.filter(p => p.enabled !== false && matchesSpecies(p.species, form.payload.species));
});

function matchesSpecies(speciesList: string[] | undefined, current: string): boolean {
    if (!Array.isArray(speciesList)) return true;
    if (speciesList.length === 0) return false;
    return speciesList.includes(current);
}

watch(() => form.payload.species, () => {
    const validParamKeys = new Set(enabledParams.value.map(param => param.key));

    Object.keys(form.payload.params).forEach(key => {
        if (!validParamKeys.has(key)) delete form.payload.params[key];
    });
});

function normForSpecies(paramKey: string): NormRange | null {
    const norms = props.settings.norms;
    if (!norms || typeof norms !== 'object') return null;
    const speciesNorms = norms[form.payload.species];
    if (!speciesNorms || typeof speciesNorms !== 'object') return null;
    return speciesNorms[paramKey] ?? null;
}

function getValueStatus(paramKey: string, value: string): 'low' | 'high' | 'normal' | null {
    if (!value || value === '') return null;
    const norm = normForSpecies(paramKey);
    if (!norm) return null;
    const num = parseFloat(value);
    if (isNaN(num)) return null;
    if (norm.min !== null && num < norm.min) return 'low';
    if (norm.max !== null && num > norm.max) return 'high';
    return 'normal';
}

function statusClass(status: 'low' | 'high' | 'normal' | null): string {
    if (status === 'low' || status === 'high') return 'border-orange-400 bg-orange-50 dark:bg-orange-950/20';
    return '';
}

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
            <div>
                <h1 class="text-xl font-bold text-foreground">{{ isEdit ? 'Modifier' : 'Nouveau' }} {{ module.short_label }}</h1>
                <p class="text-xs text-muted-foreground">{{ module.label }}</p>
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
                        <label class="text-sm font-medium" for="sampled_at">Prelevement</label>
                        <input id="sampled_at" v-model="form.sampled_at" type="date" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-1 xl:col-span-2">
                        <label class="text-sm font-medium" for="analyzed_at">Analyse</label>
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
                    <div class="grid gap-1 xl:col-span-4">
                        <label class="text-sm font-medium" for="commemoratifs">Commemoratifs</label>
                        <input id="commemoratifs" v-model="form.payload.commemoratifs" class="h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                    </div>
                </div>
            </section>

            <!-- Parametres biochimiques -->
            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Parametres biochimiques</h2>
                        <p class="text-xs text-muted-foreground">Valeurs hors normes surlignees en orange.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-xs text-muted-foreground">
                                <th class="py-1 pr-3 text-left font-medium">Parametre</th>
                                <th class="py-1 pr-3 text-left font-medium">Valeur</th>
                                <th class="py-1 pr-3 text-left font-medium">Normes</th>
                                <th class="py-1 text-left font-medium">Unite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="param in enabledParams" :key="param.key" class="border-b border-border/50">
                                <td class="py-1 pr-3 text-xs font-medium">{{ param.label }}</td>
                                <td class="py-1 pr-3">
                                    <input
                                        v-model="form.payload.params[param.key]"
                                        type="text"
                                        inputmode="decimal"
                                        :class="['w-20 rounded border bg-background px-1.5 py-0.5 text-sm', statusClass(getValueStatus(param.key, form.payload.params[param.key] ?? ''))]"
                                        placeholder="-"
                                    />
                                </td>
                                <td class="py-1 pr-3 text-xs text-muted-foreground">
                                    <template v-if="normForSpecies(param.key)">
                                        <span v-if="normForSpecies(param.key)!.min !== null">{{ normForSpecies(param.key)!.min }}</span>
                                        <span v-if="normForSpecies(param.key)!.min !== null && normForSpecies(param.key)!.max !== null"> – </span>
                                        <span v-if="normForSpecies(param.key)!.max !== null">{{ normForSpecies(param.key)!.max }}</span>
                                    </template>
                                    <span v-else>–</span>
                                </td>
                                <td class="py-1 text-xs text-muted-foreground">{{ normForSpecies(param.key)?.unit ?? '' }}</td>
                            </tr>
                        </tbody>
                    </table>
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
