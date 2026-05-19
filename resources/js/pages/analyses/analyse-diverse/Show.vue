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
    { title: `Analyse #${props.analysis.id}`, href: '#' },
];

const payload = computed(() => props.analysis.payload as {
    species: string;
    sample_count: number;
    commemoratifs: string;
    analyses: Array<{ type: string; results: string }>;
    commentaires: string;
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
                    <p class="text-sm">Prelevement : {{ formatDate(analysis.sampled_at) }}</p>
                    <p class="text-sm">Analyse : {{ formatDate(analysis.analyzed_at) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Intervenant</p>
                    <p class="font-medium">{{ analysis.intervenant ?? '-' }}</p>
                </div>
            </section>

            <section class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <div class="mb-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    <span>Espece : {{ payload.species }}</span>
                    <span>· {{ payload.sample_count }} analyse(s)</span>
                </div>
                <div v-if="payload.commemoratifs" class="mb-4">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commemoratifs</p>
                    <p class="whitespace-pre-wrap text-sm">{{ payload.commemoratifs }}</p>
                </div>
                <div class="space-y-4">
                    <div v-for="(analyse, index) in payload.analyses" :key="index" class="rounded-lg border border-border p-3">
                        <p class="mb-2 text-xs font-semibold uppercase text-muted-foreground">Analyse n°{{ index + 1 }}</p>
                        <p v-if="analyse.type" class="mb-1 text-sm font-medium">{{ analyse.type }}</p>
                        <p class="whitespace-pre-wrap text-sm">{{ analyse.results || '-' }}</p>
                    </div>
                </div>
            </section>

            <section v-if="payload.commentaires" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commentaires</p>
                <p class="whitespace-pre-wrap text-sm">{{ payload.commentaires }}</p>
            </section>
        </div>
    </AppLayout>
</template>
