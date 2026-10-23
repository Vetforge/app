<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TypeAliment;
use App\Http\Requests\CalculateAgrinirRequest;
use App\Http\Requests\StoreAgrinirAlimentRequest;
use App\Models\Aliment;
use App\Models\Breeder;
use App\Services\Agrinir\ForageCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgrinirController extends Controller
{
    public function show(Request $request): Response
    {
        $referenceAliments = Aliment::query()
            ->whereNotNull('code_inra')
            ->orderBy('libelle0')
            ->orderBy('libelle1')
            ->get(['id', 'code_inra', 'type', 'libelle0', 'libelle1'])
            ->map(static fn (Aliment $aliment): array => [
                'id' => $aliment->id,
                'code_inra' => $aliment->code_inra,
                'type' => $aliment->type,
                'libelle0' => $aliment->libelle0,
                'libelle1' => $aliment->libelle1,
                'label' => collect([$aliment->libelle0, $aliment->libelle1])->filter()->implode(' - '),
            ])
            ->values()
            ->all();

        return Inertia::render('agrinir/Show', [
            'referenceAliments' => $referenceAliments,
            'breeders' => Breeder::query()->where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name', 'city', 'herd_number']),
            'quickBreederStoreUrl' => route('breeders.quick-store'),
        ]);
    }

    public function calculer(CalculateAgrinirRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $referenceAliment = $this->referenceAlimentFromValidated($validated);
        $params = $this->withReferenceMinerals($validated['params'], $referenceAliment);

        try {
            $resultats = ForageCalculator::calculer2018($validated['type'], $params);
            $resultats2007 = ForageCalculator::calculer2007($validated['type'], $params);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'resultats' => $resultats,
            'resultats2007' => $resultats2007,
        ]);
    }

    public function types(string $inra, Request $request): JsonResponse
    {
        $famille = $request->query('famille', '');
        $groups = ForageCalculator::typesForFamille($famille);

        return response()->json(['groups' => $groups]);
    }

    public function sauvegarder(StoreAgrinirAlimentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $referenceAliment = $this->referenceAlimentFromValidated($validated);
        $params = $this->withReferenceMinerals($validated['params'], $referenceAliment);
        $resultats2018 = ForageCalculator::calculer2018($validated['type'], $params);
        $resultats2007 = ForageCalculator::calculer2007($validated['type'], $params);

        $aliment = $referenceAliment?->replicate() ?? new Aliment;

        $aliment->user_id = $request->user()->id;
        $aliment->code_inra = null;
        $aliment->breeder_id = $validated['breeder_id'] ?? null;
        $aliment->libelle1 = $validated['nom'];

        if (! isset($validated['aliment_de_reference_id'])) {
            $aliment->libelle0 = $this->defaultLibelle0($validated['type']);
        }

        $aliment->type = $validated['type'] === 'luzerneD' ? TypeAliment::Concentre->value : TypeAliment::Fourrage->value;
        $aliment->famille_botanique = match (true) {
            str_starts_with($validated['type'], 'luzerne') => 'luzerne',
            str_starts_with($validated['type'], 'mais') => 'mais',
            default => 'graminee',
        };
        $aliment->procede_technologique = match (true) {
            $validated['type'] === 'luzerneD' => 'deshydrate',
            str_contains($validated['type'], 'FV') => 'vert',
            str_contains($validated['type'], 'F1'), str_contains($validated['type'], 'F2'), $validated['type'] === 'luzerneF' => 'foin',
            default => 'ensile',
        };

        $aliment->fill($this->agriNirValues($resultats2018, $resultats2007, $referenceAliment));
        $aliment->save();

        return redirect()->route('aliments.index')
            ->with('success', "Aliment « {$validated['nom']} » créé depuis AgriNIR.");
    }

    private function defaultLibelle0(string $type): string
    {
        if ($type === 'luzerneD') {
            return 'Fourrages déshydratés et agglomérés';
        }

        return match (true) {
            str_contains($type, 'E') => 'Ensilages',
            str_contains($type, 'F1'), str_contains($type, 'F2'), $type === 'luzerneF' => 'Foins',
            str_contains($type, 'FV'), $type === 'luzerneFV' => 'Fourrages verts',
            default => 'Fourrages',
        };
    }

    /**
     * @param  array<string, mixed>  $values2018
     * @param  array<string, mixed>  $values2007
     * @return array<string, mixed>
     */
    private function agriNirValues(array $values2018, array $values2007, ?Aliment $referenceAliment): array
    {
        return [
            'ms' => $values2018['ms'] ?? null,
            'mat' => $values2018['mat'] ?? null,
            'ndf' => $values2018['ndf'] ?? null,
            'adf' => $values2018['adf'] ?? null,
            'mo' => $values2018['mo'] ?? null,
            'cb' => $values2018['cb'] ?? null,
            'eb' => $values2018['eb'] ?? null,
            'em' => $values2018['em'] ?? null,
            'd_mo' => $values2018['dmo'] ?? null,
            'd_e' => $values2018['de'] ?? null,
            'niref' => $values2018['niref'] ?? null,
            'dt_n' => $values2018['dt_n'] ?? null,
            'dt6_n' => $values2018['dt6_n'] ?? null,
            'ag' => $values2018['ag'] ?? null,
            'ee' => $values2018['ee'] ?? null,
            'pf' => $values2018['pf'] ?? null,
            'amidon' => $values2018['amidon'] ?? $referenceAliment?->amidon,
            'dt6_ami' => $values2018['dt6_ami'] ?? $referenceAliment?->dt6_ami,
            'dt_ami' => $values2018['dt_ami'] ?? $referenceAliment?->dt_ami,
            'dr_n' => $values2018['dr_n'] ?? null,
            'ufl' => $values2018['ufl'] ?? null,
            'ufv' => $values2018['ufv'] ?? null,
            'pdia' => $values2018['pdia'] ?? null,
            'pdi' => $values2018['pdi'] ?? null,
            'bpr' => $values2018['bpr'] ?? null,
            'uel' => $values2018['uel'] ?? null,
            'uem' => $values2018['uem'] ?? null,
            'ueb' => $values2018['ueb'] ?? null,
            'ca' => $values2018['ca'] ?? null,
            'caabs' => $values2018['caabs'] ?? null,
            'p' => $values2018['p'] ?? null,
            'pabs' => $values2018['pabs'] ?? null,
            'mg' => $values2018['mg'] ?? null,
            'ufl2007' => $values2007['ufl2007'] ?? null,
            'ufv2007' => $values2007['ufv2007'] ?? null,
            'pdia2007' => $values2007['pdia2007'] ?? null,
            'pdie2007' => $values2007['pdie2007'] ?? null,
            'pdin2007' => $values2007['pdin2007'] ?? null,
            'd_mo2007' => $values2007['dmo2007'] ?? null,
            'd_ma2007' => $values2007['dma2007'] ?? null,
            'uem2007' => $values2007['uem2007'] ?? null,
            'uel2007' => $values2007['uel2007'] ?? null,
            'ueb2007' => $values2007['ueb2007'] ?? null,
        ];
    }

    private function referenceAliment(int $id): Aliment
    {
        return Aliment::query()
            ->whereKey($id)
            ->whereNotNull('code_inra')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function referenceAlimentFromValidated(array $validated): ?Aliment
    {
        if (! isset($validated['aliment_de_reference_id'])) {
            return null;
        }

        return $this->referenceAliment((int) $validated['aliment_de_reference_id']);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function withReferenceMinerals(array $params, ?Aliment $referenceAliment): array
    {
        if ($referenceAliment === null) {
            return $params;
        }

        foreach (['ca', 'p', 'mg'] as $field) {
            if (($params[$field] ?? null) === null) {
                $params[$field] = $referenceAliment->{$field} === null ? null : (float) $referenceAliment->{$field} / 10.0;
            }
        }

        return $params;
    }
}
