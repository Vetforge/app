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
    { title: isEdit ? 'Modifier' : 'Nouvel eleveur', href: '#' },
];
</script>

<template>
    <Head :title="isEdit ? 'Modifier eleveur' : 'Nouvel eleveur'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full min-w-0 max-w-2xl p-4 sm:p-6">
            <h1 class="mb-6 text-2xl font-bold text-foreground">
                {{ isEdit ? 'Modifier eleveur' : 'Nouvel eleveur' }}
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
                        id="name"
                        name="name"
                        :value="breeder?.name ?? ''"
                        required
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="address"
                        >Adresse</label
                    >
                    <input
                        id="address"
                        name="address"
                        :value="breeder?.address ?? ''"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.address" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="postal_code"
                            >Code postal</label
                        >
                        <input
                            id="postal_code"
                            name="postal_code"
                            :value="breeder?.postal_code ?? ''"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="errors.postal_code" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="city"
                            >Ville</label
                        >
                        <input
                            id="city"
                            name="city"
                            :value="breeder?.city ?? ''"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="errors.city" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="phone"
                            >Telephone</label
                        >
                        <input
                            id="phone"
                            name="phone"
                            :value="breeder?.phone ?? ''"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="email"
                            >Email</label
                        >
                        <input
                            id="email"
                            name="email"
                            type="email"
                            :value="breeder?.email ?? ''"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="errors.email" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="herd_number"
                        >Numero cheptel</label
                    >
                    <input
                        id="herd_number"
                        name="herd_number"
                        :value="breeder?.herd_number ?? ''"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.herd_number" />
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="notes">Notes</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm"
                    >{{ breeder?.notes ?? '' }}</textarea>
                    <InputError :message="errors.notes" />
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
