<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TypeAliment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAlimentRequest;
use App\Http\Requests\Admin\UpdateAlimentRequest;
use App\Models\Aliment;
use App\Models\User;
use App\Support\SearchTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlimentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Aliment::query()->with('user:id,name');

        if ($request->filled('search')) {
            $query->where('libelle0', 'ilike', SearchTerm::likeContains((string) $request->search));
        }

        if ($request->filled('type')) {
            $type = TypeAliment::tryFromLoose($request->input('type'));
            $query->where('type', $type === null ? $request->input('type') : $type->value);
            if ($type !== null) {
                $request->merge(['type' => $type->value]);
            }
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $aliments = $query->orderBy('libelle0')->paginate(100)->withQueryString();
        $types = TypeAliment::values();
        $users = User::query()->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('admin/aliments/Index', [
            'aliments' => $aliments,
            'types' => $types,
            'users' => $users,
            'filters' => $request->only('search', 'type', 'user_id'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/aliments/Form');
    }

    public function store(StoreAlimentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Aliment::create($validated);

        return redirect()->route('admin.aliments.index')->with('success', 'Aliment créé.');
    }

    public function edit(Aliment $aliment): Response
    {
        return Inertia::render('admin/aliments/Form', [
            'aliment' => $aliment,
        ]);
    }

    public function update(UpdateAlimentRequest $request, Aliment $aliment): RedirectResponse
    {
        $aliment->update($request->validated());

        return back()->with('success', 'Aliment mis à jour.');
    }

    public function destroy(Aliment $aliment): RedirectResponse
    {
        $aliment->delete();

        return redirect()->route('admin.aliments.index')->with('success', 'Aliment supprimé.');
    }
}
