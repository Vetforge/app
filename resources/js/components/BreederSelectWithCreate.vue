<script setup lang="ts">
import { Plus, Search } from 'lucide-vue-next';
import {
    ComboboxRoot,
    ComboboxInput,
    ComboboxContent,
    ComboboxViewport,
    ComboboxItem,
    ComboboxEmpty,
} from 'reka-ui';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogTrigger,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { focusFirstInvalidField } from '@/lib/formValidation';

defineOptions({ inheritAttrs: false });

export interface BreederOption {
    id: number;
    name: string;
    city: string | null;
    herd_number: string | null;
}

type FieldErrors = Record<string, string>;

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        breeders: BreederOption[];
        createUrl: string;
        inputId?: string;
        name?: string;
        placeholder?: string;
        required?: boolean;
    }>(),
    {
        inputId: 'breeder_id',
        name: undefined,
        placeholder: 'Rechercher un éleveur...',
        required: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number];
}>();

const localBreeders = ref<BreederOption[]>([]);
const query = ref('');
const dropdownOpen = ref(false);
const modalOpen = ref(false);
const creating = ref(false);
const createErrors = ref<FieldErrors>({});
const createError = ref('');
const newBreeder = reactive({
    name: '',
    city: '',
    phone: '',
    email: '',
    herd_number: '',
});

const selectedBreeder = computed(() =>
    localBreeders.value.find(
        (breeder) => String(breeder.id) === String(props.modelValue ?? ''),
    ),
);

const selectedLabel = computed(() =>
    selectedBreeder.value ? breederLabel(selectedBreeder.value) : '',
);

const filteredBreeders = computed(() => {
    const tokens = normalize(query.value).split(' ').filter(Boolean);

    if (tokens.length === 0) {
        return localBreeders.value.slice(0, 50);
    }

    return localBreeders.value
        .filter((breeder) => {
            const haystack = normalize(
                [
                    breeder.name,
                    breeder.city ?? '',
                    breeder.herd_number ?? '',
                ].join(' '),
            );

            return tokens.every((token) => haystack.includes(token));
        })
        .slice(0, 50);
});

watch(
    () => props.breeders,
    (breeders) => {
        localBreeders.value = [...breeders];
    },
    { immediate: true },
);

watch(
    selectedLabel,
    (label) => {
        if (!dropdownOpen.value) {
            query.value = label;
        }
    },
    { immediate: true },
);

function normalize(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function breederLabel(breeder: BreederOption): string {
    return [breeder.name, breeder.city, breeder.herd_number]
        .filter(Boolean)
        .join(' - ');
}

function selectBreeder(breeder: BreederOption): void {
    emit('update:modelValue', breeder.id);
    query.value = breederLabel(breeder);
    dropdownOpen.value = false;
}

function handleInput(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    query.value = value;
    dropdownOpen.value = true;

    if (value !== selectedLabel.value) {
        emit('update:modelValue', '');
    }
}

watch(dropdownOpen, (open) => {
    if (!open) query.value = selectedLabel.value;
});

function openCreateModal(): void {
    resetCreateForm();
    newBreeder.name = query.value.trim();
    modalOpen.value = true;
}

function closeCreateModal(): void {
    modalOpen.value = false;
    resetCreateForm();
}

function resetCreateForm(): void {
    createErrors.value = {};
    createError.value = '';
    Object.assign(newBreeder, {
        name: '',
        city: '',
        phone: '',
        email: '',
        herd_number: '',
    });
}

async function createBreeder(): Promise<void> {
    if (creating.value) return;
    createErrors.value = {};
    createError.value = '';
    creating.value = true;

    try {
        const response = await fetch(props.createUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(newBreeder),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422) {
                createErrors.value = normalizeErrors(data.errors);
                await nextTick(focusFirstInvalidField);
            } else
                createError.value =
                    response.status === 419 || response.status === 401
                        ? 'Votre session a expiré. Reconnectez-vous avant de réessayer.'
                        : 'La création a échoué. Vos données sont conservées ; réessayez.';
            return;
        }

        const breeder = data.breeder as BreederOption;
        localBreeders.value = [
            breeder,
            ...localBreeders.value.filter((item) => item.id !== breeder.id),
        ].sort((a, b) => a.name.localeCompare(b.name));
        selectBreeder(breeder);
        closeCreateModal();
    } catch {
        createError.value =
            'Connexion interrompue. Vos données sont conservées ; réessayez.';
    } finally {
        creating.value = false;
    }
}

function normalizeErrors(errors: unknown): FieldErrors {
    if (typeof errors !== 'object' || errors === null) {
        return { name: 'Creation impossible.' };
    }

    return Object.fromEntries(
        Object.entries(errors as Record<string, string[] | string>).map(
            ([field, messages]) => [
                field,
                Array.isArray(messages) ? messages[0] : messages,
            ],
        ),
    );
}

