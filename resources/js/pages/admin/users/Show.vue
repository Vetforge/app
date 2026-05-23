<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Activity, AtSign, CalendarClock, Database, ShieldCheck, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
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
    updated_at: string;
    breeders_count: number;
    analyses_count: number;
    aliments_count: number;
    plan_rationnements_count: number;
    rations_count: number;
    melanges_count: number;
    module_settings_count: number;
}

interface AnalysisModuleCount {
    module: string;
    label: string;
    count: number;
}

const props = defineProps<{
    user: AdminUser;
    analysisModules: AnalysisModuleCount[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Utilisateurs', href: adminUsersIndex() },
    { title: props.user.name, href: `/admin/users/${props.user.id}` },
];

const usageCounts = computed(() => [
    { label: 'Eleveurs', value: props.user.breeders_count },
    { label: 'Analyses', value: props.user.analyses_count },
    { label: 'Aliments', value: props.user.aliments_count },
    { label: 'Plans', value: props.user.plan_rationnements_count },
    { label: 'Rations', value: props.user.rations_count },
    { label: 'Melanges', value: props.user.melanges_count },
    { label: 'Reglages', value: props.user.module_settings_count },
]);

function formatDate(value: string | null): string {
    if (!value) {
        return 'Jamais';
    }

    return new Date(value).toLocaleString('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head :title="user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-foreground">{{ user.name }}</h1>
                        <span
                            v-if="user.is_admin"
                            class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                        >
                            <ShieldCheck class="size-3.5" />
                            Administrateur
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">Compte #{{ user.id }}</p>
                </div>

                <Link
                    :href="adminUsersIndex().url"
                    class="inline-flex items-center justify-center rounded-md border border-border px-3 py-1.5 text-sm font-medium hover:bg-accent"
                >
                    Retour aux utilisateurs
                </Link>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(18rem,0.8fr)_1.2fr]">
                <section class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <UserRound class="size-5 text-muted-foreground" />
                        <h2 class="text-base font-semibold text-foreground">Coordonnees</h2>
                    </div>

                    <dl class="grid gap-3 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-muted-foreground">Nom</dt>
                            <dd class="mt-0.5 text-foreground">{{ user.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-muted-foreground">Email</dt>
                            <dd class="mt-0.5 flex items-center gap-2 text-foreground">
                                <AtSign class="size-4 text-muted-foreground" />
                                <a :href="`mailto:${user.email}`" class="hover:underline">{{ user.email }}</a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-muted-foreground">Email verifie</dt>
                            <dd class="mt-0.5 text-foreground">{{ formatDate(user.email_verified_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-muted-foreground">Creation</dt>
                            <dd class="mt-0.5 text-foreground">{{ formatDate(user.created_at) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <CalendarClock class="size-5 text-muted-foreground" />
                        <h2 class="text-base font-semibold text-foreground">Connexion</h2>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-border bg-background px-4 py-3">
                            <p class="text-xs font-medium text-muted-foreground">Derniere connexion</p>
                            <p class="mt-1 text-lg font-semibold text-foreground">{{ formatDate(user.last_login_at) }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-background px-4 py-3">
                            <p class="text-xs font-medium text-muted-foreground">Derniere mise a jour</p>
                            <p class="mt-1 text-lg font-semibold text-foreground">{{ formatDate(user.updated_at) }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <section>
                <div class="mb-3 flex items-center gap-2">
                    <Database class="size-5 text-muted-foreground" />
                    <h2 class="text-base font-semibold text-foreground">Elements du compte</h2>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    <div
                        v-for="item in usageCounts"
                        :key="item.label"
                        class="rounded-lg border border-border bg-card px-4 py-3 shadow-sm"
                    >
                        <p class="text-xs font-medium text-muted-foreground">{{ item.label }}</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">{{ item.value }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Activity class="size-5 text-muted-foreground" />
                    <h2 class="text-base font-semibold text-foreground">Analyses par module</h2>
                </div>

                <div v-if="analysisModules.length > 0" class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="module in analysisModules"
                        :key="module.module"
                        class="flex items-center justify-between rounded-lg border border-border bg-background px-3 py-2"
                    >
                        <span class="text-sm text-foreground">{{ module.label }}</span>
                        <span class="text-sm font-semibold tabular-nums text-foreground">{{ module.count }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">Aucune analyse pour ce compte.</p>
            </section>
        </div>
    </AppLayout>
</template>
