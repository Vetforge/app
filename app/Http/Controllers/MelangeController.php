<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAlimentRequest;
use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MelangeController extends Controller
{
    public function store(Request $request, PlanRationnement $plan, Ration $ration): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'nom' => ['nullable', 'string', 'max:255'],
        ]);

        Melange::create([
            'ration_id' => $ration->id,
            'nom' => $validated['nom'] ?? null,
            'ordre' => Melange::where('ration_id', $ration->id)->max('ordre') + 1,
        ]);

        return back()->with('success', 'Mélange créé.');
    }

    public function update(Request $request, PlanRationnement $plan, Ration $ration, Melange $melange): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'nom' => ['nullable', 'string', 'max:255'],
            'quantite' => ['nullable', 'required_unless:is_volonte,true', 'numeric', 'gt:0'],
            'is_volonte' => ['boolean'],
            'is_mb' => ['boolean'],
        ]);

        if (($validated['is_volonte'] ?? false) && $melange->melangeAliments()->whereHas('aliment', fn ($q) => $q->where('type', '!=', 'Fourrage'))->exists()) {
            return back()->withErrors(['is_volonte' => 'Un mélange à volonté doit contenir exclusivement des fourrages.']);
        }
        if (($validated['is_volonte'] ?? false)) {
            $autre = $ration->rationAliments()->where('is_volonte', true)->exists()
                || $ration->melanges()->where('is_volonte', true)->where('id', '!=', $melange->getKey())->exists();
            if ($autre) {
                return back()->withErrors(['is_volonte' => 'La ration contient déjà un composant à volonté.']);
            }
        }

        $melange->update($validated);

        return back();
    }

    public function destroy(PlanRationnement $plan, Ration $ration, Melange $melange): RedirectResponse
    {
        $this->authorize('update', $plan);

        $melange->delete();

        return back()->with('success', 'Mélange supprimé.');
    }

    public function addAliment(Request $request, PlanRationnement $plan, Ration $ration, Melange $melange): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'aliment_id' => ['required', 'exists:aliments,id'],
            'quantite' => ['required', 'numeric', 'gt:0'],
            'is_mb' => ['boolean'],
        ]);

        $original = Aliment::findOrFail($validated['aliment_id']);
        $this->authorize('view', $original);

        $clone = $original->replicate();
        $clone->usage_aliment = 2;
        $clone->user_id = $request->user()->id;
        $clone->save();

        MelangeAliment::create([
            'melange_id' => $melange->id,
            'aliment_id' => $clone->id,
            'quantite' => $validated['quantite'] ?? null,
            'is_mb' => $validated['is_mb'] ?? false,
            'ordre' => MelangeAliment::where('melange_id', $melange->id)->max('ordre') + 1,
        ]);

        return back()->with('success', 'Aliment ajouté au mélange.');
    }

    public function updateAliment(Request $request, PlanRationnement $plan, Ration $ration, Melange $melange, MelangeAliment $melangeAliment): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'quantite' => ['required', 'numeric', 'gte:0'],
            'is_mb' => ['boolean'],
        ]);

        $melangeAliment->update($validated);

        return back();
    }

    public function updateAlimentValeurs(UpdateAlimentRequest $request, PlanRationnement $plan, Ration $ration, Melange $melange, MelangeAliment $melangeAliment): RedirectResponse
    {
        $this->authorize('update', $plan);

        /** @var Aliment|null $aliment */
        $aliment = $melangeAliment->aliment;
        if (! $aliment || $aliment->usage_aliment !== 2) {
            return back();
        }

        $validated = $request->validated();
        unset($validated['usage_aliment']);

        $aliment->update($validated);

        return back();
    }

    public function reorder(Request $request, PlanRationnement $plan, Ration $ration): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($validated['ids'] as $ordre => $id) {
            Melange::where('id', $id)->where('ration_id', $ration->id)->update(['ordre' => $ordre]);
        }

        return back();
    }

    public function reorderAliments(Request $request, PlanRationnement $plan, Ration $ration, Melange $melange): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($validated['ids'] as $ordre => $id) {
            MelangeAliment::where('id', $id)->where('melange_id', $melange->id)->update(['ordre' => $ordre]);
        }

        return back();
    }

    public function removeAliment(PlanRationnement $plan, Ration $ration, Melange $melange, MelangeAliment $melangeAliment): RedirectResponse
    {
        $this->authorize('update', $plan);

        $melangeAliment->delete();

        return back()->with('success', 'Aliment retiré du mélange.');
    }
}
