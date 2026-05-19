import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function clonePlain<T>(value: T): T {
    if (value === null || value === undefined) {
        return value;
    }

    return JSON.parse(JSON.stringify(value)) as T;
}

export function roundNumber(value: number | null | undefined, maxDecimals = 2): number | null | undefined {
    if (value === undefined || value === null || Number.isNaN(value)) {
        return value;
    }

    const decimals = Math.min(Math.max(Math.trunc(maxDecimals), 0), 2);
    const rounded = Number(value.toFixed(decimals));

    return Object.is(rounded, -0) ? 0 : rounded;
}

export function formatNumber(value: number | null | undefined, maxDecimals = 2): string {
    const rounded = roundNumber(value, maxDecimals);

    if (rounded === undefined || rounded === null) {
        return '–';
    }

    return rounded.toString();
}

export function formatNumberInput(value: number | null | undefined, maxDecimals = 2): string {
    const rounded = roundNumber(value, maxDecimals);

    if (rounded === undefined || rounded === null) {
        return '';
    }

    return rounded.toString();
}
