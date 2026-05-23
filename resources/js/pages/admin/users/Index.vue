<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, ShieldCheck, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { index as adminUsersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified_at: string | null;
    last_login_at: string | null;
    created_at: string;
    breeders_count: number;
    analyses_count: number;
    aliments_count: number;
    plan_rationnements_count: number;
    rations_count: number;
    melanges_count: number;
}

interface PaginatedUsers {
    data: AdminUser[];
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    users: PaginatedUsers;
    filters: { search?: string };
    totals: { users: number; admins: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Utilisateurs', href: adminUsersIndex() },
];

const search = ref(props.filters.search ?? '');
let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            adminUsersIndex().url,
            { search: value },
            { preserveState: true, replace: true },
        );
    }, 350);
});

function userShowUrl(user: AdminUser): string {
    return `/admin/users/${user.id}`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Jamais';
    }

    return new Date(value).toLocaleString('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head title="Utilisateurs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Utilisateurs</h1>
                    <p class="text-sm text-muted-foreground">Pilotage administrateur des comptes et de leur activite.</p>
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="rounded-lg border border-border bg-card px-4 py-3">
                        <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <Users class="size-4" />
                            Comptes
                        </div>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ totals.users }}</p>
                    </div>
                    <div class="rounded-lg border border-border bg-card px-4 py-3">
                        <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <ShieldCheck class="size-4" />
                            Admins
                        </div>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ totals.admins }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-center">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Rechercher par nom ou email..."
                        class="h-10 w-full rounded-lg border border-border bg-background py-2 pr-3 pl-10 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ users.total }} utilisateur{{ users.total > 1 ? 's' : '' }}
                </p>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border bg-muted/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Utilisateur</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Derniere connexion</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Eleveurs</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Analyses</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Ration</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Autres</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/50">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-accent/20">
                                <td class="px-4 py-3">
                                    <Link :href="userShowUrl(user)" class="font-medium text-foreground hover:underline">
                                        {{ user.name }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">{{ user.email }}</p>
                                    <p v-if="user.is_admin" class="mt-1 text-xs font-medium text-primary">Administrateur</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ formatDate(user.last_login_at) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ user.breeders_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ user.analyses_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ user.plan_rationnements_count + user.rations_count + user.melanges_count }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ user.aliments_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="userShowUrl(user)"
                                        class="inline-flex items-center justify-center rounded-md border border-border px-3 py-1.5 text-xs font-medium hover:bg-accent"
                                    >
                                        Ouvrir
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                                    Aucun utilisateur trouve.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
