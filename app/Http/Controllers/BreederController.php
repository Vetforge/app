<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportBreedersRequest;
use App\Http\Requests\StoreBreederRequest;
use App\Http\Requests\UpdateBreederRequest;
use App\Models\Breeder;
use App\Services\BreederImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BreederController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Breeder::query()
            ->where('user_id', $request->user()->id)
            ->withCount('analyses');

        if ($request->filled('search')) {
            $this->applySearch($query, trim((string) $request->input('search')));
        }

        $breeders = $query
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('breeders/Index', [
            'breeders' => $breeders,
            'filters' => $request->only('search'),
            'exampleCsvUrl' => route('breeders.import-example'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('breeders/Form');
    }

    public function store(StoreBreederRequest $request): RedirectResponse
    {
        $breeder = Breeder::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('breeders.edit', $breeder)
            ->with('success', 'Eleveur cree.');
    }

    public function quickStore(StoreBreederRequest $request): JsonResponse
    {
        $breeder = Breeder::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'breeder' => $this->breederOption($breeder),
        ], 201);
    }

    public function edit(Request $request, Breeder $breeder): Response
    {
        $this->ensureOwned($request, $breeder);

        return Inertia::render('breeders/Form', [
            'breeder' => $breeder,
        ]);
    }

    public function update(UpdateBreederRequest $request, Breeder $breeder): RedirectResponse
    {
        $breeder->update($request->validated());

        return redirect()->route('breeders.edit', $breeder)
            ->with('success', 'Eleveur mis a jour.');
    }

    public function destroy(Request $request, Breeder $breeder): RedirectResponse
    {
        $this->ensureOwned($request, $breeder);
        $breeder->delete();

        return redirect()->route('breeders.index')
            ->with('success', 'Eleveur supprime.');
    }

    public function importCsv(ImportBreedersRequest $request, BreederImporter $importer): RedirectResponse
    {
        $result = $importer->import($request->user(), $request->file('file'));

        return redirect()->route('breeders.index')
            ->with('success', "{$result['created']} eleveur(s) crees, {$result['updated']} mis a jour, {$result['skipped']} ignores.")
            ->with('import_errors', $result['errors']);
    }

    public function importExample(): StreamedResponse
    {
        $rows = [
            ['nom', 'adresse', 'code_postal', 'ville', 'telephone', 'email', 'numero_cheptel', 'notes'],
            ['GAEC du Val', '12 route des Pres', '15000', 'Aurillac', '0102030405', 'contact@gaec-du-val.test', 'FR12345678', 'Client laitier'],
            ['EARL Bellevue', '4 chemin du Moulin', '12000', 'Rodez', '0601020304', 'earl-bellevue@example.test', 'FR87654321', 'Suivi veaux'],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';', '"', '\\');
            }

            fclose($handle);
        }, 'exemple-eleveurs.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function ensureOwned(Request $request, Breeder $breeder): void
    {
        abort_unless($breeder->user_id === $request->user()->id, 404);
    }

    /**
     * @return array{id: int, name: string, city: string|null, herd_number: string|null}
     */
    private function breederOption(Breeder $breeder): array
    {
        return [
            'id' => $breeder->id,
            'name' => $breeder->name,
            'city' => $breeder->city,
            'herd_number' => $breeder->herd_number,
        ];
    }

    private function applySearch(Builder $query, string $search): void
    {
        $tokens = array_values(array_filter(preg_split('/\s+/', $search) ?: []));

        if ($tokens === []) {
            return;
        }

        $il = DB::getDriverName() === 'pgsql'
            ? fn (string $col) => "unaccent(COALESCE({$col}, '')) ILIKE unaccent(?)"
            : fn (string $col) => "LOWER(COALESCE({$col}, '')) LIKE LOWER(?)";

        $query->where(function (Builder $searchQuery) use ($tokens, $il): void {
            foreach ($tokens as $token) {
                $v = ["%{$token}%"];

                $searchQuery->where(function (Builder $tokenQuery) use ($il, $v): void {
                    $tokenQuery
                        ->whereRaw($il('name'), $v)
                        ->orWhereRaw($il('city'), $v)
                        ->orWhereRaw($il('herd_number'), $v)
                        ->orWhereRaw($il('email'), $v);
                });
            }
        });
    }
}
