<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;
use App\Services\Equations2018\Apport as Apport2018;
use App\Services\Equations2018\Besoin as Besoin2018;
use App\Services\RationCalculator;
use App\Support\RationNormes;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;

function createWorkspaceRation(User $user): array
{
    $plan = PlanRationnement::factory()->create([
        'user_id' => $user->id,
        'inra' => '2018',
    ]);

    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id,
        'categorie_animal' => 'vacheLaitiere',
        'lait_objectif' => 30,
        'lait_potentiel305j' => 9000,
        'poids_vif' => 650,
        'pourcentage_primipare' => 30,
        'tb_annuel' => 40,
        'tp_annuel' => 32,
        'mois_lactation' => 4.0,
        'mois_gestation' => 2.0,
        'temperature_ambiante' => 18,
        'poids_veau_naissance' => 45,
        'ecart_variation_reserve' => -0.5,
    ]);

    $fourrage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'libelle0' => 'Foins',
        'libelle1' => 'Prairie',
        'ms' => 85,
        'mo' => 900,
        'mat' => 140,
        'cb' => 260,
        'ndf' => 480,
        'adf' => 290,
        'ag' => 25,
        'eb' => 4200,
        'amidon' => 40,
        'pf' => 12,
        'd_mo' => 68,
        'dt6_n' => 72,
        'dr_n' => 85,
        'dt6_ami' => 50,
        'ca' => 8,
        'p' => 3,
        'mg' => 2.4,
        'na' => 1.5,
        'k' => 22,
        'cl' => 5,
        's' => 2,
    ]);

    $concentre = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'libelle0' => 'Cereales',
        'libelle1' => 'Mais grain',
        'ms' => 88,
        'mo' => 970,
        'mat' => 180,
        'cb' => 70,
        'ndf' => 180,
        'adf' => 90,
        'ag' => 35,
        'eb' => 4350,
        'amidon' => 320,
        'pf' => 6,
        'd_mo' => 88,
        'dt6_n' => 78,
        'dr_n' => 88,
        'dt6_ami' => 86,
        'ca' => 1.2,
        'p' => 4.1,
        'mg' => 1.6,
        'na' => 2.2,
        'k' => 13,
        'cl' => 3,
        's' => 1.5,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $fourrage->id,
        'quantite' => 12,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $concentre->id,
        'quantite' => 4,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 2,
    ]);

    return [$plan, $ration];
}

