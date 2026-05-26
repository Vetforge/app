<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlimentRequest;
use App\Http\Requests\UpdateAlimentRequest;
use App\Models\Aliment;
use App\Models\User;
use App\Support\PdfClinicHeader;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class AlimentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Aliment::query()
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $request->user()->id));

        if ($request->filled('search')) {
            $this->applySearch($query, trim((string) $request->input('search')));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $aliments = $query
            ->orderByRaw('user_id IS NULL DESC, libelle0 ASC')
            ->paginate(50)
            ->withQueryString();

        $types = Aliment::query()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return Inertia::render('aliments/Index', [
            'aliments' => $aliments,
            'types' => $types,
            'filters' => $request->only('search', 'type'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('aliments/Form');
    }

    public function store(StoreAlimentRequest $request): RedirectResponse
    {
        $aliment = Aliment::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('aliments.edit', $aliment)
            ->with('success', 'Aliment créé.');
    }

    public function edit(Request $request, Aliment $aliment): Response
    {
        $this->authorize('view', $aliment);

        if ($this->canUpdateAliment($request->user(), $aliment)) {
            return Inertia::render('aliments/Form', [
                'aliment' => $aliment,
                'mode' => 'edit',
            ]);
        }

        return Inertia::render('aliments/Form', [
            'aliment' => $this->copyDraft($aliment),
            'mode' => 'copy',
            'sourceAliment' => [
                'id' => $aliment->id,
                'libelle0' => $aliment->libelle0,
                'code_inra' => $aliment->code_inra,
            ],
        ]);
    }

    public function update(UpdateAlimentRequest $request, Aliment $aliment): RedirectResponse
    {
        $this->authorize('update', $aliment);

        $aliment->update($request->validated());

        return redirect()->route('aliments.edit', $aliment)
            ->with('success', 'Aliment mis à jour.');
    }

    public function copy(Request $request, Aliment $aliment): RedirectResponse
    {
        $this->authorize('view', $aliment);

        $copy = $aliment->replicate();
        $copy->user_id = $request->user()->id;
        $copy->code_inra = null;
        $copy->libelle0 = 'Copie de '.$aliment->libelle0;
        $copy->save();

        return redirect()->route('aliments.edit', $copy)
            ->with('success', 'Aliment copié. Vous pouvez maintenant le modifier.');
    }

    public function destroy(Aliment $aliment): RedirectResponse
    {
        $this->authorize('delete', $aliment);

        $aliment->delete();

        return redirect()->route('aliments.index')
            ->with('success', 'Aliment supprimé.');
    }

    public function pdf(Request $request, Aliment $aliment): \Illuminate\Http\Response
    {
        $this->authorize('view', $aliment);

        return Pdf::view('pdf.aliment', [
            'aliment' => $aliment,
            'clinicHeader' => PdfClinicHeader::forUser($request->user()),
        ])
            ->format(Format::A4)
            ->margins(top: 10, right: 8, bottom: 10, left: 8, unit: 'mm')
            ->name('aliment-'.str($aliment->libelle0)->slug().'.pdf')
            ->download()
            ->toResponse($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function copyDraft(Aliment $aliment): array
    {
        $copy = $aliment->replicate();
        $copy->code_inra = null;
        $copy->libelle0 = 'Copie de '.$aliment->libelle0;

        return collect($copy->attributesToArray())
            ->except(['id', 'user_id', 'created_at', 'updated_at'])
            ->all();
    }

    private function canUpdateAliment(?Authenticatable $user, Aliment $aliment): bool
    {
        return $user instanceof User && ($user->is_admin || $user->can('update', $aliment));
    }

    private function applySearch(Builder $query, string $search): void
    {
        $tokens = array_values(array_filter(preg_split('/\s+/', $search) ?: []));

        if ($tokens === []) {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($tokens): void {
            foreach ($tokens as $token) {
                $like = '%'.mb_strtolower($token).'%';

                $searchQuery->where(function (Builder $tokenQuery) use ($like): void {
                    $tokenQuery
                        ->whereRaw("LOWER(COALESCE(libelle0, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(libelle1, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(type, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(code_inra, '')) LIKE ?", [$like]);
                });
            }
        });
    }
}
