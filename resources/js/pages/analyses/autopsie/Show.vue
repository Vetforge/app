<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Edit, FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    edit as analysisEdit,
    index as analysesIndex,
    pdf as analysisPdf,
} from '@/actions/App/Http/Controllers/VeterinaryAnalysisController';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface ModuleInfo {
    slug: string;
    label: string;
    short_label: string;
}

interface Analysis {
    id: number;
    breeder: { name: string; address: string | null; postal_code: string | null; city: string | null; herd_number: string | null };
    animal_nom: string | null;
    sampled_at: string | null;
    analyzed_at: string | null;
    intervenant: string | null;
    payload: Record<string, unknown>;
}

const props = defineProps<{
    analysis: Analysis;
    module: ModuleInfo;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: props.module.short_label, href: analysesIndex({ module: props.module.slug }).url },
    { title: `Autopsie #${props.analysis.id}`, href: '#' },
];

const payload = computed(() => props.analysis.payload as {
    identification: string;
    species: string;
    sexe: string;
    conformation: string;
    conservation: string;
    engraissement: string;
    poids: number | null;
    commemoratifs: string;
    lesions: string;
    conclusion: string;
});

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR');
}
</script>

<template>
    <Head :title="`${module.label} #${analysis.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="analysis-show mx-auto flex w-full min-w-0 max-w-5xl flex-col gap-4 px-3 py-4 sm:gap-6 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ module.label }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ analysis.breeder.name }}<span v-if="analysis.animal_nom"> - {{ analysis.animal_nom }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="analysisEdit({ analysis: analysis.id }).url" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium hover:bg-accent">
                        <Edit class="size-4" />
                        Modifier
                    </Link>
                    <a :href="analysisPdf({ analysis: analysis.id }).url" target="_blank" rel="noreferrer" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                        <FileText class="size-4" />
                        PDF
                    </a>
                </div>
            </div>

            <section class="grid gap-4 rounded-xl border border-border bg-card p-3 sm:p-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Eleveur</p>
                    <p class="font-medium">{{ analysis.breeder.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ analysis.breeder.postal_code }} {{ analysis.breeder.city }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Animal</p>
                    <p class="font-medium">{{ analysis.animal_nom ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Dates</p>
                    <p class="text-sm">Mort : {{ formatDate(analysis.sampled_at) }}</p>
                    <p class="text-sm">Autopsie : {{ formatDate(analysis.analyzed_at) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Intervenant</p>
                    <p class="font-medium">{{ analysis.intervenant ?? '-' }}</p>
                </div>
            </section>

            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <div class="mb-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <div v-if="payload.identification">
                        <span class="text-xs uppercase text-muted-foreground">Identification</span>
                        <p class="font-medium">{{ payload.identification }}</p>
                    </div>
                    <div>
                        <span class="text-xs uppercase text-muted-foreground">Espece</span>
                        <p class="font-medium">{{ payload.species }}</p>
                    </div>
                    <div v-if="payload.sexe">
                        <span class="text-xs uppercase text-muted-foreground">Sexe</span>
                        <p class="font-medium">{{ payload.sexe }}</p>
                    </div>
                    <div v-if="payload.poids">
                        <span class="text-xs uppercase text-muted-foreground">Poids</span>
                        <p class="font-medium">{{ payload.poids }} kg</p>
                    </div>
                    <div v-if="payload.engraissement">
                        <span class="text-xs uppercase text-muted-foreground">Engraissement</span>
                        <p class="font-medium">{{ payload.engraissement }}</p>
                    </div>
                    <div v-if="payload.conformation">
                        <span class="text-xs uppercase text-muted-foreground">Conformation</span>
                        <p class="font-medium">{{ payload.conformation }}</p>
                    </div>
                    <div v-if="payload.conservation">
                        <span class="text-xs uppercase text-muted-foreground">Conservation</span>
                        <p class="font-medium">{{ payload.conservation }}</p>
                    </div>
                </div>
                <div v-if="payload.commemoratifs">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commemoratifs</p>
                    <p class="whitespace-pre-wrap text-sm">{{ payload.commemoratifs }}</p>
                </div>
            </section>

            <section v-if="payload.lesions" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-2 text-xs font-semibold uppercase text-muted-foreground">Lesions</p>
                <p class="whitespace-pre-wrap font-mono text-sm">{{ payload.lesions }}</p>
            </section>

            <section v-if="payload.conclusion" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-2 text-xs font-semibold uppercase text-muted-foreground">Conclusion necropique</p>
                <p class="whitespace-pre-wrap text-sm">{{ payload.conclusion }}</p>
            </section>
        </div>
    </AppLayout>
</template>
