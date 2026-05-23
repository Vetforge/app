<?php

use App\Models\Aliment;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\Melange;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use App\Models\UserModuleSetting;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('forbids non admin users from the admin user list', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('lists users for admins with usage counts and last login', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'name' => 'Claire Martin',
        'email' => 'claire@example.test',
        'last_login_at' => Carbon::parse('2026-05-20 10:15:00'),
    ]);

    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    Analysis::factory()->count(2)->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'autopsie',
    ]);
    Aliment::factory()->create(['user_id' => $user->id]);
    UserModuleSetting::factory()->create(['user_id' => $user->id, 'module' => 'autopsie']);

    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    Melange::query()->create(['ration_id' => $ration->id, 'nom' => 'Melange hiver']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'claire']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->where('totals.users', 2)
            ->where('totals.admins', 1)
            ->has('users.data', 1)
            ->where('users.data.0.id', $user->id)
            ->where('users.data.0.name', 'Claire Martin')
            ->where('users.data.0.breeders_count', 1)
            ->where('users.data.0.analyses_count', 2)
            ->where('users.data.0.aliments_count', 1)
            ->where('users.data.0.plan_rationnements_count', 1)
            ->where('users.data.0.rations_count', 1)
            ->where('users.data.0.melanges_count', 1)
            ->where('users.data.0.module_settings_count', 1)
            ->whereNot('users.data.0.last_login_at', null)
        );
});

it('shows a user detail page with coordinates counts and analysis modules', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'name' => 'Paul Durand',
        'email' => 'paul@example.test',
        'last_login_at' => Carbon::parse('2026-05-19 08:00:00'),
    ]);

    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'analyse-diverse',
    ]);
    Aliment::factory()->create(['user_id' => $user->id]);

    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    Melange::query()->create(['ration_id' => $ration->id, 'nom' => 'Melange ete']);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Show')
            ->where('user.id', $user->id)
            ->where('user.email', 'paul@example.test')
            ->where('user.breeders_count', 1)
            ->where('user.analyses_count', 1)
            ->where('user.aliments_count', 1)
            ->where('user.plan_rationnements_count', 1)
            ->where('user.rations_count', 1)
            ->where('user.melanges_count', 1)
            ->whereNot('user.last_login_at', null)
            ->has('analysisModules', 1)
            ->where('analysisModules.0.module', 'analyse-diverse')
            ->where('analysisModules.0.count', 1)
        );
});
