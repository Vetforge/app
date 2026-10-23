<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    store as breederStore,
    update as breederUpdate,
    index as breedersIndex,
} from '@/actions/App/Http/Controllers/BreederController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Breeder {
    id: number;
    name: string;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    phone: string | null;
    email: string | null;
    herd_number: string | null;
    notes: string | null;
}

const props = defineProps<{
    breeder?: Breeder;
}>();

const isEdit = !!props.breeder;
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accueil', href: dashboard() },
    { title: 'Eleveurs', href: breedersIndex() },
    { title: isEdit ? 'Modifier' : 'Nouveléleveur', href: '#' },
];
</script>

<template>
    <Head :title="isEdit ? 'Modifier éleveur' : 'Nouvel éleveur'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl min-w-0 p-4 sm:p-6">
            <h1 class="mb-6 text-2xl font-bold text-foreground">
                {{ isEdit ? 'Modifier éleveur' : 'Nouvel éleveur' }}
            </h1>

            <Form
                v-bind="
                    isEdit
                        ? breederUpdate.form({ breeder: breeder!.id })
                        : breederStore.form()
                "
                :defaults="{
                    name: breeder?.name ?? '',
                    address: breeder?.address ?? '',
                    postal_code: breeder?.postal_code ?? '',
                    city: breeder?.city ?? '',
                    phone: breeder?.phone ?? '',
                    email: breeder?.email ?? '',
                    herd_number: breeder?.herd_number ?? '',
                    notes: breeder?.notes ?? '',
                }"
                #default="{ errors, processing }"
                class="grid gap-5"
            >
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="name"
                        >Nom / raison sociale *</label
                    >
                    <input
                        :aria-invalid="Boolean(errors.name)"
                        :aria-describedby="
                            errors.name ? 'name-error' : undefined
                        "
                        id="name"
                        name="name"
                        :value="breeder?.name ?? ''"
                        required
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                    />
                    <InputError id="name-error" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="address"
                        >Adresse</label
                    >
                    <input
                        :aria-invalid="Boolean(errors.address)"
                        :aria-describedby="
                            errors.address ? 'address-error' : undefined
                        "
                        id="address"
                        name="address"
                        :value="breeder?.address ?? ''"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                    />
                    <InputError id="address-error" :message="errors.address" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="postal_code"
                            >Code postal</label
                        >
                        <input
                            :aria-invalid="Boolean(errors.postal_code)"
                            :aria-describedby="
                                errors.postal_code
                                    ? 'postal_code-error'
                                    : undefined
                            "
                            id="postal_code"
                            name="postal_code"
                            :value="breeder?.postal_code ?? ''"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError
                            id="postal_code-error"
                            :message="errors.postal_code"
                        />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="city"
                            >Ville</label
                        >
                        <input
                            :aria-invalid="Boolean(errors.city)"
                            :aria-describedby="
                                errors.city ? 'city-error' : undefined
                            "
                            id="city"
                            name="city"
                            :value="breeder?.city ?? ''"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError id="city-error" :message="errors.city" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="phone"
                            >Telephone</label
                        >
                        <input
                            :aria-invalid="Boolean(errors.phone)"
                            :aria-describedby="
                                errors.phone ? 'phone-error' : undefined
                            "
                            id="phone"
                            name="phone"
                            :value="breeder?.phone ?? ''"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError id="phone-error" :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="email"
                            >Email</label
                        >
                        <input
                            :aria-invalid="Boolean(errors.email)"
                            :aria-describedby="
                                errors.email ? 'email-error' : undefined
                            "
                            id="email"
                            name="email"
                            type="email"
                            :value="breeder?.email ?? ''"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError id="email-error" :message="errors.email" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="herd_number"
                        >Numero cheptel</label
                    >
                    <input
                        :aria-invalid="Boolean(errors.herd_number)"
                        :aria-describedby="
                            errors.herd_number ? 'herd_number-error' : undefined
                        "
                        id="herd_number"
                        name="herd_number"
                        :value="breeder?.herd_number ?? ''"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                    />
                    <InputError
                        id="herd_number-error"
                        :message="errors.herd_number"
                    />
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="notes">Notes</label>
                    <textarea
                        :aria-invalid="Boolean(errors.notes)"
                        :aria-describedby="
                            errors.notes ? 'notes-error' : undefined
                        "
                        id="notes"
                        name="notes"
                        :value="breeder?.notes ?? ''"
                        rows="4"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                    ></textarea>
                    <InputError id="notes-error" :message="errors.notes" />
                </div>

                <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{
                            processing
                                ? 'Enregistrement...'
                                : isEdit
                                  ? 'Mettre a jour'
                                  : 'Creer'
                        }}
                    </button>
                    <a
                        :href="breedersIndex().url"
                        class="inline-flex justify-center rounded-lg border border-border px-4 py-2 text-sm font-medium hover:bg-accent"
                        >Annuler</a
                    >
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
