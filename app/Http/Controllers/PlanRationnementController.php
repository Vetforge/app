<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanRationnementRequest;
use App\Http\Requests\UpdatePlanRationnementRequest;
use App\Models\Breeder;
use App\Models\PlanRationnement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlanRationnementController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PlanRationnement::query()
            ->where('user_id', $request->user()->id)
            ->with('breeder:id,name,city,herd_number')
            ->withCount('rations');

        if ($request->filled('search')) {
            $this->applySearch($query, trim((string) $request->input('search')));
        }

        $plans = $query
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('plans/Index', [
            'plans' => Inertia::scroll($plans),
            'filters' => $request->only('search'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('plans/Form', [
            'breeders' => $this->breederOptions($request),
            'quickBreederStoreUrl' => route('breeders.quick-store'),
        ]);
    }

    public function store(StorePlanRationnementRequest $request): RedirectResponse
    {
        $plan = PlanRationnement::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('plans.show', $plan)
            ->with('success', 'Plan créé avec succès.');
    }

    public function show(PlanRationnement $plan): Response
    {
        $this->authorize('view', $plan);

        $plan->load([
            'breeder:id,name,city,herd_number',
            'rations' => fn ($q) => $q->orderBy('nom'),
        ]);

        return Inertia::render('plans/Show', [
            'plan' => $plan,
        ]);
    }

    public function edit(Request $request, PlanRationnement $plan): Response
    {
        $this->authorize('update', $plan);

        return Inertia::render('plans/Form', [
            'plan' => $plan,
            'breeders' => $this->breederOptions($request),
            'quickBreederStoreUrl' => route('breeders.quick-store'),
        ]);
    }

    public function update(UpdatePlanRationnementRequest $request, PlanRationnement $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $plan->update($request->validated());

        return redirect()->route('plans.show', $plan)
            ->with('success', 'Plan mis à jour.');
    }

    public function destroy(PlanRationnement $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()->route('plans.index')
            ->with('success', 'Plan supprimé.');
    }

    /**
     * @return array<int, array{id: int, name: string, city: string|null, herd_number: string|null}>
     */
    private function breederOptions(Request $request): array
    {
        return Breeder::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'herd_number'])
            ->map(fn (Breeder $breeder): array => [
                'id' => $breeder->id,
                'name' => $breeder->name,
                'city' => $breeder->city,
                'herd_number' => $breeder->herd_number,
            ])
            ->all();
    }

    private function applySearch(Builder $query, string $search): void
    {
        $tokens = array_values(array_filter(preg_split('/\s+/', $search) ?: []));

        if ($tokens === []) {
            return;
        }

        $dateSearchSql = $this->dateSearchSql($query, 'date');

        $query->where(function (Builder $searchQuery) use ($dateSearchSql, $tokens): void {
            foreach ($tokens as $token) {
                $like = '%'.mb_strtolower($token).'%';

                $searchQuery->where(function (Builder $tokenQuery) use ($dateSearchSql, $like): void {
                    $tokenQuery
                        ->whereRaw("LOWER(COALESCE(nom, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(inra, '')) LIKE ?", [$like])
                        ->orWhereRaw($dateSearchSql, [$like])
                        ->orWhereHas('breeder', function (Builder $breederQuery) use ($like): void {
                            $breederQuery
                                ->whereRaw("LOWER(COALESCE(name, '')) LIKE ?", [$like])
                                ->orWhereRaw("LOWER(COALESCE(city, '')) LIKE ?", [$like])
                                ->orWhereRaw("LOWER(COALESCE(herd_number, '')) LIKE ?", [$like]);
                        });
                });
            }
        });
    }

    private function dateSearchSql(Builder $query, string $column): string
    {
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);

        return match (DB::getDriverName()) {
            'pgsql' => "LOWER(COALESCE(TO_CHAR({$wrappedColumn}, 'YYYY-MM-DD'), '')) LIKE ?",
            'sqlite' => "LOWER(COALESCE(CAST({$wrappedColumn} AS TEXT), '')) LIKE ?",
            'sqlsrv' => "LOWER(COALESCE(CONVERT(varchar(10), {$wrappedColumn}, 23), '')) LIKE ?",
            default => "LOWER(COALESCE(CAST({$wrappedColumn} AS CHAR), '')) LIKE ?",
        };
    }
}
