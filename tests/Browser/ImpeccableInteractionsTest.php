<?php

use App\Models\Aliment;
use App\Models\Breeder;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;

it('selects a breeder and creates a plan entirely with the keyboard', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'Élevage des Prés']);
    $this->actingAs($user);

    $page = visit('/plans/create')->fill('#breeder_id', 'elevage')
        ->keys('#breeder_id', ['ArrowDown', 'Enter']);

    expect($page->attribute('#breeder_id', 'aria-expanded'))->toBe('false');
    expect($page->script('document.querySelector("input[name=breeder_id]").value'))->toBe((string) $breeder->id);
    $page->fill('#nom', 'Plan au clavier')->fill('#date', '2026-10-08')
        ->keys('#nom', 'Enter')->assertPathIsNot('/plans/create')->assertNoJavaScriptErrors();
    expect(PlanRationnement::where('nom', 'Plan au clavier')->first()?->breeder_id)->toBe($breeder->id);
});

it('traps focus in a small breeder dialog and returns it on escape', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = visit('/plans/create')->resize(320, 600)->click('[aria-label="Créer un éleveur"]')
        ->assertVisible('[role=dialog]');
    expect($page->script('async () => { await new Promise(requestAnimationFrame); return document.activeElement.id; }'))->toBe('quick_breeder_name');
    if ($captureDir = getenv('IMPECCABLE_CAPTURE_DIR')) {
        $page->screenshot(false, 'impeccable-dialog-320-600.png');
        copy(base_path('tests/Browser/Screenshots/impeccable-dialog-320-600.png'), $captureDir.'/impeccable-dialog-320-600.png');
    }
    for ($index = 0; $index < 10; $index++) {
        $page->keys(':focus', 'Tab');
        expect($page->script('document.querySelector("[role=dialog]").contains(document.activeElement)'))->toBeTrue();
    }
    expect($page->script('document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->keys(':focus', 'Escape')->assertNotPresent('[role=dialog]')
        ->assertNoJavaScriptErrors();
    expect($page->script('async () => { await new Promise(requestAnimationFrame); return document.activeElement.getAttribute("aria-label"); }'))->toBe('Créer un éleveur');
    expect(PlanRationnement::count())->toBe(0);
});

it('creates a breeder from a portalled dialog without submitting its parent form', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Breeder::factory()->create(['user_id' => $user->id, 'name' => 'Ferme existante']);
    $page = visit('/plans/create')->click('[aria-label="Créer un éleveur"]')
        ->fill('#quick_breeder_name', 'Ferme existante')->click('button[type=submit]:text("Creer")')
        ->assertVisible('#quick_breeder_name-error')->assertSee('Cette valeur est déjà utilisée.');
    expect($page->script('document.activeElement.id'))->toBe('quick_breeder_name');
    $page->fill('#quick_breeder_name', 'Ferme créée au cabinet')
        ->click('button[type=submit]:text("Creer")')->assertNotPresent('[role=dialog]')
        ->assertPathIs('/plans/create')->assertNoJavaScriptErrors();
    $breeder = Breeder::where('name', 'Ferme créée au cabinet')->first();
    expect($breeder)->not->toBeNull()
        ->and($page->script('document.querySelector("input[name=breeder_id]").value'))->toBe((string) $breeder->id)
        ->and(PlanRationnement::count())->toBe(0);
});

it('associates a server validation error with its field', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = visit('/eleveurs/create')->fill('#name', str_repeat('A', 256))
        ->click('button[type=submit]')->assertAttribute('#name', 'aria-invalid', 'true')
        ->assertAttribute('#name', 'aria-describedby', 'name-error')
        ->assertVisible('#name-error')->assertSee('La valeur dépasse la limite autorisée.')->assertNoJavaScriptErrors();
    expect($page->script('async () => { await new Promise(requestAnimationFrame); return document.activeElement.id; }'))->toBe('name');
    expect(Breeder::count())->toBe(0);
});

