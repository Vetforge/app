<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use App\Support\VeterinaryModules;
use PHPUnit\Framework\Assert;

function navigateToResponsivePath(mixed $page, string $path): void
{
    $page->script('() => { window.location.href = '.json_encode($path).'; return true; }');
    $page->waitForEvent('networkidle');
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

it('keeps the main authenticated pages inside a mobile viewport', function () {
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

    $page = visit('/login')
        ->resize(375, 812)
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Se connecter')
        ->assertPathIsNot('/login');

    foreach ($paths as $path) {
        navigateToResponsivePath($page, $path);
        $page->assertNoJavaScriptErrors();
        assertResponsiveViewport($page, $path);
    }
});

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
        ->assertPathIsNot('/login');

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
