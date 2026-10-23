<?php

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
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

it('opens nonblocking calculation warnings in the ration and its mixture on desktop and mobile', function (int $width, int $height) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vache_laitiere']);
    $first = Aliment::factory()->create(['user_id' => $user->id, 'libelle0' => 'Foin audit', 'type' => 'fourrage', 'ms' => 85, 'dt6_n' => null]);
    $second = Aliment::factory()->create(['user_id' => $user->id, 'libelle0' => 'Enrubannage audit', 'type' => 'fourrage', 'ms' => 50, 'dt6_n' => null]);
    RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $first->id, 'quantite' => 5, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 1]);
    $mix = Melange::create(['ration_id' => $ration->id, 'nom' => 'Mélange audit', 'quantite' => 2, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 1]);
    MelangeAliment::create(['melange_id' => $mix->id, 'aliment_id' => $second->id, 'quantite' => 1, 'is_mb' => false, 'ordre' => 1]);
    $this->actingAs($user);

    $page = visit(route('plans.rations.composition', [$plan, $ration], absolute: false))->resize($width, $height)
        ->click('[aria-label="Avertissements de calcul pour Foin audit"]')
        ->assertSee('dt6_n')->assertSee('tabulés utilisés')
        ->keys(':focus', 'Escape')
        ->click('[aria-label="Avertissements de calcul pour Mélange audit"]')
        ->assertSee('Enrubannage audit :')
        ->keys(':focus', 'Escape')
        ->click('[aria-label="Déplier Mélange audit"]')
        ->click('[aria-label="Avertissements de calcul pour Enrubannage audit"]')
        ->assertSee('dt6_n')->keys(':focus', 'Escape');

    $quantity = 'input[aria-label^="Quantité de Mélange audit,"]';
    $page->fill($quantity, '0')->keys($quantity, 'Tab')->waitForEvent('networkidle')
        ->assertNotPresent('#ration-save-errors')->assertValue($quantity, '0')
        ->refresh()->assertValue($quantity, '0')
        ->assertNotPresent('[aria-label="Avertissements de calcul pour Mélange audit"]')
        ->assertNoJavaScriptErrors();
    expect($mix->fresh()->quantite)->toBe(0.0)
        ->and($mix->fresh()->melangeAliments)->toHaveCount(1);
})->with(['desktop' => [1440, 1000], 'mobile' => [390, 844]]);

it('shows fixed mineral DM in aliment forms and in the ration editor for legacy records', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = visit(route('aliments.create', absolute: false))
        ->select('#type', 'fourrage')->fill('#ms', '85')
        ->select('#type', 'mineral')->assertValue('#ms', '100')->assertAttribute('#ms', 'readonly', '')
        ->select('#type', 'fourrage')->assertValue('#ms', '85')
        ->assertNoJavaScriptErrors();

    $mineral = Aliment::factory()->create(['user_id' => $user->id, 'code_inra' => null, 'type' => 'mineral', 'ms' => null, 'libelle0' => 'Minéral sec', 'usage_aliment' => 2]);
    Aliment::query()->whereKey($mineral->id)->update(['ms' => null]);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vache_laitiere']);
    RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $mineral->id, 'quantite' => 0.2, 'is_mb' => true, 'is_volonte' => false, 'ordre' => 1]);

    $page->navigate(route('plans.rations.composition', [$plan, $ration], absolute: false))
        ->click('button[title="Éditer les valeurs"]')
        ->assertValue('[role=dialog] input[name=ms]', '100')
        ->assertAttribute('[role=dialog] input[name=ms]', 'readonly', '')
        ->assertNoJavaScriptErrors();
});
