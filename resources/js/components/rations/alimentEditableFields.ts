export interface AlimentEditableField {
    key: string;
    label: string;
    type: 'text' | 'number';
    required?: boolean;
    step?: string;
}

export interface AlimentEditableFieldGroup {
    key: string;
    title: string;
    columnsClass: string;
    fields: AlimentEditableField[];
}

function numericField(key: string, label?: string): AlimentEditableField {
    return {
        key,
        label: label ?? key.toUpperCase().replace(/_/g, ' '),
        type: 'number',
        step: '0.01',
    };
}

export const alimentEditableFieldGroups: AlimentEditableFieldGroup[] = [
    {
        key: 'identification',
        title: 'Identification',
        columnsClass: 'md:grid-cols-2',
        fields: [
            { key: 'libelle0', label: 'Libellé principal', type: 'text', required: true },
            { key: 'libelle1', label: 'Libellé 1', type: 'text' },
            { key: 'libelle2', label: 'Libellé 2', type: 'text' },
            { key: 'type', label: 'Type', type: 'text' },
            numericField('ms', 'MS (%)'),
            numericField('prix', 'Prix (€/unité MB)'),
        ],
    },
    {
        key: 'energie',
        title: 'Énergie',
        columnsClass: 'md:grid-cols-3',
        fields: [
            'ufl', 'ufv', 'uem', 'uel', 'ueb', 'eb', 'em', 'mo', 'mat', 'cb',
            'ndf', 'adf', 'adl', 'ee', 'ag', 'amidon', 'sucres', 'pf', 'd_mo',
            'd_ma', 'd_cb', 'd_ndf', 'd_adf', 'd_e', 'dt_n', 'dt6_n', 'dr_n',
            'dt_ami', 'dt6_ami', 'dt_ms', 'dt6_ms',
        ].map((field) => numericField(field)),
    },
    {
        key: 'proteines',
        title: 'Protéines',
        columnsClass: 'md:grid-cols-3',
        fields: [
            'pdia', 'pdi', 'bpr', 'niref', 'lys_di', 'met_di', 'his_di', 'arg_di',
            'thr_di', 'val_di', 'ile_di', 'leu_di', 'phe_di',
        ].map((field) => numericField(field)),
    },
    {
        key: 'mineraux',
        title: 'Minéraux',
        columnsClass: 'md:grid-cols-3',
        fields: [
            'ca', 'caabs', 'p', 'pabs', 'mg', 'na', 'k', 'cl', 's', 'be',
            'baca', 'cu', 'zn', 'mn', 'co', 'se', 'i',
        ].map((field) => numericField(field)),
    },
    {
        key: 'vitamines',
        title: 'Vitamines',
        columnsClass: 'md:grid-cols-3',
        fields: [
            'vit_a', 'vit_d', 'vit_e', 'c6_10', 'c12_0', 'c14_0', 'c16_0',
            'c16_1', 'c18_0', 'c18_1', 'c18_2', 'c18_3', 'b_vec',
        ].map((field) => numericField(field)),
    },
    {
        key: 'valeurs2007',
        title: 'Valeurs 2007',
        columnsClass: 'md:grid-cols-3',
        fields: [
            'ufl2007', 'ufv2007', 'pdia2007', 'pdie2007', 'pdin2007',
            'd_mo2007', 'd_ma2007', 'd_cb2007', 'd_ndf2007', 'd_adf2007',
            'uem2007', 'uel2007', 'ueb2007', 'eb2007', 'd_e2007', 'em2007',
        ].map((field) => numericField(field)),
    },
];

export const alimentEditableKeys = alimentEditableFieldGroups.flatMap((group) =>
    group.fields.map((field) => field.key),
);

export const alimentEditableNumericKeys = alimentEditableFieldGroups.flatMap((group) =>
    group.fields
        .filter((field) => field.type === 'number')
        .map((field) => field.key),
);
