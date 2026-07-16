<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CategorieAnimal;
use App\Http\Requests\StoreRationRequest;
use App\Http\Requests\UpdateAlimentRequest;
use App\Http\Requests\UpdateRationDescriptionRequest;
use App\Models\Aliment;
use App\Models\Melange;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2007\Apport as Apport2007;
use App\Services\Equations2007\Besoin as Besoin2007;
use App\Services\Equations2018\Apport as Apport2018;
use App\Services\Equations2018\Besoin as Besoin2018;
use App\Services\RationCalculator;
use App\Support\PdfClinicHeader;
use App\Support\RationNormes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class RationController extends Controller
{
    public function create(PlanRationnement $plan): Response
    {
        $this->authorize('view', $plan);

        return Inertia::render('rations/Create', [
            'plan' => $plan,
            'categorie_options' => CategorieAnimal::optionsGroupedBySpecies(),
        ]);
    }

    public function store(StoreRationRequest $request, PlanRationnement $plan): RedirectResponse
    {
        $this->authorize('view', $plan);

        $ration = Ration::create([
            ...$request->validated(),
            'plan_rationnement_id' => $plan->id,
        ]);

        return redirect()->route('plans.rations.description', [$plan, $ration])
            ->with('success', 'Ration créée.');
    }

    public function description(PlanRationnement $plan, Ration $ration): Response
    {
        $this->authorize('view', $plan);

        return Inertia::render('rations/Description', [
            'plan' => $plan,
            'ration' => $ration,
            'categorie_options' => CategorieAnimal::optionsGroupedBySpecies(),
        ]);
    }

    public function updateDescription(UpdateRationDescriptionRequest $request, PlanRationnement $plan, Ration $ration): RedirectResponse
    {
        $this->authorize('update', $plan);

        $ration->update($request->validated());

        return redirect()->route('plans.rations.composition', [$plan, $ration])
            ->with('success', 'Description mise à jour.');
    }

    public function composition(PlanRationnement $plan, Ration $ration): Response
    {
        $this->authorize('view', $plan);

        return Inertia::render('rations/Composition', $this->workspacePayload($plan, $ration));
    }

    public function addAliment(Request $request, PlanRationnement $plan, Ration $ration): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'aliment_id' => ['required', 'integer', 'exists:aliments,id'],
            'quantite' => ['nullable', 'required_unless:is_volonte,true', 'numeric', 'gt:0'],
            'is_volonte' => ['boolean'],
            'is_mb' => ['boolean'],
        ]);

        $original = Aliment::query()->whereKey($validated['aliment_id'])->firstOrFail();
        $this->authorize('view', $original);

        if (($validated['is_volonte'] ?? false) && $original->type !== 'Fourrage') {
            return back()->withErrors(['is_volonte' => 'Seul un fourrage peut être distribué à volonté.']);
        }
        if (($validated['is_volonte'] ?? false) && $this->rationPossedeDejaUnComposantAVolonte($ration)) {
            return back()->withErrors(['is_volonte' => 'La ration contient déjà un composant à volonté.']);
        }

        $clone = $original->replicate();
        $clone->usage_aliment = 2;
        $clone->user_id = $request->user()->id;
        $clone->save();

        RationAliment::create([
            'ration_id' => $ration->id,
            'aliment_id' => $clone->id,
            'quantite' => $validated['quantite'] ?? null,
            'is_volonte' => $validated['is_volonte'] ?? false,
            'is_mb' => $validated['is_mb'] ?? false,
            'ordre' => RationAliment::where('ration_id', $ration->id)->max('ordre') + 1,
        ]);

        return back()->with('success', 'Aliment ajouté.');
    }

    public function updateAlimentValeurs(UpdateAlimentRequest $request, PlanRationnement $plan, Ration $ration, RationAliment $rationAliment): RedirectResponse
    {
        $this->authorize('update', $plan);

        /** @var Aliment|null $aliment */
        $aliment = $rationAliment->aliment;
        if (! $aliment || $aliment->usage_aliment !== 2) {
            return back();
        }

        $validated = $request->validated();
        unset($validated['usage_aliment']);

        $aliment->update($validated);

        return back();
    }

    public function updateAliment(Request $request, PlanRationnement $plan, Ration $ration, RationAliment $rationAliment): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'quantite' => ['nullable', 'required_unless:is_volonte,true', 'numeric', 'gte:0'],
            'is_volonte' => ['boolean'],
            'is_mb' => ['boolean'],
        ]);

        if (($validated['is_volonte'] ?? false) && $rationAliment->aliment?->type !== 'Fourrage') {
            return back()->withErrors(['is_volonte' => 'Seul un fourrage peut être distribué à volonté.']);
        }
        if (($validated['is_volonte'] ?? false) && $this->rationPossedeDejaUnComposantAVolonte($ration, $rationAliment)) {
            return back()->withErrors(['is_volonte' => 'La ration contient déjà un composant à volonté.']);
        }

        $rationAliment->update($validated);

        return back();
    }

    public function reorderAliments(Request $request, PlanRationnement $plan, Ration $ration): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($validated['ids'] as $ordre => $id) {
            RationAliment::where('id', $id)->where('ration_id', $ration->id)->update(['ordre' => $ordre]);
        }

        return back();
    }

    public function removeAliment(PlanRationnement $plan, Ration $ration, RationAliment $rationAliment): RedirectResponse
    {
        $this->authorize('update', $plan);

        $rationAliment->delete();

        return back()->with('success', 'Aliment retiré.');
    }

    public function resultats(PlanRationnement $plan, Ration $ration): Response
    {
        $this->authorize('view', $plan);

        return Inertia::render('rations/Resultats', $this->workspacePayload($plan, $ration));
    }

    public function pdf(PlanRationnement $plan, Ration $ration): \Illuminate\Http\Response
    {
        $this->authorize('view', $plan);

        $ration->load([
            'rationAliments.aliment',
            'melanges.melangeAliments.aliment',
            'planRationnement',
        ]);

        $iterationsVolonte = $this->calculerVolonte($ration);
        $resultats = RationCalculator::calculer($ration);

        return Pdf::view('pdf.ration', [
            'plan' => $plan,
            'ration' => $ration,
            'resultats' => $resultats,
            'iterations_volonte' => $iterationsVolonte,
            'normes' => RationNormes::payloadForUser(request()->user()),
            'clinicHeader' => PdfClinicHeader::forUser(request()->user()),
        ])
            ->format(Format::A4)
            ->margins(top: 12, right: 10, bottom: 12, left: 10)
            ->name('ration-'.str($plan->nom.'-'.$ration->nom)->slug().'.pdf')
            ->download()
            ->toResponse(request());
    }

    /**
     * @return array<string, mixed>
     */
    private function workspacePayload(PlanRationnement $plan, Ration $ration): array
    {
        $ration->load([
            'rationAliments.aliment',
            'melanges.melangeAliments.aliment',
            'planRationnement',
        ]);

        $iterationsVolonte = $this->calculerVolonte($ration);
        $resultats = RationCalculator::calculer($ration);
        $categorie = $ration->categorie();

        return [
            'plan' => $plan,
            'ration' => $ration,
            'aliments_disponibles' => $this->alimentsDisponibles(),
            'resultats' => $resultats,
            'iterations_volonte' => $iterationsVolonte,
            'normes' => RationNormes::payloadForUser(request()->user()),
            'categorie_meta' => [
                'value' => $categorie->value,
                'label' => $categorie->label(),
                'espece' => $categorie->espece()->value,
                'espece_label' => $categorie->espece()->label(),
                'est_laitiere' => $categorie->estLaitiere(),
                'est_croissance' => $categorie->estEnCroissance(),
                'est_implementee' => $categorie->estImplementee(),
                'unite_encombrement' => $categorie->uniteEncombrementLabel(),
                'unite_fourragere' => $categorie->uniteFourragereLabel(),
            ],
        ];
    }

    private function alimentsDisponibles(): Collection
    {
        return Aliment::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', request()->user()->id))
            ->where(fn ($query) => $query->whereNull('usage_aliment')->orWhere('usage_aliment', '!=', 2))
            ->orderBy('libelle0')
            ->get(['id', 'libelle0', 'libelle1', 'type', 'ufl', 'ufv', 'ms']);
    }

    /**
     * Algorithme d'ajustement "à volonté".
     * Ajuste la quantité d'un seul composant (aliment ou mélange) pour atteindre la capacité d'ingestion.
     * En 2018, le calcul démarre à 100 puis décrémente ; en 2007, il démarre à 0 puis incrémente.
     */
    private function calculerVolonte(Ration $ration): int
    {
        $inra = $ration->planRationnement->inra ?? '2018';

        /** @var array<RationAliment|Melange> $composants */
        $composants = [
            ...$ration->rationAliments->all(),
            ...$ration->melanges->all(),
        ];

        foreach ($composants as $composant) {
            if (! $composant->is_volonte) {
                continue;
            }

            $compteur = 0;

            if ($inra === '2018') {
                $compteur = $this->ajusterVolonte2018($ration, $composant);
            } else {
                $composant->quantite = 0.0;
                Apport2007::precompute($ration);
                $apportUE = Apport2007::calculerApportTotalUE($ration);
                $capaciteI = Besoin2007::calculerCapaciteIngestion($ration);
                while ($apportUE < $capaciteI) {
                    $delta = $capaciteI - $apportUE;
                    if ($delta > 3) {
                        $composant->quantite += 1;
                    } elseif ($delta > 2) {
                        $composant->quantite += 0.1;
                    } elseif ($delta > 1) {
                        $composant->quantite += 0.01;
                    } elseif ($delta > 0.1) {
                        $composant->quantite += 0.001;
                    } else {
                        $composant->quantite += 0.0001;
                    }
                    if (++$compteur > 1000) {
                        break;
                    }
                    Apport2007::precompute($ration);
                    $apportUE = Apport2007::calculerApportTotalUE($ration);
                    $capaciteI = Besoin2007::calculerCapaciteIngestion($ration);
                }
                Apport2007::clearCache();
            }

            $composant->quantite = max(0.0, round((float) $composant->quantite, 4));
            $composant->save();

            return $compteur; // Un seul à volonté à la fois
        }

        return 0;
    }

    private function rationPossedeDejaUnComposantAVolonte(Ration $ration, ?RationAliment $ignore = null): bool
    {
        $alimentExiste = $ration->rationAliments()
            ->where('is_volonte', true)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->exists();

        return $alimentExiste || $ration->melanges()->where('is_volonte', true)->exists();
    }

    private function ajusterVolonte2018(Ration $ration, RationAliment|Melange $composant): int
    {
        $iterations = 0;
        $minimum = 0.0;
        $maximum = 100.0;
        $meilleureQuantite = $minimum;
        $meilleurEcart = null;

        try {
            $composant->quantite = $minimum;
            $ecartMinimum = $this->ecartIngestion2018($ration);
            if ($ecartMinimum >= 0.0) {
                return $iterations;
            }

            $meilleurEcart = $ecartMinimum;
            $composant->quantite = $maximum;
            $ecartMaximum = $this->ecartIngestion2018($ration);
            $iterations++;

            if ($ecartMaximum <= 0.0) {
                return $iterations;
            }

            while ($iterations < 80 && ($maximum - $minimum) > 0.0001) {
                $milieu = ($minimum + $maximum) / 2;
                $composant->quantite = $milieu;
                $ecart = $this->ecartIngestion2018($ration);
                $iterations++;

                if (abs($ecart) <= 0.001) {
                    $meilleureQuantite = $milieu;
                    $meilleurEcart = $ecart;
                    break;
                }

                if ($ecart <= 0.0 && abs($ecart) < abs($meilleurEcart)) {
                    $meilleureQuantite = $milieu;
                    $meilleurEcart = $ecart;
                }

                if ($ecart > 0.0) {
                    $maximum = $milieu;
                } else {
                    $minimum = $milieu;
                }
            }

            $composant->quantite = $meilleureQuantite;

            return $iterations;
        } finally {
            Apport2018::clearCache();
        }
    }

    private function ecartIngestion2018(Ration $ration): float
    {
        Apport2018::precompute($ration);

        return Apport2018::calculerApportTotalUE($ration) - Besoin2018::calculerCapaciteIngestion($ration);
    }

    public function destroy(PlanRationnement $plan, Ration $ration): RedirectResponse
    {
        $this->authorize('update', $plan);

        $ration->delete();

        return redirect()->route('plans.show', $plan)
            ->with('success', 'Ration supprimée.');
    }
}
