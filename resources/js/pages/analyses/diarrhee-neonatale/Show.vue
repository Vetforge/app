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
    breeder: {
        name: string;
        address: string | null;
        postal_code: string | null;
        city: string | null;
        herd_number: string | null;
    };
    animal_nom: string | null;
    sampled_at: string | null;
    analyzed_at: string | null;
    intervenant: string | null;
    payload: Record<string, unknown>;
    results: Record<string, unknown> | null;
    settings_snapshot: Record<string, unknown> | null;
}

const props = defineProps<{
    analysis: Analysis;
    module: ModuleInfo;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    {
        title: props.module.short_label,
        href: analysesIndex({ module: props.module.slug }).url,
    },
    { title: `Analyse #${props.analysis.id}`, href: '#' },
];

const results = computed<Record<string, any>>(
    () => (props.analysis.results ?? {}) as Record<string, any>,
);
const payload = computed<Record<string, any>>(
    () => props.analysis.payload as Record<string, any>,
);
const settings = computed<Record<string, any>>(
    () => (props.analysis.settings_snapshot ?? {}) as Record<string, any>,
);

const visiblePathogens = computed<
    Array<{
        key: string;
        label: string;
        tests?: string[];
        requires_option?: string;
    }>
>(() =>
    ((settings.value.pathogens as Array<any>) ?? []).filter(
        (pathogen: any) =>
            pathogen.enabled !== false && isVisiblePathogen(pathogen),
    ),
);

const legacyPathogenTests: Record<string, string[]> = {
    rotavirus: ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
    coronavirus: ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
    ecoli_k99: ['Kitvia', 'Speed V-Diar', 'Speed V-Diar 4', 'Quick Diar 5'],
    ecoli_cs31a: ['Speed V-Diar', 'Speed V-Diar 4'],
    clostridium_perfringens: ['Quick Diar 5'],
    cryptosporidies: [
        'Kitvia',
        'Speed V-Diar',
        'Speed V-Diar 4',
        'Quick Diar 5',
    ],
    giardia: ['Kitvia'],
};

function normalizeDiarrheaTestName(value: unknown): string {
    const test = String(value ?? '')
        .trim()
        .toLowerCase();
    if (test.includes('kitvia')) return 'kitvia';
    if (test.includes('quick diar')) return 'quick diar 5';
    if (test.includes('speed v-diar')) return 'speed v-diar';
    return test;
}

function isVisiblePathogen(pathogen: Record<string, any>): boolean {
    if (
        (pathogen.requires_option === 'coccidiosis_test' ||
            pathogen.key === 'coccidies') &&
        payload.value.coccidiosis_test !== true
    ) {
        return false;
    }

    const testName = normalizeDiarrheaTestName(payload.value.test_name);
    const knownTests = ['kitvia', 'speed v-diar', 'quick diar 5'];
    const pathogenTests = Array.isArray(pathogen.tests)
        ? pathogen.tests
        : (legacyPathogenTests[String(pathogen.key)] ?? []);

    if (
        testName === '' ||
        !knownTests.includes(testName) ||
        pathogenTests.length === 0
    ) {
        return true;
    }

    return pathogenTests.map(normalizeDiarrheaTestName).includes(testName);
}

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR');
}

function formatResult(value: unknown, unit = ''): string {
    if (value === null || value === undefined || value === '') return '-';
    return `${value}${unit ? ` ${unit}` : ''}`;
}

function scaleLabel(value: string | number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    const scale =
        (settings.value.scale as Array<{ value: string; label: string }>) ?? [];
    return scale.find((s) => s.value === String(value))?.label ?? String(value);
}

function pathogenResultClass(
    value: string | number | null | undefined,
): string {
    const v = String(value ?? '0');
    if (v === '0') return 'text-muted-foreground';
    return 'font-medium text-amber-700';
}
</script>

