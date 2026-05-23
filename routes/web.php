<?php

use App\Http\Controllers\AgrinirController;
use App\Http\Controllers\AlimentController;
use App\Http\Controllers\BreederController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MelangeController;
use App\Http\Controllers\PlanRationnementController;
use App\Http\Controllers\RationController;
use App\Http\Controllers\VeterinaryAnalysisController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Support\VeterinaryModules;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/welcome', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('dashboard/search', [DashboardController::class, 'search'])->name('dashboard.search');

    // Module Ration (nouvelle entree Nexa, anciennes URLs conservees)
    Route::redirect('ration/plans', '/plans')->name('ration.plans');
    Route::redirect('ration/aliments', '/aliments')->name('ration.aliments');
    Route::redirect('ration/agrinir', '/agrinir')->name('ration.agrinir');

    // Aliments
    Route::get('aliments', [AlimentController::class, 'index'])->name('aliments.index');
    Route::get('aliments/create', [AlimentController::class, 'create'])->name('aliments.create');
    Route::post('aliments', [AlimentController::class, 'store'])->name('aliments.store');
    Route::get('aliments/{aliment}/edit', [AlimentController::class, 'edit'])->name('aliments.edit');
    Route::put('aliments/{aliment}', [AlimentController::class, 'update'])->name('aliments.update');
    Route::post('aliments/{aliment}/copy', [AlimentController::class, 'copy'])->name('aliments.copy');
    Route::get('aliments/{aliment}/pdf', [AlimentController::class, 'pdf'])->name('aliments.pdf');
    Route::delete('aliments/{aliment}', [AlimentController::class, 'destroy'])->name('aliments.destroy');

    // Plans
    Route::get('plans', [PlanRationnementController::class, 'index'])->name('plans.index');
    Route::get('plans/create', [PlanRationnementController::class, 'create'])->name('plans.create');
    Route::post('plans', [PlanRationnementController::class, 'store'])->name('plans.store');
    Route::get('plans/{plan}', [PlanRationnementController::class, 'show'])->name('plans.show');
    Route::get('plans/{plan}/edit', [PlanRationnementController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{plan}', [PlanRationnementController::class, 'update'])->name('plans.update');
    Route::delete('plans/{plan}', [PlanRationnementController::class, 'destroy'])->name('plans.destroy');

    // Rations
    Route::scopeBindings()->group(function () {
        Route::get('plans/{plan}/rations/create', [RationController::class, 'create'])->name('plans.rations.create');
        Route::post('plans/{plan}/rations', [RationController::class, 'store'])->name('plans.rations.store');
        Route::get('plans/{plan}/rations/{ration}/description', [RationController::class, 'description'])->name('plans.rations.description');
        Route::put('plans/{plan}/rations/{ration}/description', [RationController::class, 'updateDescription'])->name('plans.rations.description.update');
        Route::get('plans/{plan}/rations/{ration}/composition', [RationController::class, 'composition'])->name('plans.rations.composition');
        Route::post('plans/{plan}/rations/{ration}/aliments', [RationController::class, 'addAliment'])->name('plans.rations.aliments.add');
        Route::patch('plans/{plan}/rations/{ration}/aliments/reorder', [RationController::class, 'reorderAliments'])->name('plans.rations.aliments.reorder');
        Route::put('plans/{plan}/rations/{ration}/aliments/{rationAliment}', [RationController::class, 'updateAliment'])->name('plans.rations.aliments.update');
        Route::patch('plans/{plan}/rations/{ration}/aliments/{rationAliment}/valeurs', [RationController::class, 'updateAlimentValeurs'])->name('plans.rations.aliments.valeurs');
        Route::delete('plans/{plan}/rations/{ration}/aliments/{rationAliment}', [RationController::class, 'removeAliment'])->name('plans.rations.aliments.remove');
        Route::post('plans/{plan}/rations/{ration}/melanges', [MelangeController::class, 'store'])->name('plans.rations.melanges.store');
        Route::patch('plans/{plan}/rations/{ration}/melanges/reorder', [MelangeController::class, 'reorder'])->name('plans.rations.melanges.reorder');
        Route::put('plans/{plan}/rations/{ration}/melanges/{melange}', [MelangeController::class, 'update'])->name('plans.rations.melanges.update');
        Route::delete('plans/{plan}/rations/{ration}/melanges/{melange}', [MelangeController::class, 'destroy'])->name('plans.rations.melanges.destroy');
        Route::patch('plans/{plan}/rations/{ration}/melanges/{melange}/aliments/reorder', [MelangeController::class, 'reorderAliments'])->name('plans.rations.melanges.aliments.reorder');
        Route::post('plans/{plan}/rations/{ration}/melanges/{melange}/aliments', [MelangeController::class, 'addAliment'])->name('plans.rations.melanges.aliments.add');
        Route::put('plans/{plan}/rations/{ration}/melanges/{melange}/aliments/{melangeAliment}', [MelangeController::class, 'updateAliment'])->name('plans.rations.melanges.aliments.update');
        Route::patch('plans/{plan}/rations/{ration}/melanges/{melange}/aliments/{melangeAliment}/valeurs', [MelangeController::class, 'updateAlimentValeurs'])->name('plans.rations.melanges.aliments.valeurs');
        Route::delete('plans/{plan}/rations/{ration}/melanges/{melange}/aliments/{melangeAliment}', [MelangeController::class, 'removeAliment'])->name('plans.rations.melanges.aliments.remove');
        Route::get('plans/{plan}/rations/{ration}/resultats', [RationController::class, 'resultats'])->name('plans.rations.resultats');
        Route::get('plans/{plan}/rations/{ration}/pdf', [RationController::class, 'pdf'])->name('plans.rations.pdf');
        Route::delete('plans/{plan}/rations/{ration}', [RationController::class, 'destroy'])->name('plans.rations.destroy');
    });

    // AgriNIR
    Route::get('agrinir', [AgrinirController::class, 'show'])->name('agrinir.show');
    Route::post('agrinir/calculer', [AgrinirController::class, 'calculer'])->name('agrinir.calculer');
    Route::get('agrinir/types/{inra}', [AgrinirController::class, 'types'])->name('agrinir.types');
    Route::post('agrinir/sauvegarder', [AgrinirController::class, 'sauvegarder'])->name('agrinir.sauvegarder');

    // Eleveurs
    Route::get('eleveurs', [BreederController::class, 'index'])->name('breeders.index');
    Route::get('eleveurs/create', [BreederController::class, 'create'])->name('breeders.create');
    Route::post('eleveurs', [BreederController::class, 'store'])->name('breeders.store');
    Route::post('eleveurs/quick', [BreederController::class, 'quickStore'])->name('breeders.quick-store');
    Route::get('eleveurs/import/exemple', [BreederController::class, 'importExample'])->name('breeders.import-example');
    Route::post('eleveurs/import', [BreederController::class, 'importCsv'])->name('breeders.import');
    Route::get('eleveurs/{breeder}/edit', [BreederController::class, 'edit'])->name('breeders.edit');
    Route::put('eleveurs/{breeder}', [BreederController::class, 'update'])->name('breeders.update');
    Route::delete('eleveurs/{breeder}', [BreederController::class, 'destroy'])->name('breeders.destroy');

    // Analyses veterinaires
    Route::get('analyses/{module}', [VeterinaryAnalysisController::class, 'index'])->whereIn('module', VeterinaryModules::slugs())->name('analyses.index');
    Route::get('analyses/{module}/create', [VeterinaryAnalysisController::class, 'create'])->whereIn('module', VeterinaryModules::slugs())->name('analyses.create');
    Route::post('analyses/{module}', [VeterinaryAnalysisController::class, 'store'])->whereIn('module', VeterinaryModules::slugs())->name('analyses.store');
    Route::get('analyses/{analysis}', [VeterinaryAnalysisController::class, 'show'])->whereNumber('analysis')->name('analyses.show');
    Route::get('analyses/{analysis}/edit', [VeterinaryAnalysisController::class, 'edit'])->whereNumber('analysis')->name('analyses.edit');
    Route::put('analyses/{analysis}', [VeterinaryAnalysisController::class, 'update'])->whereNumber('analysis')->name('analyses.update');
    Route::delete('analyses/{analysis}', [VeterinaryAnalysisController::class, 'destroy'])->whereNumber('analysis')->name('analyses.destroy');
    Route::get('analyses/{analysis}/pdf', [VeterinaryAnalysisController::class, 'pdf'])->whereNumber('analysis')->name('analyses.pdf');

    // Admin
    Route::prefix('admin')->name('admin.')->middleware(EnsureUserIsAdmin::class)->group(function () {
        Route::get('aliments', [App\Http\Controllers\Admin\AlimentController::class, 'index'])->name('aliments.index');
        Route::get('aliments/create', [App\Http\Controllers\Admin\AlimentController::class, 'create'])->name('aliments.create');
        Route::post('aliments', [App\Http\Controllers\Admin\AlimentController::class, 'store'])->name('aliments.store');
        Route::get('aliments/{aliment}/edit', [App\Http\Controllers\Admin\AlimentController::class, 'edit'])->name('aliments.edit');
        Route::put('aliments/{aliment}', [App\Http\Controllers\Admin\AlimentController::class, 'update'])->name('aliments.update');
        Route::delete('aliments/{aliment}', [App\Http\Controllers\Admin\AlimentController::class, 'destroy'])->name('aliments.destroy');
        Route::get('users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
        Route::put('users/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
        Route::get('import', [App\Http\Controllers\Admin\ImportController::class, 'show'])->name('import.show');
        Route::post('import', [App\Http\Controllers\Admin\ImportController::class, 'import'])->name('import.store');
    });
});

Route::redirect('/dashboard', '/');

require __DIR__.'/settings.php';
