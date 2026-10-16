<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Plus,
    Trash2,
    SquareArrowOutUpRight,
    GripVertical,
    ChevronDown,
    ChevronUp,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import {
    store as storeMelange,
    destroy as destroyMelange,
    update as updateMelange,
    reorder as reorderMelanges,
    reorderAliments as reorderMelangeAliments,
    addAliment as addAlimentToMelange,
    removeAliment as removeAlimentFromMelange,
    updateAliment as updateMelangeAliment,
    updateAlimentValeurs as updateMelangeAlimentValeurs,
} from '@/actions/App/Http/Controllers/MelangeController';
import {
    addAliment,
    removeAliment,
    updateAliment,
    updateAlimentValeurs,
    reorderAliments,
} from '@/actions/App/Http/Controllers/RationController';
import {
    alimentEditableKeys,
    alimentEditableNumericKeys,
} from '@/components/rations/alimentEditableFields';
import ReorderButtons from '@/components/rations/ReorderButtons.vue';
import type {
    Aliment,
    Melange,
    MelangeAliment,
    Plan,
    Ration,
    RationAliment,
} from '@/components/rations/types';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DialogScrollContent } from '@/components/ui/dialog';
import { formatNumber, roundNumber } from '@/lib/utils';
import { formatValidationError } from '@/lib/validationMessages';

const AlimentValuesEditor = defineAsyncComponent(
    () => import('@/components/rations/AlimentValuesEditor.vue'),
);
const USelectMenu = defineAsyncComponent(
    () => import('@nuxt/ui/components/SelectMenu.vue'),
);

const props = defineProps<{
    plan: Plan;
    ration: Ration;
    aliments_disponibles: Aliment[];
}>();

const page = usePage();
const quantityErrorContext = ref('');
const saveErrors = computed(() =>
    Object.entries(page.props.errors).map(([field, message]) =>
        field === 'quantite' && message.startsWith('validation.')
            ? quantityErrorContext.value.startsWith('ma-')
                ? 'Saisissez une quantité supérieure ou égale à zéro.'
                : 'Saisissez une quantité supérieure à zéro ou activez « À volonté » pour un fourrage.'
            : formatValidationError(message),
    ),
);

function quantityError(context: string): string | undefined {
    return quantityErrorContext.value === context
        ? page.props.errors.quantite
        : undefined;
}

const reordering = ref(false);
const reorderStatus = ref('');
const reorderError = ref('');

function persistOrder(url: string, ids: number[]): void {
    if (reordering.value) return;
    reordering.value = true;
    reorderError.value = '';
    reorderStatus.value = '';
    router.patch(
        url,
        { ids },
        {
            preserveScroll: true,
            onSuccess: () => {
                reorderStatus.value = 'Ordre enregistré.';
            },
            onError: () => {
                reorderError.value =
                    'L’ordre n’a pas pu être enregistré. Réessayez.';
            },
            onFinish: () => {
                reordering.value = false;
            },
        },
    );
}

function moveItem(
    items: { id: number }[],
    index: number,
    direction: -1 | 1,
    url: string,
): void {
    const nextIndex = index + direction;
    if (nextIndex < 0 || nextIndex >= items.length) return;
    const ids = items.map((item) => item.id);
    [ids[index], ids[nextIndex]] = [ids[nextIndex], ids[index]];
    persistOrder(url, ids);
}

/**
 * Convertit la saisie d'un champ quantité en nombre. Un champ vide devient `null`,
 * mais 0 est une valeur valide qu'il faut préserver (ne pas traiter comme falsy).
 */
