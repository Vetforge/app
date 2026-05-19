<?php

use App\Models\Aliment;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use App\Models\UserModuleSetting;
use Illuminate\Support\Facades\Schema;

it('imports selected legacy data without persisting legacy mappings or rations', function () {
    $user = User::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'legacy_export_').'.php';

    file_put_contents($path, <<<'PHP'
<?php
$clients_cabinets = array(
  array('idEleveur' => '10', 'identification_cabinet' => 'rieupeyroux', 'nomEleveur' => 'GAEC Test', 'adresseEleveur' => '1 rue du test', 'codePostalEleveur' => '12000', 'villeEleveur' => 'Rodez', 'telephoneEleveur' => '0102030405', 'numeroCheptelEleveur' => 'FR123')
);
$liste_animaux = array(
  array('idAnimal' => '22', 'identification_cabinet' => 'rieupeyroux', 'nomAnimal' => 'Marguerite', 'idEleveur' => '10')
);
$aliments_ration = array(
  array('idAliment' => '7', 'identification_cabinet' => 'rieupeyroux', 'nom_aliment' => 'Foin test', 'matiere_seche' => '88.5', 'proteine_MS' => '12.1', 'cellulose_brute' => '28', 'NDF' => '50', 'ADF' => '30', 'amidon_MS' => '0', 'UFL' => '0.78', 'PDIE' => '80', 'PDIN' => '90', 'UEL' => '1.05', 'UEB' => '1.1', 'ca' => '5', 'p' => '2.2', 'mg' => '1.7', 'Prix' => '12', 'Type' => 'fourrage')
);
$gaz_sang = array(
  array('idAnalyse' => '100', 'identification_cabinet' => 'rieupeyroux', 'datePrelevement' => '2020-01-01', 'dateAnalyse' => '2020-01-02', 'idEleveur' => '10', 'idAnimal' => '22', 'nomIntervenant' => 'Dr Legacy', 'especeGaz' => 'Bovin', 'poidsVifAnimalGaz' => '50', 'enophtalmieAnimalGaz' => '2', 'deshydratationAnimalGaz' => '7', 'pHAnimalGaz' => '7.2', 'PCO2AnimalGaz' => '45', 'HCO3AnimalGaz' => '15', 'NaAnimalGaz' => '130', 'KAnimalGaz' => '5', 'ClAnimalGaz' => '98', 'GlycemieAnimalGaz' => '80', 'traitementAnimalGaz' => 'Perfusion', 'BicaIso1l' => '1')
);
$coproscopie_parasitaire = array();
$diarrhee_neonatale = array();
$tests_cellules = array();
$bacteriologie_antibiogramme = array();
$analyses_diverses = array();
$tests_rapides = array();
$tests_biochimie = array();
$hematologie = array();
$autopsie = array();
$compte_rendu = array();
$bse_laitier = array();
$bse_allaitant = array();
$ration = array(
  array('idAnalyse' => '999', 'identification_cabinet' => 'rieupeyroux', 'idEleveur' => '10')
);
PHP);

    try {
        $this->artisan('legacy:import-whrrdnbreports', [
            'path' => $path,
            '--user' => (string) $user->id,
            '--cabinet' => 'rieupeyroux',
        ])->assertSuccessful();
    } finally {
        @unlink($path);
    }

    expect(Breeder::query()->count())->toBe(1)
        ->and(Aliment::query()->count())->toBe(1)
        ->and(Analysis::query()->count())->toBe(1)
        ->and(PlanRationnement::query()->count())->toBe(0)
        ->and(Ration::query()->count())->toBe(0)
        ->and(Schema::hasTable('legacy_import_mappings'))->toBeFalse();

    $analysis = Analysis::query()->firstOrFail();
    $breeder = Breeder::query()->firstOrFail();
    $aliment = Aliment::query()->firstOrFail();

    expect($analysis->module)->toBe('gaz-du-sang')
        ->and($analysis->animal_nom)->toBe('Marguerite')
        ->and($analysis->payload['weight'])->toEqual(50.0)
        ->and($analysis->payload['perfusions']['bica_iso_1l'])->toEqual(1.0)
        ->and($analysis->results)->toHaveKey('deficit_bicarbonate_g')
        ->and($analysis->created_at->toDateString())->toBe('2020-01-02')
        ->and($analysis->updated_at->toDateString())->toBe('2020-01-02')
        ->and($breeder->created_at->toDateString())->toBe('2020-01-02')
        ->and($breeder->updated_at->toDateString())->toBe('2020-01-02')
        ->and($aliment->created_at->toDateString())->toBe('2020-01-02')
        ->and($aliment->updated_at->toDateString())->toBe('2020-01-02');
});

