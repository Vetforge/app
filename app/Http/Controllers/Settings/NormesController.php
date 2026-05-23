<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserNormesRequest;
use App\Support\RationNormes;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NormesController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Normes', [
            'normes' => RationNormes::payloadForUser(request()->user()),
        ]);
    }

    public function update(UpdateUserNormesRequest $request): RedirectResponse
    {
        $request->user()->update([
            'normes_personnalisees' => RationNormes::storeableOverrides($request->validated('normes')),
        ]);

        return back()->with('success', 'Normes mises à jour.');
    }
}
