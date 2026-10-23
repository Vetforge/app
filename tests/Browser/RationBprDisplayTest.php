<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;

it('shows the signed BPR directly after proteins in the technical list and circles', function (int $width, int $height, float $bpr, string $value, string $tone) {
    $user = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => -3, 'max' => 3]]]);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vache_laitiere',
        'lait_objectif' => 25, 'poids_vif' => 650,
    ]);
    $aliment = Aliment::factory()->create([
        'user_id' => $user->id, 'type' => 'fourrage', 'ms' => 85,
        'ufl' => 0.8, 'pdi' => 80, 'bpr' => $bpr, 'dt6_n' => null, 'uel' => 1,
    ]);
    RationAliment::create([
        'ration_id' => $ration->id, 'aliment_id' => $aliment->id,
        'quantite' => 10, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 1,
    ]);
    $this->actingAs($user);
    $page = visit(route('plans.rations.resultats', [$plan, $ration], absolute: false))->resize($width, $height)
        ->assertSeeIn('[data-technical-panel="BPR"]', $value)
        ->assertSeeIn('[data-technical-panel="BPR"] p.text-2xl', 'g/kg MS')
        ->assertSeeIn('[data-circle-metric="BPR"]', $value)
        ->assertSeeIn('[data-circle-metric="BPR"] p.text-xl', 'g/kg MS')
        ->assertSeeIn('[data-circle-metric="BPR"]', 'Cible -3 à 3')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("[data-technical-panel=BPR]").previousElementSibling.dataset.technicalPanel'))->toBe('Protéines')
        ->and($page->script('document.querySelector("[data-circle-metric=BPR]").previousElementSibling.dataset.circleMetric'))->toBe('Couverture protéines')
        ->and($page->script('document.querySelector("[data-circle-metric=BPR]").className'))->toContain($tone)
        ->and($page->script('document.querySelector("[data-technical-panel=BPR]").textContent'))->not->toContain('%')
        ->and($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue()
        ->and($page->script('(() => { const circles = document.querySelectorAll("[data-circle-metric]"); return circles[0].getBoundingClientRect().right <= circles[1].getBoundingClientRect().left; })()'))->toBeTrue();
    if (getenv('BPR_DISPLAY_CAPTURE') && $width === 390 && $bpr === 1.25) {
        $page->script('document.querySelector("[data-circle-metric=BPR]").parentElement.scrollIntoView({block: "center"})');
        $page->screenshot(false, 'bpr-display-mobile.png');
    }
})->with(['desktop' => [1440, 1100], 'mobile' => [390, 844]])->with([
    'negative' => [-12.5, '-12.5', 'rose'],
    'zero' => [0.0, '+0', 'emerald'],
    'positive' => [1.25, '+1.25', 'emerald'],
]);

it('does not show an INRA 2018 BPR in the INRA 2007 technical summary', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2007']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vache_laitiere']);
    $this->actingAs($user);
    visit(route('plans.rations.resultats', [$plan, $ration], absolute: false))
        ->assertSee('Analyse technique de la ration')
        ->assertNotPresent('[data-technical-panel="BPR"]')
        ->assertNotPresent('[data-circle-metric="BPR"]')
        ->assertNoJavaScriptErrors();
});
