<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
</script>

<template>
    <AuthBase
        title="Créer un compte"
        description="Renseignez vos informations pour créer votre compte"
    >
        <Head title="Inscription" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Nom</Label>
                    <Input
                        :aria-invalid="Boolean(errors.name)"
                        :aria-describedby="
                            errors.name ? 'name-error' : undefined
                        "
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Nom complet"
                    />
                    <InputError id="name-error" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Adresse e-mail</Label>
                    <Input
                        :aria-invalid="Boolean(errors.email)"
                        :aria-describedby="
                            errors.email ? 'email-error' : undefined
                        "
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError id="email-error" :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Mot de passe</Label>
                    <Input
                        :aria-invalid="Boolean(errors.password)"
                        :aria-describedby="
                            errors.password ? 'password-error' : undefined
                        "
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Mot de passe"
                    />
                    <InputError
                        id="password-error"
                        :message="errors.password"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation"
                        >Confirmer le mot de passe</Label
                    >
                    <Input
                        :aria-invalid="Boolean(errors.password_confirmation)"
                        :aria-describedby="
                            errors.password_confirmation
                                ? 'password_confirmation-error'
                                : undefined
                        "
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirmer le mot de passe"
                    />
                    <InputError
                        id="password_confirmation-error"
                        :message="errors.password_confirmation"
                    />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Créer le compte
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Vous avez déjà un compte ?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                    >Se connecter</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
