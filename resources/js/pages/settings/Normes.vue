<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import type { RationNormeDefinition, RationNormesPayload } from '@/components/rations/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { formatNumber, formatNumberInput } from '@/lib/utils';
import { edit as editNormes, update as updateNormes } from '@/routes/normes';
import type { BreadcrumbItem } from '@/types';

interface NormeGroup {
    label: string;
    items: RationNormeDefinition[];
}

type NormeDraft = Record<string, { min: string; max: string }>;

const props = defineProps<{
    normes: RationNormesPayload;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Normes',
        href: editNormes(),
    },
];

const savingNormes = ref(false);
const recentlySaved = ref(false);
const normesDraft = ref<NormeDraft>({});
const normesErrors = ref<Record<string, string>>({});
let recentlySavedTimer: number | null = null;

const editableNormGroups = computed<NormeGroup[]>(() => {
    const groups = new Map<string, RationNormeDefinition[]>();

    for (const definition of props.normes.editable) {
        const items = groups.get(definition.group) ?? [];
        items.push(definition);
        groups.set(definition.group, items);
    }

    return Array.from(groups.entries()).map(([label, items]) => ({ label, items }));
});

function fmtNorm(val: number | null | undefined, decimals = 1): string {
    return formatNumber(val, decimals).replace('.', ',');
}

function buildNormesDraft(useDefaults: boolean = false): NormeDraft {
    return Object.fromEntries(
        props.normes.editable.map((definition) => {
            const source = useDefaults
                ? { min: definition.default_min, max: definition.default_max }
                : props.normes.active[definition.key] ?? { min: definition.default_min, max: definition.default_max };

            return [
                definition.key,
                {
                    min: definition.default_min === null ? '' : formatNumberInput(source.min, definition.decimals),
                    max: definition.default_max === null ? '' : formatNumberInput(source.max, definition.decimals),
                },
            ];
        }),
    );
}

function syncNormesDraft(): void {
    normesDraft.value = buildNormesDraft();
}

function resetNormesDraftToDefaults(): void {
    normesDraft.value = buildNormesDraft(true);
    normesErrors.value = {};
    recentlySaved.value = false;
}

function parseNormInput(value: string): number | null {
    const normalized = value.replace(',', '.').trim();

    if (! normalized) {
        return null;
    }

    const parsed = Number(normalized);

    return Number.isNaN(parsed) ? null : parsed;
}

function normePayload(): Record<string, { min: number | null; max: number | null }> {
    return Object.fromEntries(
        props.normes.editable.map((definition) => {
            const draft = normesDraft.value[definition.key] ?? { min: '', max: '' };

            return [
                definition.key,
                {
                    min: definition.default_min === null ? null : parseNormInput(draft.min),
                    max: definition.default_max === null ? null : parseNormInput(draft.max),
                },
            ];
        }),
    );
}

function normeError(key: string, bound: 'min' | 'max'): string | undefined {
    return normesErrors.value[`normes.${key}.${bound}`];
}

function clearRecentlySavedTimer(): void {
    if (recentlySavedTimer !== null) {
        window.clearTimeout(recentlySavedTimer);
        recentlySavedTimer = null;
    }
}

function flashSavedState(): void {
    clearRecentlySavedTimer();
    recentlySaved.value = true;
    recentlySavedTimer = window.setTimeout(() => {
        recentlySaved.value = false;
        recentlySavedTimer = null;
    }, 2000);
}

function saveNormes(): void {
    savingNormes.value = true;
    recentlySaved.value = false;
    normesErrors.value = {};

    router.patch(updateNormes().url, {
        normes: normePayload(),
    }, {
        preserveScroll: true,
        onError: (errors) => {
            normesErrors.value = Object.fromEntries(
                Object.entries(errors).filter(([key]) => key.startsWith('normes.')),
            );
        },
        onSuccess: () => {
            flashSavedState();
        },
        onFinish: () => {
            savingNormes.value = false;
        },
    });
}

syncNormesDraft();

watch(() => props.normes, () => {
    if (! savingNormes.value) {
        syncNormesDraft();
    }
}, { deep: true, immediate: true });

onBeforeUnmount(() => {
    clearRecentlySavedTimer();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Normes" />

        <h1 class="sr-only">Normes</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Normes de lecture"
                    description="Ajustez les seuils techniques utilisés dans vos analyses de ration et dans les PDF."
                />

                <div class="rounded-2xl border border-border bg-muted/30 p-4 text-sm text-muted-foreground">
                    Ces réglages s’appliquent à l’ensemble de votre compte. Ils sont repris dans les résultats et dans les exports PDF.
                </div>

                <form class="space-y-6" @submit.prevent="saveNormes">
                    <section
                        v-for="group in editableNormGroups"
                        :key="group.label"
                        class="space-y-3 rounded-[1.75rem] border border-border bg-card p-5 shadow-sm"
                    >
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-muted-foreground">{{ group.label }}</h2>
                        </div>

                        <div class="grid gap-3">
                            <div
                                v-for="definition in group.items"
                                :key="definition.key"
                                class="rounded-2xl border border-border bg-background/80 p-4"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="space-y-1">
                                        <p class="font-medium text-foreground">{{ definition.label }}</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ definition.unit ?? 'Lecture sans unité' }}
                                        </p>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2 lg:w-[24rem]">
                                        <div v-if="definition.default_min !== null" class="grid gap-2">
                                            <Label :for="`${definition.key}-min`">Borne basse</Label>
                                            <Input
                                                :id="`${definition.key}-min`"
                                                v-model="normesDraft[definition.key].min"
                                                type="text"
                                                inputmode="decimal"
                                                :placeholder="fmtNorm(definition.default_min, definition.decimals)"
                                            />
                                            <p class="text-xs text-muted-foreground">
                                                Défaut : {{ fmtNorm(definition.default_min, definition.decimals) }}{{ definition.unit ? ` ${definition.unit}` : '' }}
                                            </p>
                                            <InputError :message="normeError(definition.key, 'min')" />
                                        </div>

                                        <div v-if="definition.default_max !== null" class="grid gap-2">
                                            <Label :for="`${definition.key}-max`">Borne haute</Label>
                                            <Input
                                                :id="`${definition.key}-max`"
                                                v-model="normesDraft[definition.key].max"
                                                type="text"
                                                inputmode="decimal"
                                                :placeholder="fmtNorm(definition.default_max, definition.decimals)"
                                            />
                                            <p class="text-xs text-muted-foreground">
                                                Défaut : {{ fmtNorm(definition.default_max, definition.decimals) }}{{ definition.unit ? ` ${definition.unit}` : '' }}
                                            </p>
                                            <InputError :message="normeError(definition.key, 'max')" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="savingNormes"
                            @click="resetNormesDraftToDefaults"
                        >
                            Réinitialiser
                        </Button>

                        <div class="flex items-center gap-4 self-end sm:self-auto">
                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p
                                    v-show="recentlySaved"
                                    class="text-sm text-neutral-600"
                                >
                                    Enregistré.
                                </p>
                            </Transition>

                            <Button type="submit" :disabled="savingNormes">
                                {{ savingNormes ? 'Enregistrement…' : 'Enregistrer' }}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
