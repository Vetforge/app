<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->withCount(['aliments', 'planRationnements', 'melanges'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_admin' => ['boolean'],
            'email_verified_at' => ['nullable', 'date'],
        ]);

        $user->update($validated);

        return back()->with('success', 'Utilisateur mis à jour.');
    }
}
