<script setup lang="ts">
import { alimentEditableFieldGroups } from '@/components/rations/alimentEditableFields';
import type { Aliment } from '@/components/rations/types';
import { ALIMENT_TYPES } from '@/types/aliment';

const aliment = defineModel<Aliment>('aliment', { required: true });
</script>

<template>
    <div class="space-y-5">
        <section
            v-for="group in alimentEditableFieldGroups"
            :key="group.key"
            class="space-y-3"
        >
            <p
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
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
                    <span class="font-medium text-muted-foreground">{{
                        field.label
                    }}</span>

                    <select
                        v-if="field.key === 'type'"
                        v-model="aliment.type"
                        name="type"
                        required
                        class="rounded border border-border bg-background px-2 py-1 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    >
                        <option value="">Sélectionner</option>
                        <option
                            v-for="type in ALIMENT_TYPES"
                            :key="type"
                            :value="type"
                        >
                            {{ type }}
                        </option>
                    </select>
                    <input
                        v-else-if="field.type === 'number'"
                        v-model.number="aliment[field.key]"
                        :name="field.key"
                        type="number"
                        :step="field.step"
                        class="rounded border border-border bg-background px-2 py-1 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                    <input
                        v-else
                        v-model="aliment[field.key]"
                        :name="field.key"
                        type="text"
                        :required="field.required"
                        class="rounded border border-border bg-background px-2 py-1 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                </label>
            </div>
        </section>
    </div>
</template>
