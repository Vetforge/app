<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    index as alimentsIndex,
    store as alimentStore,
    update as alimentUpdate,
} from '@/actions/App/Http/Controllers/AlimentController';
import BreederSelectWithCreate from '@/components/BreederSelectWithCreate.vue';
import type { BreederOption } from '@/components/BreederSelectWithCreate.vue';
import { alimentFieldLabel } from '@/components/rations/alimentEditableFields';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumberInput } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { ALIMENT_TYPES } from '@/types/aliment';

interface Aliment {
    id?: number;
    breeder_id?: number | null;
    code_inra: string | null;
    type: string | null;
    famille_botanique: string | null;
    procede_technologique: string | null;
    libelle0: string;
    libelle1: string | null;
    libelle2: string | null;
    libelle3: string | null;
    libelle4: string | null;
    prix: number | null;
    usage_aliment: string | null;
    ms: number | null;
    mo: number | null;
    mat: number | null;
    cb: number | null;
    ndf: number | null;
    adf: number | null;
    adl: number | null;
    ee: number | null;
    ufl: number | null;
    ufv: number | null;
    pdia: number | null;
    pdi: number | null;
    ca: number | null;
    p: number | null;
    mg: number | null;
    na: number | null;
    k: number | null;
    ufl2007: number | null;
    ufv2007: number | null;
    pdie2007: number | null;
    pdin2007: number | null;
    [key: string]: unknown;
}

interface SourceAliment {
    id: number;
    libelle0: string;
    code_inra: string | null;
}

const props = defineProps<{
    breeders: BreederOption[];
    quickBreederStoreUrl: string;
    aliment?: Aliment;
    mode?: 'create' | 'edit' | 'copy';
    sourceAliment?: SourceAliment | null;
}>();

const breederId = ref<string | number | null>(props.aliment?.breeder_id ?? '');
const page = usePage();

const mode = props.mode ?? (props.aliment ? 'edit' : 'create');
const isEdit = mode === 'edit';
const isCopy = mode === 'copy';
const activeTab = ref('identification');
const processing = ref(false);
const saved = ref(false);
const pageTitle =
    isEdit || isCopy
        ? (props.aliment?.libelle0 ?? 'Nouvel aliment')
        : 'Nouvel aliment';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Aliments', href: alimentsIndex() },
    { title: pageTitle, href: '#' },
];

const tabs = [
    { id: 'identification', label: 'Identification' },
    { id: 'energie', label: 'Énergie' },
    { id: 'proteines', label: 'Protéines' },
    { id: 'mineraux', label: 'Minéraux' },
    { id: 'vitamines', label: 'Vitamines' },
    { id: 'valeurs2007', label: 'Valeurs 2007' },
];

function numericFieldValue(field: string): string {
    return formatNumberInput(
        (props.aliment as Record<string, unknown> | undefined)?.[field] as
            | number
            | null
            | undefined,
    );
}

function fieldLabel(field: string): string {
    return alimentFieldLabel(field);
}

