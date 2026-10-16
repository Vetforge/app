<?php

use App\Models\Aliment;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;
use App\Support\VeterinaryModules;
use PHPUnit\Framework\Assert;

function navigateToResponsivePath(mixed $page, string $path): void
{
    if ($page->script('window.location.pathname') !== $path) {
        $page->script('() => { window.location.href = '.json_encode($path).'; return true; }');
        $page->assertPathIs($path)->assertVisible('main[data-slot=sidebar-inset]')->waitForEvent('networkidle');
    }
}

function assertResponsiveViewport(mixed $page, string $path): void
{
    $hasNoHorizontalOverflow = $page->script(<<<'JS'
        () => {
            const root = document.documentElement;
            const body = document.body;
            const scrollWidth = Math.ceil(Math.max(root.scrollWidth, body.scrollWidth));

            return scrollWidth <= window.innerWidth + 1;
        }
    JS);

    Assert::assertTrue(
        $hasNoHorizontalOverflow,
        "The page [{$path}] overflows horizontally on a mobile viewport.",
    );
}

it('keeps all authenticated pages usable across viewports and themes', function (int $width, int $height, bool $dark) {
    $user = User::factory()->create([
        'email' => 'responsive@example.test',
    ]);
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $plan = PlanRationnement::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'nom' => 'Plan mobile responsive',
        'inra' => '2018',
    ]);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id,
        'nom' => 'Ration mobile responsive',
    ]);

    $forage = Aliment::factory()->create(['user_id' => $user->id, 'usage_aliment' => 2, 'type' => 'fourrage', 'libelle0' => 'Foin de prairie']);
    $concentrate = Aliment::factory()->create(['user_id' => $user->id, 'usage_aliment' => 2, 'type' => 'concentre', 'libelle0' => 'Orge de la ferme']);
    foreach ([$forage, $concentrate] as $index => $aliment) {
        RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $aliment->id, 'quantite' => 2, 'is_volonte' => false, 'ordre' => $index + 1]);
    }
    $mix = Melange::create(['ration_id' => $ration->id, 'nom' => 'Mélange ferme', 'quantite' => 2, 'is_volonte' => false, 'ordre' => 1]);
    MelangeAliment::create(['melange_id' => $mix->id, 'aliment_id' => $forage->id, 'quantite' => 2, 'is_mb' => false, 'ordre' => 1]);

    $analysisPaths = [];

    foreach (VeterinaryModules::slugs() as $module) {
        $settings = VeterinaryModules::defaultSettings($module);
        $analysis = Analysis::factory()->create([
            'user_id' => $user->id,
            'breeder_id' => $breeder->id,
            'module' => $module,
            'payload' => VeterinaryModules::payloadTemplate($module, $settings),
            'settings_snapshot' => $settings,
        ]);

        $analysisPaths = [
            ...$analysisPaths,
            "/analyses/{$module}",
            "/analyses/{$module}/create",
            "/settings/modules/{$module}",
            "/analyses/{$analysis->id}",
            "/analyses/{$analysis->id}/edit",
        ];
    }

    $paths = [
        '/',
        '/plans',
        '/plans/create',
        "/plans/{$plan->id}",
        "/plans/{$plan->id}/edit",
        "/plans/{$plan->id}/rations/create",
        "/plans/{$plan->id}/rations/{$ration->id}/description",
        "/plans/{$plan->id}/rations/{$ration->id}/composition",
        "/plans/{$plan->id}/rations/{$ration->id}/resultats",
        '/aliments',
        '/aliments/create',
        "/aliments/{$forage->id}/edit",
        "/eleveurs/{$breeder->id}",
        "/eleveurs/{$breeder->id}/edit",
        '/agrinir',
        '/eleveurs',
        '/eleveurs/create',
        '/settings/profile',
        '/settings/password',
        '/settings/normes',
        '/settings/two-factor',
        '/settings/appearance',
        ...$analysisPaths,
    ];

    $page = visit('/login', ['hasTouch' => $width < 1024])
        ->resize($width, $height)
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Se connecter')
        ->assertPathIsNot('/login')->assertVisible('main[data-slot=sidebar-inset]')->waitForEvent('networkidle');

    if ($dark) {
        $page->script("() => { localStorage.setItem('appearance', 'dark'); document.cookie = 'appearance=dark;path=/;SameSite=Lax'; document.documentElement.classList.add('dark'); return true; }");
    }

    $fieldIssues = [];
    foreach ($paths as $path) {
        if ($path === '/settings/two-factor') {
            $page->script("() => { location.href = '/user/confirm-password'; }");
            $page->assertPathIs('/user/confirm-password')->fill('input#password', 'password')->click('Confirmer le mot de passe')->assertPathIsNot('/user/confirm-password')->assertVisible('main[data-slot=sidebar-inset]')->waitForEvent('networkidle');
        }
        navigateToResponsivePath($page, $path);
        $page->assertNoJavaScriptErrors();
        assertResponsiveViewport($page, $path);
        Assert::assertSame($dark, $page->script("document.documentElement.classList.contains('dark')"), "Unexpected theme on [{$path}]");
        $issues = $page->script(<<<'JS'
            () => {
                const visible = element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden';
                const named = element => element.getAttribute('aria-label')?.trim()
                    || (element.getAttribute('aria-labelledby') || '').split(' ').some(id => document.getElementById(id)?.textContent.trim())
                    || Array.from(element.labels || []).some(label => label.textContent.trim())
                    || element.getAttribute('title')?.trim()
                    || (element.matches('button, a') && element.textContent.trim());
                return Array.from(document.querySelectorAll('main input:not([type=hidden]), main select, main textarea, main button, main a'))
                    .filter(element => visible(element) && !named(element))
                    .map(element => element.outerHTML.slice(0, 180));
            }
        JS);
        if ($issues !== []) {
            $fieldIssues[$path] = $issues;
        }
        $captureDir = getenv('IMPECCABLE_CAPTURE_DIR');
        if ($captureDir && in_array($path, ['/', '/plans/create', '/analyses/bse-laitier/create', "/plans/{$plan->id}/rations/{$ration->id}/composition"], true)) {
            $filename = 'impeccable-'.str_replace('/', '-', trim($path, '/'))."-{$width}-".($dark ? 'dark' : 'light').'.png';
            $page->screenshot(false, $filename);
            copy(base_path('tests/Browser/Screenshots/'.$filename), $captureDir.'/'.$filename);
        }
    }
    expect($fieldIssues)->toBe([]);
})->with([
    'small mobile light' => [320, 740, false],
    'mobile dark' => [390, 844, true],
    'tablet light' => [768, 1024, false],
    'desktop dark' => [1440, 1000, true],
]);

it('keeps blood gas units outside numeric inputs on mobile', function () {
    $user = User::factory()->create([
        'email' => 'units@example.test',
    ]);
    Breeder::factory()->create(['user_id' => $user->id]);

    $page = visit('/login')
        ->resize(375, 812)
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Se connecter')
        ->assertPathIsNot('/login')->assertVisible('main[data-slot=sidebar-inset]')->waitForEvent('networkidle');

    navigateToResponsivePath($page, '/analyses/gaz-du-sang/create');

    $unitsAreOutsideInputs = $page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('.analysis-unit-field--with-unit')).every((field) => {
            const input = field.querySelector('input');
            const unit = field.querySelector('.analysis-unit');

            if (!input || !unit) {
                return false;
            }

            const inputRect = input.getBoundingClientRect();
            const unitRect = unit.getBoundingClientRect();

            return unitRect.left >= inputRect.right - 1 && unitRect.width > 0;
        })
    JS);

    Assert::assertTrue($unitsAreOutsideInputs);
    assertResponsiveViewport($page, '/analyses/gaz-du-sang/create');
});
