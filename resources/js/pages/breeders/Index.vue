<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Edit, Plus, Search, Trash2, Upload } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import {
    create as breederCreate,
    destroy as breederDestroy,
    edit as breederEdit,
    importCsv as breederImport,
    index as breedersIndex,
} from '@/actions/App/Http/Controllers/BreederController';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Breeder {
    id: number;
    name: string;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    phone: string | null;
    email: string | null;
    herd_number: string | null;
    analyses_count: number;
}

interface PaginatedBreeders {
    data: Breeder[];
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    breeders: PaginatedBreeders;
    filters: { search?: string };
    exampleCsvUrl: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Eleveurs', href: breedersIndex() },
];

const search = ref(props.filters.search ?? '');
const importFile = ref<File | null>(null);
let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(breedersIndex().url, { search: value }, { preserveState: true, replace: true });
    }, 350);
});

function deleteBreeder(breeder: Breeder): void {
    if (confirm(`Supprimer "${breeder.name}" ? Les analyses rattachees seront aussi supprimees.`)) {
        router.delete(breederDestroy({ breeder: breeder.id }).url);
    }
}

function uploadCsv(): void {
    if (! importFile.value) {
        return;
    }

    router.post(breederImport().url, { file: importFile.value }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importFile.value = null;
        },
    });
}
</script>

<template>
    <Head title="Eleveurs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Eleveurs</h1>
                    <p class="text-sm text-muted-foreground">Base privee rattachee a votre compte.</p>
                </div>
                <Link
                    :href="breederCreate().url"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                >
                    <Plus class="size-4" />
                    Nouvel eleveur
                </Link>
            </div>

            <div class="grid gap-3 lg:grid-cols-[1fr_auto]">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Rechercher par nom, ville, email ou numero cheptel..."
                        class="h-10 w-full rounded-lg border border-border bg-background py-2 pr-3 pl-10 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <a
                        :href="exampleCsvUrl"
                        download
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                    >
                        <Download class="size-4" />
                        Exemple CSV
                    </a>
                    <input
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                        @change="importFile = ($event.target as HTMLInputElement).files?.[0] ?? null"
                    />
                    <button
                        type="button"
                        :disabled="!importFile"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent disabled:opacity-50"
                        @click="uploadCsv"
                    >
                        <Upload class="size-4" />
                        Importer CSV
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border bg-muted/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Nom</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Coordonnees</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Cheptel</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Analyses</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/50">
                            <tr v-for="breeder in breeders.data" :key="breeder.id" class="hover:bg-accent/20">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">{{ breeder.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ breeder.address }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <p>{{ breeder.postal_code }} {{ breeder.city }}</p>
                                    <p>{{ breeder.phone ?? breeder.email ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ breeder.herd_number ?? '-' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ breeder.analyses_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <Link
                                            :href="breederEdit({ breeder: breeder.id }).url"
                                            class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                            title="Modifier"
                                        >
                                            <Edit class="size-4" />
                                        </Link>
                                        <button
                                            class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            title="Supprimer"
                                            @click="deleteBreeder(breeder)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="breeders.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-muted-foreground">Aucun eleveur trouve.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
