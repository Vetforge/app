<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { update } from '@/routes/password';

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <AuthLayout
        title="Réinitialiser le mot de passe"
        description="Veuillez saisir votre nouveau mot de passe ci-dessous"
    >
        <Head title="Réinitialiser le mot de passe" />

        <Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Adresse e-mail</Label>
                    <Input
                        :aria-invalid="Boolean(errors.email)"
                        :aria-describedby="
                            errors.email ? 'email-error' : undefined
                        "
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        v-model="inputEmail"
                        class="mt-1 block w-full"
                        readonly
                    />
                    <InputError
                        id="email-error"
                        :message="errors.email"
                        class="mt-2"
                    />
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
                        name="password"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        autofocus
                        placeholder="Mot de passe"
                    />
                    <InputError
                        id="password-error"
                        :message="errors.password"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        Confirmer le mot de passe
                    </Label>
                    <Input
                        :aria-invalid="Boolean(errors.password_confirmation)"
                        :aria-describedby="
                            errors.password_confirmation
                                ? 'password_confirmation-error'
                                : undefined
                        "
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        placeholder="Confirmer le mot de passe"
                    />
                    <InputError
                        id="password_confirmation-error"
                        :message="errors.password_confirmation"
                    />
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="processing"
                    data-test="reset-password-button"
                >
                    <Spinner v-if="processing" />
                    Réinitialiser le mot de passe
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
