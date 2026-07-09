<script setup lang="ts">
import { computed } from 'vue';
import { pdf as rationPdf } from '@/actions/App/Http/Controllers/RationController';
import type {
    Plan,
    Ration,
    RationNormeDefinition,
    RationNormeValue,
    RationNormesPayload,
    Resultats,
} from '@/components/rations/types';
import { formatNumber } from '@/lib/utils';

type StatusTone = 'ok' | 'watch' | 'alert' | 'neutral';

interface DetailRow {
    metricKey?: string;
    label: string;
    value: number | undefined;
    unit?: string;
    decimals?: number;
    min?: number;
    max?: number;
    goodAbove?: number;
    goodBelow?: number;
}

interface ComparisonRow {
    label: string;
    apport: number | undefined;
    besoin: number | undefined;
    unit: string;
    decimals?: number;
}

interface BalanceRow {
    label: string;
    apport: number | undefined;
    besoin: number | undefined;
    bilan: number | undefined;
    decimals?: number;
    besoinSuffix?: string;
}

interface TechnicalPanel {
    label: string;
    valueLabel: string;
    note: string;
    percent: number;
    status: StatusTone;
}

interface InsightItem {
    label: string;
    valueLabel: string;
    note: string;
    status: StatusTone;
}

interface CircleMetric {
    label: string;
    valueLabel: string;
    note: string;
    status: StatusTone;
}

const props = defineProps<{
    plan: Plan;
    ration: Ration;
    resultats: Resultats;
    iterations_volonte: number;
    normes: RationNormesPayload;
}>();

function fmt(val: number | undefined, decimals = 1): string {
    return formatNumber(val, decimals);
}

