<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Copy, FileText, Trash2, Edit, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import {
    index as alimentsIndex,
    create as alimentCreate,
    edit as alimentEdit,
    copy as alimentCopy,
    destroy as alimentDestroy,
    pdf as alimentPdf,
} from '@/actions/App/Http/Controllers/AlimentController';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Aliment {
    id: number;
    libelle0: string;
    libelle1: string | null;
    type: string | null;
    code_inra: string | null;
    ufl: number | null;
    ufv: number | null;
    prix: number | null;
    user_id: number | null;
}

interface PaginatedAliments {
    data: Aliment[];
    current_page: number;
    last_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    aliments: PaginatedAliments;
    types: string[];
    filters: { search?: string; type?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Aliments', href: alimentsIndex() },
];

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            alimentsIndex().url,
            { search: val, type: type.value },
            { preserveState: true, replace: true },
        );
    }, 350);
});

watch(type, (val) => {
    router.get(
        alimentsIndex().url,
        { search: search.value, type: val },
        { preserveState: true, replace: true },
    );
});

function deleteAliment(aliment: Aliment) {
    if (confirm(`Supprimer l'aliment "${aliment.libelle0}" ?`)) {
        router.delete(alimentDestroy({ aliment: aliment.id }).url);
    }
}
</script>

<template>
    <Head title="Aliments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <h1 class="text-2xl font-bold text-foreground">Aliments</h1>
                <Link
                    :href="alimentCreate().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                >
                    <Plus class="size-4" />
                    Nouvel aliment
                </Link>
            </div>

            <!-- Filtres -->
            <div class="flex flex-wrap gap-3">
                <div class="relative min-w-52 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Rechercher un aliment…"
                        class="h-10 w-full rounded-lg border border-border bg-background py-2 pr-3 pl-10 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                </div>
                <select
                    v-model="type"
                    class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                >
                    <option value="">Tous les types</option>
                    <option v-for="t in types" :key="t" :value="t">
                        {{ t }}
                    </option>
                </select>
            </div>

            <!-- Tableau -->
            <div
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="hidden md:table-header-group">
                            <tr class="border-b border-border bg-muted/50">
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Libellé
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Type
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-muted-foreground"
                                >
                                    UFL
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-muted-foreground"
                                >
                                    UFV
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-medium text-muted-foreground"
                                >
                                    Prix (€/unité MB)
                                </th>
                                <th
                                    class="px-4 py-3 text-center font-medium text-muted-foreground"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/50">
                            <tr
                                v-for="aliment in aliments.data"
                                :key="aliment.id"
                                class="hover:bg-accent/20"
                            >
                                <td class="px-4 py-3">
                                    <div class="min-w-0">
                                        <div
                                            class="flex min-w-0 items-center gap-2"
                                        >
                                            <span
                                                class="truncate font-medium text-foreground"
                                                :title="aliment.libelle0"
                                                >{{ aliment.libelle0 }}</span
                                            >
                                            <span
                                                v-if="aliment.code_inra"
                                                class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300"
                                                title="Aliment INRA (protégé)"
                                            >
                                                INRA
                                            </span>
                                        </div>
                                        <p
                                            v-if="aliment.libelle1"
                                            class="hidden text-xs text-muted-foreground md:block"
                                        >
                                            {{ aliment.libelle1 }}
                                        </p>
                                    </div>

                                    <div
                                        class="mt-2 flex items-center justify-between gap-2 md:hidden"
                                    >
                                        <div
                                            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                        >
                                            <span>{{
                                                aliment.type ?? '–'
                                            }}</span>
                                            <span
                                                >UFL
                                                {{
                                                    formatNumber(aliment.ufl)
                                                }}</span
                                            >
                                            <span
                                                >UFV
                                                {{
                                                    formatNumber(aliment.ufv)
                                                }}</span
                                            >
                                            <span
                                                >{{
                                                    formatNumber(aliment.prix)
                                                }}
                                                €/unité MB</span
                                            >
                                        </div>
                                        <div
                                            class="flex shrink-0 items-center gap-1"
                                        >
                                            <Link
                                                :href="
                                                    alimentEdit({
                                                        aliment: aliment.id,
                                                    }).url
                                                "
                                                class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                title="Modifier"
                                            >
                                                <Edit class="size-4" />
                                            </Link>
                                            <a
                                                :href="
                                                    alimentPdf({
                                                        aliment: aliment.id,
                                                    }).url
                                                "
                                                class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                title="Fiche PDF"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileText class="size-4" />
                                            </a>
                                            <Link
                                                :href="
                                                    alimentCopy({
                                                        aliment: aliment.id,
                                                    }).url
                                                "
                                                method="post"
                                                as="button"
                                                class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                title="Copier"
                                            >
                                                <Copy class="size-4" />
                                            </Link>
                                            <button
                                                v-if="!aliment.code_inra"
                                                @click="deleteAliment(aliment)"
                                                class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                title="Supprimer"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="hidden px-4 py-3 text-muted-foreground md:table-cell"
                                >
                                    {{ aliment.type ?? '–' }}
                                </td>
                                <td
                                    class="hidden px-4 py-3 text-right text-foreground tabular-nums md:table-cell"
                                >
                                    {{ formatNumber(aliment.ufl) }}
                                </td>
                                <td
                                    class="hidden px-4 py-3 text-right text-foreground tabular-nums md:table-cell"
                                >
                                    {{ formatNumber(aliment.ufv) }}
                                </td>
                                <td
                                    class="hidden px-4 py-3 text-right text-foreground tabular-nums md:table-cell"
                                >
                                    {{ formatNumber(aliment.prix) }}
                                </td>
                                <td class="hidden px-4 py-3 md:table-cell">
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <Link
                                            :href="
                                                alimentEdit({
                                                    aliment: aliment.id,
                                                }).url
                                            "
                                            class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                            title="Modifier"
                                        >
                                            <Edit class="size-4" />
                                        </Link>
                                        <a
                                            :href="
                                                alimentPdf({
                                                    aliment: aliment.id,
                                                }).url
                                            "
                                            class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                            title="Fiche PDF"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <FileText class="size-4" />
                                        </a>
                                        <Link
                                            :href="
                                                alimentCopy({
                                                    aliment: aliment.id,
                                                }).url
                                            "
                                            method="post"
                                            as="button"
                                            class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                            title="Copier"
                                        >
                                            <Copy class="size-4" />
                                        </Link>
                                        <button
                                            v-if="!aliment.code_inra"
                                            @click="deleteAliment(aliment)"
                                            class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            title="Supprimer"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="aliments.last_page > 1"
                    class="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ aliments.total }} aliment{{
                            aliments.total !== 1 ? 's' : ''
                        }}
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in aliments.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="rounded px-3 py-1 text-sm"
                                :class="
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border border-border hover:bg-accent'
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                            <span
                                v-else
                                class="px-3 py-1 text-sm text-muted-foreground"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
