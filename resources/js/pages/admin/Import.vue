<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { store as importStore } from '@/routes/admin/import';
import type { BreadcrumbItem } from '@/types';

const file = ref<File | null>(null);
const importing = ref(false);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Import aliments', href: '#' },
];

const requiredColumns = [
    ['code_inra', 'Identifiant stable'],
    ['type', 'Fourrage, Conc ou Mineral'],
    ['libelle0', 'Texte'],
    ['famille_botanique', 'mais, luzerne, legumineuse, graminee ou autre'],
    [
        'procede_technologique',
        'vert, ensile, foin, deshydrate, paille, concentre, mineral ou autre',
    ],
];

function selectFile(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit(): void {
    if (!file.value) return;
    importing.value = true;
    router.post(
        importStore().url,
        { file: file.value },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                importing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Import aliments" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-5xl flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold text-foreground">
                    Import CSV des aliments
                </h1>
                <p class="text-sm text-muted-foreground">
                    Séparateur point-virgule. Chaque ligne doit respecter le
                    même contrat que le formulaire aliment.
                </p>
            </div>

            <section
                class="rounded-xl border border-border bg-card p-5 shadow-sm"
            >
                <h2 class="font-semibold text-foreground">
                    Colonnes d’identification obligatoires
                </h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody>
                            <tr
                                v-for="column in requiredColumns"
                                :key="column[0]"
                                class="border-t border-border first:border-0"
                            >
                                <th class="px-2 py-2 font-mono text-xs">
                                    {{ column[0] }}
                                </th>
                                <td class="px-2 py-2 text-muted-foreground">
                                    {{ column[1] }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-sm text-muted-foreground">
                    Voie tabulée : <code>ms;ufl;ufv;pdi;uel;uem;ueb</code>. Voie
                    calculée : <code>mo;mat;d_mo;eb;dt6_n;dr_n</code>. Unités :
                    MS et digestibilités en %, constituants en g/kg MS, EB/EM en
                    kcal/kg MS, oligoéléments dont <code>molybdene</code> en
                    mg/kg MS, vitamines en UI/kg MS et acides aminés en % PDI.
                </p>
            </section>

            <form
                class="rounded-xl border border-border bg-card p-5 shadow-sm"
                @submit.prevent="submit"
            >
                <label class="text-sm font-medium text-foreground" for="file"
                    >Fichier CSV</label
                >
                <input
                    id="file"
                    type="file"
                    accept=".csv,text/csv,text/plain"
                    required
                    class="mt-2 block w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                    @change="selectFile"
                />
                <button
                    type="submit"
                    :disabled="!file || importing"
                    class="mt-4 rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                >
                    {{ importing ? 'Import en cours…' : 'Importer' }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