<template>
    <Head :title="`${module.label} #${analysis.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="analysis-show mx-auto flex w-full max-w-5xl min-w-0 flex-col gap-4 px-3 py-4 sm:gap-6 sm:p-6"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ module.label }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ analysis.breeder.name
                        }}<span v-if="analysis.animal_nom">
                            - {{ analysis.animal_nom }}</span
                        >
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="analysisEdit({ analysis: analysis.id }).url"
                        class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium hover:bg-accent"
                    >
                        <Edit class="size-4" />
                        Modifier
                    </Link>
                    <a
                        :href="analysisPdf({ analysis: analysis.id }).url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        <FileText class="size-4" />
                        PDF
                    </a>
                </div>
            </div>

            <section
                class="grid gap-4 rounded-xl border border-border bg-card p-3 sm:grid-cols-2 sm:p-5 lg:grid-cols-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Eleveur
                    </p>
                    <p class="font-medium">{{ analysis.breeder.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ analysis.breeder.postal_code }}
                        {{ analysis.breeder.city }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Animal
                    </p>
                    <p class="font-medium">{{ analysis.animal_nom ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">Dates</p>
                    <p class="text-sm">
                        Prelevement : {{ formatDate(analysis.sampled_at) }}
                    </p>
                    <p class="text-sm">
                        Analyse : {{ formatDate(analysis.analyzed_at) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Intervenant
                    </p>
                    <p class="font-medium">{{ analysis.intervenant ?? '-' }}</p>
                </div>
            </section>

            <section
                class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-3 sm:p-5"
            >
                <div
                    class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
                >
                    <span v-if="payload.test_name"
                        >Test : {{ payload.test_name }}</span
                    >
                    <span v-if="payload.sample_nature"
                        >· {{ payload.sample_nature }}</span
                    >
                    <span v-if="payload.sample_name"
                        >· {{ payload.sample_name }}</span
                    >
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs text-muted-foreground uppercase">
                            Agents positifs
                        </p>
                        <p class="text-lg font-semibold">
                            {{ results.positive_count ?? 0 }}
                        </p>
                    </div>
                </div>
                <div class="rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead class="hidden sm:table-header-group">
                            <tr class="border-b border-border bg-muted/40">
                                <th class="px-3 py-2 text-left font-medium">
                                    Agent pathogene
                                </th>
                                <th class="px-3 py-2 text-left font-medium">
                                    Resultat
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="pathogen in visiblePathogens"
                                :key="pathogen.key"
                                class="border-b border-border/50 last:border-b-0"
                            >
                                <td class="px-3 py-2">
                                    <div class="font-medium">
                                        {{ pathogen.label }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-xs sm:hidden"
                                        :class="
                                            pathogenResultClass(
                                                (payload.pathogens as any)?.[
                                                    pathogen.key
                                                ],
                                            )
                                        "
                                    >
                                        {{
                                            scaleLabel(
                                                (payload.pathogens as any)?.[
                                                    pathogen.key
                                                ] ?? '0',
                                            )
                                        }}
                                    </div>
                                </td>
                                <td
                                    class="hidden px-3 py-2 sm:table-cell"
                                    :class="
                                        pathogenResultClass(
                                            (payload.pathogens as any)?.[
                                                pathogen.key
                                            ],
                                        )
                                    "
                                >
                                    {{
                                        scaleLabel(
                                            (payload.pathogens as any)?.[
                                                pathogen.key
                                            ] ?? '0',
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="
                        results.positives &&
                        Object.keys(results.positives as object).length > 0
                    "
                    class="space-y-2"
                >
                    <p class="text-sm font-semibold">Agents detectes</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="(pos, key) in results.positives as Record<
                                string,
                                any
                            >"
                            :key="key"
                            class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800"
                        >
                            {{ pos.label }}
                        </span>
                    </div>
                </div>
            </section>

            <section
                class="flex flex-col gap-4 rounded-xl border border-border bg-card p-3 sm:p-5"
            >
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <p
                            class="mb-1 text-xs font-semibold text-muted-foreground uppercase"
                        >
                            Conseils preventifs
                        </p>
                        <p class="text-sm whitespace-pre-line">
                            {{ formatResult(payload.advice_preventive) }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="mb-1 text-xs font-semibold text-muted-foreground uppercase"
                        >
                            Conseils curatifs
                        </p>
                        <p class="text-sm whitespace-pre-line">
                            {{ formatResult(payload.advice_curative) }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
