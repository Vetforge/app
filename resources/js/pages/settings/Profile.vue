<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Paramètres du profil',
        href: edit(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Paramètres du profil" />

        <h1 class="sr-only">Paramètres du profil</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Informations du profil"
                    description="Mettez à jour votre nom et votre adresse e-mail"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nom</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Nom complet"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Adresse e-mail</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Adresse e-mail"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Votre adresse e-mail n'est pas vérifiée.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Cliquez ici pour renvoyer l’e-mail de
                                vérification.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            Un nouveau lien de vérification a été envoyé à votre
                            adresse e-mail.
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-border pt-6">
                        <div>
                            <h2 class="text-base font-medium">Entête PDF</h2>
                            <p class="text-sm text-muted-foreground">
                                Coordonnées de la clinique
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="clinic_profile_name"
                                >Nom de la clinique</Label
                            >
                            <Input
                                id="clinic_profile_name"
                                class="mt-1 block w-full"
                                name="clinic_profile[name]"
                                :default-value="user.clinic_profile?.name ?? ''"
                                autocomplete="organization"
                                placeholder="Clinique vétérinaire"
                            />
                            <InputError
                                class="mt-2"
                                :message="errors['clinic_profile.name']"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="clinic_profile_address">Adresse</Label>
                            <Input
                                id="clinic_profile_address"
                                class="mt-1 block w-full"
                                name="clinic_profile[address]"
                                :default-value="
                                    user.clinic_profile?.address ?? ''
                                "
                                autocomplete="street-address"
                                placeholder="Adresse"
                            />
                            <InputError
                                class="mt-2"
                                :message="errors['clinic_profile.address']"
                            />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="clinic_profile_postal_code"
                                    >Code postal</Label
                                >
                                <Input
                                    id="clinic_profile_postal_code"
                                    class="mt-1 block w-full"
                                    name="clinic_profile[postal_code]"
                                    :default-value="
                                        user.clinic_profile?.postal_code ?? ''
                                    "
                                    autocomplete="postal-code"
                                    placeholder="Code postal"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="
                                        errors['clinic_profile.postal_code']
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="clinic_profile_city">Ville</Label>
                                <Input
                                    id="clinic_profile_city"
                                    class="mt-1 block w-full"
                                    name="clinic_profile[city]"
                                    :default-value="
                                        user.clinic_profile?.city ?? ''
                                    "
                                    autocomplete="address-level2"
                                    placeholder="Ville"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="errors['clinic_profile.city']"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="clinic_profile_phone"
                                    >Téléphone</Label
                                >
                                <Input
                                    id="clinic_profile_phone"
                                    class="mt-1 block w-full"
                                    name="clinic_profile[phone]"
                                    :default-value="
                                        user.clinic_profile?.phone ?? ''
                                    "
                                    autocomplete="tel"
                                    placeholder="Téléphone"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="errors['clinic_profile.phone']"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="clinic_profile_email"
                                    >E-mail clinique</Label
                                >
                                <Input
                                    id="clinic_profile_email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    name="clinic_profile[email]"
                                    :default-value="
                                        user.clinic_profile?.email ?? ''
                                    "
                                    autocomplete="email"
                                    placeholder="contact@clinique.fr"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="errors['clinic_profile.email']"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Enregistrer</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Enregistré.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
