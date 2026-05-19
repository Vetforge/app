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
    settings_snapshot: Record<string, unknown> | null;
}

const props = defineProps<{
    analysis: Analysis;
    module: ModuleInfo;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: props.module.short_label, href: analysesIndex({ module: props.module.slug }).url },
    { title: `#${props.analysis.id}`, href: '#' },
];

const payload = computed(() => props.analysis.payload as {
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
});
const settings = computed(() => props.analysis.settings_snapshot ?? {});

const BANDELETTE_LABELS: Record<string, string> = {
    densite: 'Densite urinaire', ph: 'pH', leucocytes: 'Leucocytes', nitrite: 'Nitrite',
    proteine: 'Proteine', glucose: 'Glucose', cetone: 'Cetone', urobilinogene: 'Urobilinogene',
    bilirubine: 'Bilirubine', sang: 'Sang', hemoglobine: 'Hemoglobine',
};

function resultBadge(value: string): string {
    if (value === 'pos') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
    if (value === 'neg') return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
    return 'bg-muted text-muted-foreground';
}

function resultLabel(value: string): string {
    if (value === 'pos') return 'Positif';
    if (value === 'neg') return 'Negatif';
    if (value === 'douteux') return 'Douteux';
    return value || '-';
}

const elisaEntries = computed(() =>
    Object.entries(payload.value.elisa ?? {}).filter(([, v]) => v !== '' && v != null)
);
const biochemEntries = computed(() =>
    Object.entries(payload.value.biochem_rapide ?? {}).filter(([, v]) => v !== '' && v != null)
);
const pcrEntries = computed(() =>
    Object.entries(payload.value.pcr ?? {}).filter(([, v]) => v !== '' && v != null)
);
const bandeletteEntries = computed(() =>
    Object.entries(payload.value.bandelette ?? {}).filter(([, v]) => v !== '' && v != null)
);
const frottisEntries = computed(() =>
    Object.entries(payload.value.frottis ?? {}).filter(([, v]) => v !== '' && v != null)
);

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR');
}

function configuredLabel(group: string, key: string): string {
    const items = settings.value[group];

    if (Array.isArray(items)) {
        const item = items.find((entry: any) => entry?.key === key);

        if (item?.label) return String(item.label);
    }

    return key.replace(/_/g, ' ');
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

            <!-- Header info -->
            <section class="grid gap-4 rounded-xl border border-border bg-card p-3 sm:p-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Eleveur</p>
                    <p class="font-medium">{{ analysis.breeder.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ analysis.breeder.postal_code }} {{ analysis.breeder.city }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Animal / Espece</p>
                    <p class="font-medium">{{ analysis.animal_nom ?? '-' }}</p>
                    <p class="text-sm text-muted-foreground">{{ payload.species }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Date</p>
                    <p class="text-sm">{{ formatDate(analysis.analyzed_at) }}</p>
                    <p v-if="payload.sample_nature" class="text-xs text-muted-foreground">{{ payload.sample_nature }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Intervenant</p>
                    <p class="font-medium">{{ analysis.intervenant ?? '-' }}</p>
                </div>
            </section>

            <!-- ELISA -->
            <section v-if="elisaEntries.length > 0" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">Tests ELISA</p>
                <div class="flex flex-wrap gap-2">
                    <div v-for="([key, value]) in elisaEntries" :key="key" class="flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5">
                        <span class="text-sm">{{ configuredLabel('elisa_tests', key) }}</span>
                        <span :class="['rounded px-1.5 py-0.5 text-xs font-medium', resultBadge(value)]">{{ resultLabel(value) }}</span>
                    </div>
                </div>
            </section>

            <!-- PCR -->
            <section v-if="pcrEntries.length > 0" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">PCR</p>
                <div class="flex flex-wrap gap-2">
                    <div v-for="([key, value]) in pcrEntries" :key="key" class="flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5">
                        <span class="text-sm">{{ configuredLabel('pcr_tests', key) }}</span>
                        <span :class="['rounded px-1.5 py-0.5 text-xs font-medium', resultBadge(value)]">{{ resultLabel(value) }}</span>
                    </div>
                </div>
            </section>

            <!-- Biochimie rapide -->
            <section v-if="biochemEntries.length > 0" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">Biochimie rapide</p>
                <div class="flex flex-wrap gap-2">
                    <div v-for="([key, value]) in biochemEntries" :key="key" class="flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5">
                        <span class="text-sm">{{ configuredLabel('biochem_rapide', key) }}</span>
                        <span class="text-sm font-medium">{{ value }}</span>
                    </div>
                </div>
            </section>

            <!-- Bandelette -->
            <section v-if="bandeletteEntries.length > 0" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">{{ configuredLabel('optional_sections', 'bandelette_urinaire') }}</p>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="([key, value]) in bandeletteEntries" :key="key" class="flex items-center justify-between rounded-lg border border-border px-3 py-1.5">
                        <span class="text-sm text-muted-foreground">{{ BANDELETTE_LABELS[key] ?? key }}</span>
                        <span class="text-sm font-medium">{{ value }}</span>
                    </div>
                </div>
            </section>

            <!-- Frottis sanguin -->
            <section v-if="frottisEntries.length > 0" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <p class="mb-3 text-xs font-semibold uppercase text-muted-foreground">{{ configuredLabel('optional_sections', 'frottis_sanguin') }}</p>
                <div class="flex flex-wrap gap-2">
                    <div v-for="([key, value]) in frottisEntries" :key="key" class="flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5">
                        <span class="text-sm">{{ key.replace(/_/g, ' ') }}</span>
                        <span :class="['rounded px-1.5 py-0.5 text-xs font-medium', resultBadge(value)]">{{ resultLabel(value) }}</span>
                    </div>
                </div>
            </section>

            <!-- Commentaires -->
            <section v-if="payload.commemoratifs || payload.commentaires" class="rounded-xl border border-border bg-card p-3 sm:p-5">
                <div v-if="payload.commemoratifs" class="mb-3">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commemoratifs</p>
                    <p class="text-sm">{{ payload.commemoratifs }}</p>
                </div>
                <div v-if="payload.commentaires">
                    <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Commentaires</p>
                    <div class="text-sm" v-html="String(payload.commentaires || '')"></div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