it('exposes composition and results props on the composition route', function () {
    $user = User::factory()->create([
        'normes_personnalisees' => [
            'ph_ruminal' => ['min' => 6.4, 'max' => null],
        ],
    ]);
    [$plan, $ration] = createWorkspaceRation($user);

    $this->actingAs($user)
        ->get(route('plans.rations.composition', [$plan, $ration]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rations/Composition')
            ->where('plan.id', $plan->id)
            ->where('ration.id', $ration->id)
            ->where('resultats.inra', '2018')
            ->where('iterations_volonte', 0)
            ->where('normes.active.ph_ruminal.min', 6.4)
            ->has('normes.editable', 10)
            ->has('aliments_disponibles')
            ->has('ration.ration_aliments', 2)
        );
});

it('exposes composition and results props on the results route', function () {
    $user = User::factory()->create([
        'normes_personnalisees' => [
            'bpr' => ['min' => 1, 'max' => 4],
        ],
    ]);
    [$plan, $ration] = createWorkspaceRation($user);

    $this->actingAs($user)
        ->get(route('plans.rations.resultats', [$plan, $ration]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rations/Resultats')
            ->where('plan.id', $plan->id)
            ->where('ration.id', $ration->id)
            ->where('resultats.inra', '2018')
            ->where('iterations_volonte', 0)
            ->where('normes.active.bpr.min', 1)
            ->where('normes.active.bpr.max', 4)
            ->has('normes.editable', 10)
            ->has('aliments_disponibles')
            ->has('ration.ration_aliments', 2)
        );
});

it('fills intake capacity for an INRA 2018 ad libitum silage', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create([
        'user_id' => $user->id,
        'inra' => '2018',
    ]);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id,
        'categorie_animal' => 'brebis_laitiere',
        'poids_vif' => 60,
        'lait_objectif' => 4,
        'race' => 'lacaune',
        'mfc' => 70,
        'mpc' => 55,
        'nec' => 3,
    ]);

    $ensilage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'libelle0' => 'Ensilages',
        'libelle1' => 'Ray-grass anglais',
        'ms' => 55,
        'mo' => 900,
        'mat' => 145,
        'cb' => 270,
        'ndf' => 520,
        'adf' => 310,
        'ag' => 25,
        'eb' => 4200,
        'amidon' => 20,
        'pf' => 12,
        'd_mo' => 72,
        'dt6_n' => 72,
        'dr_n' => 82,
        'dt6_ami' => 50,
        'niref' => 1.8,
        'b_vec' => 0.78,
        'uem' => 0.95,
    ]);
    $orge = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'libelle0' => 'Cereales',
        'libelle1' => 'Orge',
        'ms' => 87.2,
        'mo' => 970,
        'mat' => 110,
        'cb' => 50,
        'ndf' => 190,
        'adf' => 65,
        'ag' => 22,
        'eb' => 4350,
        'amidon' => 520,
        'pf' => 6,
        'd_mo' => 88,
        'dt6_n' => 78,
        'dr_n' => 88,
        'dt6_ami' => 86,
        'niref' => 2.0,
        'b_vec' => 0.94,
    ]);
    $soja = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'libelle0' => 'Tourteaux oleagineux',
        'libelle1' => 'Tourteau de soja',
        'ms' => 88,
        'mo' => 930,
        'mat' => 480,
        'cb' => 65,
        'ndf' => 140,
        'adf' => 80,
        'ag' => 18,
        'eb' => 4550,
        'amidon' => 20,
        'pf' => 6,
        'd_mo' => 90,
        'dt6_n' => 82,
        'dr_n' => 95,
        'dt6_ami' => 50,
        'niref' => 2.0,
        'b_vec' => 0.98,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $ensilage->id,
        'quantite' => null,
        'is_mb' => false,
        'is_volonte' => true,
        'ordre' => 1,
    ]);
    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $orge->id,
        'quantite' => 0.5,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 2,
    ]);
    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $soja->id,
        'quantite' => 0.3,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('plans.rations.resultats', [$plan, $ration]))
        ->assertOk();

    $ration = $ration->fresh()->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
        'planRationnement',
    ]);
    $volonte = $ration->rationAliments->firstWhere('is_volonte', true);

    expect((float) $volonte->quantite)->toBeGreaterThan(0.0);

    Apport2018::precompute($ration);
    try {
        $ratio = Apport2018::calculerApportTotalUE($ration) / Besoin2018::calculerCapaciteIngestion($ration);
    } finally {
        Apport2018::clearCache();
    }

    expect($ratio)->toBeGreaterThan(0.995);
    expect($ratio)->toBeLessThan(1.001);
});

it('exposes editable norms on the settings route', function () {
    $user = User::factory()->create([
        'normes_personnalisees' => [
            'ira' => ['min' => 0.7, 'max' => 1.1],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('normes.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Normes')
            ->where('normes.active.ira.min', 0.7)
            ->where('normes.active.ira.max', 1.1)
            ->has('normes.editable', 10)
        );
});

it('updates custom norms for the authenticated user', function () {
    $user = User::factory()->create();

    $normes = RationNormes::active();
    $normes['bpr']['min'] = 1;
    $normes['bpr']['max'] = 4;
    $normes['ira']['min'] = 0.7;
    $normes['ira']['max'] = 1.1;
    $normes['ph_ruminal']['min'] = 6.4;

    $this->actingAs($user)
        ->from(route('normes.edit'))
        ->patch(route('normes.update'), [
            'normes' => $normes,
        ])
        ->assertRedirect(route('normes.edit'));

    expect($user->fresh()->normes_personnalisees)->toMatchArray([
        'bpr' => ['min' => 1, 'max' => 4],
        'ira' => ['min' => 0.7, 'max' => 1.1],
        'ph_ruminal' => ['min' => 6.4, 'max' => null],
    ]);
});

it('downloads the ration pdf', function () {
    Pdf::fake();

    $user = User::factory()->create([
        'normes_personnalisees' => [
            'ph_ruminal' => ['min' => 6.4, 'max' => null],
        ],
        'clinic_profile' => [
            'name' => 'Clinique Ration Conseil',
            'address' => '8 avenue des Rations',
            'postal_code' => '31000',
            'city' => 'Toulouse',
            'phone' => '05 00 00 00 00',
            'email' => 'ration@example.test',
        ],
    ]);
    [$plan, $ration] = createWorkspaceRation($user);

    $this->actingAs($user)
        ->get(route('plans.rations.pdf', [$plan, $ration]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) use ($plan, $ration) {
        expect($pdf->viewName)->toBe('pdf.ration');
        expect($pdf->viewData['plan']->is($plan))->toBeTrue();
        expect($pdf->viewData['ration']->is($ration))->toBeTrue();
        expect($pdf->viewData['resultats']['inra'])->toBe('2018');
        expect($pdf->viewData['iterations_volonte'])->toBeInt();
        expect($pdf->viewData['normes']['active']['ph_ruminal']['min'])->toBe(6.4);
        expect($pdf->viewData['clinicHeader'])->toMatchArray([
            'name' => 'Clinique Ration Conseil',
            'city' => 'Toulouse',
        ]);
        expect($pdf->downloadName)->toContain('ration-');

        return true;
    });
});

it('renders a zero share for pdf components with null quantity', function () {
    $user = User::factory()->create();
    [$plan, $ration] = createWorkspaceRation($user);

    $rationAliment = $ration->rationAliments()->whereHas('aliment', fn ($query) => $query->where('libelle0', 'Cereales'))->firstOrFail();
    $rationAliment->update(['quantite' => null]);

    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
        'planRationnement',
    ]);

    $html = view('pdf.ration', [
        'plan' => $plan,
        'ration' => $ration,
        'resultats' => RationCalculator::calculer($ration),
        'iterations_volonte' => 0,
    ])->render();

    expect(preg_match('/Cereales.*?Part ration.*?0 %.*?width="0%"/s', $html))->toBe(1);
    expect(preg_match('/Cereales.*?Part ration.*?width="50%"/s', $html))->toBe(0);
});

