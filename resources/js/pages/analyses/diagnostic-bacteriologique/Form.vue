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

const germFamilies = computed<string[]>(() => props.settings.germ_families ?? []);
const antibioticRows = computed(() => {
    const items = props.settings.antibiotics;
    if (!Array.isArray(items)) return [];
    return items.filter((item: any) => item.enabled !== false);
});

function setGermCount(count: number): void {
    const safeCount = Math.max(0, count);
    form.payload.germ_count = safeCount;
    const current = Array.isArray(form.payload.germs) ? form.payload.germs : [];
    const entryCount = safeCount >= 3 ? 0 : safeCount;
    form.payload.germs = Array.from({ length: entryCount }, (_, index) => {
        const existing = current[index] ?? {};
        return { family: existing.family ?? germFamilies.value[0] ?? 'Autre', antibiotics: existing.antibiotics ?? {} };
    });
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
    () => form.payload.germ_count,
    (count) => setGermCount(Number(count ?? 0)),
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
                        <h2>Diagnostic bacteriologique</h2>
                        <p>Prelevement, germes et antibiogramme.</p>
                    </div>
                </div>

                <div class="grid gap-2 md:grid-cols-3">
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="bact_nature">Prelevement</label>
                        <input id="bact_nature" v-model="form.payload.sample_nature" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="bact_identification">Identification</label>
                        <input id="bact_identification" v-model="form.payload.sample_identification" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="germ_count">Nombre de germes</label>
                        <input id="germ_count" v-model.number="form.payload.germ_count" type="number" min="0" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                        <span v-if="form.payload.germ_count === 0" class="text-xs font-medium text-green-700">→ Affichera : Sterile</span>
                        <span v-else-if="form.payload.germ_count >= 3" class="text-xs font-medium text-red-700">→ Affichera : Contaminé</span>
                    </div>
                </div>

                <div class="grid gap-1">
                    <label class="text-sm font-medium" for="bact_commemoratives">Commemoratifs</label>
                    <textarea id="bact_commemoratives" v-model="form.payload.commemoratives" rows="3" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
                </div>

                <div v-for="(germ, germIndex) in form.payload.germs" :key="germIndex" class="analysis-subsection">
                    <div class="grid gap-1.5 md:w-80">
                        <label class="text-sm font-medium" :for="`germ-family-${germIndex}`">Germe {{ Number(germIndex) + 1 }}</label>
                        <select :id="`germ-family-${germIndex}`" v-model="germ.family" class="rounded border border-border bg-background px-2 py-1 text-sm">
                            <option v-for="family in germFamilies" :key="family" :value="family">{{ family }}</option>
                        </select>
                    </div>
                    <div class="analysis-table-wrap">
                        <table class="w-full text-sm">
                            <thead class="hidden sm:table-header-group">
                                <tr class="border-b border-border">
                                    <th class="px-2 py-1 text-left font-medium text-xs">Antibiotique</th>
                                    <th class="px-2 py-1 text-left font-medium text-xs">Dose</th>
                                    <th class="px-2 py-1 text-left font-medium text-xs">Diametre</th>
                                    <th class="px-2 py-1 text-left font-medium text-xs">Seuils</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="antibiotic in antibioticRows" :key="antibiotic.code" class="border-b border-border/50">
                                    <td class="px-2 py-1">
                                        <div>{{ antibiotic.code }} - {{ antibiotic.label }}</div>
                                        <div class="mt-0.5 flex flex-wrap gap-x-2 gap-y-0.5 text-xs text-muted-foreground sm:hidden">
                                            <span v-if="antibiotic.dose">{{ antibiotic.dose }}</span>
                                            <span>I >= {{ antibiotic.intermediate_min }} / S >= {{ antibiotic.sensitive_min }}</span>
                                        </div>
                                    </td>
                                    <td class="hidden px-2 py-1 text-muted-foreground sm:table-cell">{{ antibiotic.dose ?? '-' }}</td>
                                    <td class="px-2 py-1">
                                        <input v-model.number="germ.antibiotics[antibiotic.code]" type="number" min="0" step="1" class="w-28 rounded border border-border bg-background px-2 py-1" />
                                    </td>
                                    <td class="hidden px-2 py-1 text-muted-foreground sm:table-cell">I >= {{ antibiotic.intermediate_min }} / S >= {{ antibiotic.sensitive_min }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Conseils bacteriologie</h2>
                        <p>Texte qui apparaitra dans le compte rendu.</p>
                    </div>
                </div>
                <div class="grid gap-1">
                    <label class="text-sm font-medium" for="bact_advice">Conseils</label>
                    <textarea id="bact_advice" v-model="form.payload.advice" rows="4" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
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