function parseQuantite(value: string): number | null {
    const parsed = parseFloat(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function normalizeAliment(aliment: Aliment): void {
    for (const key of alimentEditableNumericKeys) {
        aliment[key] =
            roundNumber(getNumericAlimentValue(aliment, key)) ?? null;
    }
}

function normalizeCompositionData(): void {
    for (const rationAliment of props.ration.ration_aliments) {
        rationAliment.quantite = roundNumber(rationAliment.quantite) ?? null;
        normalizeAliment(rationAliment.aliment);
    }

    for (const melange of props.ration.melanges) {
        melange.quantite = roundNumber(melange.quantite) ?? null;

        for (const melangeAliment of melange.melange_aliments) {
            melangeAliment.quantite =
                roundNumber(melangeAliment.quantite) ?? null;
            normalizeAliment(melangeAliment.aliment);
        }
    }

    for (const aliment of props.aliments_disponibles) {
        normalizeAliment(aliment);
    }
}

normalizeCompositionData();

// ─── Aliments disponibles (pour USelectMenu) ────────────────────────────────

const alimentSearchInputProps = {
    placeholder: 'Rechercher un aliment…',
    'aria-label': 'Rechercher un aliment',
};
const ALIMENT_SELECT_VISIBLE_ITEMS = 20;
const ALIMENT_SELECT_ITEM_HEIGHT = 36;
const alimentSelectMenuOptions = {
    content: {
        style: {
            maxHeight: `${ALIMENT_SELECT_VISIBLE_ITEMS * ALIMENT_SELECT_ITEM_HEIGHT}px`,
        },
    },
    virtualize: {
        estimateSize: ALIMENT_SELECT_ITEM_HEIGHT,
        overscan: 0,
    },
} as const;

type AlimentSelectItem = {
    label: string;
    value: number;
    code_inra: string | null;
    searchLabel: string;
};

function normalizeAlimentSearchValue(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/œ/g, 'oe')
        .replace(/æ/g, 'ae')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

function buildAlimentSearchLabel(aliment: Aliment): string {
    return normalizeAlimentSearchValue(
        [aliment.type, aliment.libelle0, aliment.libelle1]
            .filter((value): value is string => Boolean(value))
            .join(' '),
    );
}

function filterAlimentItems(
    items: AlimentSelectItem[],
    searchTerm: string | undefined,
): AlimentSelectItem[] {
    const normalizedSearchTerm = normalizeAlimentSearchValue(searchTerm ?? '');

    if (!normalizedSearchTerm) {
        return items;
    }

    const searchTokens = normalizedSearchTerm.split(' ').filter(Boolean);

    return items.filter((item) =>
        searchTokens.every((token) => item.searchLabel.includes(token)),
    );
}

const alimentItems = computed<AlimentSelectItem[]>(() =>
    props.aliments_disponibles.map((a) => ({
        label: `${a.type ? `(${a.type}) ` : ''}${a.libelle0}${a.libelle1 ? ` - ${a.libelle1}` : ''}`,
        value: a.id,
        code_inra: a.code_inra ?? null,
        searchLabel: buildAlimentSearchLabel(a),
    })),
);

const alimentSearchTerm = ref('');
const filteredAlimentItems = computed<AlimentSelectItem[]>(() =>
    filterAlimentItems(alimentItems.value, alimentSearchTerm.value),
);

// ─── Contrainte : un seul "à volonté" à la fois ─────────────────────────────

const composantVolonte = computed(() => {
    const ra = props.ration.ration_aliments.find((r) => r.is_volonte);
    if (ra) return { type: 'aliment', id: ra.id };
    const m = props.ration.melanges.find((mel) => mel.is_volonte);
    if (m) return { type: 'melange', id: m.id };
    return null;
});

// ─── Aliments ───────────────────────────────────────────────────────────────

const showAddAliment = ref(false);
const selectedAlimentId = ref<number | undefined>(undefined);
const melangeSearchTerms = ref<Record<number, string>>({});
const newQuantite = ref<number | null>(null);
const newIsVolonte = ref(false);
const newIsMb = ref(false);
const editingContext = ref<{
    type: 'aliment' | 'melange';
    ra?: RationAliment;
    melange?: Melange;
    ma?: MelangeAliment;
    aliment: Aliment;
    draft: Aliment;
} | null>(null);
const editingDialogOpen = ref(false);

function openEditAliment(ra: RationAliment) {
    editingContext.value = {
        type: 'aliment',
        ra,
        aliment: ra.aliment,
        draft: cloneAliment(ra.aliment),
    };
    editingDialogOpen.value = true;
}

function openEditMelangeAliment(melange: Melange, ma: MelangeAliment) {
    editingContext.value = {
        type: 'melange',
        melange,
        ma,
        aliment: ma.aliment,
        draft: cloneAliment(ma.aliment),
    };
    editingDialogOpen.value = true;
}

function saveEditingValeurs() {
    const ctx = editingContext.value;
    if (!ctx) return;
    if (ctx.type === 'aliment' && ctx.ra) {
        router.patch(
            updateAlimentValeurs({
                plan: props.plan.id,
                ration: props.ration.id,
                rationAliment: ctx.ra.id,
            }).url,
            alimentUpdatePayload(ctx.draft),
            {
                preserveScroll: true,
                onSuccess: closeEditingDialog,
            },
        );
    } else if (ctx.type === 'melange' && ctx.melange && ctx.ma) {
        router.patch(
            updateMelangeAlimentValeurs({
                plan: props.plan.id,
                ration: props.ration.id,
                melange: ctx.melange.id,
                melangeAliment: ctx.ma.id,
            }).url,
            alimentUpdatePayload(ctx.draft),
            { preserveScroll: true, onSuccess: closeEditingDialog },
        );
    }
}

watch(editingDialogOpen, (open) => {
    if (!open) {
        editingContext.value = null;
    }
});

function addAlimentToRation() {
    if (!selectedAlimentId.value) return;
    router.post(
        addAliment({ plan: props.plan.id, ration: props.ration.id }).url,
        {
            aliment_id: selectedAlimentId.value,
            quantite: newQuantite.value,
            is_volonte: newIsVolonte.value,
            is_mb: newIsMb.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAddAliment.value = false;
                selectedAlimentId.value = undefined;
                alimentSearchTerm.value = '';
                newQuantite.value = null;
                newIsVolonte.value = false;
                newIsMb.value = false;
            },
        },
    );
}

function removeAlimentFromRation(rationAliment: RationAliment) {
    router.delete(
        removeAliment({
            plan: props.plan.id,
            ration: props.ration.id,
            rationAliment: rationAliment.id,
        }).url,
        {
            preserveScroll: true,
        },
    );
}

function updateAlimentQty(
    rationAliment: RationAliment,
    quantite: string,
    isVolonte: boolean = rationAliment.is_volonte,
    isMb: boolean = rationAliment.is_mb,
) {
    quantityErrorContext.value = `ra-${rationAliment.id}`;
    router.put(
        updateAliment({
            plan: props.plan.id,
            ration: props.ration.id,
            rationAliment: rationAliment.id,
        }).url,
        {
            quantite: parseQuantite(quantite),
            is_volonte: isVolonte,
            is_mb: isMb,
        },
        { preserveScroll: true },
    );
}

// ─── Glisser-déposer — Aliments ─────────────────────────────────────────────

const dragAlimentId = ref<number | null>(null);
const dragOverAlimentId = ref<number | null>(null);

function onAlimentDragStart(id: number, event: DragEvent) {
    dragAlimentId.value = id;
    event.dataTransfer!.effectAllowed = 'move';
}

function onAlimentDragOver(id: number, event: DragEvent) {
    event.preventDefault();
    dragOverAlimentId.value = id;
}

function onAlimentDrop(targetId: number) {
    if (dragAlimentId.value === null || dragAlimentId.value === targetId) {
        dragAlimentId.value = null;
        dragOverAlimentId.value = null;
        return;
    }
    const ids = props.ration.ration_aliments.map((ra) => ra.id);
    const fromIdx = ids.indexOf(dragAlimentId.value);
    const toIdx = ids.indexOf(targetId);
    ids.splice(fromIdx, 1);
    ids.splice(toIdx, 0, dragAlimentId.value);
    persistOrder(
        reorderAliments({ plan: props.plan.id, ration: props.ration.id }).url,
        ids,
    );
    dragAlimentId.value = null;
    dragOverAlimentId.value = null;
}

function onAlimentDragEnd() {
    dragAlimentId.value = null;
    dragOverAlimentId.value = null;
}

// ─── Mélanges ───────────────────────────────────────────────────────────────

const newMelangeNom = ref('');
const expandedMelangeId = ref<number | null>(null);
const showAddAlimentMelange = ref<Record<number, boolean>>({});
const selectedMelangeAlimentId = ref<Record<number, number | undefined>>({});
const newMelangeAlimentQty = ref<Record<number, number | null>>({});
const newMelangeAlimentIsMb = ref<Record<number, boolean>>({});

function getFilteredMelangeAlimentItems(
    melangeId: number,
): AlimentSelectItem[] {
    return filterAlimentItems(
        alimentItems.value,
        melangeSearchTerms.value[melangeId],
    );
}

function createMelange() {
    router.post(
        storeMelange({ plan: props.plan.id, ration: props.ration.id }).url,
        {
            nom: newMelangeNom.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newMelangeNom.value = '';
            },
        },
    );
}