it('dry-runs the legacy import without writing data', function () {
    $user = User::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'legacy_export_').'.php';

    file_put_contents($path, <<<'PHP'
<?php
$clients_cabinets = array(
  array('idEleveur' => '10', 'identification_cabinet' => 'rieupeyroux', 'nomEleveur' => 'GAEC Test')
);
$liste_animaux = array();
$aliments_ration = array();
$gaz_sang = array();
$coproscopie_parasitaire = array();
$diarrhee_neonatale = array();
$tests_cellules = array();
$bacteriologie_antibiogramme = array();
$analyses_diverses = array();
$tests_rapides = array();
$tests_biochimie = array();
$hematologie = array();
$autopsie = array();
$compte_rendu = array();
$bse_laitier = array();
$bse_allaitant = array();
PHP);

    try {
        $this->artisan('legacy:import-whrrdnbreports', [
            'path' => $path,
            '--user' => (string) $user->id,
            '--dry-run' => true,
        ])->assertSuccessful();
    } finally {
        @unlink($path);
    }

    expect(Breeder::query()->count())->toBe(0)
        ->and(Schema::hasTable('legacy_import_mappings'))->toBeFalse();
});

it('imports legacy veterinary module settings before creating analysis snapshots', function () {
    $user = User::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'legacy_export_').'.php';

    file_put_contents($path, <<<'PHP'
<?php
$clients_cabinets = array(
  array('idEleveur' => '10', 'identification_cabinet' => 'rieupeyroux', 'nom_holding' => 'holding-test', 'nomEleveur' => 'GAEC Config')
);
$liste_animaux = array();
$aliments_ration = array();
$config_vetoapplis = array(
  array('idStd' => '1', 'nom_holding' => 'tous', 'prixStdMalDiar1' => '55', 'txtStd_txMammitesS' => 'Mammites standard', 'txtStd_txMortaliteTotalVeauxS' => 'Mortalite standard'),
  array('nom_holding' => 'holding-test', 'prixMalDiar1' => '66', 'prixVeauAvortement' => '222', 'txt_txMammitesS' => 'Mammites<br><i><b>perso</b></i>', 'txt_txMortaliteTotalVeauxS' => 'Mortalite&nbsp;<span style="color: #FF4500">perso</span>')
);
$config_vetoapplis_bioch = array(
  array('nom_holding' => 'tous', 'EspBioch' => 'Bovin', 'ALBbiochmin' => '30', 'ALBbiochmax' => '40', 'UniteALBbioch' => 'g/L'),
  array('nom_holding' => 'holding-test', 'EspBioch' => 'Bovin', 'ALBbiochmin' => '12', 'ALBbiochmax' => '34', 'UniteALBbioch' => 'custom-unit')
);
$config_vetoapplis_hemato = array(
  array('nom_holding' => 'tous', 'EspHemato' => 'Chien', 'GRhematomin' => '4.4', 'GRhematomax' => '9.9', 'UniteGR' => 'T/L legacy')
);
$tests_biochimie = array(
  array('idAnalyse' => '200', 'identification_cabinet' => 'rieupeyroux', 'dateAnalyse' => '2020-02-01', 'idEleveur' => '10', 'especeBioch' => 'Bovin', 'ALBbioch' => '20')
);
$hematologie = array(
  array('idAnalyse' => '201', 'identification_cabinet' => 'rieupeyroux', 'dateAnalyse' => '2020-02-02', 'idEleveur' => '10', 'especeHemato' => 'Chien', 'hematoGR' => '5.5')
);
$bse_laitier = array(
  array('idAnalyse' => '202', 'identification_cabinet' => 'rieupeyroux', 'dateAnalyse' => '2020-02-03', 'idEleveur' => '10', 'nbVachesProductrices' => '20')
);
$bse_allaitant = array(
  array('idAnalyse' => '203', 'identification_cabinet' => 'rieupeyroux', 'dateAnalyse' => '2020-02-04', 'idEleveur' => '10', 'nbVachesReproductrices' => '30')
);
$coproscopie_parasitaire = array();
$diarrhee_neonatale = array();
$gaz_sang = array();
$tests_cellules = array();
$bacteriologie_antibiogramme = array();
$analyses_diverses = array();
$tests_rapides = array();
$autopsie = array();
$compte_rendu = array();
PHP);

    try {
        $this->artisan('legacy:import-whrrdnbreports', [
            'path' => $path,
            '--user' => (string) $user->id,
            '--cabinet' => 'rieupeyroux',
        ])->assertSuccessful();
    } finally {
        @unlink($path);
    }

    $biochimieSettings = UserModuleSetting::query()
        ->where('user_id', $user->id)
        ->where('module', 'tests-biochimie')
        ->firstOrFail()
        ->settings;

    $hemogrammeSettings = UserModuleSetting::query()
        ->where('user_id', $user->id)
        ->where('module', 'hemogramme')
        ->firstOrFail()
        ->settings;

    $bseLaitierSettings = UserModuleSetting::query()
        ->where('user_id', $user->id)
        ->where('module', 'bse-laitier')
        ->firstOrFail()
        ->settings;

    $bseAllaitantSettings = UserModuleSetting::query()
        ->where('user_id', $user->id)
        ->where('module', 'bse-allaitant')
        ->firstOrFail()
        ->settings;

    expect(data_get($biochimieSettings, 'norms.Bovin.ALB.min'))->toEqual(12.0)
        ->and(data_get($biochimieSettings, 'norms.Bovin.ALB.max'))->toEqual(34.0)
        ->and(data_get($biochimieSettings, 'norms.Bovin.ALB.unit'))->toBe('custom-unit')
        ->and(data_get($hemogrammeSettings, 'norms.Chien.GR.min'))->toEqual(4.4)
        ->and(data_get($hemogrammeSettings, 'norms.Chien.GR.max'))->toEqual(9.9)
        ->and(data_get($hemogrammeSettings, 'norms.Chien.GR.unit'))->toBe('T/L legacy')
        ->and($bseLaitierSettings['prix_mal_diar1'])->toEqual(66.0)
        ->and($bseLaitierSettings['txt_tx_mammites_s'])->toBe('Mammites perso')
        ->and($bseAllaitantSettings['prix_veau_avortement'])->toEqual(222.0)
        ->and($bseAllaitantSettings['txt_tx_mortalite_total_veaux_s'])->toBe('Mortalite perso');

    $biochimie = Analysis::query()->where('module', 'tests-biochimie')->firstOrFail();
    $hemogramme = Analysis::query()->where('module', 'hemogramme')->firstOrFail();
    $bseLaitier = Analysis::query()->where('module', 'bse-laitier')->firstOrFail();

    expect(data_get($biochimie->settings_snapshot, 'norms.Bovin.ALB.min'))->toEqual(12.0)
        ->and(data_get($hemogramme->settings_snapshot, 'norms.Chien.GR.min'))->toEqual(4.4)
        ->and($bseLaitier->settings_snapshot['txt_tx_mammites_s'])->toBe('Mammites perso')
        ->and(data_get($bseLaitier->results, 'commentaires.tx_mammites.s'))->toBe('Mammites perso');
});