it('preserves zero mixture quantities and saves all three kinds of order from keyboard controls', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vache_laitiere']);
    $first = Aliment::factory()->create(['user_id' => $user->id, 'type' => 'fourrage', 'usage_aliment' => 2, 'libelle0' => 'Foin des Prés']);
    $second = Aliment::factory()->create(['user_id' => $user->id, 'type' => 'concentre', 'usage_aliment' => 2, 'libelle0' => 'Orge ferme']);
    $lineOne = RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $first->id, 'quantite' => 2, 'is_volonte' => false, 'ordre' => 1]);
    $lineTwo = RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $second->id, 'quantite' => 1, 'is_volonte' => false, 'ordre' => 2]);
    $mixOne = Melange::create(['ration_id' => $ration->id, 'nom' => 'Mélange ferme', 'quantite' => 3, 'is_volonte' => false, 'ordre' => 1]);
    $mixTwo = Melange::create(['ration_id' => $ration->id, 'nom' => 'Mélange été', 'quantite' => 2, 'is_volonte' => false, 'ordre' => 2]);
    $ingredientOne = MelangeAliment::create(['melange_id' => $mixOne->id, 'aliment_id' => $first->id, 'quantite' => 1, 'is_mb' => false, 'ordre' => 1]);
    $ingredientTwo = MelangeAliment::create(['melange_id' => $mixOne->id, 'aliment_id' => $second->id, 'quantite' => 1, 'is_mb' => false, 'ordre' => 2]);
    $this->actingAs($user);
    $page = visit(route('plans.rations.composition', [$plan, $ration], absolute: false))->resize(390, 844)
        ->keys('[aria-label="Descendre Foin des Prés"]', 'Enter')->assertSee('Ordre enregistré.');
    expect($lineOne->fresh()->ordre)->toBeGreaterThan($lineTwo->fresh()->ordre);
    $page->keys('[aria-label="Descendre Mélange ferme"]', 'Enter')->assertSee('Ordre enregistré.');
    expect($mixOne->fresh()->ordre)->toBeGreaterThan($mixTwo->fresh()->ordre);
    $page->script(<<<'JS'
        () => {
            const send = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function(body) {
                if (typeof body === 'string' && body.includes('quantite')) window.lastQuantityPayload = JSON.parse(body);
                return send.call(this, body);
            };
        }
    JS);
    $page->fill('input[aria-label^="Quantité de Mélange ferme,"]', '0')->keys('input[aria-label^="Quantité de Mélange ferme,"]', 'Tab')->waitForEvent('networkidle');
    $page->assertVisible('#ration-save-errors')->assertAttribute('input[aria-label^="Quantité de Mélange ferme,"]', 'aria-invalid', 'true');
    expect($page->script('window.lastQuantityPayload.quantite'))->toBe(0)
        ->and((float) $mixOne->fresh()->quantite)->toBe(3.0);
    $page->click('[aria-label="Déplier Mélange ferme"]')
        ->keys('[aria-label="Descendre Foin des Prés dans Mélange ferme"]', 'Enter')->assertSee('Ordre enregistré.');
    expect($ingredientOne->fresh()->ordre)->toBeGreaterThan($ingredientTwo->fresh()->ordre);
    $page->fill('input[aria-label^="Quantité de Foin des Prés dans"]', '0')->keys('input[aria-label^="Quantité de Foin des Prés dans"]', 'Tab')->waitForEvent('networkidle');
    expect((float) $ingredientOne->fresh()->quantite)->toBe(0.0)->and($ingredientOne->fresh()->quantite)->not->toBeNull();
    $page->assertNoJavaScriptErrors();
});

it('ignores late search responses and retries after a network interruption', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = visit('/');
    $page->script(<<<'JS'
        () => {
            const realFetch = window.fetch;
            window.fetch = (url, options) => {
                if (!String(url).includes('/search')) return realFetch(url, options);
                const query = new URL(url, location.href).searchParams.get('q');
                if (query === 'panne') return Promise.reject(new TypeError('offline'));
                return new Promise(resolve => setTimeout(() => resolve(new Response(JSON.stringify([{
                    id: 1, type: 'breeder', type_label: 'Éleveur', label: query === 'ancien' ? 'Résultat obsolète' : 'Résultat actuel',
                    sub: null, updated_at: '2026-10-08', url: '/eleveurs'
                }]), {status: 200})), query === 'ancien' ? 900 : 50));
            };
        }
    JS);
    $page->fill('input[aria-controls=dashboard-results]', 'ancien')->wait(0.4)
        ->fill('input[aria-controls=dashboard-results]', 'actuel')->assertSee('Résultat actuel')->wait(0.7)
        ->assertDontSee('Résultat obsolète')
        ->fill('input[aria-controls=dashboard-results]', 'panne')->assertSee('La recherche est indisponible.');
    $page->script('() => { window.fetch = () => Promise.resolve(new Response("[]", { status: 200 })); }');
    $page->click('Réessayer')->assertSee('Aucun résultat pour cette recherche')
        ->assertDontSee('La recherche est indisponible.')->assertNoJavaScriptErrors();
});

it('keeps text contrast, touch targets and reduced motion in both themes', function (bool $dark) {
    $user = User::factory()->create();
    $this->actingAs($user);
    $pending = visit('/', ['hasTouch' => true, 'reducedMotion' => 'reduce']);
    $page = ($dark ? $pending->inDarkMode() : $pending->inLightMode())->resize(390, 844);
    $ratios = $page->script(<<<'JS'
        () => {
            const sample = document.createElement('span');
            document.body.append(sample);
            const color = token => {
                sample.style.color = `var(--${token})`;
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                context.fillStyle = getComputedStyle(sample).color;
                context.fillRect(0, 0, 1, 1);
                return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3);
            };
            const luminance = rgb => rgb.map(v => v / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4)
                .reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
            const ratio = (foreground, background) => {
                const a = luminance(color(foreground)), b = luminance(color(background));
                return (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
            };
            const ratios = {
                muted: ratio('muted-foreground', 'background'),
                mutedCard: ratio('muted-foreground', 'card'),
                primary: ratio('primary-foreground', 'primary'),
                destructive: ratio('destructive', 'background'),
                comparison: ratio('comparison', 'card'),
            };
            sample.remove();
            return ratios;
        }
    JS);
    foreach ($ratios as $name => $ratio) {
        expect($ratio, $name)->toBeGreaterThanOrEqual(4.5);
    }
    expect($page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('main button, main a')).filter(element => element.getClientRects().length).every(element => {
            const box = element.getBoundingClientRect();
            return box.width >= 44 && box.height >= 44;
        })
    JS))->toBeTrue();
    $page->script('() => { window.fetch = () => new Promise(() => {}); }');
    $page->fill('input[aria-controls=dashboard-results]', 'test')->assertVisible('.animate-pulse:first-child');
    expect($page->script('getComputedStyle(document.querySelector(".animate-pulse")).animationName'))->toBe('none');
    $page->assertNoJavaScriptErrors();
})->with([false, true]);

