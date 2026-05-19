<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Minus, Plus, Save, Trash2 } from 'lucide-vue-next';
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

type Errors = Record<string, string>;

const SPECIES_OPTIONS = ['Bovin', 'Ovin', 'Caprin', 'Porcin', 'Equin', 'Volaille', 'Chien', 'Chat'];

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
        sample_count: number;
        commemoratifs: string;
        analyses: Array<{ type: string; results: string }>;
        commentaires: string;
    },
});

watch(
    () => form.payload.sample_count,
    (count) => {
        const current = form.payload.analyses.length;
        if (count > current) {
            for (let i = current; i < count; i++) {
                form.payload.analyses.push({ type: '', results: '' });
            }
        } else if (count < current) {
            form.payload.analyses.splice(count);
        }
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: props.module.short_label, href: analysesIndex({ module: props.module.slug }).url },
    { title: isEdit.value ? 'Modifier' : 'Nouvelle analyse', href: '#' },
];

function submit(): void {
    processing.value = true;
    errors.value = {};
    const options = {
        preserveScroll: true,
        onError: (serverErrors: Errors) => {
            errors.value = serverErrors;
        },
        onFinish: () => {
            processing.value = false;
        },
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
                    <h1 class="text-xl font-bold text-foreground">{{ isEdit ? 'Modifier analyse' : 'Nouvelle analyse' }}</h1>
                    <p class="text-xs text-muted-foreground">{{ module.label }}</p>
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
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Description</h2>
                        <p>Espece, nombre d'analyses et commemoratifs.</p>
                    </div>
                </div>
                <div class="grid gap-2 md:grid-cols-3">
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="species">Espece</label>
                        <select id="species" v-model="form.payload.species" class="rounded border border-border bg-background px-2 py-1 text-sm">
                            <option v-for="s in SPECIES_OPTIONS" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" for="sample_count">Nombre d'analyses (1-6)</label>
                        <input id="sample_count" v-model.number="form.payload.sample_count" type="number" min="1" max="6" class="rounded border border-border bg-background px-2 py-1 text-sm" />
                    </div>
                </div>
                <div class="grid gap-1">
                    <label class="text-sm font-medium" for="commemoratifs">Commemoratifs</label>
                    <textarea id="commemoratifs" v-model="form.payload.commemoratifs" rows="3" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Resultats des analyses</h2>
                        <p>Type et resultats pour chaque analyse.</p>
                    </div>
                </div>
                <div v-for="(analyse, index) in form.payload.analyses" :key="index" class="grid gap-2 rounded-md border border-border bg-muted/20 p-3">
                    <p class="text-xs font-semibold text-muted-foreground uppercase">Analyse n°{{ index + 1 }}</p>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" :for="`type-${index}`">Type d'analyse</label>
                        <input :id="`type-${index}`" v-model="analyse.type" class="rounded border border-border bg-background px-2 py-1 text-sm" maxlength="100" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-sm font-medium" :for="`results-${index}`">Resultats</label>
                        <textarea :id="`results-${index}`" v-model="analyse.results" rows="4" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
                    </div>
                </div>
            </section>

            <section class="analysis-section">
                <div class="analysis-section-heading">
                    <div>
                        <h2>Commentaires</h2>
                    </div>
                </div>
                <div class="grid gap-1">
                    <textarea id="commentaires" v-model="form.payload.commentaires" rows="4" class="rounded border border-border bg-background px-2 py-1 text-sm"></textarea>
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
@media (max-width: 640px) {
    .analysis-section {
        padding: 0.5rem;
    }
}
</style>
