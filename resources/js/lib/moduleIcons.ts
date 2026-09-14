import { Activity, Biohazard, ChartScatter, ClipboardCheck, Columns4, Droplets, FlaskConical, NotebookPen, Slice, SquareActivity, TestTube2, Worm, Zap } from 'lucide-vue-next';
import type { Component } from 'vue';

const moduleIconMap: Record<string, Component> = {
    'coproscopie-parasitaire': Worm,
    'diarrhee-neonatale': Columns4,
    'gaz-du-sang': SquareActivity,
    'comptage-cellulaire': Droplets,
    'diagnostic-bacteriologique': Biohazard,
    'analyse-diverse': TestTube2,
    'tests-rapides': Zap,
    'tests-biochimie': FlaskConical,
    hemogramme: ChartScatter,
    'bse-laitier': ClipboardCheck,
    'bse-allaitant': ClipboardCheck,
    autopsie: Slice,
    'compte-rendu': NotebookPen,
};

export function getModuleIcon(slug: string): Component {
    return moduleIconMap[slug] ?? Activity;
}
