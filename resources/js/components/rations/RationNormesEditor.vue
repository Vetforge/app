<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import type { RationNormesPayload } from '@/components/rations/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatNumberInput } from '@/lib/utils';
import { edit, update } from '@/routes/ration-settings';

const props = defineProps<{ normes: RationNormesPayload }>();
const processing = ref(false);
const saved = ref(false);
const query = ref('');
const errors = ref<Record<string, string>>({});
const selectedCategory = ref(props.normes.categorie_animal ?? 'vache_laitiere');
type Draft = Record<string, { min: string; max: string }>;
const drafts = ref<Record<string, Draft>>({});
const draft = computed(() => drafts.value[selectedCategory.value]);
let savedTimer: ReturnType<typeof setTimeout> | undefined;

const groups = computed(() => {
    const grouped = new Map<string, RationNormesPayload['editable']>();
    const search = query.value.trim().toLocaleLowerCase('fr');
    for (const definition of props.normes.editable) {
        if (
            search &&
            !`${definition.label} ${definition.group} ${definition.unit ?? ''}`
                .toLocaleLowerCase('fr')
                .includes(search)
        )
            continue;
        const items = grouped.get(definition.group) ?? [];
        items.push(definition);
        grouped.set(definition.group, items);
    }
    return Array.from(grouped, ([label, items]) => ({ label, items }));
});
const hasErrors = computed(() => Object.keys(errors.value).length > 0);

function sync(useDefaults = false): void {
    const active =
        props.normes.profiles?.[selectedCategory.value] ?? props.normes.active;
    drafts.value[selectedCategory.value] = Object.fromEntries(
        props.normes.editable.map((definition) => {
            const value = useDefaults
                ? { min: definition.default_min, max: definition.default_max }
                : active[definition.key];
            return [
                definition.key,
                {
                    min: formatNumberInput(value?.min, definition.decimals),
                    max: formatNumberInput(value?.max, definition.decimals),
                },
            ];
        }),
    );
}

function selectCategory(): void {
    if (!draft.value) sync();
    errors.value = {};
    saved.value = false;
    clearTimeout(savedTimer);
    router.replace({
        url: edit({ query: { categorie_animal: selectedCategory.value } }).url,
        props: (pageProps) => ({
            ...pageProps,
            normes: {
                ...props.normes,
                categorie_animal: selectedCategory.value,
            },
        }),
        preserveState: true,
        preserveScroll: true,
    });
}

function reset(): void {
    sync(true);
    query.value = '';
    errors.value = {};
    saved.value = false;
}

function parse(value: string): number | string | null {
    const normalized = value.trim().replace(',', '.');
    if (!normalized) return null;
    const number = Number(normalized);
    return Number.isFinite(number) ? number : normalized;
}