function fmtNorm(val: number | null | undefined, decimals = 1): string {
    return formatNumber(val, decimals).replace('.', ',');
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

function getNormeDefinition(key: string): RationNormeDefinition | undefined {
    return props.normes.editable.find((definition) => definition.key === key);
}

function getActiveNorme(key: string): RationNormeValue {
    return props.normes.active[key] ?? { min: null, max: null };
}

function metricThresholds(key: string): Pick<DetailRow, 'min' | 'max'> {
    const norme = getActiveNorme(key);

    return {
        min: norme.min ?? undefined,
        max: norme.max ?? undefined,
    };
}

function metricLabel(key: string): string {
    const definition = getNormeDefinition(key);

    if (! definition) {
        return key;
    }

    const norme = getActiveNorme(key);
    const parts = [norme.min, norme.max]
        .filter((value): value is number => value !== null && value !== undefined)
        .map((value) => fmtNorm(value, definition.decimals));

    return parts.length > 0 ? `${definition.label} (${parts.join(' - ')})` : definition.label;
}

function metricNormNote(key: string): string {
    const definition = getNormeDefinition(key);

    if (! definition) {
        return '';
    }

    const norme = getActiveNorme(key);

    if (norme.min !== null && norme.max !== null) {
        return `Cible ${fmtNorm(norme.min, definition.decimals)} à ${fmtNorm(norme.max, definition.decimals)}${definition.unit ? ` ${definition.unit}` : ''}`;
    }

    if (norme.min !== null) {
        return `Seuil ${fmtNorm(norme.min, definition.decimals)}${definition.unit ? ` ${definition.unit}` : ''}`;
    }

    if (norme.max !== null) {
        return `Seuil ${fmtNorm(norme.max, definition.decimals)}${definition.unit ? ` ${definition.unit}` : ''}`;
    }

    return definition.label;
}

function safeRatio(apport: number | undefined, besoin: number | undefined): number | undefined {
    if (apport === undefined || besoin === undefined || besoin === null || besoin <= 0) {
        return undefined;
    }

    return apport / besoin;
}

function coverageStatus(ratio: number | undefined): StatusTone {
    if (ratio === undefined) {
        return 'neutral';
    }

    if (ratio < 0.95) {
        return 'alert';
    }

    if (ratio < 1 || ratio > 1.15) {
        return 'watch';
    }

    return 'ok';
}

function goalStatus(limitant: number | undefined, objectif: number | undefined): StatusTone {
    if (limitant === undefined || objectif === undefined || objectif === null || objectif <= 0) {
        return 'neutral';
    }

    if (limitant < objectif * 0.95) {
        return 'alert';
    }

    if (limitant < objectif) {
        return 'watch';
    }

    return 'ok';
}

function rangeStatus(row: DetailRow): StatusTone {
    if (row.value === undefined || row.value === null || Number.isNaN(row.value)) {
        return 'neutral';
    }

    if (row.min !== undefined && row.value < row.min) {
        return 'alert';
    }

    if (row.max !== undefined && row.value > row.max) {
        return 'alert';
    }

    if (row.goodAbove !== undefined && row.value < row.goodAbove) {
        return 'alert';
    }

    if (row.goodBelow !== undefined && row.value > row.goodBelow) {
        return 'alert';
    }

    return row.min !== undefined || row.max !== undefined || row.goodAbove !== undefined || row.goodBelow !== undefined ? 'ok' : 'neutral';
}

function detailStatus(row: DetailRow): StatusTone {
    return rangeStatus(row);
}

function healthDetailStatus(row: DetailRow): StatusTone {
    if (row.value === undefined || row.value === null || Number.isNaN(row.value)) {
        return 'neutral';
    }

    if (row.metricKey === 'be') {
        if (row.max !== undefined && row.value > row.max) {
            return 'ok';
        }

        if (row.min !== undefined && row.value > row.min) {
            return 'watch';
        }

        return 'alert';
    }

    if (row.metricKey === 'amid_ru' || row.metricKey === 'pco_percent' || row.metricKey === 'ira') {
        if (row.min !== undefined && row.value < row.min) {
            return 'ok';
        }

        if (row.max !== undefined && row.value < row.max) {
            return 'watch';
        }

        return 'alert';
    }

    if (row.metricKey === 'ndf_total') {
        if (row.max !== undefined && row.value > row.max) {
            return 'ok';
        }

        if (row.min !== undefined && row.value > row.min) {
            return 'watch';
        }

        return 'alert';
    }

    if (row.metricKey === 'ph_ruminal') {
        return row.min !== undefined && row.value < row.min ? 'alert' : 'ok';
    }

    return detailStatus(row);
}

function fiberDetailStatus(row: DetailRow): StatusTone {
    if (row.value === undefined || row.value === null || Number.isNaN(row.value)) {
        return 'neutral';
    }

    if (row.metricKey === 'cb_par_kg_ms') {
        return row.min !== undefined && row.value < row.min ? 'alert' : 'ok';
    }

    if (row.label === 'Apport en ADF' || row.label === 'Apport en NDF' || row.label === 'Apport en MS') {
        return 'neutral';
    }

    if (row.metricKey === 'ndf_total') {
        if (row.max !== undefined && row.value > row.max) {
            return 'ok';
        }

        if (row.min !== undefined && row.value > row.min) {
            return 'watch';
        }

        return 'alert';
    }

    return detailStatus(row);
}

function comparisonStatus(row: ComparisonRow): StatusTone {
    const ratio = safeRatio(row.apport, row.besoin);

    if (ratio === undefined) {
        return 'neutral';
    }

    if (ratio < 0.95) {
        return 'alert';
    }

    if (ratio > 1.4) {
        return 'watch';
    }

    return 'ok';
}

function balanceStatus(row: BalanceRow): StatusTone {
    const ratio = safeRatio(row.apport, row.besoin);

    if (ratio === undefined) {
        return 'neutral';
    }

    if (ratio <= 0.9) {
        return 'alert';
    }

    if (ratio >= 1.1) {
        return 'watch';
    }

    return 'ok';
}

function statusLabel(status: StatusTone): string {
    switch (status) {
        case 'ok':
            return 'équilibré';
        case 'watch':
            return 'à surveiller';
        case 'alert':
            return 'prioritaire';
        default:
            return 'info';
    }
}

function isIssue(status: StatusTone): boolean {
    return status === 'alert' || status === 'watch';
}

function tonePanelClass(status: StatusTone): string {
    switch (status) {
        case 'ok':
            return 'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-900/70 dark:bg-emerald-950/20';
        case 'watch':
            return 'border-blue-200/80 bg-blue-50/70 dark:border-blue-900/70 dark:bg-blue-950/20';
        case 'alert':
            return 'border-rose-200/80 bg-rose-50/70 dark:border-rose-900/70 dark:bg-rose-950/20';
        default:
            return 'border-border bg-background/80';
    }
}

function toneChipClass(status: StatusTone): string {
    switch (status) {
        case 'ok':
            return 'border-emerald-300/70 bg-emerald-100/80 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300';
        case 'watch':
            return 'border-blue-300/70 bg-blue-100/80 text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300';
        case 'alert':
            return 'border-rose-300/70 bg-rose-100/80 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300';
        default:
            return 'border-border bg-muted/60 text-muted-foreground';
    }
}

function toneTextClass(status: StatusTone): string {
    switch (status) {
        case 'ok':
            return 'text-emerald-700 dark:text-emerald-300';
        case 'watch':
            return 'text-blue-700 dark:text-blue-300';
        case 'alert':
            return 'text-rose-700 dark:text-rose-300';
        default:
            return 'text-foreground';
    }
}

function toneBarClass(status: StatusTone): string {
    switch (status) {
        case 'ok':
            return 'bg-emerald-500';
        case 'watch':
            return 'bg-blue-500';
        case 'alert':
            return 'bg-rose-500';
        default:
            return 'bg-sky-500';
    }
}

function toneDotClass(status: StatusTone): string {
    switch (status) {
        case 'ok':
            return 'bg-emerald-500';
        case 'watch':
            return 'bg-blue-500';
        case 'alert':
            return 'bg-rose-500';
        default:
            return 'bg-slate-400';
    }
}

function toneCircleClass(status: StatusTone): string {
    switch (status) {
        case 'ok':
            return 'border-emerald-400/80 bg-emerald-100/40 dark:border-emerald-700 dark:bg-emerald-950/20';
        case 'watch':
            return 'border-blue-400/80 bg-blue-100/40 dark:border-blue-700 dark:bg-blue-950/20';
        case 'alert':
            return 'border-rose-400/80 bg-rose-100/40 dark:border-rose-700 dark:bg-rose-950/20';
        default:
            return 'border-sky-300/80 bg-sky-100/30 dark:border-sky-800 dark:bg-sky-950/20';
    }
}

function tableRowClass(status: StatusTone): string {
    switch (status) {
        case 'ok':
            return 'bg-emerald-50/35 dark:bg-emerald-950/10';
        case 'watch':
            return 'bg-blue-50/45 dark:bg-blue-950/10';
        case 'alert':
            return 'bg-rose-50/45 dark:bg-rose-950/10';
        default:
            return '';
    }
}

function aggregateStatus(statuses: StatusTone[]): StatusTone {
    if (statuses.includes('alert')) {
        return 'alert';
    }

    if (statuses.includes('watch')) {
        return 'watch';
    }

    if (statuses.includes('ok')) {
        return 'ok';
    }

    return 'neutral';
}

function visibleDetailRows(rows: DetailRow[]): DetailRow[] {
    return rows;
}

function visibleComparisonRows(rows: ComparisonRow[]): ComparisonRow[] {
    return rows;
}

function bilanClass(val: number | undefined): string {
    if (val === undefined || val === null) {
        return '';
    }

    if (val > 0.5) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (val < -0.5) {
        return 'text-rose-600 dark:text-rose-400';
    }

    return 'text-muted-foreground';
}

function bilanSign(val: number | undefined, decimals = 1): string {
    if (val === undefined || val === null) {
        return '–';
    }

    return val >= 0 ? `+${fmt(val, decimals)}` : fmt(val, decimals);
}

const laitObjectif = computed(() => props.ration.lait_objectif ?? 0);

const ufApport = computed(() => props.resultats.apports.ufl ?? 0);
const ufBesoin = computed(() => props.resultats.besoins.uf_total ?? 0);
const ufRatio = computed(() => safeRatio(ufApport.value, ufBesoin.value));

const proteinApport = computed(() => {
    if (props.resultats.inra === '2007') {
        return Math.min(props.resultats.apports.pdie ?? 0, props.resultats.apports.pdin ?? 0);
    }

    return props.resultats.apports.pdi ?? props.resultats.apports.pdie ?? 0;
});

const proteinBesoin = computed(() => props.resultats.besoins.pdi_total ?? 0);
const proteinRatio = computed(() => safeRatio(proteinApport.value, proteinBesoin.value));

const ueApport = computed(() => props.resultats.apports.ue ?? 0);
const ueBesoin = computed(() => props.resultats.besoins.ci ?? 0);
const ueRatio = computed(() => safeRatio(ueApport.value, ueBesoin.value));

const laitPermisRows = computed(() => {
    if (props.resultats.inra === '2007') {
        return [
            { label: 'Par UFL', value: props.resultats.impacts.lait_par_ufl, decimals: 2, unit: 'kg/j' },
            { label: 'Par PDIE', value: props.resultats.impacts.lait_par_pdie, decimals: 2, unit: 'kg/j' },
            { label: 'Par PDIN', value: props.resultats.impacts.lait_par_pdin, decimals: 2, unit: 'kg/j' },
        ];
    }

    const rows = [
        { label: 'Par UFL', value: props.resultats.impacts.lait_par_ufl, decimals: 2, unit: 'kg/j' },
        { label: 'Par PDI', value: props.resultats.impacts.lait_par_pdi, decimals: 2, unit: 'kg/j' },
        { label: 'Lait limitant', value: props.resultats.impacts.lait_limitant, decimals: 2, unit: 'kg/j' },
    ];

    if (props.resultats.impacts.production_lait_attendue !== undefined) {
        rows.push({ label: 'Production attendue', value: props.resultats.impacts.production_lait_attendue, decimals: 2, unit: 'kg/j' });
    }

    return rows;
});

const productionLaitAttendue = computed(() => props.resultats.impacts.production_lait_attendue);
const laitLimitant = computed(() => {
    const explicitLimit = props.resultats.impacts.lait_limitant;

    if (explicitLimit !== undefined && explicitLimit !== null) {
        return explicitLimit;
    }

    const candidates = laitPermisRows.value
        .map((row) => row.value)
        .filter((value): value is number => value !== undefined && value !== null);

    return candidates.length > 0 ? Math.min(...candidates) : 0;
});

const laitComparable = computed(() => productionLaitAttendue.value ?? laitLimitant.value);
const laitDelta = computed(() => (laitObjectif.value > 0 ? laitComparable.value - laitObjectif.value : undefined));
const laitStatus = computed(() => goalStatus(laitComparable.value, laitObjectif.value));

const limitingMilkSource = computed(() => {
    const candidates = laitPermisRows.value
        .filter((row) => row.label !== 'Lait limitant' && row.label !== 'Production attendue')
        .filter((row) => row.value !== undefined && row.value !== null)
        .sort((first, second) => (first.value ?? Number.POSITIVE_INFINITY) - (second.value ?? Number.POSITIVE_INFINITY));

    return candidates[0]?.label ?? 'Analyse indisponible';
});

const proteinsRows = computed<DetailRow[]>(() => {
    if (props.resultats.inra !== '2018') {
        return [];
    }

    const effPdi = props.resultats.indicateurs?.eff_pdi;

    return [
        { label: 'Apport PDI', value: props.resultats.apports.pdi, unit: 'g/j', decimals: 0 },
        { label: 'Besoin PDI', value: props.resultats.besoins.pdi_total, unit: 'g/j', decimals: 0 },
        { label: 'PDI', value: props.resultats.indicateurs?.pdi_par_kg_ms, unit: 'g/kg MS', decimals: 2 },
        { metricKey: 'eff_pdi', label: metricLabel('eff_pdi'), value: effPdi !== undefined ? effPdi * 100 : undefined, unit: '%', decimals: 0, ...metricThresholds('eff_pdi') },
        { metricKey: 'bpr', label: metricLabel('bpr'), value: props.resultats.indicateurs?.bpr, unit: 'g/kg MS', decimals: 2, ...metricThresholds('bpr') },
        { label: 'Lait permis par les PDI', value: props.resultats.impacts.lait_par_pdi, unit: 'kg/j', decimals: 2 },
        { label: 'Production laitière attendue', value: props.resultats.impacts.production_lait_attendue, unit: 'kg/j', decimals: 2 },
        { label: 'Azote urinaire', value: props.resultats.indicateurs?.azote_urinaire, unit: 'g/j', decimals: 0 },
        { label: 'Azote fecale', value: props.resultats.indicateurs?.azote_fecale, unit: 'g/j', decimals: 0 },
    ];
});

const healthRows = computed<DetailRow[]>(() => {
    if (props.resultats.inra !== '2018') {
        return [];
    }

    return [
        { metricKey: 'be', label: metricLabel('be'), value: props.resultats.indicateurs?.be, unit: 'mEq/kg MS', decimals: 0, ...metricThresholds('be') },
        { label: 'MOD des concentrés (proxy interne)', value: props.resultats.indicateurs?.mod_concentre, unit: 'g/kg MS', decimals: 0 },
        { metricKey: 'amid_ru', label: metricLabel('amid_ru'), value: props.resultats.indicateurs?.amid_ru, unit: 'g/kg MS', decimals: 0, ...metricThresholds('amid_ru') },
        { metricKey: 'pco_percent', label: metricLabel('pco_percent'), value: props.resultats.indicateurs?.pco_percent, unit: '% MS', decimals: 0, ...metricThresholds('pco_percent') },
        { label: 'NDF des fourrages (proxy NDFfo)', value: props.resultats.indicateurs?.ndf_fourrages, unit: 'g/kg MS', decimals: 0 },
        { metricKey: 'ndf_total', label: metricLabel('ndf_total'), value: props.resultats.indicateurs?.ndf_total, unit: 'g/kg MS', decimals: 0, ...metricThresholds('ndf_total') },
        { metricKey: 'ira', label: metricLabel('ira'), value: props.resultats.indicateurs?.ira, decimals: 2, ...metricThresholds('ira') },
        { metricKey: 'ph_ruminal', label: metricLabel('ph_ruminal'), value: props.resultats.indicateurs?.ph_ruminal, decimals: 2, ...metricThresholds('ph_ruminal') },
    ];
});

const fiberRows = computed<DetailRow[]>(() => {
    if (props.resultats.inra !== '2018') {
        return [];
    }

    return [
        { label: 'Apport en MS', value: props.resultats.apports.ms, unit: 'kg', decimals: 2 },
        { metricKey: 'cb_par_kg_ms', label: metricLabel('cb_par_kg_ms'), value: props.resultats.indicateurs?.cb_par_kg_ms, unit: 'g/kg MS', decimals: 0, ...metricThresholds('cb_par_kg_ms') },
        { label: 'Apport en ADF', value: props.resultats.indicateurs?.adf_par_kg_ms, unit: 'g/kg MS', decimals: 0 },
        { metricKey: 'ndf_total', label: metricLabel('ndf_total'), value: props.resultats.indicateurs?.ndf_total, unit: 'g/kg MS', decimals: 0, ...metricThresholds('ndf_total') },
        { label: 'NDF des fourrages (proxy NDFfo)', value: props.resultats.indicateurs?.ndf_fourrages, unit: 'g/kg MS', decimals: 0 },
    ];
});

const energyRows = computed<DetailRow[]>(() => {
    if (props.resultats.inra !== '2018') {
        return [];
    }

    const rows: DetailRow[] = [
        { label: 'Apport UFL', value: props.resultats.apports.ufl, unit: 'UFL/j', decimals: 2 },
        { label: 'Besoin UFL', value: props.resultats.besoins.uf_total, unit: 'UFL/j', decimals: 2 },
        { label: 'Apport UFL/kg MS', value: props.resultats.indicateurs?.ufl_par_kg_ms, unit: 'UFL/kg MS', decimals: 2 },
    ];

    // Le bilan UFL de production (régression bovine) n'est présent que pour les bovins reproducteurs.
    if (props.resultats.impacts.bil_ufl !== undefined) {
        rows.push({ metricKey: 'bil_ufl', label: metricLabel('bil_ufl'), value: props.resultats.impacts.bil_ufl, unit: 'UFL/j', decimals: 2, ...metricThresholds('bil_ufl') });
    }

    rows.push(
        { label: 'Lait permis par les UFL', value: props.resultats.impacts.lait_par_ufl, unit: 'kg/j', decimals: 2 },
        { label: 'PLPot', value: props.resultats.indicateurs?.pl_pot, unit: 'kg/j', decimals: 2 },
        { label: 'Production CH4', value: props.resultats.impacts.ch4, unit: 'g/j', decimals: 0 },
    );

    return rows;
});

const mineralBalanceRows = computed<DetailRow[]>(() => {
    if (props.resultats.inra !== '2018') {
        return [];
    }

    return [
        { label: 'BACA', value: props.resultats.indicateurs?.baca, unit: 'mEq/kg MS', decimals: 0 },
    ];
});

const mineralRows = computed<ComparisonRow[]>(() => {
    if (props.resultats.inra !== '2018') {
        return [];
    }

    return [
        { label: 'Calcium abs', apport: props.resultats.apports.caabs, besoin: props.resultats.besoins.caabs, unit: 'g/j', decimals: 0 },
        { label: 'Phosphore abs', apport: props.resultats.apports.pabs ?? props.resultats.apports.p, besoin: props.resultats.besoins.pabs, unit: 'g/j', decimals: 0 },
        { label: 'Magnésium abs', apport: props.resultats.apports.mgabs, besoin: props.resultats.besoins.mgabs, unit: 'g/j', decimals: 0 },
        { label: 'Sodium', apport: props.resultats.apports.na, besoin: props.resultats.besoins.na, unit: 'g/j', decimals: 0 },
        { label: 'Chlore', apport: props.resultats.apports.cl, besoin: props.resultats.besoins.cl, unit: 'g/j', decimals: 0 },
        { label: 'Potassium', apport: props.resultats.apports.k, besoin: props.resultats.besoins.k, unit: 'g/j', decimals: 0 },
        { label: 'Soufre', apport: props.resultats.apports.s, besoin: props.resultats.besoins.s, unit: 'g/j', decimals: 0 },
        { label: 'Cobalt', apport: props.resultats.apports.co, besoin: props.resultats.besoins.co, unit: 'mg/j', decimals: 0 },
        { label: 'Selenium', apport: props.resultats.apports.se, besoin: props.resultats.besoins.se, unit: 'mg/j', decimals: 0 },
        { label: 'Zinc', apport: props.resultats.apports.zn, besoin: props.resultats.besoins.zn, unit: 'mg/j', decimals: 0 },
        { label: 'Manganèse', apport: props.resultats.apports.mn, besoin: props.resultats.besoins.mn, unit: 'mg/j', decimals: 0 },
        { label: 'Cuivre', apport: props.resultats.apports.cu, besoin: props.resultats.besoins.cu, unit: 'mg/j', decimals: 0 },
        { label: 'Iode', apport: props.resultats.apports.i, besoin: props.resultats.besoins.i, unit: 'mg/j', decimals: 0 },
        { label: 'Vitamine A', apport: props.resultats.apports.vit_a, besoin: props.resultats.besoins.vit_a, unit: 'UI/j', decimals: 0 },
        { label: 'Vitamine D', apport: props.resultats.apports.vit_d, besoin: props.resultats.besoins.vit_d, unit: 'UI/j', decimals: 0 },
        { label: 'Vitamine E', apport: props.resultats.apports.vit_e, besoin: props.resultats.besoins.vit_e, unit: 'UI/j', decimals: 0 },
    ];
});

const balanceRows = computed<BalanceRow[]>(() => {
    const rows: BalanceRow[] = [
        {
            label: 'UFL',
            apport: props.resultats.apports.ufl,
            besoin: props.resultats.besoins.uf_total,
            bilan: props.resultats.bilans.ufl,
            decimals: 2,
        },
        {
            label: 'UE (kg MS)',
            apport: props.resultats.apports.ue,
            besoin: props.resultats.besoins.ci,
            bilan: props.resultats.bilans.ue,
            decimals: 2,
            besoinSuffix: 'CI',
        },
    ];

    if (props.resultats.inra === '2007') {
        rows.push(
            {
                label: 'PDIE (g/j)',
                apport: props.resultats.apports.pdie,
                besoin: props.resultats.besoins.pdi_total,
                bilan: props.resultats.bilans.pdie,
                decimals: 0,
            },
            {
                label: 'PDIN (g/j)',
                apport: props.resultats.apports.pdin,
                besoin: undefined,
                bilan: props.resultats.bilans.pdin,
                decimals: 0,
            },
        );
    } else {
        rows.push({
            label: 'PDI (g/j)',
            apport: props.resultats.apports.pdi,
            besoin: props.resultats.besoins.pdi_total,
            bilan: props.resultats.bilans.pdi,
            decimals: 0,
        });
    }

    rows.push(
        {
            label: 'Ca abs (g/j)',
            apport: props.resultats.apports.caabs,
            besoin: props.resultats.besoins.caabs,
            bilan: props.resultats.bilans.caabs,
            decimals: 1,
        },
        {
            label: 'P abs (g/j)',
            apport: props.resultats.apports.pabs ?? props.resultats.apports.p,
            besoin: props.resultats.besoins.pabs,
            bilan: props.resultats.bilans.pabs,
            decimals: 1,
        },
    );

    return rows;
});

const fattyAcidRows = computed<DetailRow[]>(() => {
    if (props.resultats.inra !== '2018') {
        return [];
    }

    return [
        { label: 'Production AGV totale dans le rumen', value: props.resultats.indicateurs?.prod_agvt_jour, unit: 'mol/j', decimals: 2 },
        { label: 'Acide acétique', value: props.resultats.indicateurs?.acetate, unit: 'mol/100 mol AGV', decimals: 0 },
        { label: 'Acide propionique', value: props.resultats.indicateurs?.propionate, unit: 'mol/100 mol AGV', decimals: 0 },
        { label: 'Acide butyrique', value: props.resultats.indicateurs?.butyrate, unit: 'mol/100 mol AGV', decimals: 0 },
    ];
});

const healthStatus = computed(() => aggregateStatus(healthRows.value.map((row) => healthDetailStatus(row)).filter((status) => status !== 'neutral')));
const healthIssueCount = computed(() => healthRows.value.filter((row) => isIssue(healthDetailStatus(row))).length);
const mineralStatus = computed(() => aggregateStatus(mineralRows.value.map((row) => comparisonStatus(row)).filter((status) => status !== 'neutral')));
const mineralIssueCount = computed(() => mineralRows.value.filter((row) => isIssue(comparisonStatus(row))).length);

const technicalPanels = computed<TechnicalPanel[]>(() => {
    const panels: TechnicalPanel[] = [
        {
            label: 'Énergie',
            valueLabel: ufRatio.value === undefined ? '–' : `${fmt(ufRatio.value * 100, 0)} %`,
            note: `${fmt(ufApport.value, 2)} / ${fmt(ufBesoin.value, 2)} UFL`,
            percent: ufRatio.value === undefined ? 0 : clamp(ufRatio.value * 100, 0, 100),
            status: coverageStatus(ufRatio.value),
        },
        {
            label: 'Protéines',
            valueLabel: proteinRatio.value === undefined ? '–' : `${fmt(proteinRatio.value * 100, 0)} %`,
            note: `${fmt(proteinApport.value, 0)} / ${fmt(proteinBesoin.value, 0)} g`,
            percent: proteinRatio.value === undefined ? 0 : clamp(proteinRatio.value * 100, 0, 100),
            status: coverageStatus(proteinRatio.value),
        },
        {
            label: 'Ingestion',
            valueLabel: ueRatio.value === undefined ? '–' : `${fmt(ueRatio.value * 100, 0)} %`,
            note: `${fmt(ueApport.value, 2)} / ${fmt(ueBesoin.value, 2)} CI`,
            percent: ueRatio.value === undefined ? 0 : clamp(ueRatio.value * 100, 0, 100),
            status: coverageStatus(ueRatio.value),
        },
    ];

    if (props.resultats.inra === '2018') {
        panels.push(
            {
                label: 'Santé ruminale',
                valueLabel: healthIssueCount.value === 0 ? 'Stable' : `${healthIssueCount.value} écart${healthIssueCount.value > 1 ? 's' : ''}`,
                note: `pH AmiD_ru ${fmt(props.resultats.indicateurs?.ph_ruminal, 2)} · IRA ${fmt(props.resultats.indicateurs?.ira, 2)}`,
                percent: clamp(100 - healthIssueCount.value * 22, 18, 100),
                status: healthStatus.value,
            },
            {
                label: 'Minéraux',
                valueLabel: mineralIssueCount.value === 0 ? 'Couverts' : `${mineralIssueCount.value} déficit${mineralIssueCount.value > 1 ? 's' : ''}`,
                note: 'Macro, oligos et vitamines',
                percent: clamp(100 - mineralIssueCount.value * 10, 18, 100),
                status: mineralStatus.value,
            },
        );
    } else {
        panels.push(
            {
                label: 'Rmic',
                valueLabel: fmt(props.resultats.impacts.rmic, 2),
                note: 'Couverture microbienne',
                percent: clamp((props.resultats.impacts.rmic ?? 0) >= 0 ? 100 : 35, 0, 100),
                status: (props.resultats.impacts.rmic ?? 0) >= 0 ? 'ok' : 'alert',
            },
            {
                label: 'Minéraux',
                valueLabel: fmt(props.resultats.bilans.caabs, 1),
                note: 'Lecture calcium absorbable',
                percent: clamp((safeRatio(props.resultats.apports.caabs, props.resultats.besoins.caabs) ?? 0) * 100, 0, 100),
                status: coverageStatus(safeRatio(props.resultats.apports.caabs, props.resultats.besoins.caabs)),
            },
        );
    }

    return panels;
});

const topMetrics = computed<CircleMetric[]>(() => {
    const metrics: CircleMetric[] = [
        {
            label: productionLaitAttendue.value !== undefined ? 'Lait attendu' : 'Lait permis',
            valueLabel: `${fmt(laitComparable.value, 1)} kg/j`,
            note: productionLaitAttendue.value !== undefined
                ? `PLPot ${fmt(props.resultats.indicateurs?.pl_pot, 1)} · plafond ${fmt(laitLimitant.value, 1)}`
                : limitingMilkSource.value,
            status: laitStatus.value,
        },
        {
            label: 'Objectif',
            valueLabel: laitObjectif.value > 0 ? `${fmt(laitObjectif.value, 0)} kg/j` : 'Sans cible',
            note: laitDelta.value === undefined ? 'Aucun objectif de production' : `Écart ${bilanSign(laitDelta.value, 1)} kg/j`,
            status: laitStatus.value,
        },
        {
            label: 'Couverture UFL',
            valueLabel: ufRatio.value === undefined ? '–' : `${fmt(ufRatio.value * 100, 0)} %`,
            note: `${fmt(ufApport.value, 2)} / ${fmt(ufBesoin.value, 2)} UFL`,
            status: coverageStatus(ufRatio.value),
        },
        {
            label: 'Couverture protéines',
            valueLabel: proteinRatio.value === undefined ? '–' : `${fmt(proteinRatio.value * 100, 0)} %`,
            note: props.resultats.inra === '2018' ? 'Lecture PDI' : 'Lecture PDIE / PDIN',
            status: coverageStatus(proteinRatio.value),
        },
    ];

    if (props.resultats.inra === '2018') {
        const phRow = healthRows.value.find((row) => row.metricKey === 'ph_ruminal');

        metrics.push({
            label: 'Santé ruminale',
            valueLabel: fmt(props.resultats.indicateurs?.ph_ruminal, 2),
            note: `IRA ${fmt(props.resultats.indicateurs?.ira, 2)}`,
            status: phRow ? healthDetailStatus(phRow) : 'neutral',
        });
    } else {
        metrics.push({
            label: 'Rmic',
            valueLabel: fmt(props.resultats.impacts.rmic, 2),
            note: 'Couverture microbienne',
            status: (props.resultats.impacts.rmic ?? 0) >= 0 ? 'ok' : 'alert',
        });
    }

    return metrics;
});

const insightCandidates = computed<InsightItem[]>(() => {
    const insights: InsightItem[] = [
        {
            label: 'Objectif laitier',
            valueLabel: laitDelta.value === undefined ? 'Sans cible' : `${bilanSign(laitDelta.value, 1)} kg/j`,
            note: productionLaitAttendue.value !== undefined
                ? `Lait attendu ${fmt(productionLaitAttendue.value, 1)} kg/j`
                : `Lait permis ${fmt(laitLimitant.value, 1)} kg/j`,
            status: laitStatus.value,
        },
        {
            label: 'Énergie',
            valueLabel: ufRatio.value === undefined ? '–' : `${fmt(ufRatio.value * 100, 0)} %`,
            note: `${fmt(ufApport.value, 2)} apportés pour ${fmt(ufBesoin.value, 2)} requis`,
            status: coverageStatus(ufRatio.value),
        },
        {
            label: 'Protéines',
            valueLabel: proteinRatio.value === undefined ? '–' : `${fmt(proteinRatio.value * 100, 0)} %`,
            note: props.resultats.inra === '2018' ? 'Couverture PDI' : 'Couverture PDIE / PDIN',
            status: coverageStatus(proteinRatio.value),
        },
        {
            label: 'Ingestion',
            valueLabel: ueRatio.value === undefined ? '–' : `${fmt(ueRatio.value * 100, 0)} %`,
            note: `${fmt(ueApport.value, 2)} ingérés pour ${fmt(ueBesoin.value, 2)} visés`,
            status: coverageStatus(ueRatio.value),
        },
    ];

    if (props.resultats.inra === '2018') {
        const iraRow = healthRows.value.find((row) => row.metricKey === 'ira');
        const phRow = healthRows.value.find((row) => row.metricKey === 'ph_ruminal');
        const bprRow = proteinsRows.value.find((row) => row.metricKey === 'bpr');

        insights.push(
            {
                label: 'Santé ruminale',
                valueLabel: healthIssueCount.value === 0 ? 'Stable' : `${healthIssueCount.value} écart${healthIssueCount.value > 1 ? 's' : ''}`,
                note: `pH AmiD_ru ${fmt(props.resultats.indicateurs?.ph_ruminal, 2)} · IRA ${fmt(props.resultats.indicateurs?.ira, 2)}`,
                status: healthStatus.value,
            },
            {
                label: 'Acidose',
                valueLabel: fmt(props.resultats.indicateurs?.ira, 2),
                note: metricNormNote('ira'),
                status: iraRow ? healthDetailStatus(iraRow) : 'neutral',
            },
            {
                label: 'pH ruminal',
                valueLabel: fmt(props.resultats.indicateurs?.ph_ruminal, 2),
                note: 'Équation 15.4 via AmiD_ru',
                status: phRow ? healthDetailStatus(phRow) : 'neutral',
            },
            {
                label: 'BPR',
                valueLabel: fmt(props.resultats.indicateurs?.bpr, 2),
                note: metricNormNote('bpr'),
                status: bprRow ? detailStatus(bprRow) : 'neutral',
            },
            {
                label: 'Minéraux et vitamines',
                valueLabel: mineralIssueCount.value === 0 ? 'Couverts' : `${mineralIssueCount.value} écart${mineralIssueCount.value > 1 ? 's' : ''}`,
                note: 'Macro, oligos et vitamines',
                status: mineralStatus.value,
            },
        );
    } else {
        insights.push({
            label: 'Rmic',
            valueLabel: fmt(props.resultats.impacts.rmic, 2),
            note: 'Bilan microbien',
            status: (props.resultats.impacts.rmic ?? 0) >= 0 ? 'ok' : 'alert',
        });
    }

    return insights;
});

const priorityInsights = computed(() => insightCandidates.value.filter((item) => isIssue(item.status)).slice(0, 4));
const strengthInsights = computed(() => insightCandidates.value.filter((item) => item.status === 'ok').slice(0, 4));
</script>

<template>
    <div class="space-y-6">
        <section
            id="resultats"
            class="scroll-mt-24 relative overflow-hidden rounded-4xl border border-sky-200/70 bg-linear-to-br from-sky-50 via-background to-amber-50/70 p-5 shadow-sm dark:border-sky-950/60 dark:from-sky-950/25 dark:via-background dark:to-amber-950/10 sm:p-7"
        >
                <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-sky-300/20 blur-3xl dark:bg-sky-500/10"></div>
                <div class="absolute bottom-0 left-0 h-28 w-28 rounded-full bg-amber-200/30 blur-3xl dark:bg-amber-500/10"></div>

                <div class="relative grid ">
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-sky-700 dark:text-sky-300">Dossier ration</p>
                            <div class="flex items-center gap-2">
                                <a
                                    :href="rationPdf({ plan: plan.id, ration: ration.id }).url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="rounded-full bg-sky-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-800 dark:bg-sky-500 dark:text-slate-950 dark:hover:bg-sky-400"
                                >
                                    PDF
                                </a>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-sky-300/70 pt-4 dark:border-sky-900/80">
                            <h2 class="max-w-4xl text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                                Analyse technique de la ration
                            </h2>
                        </div>

                        <div class="mt-5 space-y-4">
                            <div v-for="panel in technicalPanels" :key="panel.label" class="rounded-2xl border p-4" :class="tonePanelClass(panel.status)">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-foreground">{{ panel.label }}</p>
                                    </div>
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="toneChipClass(panel.status)">
                                        {{ statusLabel(panel.status) }}
                                    </span>
                                </div>
                                <div class="mt-4 flex items-end justify-between gap-4">
                                    <p class="text-2xl font-semibold tracking-tight text-foreground">{{ panel.valueLabel }}</p>
                                    <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">{{ panel.note }}</p>
                                </div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full transition-all" :class="toneBarClass(panel.status)" :style="`width:${panel.percent}%`"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
                    <div
                        v-for="metric in topMetrics"
                        :key="metric.label"
                        class="flex aspect-square min-h-44 flex-col items-center justify-center rounded-full border-8 p-5 text-center shadow-sm"
                        :class="toneCircleClass(metric.status)"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-muted-foreground">{{ metric.label }}</p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight text-foreground">{{ metric.valueLabel }}</p>
                        <p class="mt-2 text-xs leading-5 text-muted-foreground">{{ metric.note }}</p>
                    </div>
                </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <section class="rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Points clés</h2>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-rose-200/80 bg-rose-50/60 p-4 dark:border-rose-950/70 dark:bg-rose-950/20">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-rose-800 dark:text-rose-300">Ecarts</h3>
                            <div v-if="priorityInsights.length > 0" class="mt-4 space-y-3">
                                <div v-for="item in priorityInsights" :key="item.label" class="rounded-xl border border-white/60 bg-white/70 p-3 dark:border-white/5 dark:bg-slate-950/20">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-1 h-2.5 w-2.5 rounded-full" :class="toneDotClass(item.status)"></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-medium text-foreground">{{ item.label }}</p>
                                                    <p class="mt-1 text-sm text-muted-foreground">{{ item.note }}</p>
                                                </div>
                                                <p class="shrink-0 text-sm font-semibold" :class="toneTextClass(item.status)">{{ item.valueLabel }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="mt-4 text-sm text-muted-foreground">Aucun écart prioritaire détecté sur les indicateurs disponibles.</p>
                        </div>

                        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/60 p-4 dark:border-emerald-950/70 dark:bg-emerald-950/20">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-800 dark:text-emerald-300">Equilibres</h3>
                            <div v-if="strengthInsights.length > 0" class="mt-4 space-y-3">
                                <div v-for="item in strengthInsights" :key="item.label" class="rounded-xl border border-white/60 bg-white/70 p-3 dark:border-white/5 dark:bg-slate-950/20">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-1 h-2.5 w-2.5 rounded-full" :class="toneDotClass(item.status)"></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-medium text-foreground">{{ item.label }}</p>
                                                    <p class="mt-1 text-sm text-muted-foreground">{{ item.note }}</p>
                                                </div>
                                                <p class="shrink-0 text-sm font-semibold" :class="toneTextClass(item.status)">{{ item.valueLabel }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="mt-4 text-sm text-muted-foreground">Les données sont trop incomplètes pour isoler des atouts nets.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-foreground">Lecture lait & économie</h2>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">Facteur limitant</dt>
                            <dd class="text-right font-medium text-foreground">{{ limitingMilkSource }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">Lait permis</dt>
                            <dd class="text-right font-mono text-foreground">{{ fmt(laitLimitant, 2) }} kg/j</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">Objectif</dt>
                            <dd class="text-right font-mono text-foreground">{{ laitObjectif > 0 ? `${fmt(laitObjectif, 0)} kg/j` : '–' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">Écart production</dt>
                            <dd class="text-right font-mono font-medium" :class="toneTextClass(laitStatus)">{{ bilanSign(laitDelta, 1) }} kg/j</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">Coût / animal / jour</dt>
                            <dd class="text-right font-mono text-foreground">{{ fmt(resultats.impacts.cout_animal, 2) }} €</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">Coût / 1 000 L</dt>
                            <dd class="text-right font-mono text-foreground">{{ fmt(resultats.impacts.cout_1000l, 2) }} €</dd>
                        </div>
                        <div v-if="resultats.impacts.eau_bue !== undefined" class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">Eau bue estimée</dt>
                            <dd class="text-right font-mono text-foreground">{{ fmt(resultats.impacts.eau_bue, 1) }} L/j</dd>
                        </div>
                        <div v-if="resultats.inra === '2018'" class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">Production CH4</dt>
                            <dd class="text-right font-mono text-foreground">{{ fmt(resultats.impacts.ch4, 0) }} g/j</dd>
                        </div>
                        <div v-else class="flex items-start justify-between gap-4">
                            <dt class="text-muted-foreground">Rmic</dt>
                            <dd class="text-right font-mono font-medium" :class="bilanClass(resultats.impacts.rmic)">{{ fmt(resultats.impacts.rmic, 2) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-border bg-background/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Par énergie</p>
                            <p class="mt-2 text-2xl font-semibold text-foreground">{{ fmt(resultats.impacts.lait_par_ufl, 2) }}</p>
                            <p class="mt-1 text-sm text-muted-foreground">kg de lait permis par les UFL</p>
                        </div>
                        <div class="rounded-2xl border border-border bg-background/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                {{ resultats.inra === '2018' ? 'Par protéines' : 'Par PDIE / PDIN' }}
                            </p>
                            <p class="mt-2 text-2xl font-semibold text-foreground">
                                {{ resultats.inra === '2018' ? fmt(resultats.impacts.lait_par_pdi, 2) : fmt(resultats.impacts.lait_par_pdie, 2) }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ resultats.inra === '2018' ? 'kg de lait permis par les PDI' : 'lecture protéique principale' }}
                            </p>
                        </div>
                    </div>
                </section>
        </div>

        <section id="bilan" class="scroll-mt-24 overflow-hidden rounded-[1.75rem] border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-2 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Apports vs besoins</h2>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-border bg-muted/30">
                                <th class="px-5 py-3 text-left font-medium text-muted-foreground">Nutriment</th>
                                <th class="px-5 py-3 text-right font-medium text-muted-foreground">Apport</th>
                                <th class="px-5 py-3 text-right font-medium text-muted-foreground">Besoin</th>
                                <th class="px-5 py-3 text-right font-medium text-muted-foreground">Bilan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in balanceRows" :key="row.label" :class="isIssue(balanceStatus(row)) ? tableRowClass(balanceStatus(row)) : ''">
                                <td class="px-5 py-3 text-foreground">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span>{{ row.label }}</span>
                                        <span
                                            v-if="isIssue(balanceStatus(row))"
                                            class="rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                            :class="toneChipClass(balanceStatus(row))"
                                        >
                                            {{ statusLabel(balanceStatus(row)) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right font-mono" :class="isIssue(balanceStatus(row)) ? toneTextClass(balanceStatus(row)) : 'text-foreground'">
                                    {{ fmt(row.apport, row.decimals ?? 1) }}
                                </td>
                                <td class="px-5 py-3 text-right font-mono text-muted-foreground">
                                    {{ fmt(row.besoin, row.decimals ?? 1) }}
                                    <span v-if="row.besoinSuffix" class="ml-1">{{ row.besoinSuffix }}</span>
                                </td>
                                <td class="px-5 py-3 text-right font-mono font-medium" :class="isIssue(balanceStatus(row)) ? toneTextClass(balanceStatus(row)) : 'text-muted-foreground'">
                                    {{ bilanSign(row.bilan, row.decimals ?? 1) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
        </section>

        <div v-if="resultats.inra === '2018'" class="grid gap-4 xl:grid-cols-2">
                <section id="proteines" class="scroll-mt-24 rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Protéines</h2>
                        </div>
                    </div>

                    <div v-if="visibleDetailRows(proteinsRows).length > 0" class="-mx-5 -mb-5 mt-4 overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in visibleDetailRows(proteinsRows)" :key="row.label" :class="tableRowClass(detailStatus(row))">
                                    <th class="px-5 py-3 pr-4 text-left font-normal text-muted-foreground">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ row.label }}</span>
                                            <span
                                                v-if="detailStatus(row) !== 'neutral'"
                                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                                :class="toneChipClass(detailStatus(row))"
                                            >
                                                {{ statusLabel(detailStatus(row)) }}
                                            </span>
                                        </div>
                                    </th>
                                    <td class="px-5 py-3 text-right font-mono" :class="toneTextClass(detailStatus(row))">
                                        {{ fmt(row.value, row.decimals ?? 1) }}
                                        <span v-if="row.unit" class="ml-1 text-xs font-normal text-muted-foreground">{{ row.unit }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">Aucun écart détecté sur cette section avec le filtre actif.</p>
                </section>

                <section id="sante" class="scroll-mt-24 rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Santé</h2>
                        </div>
                    </div>

                    <div v-if="visibleDetailRows(healthRows).length > 0" class="-mx-5 -mb-5 mt-4 overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in visibleDetailRows(healthRows)" :key="row.label" :class="tableRowClass(healthDetailStatus(row))">
                                    <th class="px-5 py-3 pr-4 text-left font-normal text-muted-foreground">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ row.label }}</span>
                                            <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.14em]" :class="toneChipClass(healthDetailStatus(row))">
                                                {{ statusLabel(healthDetailStatus(row)) }}
                                            </span>
                                        </div>
                                    </th>
                                    <td class="px-5 py-3 text-right font-mono" :class="toneTextClass(healthDetailStatus(row))">
                                        {{ fmt(row.value, row.decimals ?? 1) }}
                                        <span v-if="row.unit" class="ml-1 text-xs font-normal text-muted-foreground">{{ row.unit }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">Aucun écart détecté sur cette section avec le filtre actif.</p>
                </section>
        </div>

        <div v-if="resultats.inra === '2018'" class="grid gap-4 xl:grid-cols-2">
                <section class="rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-foreground">Fibres</h2>

                    <div v-if="visibleDetailRows(fiberRows).length > 0" class="-mx-5 -mb-5 mt-4 overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in visibleDetailRows(fiberRows)" :key="row.label" :class="tableRowClass(fiberDetailStatus(row))">
                                    <th class="px-5 py-3 pr-4 text-left font-normal text-muted-foreground">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ row.label }}</span>
                                            <span
                                                v-if="fiberDetailStatus(row) !== 'neutral'"
                                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                                :class="toneChipClass(fiberDetailStatus(row))"
                                            >
                                                {{ statusLabel(fiberDetailStatus(row)) }}
                                            </span>
                                        </div>
                                    </th>
                                    <td class="px-5 py-3 text-right font-mono" :class="toneTextClass(fiberDetailStatus(row))">
                                        {{ fmt(row.value, row.decimals ?? 1) }}
                                        <span v-if="row.unit" class="ml-1 text-xs font-normal text-muted-foreground">{{ row.unit }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">Aucun écart détecté sur cette section avec le filtre actif.</p>
                </section>

                <section class="rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-foreground">Énergie</h2>

                    <div v-if="visibleDetailRows(energyRows).length > 0" class="-mx-5 -mb-5 mt-4 overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in visibleDetailRows(energyRows)" :key="row.label" :class="tableRowClass(detailStatus(row))">
                                    <th class="px-5 py-3 pr-4 text-left font-normal text-muted-foreground">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ row.label }}</span>
                                            <span
                                                v-if="detailStatus(row) !== 'neutral'"
                                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                                :class="toneChipClass(detailStatus(row))"
                                            >
                                                {{ statusLabel(detailStatus(row)) }}
                                            </span>
                                        </div>
                                    </th>
                                    <td class="px-5 py-3 text-right font-mono" :class="toneTextClass(detailStatus(row))">
                                        {{ fmt(row.value, row.decimals ?? 1) }}
                                        <span v-if="row.unit" class="ml-1 text-xs font-normal text-muted-foreground">{{ row.unit }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">Aucun écart détecté sur cette section avec le filtre actif.</p>
                </section>
        </div>

        <div v-if="resultats.inra === '2018'" class="grid gap-4 xl:grid-cols-2">
                <section id="mineraux" class="scroll-mt-24 rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-foreground">Minéraux et vitamines</h2>

                    <div v-if="visibleDetailRows(mineralBalanceRows).length > 0" class="-mx-5 -mb-5 mt-4 overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in visibleDetailRows(mineralBalanceRows)" :key="row.label" :class="tableRowClass(detailStatus(row))">
                                    <th class="px-5 py-3 pr-4 text-left font-normal text-muted-foreground">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ row.label }}</span>
                                            <span
                                                v-if="detailStatus(row) !== 'neutral'"
                                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                                :class="toneChipClass(detailStatus(row))"
                                            >
                                                {{ statusLabel(detailStatus(row)) }}
                                            </span>
                                        </div>
                                    </th>
                                    <td class="px-5 py-3 text-right font-mono" :class="toneTextClass(detailStatus(row))">
                                        {{ fmt(row.value, row.decimals ?? 1) }}
                                        <span v-if="row.unit" class="ml-1 text-xs font-normal text-muted-foreground">{{ row.unit }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="-mx-5 mt-5 overflow-x-auto">
                        <table v-if="visibleComparisonRows(mineralRows).length > 0" class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-border bg-muted/30">
                                    <th class="px-5 py-3 text-left font-medium text-muted-foreground">Minéral</th>
                                    <th class="px-5 py-3 text-right font-medium text-muted-foreground">Apport</th>
                                    <th class="px-5 py-3 text-right font-medium text-muted-foreground">Besoin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in visibleComparisonRows(mineralRows)" :key="row.label" :class="tableRowClass(comparisonStatus(row))">
                                    <th class="px-5 py-3 text-left font-normal text-muted-foreground">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ row.label }}</span>
                                            <span
                                                v-if="comparisonStatus(row) !== 'neutral'"
                                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                                :class="toneChipClass(comparisonStatus(row))"
                                            >
                                                {{ statusLabel(comparisonStatus(row)) }}
                                            </span>
                                        </div>
                                    </th>
                                    <td class="px-5 py-3 text-right font-mono" :class="toneTextClass(comparisonStatus(row))">
                                        {{ fmt(row.apport, row.decimals ?? 1) }}
                                        <span class="ml-1 text-xs font-normal text-muted-foreground">{{ row.unit }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono text-foreground">
                                        {{ fmt(row.besoin, row.decimals ?? 1) }}
                                        <span class="ml-1 text-xs font-normal text-muted-foreground">{{ row.unit }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="px-5 py-4 text-sm text-muted-foreground">Aucun écart détecté sur cette section avec le filtre actif.</p>
                    </div>
                </section>

                <section class="rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-foreground">Acides gras volatils</h2>

                    <div v-if="visibleDetailRows(fattyAcidRows).length > 0" class="-mx-5 -mb-5 mt-4 overflow-hidden">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in visibleDetailRows(fattyAcidRows)" :key="row.label" :class="tableRowClass(detailStatus(row))">
                                    <th class="px-5 py-3 pr-4 text-left font-normal text-muted-foreground">{{ row.label }}</th>
                                    <td class="px-5 py-3 text-right font-mono text-foreground">
                                        {{ fmt(row.value, row.decimals ?? 1) }}
                                        <span v-if="row.unit" class="ml-1 text-xs font-normal text-muted-foreground">{{ row.unit }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">Aucun écart détecté sur cette section avec le filtre actif.</p>
                </section>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
                <section id="besoins-ufl" class="scroll-mt-24 rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-foreground">Décomposition des besoins UFL</h2>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Entretien</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.besoins.uf_entretien, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Production</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.besoins.uf_production, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Gestation</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.besoins.uf_gestation, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Croissance</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.besoins.uf_croissance, 2) }}</dd>
                        </div>
                        <div v-if="resultats.inra === '2018'" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">DRC (réserves)</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.besoins.uf_drc, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t border-border pt-2 font-medium">
                            <dt class="text-foreground">Total</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.besoins.uf_total, 2) }}</dd>
                        </div>
                    </dl>
                </section>

                <section id="economie" class="scroll-mt-24 rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-foreground">Économie & eau</h2>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Coût / animal / jour</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.impacts.cout_animal, 2) }} €</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Coût / 1 000 L lait</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.impacts.cout_1000l, 2) }} €</dd>
                        </div>
                        <div v-if="resultats.impacts.eau_bue !== undefined" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Eau bue estimée</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.impacts.eau_bue, 1) }} L/j</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">MS ingérée</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.apports.ms, 2) }} kg/j</dd>
                        </div>
                        <div v-if="resultats.indicateurs" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">NI (% PV)</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.indicateurs.ni, 2) }}</dd>
                        </div>
                        <div v-if="resultats.indicateurs" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">PCO (prop. concentrés)</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.indicateurs.pco, 3) }}</dd>
                        </div>
                        <div v-if="resultats.indicateurs" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Sg (taux substitution)</dt>
                            <dd class="font-mono text-foreground">{{ fmt(resultats.indicateurs.sg, 3) }}</dd>
                        </div>
                    </dl>
                </section>
        </div>

        <section class="rounded-[1.75rem] border border-border bg-card p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-foreground">Taux laitiers ajustés au stade</h2>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-border bg-background/80 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">TB</p>
                        <p class="mt-2 text-2xl font-semibold text-foreground">{{ fmt(resultats.besoins.tb_ajuste, 1) }} g/kg</p>
                    </div>
                    <div class="rounded-2xl border border-border bg-background/80 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">TP</p>
                        <p class="mt-2 text-2xl font-semibold text-foreground">{{ fmt(resultats.besoins.tp_ajuste, 1) }} g/kg</p>
                    </div>
                    <div v-if="resultats.inra === '2018' && resultats.indicateurs" class="rounded-2xl border border-border bg-background/80 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">EffPDI</p>
                        <p class="mt-2 text-2xl font-semibold text-foreground">{{ fmt((resultats.indicateurs.eff_pdi ?? 0) * 100, 1) }} %</p>
                    </div>
                </div>
        </section>
    </div>
</template>
