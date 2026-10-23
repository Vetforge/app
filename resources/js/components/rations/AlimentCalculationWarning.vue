<script setup lang="ts">
import UPopover from '@nuxt/ui/components/Popover.vue';
import { TriangleAlert } from 'lucide-vue-next';

defineProps<{
    warnings?: string[];
    label: string;
}>();
</script>

<template>
    <span v-if="warnings?.length" class="inline-flex shrink-0">
        <UPopover :content="{ align: 'start' }">
            <button
                type="button"
                :aria-label="`Avertissements de calcul pour ${label}`"
                title="Données nutritionnelles incomplètes"
                class="rounded p-1 text-amber-700 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:text-amber-400 dark:hover:bg-amber-950"
            >
                <TriangleAlert class="size-4" aria-hidden="true" />
            </button>
            <template #content>
                <div
                    class="max-h-80 w-80 max-w-[calc(100vw-2rem)] overflow-y-auto p-4 text-sm"
                >
                    <p class="font-semibold text-foreground">
                        Données nutritionnelles incomplètes
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Le calcul reste possible, mais certains résultats sont
                        approximatifs.
                    </p>
                    <ul class="mt-3 list-disc space-y-2 pl-4 text-foreground">
                        <li v-for="warning in warnings" :key="warning">
                            {{ warning }}
                        </li>
                    </ul>
                </div>
            </template>
        </UPopover>
    </span>
</template>