function deleteMelange(melange: Melange) {
    router.delete(
        destroyMelange({
            plan: props.plan.id,
            ration: props.ration.id,
            melange: melange.id,
        }).url,
        {
            preserveScroll: true,
        },
    );
}

function saveMelange(
    melange: Melange,
    overrides: Partial<
        Pick<Melange, 'nom' | 'quantite' | 'is_volonte' | 'is_mb'>
    > = {},
) {
    quantityErrorContext.value = `mix-${melange.id}`;
    router.put(
        updateMelange({
            plan: props.plan.id,
            ration: props.ration.id,
            melange: melange.id,
        }).url,
        {
            nom: melange.nom,
            quantite: melange.quantite,
            is_volonte: melange.is_volonte,
            is_mb: melange.is_mb,
            ...overrides,
        },
        { preserveScroll: true },
    );
}

function addAlimentToMelangeAction(melange: Melange) {
    const alimentId = selectedMelangeAlimentId.value[melange.id];
    if (!alimentId) return;
    router.post(
        addAlimentToMelange({
            plan: props.plan.id,
            ration: props.ration.id,
            melange: melange.id,
        }).url,
        {
            aliment_id: alimentId,
            quantite: newMelangeAlimentQty.value[melange.id] ?? null,
            is_mb: newMelangeAlimentIsMb.value[melange.id] ?? false,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAddAlimentMelange.value[melange.id] = false;
                selectedMelangeAlimentId.value[melange.id] = undefined;
                melangeSearchTerms.value[melange.id] = '';
                newMelangeAlimentQty.value[melange.id] = null;
                newMelangeAlimentIsMb.value[melange.id] = false;
            },
        },
    );
}

function updateMelangeAlimentQty(
    melange: Melange,
    ma: MelangeAliment,
    quantite: string,
    isMb: boolean = ma.is_mb,
) {
    quantityErrorContext.value = `ma-${ma.id}`;
    router.put(
        updateMelangeAliment({
            plan: props.plan.id,
            ration: props.ration.id,
            melange: melange.id,
            melangeAliment: ma.id,
        }).url,
        {
            quantite: parseQuantite(quantite),
            is_mb: isMb,
        },
        { preserveScroll: true },
    );
}

