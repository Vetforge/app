<script setup lang="ts">
import { nextTick, onMounted } from 'vue';
import RationCompositionEditor from '@/components/rations/RationCompositionEditor.vue';
import RationResultsPanel from '@/components/rations/RationResultsPanel.vue';
import type { Aliment, Plan, Ration, RationNormesPayload, Resultats } from '@/components/rations/types';

const props = defineProps<{
    plan: Plan;
    ration: Ration;
    aliments_disponibles: Aliment[];
    resultats: Resultats;
    iterations_volonte: number;
    normes: RationNormesPayload;
    activeView: 'composition' | 'resultats';
}>();

onMounted(async () => {
    if (props.activeView !== 'resultats') {
        return;
    }

    await nextTick();

    document.getElementById('resultats')?.scrollIntoView({
        block: 'start',
        behavior: 'smooth',
    });
});
</script>

<template>
    <div class="mx-auto w-full max-w-7xl p-4 sm:p-6">
        <section
            class="scroll-mt-24 relative overflow-hidden rounded-[2rem] border border-sky-200/70 bg-gradient-to-br from-sky-50 via-background to-amber-50/70 p-5 shadow-sm dark:border-sky-950/60 dark:from-sky-950/25 dark:via-background dark:to-amber-950/10 sm:p-7"
        >
            <div class="flex flex-col gap-5">
                <div>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">Composition et résultats</h1>
                </div>

            </div>

            <div class="my-5 flex flex-wrap gap-2">
                <span class="rounded-full border border-border bg-background px-3 py-1 text-sm text-foreground">{{ ration.nom }}</span>
                <span class="rounded-full border border-border bg-background px-3 py-1 text-sm text-foreground">{{ plan.nom }}</span>
                <span class="rounded-full border border-border bg-background px-3 py-1 text-sm text-foreground">INRA {{ resultats.inra }}</span>
                <span v-if="ration.lait_objectif" class="rounded-full border border-border bg-background px-3 py-1 text-sm text-foreground">
                    Objectif {{ ration.lait_objectif }} kg/j
                </span>
                <span v-if="iterations_volonte > 0" class="rounded-full border border-border bg-background px-3 py-1 text-sm text-foreground">
                    À volonté · {{ iterations_volonte }} itérations
                </span>
            </div>
            <RationCompositionEditor
                :plan="plan"
                :ration="ration"
                :aliments_disponibles="aliments_disponibles"
            />
        </section>

        <div class="mt-6 space-y-6">

            <RationResultsPanel
                :plan="plan"
                :ration="ration"
                :resultats="resultats"
                :iterations_volonte="iterations_volonte"
                :normes="normes"
            />
        </div>
    </div>
</template>
