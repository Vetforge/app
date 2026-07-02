<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Melange;
use App\Models\Ration;
use App\Models\User;
use App\Support\SearchTerm;
use App\Support\VeterinaryModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $this->userQueryWithUsageCounts()
            ->orderBy('name')
            ->orderBy('email');

        if ($request->filled('search')) {
            $search = SearchTerm::likeContains(mb_strtolower(trim((string) $request->input('search'))));

            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$search])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$search]);
            });
        }

        return Inertia::render('admin/users/Index', [
            'users' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only('search'),
            'totals' => [
                'users' => User::query()->count(),
                'admins' => User::query()->where('is_admin', true)->count(),
            ],
        ]);
    }

    public function show(User $user): Response
    {
        $user = $this->userQueryWithUsageCounts()
            ->whereKey($user->id)
            ->firstOrFail();

        $moduleDefinitions = VeterinaryModules::all();
        $analysisModules = Analysis::query()
            ->select('module')
            ->selectRaw('COUNT(*) as total')
            ->where('user_id', $user->id)
            ->groupBy('module')
            ->orderBy('module')
            ->get()
            ->map(fn (Analysis $analysis): array => [
                'module' => $analysis->module,
                'label' => $moduleDefinitions[$analysis->module]['short_label'] ?? $analysis->module,
                'count' => (int) $analysis->getAttribute('total'),
            ])
            ->values();

        return Inertia::render('admin/users/Show', [
            'user' => $user,
            'analysisModules' => $analysisModules,
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

    /**
     * @return Builder<User>
     */
    private function userQueryWithUsageCounts(): Builder
    {
        return User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.email_verified_at',
                'users.last_login_at',
                'users.is_admin',
                'users.created_at',
                'users.updated_at',
            ])
            ->withCount([
                'aliments',
                'analyses',
                'breeders',
                'moduleSettings',
                'planRationnements',
            ])
            ->addSelect([
                'rations_count' => Ration::query()
                    ->selectRaw('COUNT(*)')
                    ->join('plan_rationnements', 'plan_rationnements.id', '=', 'rations.plan_rationnement_id')
                    ->whereColumn('plan_rationnements.user_id', 'users.id'),
                'melanges_count' => Melange::query()
                    ->selectRaw('COUNT(*)')
                    ->join('rations', 'rations.id', '=', 'melanges.ration_id')
                    ->join('plan_rationnements', 'plan_rationnements.id', '=', 'rations.plan_rationnement_id')
                    ->whereColumn('plan_rationnements.user_id', 'users.id'),
            ]);
    }
}
