export const ALIMENT_TYPES = ['fourrage', 'concentre', 'mineral'] as const;

export type AlimentType = (typeof ALIMENT_TYPES)[number];