function save(): void {
    processing.value = true;
    saved.value = false;
    errors.value = {};
    clearTimeout(savedTimer);
    router.patch(
        update().url,
        {
            categorie_animal: selectedCategory.value,
            normes: Object.fromEntries(
                Object.entries(draft.value).map(([key, bounds]) => [
                    key,
                    {
                        min: parse(bounds.min),
                        max: parse(bounds.max),
                    },
                ]),
            ),
        },
        {
            preserveScroll: true,
            onError: (validationErrors) => {
                errors.value = validationErrors;
                query.value = '';
            },
            onSuccess: () => {
                sync();
                saved.value = true;
                savedTimer = setTimeout(() => {
                    saved.value = false;
                }, 3000);
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

watch(
    () => props.normes,
    () => {
        if (!processing.value) {
            selectedCategory.value =
                props.normes.categorie_animal ?? selectedCategory.value;
            if (!draft.value) sync();
        }
    },
    { immediate: true },
);
onBeforeUnmount(() => clearTimeout(savedTimer));
</script>

<template>
    <form class="space-y-6" @submit.prevent="save">
        <div class="space-y-2 text-sm text-muted-foreground">
            <p>
                Personnalisez les seuils appliqués à vos rations et aux PDF.
                Laissez une borne vide pour la désactiver. Les limites sont
                incluses.
            </p>
        </div>

        <div class="grid max-w-md gap-2">
            <Label for="ration-norm-category"
                >Espèce et catégorie animale</Label
            >
            <select
                id="ration-norm-category"
                v-model="selectedCategory"
                class="flex h-10 w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="processing"
                aria-describedby="ration-norm-category-help"
                @change="selectCategory"
            >
                <optgroup
                    v-for="species in normes.categories"
                    :key="species.espece"
                    :label="species.label"
                >
                    <option
                        v-for="category in species.categories"
                        :key="category.value"
                        :value="category.value"
                    >
                        {{ category.label }}
                    </option>
                </optgroup>
            </select>
            <InputError :message="errors.categorie_animal" />
        </div>

        <div class="grid max-w-md gap-2">
            <Label for="ration-norm-search">Rechercher une norme</Label>
            <Input
                id="ration-norm-search"
                v-model="query"
                type="search"
                placeholder="pH, énergie, calcium…"
            />
        </div>

        <p v-if="hasErrors" role="alert" class="text-sm text-destructive">
            Les normes n’ont pas été enregistrées. Corrigez les bornes signalées
            ci-dessous.
        </p>
        <InputError :message="errors.normes" />
        <p
            v-if="groups.length === 0"
            role="status"
            class="py-6 text-sm text-muted-foreground"
        >
            Aucune norme ne correspond à votre recherche.
        </p>

        <fieldset
            v-for="group in groups"
            :key="group.label"
            class="min-w-0 space-y-3"
            :disabled="processing"
        >
            <legend class="mb-3 text-base font-semibold">
                {{ group.label }}
            </legend>
            <p
                v-if="group.label === 'Couverture des besoins'"
                class="max-w-3xl text-sm text-muted-foreground"
            >
                La plage cible détermine les écarts à surveiller et les limites
                d’alerte signalent les écarts prioritaires. Les pourcentages
                sont calculés par rapport aux besoins de l’animal ou à son
                objectif laitier.
            </p>
            <p
                v-if="
                    [
                        'Minéraux et oligoéléments',
                        'Bilans apports / besoins',
                    ].includes(group.label)
                "
                class="max-w-3xl text-sm text-muted-foreground"
            >
                Une couverture inférieure à la borne basse est prioritaire ;
                au-dessus de la borne haute, elle est à surveiller.
            </p>
            <p
                v-if="group.label === 'Barème de calcul de l’IRA'"
                class="max-w-3xl text-sm text-muted-foreground"
            >
                Ces paliers modifient le calcul de l’indice de risque d’acidose.
                Pour l’amidon et les concentrés, le risque augmente au-dessus
                des paliers ; pour le bilan électrolytique et les NDF, il
                augmente en dessous. Chaque palier franchi ajoute un point au
                score, divisé par cinq.
            </p>
            <div class="divide-y divide-border rounded-xl border border-border">
                <div
                    v-for="definition in group.items"
                    :key="definition.key"
                    class="grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] md:items-start md:gap-6"
                >
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-medium">
                            {{ definition.label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ definition.unit ?? 'Sans unité' }}
                        </p>
                        <InputError
                            :message="errors[`normes.${definition.key}`]"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="bound in ['min', 'max'] as const"
                            :key="bound"
                            class="grid gap-2"
                        >
                            <Label :for="`${definition.key}-${bound}`">{{
                                bound === 'min' ? 'Borne basse' : 'Borne haute'
                            }}</Label>
                            <Input
                                :id="`${definition.key}-${bound}`"
                                v-model="draft[definition.key][bound]"
                                type="text"
                                inputmode="decimal"
                                placeholder="Sans borne"
                                :aria-label="`${definition.label} — ${bound === 'min' ? 'borne basse' : 'borne haute'}`"
                                :aria-invalid="
                                    Boolean(
                                        errors[
                                            `normes.${definition.key}.${bound}`
                                        ],
                                    )
                                "
                                :aria-describedby="`${definition.key}-${bound}-error`"
                                @update:model-value="saved = false"
                            />
                            <InputError
                                :id="`${definition.key}-${bound}-error`"
                                :message="
                                    errors[`normes.${definition.key}.${bound}`]
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>

        <div
            class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t border-border bg-background py-4"
        >
            <Button
                type="button"
                variant="outline"
                :disabled="processing"
                @click="reset"
                >Réinitialiser</Button
            >
            <div class="flex items-center gap-3">
                <p
                    v-if="saved"
                    role="status"
                    class="text-sm text-muted-foreground"
                >
                    Enregistré.
                </p>
                <Button type="submit" :disabled="processing">{{
                    processing ? 'Enregistrement…' : 'Enregistrer'
                }}</Button>
            </div>
        </div>
    </form>
</template>
