<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editModuleSettings } from '@/routes/module-settings';
import { edit as editNormes } from '@/routes/normes';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profil',
        href: editProfile(),
    },
    {
        title: 'Mot de passe',
        href: editPassword(),
    },
    {
        title: 'Authentification à deux facteurs',
        href: show(),
    },
    {
        title: 'Normes',
        href: editNormes(),
    },
    {
        title: 'Modules analyses',
        href: editModuleSettings({ module: 'coproscopie-parasitaire' }),
    },
    {
        title: 'Apparence',
        href: editAppearance(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Paramètres"
            description="Gérez votre profil et les paramètres de votre compte"
        />

        <nav class="mb-6 flex flex-wrap gap-1 border-b border-border" aria-label="Paramètres">
            <Link
                v-for="item in sidebarNavItems"
                :key="toUrl(item.href)"
                :href="item.href"
                class="relative px-4 py-2.5 text-sm font-medium transition-colors"
                :class="
                    isCurrentOrParentUrl(item.href)
                        ? 'text-foreground after:absolute after:inset-x-0 after:-bottom-px after:h-0.5 after:bg-primary'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                {{ item.title }}
            </Link>
        </nav>

        <section class="w-full space-y-12">
            <slot />
        </section>
    </div>
</template>
