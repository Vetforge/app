<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { SquareActivity, Worm, ClipboardList, ClipboardCheck, Droplets, Biohazard, FlaskConical, LayoutGrid, Columns4, Settings, Salad, Users, TestTube2, Scissors, NotebookPen, Zap, Leaf, ChartScatter, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import { index as adminUsersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import { show as agrinirShow } from '@/actions/App/Http/Controllers/AgrinirController';
import { index as alimentsIndex } from '@/actions/App/Http/Controllers/AlimentController';
import { index as breedersIndex } from '@/actions/App/Http/Controllers/BreederController';
import { index as plansIndex } from '@/actions/App/Http/Controllers/PlanRationnementController';
import { edit as moduleSettingsEdit } from '@/actions/App/Http/Controllers/Settings/ModuleSettingsController';
import { index as analysesIndex } from '@/actions/App/Http/Controllers/VeterinaryAnalysisController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems: NavItem[] = [
    { title: 'Accueil', href: dashboard(), icon: LayoutGrid },
];

const rationNavItems: NavItem[] = [
    { title: 'Plans de rationnement', href: plansIndex().url, icon: ClipboardList },
    { title: 'Aliments', href: alimentsIndex().url, icon: Salad },
    { title: 'AgriNIR', href: agrinirShow().url, icon: Leaf },
];

const analysisNavItems: NavItem[] = [
    { title: 'Coproscopie', href: analysesIndex({ module: 'coproscopie-parasitaire' }).url, icon: Worm },
    { title: 'Diarrhée néonatale', href: analysesIndex({ module: 'diarrhee-neonatale' }).url, icon: Columns4 },
    { title: 'Gaz du sang', href: analysesIndex({ module: 'gaz-du-sang' }).url, icon: SquareActivity },
    { title: 'Comptage cellulaire', href: analysesIndex({ module: 'comptage-cellulaire' }).url, icon: Droplets },
    { title: 'Bacteriologie', href: analysesIndex({ module: 'diagnostic-bacteriologique' }).url, icon: Biohazard },
    { title: 'Analyses diverses', href: analysesIndex({ module: 'analyse-diverse' }).url, icon: TestTube2 },
    { title: 'Tests rapides', href: analysesIndex({ module: 'tests-rapides' }).url, icon: Zap },
    { title: 'Biochimie', href: analysesIndex({ module: 'tests-biochimie' }).url, icon: FlaskConical },
    { title: 'Hemogramme', href: analysesIndex({ module: 'hemogramme' }).url, icon: ChartScatter },
];

const rapportNavItems: NavItem[] = [
    { title: 'BSE Laitier', href: analysesIndex({ module: 'bse-laitier' }).url, icon: ClipboardCheck },
    { title: 'BSE Allaitant', href: analysesIndex({ module: 'bse-allaitant' }).url, icon: ClipboardCheck },
    { title: 'Autopsie', href: analysesIndex({ module: 'autopsie' }).url, icon: Scissors },
    { title: 'Compte-rendu', href: analysesIndex({ module: 'compte-rendu' }).url, icon: NotebookPen },
];

const accountNavItems: NavItem[] = [
    { title: 'Eleveurs', href: breedersIndex().url, icon: Users },
    { title: 'Reglages analyses', href: moduleSettingsEdit({ module: 'coproscopie-parasitaire' }).url, icon: Settings },
];

const adminNavItems = computed<NavItem[]>(() => (
    page.props.auth.user.is_admin
        ? [{ title: 'Utilisateurs', href: adminUsersIndex().url, icon: ShieldCheck }]
        : []
));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain title="Ration" :items="rationNavItems" />
            <NavMain title="Analyses" :items="analysisNavItems" />
            <NavMain title="Rapports" :items="rapportNavItems" />
            <NavMain title="Compte" :items="accountNavItems" />
            <NavMain v-if="adminNavItems.length > 0" title="Admin" :items="adminNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