function removeAlimentFromMelangeAction(melange: Melange, ma: MelangeAliment) {
    router.delete(
        removeAlimentFromMelange({
            plan: props.plan.id,
            ration: props.ration.id,
            melange: melange.id,
            melangeAliment: ma.id,
        }).url,
        {
            preserveScroll: true,
        },
    );
}

function alimentUpdatePayload(
    aliment: Aliment,
): Record<string, string | number | null> {
    return Object.fromEntries(
        alimentEditableKeys.map((key) => {
            const value = aliment[key];

            return [key, value === '' ? null : (value ?? null)];
        }),
    );
}

function getNumericAlimentValue(
    aliment: Aliment,
    key: string,
): number | null | undefined {
    const value = aliment[key];

    return typeof value === 'number' || value === null || value === undefined
        ? value
        : null;
}

function formatAlimentValue(aliment: Aliment, key: string): string {
    return formatNumber(getNumericAlimentValue(aliment, key));
}

function cloneAliment(aliment: Aliment): Aliment {
    return { ...aliment };
}

function closeEditingDialog(): void {
    editingDialogOpen.value = false;
    editingContext.value = null;
}

// ─── Glisser-déposer — Mélanges ─────────────────────────────────────────────

const dragMelangeId = ref<number | null>(null);
const dragOverMelangeId = ref<number | null>(null);

function onMelangeDragStart(id: number, event: DragEvent) {
    dragMelangeId.value = id;
    event.dataTransfer!.effectAllowed = 'move';
}

function onMelangeDragOver(id: number, event: DragEvent) {
    event.preventDefault();
    dragOverMelangeId.value = id;
}

function onMelangeDrop(targetId: number) {
    if (dragMelangeId.value === null || dragMelangeId.value === targetId) {
        dragMelangeId.value = null;
        dragOverMelangeId.value = null;
        return;
    }
    const ids = props.ration.melanges.map((m) => m.id);
    const fromIdx = ids.indexOf(dragMelangeId.value);
    const toIdx = ids.indexOf(targetId);
    ids.splice(fromIdx, 1);
    ids.splice(toIdx, 0, dragMelangeId.value);
    persistOrder(
        reorderMelanges({ plan: props.plan.id, ration: props.ration.id }).url,
        ids,
    );
    dragMelangeId.value = null;
    dragOverMelangeId.value = null;
}

function onMelangeDragEnd() {
    dragMelangeId.value = null;
    dragOverMelangeId.value = null;
}

// ─── Glisser-déposer — Aliments dans un mélange ─────────────────────────────

const dragMelangeAlimentKey = ref<string | null>(null); // "melangeId-alimentId"
const dragOverMelangeAlimentKey = ref<string | null>(null);

function maKey(melangeId: number, maId: number) {
    return `${melangeId}-${maId}`;
}

function onMelangeAlimentDragStart(
    melangeId: number,
    maId: number,
    event: DragEvent,
) {
    dragMelangeAlimentKey.value = maKey(melangeId, maId);
    event.dataTransfer!.effectAllowed = 'move';
}

function onMelangeAlimentDragOver(
    melangeId: number,
    maId: number,
    event: DragEvent,
) {
    event.preventDefault();
    dragOverMelangeAlimentKey.value = maKey(melangeId, maId);
}

function onMelangeAlimentDrop(melange: Melange, targetMaId: number) {
    if (dragMelangeAlimentKey.value === null) return;
    const [, draggedIdStr] = dragMelangeAlimentKey.value.split('-');
    const draggedId = parseInt(draggedIdStr);
    if (draggedId === targetMaId) {
        dragMelangeAlimentKey.value = null;
        dragOverMelangeAlimentKey.value = null;
        return;
    }
    const ids = melange.melange_aliments.map((ma) => ma.id);
    const fromIdx = ids.indexOf(draggedId);
    const toIdx = ids.indexOf(targetMaId);
    ids.splice(fromIdx, 1);
    ids.splice(toIdx, 0, draggedId);
    persistOrder(
        reorderMelangeAliments({
            plan: props.plan.id,
            ration: props.ration.id,
            melange: melange.id,
        }).url,
        ids,
    );
    dragMelangeAlimentKey.value = null;
    dragOverMelangeAlimentKey.value = null;
}

function onMelangeAlimentDragEnd() {
    dragMelangeAlimentKey.value = null;
    dragOverMelangeAlimentKey.value = null;
}
</script>

