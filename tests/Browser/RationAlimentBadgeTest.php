<?php

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;

it('shows the INRA badge in ration and mixture aliment selectors', function (int $width, int $height) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vache_laitiere',
    ]);
    Melange::create(['ration_id' => $ration->id, 'nom' => 'Mélange test']);
    Aliment::factory()->systemique()->create([
        'libelle0' => 'Foin référence', 'libelle1' => null, 'code_inra' => '1234', 'usage_aliment' => 1,
    ]);
    Aliment::factory()->create([
        'user_id' => $user->id, 'libelle0' => 'Foin ferme', 'libelle1' => null, 'code_inra' => null, 'usage_aliment' => 1,
    ]);
    $this->actingAs($user);

    $page = visit(route('plans.rations.composition', [$plan, $ration], absolute: false))
        ->resize($width, $height)
        ->click('#composition > section:first-child > div:first-child button');

    foreach (['Aliment à ajouter à la ration', 'Aliment à ajouter à Mélange test'] as $label) {
        if ($label === 'Aliment à ajouter à Mélange test') {
            $page->click('[aria-label="Déplier Mélange test"]')->click('Ajouter un aliment');
        }

        $page->click('[aria-label="'.$label.'"]')
            ->assertVisible('[role=option]:has-text("Foin référence") [title="Aliment INRA (protégé)"]')
            ->assertVisible('[role=option]:has-text("Foin ferme")')
            ->assertNotPresent('[role=option]:has-text("Foin ferme") [title="Aliment INRA (protégé)"]');

        if ($captureDir = getenv('INRA_BADGE_CAPTURE_DIR')) {
            $target = $label === 'Aliment à ajouter à la ration' ? 'ration' : 'melange';
            $filename = "inra-badge-{$target}-{$width}.png";
            $page->screenshot(false, $filename);
            copy(base_path('tests/Browser/Screenshots/'.$filename), $captureDir.'/'.$filename);
        }

        $page
            ->fill('input[role=combobox]', 'reference')
            ->assertVisible('[role=option]:has-text("Foin référence") [title="Aliment INRA (protégé)"]')
            ->assertNotPresent('[role=option]:has-text("Foin ferme")')
            ->keys('input[role=combobox]', ['ArrowDown', 'Enter'])
            ->assertAttribute('[aria-label="'.$label.'"]', 'aria-expanded', 'false')
            ->assertNoJavaScriptErrors();
    }
})->with(['desktop' => [1440, 1000], 'mobile' => [390, 844]]);
