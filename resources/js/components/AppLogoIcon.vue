<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 42 42"
        :class="className"
        v-bind="$attrs"
    >
        <path
            d="M21 7A14 14 0 1 0 35 21"
            fill="none"
            stroke="#4CAF50"
            stroke-width="2.5"
            stroke-linecap="round"
        />

        <path
            d="M21 34V21"
            fill="none"
            stroke="#2E7D32"
            stroke-width="2.5"
            stroke-linecap="round"
        />

        <path d="M21 28C16 28 13 23 13 23C13 23 16 18 21 18" fill="#81C784" />

        <path d="M21 23C27 23 31 16 31 16C31 16 26 12 21 12" fill="#4CAF50" />

        <circle cx="21" cy="21" r="1.2" fill="#1B5E20" />
    </svg>
</template>
