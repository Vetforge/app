<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-white shadow-sm group-data-[collapsible=icon]:size-10 group-data-[collapsible=icon]:shadow-none"
    >
        <AppLogoIcon class="size-5 group-data-[collapsible=icon]:size-7" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
        <span class="mb-0.5 truncate leading-tight font-semibold">Nexa</span>
    </div>
</template>
