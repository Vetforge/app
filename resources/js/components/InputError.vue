<script setup lang="ts">
import { formatValidationError } from '@/lib/validationMessages';
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message" aria-live="polite" aria-atomic="true">
        <p class="text-sm text-destructive">
            {{ formatValidationError(message) }}
        </p>
    </div>
</template>
