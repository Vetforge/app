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
    const maxThresh =
        props.thresholds.length > 0 ? Math.max(...props.thresholds) : 10;
    const v = props.value ?? 0;
    const comparison = props.comparisonValue ?? 0;
    return Math.max(v * 1.3, comparison * 1.3, maxThresh * 1.5, 0.01);
});

function yOf(v: number): number {
    const clamped = Math.max(0, Math.min(v, displayMax.value));
    return PLOT_BOTTOM - (clamped / displayMax.value) * PLOT_H;
}

const thresholdMarkers = computed(() => {
    const markers = props.thresholds
        .map((value, index) => ({
            value,
            index,
            y: yOf(value),
            labelY: yOf(value),
        }))
        .sort((a, b) => a.y - b.y);

    for (let i = 1; i < markers.length; i++) {
        markers[i].labelY = Math.max(markers[i].y, markers[i - 1].labelY + 10);
    }
    const offset = markers.length
        ? markers.reduce((sum, marker) => sum + marker.labelY - marker.y, 0) /
          markers.length
        : 0;
    return markers
        .map((marker) => ({ ...marker, labelY: marker.labelY - offset }))
        .sort((a, b) => a.index - b.index);
});

const barY = computed(() =>
    props.value !== null ? yOf(props.value) : PLOT_BOTTOM,
);
const barH = computed(() => PLOT_BOTTOM - barY.value);
const hasComparison = computed(
    () => props.comparisonValue !== null && props.comparisonValue !== undefined,
);
const comparisonY = computed(() =>
    hasComparison.value ? yOf(props.comparisonValue as number) : PLOT_BOTTOM,
);

const fillColor = computed((): string => {
    const v = props.value;
    if (v === null) return 'var(--muted-foreground)';
    const ts = props.thresholds;
    if (ts.length === 0) return 'var(--muted-foreground)';

    if (props.higherIsBetter) {
        const lo = ts[0];
        const hi = ts.length >= 2 ? ts[1] : ts[0];
        if (v >= hi) return 'var(--metric-good)';
        if (v >= lo) return 'var(--metric-warning)';
        return 'var(--metric-bad)';
    } else {
        const lo = ts[0];
        const hi = ts.length >= 2 ? ts[1] : Infinity;
        if (v < lo) return 'var(--metric-good)';
        if (v < hi) return 'var(--metric-warning)';
        return 'var(--metric-bad)';
    }
});

const formattedValue = computed(() => {
    if (props.value === null || props.value === undefined) return '–';
    return (
        props.value.toFixed(props.decimals) +
        (props.unit ? ' ' + props.unit : '')
    );
});

const formattedComparisonValue = computed(() => {
    if (!hasComparison.value) return '';
    return (
        (props.comparisonValue as number).toFixed(props.decimals) +
        (props.unit ? ' ' + props.unit : '')
    );
});
</script>

<template>
    <figure class="flex w-[88px] shrink-0 flex-col items-center gap-1">
        <svg
            role="img"
            :aria-label="`${label} : ${formattedValue}${hasComparison ? `, ${comparisonLabel} : ${formattedComparisonValue}` : ''}`"
            viewBox="0 0 88 96"
            width="88"
            height="96"
            class="overflow-visible"
        >
            <!-- Threshold dashed lines and labels -->
            <template v-for="marker in thresholdMarkers" :key="marker.index">
                <line
                    :x1="LABEL_X"
                    :y1="marker.y"
                    :x2="BAR_X + BAR_W + 4"
                    :y2="marker.y"
                    stroke="var(--muted-foreground)"
                    stroke-width="1"
                    stroke-dasharray="3,2"
                />
                <line
                    v-if="Math.abs(marker.labelY - marker.y) > 0.5"
                    :x1="LABEL_X - 4"
                    :y1="marker.labelY"
                    :x2="LABEL_X"
                    :y2="marker.y"
                    stroke="var(--muted-foreground)"
                    stroke-width="0.75"
                />
                <text
                    :x="LABEL_X - 6"
                    :y="marker.labelY + 3"
                    text-anchor="end"
                    font-size="7.5"
                    fill="var(--muted-foreground)"
                >
                    {{ marker.value }}{{ unit }}
                </text>
            </template>

            <!-- Base line -->
            <line
                :x1="LABEL_X"
                :y1="PLOT_BOTTOM"
                :x2="BAR_X + BAR_W + 4"
                :y2="PLOT_BOTTOM"
                stroke="var(--border)"
                stroke-width="1"
            />

            <!-- Value bar -->
            <rect
                v-if="value !== null && barH > 0"
                :x="BAR_X"
                :y="barY"
                :width="BAR_W"
                :height="barH"
                :fill="fillColor"
                rx="2"
            />

            <!-- Previous report marker -->
            <template v-if="hasComparison">
                <line
                    :x1="BAR_X - 5"
                    :y1="comparisonY"
                    :x2="BAR_X + BAR_W + 5"
                    :y2="comparisonY"
                    stroke="var(--comparison)"
                    stroke-width="1.3"
                    stroke-dasharray="2,2"
                />
                <circle
                    :cx="BAR_X + BAR_W + 7"
                    :cy="comparisonY"
                    r="2"
                    fill="var(--comparison)"
                />
            </template>
        </svg>
        <figcaption class="flex w-full flex-col items-center gap-1 text-center">
            <p
                class="text-sm leading-tight font-semibold tabular-nums"
                :style="{ color: fillColor }"
            >
                {{ formattedValue }}
            </p>
            <p class="text-[11px] leading-tight text-muted-foreground">
                {{ label }}
            </p>
            <p
                v-if="hasComparison"
                class="text-[10px] leading-tight text-comparison"
            >
                {{ comparisonLabel }}: {{ formattedComparisonValue }}
            </p>
        </figcaption>
    </figure>
</template>