function csrfHeader(): Record<string, string> {
    const metaToken = (
        document.querySelector(
            'meta[name="csrf-token"]',
        ) as HTMLMetaElement | null
    )?.content;

    if (metaToken) {
        return { 'X-CSRF-TOKEN': metaToken };
    }

    const xsrfCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');

    return xsrfCookie ? { 'X-CSRF-TOKEN': decodeURIComponent(xsrfCookie) } : {};
}
</script>

<template>
    <div class="flex gap-2">
        <input
            v-if="name"
            type="hidden"
            :name="name"
            :value="modelValue ?? ''"
        />

        <ComboboxRoot
            v-model:open="dropdownOpen"
            :model-value="selectedBreeder ?? null"
            @update:model-value="
                (breeder) => {
                    if (breeder) selectBreeder(breeder);
                }
            "
            open-on-focus
            open-on-click
            ignore-filter
            :reset-search-term-on-select="false"
            :reset-search-term-on-blur="false"
            class="relative min-w-0 flex-1"
        >
            <Search
                class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <ComboboxInput
                v-bind="$attrs"
                :id="inputId"
                type="text"
                v-model="query"
                :placeholder="placeholder"
                :required="required"
                class="h-10 w-full rounded-lg border border-border bg-background py-2 pr-3 pl-9! text-sm leading-5 text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                autocomplete="off"
                @input="handleInput"
            />

            <ComboboxContent
                class="absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-border bg-popover py-1 text-sm shadow-lg"
            >
                <ComboboxViewport>
                    <ComboboxItem
                        v-for="breeder in filteredBreeders"
                        :key="breeder.id"
                        :value="breeder"
                        class="flex min-h-11 w-full cursor-pointer flex-col px-3 py-2 text-left outline-none data-[highlighted]:bg-accent"
                    >
                        <span class="font-medium text-foreground">{{
                            breeder.name
                        }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{
                                [breeder.city, breeder.herd_number]
                                    .filter(Boolean)
                                    .join(' - ') || '-'
                            }}
                        </span>
                    </ComboboxItem>
                    <ComboboxEmpty
                        class="px-3 py-2 text-sm text-muted-foreground"
                    >
                        Aucun éleveur trouve.
                    </ComboboxEmpty>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxRoot>

        <Dialog v-model:open="modalOpen">
            <DialogTrigger as-child>
                <button
                    type="button"
                    class="breeder-create-btn inline-flex size-11 shrink-0 items-center justify-center rounded-lg border border-border text-foreground hover:bg-accent"
                    aria-label="Créer un éleveur"
                    title="Nouvel éleveur"
                    @click="openCreateModal"
                >
                    <Plus class="size-4" />
                </button>
            </DialogTrigger>
            <DialogScrollContent>
                <form
                    class="grid gap-5"
                    @submit.prevent.stop="createBreeder"
                    :aria-busy="creating"
                >
                    <DialogHeader>
                        <DialogTitle> Nouvel éleveur </DialogTitle>
                        <DialogDescription>
                            Creation rapide rattachee à votre compte.
                        </DialogDescription>
                    </DialogHeader>
                    <p
                        v-if="createError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ createError }}
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5 sm:col-span-2">
                            <label
                                class="text-sm font-medium"
                                for="quick_breeder_name"
                            >
                                Nom *
                            </label>
                            <input
                                :aria-invalid="Boolean(createErrors.name)"
                                :aria-describedby="
                                    createErrors.name
                                        ? 'quick_breeder_name-error'
                                        : undefined
                                "
                                id="quick_breeder_name"
                                v-model="newBreeder.name"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                                required
                            />
                            <InputError
                                id="quick_breeder_name-error"
                                :message="createErrors.name"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <label
                                class="text-sm font-medium"
                                for="quick_breeder_city"
                            >
                                Ville
                            </label>
                            <input
                                id="quick_breeder_city"
                                v-model="newBreeder.city"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <label
                                class="text-sm font-medium"
                                for="quick_breeder_herd"
                            >
                                Numero cheptel
                            </label>
                            <input
                                id="quick_breeder_herd"
                                v-model="newBreeder.herd_number"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <label
                                class="text-sm font-medium"
                                for="quick_breeder_phone"
                            >
                                Telephone
                            </label>
                            <input
                                id="quick_breeder_phone"
                                v-model="newBreeder.phone"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <label
                                class="text-sm font-medium"
                                for="quick_breeder_email"
                            >
                                Email
                            </label>
                            <input
                                :aria-invalid="Boolean(createErrors.email)"
                                :aria-describedby="
                                    createErrors.email
                                        ? 'quick_breeder_email-error'
                                        : undefined
                                "
                                id="quick_breeder_email"
                                v-model="newBreeder.email"
                                type="email"
                                class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                            />
                            <InputError
                                id="quick_breeder_email-error"
                                :message="createErrors.email"
                            />
                        </div>
                    </div>

                    <div
                        class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium hover:bg-accent"
                            @click="closeCreateModal"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            :disabled="creating"
                            class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                        >
                            {{ creating ? 'Creation...' : 'Creer' }}
                        </button>
                    </div>
                </form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
