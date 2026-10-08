<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;

test('ration results show milk indicators only with planned milk production', function (string $inra, ?float $milk) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => $inra]);
    $ration = Ration::factory()->vacheLaitiere()->create([
        'plan_rationnement_id' => $plan->id,
        'lait_objectif' => $milk,
        'lait_potentiel305j' => 9000,
        'poids_vif' => 650,
        'parite' => 2,
        'mois_lactation' => 4,
        'mois_gestation' => 2,
        'tb_annuel' => 40,
        'tp_annuel' => 32,
    ]);
    $aliment = Aliment::factory()->systemique()->create([
        'type' => 'fourrage', 'ms' => 85, 'mo' => 900, 'mat' => 140,
        'cb' => 260, 'ndf' => 480, 'adf' => 290, 'ag' => 25, 'eb' => 4200,
        'amidon' => 40, 'pf' => 12, 'd_mo' => 68, 'dt6_n' => 72, 'dr_n' => 85,
        'dt6_ami' => 50, 'niref' => 1.8, 'b_vec' => 0.78,
    ]);
    RationAliment::factory()->create([
        'ration_id' => $ration->id, 'aliment_id' => $aliment->id,
        'quantite' => 12, 'is_mb' => false, 'is_volonte' => false,
    ]);
    $this->actingAs($user);

    $page = visit(route('plans.rations.resultats', [$plan, $ration], absolute: false));
    $page->assertSee('Apports vs besoins')->assertSee('Coût / animal / jour')->assertNoJavaScriptErrors();

    if ($milk > 0) {
        $page->assertSee('Lait permis')->assertSee('Lecture lait & économie')->assertSee('Coût / 1 000 L lait');
    } else {
        $page->assertDontSee('Lait permis')->assertDontSee('Lait attendu')->assertDontSee('Lecture lait & économie')
            ->assertDontSee('Production laitière attendue')->assertDontSee('Objectif laitier')->assertDontSee('PLPot')
            ->assertDontSee('Coût / 1 000 L lait')->assertDontSee('Taux laitiers ajustés au stade');
        if ($inra === '2018') {
            $page->assertSee('Variation de poids estimée')->assertSee('Estimation selon le bilan énergétique');
        }
    }
})->with(['2018', '2007'])->with(['missing' => [null], 'zero' => [0.0], 'lactating' => [30.0]]);