it('renders the cost and water summary below the balance table in the ration pdf', function () {
    $user = User::factory()->create();
    [$plan, $ration] = createWorkspaceRation($user);

    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
        'planRationnement',
    ]);

    $html = view('pdf.ration', [
        'plan' => $plan,
        'ration' => $ration,
        'resultats' => RationCalculator::calculer($ration),
        'iterations_volonte' => 0,
        'clinicHeader' => [
            'name' => 'Clinique Ration Conseil',
            'address' => '8 avenue des Rations',
            'postal_code' => '31000',
            'city' => 'Toulouse',
            'phone' => '05 00 00 00 00',
            'email' => 'ration@example.test',
        ],
    ])->render();

    expect($html)->toContain('Apports vs besoins');
    expect($html)->toContain('Coût / animal / jour');
    expect($html)->toContain('Coût / 1 000 L');
    expect($html)->toContain('Eau bue estimée');
    expect($html)->toContain('Clinique Ration Conseil');
    expect($html)->toContain('8 avenue des Rations');
    expect($html)->toContain('ration@example.test');
});

it('renders updated rumen-health wording in the ration pdf', function () {
    $user = User::factory()->create();
    [$plan, $ration] = createWorkspaceRation($user);

    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
        'planRationnement',
    ]);

    $html = view('pdf.ration', [
        'plan' => $plan,
        'ration' => $ration,
        'resultats' => RationCalculator::calculer($ration),
        'iterations_volonte' => 0,
    ])->render();

    expect($html)->toContain('MOD des concentrés (proxy interne)');
    expect($html)->toContain('pH ruminal estimé via AmiD_ru (6,2)');
    expect($html)->toContain('NDF des fourrages (proxy NDFfo)');
    expect($html)->not->toContain('MO dégradable du concentré (250 - 300)');
    expect($html)->not->toContain('Prévision du pH ruminal (6,2)');
});

it('renders custom norm labels in the ration pdf', function () {
    $user = User::factory()->create();
    [$plan, $ration] = createWorkspaceRation($user);

    $user->update([
        'normes_personnalisees' => [
            'bpr' => ['min' => 1, 'max' => 4],
            'ph_ruminal' => ['min' => 6.4, 'max' => null],
        ],
    ]);

    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
        'planRationnement',
    ]);

    $html = view('pdf.ration', [
        'plan' => $plan,
        'ration' => $ration,
        'resultats' => RationCalculator::calculer($ration),
        'iterations_volonte' => 0,
        'normes' => RationNormes::payloadForUser($user),
    ])->render();

    expect($html)->toContain('BPR (1 - 4)');
    expect($html)->toContain('pH ruminal estimé via AmiD_ru (6,4)');
    expect($html)->not->toContain('BPR (0 - 3)');
});
