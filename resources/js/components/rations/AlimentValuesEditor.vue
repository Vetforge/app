<script setup lang="ts">
import { alimentEditableFieldGroups } from '@/components/rations/alimentEditableFields';
import type { Aliment } from '@/components/rations/types';

const aliment = defineModel<Aliment>('aliment', { required: true });
</script>

<template>
    <div class="space-y-5">
        <section
            v-for="group in alimentEditableFieldGroups"
            :key="group.key"
            class="space-y-3"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {{ group.title }}
            </p>

            <div
                class="grid grid-cols-1 gap-x-6 gap-y-2"
                :class="group.columnsClass"
            >
                <label
                    v-for="field in group.fields"
                    :key="field.key"
                    class="flex flex-col gap-0.5 text-xs"
                >
                    <span class="font-medium text-muted-foreground">{{ field.label }}</span>

                    <input
                        v-if="field.type === 'number'"
                        v-model.number="aliment[field.key]"
                        :name="field.key"
                        type="number"
                        :step="field.step"
                        class="rounded border border-border bg-background px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                    <input
                        v-else
                        v-model="aliment[field.key]"
                        :name="field.key"
                        type="text"
                        :required="field.required"
                        class="rounded border border-border bg-background px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </label>
            </div>
        </section>
    </div>
</template>