<template>
    <section id="composition" class="scroll-mt-24 space-y-6">
        <!-- Aliments -->
        <section class="rounded-xl border border-border bg-card shadow-sm">
            <div
                class="flex items-center justify-between border-b border-border px-5 py-4"
            >
                <h2 class="font-semibold text-foreground">Aliments</h2>
                <button
                    @click="showAddAliment = !showAddAliment"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-accent"
                >
                    <Plus class="size-4" />
                    Ajouter
                </button>
            </div>

            <div
                v-if="showAddAliment"
                class="border-b border-border bg-muted/30 px-5 py-4"
            >
                <div class="flex flex-col gap-3">
                    <USelectMenu
                        aria-label="Aliment à ajouter à la ration"
                        v-model="selectedAlimentId"
                        v-model:search-term="alimentSearchTerm"
                        v-bind="alimentSelectMenuOptions"
                        ignore-filter
                        value-key="value"
                        :items="filteredAlimentItems"
                        placeholder="Choisir un aliment…"
                        :search-input="alimentSearchInputProps"
                        class="w-full"
                    >
                        <template #item-trailing="{ item }">
                            <span
                                v-if="item.code_inra"
                                class="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300"
                                title="Aliment INRA (protégé)"
                            >
                                INRA
                            </span>
                        </template>
                        <template #empty>Aucun aliment trouvé.</template>
                    </USelectMenu>
                    <div class="flex flex-wrap items-center gap-3">
                        <input
                            aria-label="Quantité de l’aliment à ajouter, kg par animal et par jour"
                            v-model="newQuantite"
                            type="number"
                            min="0"
                            step="0.1"
                            placeholder="kg/animal/j"
                            class="w-36 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                        <label class="flex items-center gap-1.5 text-sm">
                            <input
                                v-model="newIsMb"
                                type="checkbox"
                                class="accent-primary"
                            />
                            MB
                        </label>
                        <label class="flex items-center gap-1.5 text-sm">
                            <input
                                v-model="newIsVolonte"
                                type="checkbox"
                                class="accent-primary"
                            />
                            À volonté
                        </label>
                        <button
                            @click="addAlimentToRation"
                            class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Ajouter
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="ration.ration_aliments.length === 0 && !showAddAliment"
                class="px-5 py-8 text-center text-sm text-muted-foreground"
            >
                Aucun aliment dans cette ration.
            </div>

            <template
                v-for="(ra, index) in ration.ration_aliments"
                :key="ra.id"
            >
                <div
                    draggable="true"
                    @dragstart="onAlimentDragStart(ra.id, $event)"
                    @dragover="onAlimentDragOver(ra.id, $event)"
                    @drop="onAlimentDrop(ra.id)"
                    @dragend="onAlimentDragEnd"
                    class="flex flex-wrap items-center gap-4 border-b border-border/50 px-5 py-3 transition-colors last:border-0 xl:flex-nowrap"
                    :class="{
                        'border-t-2 border-t-primary bg-primary/5':
                            dragOverAlimentId === ra.id &&
                            dragAlimentId !== ra.id,
                        'opacity-40': dragAlimentId === ra.id,
                    }"
                >
                    <GripVertical
                        class="size-4 shrink-0 cursor-grab text-muted-foreground/40 active:cursor-grabbing"
                        aria-hidden="true"
                    />
                    <ReorderButtons
                        :label="ra.aliment.libelle0"
                        :index="index"
                        :count="ration.ration_aliments.length"
                        :disabled="reordering"
                        @move="
                            moveItem(
                                ration.ration_aliments,
                                index,
                                $event,
                                reorderAliments({
                                    plan: plan.id,
                                    ration: ration.id,
                                }).url,
                            )
                        "
                    />
                    <div
                        class="order-first min-w-0 basis-full sm:order-none sm:flex-1 sm:basis-auto"
                    >
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ ra.aliment.libelle0 }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <span
                                v-if="ra.aliment.libelle1"
                                class="mr-1 italic"
                                >{{ ra.aliment.libelle1 }}</span
                            >
                            UFL: {{ formatAlimentValue(ra.aliment, 'ufl') }} ·
                            MS: {{ formatAlimentValue(ra.aliment, 'ms') }}%
                            <span
                                v-if="ra.is_mb"
                                class="ml-1 rounded bg-blue-100 px-1 text-blue-700"
                                >MB</span
                            >
                            <span
                                v-if="ra.is_volonte"
                                class="ml-1 rounded bg-amber-100 px-1 text-amber-700"
                                >À volonté</span
                            >
                        </p>
                    </div>
                    <input
                        :aria-label="`Quantité de ${ra.aliment.libelle0}, kg ${ra.is_mb ? 'de matière brute' : 'de matière sèche'} par animal et par jour`"
                        :value="ra.quantite"
                        :aria-invalid="Boolean(quantityError(`ra-${ra.id}`))"
                        :aria-describedby="
                            quantityError(`ra-${ra.id}`)
                                ? 'ration-save-errors'
                                : undefined
                        "
                        @change="
                            updateAlimentQty(
                                ra,
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                        type="number"
                        min="0"
                        step="0.1"
                        :placeholder="ra.is_volonte ? '∞' : 'kg'"
                        :disabled="ra.is_volonte"
                        class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none disabled:opacity-50 sm:w-24"
                    />
                    <label
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                        title="À volonté"
                        :class="{
                            'opacity-40':
                                composantVolonte &&
                                !(
                                    composantVolonte.type === 'aliment' &&
                                    composantVolonte.id === ra.id
                                ),
                        }"
                    >
                        <input
                            :checked="ra.is_volonte"
                            @change="
                                updateAlimentQty(
                                    ra,
                                    ra.quantite?.toString() ?? '',
                                    !ra.is_volonte,
                                    ra.is_mb,
                                )
                            "
                            type="checkbox"
                            class="accent-primary"
                            :disabled="
                                composantVolonte !== null &&
                                !(
                                    composantVolonte.type === 'aliment' &&
                                    composantVolonte.id === ra.id
                                )
                            "
                        />
                        ∞
                    </label>
                    <label
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                        title="Matière brute"
                    >
                        <input
                            :checked="ra.is_mb"
                            @change="
                                updateAlimentQty(
                                    ra,
                                    ra.quantite?.toString() ?? '',
                                    ra.is_volonte,
                                    !ra.is_mb,
                                )
                            "
                            type="checkbox"
                            class="accent-primary"
                        />
                        MB
                    </label>
                    <button
                        @click="openEditAliment(ra)"
                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent"
                        title="Éditer les valeurs"
                    >
                        <SquareArrowOutUpRight class="size-4" />
                    </button>
                    <button
                        :aria-label="`Retirer ${ra.aliment.libelle0} de la ration`"
                        @click="removeAlimentFromRation(ra)"
                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </template>
        </section>

        <!-- Mélanges -->
        <section class="rounded-xl border border-border bg-card shadow-sm">
            <div
                class="flex items-center justify-between border-b border-border px-5 py-4"
            >
                <h2 class="font-semibold text-foreground">Mélanges</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        aria-label="Nom du nouveau mélange"
                        v-model="newMelangeNom"
                        type="text"
                        placeholder="Nom du mélange (optionnel)"
                        class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none sm:w-auto"
                    />
                    <button
                        @click="createMelange"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        <Plus class="size-4" />
                        Nouveau mélange
                    </button>
                </div>
            </div>

            <div
                v-if="ration.melanges.length === 0"
                class="px-5 py-8 text-center text-sm text-muted-foreground"
            >
                Aucun mélange dans cette ration.
            </div>

            <div
                v-for="(melange, index) in ration.melanges"
                :key="melange.id"
                class="border-b border-border/50 transition-colors last:border-0"
                :class="{
                    'border-t-2 border-t-primary bg-primary/5':
                        dragOverMelangeId === melange.id &&
                        dragMelangeId !== melange.id,
                    'opacity-40': dragMelangeId === melange.id,
                }"
            >
                <!-- En-tête mélange -->
                <div
                    draggable="true"
                    @dragstart="onMelangeDragStart(melange.id, $event)"
                    @dragover="onMelangeDragOver(melange.id, $event)"
                    @drop="onMelangeDrop(melange.id)"
                    @dragend="onMelangeDragEnd"
                    class="flex flex-wrap items-center gap-3 px-5 py-3 xl:flex-nowrap"
                >
                    <GripVertical
                        class="size-4 shrink-0 cursor-grab text-muted-foreground/40 active:cursor-grabbing"
                        aria-hidden="true"
                    />
                    <ReorderButtons
                        :label="melange.nom || 'ce mélange'"
                        :index="index"
                        :count="ration.melanges.length"
                        :disabled="reordering"
                        @move="
                            moveItem(
                                ration.melanges,
                                index,
                                $event,
                                reorderMelanges({
                                    plan: plan.id,
                                    ration: ration.id,
                                }).url,
                            )
                        "
                    />
                    <input
                        :aria-label="`Nom du mélange ${index + 1}`"
                        :value="melange.nom"
                        @change="
                            saveMelange(melange, {
                                nom: ($event.target as HTMLInputElement).value,
                            })
                        "
                        type="text"
                        placeholder="Nom du mélange"
                        class="order-first min-w-0 basis-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm font-medium focus:ring-2 focus:ring-primary focus:outline-none sm:order-none sm:flex-1 sm:basis-auto"
                    />
                    <input
                        :aria-label="`Quantité de ${melange.nom || 'ce mélange'}, kg ${melange.is_mb ? 'de matière brute' : 'de matière sèche'} par animal et par jour`"
                        :value="melange.quantite"
                        :aria-invalid="
                            Boolean(quantityError(`mix-${melange.id}`))
                        "
                        :aria-describedby="
                            quantityError(`mix-${melange.id}`)
                                ? 'ration-save-errors'
                                : undefined
                        "
                        @change="
                            saveMelange(melange, {
                                quantite: parseQuantite(
                                    ($event.target as HTMLInputElement).value,
                                ),
                            })
                        "
                        type="number"
                        min="0"
                        step="0.1"
                        :placeholder="melange.is_volonte ? '∞' : 'kg/animal/j'"
                        :disabled="melange.is_volonte"
                        class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none disabled:opacity-50 sm:w-24"
                    />
                    <label
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                        title="À volonté"
                        :class="{
                            'opacity-40':
                                composantVolonte &&
                                !(
                                    composantVolonte.type === 'melange' &&
                                    composantVolonte.id === melange.id
                                ),
                        }"
                    >
                        <input
                            :checked="melange.is_volonte"
                            @change="
                                saveMelange(melange, {
                                    is_volonte: !melange.is_volonte,
                                })
                            "
                            type="checkbox"
                            class="accent-primary"
                            :disabled="
                                composantVolonte !== null &&
                                !(
                                    composantVolonte.type === 'melange' &&
                                    composantVolonte.id === melange.id
                                )
                            "
                        />
                        ∞
                    </label>
                    <label
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                        title="Matière brute"
                    >
                        <input
                            :checked="melange.is_mb"
                            @change="
                                saveMelange(melange, { is_mb: !melange.is_mb })
                            "
                            type="checkbox"
                            class="accent-primary"
                        />
                        MB
                    </label>
                    <span
                        v-if="melange.is_mb"
                        class="rounded bg-blue-100 px-1 text-xs text-blue-700"
                        >MB</span
                    >
                    <span
                        class="text-xs whitespace-nowrap text-muted-foreground"
                        >{{ melange.melange_aliments.length }} aliment(s)</span
                    >
                    <button
                        :aria-label="`${expandedMelangeId === melange.id ? 'Replier' : 'Déplier'} ${melange.nom || 'le mélange'}`"
                        :aria-expanded="expandedMelangeId === melange.id"
                        @click="
                            expandedMelangeId =
                                expandedMelangeId === melange.id
                                    ? null
                                    : melange.id
                        "
                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent"
                    >
                        <ChevronDown
                            v-if="expandedMelangeId !== melange.id"
                            class="size-4"
                        />
                        <ChevronUp v-else class="size-4" />
                    </button>
                    <button
                        :aria-label="`Supprimer ${melange.nom || 'le mélange'}`"
                        @click="deleteMelange(melange)"
                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>

                <!-- Contenu mélange (dépliable) -->
                <div
                    v-if="expandedMelangeId === melange.id"
                    class="bg-muted/20 pb-3"
                >
                    <!-- Aliments du mélange -->
                    <template
                        v-for="(ma, alimentIndex) in melange.melange_aliments"
                        :key="ma.id"
                    >
                        <div
                            draggable="true"
                            @dragstart="
                                onMelangeAlimentDragStart(
                                    melange.id,
                                    ma.id,
                                    $event,
                                )
                            "
                            @dragover="
                                onMelangeAlimentDragOver(
                                    melange.id,
                                    ma.id,
                                    $event,
                                )
                            "
                            @drop="onMelangeAlimentDrop(melange, ma.id)"
                            @dragend="onMelangeAlimentDragEnd"
                            class="flex flex-wrap items-center gap-4 border-t border-border/30 px-8 py-2 transition-colors xl:flex-nowrap"
                            :class="{
                                'border-t-2 border-t-primary bg-primary/5':
                                    dragOverMelangeAlimentKey ===
                                        maKey(melange.id, ma.id) &&
                                    dragMelangeAlimentKey !==
                                        maKey(melange.id, ma.id),
                                'opacity-40':
                                    dragMelangeAlimentKey ===
                                    maKey(melange.id, ma.id),
                            }"
                        >
                            <GripVertical
                                class="size-4 shrink-0 cursor-grab text-muted-foreground/40 active:cursor-grabbing"
                                aria-hidden="true"
                            />
                            <ReorderButtons
                                :label="`${ma.aliment.libelle0} dans ${melange.nom || 'le mélange'}`"
                                :index="alimentIndex"
                                :count="melange.melange_aliments.length"
                                :disabled="reordering"
                                @move="
                                    moveItem(
                                        melange.melange_aliments,
                                        alimentIndex,
                                        $event,
                                        reorderMelangeAliments({
                                            plan: plan.id,
                                            ration: ration.id,
                                            melange: melange.id,
                                        }).url,
                                    )
                                "
                            />
                            <div
                                class="order-first min-w-0 basis-full sm:order-none sm:flex-1 sm:basis-auto"
                            >
                                <p class="truncate text-sm text-foreground">
                                    {{ ma.aliment.libelle0 }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <span
                                        v-if="ma.aliment.libelle1"
                                        class="mr-1 italic"
                                        >{{ ma.aliment.libelle1 }}</span
                                    >
                                    UFL:
                                    {{ formatAlimentValue(ma.aliment, 'ufl') }}
                                    · MS:
                                    {{ formatAlimentValue(ma.aliment, 'ms') }}%
                                    <span
                                        v-if="ma.is_mb"
                                        class="ml-1 rounded bg-blue-100 px-1 text-blue-700"
                                        >MB</span
                                    >
                                </p>
                            </div>
                            <input
                                :aria-label="`Quantité de ${ma.aliment.libelle0} dans ${melange.nom || 'le mélange'}, kg ${ma.is_mb ? 'de matière brute' : 'de matière sèche'}`"
                                :value="ma.quantite"
                                :aria-invalid="
                                    Boolean(quantityError(`ma-${ma.id}`))
                                "
                                :aria-describedby="
                                    quantityError(`ma-${ma.id}`)
                                        ? 'ration-save-errors'
                                        : undefined
                                "
                                @change="
                                    updateMelangeAlimentQty(
                                        melange,
                                        ma,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                                type="number"
                                min="0"
                                step="0.1"
                                placeholder="kg"
                                class="w-full rounded-lg border border-border bg-background px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none sm:w-24"
                            />
                            <label
                                class="flex items-center gap-1 text-xs text-muted-foreground"
                                title="Matière brute"
                            >
                                <input
                                    :checked="ma.is_mb"
                                    @change="
                                        updateMelangeAlimentQty(
                                            melange,
                                            ma,
                                            ma.quantite?.toString() ?? '',
                                            !ma.is_mb,
                                        )
                                    "
                                    type="checkbox"
                                    class="accent-primary"
                                />
                                MB
                            </label>
                            <button
                                @click="openEditMelangeAliment(melange, ma)"
                                class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent"
                                title="Éditer les valeurs"
                            >
                                <SquareArrowOutUpRight class="size-4" />
                            </button>
                            <button
                                :aria-label="`Retirer ${ma.aliment.libelle0} de ${melange.nom || 'ce mélange'}`"
                                @click="
                                    removeAlimentFromMelangeAction(melange, ma)
                                "
                                class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </template>

                    <!-- Ajouter aliment au mélange -->
                    <div class="border-t border-border/30 px-8 pt-3">
                        <div v-if="!showAddAlimentMelange[melange.id]">
                            <button
                                @click="
                                    showAddAlimentMelange[melange.id] = true
                                "
                                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                            >
                                <Plus class="size-3.5" />
                                Ajouter un aliment
                            </button>
                        </div>
                        <div v-else class="flex flex-col gap-2">
                            <USelectMenu
                                :aria-label="`Aliment à ajouter à ${melange.nom || 'ce mélange'}`"
                                v-model="selectedMelangeAlimentId[melange.id]"
                                v-model:search-term="
                                    melangeSearchTerms[melange.id]
                                "
                                v-bind="alimentSelectMenuOptions"
                                ignore-filter
                                value-key="value"
                                :items="
                                    getFilteredMelangeAlimentItems(melange.id)
                                "
                                placeholder="Choisir un aliment…"
                                :search-input="alimentSearchInputProps"
                                class="w-full"
                            >
                                <template #item-trailing="{ item }">
                                    <span
                                        v-if="item.code_inra"
                                        class="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300"
                                        title="Aliment INRA (protégé)"
                                    >
                                        INRA
                                    </span>
                                </template>
                                <template #empty
                                    >Aucun aliment trouvé.</template
                                >
                            </USelectMenu>
                            <div class="flex flex-wrap items-center gap-2">
                                <input
                                    :aria-label="`Quantité de l’aliment à ajouter à ${melange.nom || 'ce mélange'}, kg`"
                                    v-model="newMelangeAlimentQty[melange.id]"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    placeholder="kg"
                                    class="w-24 rounded-lg border border-border bg-background px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                                />
                                <label
                                    class="flex items-center gap-1.5 text-sm"
                                >
                                    <input
                                        v-model="
                                            newMelangeAlimentIsMb[melange.id]
                                        "
                                        type="checkbox"
                                        class="accent-primary"
                                    />
                                    MB
                                </label>
                                <button
                                    @click="addAlimentToMelangeAction(melange)"
                                    class="rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                >
                                    Ajouter
                                </button>
                                <button
                                    @click="
                                        showAddAlimentMelange[melange.id] =
                                            false;
                                        melangeSearchTerms[melange.id] = '';
                                        newMelangeAlimentIsMb[melange.id] =
                                            false;
                                    "
                                    class="rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-accent"
                                >
                                    Annuler
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <p
            role="status"
            aria-live="polite"
            class="text-sm text-muted-foreground"
        >
            {{ reorderStatus }}
        </p>
        <p v-if="reorderError" role="alert" class="text-sm text-destructive">
            {{ reorderError }}
        </p>
        <div
            v-if="saveErrors.length"
            id="ration-save-errors"
            role="alert"
            class="rounded-lg border border-destructive/40 p-4 text-sm text-destructive"
        >
            <p class="font-medium">La modification n’a pas été enregistrée.</p>
            <ul class="mt-2 list-disc pl-5">
                <li v-for="error in saveErrors" :key="error">{{ error }}</li>
            </ul>
        </div>
        <!-- Fenêtre modale d'édition des valeurs aliment -->
        <Dialog v-model:open="editingDialogOpen">
            <DialogScrollContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Modifier les valeurs</DialogTitle>
                    <DialogDescription>
                        {{ editingContext?.aliment.libelle0 }}
                        <span v-if="editingContext?.aliment.libelle1">
                            — {{ editingContext.aliment.libelle1 }}</span
                        >
                    </DialogDescription>
                </DialogHeader>
                <div v-if="editingContext" class="py-2">
                    <AlimentValuesEditor
                        v-model:aliment="editingContext.draft"
                        :errors="page.props.errors"
                    />
                </div>
                <DialogFooter>
                    <button
                        @click="closeEditingDialog"
                        class="rounded-lg border border-border px-4 py-1.5 text-sm hover:bg-accent"
                    >
                        Annuler
                    </button>
                    <button
                        @click="saveEditingValeurs"
                        class="rounded-lg bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Enregistrer
                    </button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </section>
</template>
