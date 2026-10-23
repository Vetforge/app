<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\CategorieAnimal;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserNormesRequest;
use App\Support\RationNormes;
use App\Support\VeterinaryModules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NormesController extends Controller
{
    public function edit(Request $request): Response
    {
        $categorie = $this->categorie($request);

        return Inertia::render('settings/ModuleSettings', [
            'module' => RationNormes::module(),
            'modules' => VeterinaryModules::settingsNavigation(),
            'settings' => [],
            'defaults' => [],
            'normes' => RationNormes::settingsPayloadForUser($request->user(), $categorie),
        ]);
    }

    public function update(UpdateUserNormesRequest $request): RedirectResponse
    {
        $categorie = CategorieAnimal::from($request->validated('categorie_animal', CategorieAnimal::VacheLaitiere->value));
        $request->user()->update([
            'normes_personnalisees' => RationNormes::storeForCategory(
                $request->user()->normes_personnalisees,
                $categorie,
                array_replace_recursive(
                    RationNormes::active($request->user()->normes_personnalisees, $categorie),
                    $request->validated('normes'),
                ),
            ),
        ]);

        return back()->with('success', 'Normes mises à jour.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $categorie = $this->categorie($request);
        $request->user()->update([
            'normes_personnalisees' => RationNormes::storeForCategory(
                $request->user()->normes_personnalisees, $categorie, RationNormes::defaults(),
            ),
        ]);

        return back()->with('success', 'Normes de ration réinitialisées.');
    }

    private function categorie(Request $request): CategorieAnimal
    {
        $validated = $request->validate([
            'categorie_animal' => ['sometimes', 'required', Rule::enum(CategorieAnimal::class)],
        ]);

        return CategorieAnimal::from($validated['categorie_animal'] ?? CategorieAnimal::VacheLaitiere->value);
    }
}
