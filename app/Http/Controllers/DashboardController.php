<?php

namespace App\Http\Controllers;

use App\Models\Aliment;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use App\Support\VeterinaryModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $cursor = $request->query('cursor');
        $before = $cursor ? Carbon::parse($cursor) : null;

        $elements = $this->recentElements($user, $before);

        return Inertia::render('Dashboard', [
            'analysis_modules' => Inertia::once(fn () => VeterinaryModules::navigation()),
            'recent_elements' => Inertia::merge($elements['data']),
            'recent_next_cursor' => $elements['next_cursor'],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->string('q')->trim()->value();

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        /** @var User $user */
        $user = $request->user();

        /** @var string[] $terms */
        $terms = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [$q];

        $il = DB::getDriverName() === 'pgsql'
            ? fn (string $col) => "unaccent(COALESCE({$col}, '')) ILIKE unaccent(?)"
            : fn (string $col) => "LOWER(COALESCE({$col}, '')) LIKE LOWER(?)";

        $analyses = Analysis::query()
            ->with('breeder:id,name')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($il, $terms) {
                foreach ($terms as $term) {
                    $v = ["%{$term}%"];
                    $matchingSlugs = collect(VeterinaryModules::all())
                        ->filter(fn ($m) => str_contains(mb_strtolower($m['label']), mb_strtolower($term))
                            || str_contains(mb_strtolower($m['short_label']), mb_strtolower($term)))
                        ->keys()
                        ->all();
                    $query->where(function ($q2) use ($il, $v, $matchingSlugs) {
                        $q2->whereRaw($il('animal_nom'), $v)
                            ->orWhereRaw($il('intervenant'), $v);
                        if (count($matchingSlugs) > 0) {
                            $q2->orWhereIn('module', $matchingSlugs);
                        }
                        $q2->orWhereHas('breeder', fn ($q3) => $q3
                            ->whereRaw($il('name'), $v)
                            ->orWhereRaw($il('city'), $v)
                            ->orWhereRaw($il('herd_number'), $v));
                    });
                }
            })
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Analysis $a) => [
                'type' => 'analysis',
                'type_label' => 'Analyse',
                'id' => $a->id,
                'label' => data_get(VeterinaryModules::all(), "{$a->module}.short_label", $a->module).($a->animal_nom ? ' — '.$a->animal_nom : ''),
                'sub' => $a->breeder?->name,
                'updated_at' => $a->updated_at,
                'url' => route('analyses.show', $a, false),
            ]);

        $breeders = Breeder::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($il, $terms) {
                foreach ($terms as $term) {
                    $v = ["%{$term}%"];
                    $query->where(function ($q2) use ($il, $v) {
                        $q2->whereRaw($il('name'), $v)
                            ->orWhereRaw($il('city'), $v)
                            ->orWhereRaw($il('herd_number'), $v)
                            ->orWhereRaw($il('email'), $v)
                            ->orWhereRaw($il('phone'), $v);
                    });
                }
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Breeder $b) => [
                'type' => 'breeder',
                'type_label' => 'Eleveur',
                'id' => $b->id,
                'label' => $b->name,
                'sub' => collect([$b->city, $b->herd_number])->filter()->join(' · '),
                'updated_at' => $b->updated_at,
                'url' => route('breeders.edit', $b, false),
            ]);

        $plans = PlanRationnement::query()
            ->with('breeder:id,name')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($il, $terms) {
                foreach ($terms as $term) {
                    $v = ["%{$term}%"];
                    $query->where(function ($q2) use ($il, $v) {
                        $q2->whereRaw($il('nom'), $v)
                            ->orWhereHas('breeder', fn ($q3) => $q3->whereRaw($il('name'), $v));
                    });
                }
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (PlanRationnement $p) => [
                'type' => 'plan',
                'type_label' => 'Plan',
                'id' => $p->id,
                'label' => $p->nom,
                'sub' => $p->breeder?->name,
                'updated_at' => $p->updated_at,
                'url' => route('plans.show', $p, false),
            ]);

        $rations = Ration::query()
            ->with('planRationnement:id,nom')
            ->whereHas('planRationnement', fn ($q2) => $q2->where('user_id', $user->id))
            ->where(function ($query) use ($il, $terms) {
                foreach ($terms as $term) {
                    $query->whereRaw($il('nom'), ["%{$term}%"]);
                }
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Ration $r) => [
                'type' => 'ration',
                'type_label' => 'Ration',
                'id' => $r->id,
                'label' => $r->nom,
                'sub' => $r->planRationnement?->nom,
                'updated_at' => $r->updated_at,
                'url' => route('plans.rations.composition', ['plan' => $r->plan_rationnement_id, 'ration' => $r->id], false),
            ]);

        $aliments = Aliment::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($il, $terms) {
                foreach ($terms as $term) {
                    $v = ["%{$term}%"];
                    $query->where(function ($q2) use ($il, $v) {
                        $q2->whereRaw($il('libelle0'), $v)
                            ->orWhereRaw($il('libelle1'), $v)
                            ->orWhereRaw($il('libelle2'), $v);
                    });
                }
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Aliment $a) => [
                'type' => 'aliment',
                'type_label' => 'Aliment',
                'id' => $a->id,
                'label' => $a->libelle0 ?: $a->libelle1,
                'sub' => $a->type,
                'updated_at' => $a->updated_at,
                'url' => route('aliments.edit', $a, false),
            ]);

        return response()->json(
            collect($analyses->all())
                ->merge($breeders)
                ->merge($plans)
                ->merge($rations)
                ->merge($aliments)
                ->sortByDesc('updated_at')
                ->values()
        );
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null}
     */
    private function recentElements(User $user, ?Carbon $before = null): array
    {
        $perPage = 20;
        $limit = $perPage + 1;

        $filter = fn ($q) => $q->when($before, fn ($q2) => $q2->where('updated_at', '<', $before));

        $analyses = Analysis::query()
            ->with('breeder:id,name')
            ->where('user_id', $user->id)
            ->tap($filter)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Analysis $a) => [
                'type' => 'analysis',
                'type_label' => 'Analyse',
                'id' => $a->id,
                'label' => data_get(VeterinaryModules::all(), "{$a->module}.short_label", $a->module).($a->animal_nom ? ' — '.$a->animal_nom : ''),
                'sub' => $a->breeder?->name,
                'updated_at' => $a->updated_at,
                'url' => route('analyses.show', $a, false),
            ]);

        $breeders = Breeder::query()
            ->where('user_id', $user->id)
            ->tap($filter)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Breeder $b) => [
                'type' => 'breeder',
                'type_label' => 'Eleveur',
                'id' => $b->id,
                'label' => $b->name,
                'sub' => collect([$b->city, $b->herd_number])->filter()->join(' · '),
                'updated_at' => $b->updated_at,
                'url' => route('breeders.edit', $b, false),
            ]);

        $plans = PlanRationnement::query()
            ->with('breeder:id,name')
            ->where('user_id', $user->id)
            ->tap($filter)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (PlanRationnement $p) => [
                'type' => 'plan',
                'type_label' => 'Plan',
                'id' => $p->id,
                'label' => $p->nom,
                'sub' => $p->breeder?->name,
                'updated_at' => $p->updated_at,
                'url' => route('plans.show', $p, false),
            ]);

        $rations = Ration::query()
            ->with('planRationnement:id,nom')
            ->whereHas('planRationnement', fn ($q) => $q->where('user_id', $user->id))
            ->tap($filter)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Ration $r) => [
                'type' => 'ration',
                'type_label' => 'Ration',
                'id' => $r->id,
                'label' => $r->nom,
                'sub' => $r->planRationnement?->nom,
                'updated_at' => $r->updated_at,
                'url' => route('plans.rations.composition', ['plan' => $r->plan_rationnement_id, 'ration' => $r->id], false),
            ]);

        $aliments = Aliment::query()
            ->where('user_id', $user->id)
            ->tap($filter)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Aliment $a) => [
                'type' => 'aliment',
                'type_label' => 'Aliment',
                'id' => $a->id,
                'label' => $a->libelle0 ?: $a->libelle1,
                'sub' => $a->type,
                'updated_at' => $a->updated_at,
                'url' => route('aliments.edit', $a, false),
            ]);

        $all = collect($analyses->all())
            ->merge($breeders)
            ->merge($plans)
            ->merge($rations)
            ->merge($aliments)
            ->sortByDesc('updated_at');

        $page = $all->take($perPage)->values();
        $hasMore = $all->count() > $perPage;

        return [
            'data' => $page->all(),
            'next_cursor' => $hasMore ? $page->last()['updated_at']->toIso8601String() : null,
        ];
    }
}
