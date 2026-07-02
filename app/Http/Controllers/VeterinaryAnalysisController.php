<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnalysisRequest;
use App\Http\Requests\UpdateAnalysisRequest;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Services\VeterinaryAnalysisCalculator;
use App\Support\PdfClinicHeader;
use App\Support\SearchTerm;
use App\Support\VeterinaryModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class VeterinaryAnalysisController extends Controller
{
    private const BSE_MODULES = ['bse-laitier', 'bse-allaitant'];

    public function index(Request $request, string $module): Response
    {
        VeterinaryModules::assertExists($module);

        $query = Analysis::query()
            ->with('breeder:id,name,city,herd_number')
            ->where('user_id', $request->user()->id)
            ->where('module', $module);

        if ($request->filled('search')) {
            $this->applySearch($query, trim((string) $request->input('search')));
        }

        $analyses = $query
            ->latest('analyzed_at')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $moduleData = VeterinaryModules::get($module);

        return Inertia::render('analyses/Index', [
            'module' => ['slug' => $module, ...$moduleData],
            'modules' => VeterinaryModules::navigationByType($moduleData['type']),
            'analyses' => Inertia::scroll($analyses),
            'filters' => $request->only('search'),
        ]);
    }

    public function create(Request $request, string $module): Response
    {
        VeterinaryModules::assertExists($module);

        $settings = VeterinaryModules::settingsForUser($request->user(), $module);

        return Inertia::render("analyses/{$module}/Form", [
            'module' => ['slug' => $module, ...VeterinaryModules::get($module)],
            'settings' => $settings,
            'payloadTemplate' => VeterinaryModules::payloadTemplate($module, $settings),
            'breeders' => $this->breederOptions($request),
            'comparisonAnalyses' => $this->comparisonOptions($request, $module),
            'quickBreederStoreUrl' => route('breeders.quick-store'),
        ]);
    }

    public function store(StoreAnalysisRequest $request, string $module): RedirectResponse
    {
        $settings = VeterinaryModules::settingsForUser($request->user(), $module);
        $payload = $request->validated('payload');
        $analysis = Analysis::create([
            ...collect($request->validated())->except('payload')->all(),
            'user_id' => $request->user()->id,
            'module' => $module,
            'status' => 'complete',
            'payload' => $payload,
            'settings_snapshot' => $settings,
            'results' => VeterinaryAnalysisCalculator::calculate($module, $payload, $settings),
        ]);

        return redirect()->route('analyses.show', $analysis)
            ->with('success', 'Analyse creee.');
    }

    public function show(Request $request, Analysis $analysis): Response
    {
        $this->ensureOwned($request, $analysis);
        $analysis->load('breeder:id,name,address,postal_code,city,herd_number,email,phone');
        $this->refreshCalculatedBseResults($analysis);
        $comparisonAnalysis = $this->comparisonAnalysisFor($request, $analysis);

        return Inertia::render("analyses/{$analysis->module}/Show", [
            'analysis' => $analysis,
            'comparisonAnalysis' => $comparisonAnalysis,
            'module' => ['slug' => $analysis->module, ...VeterinaryModules::get($analysis->module)],
        ]);
    }

    public function edit(Request $request, Analysis $analysis): Response
    {
        $this->ensureOwned($request, $analysis);

        $settings = VeterinaryModules::settingsForUser($request->user(), $analysis->module);

        return Inertia::render("analyses/{$analysis->module}/Form", [
            'analysis' => $analysis,
            'module' => ['slug' => $analysis->module, ...VeterinaryModules::get($analysis->module)],
            'settings' => $settings,
            'payloadTemplate' => VeterinaryModules::payloadTemplate($analysis->module, $settings),
            'breeders' => $this->breederOptions($request),
            'comparisonAnalyses' => $this->comparisonOptions($request, $analysis->module, $analysis),
            'quickBreederStoreUrl' => route('breeders.quick-store'),
        ]);
    }

    public function update(UpdateAnalysisRequest $request, Analysis $analysis): RedirectResponse
    {
        $settings = VeterinaryModules::settingsForUser($request->user(), $analysis->module);
        $payload = $request->validated('payload');

        $analysis->update([
            ...collect($request->validated())->except('payload')->all(),
            'payload' => $payload,
            'settings_snapshot' => $settings,
            'results' => VeterinaryAnalysisCalculator::calculate($analysis->module, $payload, $settings),
        ]);

        return redirect()->route('analyses.show', $analysis)
            ->with('success', 'Analyse mise a jour.');
    }

    public function destroy(Request $request, Analysis $analysis): RedirectResponse
    {
        $this->ensureOwned($request, $analysis);
        $module = $analysis->module;
        $analysis->delete();

        return redirect()->route('analyses.index', ['module' => $module])
            ->with('success', 'Analyse supprimee.');
    }

    public function pdf(Request $request, Analysis $analysis): \Illuminate\Http\Response
    {
        $this->ensureOwned($request, $analysis);
        $analysis->load('breeder');
        $this->refreshCalculatedBseResults($analysis);
        $comparisonAnalysis = $this->comparisonAnalysisFor($request, $analysis);

        return Pdf::view('pdf.analysis', [
            'analysis' => $analysis,
            'comparisonAnalysis' => $comparisonAnalysis,
            'module' => VeterinaryModules::get($analysis->module),
            'clinicHeader' => PdfClinicHeader::forUser($request->user()),
        ])
            ->format(Format::A4)
            ->margins(top: 10, right: 8, bottom: 10, left: 8, unit: 'mm')
            ->name('analyse-'.$analysis->id.'.pdf')
            ->download()
            ->toResponse($request);
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

    private function ensureOwned(Request $request, Analysis $analysis): void
    {
        abort_unless($analysis->user_id === $request->user()->id, 404);
    }

    private function refreshCalculatedBseResults(Analysis $analysis): void
    {
        if (! $this->isBseModule($analysis->module)) {
            return;
        }

        $analysis->setAttribute('results', VeterinaryAnalysisCalculator::calculate(
            $analysis->module,
            $analysis->payload ?? [],
            $analysis->settings_snapshot ?? [],
        ));
    }

    /**
     * @return array<int, array{id: int, breeder_id: int, label: string}>
     */
    private function comparisonOptions(Request $request, string $module, ?Analysis $exclude = null): array
    {
        if (! $this->isBseModule($module)) {
            return [];
        }

        return Analysis::query()
            ->with('breeder:id,name,city')
            ->where('user_id', $request->user()->id)
            ->where('module', $module)
            ->when($exclude, fn (Builder $query): Builder => $query->whereKeyNot($exclude->id))
            ->latest('analyzed_at')
            ->latest()
            ->get(['id', 'breeder_id', 'analyzed_at', 'payload'])
            ->map(fn (Analysis $analysis): array => [
                'id' => $analysis->id,
                'breeder_id' => $analysis->breeder_id,
                'label' => $this->comparisonLabel($analysis),
            ])
            ->all();
    }

    private function comparisonAnalysisFor(Request $request, Analysis $analysis): ?Analysis
    {
        if (! $this->isBseModule($analysis->module)) {
            return null;
        }

        $comparisonId = $this->comparisonAnalysisId($analysis->payload ?? []);

        if ($comparisonId === null || $comparisonId === $analysis->id) {
            return null;
        }

        $comparisonAnalysis = Analysis::query()
            ->with('breeder:id,name,address,postal_code,city,herd_number,email,phone')
            ->where('user_id', $request->user()->id)
            ->where('module', $analysis->module)
            ->where('breeder_id', $analysis->breeder_id)
            ->find($comparisonId);

        if (! $comparisonAnalysis) {
            return null;
        }

        $this->refreshCalculatedBseResults($comparisonAnalysis);

        return $comparisonAnalysis;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function comparisonAnalysisId(array $payload): ?int
    {
        $value = $payload['comparison_analysis_id'] ?? null;

        if ($value === null || $value === '' || $value === 0 || $value === '0' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function comparisonLabel(Analysis $analysis): string
    {
        $date = $analysis->analyzed_at?->format('d/m/Y');
        $year = data_get($analysis->payload, 'annee_reference');
        $period = $date ?: ($year ? (string) $year : 'date non renseignee');
        $breederName = $analysis->breeder?->name ?: 'Eleveur';
        $city = $analysis->breeder?->city ? ' - '.$analysis->breeder->city : '';

        return "{$breederName}{$city} - Bilan du {$period}";
    }

    private function isBseModule(string $module): bool
    {
        return in_array($module, self::BSE_MODULES, true);
    }

    private function applySearch(Builder $query, string $search): void
    {
        $tokens = array_values(array_filter(preg_split('/\s+/', $search) ?: []));

        if ($tokens === []) {
            return;
        }

        $sampledAtSearchSql = $this->dateSearchSql($query, 'sampled_at');
        $analyzedAtSearchSql = $this->dateSearchSql($query, 'analyzed_at');

        $query->where(function (Builder $searchQuery) use ($analyzedAtSearchSql, $sampledAtSearchSql, $tokens): void {
            foreach ($tokens as $token) {
                $like = SearchTerm::likeContains(mb_strtolower($token));

                $searchQuery->where(function (Builder $tokenQuery) use ($analyzedAtSearchSql, $like, $sampledAtSearchSql): void {
                    $tokenQuery
                        ->whereRaw("LOWER(COALESCE(animal_nom, '')) LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("LOWER(COALESCE(intervenant, '')) LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw($sampledAtSearchSql, [$like])
                        ->orWhereRaw($analyzedAtSearchSql, [$like])
                        ->orWhereHas('breeder', function (Builder $breederQuery) use ($like): void {
                            $breederQuery
                                ->whereRaw("LOWER(COALESCE(name, '')) LIKE ? ESCAPE '\\'", [$like])
                                ->orWhereRaw("LOWER(COALESCE(city, '')) LIKE ? ESCAPE '\\'", [$like])
                                ->orWhereRaw("LOWER(COALESCE(herd_number, '')) LIKE ? ESCAPE '\\'", [$like]);
                        });
                });
            }
        });
    }

    private function dateSearchSql(Builder $query, string $column): string
    {
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);

        return match (DB::getDriverName()) {
            'pgsql' => "LOWER(COALESCE(TO_CHAR({$wrappedColumn}, 'YYYY-MM-DD'), '')) LIKE ? ESCAPE '\\'",
            'sqlite' => "LOWER(COALESCE(CAST({$wrappedColumn} AS TEXT), '')) LIKE ? ESCAPE '\\'",
            'sqlsrv' => "LOWER(COALESCE(CONVERT(varchar(10), {$wrappedColumn}, 23), '')) LIKE ?",
            default => "LOWER(COALESCE(CAST({$wrappedColumn} AS CHAR), '')) LIKE ?",
        };
    }
}