function submit(event: Event) {
    const form = event.target as HTMLFormElement;
    const data = Object.fromEntries(new FormData(form));
    if (isEdit && props.aliment) {
        for (const [field, value] of Object.entries(data)) {
            if (field === 'type' || field === 'libelle0') {
                continue;
            }
            const input = form.elements.namedItem(field);
            const initialValue =
                input instanceof HTMLInputElement && input.type === 'number'
                    ? numericFieldValue(field)
                    : String(
                          props.aliment[field] ??
                              (field === 'famille_botanique' ||
                              field === 'procede_technologique'
                                  ? 'autre'
                                  : ''),
                      );
            if (value === initialValue) {
                delete data[field];
            }
        }
    }
    const options = {
        onStart: () => {
            processing.value = true;
            saved.value = false;
        },
        onSuccess: () => {
            saved.value = true;
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (isEdit && props.aliment?.id !== undefined) {
        router.put(
            alimentUpdate({ aliment: props.aliment.id }).url,
            data,
            options,
        );
    } else {
        router.post(alimentStore().url, data, options);
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ pageTitle }}
                    </h1>
                    <span
                        v-if="isEdit && aliment!.code_inra"
                        class="mt-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300"
                    >
                        INRA {{ aliment!.code_inra }}
                    </span>
                    <span
                        v-if="isCopy && sourceAliment?.code_inra"
                        class="mt-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                    >
                        Copie de INRA {{ sourceAliment.code_inra }}
                    </span>
                </div>
            </div>

            <div
                v-if="isCopy && sourceAliment"
                class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200"
            >
                Cet aliment n'est pas modifiable directement. Les valeurs
                ci-dessous seront enregistrées comme un nouvel aliment à partir
                de
                {{ sourceAliment.libelle0 }}.
            </div>

            <!-- Onglets -->
            <div class="mb-6 flex gap-1 overflow-x-auto border-b border-border">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    class="shrink-0 px-4 py-2 text-sm font-medium transition"
                    :class="
                        activeTab === tab.id
                            ? 'border-b-2 border-primary text-primary'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ tab.label }}
                </button>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div
                    v-if="Object.keys(page.props.errors).length"
                    role="alert"
                    class="rounded-lg border border-destructive p-4 text-sm text-destructive"
                >
                    <p>
                        L'aliment n'a pas été enregistré. Corrigez les erreurs
                        suivantes :
                    </p>
                    <ul>
                        <li
                            v-for="(message, field) in page.props.errors"
                            :key="field"
                        >
                            {{ fieldLabel(String(field)) }} : {{ message }}
                        </li>
                    </ul>
                </div>
                <p
                    v-else-if="saved"
                    role="status"
                    class="text-sm text-emerald-700 dark:text-emerald-300"
                >
                    Aliment enregistré.
                </p>
                <!-- Identification -->
                <section
                    v-show="activeTab === 'identification'"
                    class="grid gap-4 md:grid-cols-2"
                >
                    <div
                        v-if="!isEdit || !aliment?.code_inra"
                        class="flex flex-col gap-1.5 md:col-span-2"
                    >
                        <label
                            for="breeder_id"
                            class="text-sm font-medium text-foreground"
                            >Éleveur (facultatif)</label
                        >
                        <BreederSelectWithCreate
                            :aria-label="'Éleveur (facultatif)'"
                            v-model="breederId"
                            name="breeder_id"
                            :breeders="breeders"
                            :create-url="quickBreederStoreUrl"
                        />
                        <p
                            v-if="page.props.errors.breeder_id"
                            class="text-sm text-red-600"
                        >
                            {{ page.props.errors.breeder_id }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5 md:col-span-2">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="libelle0"
                            >Libellé principal *</label
                        >
                        <input
                            id="libelle0"
                            name="libelle0"
                            type="text"
                            :value="aliment?.libelle0"
                            required
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="libelle1"
                            >Libellé 1</label
                        >
                        <input
                            id="libelle1"
                            name="libelle1"
                            type="text"
                            :value="aliment?.libelle1"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="libelle2"
                            >Libellé 2</label
                        >
                        <input
                            id="libelle2"
                            name="libelle2"
                            type="text"
                            :value="aliment?.libelle2"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="type"
                            >Type *</label
                        >
                        <select
                            id="type"
                            name="type"
                            :value="aliment?.type"
                            required
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        >
                            <option value="">Sélectionner</option>
                            <option
                                v-for="type in ALIMENT_TYPES"
                                :key="type"
                                :value="type"
                            >
                                {{ type }}
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="famille_botanique"
                            >Famille botanique *</label
                        >
                        <select
                            id="famille_botanique"
                            name="famille_botanique"
                            :value="aliment?.famille_botanique ?? 'autre'"
                            required
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        >
                            <option value="mais">Maïs</option>
                            <option value="luzerne">Luzerne</option>
                            <option value="legumineuse">
                                Autre légumineuse
                            </option>
                            <option value="graminee">Graminée</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="procede_technologique"
                            >Procédé technologique *</label
                        >
                        <select
                            id="procede_technologique"
                            name="procede_technologique"
                            :value="aliment?.procede_technologique ?? 'autre'"
                            required
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        >
                            <option value="vert">Fourrage vert</option>
                            <option value="ensile">Ensilé</option>
                            <option value="foin">Foin</option>
                            <option value="deshydrate">Déshydraté</option>
                            <option value="paille">Paille</option>
                            <option value="concentre">Concentré</option>
                            <option value="mineral">Minéral</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="usage_aliment"
                            >Usage</label
                        >
                        <input
                            id="usage_aliment"
                            name="usage_aliment"
                            type="text"
                            :value="aliment?.usage_aliment"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="ms"
                            >MS (%)</label
                        >
                        <input
                            id="ms"
                            name="ms"
                            type="number"
                            step="0.01"
                            :value="formatNumberInput(aliment?.ms)"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="prix"
                            >Prix (€/unité MB)</label
                        >
                        <input
                            id="prix"
                            name="prix"
                            type="number"
                            step="0.01"
                            :value="formatNumberInput(aliment?.prix)"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                </section>

                <!-- Énergie -->
                <section
                    v-show="activeTab === 'energie'"
                    class="grid gap-4 md:grid-cols-3"
                >
                    <div
                        v-for="field in [
                            'ufl',
                            'ufv',
                            'uem',
                            'uel',
                            'ueb',
                            'eb',
                            'em',
                            'mo',
                            'mat',
                            'cb',
                            'ndf',
                            'adf',
                            'adl',
                            'ee',
                            'ag',
                            'amidon',
                            'sucres',
                            'pf',
                            'd_mo',
                            'd_ma',
                            'd_cb',
                            'd_ndf',
                            'd_adf',
                            'd_e',
                            'dt_n',
                            'dt6_n',
                            'dr_n',
                            'dt_ami',
                            'dt6_ami',
                            'dt_ms',
                            'dt6_ms',
                        ]"
                        :key="field"
                        class="flex flex-col gap-1.5"
                    >
                        <label
                            class="text-sm font-medium text-foreground"
                            :for="field"
                            >{{ fieldLabel(field) }}</label
                        >
                        <input
                            :id="field"
                            :name="field"
                            type="number"
                            step="0.01"
                            :value="numericFieldValue(field)"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                </section>

                <!-- Protéines -->
                <section
                    v-show="activeTab === 'proteines'"
                    class="grid gap-4 md:grid-cols-3"
                >
                    <div
                        v-for="field in [
                            'pdia',
                            'pdi',
                            'bpr',
                            'niref',
                            'lys_di',
                            'met_di',
                            'his_di',
                            'arg_di',
                            'thr_di',
                            'val_di',
                            'ile_di',
                            'leu_di',
                            'phe_di',
                        ]"
                        :key="field"
                        class="flex flex-col gap-1.5"
                    >
                        <label
                            class="text-sm font-medium text-foreground"
                            :for="field"
                            >{{ fieldLabel(field) }}</label
                        >
                        <input
                            :id="field"
                            :name="field"
                            type="number"
                            step="0.01"
                            :value="numericFieldValue(field)"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                </section>

                <!-- Minéraux -->
                <section
                    v-show="activeTab === 'mineraux'"
                    class="grid gap-4 md:grid-cols-3"
                >
                    <div
                        v-for="field in [
                            'ca',
                            'caabs',
                            'p',
                            'pabs',
                            'mg',
                            'na',
                            'k',
                            'cl',
                            's',
                            'be',
                            'baca',
                            'cu',
                            'zn',
                            'mn',
                            'co',
                            'se',
                            'i',
                            'molybdene',
                        ]"
                        :key="field"
                        class="flex flex-col gap-1.5"
                    >
                        <label
                            class="text-sm font-medium text-foreground"
                            :for="field"
                            >{{ fieldLabel(field) }}</label
                        >
                        <input
                            :id="field"
                            :name="field"
                            type="number"
                            step="0.01"
                            :value="numericFieldValue(field)"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                </section>

                <!-- Vitamines -->
                <section
                    v-show="activeTab === 'vitamines'"
                    class="grid gap-4 md:grid-cols-3"
                >
                    <div
                        v-for="field in [
                            'vit_a',
                            'vit_d',
                            'vit_e',
                            'c6_10',
                            'c12_0',
                            'c14_0',
                            'c16_0',
                            'c16_1',
                            'c18_0',
                            'c18_1',
                            'c18_2',
                            'c18_3',
                            'b_vec',
                        ]"
                        :key="field"
                        class="flex flex-col gap-1.5"
                    >
                        <label
                            class="text-sm font-medium text-foreground"
                            :for="field"
                            >{{ fieldLabel(field) }}</label
                        >
                        <input
                            :id="field"
                            :name="field"
                            type="number"
                            step="0.01"
                            :value="numericFieldValue(field)"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                </section>

                <!-- Valeurs 2007 -->
                <section
                    v-show="activeTab === 'valeurs2007'"
                    class="grid gap-4 md:grid-cols-3"
                >
                    <div
                        v-for="field in [
                            'ufl2007',
                            'ufv2007',
                            'pdia2007',
                            'pdie2007',
                            'pdin2007',
                            'd_mo2007',
                            'd_ma2007',
                            'd_cb2007',
                            'd_ndf2007',
                            'd_adf2007',
                            'uem2007',
                            'uel2007',
                            'ueb2007',
                            'eb2007',
                            'd_e2007',
                            'em2007',
                        ]"
                        :key="field"
                        class="flex flex-col gap-1.5"
                    >
                        <label
                            class="text-sm font-medium text-foreground"
                            :for="field"
                            >{{ fieldLabel(field) }}</label
                        >
                        <input
                            :id="field"
                            :name="field"
                            type="number"
                            step="0.01"
                            :value="numericFieldValue(field)"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                        />
                    </div>
                </section>

                <div
                    class="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="rounded-lg bg-primary px-6 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 sm:w-auto"
                    >
                        {{
                            isEdit
                                ? 'Mettre à jour'
                                : isCopy
                                  ? 'Créer la copie'
                                  : "Créer l'aliment"
                        }}
                    </button>
                    <a
                        :href="alimentsIndex().url"
                        class="inline-flex justify-center rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                    >
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
