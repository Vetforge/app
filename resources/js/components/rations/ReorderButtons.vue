<script setup lang="ts">
import { ChevronDown, ChevronUp } from 'lucide-vue-next';

defineProps<{
    label: string;
    index: number;
    count: number;
    disabled?: boolean;
}>();
defineEmits<{ move: [direction: -1 | 1] }>();
</script>

<template>
    <div class="flex shrink-0 gap-1">
        <button
            type="button"
            :aria-label="`Monter ${label}`"
            :title="`Monter ${label}`"
            :disabled="disabled || index === 0"
            @click="$emit('move', -1)"
            class="inline-flex size-11 items-center justify-center rounded-lg border border-border hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
        >
            <ChevronUp class="size-4" />
        </button>
        <button
            type="button"
            :aria-label="`Descendre ${label}`"
            :title="`Descendre ${label}`"
            :disabled="disabled || index === count - 1"
            @click="$emit('move', 1)"
            class="inline-flex size-11 items-center justify-center rounded-lg border border-border hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
        >
            <ChevronDown class="size-4" />
        </button>
    </div>
</template>
