<script setup lang="ts">
import { Plus, Search, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

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
        placeholder: 'Rechercher un eleveur...',
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

function closeDropdownSoon(): void {
    window.setTimeout(() => {
        dropdownOpen.value = false;
        query.value = selectedLabel.value;
    }, 120);
}

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
    Object.assign(newBreeder, {
        name: '',
        city: '',
        phone: '',
        email: '',
        herd_number: '',
    });
}

async function createBreeder(): Promise<void> {
    createErrors.value = {};
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
            createErrors.value = normalizeErrors(data.errors);
            return;
        }

        const breeder = data.breeder as BreederOption;
        localBreeders.value = [
            breeder,
            ...localBreeders.value.filter((item) => item.id !== breeder.id),
        ].sort((a, b) => a.name.localeCompare(b.name));
        selectBreeder(breeder);
        closeCreateModal();
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

        <div class="relative min-w-0 flex-1">
            <Search
                class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <input
                :id="inputId"
                type="text"
                role="combobox"
                :aria-expanded="dropdownOpen"
                :value="query"
                :placeholder="placeholder"
                :required="required"
                class="h-10 w-full rounded-lg border border-border bg-background py-2 pr-3 pl-9! text-sm leading-5 text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:outline-none"
                autocomplete="off"
                @focus="dropdownOpen = true"
                @blur="closeDropdownSoon"
                @input="handleInput"
                @keydown.esc="dropdownOpen = false"
            />

            <div
                v-if="dropdownOpen"
                class="absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-border bg-popover py-1 text-sm shadow-lg"
            >
                <button
                    v-for="breeder in filteredBreeders"
                    :key="breeder.id"
                    type="button"
                    class="flex w-full flex-col px-3 py-2 text-left hover:bg-accent"
                    @mousedown.prevent="selectBreeder(breeder)"
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
                </button>
                <p
                    v-if="filteredBreeders.length === 0"
                    class="px-3 py-2 text-sm text-muted-foreground"
                >
                    Aucun eleveur trouve.
                </p>
            </div>
        </div>

        <button
            type="button"
            class="breeder-create-btn inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-border text-foreground hover:bg-accent"
            title="Nouvel eleveur"
            @click="openCreateModal"
        >
            <Plus class="size-4" />
        </button>
    </div>

    <div
        v-if="modalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
    >
        <form
            class="w-full max-w-lg rounded-lg border border-border bg-card p-5 shadow-xl"
            @submit.prevent="createBreeder"
        >
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">
                        Nouvel eleveur
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Creation rapide rattachee a votre compte.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                    title="Fermer"
                    @click="closeCreateModal"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5 sm:col-span-2">
                    <label class="text-sm font-medium" for="quick_breeder_name">
                        Nom *
                    </label>
                    <input
                        id="quick_breeder_name"
                        v-model="newBreeder.name"
                        class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                        required
                    />
                    <p
                        v-if="createErrors.name"
                        class="text-sm text-destructive"
                    >
                        {{ createErrors.name }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <label class="text-sm font-medium" for="quick_breeder_city">
                        Ville
                    </label>
                    <input
                        id="quick_breeder_city"
                        v-model="newBreeder.city"
                        class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                </div>

                <div class="grid gap-1.5">
                    <label class="text-sm font-medium" for="quick_breeder_herd">
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
                        id="quick_breeder_email"
                        v-model="newBreeder.email"
                        type="email"
                        class="h-10 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                    <p
                        v-if="createErrors.email"
                        class="text-sm text-destructive"
                    >
                        {{ createErrors.email }}
                    </p>
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
    </div>
</template>
