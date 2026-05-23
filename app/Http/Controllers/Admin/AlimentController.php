<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aliment;
use App\Models\User;
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
            $query->where('libelle0', 'ilike', '%'.$request->search.'%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $aliments = $query->orderBy('libelle0')->paginate(100)->withQueryString();
        $types = Aliment::query()->select('type')->distinct()->orderBy('type')->pluck('type');
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code_inra' => ['nullable', 'string', 'unique:aliments,code_inra'],
            'user_id' => ['nullable', 'exists:users,id'],
            'libelle0' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);

        Aliment::create($validated);

        return redirect()->route('admin.aliments.index')->with('success', 'Aliment créé.');
    }

    public function edit(Aliment $aliment): Response
    {
        return Inertia::render('admin/aliments/Form', [
            'aliment' => $aliment,
        ]);
    }

    public function update(Request $request, Aliment $aliment): RedirectResponse
    {
        $aliment->update($request->except('code_inra'));

        return back()->with('success', 'Aliment mis à jour.');
    }

    public function destroy(Aliment $aliment): RedirectResponse
    {
        $aliment->delete();

        return redirect()->route('admin.aliments.index')->with('success', 'Aliment supprimé.');
    }
}
