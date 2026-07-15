export type AlimentValue = string | number | null | undefined;

export interface Aliment {
    id: number;
    libelle0: string;
    libelle1: string | null;
    type?: string | null;
    ufl?: number | null;
    ufv?: number | null;
    ms?: number | null;
    [key: string]: AlimentValue;
}

export interface RationAliment {
    id: number;
    aliment_id: number;
    aliment: Aliment;
    quantite: number | null;
    is_volonte: boolean;
    is_mb: boolean;
}

export interface MelangeAliment {
    id: number;
    aliment: Aliment;
    quantite: number | null;
    is_mb: boolean;
}

export interface Melange {
    id: number;
    nom: string | null;
    quantite: number | null;
    is_volonte: boolean;
    is_mb: boolean;
    melange_aliments: MelangeAliment[];
}

export interface Plan {
    id: number;
    nom: string;
    inra: string;
}

export interface RationNormeValue {
    min: number | null;
    max: number | null;
}

export interface RationNormeDefinition {
    key: string;
    label: string;
    group: string;
    unit: string | null;
    decimals: number;
    default_min: number | null;
    default_max: number | null;
}

export interface RationNormesPayload {
    active: Record<string, RationNormeValue>;
    editable: RationNormeDefinition[];
}

export interface Ration {
    id: number;
    nom: string;
    ration_aliments: RationAliment[];
    melanges: Melange[];
    lait_objectif: number | null;
    effectif: number | null;
}

export interface ResultatsMeta {
    categorie: string;
    espece: string;
    unite_fourragere: string;
    unite_encombrement: string;
}

export interface Resultats {
    inra: '2007' | '2018';
    meta?: ResultatsMeta;
    apports: Record<string, number>;
    besoins: Record<string, number>;
    impacts: Record<string, number>;
    bilans: Record<string, number>;
    indicateurs?: Record<string, number>;
}