it('loads the deferred aliment selector and editor and persists their changes', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vache_laitiere']);
    $aliment = Aliment::factory()->create(['user_id' => $user->id, 'usage_aliment' => 1, 'type' => 'fourrage', 'libelle0' => 'Foin du test différé', 'code_inra' => null, 'ms' => 85, 'ufl' => 0.9, 'ufv' => 0.8, 'pdi' => 90]);
    $this->actingAs($user);
    $page = visit(route('plans.rations.composition', [$plan, $ration], absolute: false))
        ->click('#composition > section:first-child > div:first-child button')
        ->click('[aria-label="Aliment à ajouter à la ration"]')
        ->keys('input[role=combobox]', ['ArrowDown', 'Enter'])
        ->assertAttribute('[aria-label="Aliment à ajouter à la ration"]', 'aria-expanded', 'false')
        ->assertNotPresent('[data-slot=content]')
        ->fill('[aria-label^="Quantité de l’aliment à ajouter,"]', '2')
        ->assertValue('[aria-label^="Quantité de l’aliment à ajouter,"]', '2')
        ->click('#composition > section:first-child > div:nth-child(2) button:text-is("Ajouter")')
        ->assertVisible('[aria-label="Retirer Foin du test différé de la ration"]');
    expect($ration->rationAliments()->count())->toBe(1);
    $page->click('button[title="Éditer les valeurs"]')->assertSee('Modifier les valeurs')
        ->fill('input[name=libelle0]', str_repeat('A', 256))->click('button:text-is("Enregistrer")')
        ->assertAttribute('input[name=libelle0]', 'aria-invalid', 'true')
        ->assertVisible('#aliment-libelle0-error')->assertSee('La valeur dépasse la limite autorisée.')
        ->fill('input[name=libelle0]', 'Foin modifié dans le dialogue')->click('button:text-is("Enregistrer")')
        ->assertNotPresent('[role=dialog]')->assertSee('Foin modifié dans le dialogue')->assertNoJavaScriptErrors();
    expect($ration->rationAliments()->first()->aliment->libelle0)->toBe('Foin modifié dans le dialogue');
});

it('covers the dynamic agrinir fields and admin pages on a touch viewport', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $page = visit('/agrinir', ['hasTouch' => true])->resize(390, 844)
        ->click('Maïs / Sorgho')->select('select[aria-label="Type spécifique"]', 'maisE');
    foreach (['humidite' => '34.8', 'proteine' => '8.9', 'ndf' => '42.1', 'adf' => '24.4', 'cendres' => '4.7', 'matiere_grasse' => '3.3', 'fieldErrors_amidon' => '29.5'] as $field => $value) {
        $page->fill('#'.$field, $value);
    }
    $page->click('Calculer les valeurs')->assertSee('Valeurs INRA 2018 et INRA 2007 calculées sur matière sèche')->assertNoJavaScriptErrors();
    expect($page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('main input:not([type=hidden]), main select')).filter(element => element.getClientRects().length)
            .every(element => element.getAttribute('aria-label') || element.labels?.length)
    JS))->toBeTrue();
    foreach (['/admin/users', '/admin/users/'.$admin->id] as $path) {
        $page->script('() => { location.href = '.json_encode($path).'; }');
        $page->waitForEvent('networkidle')->assertNoJavaScriptErrors();
        expect($page->script('document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    }
});

it('preserves an explicit dark preference after a reload on a light system', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = visit('/settings/appearance')->inLightMode()->click('Sombre');
    expect($page->script("document.documentElement.classList.contains('dark')"))->toBeTrue();
    $page->script("() => { location.href = '/plans/create'; }");
    $page->waitForEvent('networkidle')->assertNoJavaScriptErrors();
    expect($page->script("document.documentElement.classList.contains('dark')"))->toBeTrue();
});

it('explains an authentication error and focuses the affected field', function () {
    $user = User::factory()->create();
    $page = visit('/login')->fill('#email', $user->email)->fill('#password', 'incorrect-password')
        ->click('Se connecter')->assertAttribute('#email', 'aria-invalid', 'true')
        ->assertSee('Ces identifiants ne correspondent pas à nos enregistrements.');
    expect($page->script('async () => { await new Promise(requestAnimationFrame); return document.activeElement.id; }'))->toBe('email');
});
