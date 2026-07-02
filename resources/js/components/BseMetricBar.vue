<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        value: number | null;
        thresholds: number[];
        unit?: string;
        label: string;
        comparisonValue?: number | null;
        comparisonLabel?: string;
        higherIsBetter?: boolean;
        decimals?: number;
    }>(),
    {
        unit: '%',
        comparisonValue: null,
        comparisonLabel: 'Ancien',
        higherIsBetter: false,
        decimals: 1,
    },
);

// Plot area constants
const PLOT_TOP = 8;
const PLOT_H = 76;
const PLOT_BOTTOM = PLOT_TOP + PLOT_H;
const BAR_X = 36;
const BAR_W = 28;
const LABEL_X = 34;

const displayMax = computed(() => {
    const maxThresh = props.thresholds.length > 0 ? Math.max(...props.thresholds) : 10;
    const v = props.value ?? 0;
    const comparison = props.comparisonValue ?? 0;
    return Math.max(v * 1.3, comparison * 1.3, maxThresh * 1.5, 0.01);
});

function yOf(v: number): number {
    const clamped = Math.max(0, Math.min(v, displayMax.value));
    return PLOT_BOTTOM - (clamped / displayMax.value) * PLOT_H;
}

const barY = computed(() => (props.value !== null ? yOf(props.value) : PLOT_BOTTOM));
const barH = computed(() => PLOT_BOTTOM - barY.value);
const hasComparison = computed(() => props.comparisonValue !== null && props.comparisonValue !== undefined);
const comparisonY = computed(() => (hasComparison.value ? yOf(props.comparisonValue as number) : PLOT_BOTTOM));

const fillColor = computed((): string => {
    const v = props.value;
    if (v === null) return '#94a3b8';
    const ts = props.thresholds;
    if (ts.length === 0) return '#94a3b8';

    if (props.higherIsBetter) {
        const lo = ts[0];
        const hi = ts.length >= 2 ? ts[1] : ts[0];
        if (v >= hi) return '#16a34a';
        if (v >= lo) return '#d97706';
        return '#dc2626';
    } else {
        const lo = ts[0];
        const hi = ts.length >= 2 ? ts[1] : Infinity;
        if (v < lo) return '#16a34a';
        if (v < hi) return '#d97706';
        return '#dc2626';
    }
});

const formattedValue = computed(() => {
    if (props.value === null || props.value === undefined) return '–';
    return props.value.toFixed(props.decimals) + (props.unit ? ' ' + props.unit : '');
});

const formattedComparisonValue = computed(() => {
    if (!hasComparison.value) return '';
    return (props.comparisonValue as number).toFixed(props.decimals) + (props.unit ? ' ' + props.unit : '');
});
</script>

<template>
    <div class="flex flex-col items-center gap-1">
        <svg viewBox="0 0 88 96" width="88" height="96" class="overflow-visible">
            <!-- Threshold dashed lines and labels -->
            <template v-for="(t, i) in thresholds" :key="i">
                <line
                    :x1="LABEL_X"
                    :y1="yOf(t)"
                    :x2="BAR_X + BAR_W + 4"
                    :y2="yOf(t)"
                    stroke="#9ca3af"
                    stroke-width="1"
                    stroke-dasharray="3,2"
                />
                <text :x="LABEL_X - 2" :y="yOf(t) + 3" text-anchor="end" font-size="7.5" fill="#6b7280">
                    {{ t }}{{ unit }}
                </text>
            </template>

            <!-- Base line -->
            <line :x1="LABEL_X" :y1="PLOT_BOTTOM" :x2="BAR_X + BAR_W + 4" :y2="PLOT_BOTTOM" stroke="#d1d5db" stroke-width="1" />

            <!-- Value bar -->
            <rect v-if="value !== null && barH > 0" :x="BAR_X" :y="barY" :width="BAR_W" :height="barH" :fill="fillColor" rx="2" />

            <!-- Previous report marker -->
            <template v-if="hasComparison">
                <line
                    :x1="BAR_X - 5"
                    :y1="comparisonY"
                    :x2="BAR_X + BAR_W + 5"
                    :y2="comparisonY"
                    stroke="#2563eb"
                    stroke-width="1.3"
                    stroke-dasharray="2,2"
                />
                <circle :cx="BAR_X + BAR_W + 7" :cy="comparisonY" r="2" fill="#2563eb" />
            </template>

            <!-- Value label above bar -->
            <text
                v-if="value !== null"
                :x="BAR_X + BAR_W / 2"
                :y="barY - 3"
                text-anchor="middle"
                font-size="8.5"
                font-weight="600"
                :fill="fillColor"
            >
                {{ formattedValue }}
            </text>

            <!-- Null state -->
            <text v-else :x="BAR_X + BAR_W / 2" :y="PLOT_BOTTOM - 20" text-anchor="middle" font-size="9" fill="#94a3b8"> – </text>
        </svg>
        <p class="max-w-[88px] text-center text-[11px] leading-tight text-muted-foreground">{{ label }}</p>
        <p v-if="hasComparison" class="max-w-[88px] text-center text-[10px] leading-tight text-blue-700">
            {{ comparisonLabel }}: {{ formattedComparisonValue }}
        </p>
    </div>
</template>
