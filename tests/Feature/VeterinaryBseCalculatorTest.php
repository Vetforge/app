<?php

use App\Services\VeterinaryAnalysisCalculator;

describe('BSE Laitier calculator', function () {
    it('returns zero costs for empty payload', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [], []);

        expect($result)->toBeArray()
            ->and($result['cout_mammites'])->toEqual(0)
            ->and($result['cout_boiteries'])->toEqual(0)
            ->and($result['cout_reproduction'])->toEqual(0)
            ->and($result['cout_alimentaire'])->toEqual(0);
    });

    it('calculates production moyenne per vache', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'nb_vaches_productrices' => 100,
            'production_annuelle_lait' => 700,
        ], []);

        expect($result['production_moyenne_vache'])->toEqual(7000);
    });

    it('calculates tx_mammites as sum of locales and aigues', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'nb_vaches_productrices' => 100,
            'nb_mammites_locales' => 20,
            'nb_mammites_aigues' => 10,
        ], []);

        expect($result['tx_mammites_locales'])->toBe(20.0)
            ->and($result['tx_mammites_aigues'])->toBe(10.0)
            ->and($result['tx_mammites'])->toBe(30.0);
    });

    it('calculates cout_cct negative for cci below 300', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'concentration_cellulaire_moyen' => 200,
            'production_annuelle_lait' => 100,
        ], []);

        expect($result['cout_cct'])->toEqual(-300);
    });

    it('calculates cout_cct positive for cci between 300 and 400', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'concentration_cellulaire_moyen' => 350,
            'production_annuelle_lait' => 100,
        ], []);

        expect($result['cout_cct'])->toEqual(610);
    });

    it('calculates cout_cct for cci above 400', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'concentration_cellulaire_moyen' => 500,
            'production_annuelle_lait' => 100,
        ], []);

        expect($result['cout_cct'])->toEqual(1220);
    });

    it('calculates tx_mortalite_neonatale from 0-7j and 8j-sevr deaths', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'nb_veaux_nes_vivants' => 100,
            'nb_morts_0a7' => 5,
            'nb_morts_8a_sevr' => 3,
        ], []);

        expect($result['nb_mortalite_neonatale'])->toEqual(8.0)
            ->and($result['tx_mortalite_neonatale'])->toEqual(8.0);
    });

    it('uses prim holstein race coefficients for ivv reference', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'race' => 'Prim Holstein',
            'ivv' => 400,
            'nb_vaches_productrices' => 50,
        ], []);

        expect($result['cout_reproduction'])->toEqual(0)
            ->and($result['cout_fl'])->toBe(350.0);
    });

    it('uses normande race coefficients for ivv reference', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'race' => 'Normande',
            'ivv' => 385,
            'nb_vaches_productrices' => 50,
        ], []);

        expect($result['cout_reproduction'])->toEqual(0)
            ->and($result['cout_fl'])->toBe(378.0);
    });

    it('calculates positive cout_reproduction when ivv exceeds reference', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'race' => 'Prim Holstein',
            'ivv' => 410,
            'nb_vaches_productrices' => 100,
        ], []);

        expect($result['cout_reproduction'])->toEqual(1000);
    });

    it('calculates alimentaire cost using settings prices', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'ha_foin' => 10,
            'ha_ensilage_herbe' => 5,
        ], [
            'prix_ha_foin' => 600,
            'prix_ha_ensilage_herbe' => 800,
        ]);

        expect($result['cout_alimentaire'])->toEqual(10000);
    });

    it('returns null for cout_alimentaire_vache when production is zero', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [
            'production_annuelle_lait' => 0,
        ], []);

        expect($result['cout_alimentaire_vache'])->toBeNull();
    });

    it('includes commentaires block from settings', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-laitier', [], [
            'txt_tx_mammites_s' => 'Bien',
            'txt_tx_mammites_ns' => 'Probleme<br><span style="color: #FF4500">mammites</span>',
        ]);

        expect($result['commentaires']['tx_mammites']['s'])->toBe('Bien')
            ->and($result['commentaires']['tx_mammites']['ns'])->toBe('Probleme mammites');
    });
});

describe('BSE Allaitant calculator', function () {
    it('returns zero costs for empty payload', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [], []);

        expect($result)->toBeArray()
            ->and($result['cout_mortalite'])->toEqual(0)
            ->and($result['cout_diarrhee'])->toEqual(0)
            ->and($result['cout_alimentaire'])->toEqual(0);
    });

    it('calculates nb_morts as accidents velage plus morts post 24h', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [
            'nb_accidents_velage' => 3,
            'nb_morts_post24h' => 5,
        ], []);

        expect($result['nb_morts'])->toBe(8.0);
    });

    it('calculates veau_par_vache', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [
            'nb_vaches_reproductrices' => 100,
            'nb_veaux_nes_vivants' => 95,
        ], []);

        expect($result['veau_par_vache'])->toBe(0.95);
    });

    it('calculates tx_mortalite_total_veaux', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [
            'nb_veaux_nes_vivants' => 100,
            'nb_accidents_velage' => 4,
            'nb_morts_post24h' => 2,
        ], []);

        expect($result['tx_mortalite_total_veaux'])->toBe(6.0);
    });

    it('calculates letalite per pathology', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [
            'nb_malades_diar1' => 20,
            'nb_morts_diar1' => 4,
        ], []);

        expect($result['letalite_malades_diar1'])->toBe(20.0);
    });

    it('calculates cout_diarrhee using settings prices', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [
            'nb_malades_diar1' => 10,
            'nb_morts_diar1' => 2,
        ], [
            'prix_mal_diar1' => 50,
            'prix_mort_diar1' => 250,
        ]);

        expect($result['cout_diarrhee'])->toEqual(1000);
    });

    it('calculates cout_ivv above reference of 365', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [
            'nb_vaches_reproductrices' => 100,
            'ivv' => 635,
        ], [
            'prix_veau_ivv' => 3,
        ]);

        expect($result['cout_ivv'])->toEqual(300);
    });

    it('calculates cout_mortalite using avortements and accidents', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [
            'nb_avortons' => 5,
            'nb_accidents_velage' => 3,
        ], [
            'prix_veau_avortement' => 200,
            'prix_veau_accident_velage' => 200,
        ]);

        expect($result['cout_mortalite'])->toEqual(1600);
    });

    it('returns null for cout_alimentaire_vache when no vaches', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [
            'nb_vaches_reproductrices' => 0,
        ], []);

        expect($result['cout_alimentaire_vache'])->toBeNull();
    });

    it('includes commentaires block from settings', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [], [
            'txt_tx_mortalite_total_veaux_s' => 'OK',
            'txt_tx_mortalite_total_veaux_ns' => 'Probleme <i>HTML</i>',
        ]);

        expect($result['commentaires']['tx_mortalite_total_veaux']['s'])->toBe('OK')
            ->and($result['commentaires']['tx_mortalite_total_veaux']['ns'])->toBe('Probleme HTML');
    });

    it('calculates prolificite correctly', function () {
        $result = VeterinaryAnalysisCalculator::calculate('bse-allaitant', [
            'nb_veaux_nes_vivants' => 110,
            'nb_jumeaux' => 10,
        ], []);

        expect($result['prolificite'])->toBe(110.0);
    });
});
